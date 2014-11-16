<?php

class OCR {

	/**
	 * Scans an agent profile screenshot and returns an array of stat values.
	 *
	 * @param string $imagePath the path to the image
	 *
	 * @return array array of stat key's and values from the screenshot
	 */
	public static function scanAgentProfile($imagePath) {
		$imagePath = self::convertToPBM($imagePath);
		$lines = self::executeOCR($imagePath);
		$data = self::processAgentData($lines);
		return $data;
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
	public static function convertToPBM($imagePath, $deleteOriginal = true) {
		$img = new Imagick();
		$quantumRange = $img->getQuantumRange();
		$black_point = $quantumRange['quantumRangeLong'] * 0.04;
		$white_point = $quantumRange['quantumRangeLong'] - $black_point;
		$img->readImage($imagePath);
		$identify = $img->identifyImage();
		$newFile = UPLOAD_DIR . self::getTempFileName("pbm");
		$x = 0; $y = 0;
		$pixel = $img->getImagePixelColor($x, $y);
		$color = $pixel->getColorAsString();
		if ($color == "srgb(0,0,0") {
			for ( ; $x < 500 ; $x += 1 , $y += 1) {
				$pixel = $img->getImagePixelColor($x, $y);
				$color = $pixel->getColorAsString();
				if (!preg_match('/^srgb\(\d{1,2},\d{1,2},\d{1,2}\)$/sxmi', $color)) {
					break;
				}
			}
		}
		else {
			for ( ; $y < 500 ; $y += 5) {
				$pixel = $img->getImagePixelColor($x, $y);
				$color = $pixel->getColorAsString();
				if ($color == 'srgb(0,0,0)') {
					break;
				}
			}
			for ( ; $x < 500 ; $x += 1 , $y += 1) {
				$pixel = $img->getImagePixelColor($x, $y);
				$color = $pixel->getColorAsString();
				if (!preg_match('/^srgb\(\d{1,2},\d{1,2},\d{1,2}\)$/sxmi', $color)) {
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
				break;
			}
		}
		for ( ; $x < 500 ; $x += 1 , $y += 1) {
			$pixel = $img->getImagePixelColor($x, $y);
			$color = $pixel->getColorAsString();
			if (preg_match('/^srgb\(\d,\d,\d\)$/sxmi', $color)) {
				$y += 1;
				break;
			}
		}
		$rightx = $x;
		$x = 0;
		for ( ; $x < 500 ; $x += 1) {
			$pixel = $img->getImagePixelColor($x, $y);
			$color = $pixel->getColorAsString();
			if (!preg_match('/^srgb\(\d,\d,\d\)$/sxmi', $color)) {
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
					break;
				}
			}
		}

		for ( ; $y < 500 ; $y += 1) {
			$pixel = $img->getImagePixelColor($x, $y);
			$color = $pixel->getColorAsString();
			if (!preg_match('/^srgb\(\d{1,2},\d{1,2},\d{1,2}\)$/sxmi', $color)) {
				break;
			}
		}
		for ( ; $y < 500 ; $y += 1) {
			$pixel = $img->getImagePixelColor($x, $y);
			$color = $pixel->getColorAsString();
			if (preg_match('/^srgb\(\d{1,2},\d{1,2},\d{1,2}\)$/sxmi', $color)) {
				$x -= round($space / 2);
				break;
			}
		}

		$img->cropImage($identify['geometry']['width'], $identify['geometry']['height'] - $y, 0, $y);
		$draw = new ImagickDraw();
		$draw->setFillColor('black');
		$draw->rectangle(0, 0, $x, $y);
		$img->drawImage($draw);
		$img->levelImage($black_point, 1, $white_point);
		$img->resizeImage($identify['geometry']['width'] * 2, 0,  imagick::FILTER_LANCZOS, 1);
		$img->writeImage($newFile);

		if ($deleteOriginal) {
			unlink($imagePath);
		}

		return $newFile;
	}

	/**
	 * Invokes external OCR tool
	 *
	 * @param string $imagePath path to the image to scan
	 * @param boolean $deleteImage whether or not to delete the image after scanning
	 *
	 * @return array of lines read from the image
	 */
	public static function executeOCR($imagePath, $deleteImage = true) {
		exec("/usr/bin/ocrad -i $imagePath", $lines);

		if ($deleteImage) {
			unlink($imagePath);
		}

		return $lines;
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
		$step = 'start';
		$elements = array();
		foreach($lines as $line) {
			if ($step == 'start' && preg_match('/^\s*([\d\s\|.egiloqt,]+)\s*AP\s*$/sxmi', $line, $values)) {
				$step = 'ap';
				array_push($elements, $values[1]);
			} elseif ($step == 'ap' && preg_match('/^\s*Discovery\s*$/sxmi', $line)) {
				$step = 'discovery';
				$count = 0;
			} elseif ($step == 'discovery' && preg_match('/^\s*Building\s*$/sxmi', $line)) {
				// inject a 0 if only 2 stats, which means that the agent has 0 portals discovered
				if ($count == 2) {
					$temp = array_pop($elements);
					array_push($elements, '0');
					array_push($elements, $temp);
				}
				$step = 'building';
			} elseif ($step == 'building' && preg_match('/^\s*Combat\s*$/sxmi', $line)) {
				$step = 'combat';
			} elseif ($step == 'combat' && preg_match('/^\s*Health\s*$/sxmi', $line)) {
				$step = 'health';
			} elseif ($step == 'health' && preg_match('/^\s*Defense\s*$/sxmi', $line)) {
				$step = 'defense';
			} elseif ($step == 'defense' && preg_match('/^\s*Missions\s*$/sxmi', $line)) {
				$step = 'missions';
			} elseif ($step == 'discovery' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*(?:XM)?\s*$/sxmi', $line, $values)) {
				$count++;
				array_push($elements, $values[1]);
			} elseif ($step == 'building' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*(?:MUs|XM|km|kln)?\s*$/sxmi', $line, $values)) {
				array_push($elements, $values[1]);
			} elseif ($step == 'combat' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*$/sxmi', $line, $values)) {
				array_push($elements, $values[1]);
			} elseif ($step == 'health' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*(?:km|kln)\s*$/sxmi', $line, $values)) {
				array_push($elements, $values[1]);
			} elseif ($step == 'defense' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*(?:(?:(?:km|kln|MU)-)?(?:days|clays|ilays|cl_ys|__ys|d_ys|_ays|\(l_ys))\s*$/sxmi', $line, $values)) {
				array_push($elements, $values[1]);
			} elseif ($step == 'missions' && preg_match('/^\s*([\d\s\|.aegiloqt,]+)\s*$/sxmi', $line, $values)) {
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
		$i = 0;

		foreach ($stats as $stat) {
			if ($stat->ocr)
				$data[$stat->stat] = $elements[$i++];
		}

		return $data;
	}
}
?>
