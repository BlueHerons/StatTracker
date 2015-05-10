<?php
namespace BlueHerons\StatTracker;

use Exception;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use StdClass;

use Psr\Log\LogLevel;
use Katzgrau\KLogger\Logger;

class OCR {

    private $logger;
    private $stats;

    public function __construct($stats, $logger) {
        $this->logger = $logger == null ? new Logger(LOG_DIR) : $logger;
        $this->stats = $stats;
    }

    /**
     * Scans an agent profile screenshot and returns an array of stat values.
     *
     * @param string $imagePath the path to the image
     *
     * @return array array of stat key's and values from the screenshot
     */
    public function scan($filename, $async = true) {
        $this->async = $async;
        $this->sess_id = pathinfo($filename, PATHINFO_FILENAME);

        try {
            $filename = $this->prepareForOCR($filename);
            $lines = $this->executeOCR($filename);
            $data = $this->processData($lines);

            $this->sendMessage(array("stats" => $data));
            return $data;
        }
        catch (OCRException $e) {
            $this->sendMessage($e);
        }
    }

    private function log($level, $message, array $context = array()) {
        $message = sprintf("%s - %s", $this->sess_id, $message);
        $this->logger->log($level, $message, $context);
    }

    /**
     * Sends a response object to the client. This is intended to be used as part of a response that is streamed, so the
     * ouput buffer is flushed (sent to the client) immediately.
     *
     * @param mixed $thing the thing to send to the client wrapped in a response object. Can be a string, exception or object
     */
    private function sendMessage($thing) {
        if ($this->async) {
            $resp = new StdClass();
            $resp->session = $this->sess_id;
            if (is_object($thing) || is_array($thing)) {
                if ($thing instanceof Exception) {
                    $resp->error = $thing->getMessage();
                }
                else {
                    $resp = (object) array_merge((array)$resp, (array)$thing);
                }
            }
            else {
                $resp->status = $thing;
            }

            $json = json_encode($resp);
            $this->log(LogLevel::INFO, sprintf("Sending payload: %s", $json));
            echo $json;
            ob_flush();
            flush();
        }
    }

