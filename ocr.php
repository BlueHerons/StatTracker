<?php
require_once("config.php");
require_once("code/StatTracker.class.php");
require_once("code/OCR.class.php");
require_once("vendor/autoload.php");

//print_r($_FILES);
$mysql = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysql->connect_errno) {
	die(sprintf("%s: %s", $mysql->connect_errno, $mysql->connect_error));
}

if (!empty($_FILES['screenshot'])) {
	$file = "uploads/" . OCR::getTempFileName() . ".png";
	move_uploaded_file($_FILES['screenshot']['tmp_name'], $file);
	$file = OCR::prepareImage($file);
	?><img src="<?php echo $file;?>" /><?php
	?><pre><?php
	$data = OCR::scanAgentProfile($file);
	print_r($data);
	?></pre><?php
}
?>

<form action="<?php echo $_SERVER['PHP_SELF'];?>" method="post" enctype="multipart/form-data">
	<input type="file" name="screenshot" />
	<input type="submit" />
</form>
