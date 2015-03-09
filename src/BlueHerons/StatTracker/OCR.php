<?php
namespace BlueHerons\StatTracker;

use Exception;
use Imagick;
use ImagickDraw;
use StdClass;

use Katzgrau\KLogger\Logger;

class OCR {

	private static $logger = null;

	/**
	 * Retrieves a PSR-3 compliant logger instance for use
	 *
	 * @return Object implementing PSR-3 LoggerInterface
	 */
	private static function logger() {
		if (self::$logger == null) {
			self::$logger = new Logger(LOG_DIR);
		}
		return self::$logger;
	}

	/**
	 * Sends a response object to the client. This is intended to be used as part of a response that is streamed, so the
	 * ouput buffer is flushed (sent to the client) immediately.
	 *
	 * @param mixed $thing the thing to send to the client wrapped in a response object. Can be a string, exception or object
	 */
	private static function sendMessage($thing) {
		$resp = new StdClass();
		if (is_object($thing)) {
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

		echo json_encode($resp);
		ob_flush();
		flush();
	}

	/**
	 * Scans an agent profile screenshot and returns an array of stat values.
	 *
	 * @param string $imagePath the path to the image
	 *
	 * @return array array of stat key's and values from the screenshot
	 */
	public static function scanAgentProfile($imagePath) {
		try {
			$response = new StdClass();
			$response->status = array();
			self::logger()->debug(sprintf("Beginning scan of %s", $imagePath));
			$imagePath = self::convertToPBM($imagePath);
			$lines = self::executeOCR($imagePath);
			$data = self::processAgentData($lines);
			self::logger()->debug("Parsed Stats:", $data);
			$response->status = "Your screenshot has been processed.<p/>Please review your stats and click the \"Submit Stats\" button to submit.";
			$response->stats = $data;
			self::sendMessage($response);
		}
		catch (Exception $e) {
			self::logger()->error(sprintf("%s: %s\n%s", get_class($e), $e->getMessage(), $e->getTraceAsString()));
			self::sendMessage($e);
			die();
		}
		finally {
			ob_flush();
			flush();
		}
	}

	/**
	 * Generates a random, temporary file name.
	 *
	 * @param string $extension optional extension for the filename
	 *
	 * @return temporary file name
	 */
	public static function getTempFileName($extension = "png") {
		return substr(str_shuffle(md5(time())), 0, 6) . "." . $extension;
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
	public static function convertToPBM($imagePath) {

		$newFile = UPLOAD_DIR . self::getTempFileName("pbm");

		try {
			self::sendMessage("Measuring screenshot size...");
			self::logger()->info(sprintf("Measuring %s", $imagePath));
			$img = new Imagick();
			$quantumRange = $img->getQuantumRange();
			$black_point = $quantumRange['quantumRangeLong'] * 0.04;
			$white_point = $quantumRange['quantumRangeLong'] - $black_point;
			$img->readImage($imagePath);
			$identify = $img->identifyImage();
			$x = 0; $y = 0;
			$pixel = $img->getImagePixelColor($x, $y);
			$color = $pixel->getColorAsString();
			self::logger()->debug(sprintf("Scanner @ [%s,%s]", $x, $y));
			if ($color == "srgb(0,0,0") {
				for ( ; $x < 500 ; $x += 1 , $y += 1) {
					$pixel = $img->getImagePixelColor($x, $y);
					$color = $pixel->getColorAsString();
					if (!preg_match('/^srgb\(\d{1,2},\d{1,2},\d{1,2}\)$/sxmi', $color)) {
						self::logger()->debug(sprintf("Scanner @ [%s,%s]", $x, $y));
						break;
					}
				}
			}
			else {
				for ( ; $y < 500 ; $y += 5) {
					$pixel = $img->getImagePixelColor($x, $y);
					$color = $pixel->getColorAsString();
					if ($color == 'srgb(0,0,0)') {
						self::logger()->debug(sprintf("Scanner @ [%s,%s]", $x, $y));
						break;
					}
				}
				for ( ; $x < 500 ; $x += 1 , $y += 1) {
					$pixel = $img->getImagePixelColor($x, $y);
					$color = $pixel->getColorAsString();
					if (!preg_match('/^srgb\(\d{1,2},\d{1,2},\d{1,2}\)$/sxmi', $color)) {
						self::logger()->debug(sprintf("Scanner @ [%s,%s]", $x, $y));
						break;
					}
				}
				$count = 0;
				for ( ; $x < 500 ; $x += 1 , $y -= 1) {
					$count++;
					$pixel = $img->getImagePixelColor($x, $y);
					$color = $pixel->getColorAsString();
					if (preg_match('/^srgb\((\d{1,2}),(\d{1,2}),(\d{1,2})\)$/sxmi', $color, $matches)
						&& $matches[1] < 80
						&& $matches[2] < 80
						&& $matches[3] < 80) {
						if ($count <= 2) {
							$y += 1;
							continue;
						} else {
							$x -= 1;
							$y += 1;
							self::logger()->debug(sprintf("Scanner @ [%s,%s]", $x, $y));
							break;
						}
					}
				}
			}

			for ( ; $x < 500 ; $x += 1) {
				$pixel = $img->getImagePixelColor($x, $y);
				$color = $pixel->getColorAsString();
				if (preg_match('/^srgb\((\d{1,2}),(\d{1,2}),(\d{1,2})\)$/sxmi', $color, $matches)
					&& $matches[1] < 80
					&& $matches[2] < 80
					&& $matches[3] < 80) {
					$x -= 1;
					self::logger()->debug(sprintf("Scanner @ [%s,%s]", $x, $y));
					break;
				}
			}
			for ( ; $x < 500 ; $x += 1 , $y += 1) {
				$pixel = $img->getImagePixelColor($x, $y);
				$color = $pixel->getColorAsString();
				if (preg_match('/^srgb\(\d,\d,\d\)$/sxmi', $color)) {
					$y += 1;
					self::logger()->debug(sprintf("Scanner @ [%s,%s]", $x, $y));
					break;
				}
			}
			$rightx = $x;
			$x = 0;
			for ( ; $x < 500 ; $x += 1) {
				$pixel = $img->getImagePixelColor($x, $y);
				$color = $pixel->getColorAsString();
				if (!preg_match('/^srgb\(\d,\d,\d\)$/sxmi', $color)) {
					self::logger()->debug(sprintf("Scanner @ [%s,%s]", $x, $y));
					break;
				}
			}

			$space = $x;
			$x += $rightx;
			$y += $space;

			if ($space <= 4) {
				for ( ; $x < 500 ; $x += 1 , $y += 1) {
					$pixel = $img->getImagePixelColor($x, $y);
					$color = $pixel->getColorAsString();
					if (!preg_match('/^srgb\(\d{1,2},\d{1,2},\d{1,2}\)$/sxmi', $color)) {
						$x -= 1;
						self::logger()->debug(sprintf("Scanner @ [%s,%s]", $x, $y));
						break;
					}
				}
			}

			for ( ; $y < 500 ; $y += 1) {
				$pixel = $img->getImagePixelColor($x, $y);
				$color = $pixel->getColorAsString();
				if (!preg_match('/^srgb\(\d{1,2},\d{1,2},\d{1,2}\)$/sxmi', $color)) {
					self::logger()->debug(sprintf("Scanner @ [%s,%s]", $x, $y));
					break;
				}
			}
			for ( ; $y < 500 ; $y += 1) {
				$pixel = $img->getImagePixelColor($x, $y);
				$color = $pixel->getColorAsString();
				if (preg_match('/^srgb\(\d{1,2},\d{1,2},\d{1,2}\)$/sxmi', $color)) {
					$x -= round($space / 2);
					self::logger()->debug(sprintf("Scanner @ [%s,%s]", $x, $y));
					break;
				}
			}

			self::sendMessage("Cropping screenshot..");
			self::logger()->info(sprintf("Cropping %s to W: %s, H: %s", $imagePath, $identify['geometry']['width'], $identify['geometry']['height'] - $y));
			$img->cropImage($identify['geometry']['width'], $identify['geometry']['height'] - $y, 0, $y);

			self::sendMessage("Masking screenshot...");
			self::logger()->info(sprintf("Masking %s", $imagePath));
			$draw = new ImagickDraw();
			$draw->setFillColor('black');
			$draw->rectangle(0, 0, $x, $y);
			$img->drawImage($draw);

			self::sendMessage("Contrasting screenshot...");
			self::logger()->info(sprintf("Constrasting %s", $imagePath));
			self::logger()->debug(sprintf("Gamma: %s, %s, %s", $black_point, 1, $white_point));
			$img->levelImage($black_point, 1, $white_point);
			$img->resizeImage($identify['geometry']['width'] * 2, 0,  imagick::FILTER_LANCZOS, 1);
			$img->writeImage($newFile);
			self::logger()->info(sprintf("Done with %s", $imagePath));

			return $newFile;
		}
		catch (Exception $e) {
			copy($imagePath, $imagePath . "_errored");
			unlink($newFile);
			throw $e;
		}
		finally {
			unlink($imagePath);
		}
	}

	/**
	 * Invokes external OCR tool
	 *
	 * @param string $imagePath path to the image to scan
	 *
	 * @return array of lines read from the image
	 */
	public static function executeOCR($imagePath) {
		try {
			$cmd = sprintf("%s -i %s", OCRAD, $imagePath);
			self::logger()->debug(sprintf("Executing %s", $cmd));
			self::sendMessage("Scanning screenshot...");
			exec($cmd, $lines);
			self::logger()->debug("OCR results:", $lines);
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
	public static function processAgentData($lines) {
		try {
			self::sendMessage("Processing scanned results...");
			$step = 'start';
			$elements = array();
			foreach($lines as $line) {
				if ($step == 'start' && preg_match('/^\s*([\d\s\|.egiloqt,]+)\s*AP\s*$/sxmi', $line, $values)) {
					$step = 'ap';
					array_push($elements, $values[1]);
				} elseif ($step == 'ap' && preg_match('/^\s*Discovery\s*$/sxmi', $line)) {
					$step = 'discovery';
					$count = 0;
				} elseif ($step == 'discovery' && preg_match('/^\s*Health\s*$/sxmi', $line)) {
					// inject a 0 if only 2 stats, which means that the agent has 0 portals discovered
					if ($count == 2) {
						$temp = array_pop($elements);
						array_push($elements, '0');
						array_push($elements, $temp);
					}
					$step = 'health';
				} elseif ($step == 'health' && preg_match('/^\s*Building\s*$/sxmi', $line)) {
					$step = 'building';
				} elseif ($step == 'building' && preg_match('/^\s*Combat\s*$/sxmi', $line)) {
					$step = 'combat';
				} elseif ($step == 'combat' && preg_match('/^\s*Defense\s*$/sxmi', $line)) {
					$step = 'defense';
				} elseif ($step == 'defense' && preg_match('/^\s*Missions\s*$/sxmi', $line)) {
					$step = 'missions';
				} elseif ($step == 'defense' && preg_match('/^\s*Resource\sGathering\s*$/sxmi', $line)) {
					// Inject a 0 for missions completed
					array_push($elements, 0);
					$step = 'resources';
				} elseif ($step == 'missions' && preg_match('/^\s*Resource\sGathering\s*$/sxmi', $line)) {
					$step = 'resources';
				} elseif ($step == 'resources' && preg_match('/^\s*Mentoring\s*$/sxmi', $line)) {
					$step = 'mentoring';
				} elseif ($step == 'discovery' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*(?:XM)?\s*$/sxmi', $line, $values)) {
					$count++;
					array_push($elements, $values[1]);
				} elseif ($step == 'health' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*(?:km|kln)\s*$/sxmi', $line, $values)) {
					array_push($elements, $values[1]);
				} elseif ($step == 'building' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*(?:MUs|XM|km|kln)?\s*$/sxmi', $line, $values)) {
					array_push($elements, $values[1]);
				} elseif ($step == 'combat' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*$/sxmi', $line, $values)) {
					array_push($elements, $values[1]);
				} elseif ($step == 'defense' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*(?:(?:(?:km|kln|MU)-)?(?:days|clays|ilays|cl_ys|__ys|d_ys|_ays|\(l_ys))\s*$/sxmi', $line, $values)) {
					array_push($elements, $values[1]);
				} elseif ($step == 'missions' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*$/sxmi', $line, $values)) {
					array_push($elements, $values[1]);
				} elseif ($step == 'resources' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*(days|clays|ilays|cl_ys|__ys|d_ys|_ays)?\s*$/sxmi', $line, $values)) {
					array_push($elements, $values[1]);
				} elseif (preg_match('/^\s*(month|week|now)\s*$/sxmi', $line, $values)) {
					$warning = sprintf($lang['maybe because'], $values[1]);
				}
			}

			$elements = preg_replace('/[.]|,|\s/', '', $elements);
			$elements = preg_replace('/o/i', '0', $elements);
			$elements = preg_replace('/\||l|i/i', '1', $elements);
			$elements = preg_replace('/q/i', '4', $elements);
			$elements = preg_replace('/t/i', '7', $elements);
			$elements = preg_replace('/a|e/i', '8', $elements);
			$elements = preg_replace('/g/', '9', $elements);

			$data = array();
			$stats = StatTracker::getStats();

			foreach ($stats as $stat) {
				if ($stat->ocr) {
					if (sizeof($elements) > 0) {
						$data[$stat->stat] = (int)(array_shift($elements));
					}
					else {
						$data[$stat->stat] = 0;
					}
				}
			}

			return $data;
		}
		catch (Exception $e) {
			throw $e;
		}
	}
}
?>