    /**
     * Converts a source image to PBM format for use with the OCRAD scanner. Also blacks out agent avatar and
     * stat labels.
     *
     * Special thanks to Eu Ji (https://plus.google.com/115412883382513227875/about) of ingress-stats.org
     *
     * @param string $imagePath the path of the image to convert.
     *
     * @return path to new PBM image
     */
    private function prepareForOCR($imagePath) {
        $newFile = UPLOAD_DIR . pathinfo($imagePath, PATHINFO_FILENAME) . ".pbm";

        try {
            $this->sendMessage("Loading screenshot...");
            $this->log(LogLevel::DEBUG, sprintf("Loading %s", $imagePath));

            $img = new Imagick();

            $quantumRange = $img->getQuantumRange();
            // Threshold in percent * quantumRange in which values higher go pure white and lower pure black
            $threshold = 0.45 * $quantumRange['quantumRangeLong'];
            // Color to compare to when looking for stat section
            $statSectionColor = new ImagickPixel('#7b6728');
            // Distance from 0-255 * quantumRange * sqrt(3) in which to consider colors similar when mapped into 3d space
            $fuzz = 35/255 * $quantumRange['quantumRangeLong'] / sqrt(3);

            $img->readImage($imagePath);
            $identify = $img->identifyImage();
            $width = $identify['geometry']['width'];
            $height = $identify['geometry']['height'];
            $x = 0; $y = 0;

            $this->sendMessage("Measuring screenshot...");
            $this->log(LogLevel::DEBUG, sprintf("Measuring %s", $imagePath));

            $this->log(LogLevel::DEBUG, sprintf("Scanner Start @ [%s,%s]", $x, $y));

            for ( ; $y < $height / 8 ; $y += 5) {
                $pixel = $img->getImagePixelColor($x, $y);
                if ($this->isBlack($pixel)) {
                    break;
                }
            }
            if ($y >= $height / 8) {
                throw new OCRException($this->sess_id, "failed to find top of Logo Box");
            }
            $logoBoxTop = $y;
            $this->log(LogLevel::DEBUG, sprintf("Scanner Logo Box Top @ [%s,%s]", $x, $y));

            for ( ; $y < $height / 4 ; $y ++) {
                $pixel = $img->getImagePixelColor($x, $y);
                if (!$this->isBlack($pixel)) {
                    break;
                }
            }
            if ($y >= $height / 4) {
                throw new OCRException($this->sess_id, "failed to find bottom of Logo Box");
            }
            $logoBoxBottom = $y - 1;
            $logoBoxHeight = $logoBoxBottom - $logoBoxTop;
            $this->log(LogLevel::DEBUG, sprintf("Scanner Logo Box Bottom @ [%s,%s]", $x, $y));


            for ( ; $x < $width / 4 ; $x += 10) {
                for ($y = $logoBoxTop ; $y < $logoBoxBottom ; $y ++) {
                    $pixel = $img->getImagePixelColor($x, $y);
                    if ($this->isLight($pixel)) {
                        break;
                    }
                }
                if ($y < $logoBoxBottom) {
                    break;
                }
            }
            if ($x >= $width / 4) {
                throw new OCRException($this->sess_id, "failed to find left of Logo");
            }
            $this->log(LogLevel::DEBUG, sprintf("Scanner Logo Left @ [%s,%s]", $x, $y));

            for ( ; $x < $width / 2 ; $x ++) {
                for ($y = $logoBoxTop ; $y < $logoBoxBottom ; $y ++) {
                    $pixel = $img->getImagePixelColor($x, $y);
                    if ($this->isLight($pixel)) {
                        break;
                    }
                }
                if ($y >= $logoBoxBottom) {
                    break;
                }
            }
            if ($x >= $width / 2) {
                throw new OCRException($this->sess_id, "failed to find right of Logo");
            }
            $apBoxLeft = $x;
            $apBoxWidth = $width - $apBoxLeft;
            $this->log(LogLevel::DEBUG, sprintf("Scanner AP Left @ [%s,%s]", $x, $y));

            for ($y = $logoBoxBottom ; $y > $logoBoxBottom - $logoBoxHeight / 4 ; $y -= 5) {
                for($x = $apBoxLeft ; $x < $apBoxLeft + $apBoxWidth / 4 ; $x ++) {
                    $pixel = $img->getImagePixelColor($x, $y);
                    if ($this->isLight($pixel)) {
                        break;
                    }
                }
                if ($x < $apBoxLeft + $apBoxWidth / 4) {
                    break;
                }
            }
            if ($y <= $logoBoxBottom - $logoBoxHeight / 4) {
                throw new OCRException($this->sess_id, "failed to find bottom of AP");
            }
            $this->log(LogLevel::DEBUG, sprintf("Scanner AP Bottom @ [%s,%s]", $x, $y));

            for ( ; $y > $logoBoxBottom - $logoBoxHeight / 2 ; $y -= 5) {
                for($x = $apBoxLeft ; $x < $apBoxLeft + $apBoxWidth / 4 ; $x ++) {
                    $pixel = $img->getImagePixelColor($x, $y);
                    if ($this->isLight($pixel)) {
                        break;
                    }
                }
                if ($x >= $apBoxLeft + $apBoxWidth / 4) {
                    break;
                }
            }
            if ($y <= $logoBoxBottom - $logoBoxHeight / 2) {
                throw new OCRException($this->sess_id, "failed to find top of AP");
            }
            $this->log(LogLevel::DEBUG, sprintf("Scanner AP Top @ [%s,%s]", $x, $y));

            for ( ; $y > $logoBoxBottom - $logoBoxHeight / 2 ; $y --) {
                for($x = $apBoxLeft ; $x < $apBoxLeft + $apBoxWidth / 4 ; $x ++) {
                    $pixel = $img->getImagePixelColor($x, $y);
                    if ($this->isLight($pixel)) {
                        break;
                    }
                }
                if ($x < $apBoxLeft + $apBoxWidth / 4) {
                    break;
                }
            }
            if ($y <= $logoBoxBottom - $logoBoxHeight / 2) {
                throw new OCRException($this->sess_id, "failed to find bottom of Progress Bar");
            }
            $this->log(LogLevel::DEBUG, sprintf("Scanner Progress Bar Bottom @ [%s,%s]", $x, $y));
            $progressBarBottom = $y + 1;

            $x = 0;
            for ($y = $logoBoxBottom ; $y < $height * 0.75 ; $y ++) {
                $pixel = $img->getImagePixelColor($x, $y);
                if ($pixel->isSimilar($statSectionColor, $fuzz)) {
                    break;
                }
            }
            if ($y >= $height * 0.75) {
                // This is non fatal, we can just not chop the image, usually this helps to make OCR faster by removing badges and mission icons
                $statsTop = false;
                $this->log(LogLevel::DEBUG, "Scanner Failed to find Stats Top");
            }
            else {
                $statsTop = $y - 1;
                $this->log(LogLevel::DEBUG, sprintf("Scanner Stats Top @ [%s,%s]", $x, $y));
            }

            $this->sendMessage("Cropping screenshot..");
            $this->log(LogLevel::DEBUG, sprintf("Cropping %s to W: %d, H: %d", $imagePath, $width, $height - $progressBarBottom));
            $img->cropImage($width, $height - $progressBarBottom, 0, $progressBarBottom);

            $this->sendMessage("Masking screenshot...");
            $this->log(LogLevel::DEBUG, sprintf("Masking %s from 0, 0 to %d, %d", $imagePath, $apBoxLeft, $logoBoxBottom - $progressBarBottom));
            $draw = new ImagickDraw();
            $draw->setFillColor('black');
            $draw->rectangle(0, 0, $apBoxLeft, $logoBoxBottom - $progressBarBottom);
            $img->drawImage($draw);

            if($statsTop !== false) {
                $this->sendMessage("Chopping screenshot...");
                $this->log(LogLevel::DEBUG, sprintf("Chopping %s from 0, %d by 0, %d", $imagePath, $logoBoxBottom - $progressBarBottom, $statsTop - $logoBoxBottom));
                $img->chopImage(0, $statsTop - $logoBoxBottom, 0, $logoBoxBottom - $progressBarBottom);
            }

            $this->sendMessage("Resizing screenshot...");
            $this->log(LogLevel::DEBUG, sprintf("Resizing %s", $imagePath));
            $img->resizeImage($width * 2, 0, imagick::FILTER_LANCZOS, 1);

            $this->sendMessage("Contrasting screenshot...");
            $this->log(LogLevel::DEBUG, sprintf("Constrasting %s", $imagePath));
            $img->thresholdImage($threshold);

            $this->sendMessage("Saving screenshot...");
            $this->log(LogLevel::DEBUG, sprintf("Saving %s", $imagePath));
            $img->writeImage($newFile);

            $this->log(LogLevel::INFO, sprintf("Completed %s", $imagePath));

            return $newFile;
        }
        catch (Exception $e) {
            copy($imagePath, $imagePath . "_errored");
            if (file_exists($newFile)) unlink($newFile);
            throw $e;
        }
        finally {
            unlink($imagePath);
        }
    }

