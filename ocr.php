<?php

require_once("vendor/autoload.php");

$tesseract = new TesseractOCR('uploads/720x1923.png.214e73a0ed000131a42c22000b2204cd');
$tesseract->setWhitelist(range('A','Z'), range('a','z'), range('0','9'), ',', '-', '<'); //tesseract will threat everything as downcase letters
$lines = array_values(array_filter(explode("\n", $tesseract->recognize())));
print_r($lines);

$agent = preg_grep("/ <$/", $lines);
$agent = array_values($agent);
if (sizeof($agent) != 1) { 
	echo "error detecting agent, not 1 result: ";
	print_r($agent);
	die();
}

$res = preg_match("/^([A-Za-z0-9]{3,15}) .*<$/", $agent[0], $matches);
$agent = $matches[1];

$ap = preg_grep("/ AP$/", $lines);
$ap = array_values($ap);
if (sizeof($ap) != 1) { 
	echo "error detecting ap, not 1 result: ";
	print_r($ap);
	die();
}

$res = preg_match("/([0-9,]{1,10})/", $ap[0], $matches);
$ap = preg_replace("/,/", "", $matches[1]);


find_stat("Unique Portals Visited", "", $lines);

function find_stat($name, $regex, $lines) {
	$line = preg_grep("/^{$name}/", $lines);
	var_dump($line);
}
