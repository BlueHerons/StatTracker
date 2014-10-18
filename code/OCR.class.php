<?php

class OCR {

	const MIN_IMAGE_WIDTH = 600;
	const LEVENSHTEIN_THRESHOLD = 10;

	public static function scanAgentProfile($imagePath) {
		$imagePath = self::prepareImage($imagePath);
		$lines = self::executeOCR($imagePath);
		$lines = self::filterOCRResults($lines);
		$data = self::processAgentData($lines);
		return $data;
	}

	public static function getTempFileName() {
		return substr(str_shuffle(md5(time())), 0, 6);
	}

	/**
	 * Prepares an image for OCR
	 *
	 * @param
	 *
	 * @return path to image for OCR use
	 */ 
	public static function prepareImage($imagePath) {
		// TODO: define path for temp files
		$newWidth = 600;
		$contrast = 2;
		$newFile = self::getTempFileName() . ".png";
		$img = new Imagick($imagePath);

		if ($img->getImageWidth() < $newWidth) {
			$newHeight = (int)(($newWidth * $img->getImageHeight()) / $img->getImageWidth());
			$img->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1);
		}

		do {
			$img->contrastImage(true);
		} while (--$contrast > 0);

		$img->writeImage("uploads/" . $newFile);
		return "uploads/" . $newFile;

	}
	
	public static function executeOCR($imagePath) {
		$tesseract = new TesseractOCR($imagePath);
		//$tesseract->setLanguage("eng");
		$tesseract->setWhitelist(range('A','Z'), range('a','z'), range('0','9'), ',', '-', '<');
		$lines = array_values(array_filter(explode("\n", $tesseract->recognize())));
		$lines = array_filter($lines, function($item) {
			return strlen(trim($item)) > 0;
		});

		return $lines;	
	}

	public static function filterOCRResults($lines) {
		// Remove lines less than 1 char
		$lines = array_filter($lines, function($item) {
			return strlen(trim($item)) > 1;
		});

		$lines = array_filter($lines, function($item) {	
			return strpos(trim($item), "LVL") !== 0;
		});

		return array_values($lines);
	}

	public static function processAgentData($lines) {
		$agent = preg_grep("/ <$/", $lines);
		$agent = array_values($agent);
		if (sizeof($agent) != 1) { 
			echo "error detecting agent, not 1 result: ";
			print_r($agent);
			//die();
		}
		$res = preg_match("/^([A-Za-z0-9]{3,15}) .*<$/", $agent[0], $matches);
		$agent = $matches[1];

		$ap = preg_grep("/ AP$/", $lines);
		$ap = array_values($ap);
		if (sizeof($ap) != 1) { 
			echo "error detecting ap, not 1 result: ";
			print_r($ap);
			//die();
		}
		$res = preg_match("/([0-9,]{1,10})\/", $ap[0], $matches);
		$ap = preg_replace("/,/", "", $matches[1]);

		$stats = array();

		foreach(StatTracker::getStats() as $stat) {
			$stats[$stat->stat] = self::findAgentStatValue($stat->name, $stat->unit, $lines);
		}

		// Add in AP, since it requires special processing
		if (empty($stats['ap'])) {
			//$stats['ap'] = $ap;
		}

		print_r(array("agent" => $agent, "stats" => $stats));
	}

	private static function findAgentStatValue($stat_name, $unit, &$lines) {
		$score = array();
		$index = -1;
		print_r($lines);

		foreach ($lines as $line) {
			$index++;
			// Skip lines that clearly aren't a match
			if (strlen($stat_name) >= strlen($line))
				continue;

			// Strip numerics from the line for comparison
			$name_line = trim(preg_replace("/[0-9,]/", "", $line));
			$name_line = trim(preg_replace("/".$unit."$/i", "",  $name_line));
			$value_line = trim(str_replace(explode(" ", $stat_name), "", $line));
			$value_line = trim(str_replace(explode(" ", $unit), "", $value_line));

			$s = array (
				"index" => $index,
				"name" => $stat_name,
				"line" => $line,
				"value_line" => $value_line,
				"name_line" => $name_line,
				"diff" => abs(strlen($stat_name) - strlen($name_line)),
				"score" => levenshtein($stat_name, $name_line),
				"threshold" => (strlen($stat_name) / 2),
				"value" => trim(preg_replace("/[^0-9lt]/", "", $value_line)) // 1 is sometimes read as l or t
			);

			

			// If more than half of the characters needs to be replaced, clearly not a match
			if (($s['score'] + $s['diff']) >= $s['threshold']) {
				echo "REJECT";
				print_r($s);
				continue;
			}

			$s['final_value'] = $s['value'];

			// replace l with 1
			$s['value'] = str_replace("l", "1", $s['value']);
			$s['value'] = str_replace("t", "1", $s['value']);

			$score[] = $s;
		}

		usort($score, function($a, $b) {
			if ($a["score"] == $b["score"]) return 0;
			return ($a["score"] < $b["score"]) ? -1 : 1;
		});

		// "Best match" should be first entry
		$line = array_shift($score);
		array_splice($lines, $line['index'], 1);

		print_r($line);

		if ($stat_name == "AP") {
			$line['value'] = preg_replace("/[^0-9]/", "", explode(" ", $line['value_line'])[0]);
		}

		return $line['value'];
	}
}
?>