    /**
     * Helper functions for the prepareForOCR function
     *
     * @param ImagickPixel $pixel color to evaluate
     *
     * @return boolean true if evaluation true
     */
    private function isBlack($pixel) {
        $color = $pixel->getColor();
        return ($color['r'] < 10 && $color['g'] < 10 && $color['b'] < 10);
    }

    private function isLight($pixel) {
        $color = $pixel->getColor();
        return ($color['r'] > 80 || $color['g'] > 80 || $color['b'] > 80);
    }

    /**
     * Invokes external OCR tool
     *
     * @param string $imagePath path to the image to scan
     *
     * @return array of lines read from the image
     */
    private function executeOCR($imagePath) {
        try {
            $cmd = sprintf("%s -i %s", OCRAD, $imagePath);
            $this->log(LogLevel::DEBUG, sprintf("Executing %s", $cmd));
            $this->sendMessage("Scanning screenshot...");
            exec($cmd, $lines);

            if (sizeof($lines) < 1) {
                throw new OCRException($this->sess_id, "No data was read from the uploaded image.");
            }

            $this->log(LogLevel::DEBUG, "OCR results:", $lines);
            return $lines;
        }
        catch (Exception $e) {
            copy($imagePath, $imagePath . "_errored");
            throw $e;
        }
        finally {
            unlink($imagePath);
        }
    }

    /**
     * Converts a source image to PBM format for use with the OCRAD scanner. Also blacks out agent avatar and
     * stat labels.
     *
     * Special thanks to Eu Ji (https://plus.google.com/115412883382513227875/about) of ingress-stats.org
     *
     * @param string $imagePath the path of the image to convert.
     *
     * @return path to new PBM image
     */
    private function processData($lines) {
        try {
            $this->sendMessage("Processing scanned results...");
            $step = 'start';
            $elements = array();
            foreach($lines as $line) {

                if (strlen(trim($line)) == 0) continue;

                $this->log(LogLevel::DEBUG, sprintf("Step: %s, Value: %s", $step, $line));

                if ($step == 'start' && preg_match('/^\s*([\d\s\|.egiloqt,]+)\s*AP\s*$/sxmi', $line, $values)) {
                    $step = 'ap';
                    array_push($elements, $values[1]);
                }
                elseif ($step == 'ap' && preg_match('/^\s*Discovery\s*$/sxmi', $line)) {
                    $step = 'discovery';
                    $count = 0;
                }
                // Some screenshots arent producing AP in the OCR results, which throws this off.
                else if ($step == 'start' && preg_match('/^\s*Discovery\s*$/sxmi', $line)) {
                    $step = 'discovery';
                    $count = 0;
                    array_push($elements, 0);
                }
                elseif ($step == 'discovery' && preg_match('/^\s*Health\s*$/sxmi', $line)) {
                    // inject a 0 if only 2 stats, which means that the agent has 0 portals discovered
                    if ($count == 2) {
                        $temp = array_pop($elements);
                        array_push($elements, '0');
                        array_push($elements, $temp);
                    }
                    $count = 0;
                    $step = 'health';
                }
                elseif ($step == 'health' && preg_match('/^\s*Building\s*$/sxmi', $line)) {
                    $step = 'building';
                }
                elseif ($step == 'building' && preg_match('/^\s*Combat\s*$/sxmi', $line)) {
                    $step = 'combat';
                }
                elseif ($step == 'combat' && preg_match('/^\s*Defense\s*$/sxmi', $line)) {
                    $step = 'defense';
                }
                elseif ($step == 'defense' && preg_match('/^\s*Missions\s*$/sxmi', $line)) {
                    $step = 'missions';
                }
                elseif ($step == 'defense' && preg_match('/^\s*Resource\sGathering\s*$/sxmi', $line)) {
                    // Inject a 0 for missions completed
                    array_push($elements, 0);
                    $step = 'resources';
                }
                elseif ($step == 'missions' && preg_match('/^\s*Resource\sGathering\s*$/sxmi', $line)) {
                    $step = 'resources';
                }
                elseif ($step == 'resources' && preg_match('/^\s*Mentoring\s*$/sxmi', $line)) {
                    // Inject a 0 for "Glyph hack points"
                    if ($count == 2) {
                        $temp = array_pop($elements);
                        array_push($elements, '0');
                        array_push($elements, $temp);
                    }
                    $step = 'mentoring';
                }
                elseif ($step == 'discovery' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*(?:XM)?\s*$/sxmi', $line, $values)) {
                    $count++;
                    array_push($elements, $values[1]);
                }
                elseif ($step == 'health' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*(?:km|kln)\s*$/sxmi', $line, $values)) {
                    array_push($elements, $values[1]);
                }
                elseif ($step == 'building' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*(?:MUs|XM|km|kln)?\s*$/sxmi', $line, $values)) {
                    array_push($elements, $values[1]);
                }
                elseif ($step == 'combat' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*$/sxmi', $line, $values)) {
                    array_push($elements, $values[1]);
                }
                elseif ($step == 'defense' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*(?:(?:(?:km|kln|MU)-)?(?:days|clays|ilays|cl_ys|__ys|d_ys|_ays|\(l_ys))\s*$/sxmi', $line, $values)) {
                    array_push($elements, $values[1]);
                }
                elseif ($step == 'missions' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*$/sxmi', $line, $values)) {
                    array_push($elements, $values[1]);
                }
                elseif ($step == 'resources' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*(days|clays|ilays|cl_ys|__ys|d_ys|_ays)?\s*$/sxmi', $line, $values)) {
                    $count++;
                    array_push($elements, $values[1]);
                }
            }

            // final check on count
            if ($step == 'resources' && $count == 2) {
                $temp = array_pop($elements);
                array_push($elements, '0');
                array_push($elements, $temp);
            }

            $elements = preg_replace('/[.]|,|\s/', '', $elements);
            $elements = preg_replace('/o/i', '0', $elements);
            $elements = preg_replace('/\||l|i/i', '1', $elements);
            $elements = preg_replace('/q/i', '4', $elements);
            $elements = preg_replace('/t/i', '7', $elements);
            $elements = preg_replace('/a|e/i', '8', $elements);
            $elements = preg_replace('/g/', '9', $elements);

            $data = array();

            foreach ($this->stats as $stat) {
                if ($stat->ocr) {
                    if (sizeof($elements) > 0) {
                        $data[$stat->stat] = (int)(array_shift($elements));
                    }
                    else {
                        $data[$stat->stat] = 0;
                    }
                }
            }

            $this->log(LogLevel::DEBUG, print_r($data, true));

            return $data;
        }
        catch (Exception $e) {
            throw $e;
        }
    }
}

class OCRException extends Exception {

    public function __construct($session_id, $message) {
        $this->session = $session_id;
        parent::__construct($message);
    }
}
?>
