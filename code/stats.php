<?php
session_start();

class StatTracker {

	static $stats;
	static $fields;
	static $predictions;

	public static function getStatsFields() {
		if (!is_array(self::$fields)) {
			global $mysql;
			$sql = "SELECT stat, name FROM Stats ORDER BY `order` ASC;";
			$res = $mysql->query($sql);
			if (!is_object($res)) {
				die(sprintf("%s: (%s) %s", __LINE__, $mysql->errno, $mysql->error));
			}

			while ($row = $res->fetch_assoc()) {
				self::$fields[$row['stat']] = $row['name'];
			}
		}

		return self::$fields;
	}

	public static function getRawStats($agent, $refresh = false) {
		if (!is_array(self::$stats) || $refresh) {
			global $mysql;
			$sql = sprintf("CALL GetRawStatsForAgent('%s');", $agent);
			if (!$mysql->query($sql)) {
				die(sprintf("%s: (%s) %s", __LINE__, $mysql->errno, $mysql->error));
			}
			$sql = "SELECT * FROM RawStatsForAgent";
			$res = $mysql->query($sql);
			
			while ($row = $res->fetch_assoc()) {
				self::$stats[$row['stat']][$row['timepoint']] = $row['value'];
			}

		}

		return self::$stats;
	}

	public static function getBadgePredictions($agent, $refresh = false) {
		if (!is_array(self::$predictions) || $refresh) {
			global $mysql;

			function badge_sort($a, $b) {
				return strcmp($a['Badge'], $b['Badge']);
			}

			foreach (self::getStatsFields() as $stat => $name) {
				$sql = "CALL GetBadgePrediction('%s');";
				$sql = sprintf($sql, $stat);
				if (!$mysql->query($sql)) {
					die(sprintf("%s: (%s) %s", __LINE__, $mysql->errno, $mysql->error));
				}

				$sql = "SELECT * FROM BadgePrediction;";
				$res = $mysql->query($sql);
				$row = $res->fetch_assoc();

				if (empty($row['Badge']))
					continue;

				self::$predictions[$stat] = $row;
			}

			uksort(self::$predictions, "badge_sort");
		}

		return self::$predictions;
	}
}

function debug($str) {
	echo "<!--";
	print_r($str);
	echo "-->";
}

$mysql = new mysqli("localhost", "SRStats", "LYdPNrbE3PVTDzVn", "SRStats");
if ($mysql->connect_errno) {
	die(sprintf("%s: %s", $mysql->connect_errno, $mysql->connect_error));
}

StatTracker::getStatsFields();

$predictions;

if ($_SERVER['REQUEST_METHOD'] == "GET") {
	$_SESSION['agent'] = filter_var($_REQUEST['agent'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
	StatTracker::getRawStats($_SESSION['agent']);
}

if (empty($_SESSION['agent'])) {
	unset($_SESSION['agent']);
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
	
	if (!isset($_SESSION['agent'])) {
		if (!isset($_REQUEST['agent'])) {
			die("No agent name");
		}
		else {
			$_SESSION['agent'] = filter_var($_REQUEST['agent'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
		}
	}

	$ts = date("Y-m-d H:i:s");

	foreach (StatTracker::getStatsFields() as $stat => $name) {
		if (!isset($_REQUEST[$stat]))
			continue;

		$value = filter_var($_REQUEST[$stat], FILTER_SANITIZE_NUMBER_INT);

		$sql = "INSERT INTO Data VALUES('%s', '%s', '%s', %s);";
		$sql = sprintf($sql, $_SESSION['agent'], $ts, $stat, $value);
		
		if (!$mysql->query($sql)) {
			die(sprintf("%s: %s", $mysql->errno, $mysql->error));
		}
	}	
	
	StatTracker::getRawStats($_SESSION['agent'], true);

	if (sizeof(StatTracker::getRawStats()['ap']) < 2) {
		$message = "Your stats have been recieved. Since this was your first submission, predictions are not available. Submit again tomorrow to see your predictions.";
	}
}

if (isset($_SESSION['agent']) && !empty($_SESSION['agent'])) {
	$predictions = StatTracker::getBadgePredictions($_SESSION['agent']);
}

$mysql->close();
?>
<!DOCTYPE html>
<html>
<head>
	<title>Agent Stats</title>
	<link href="style.css" rel="stylesheet" />
	<meta name="viewport" content="width=360" />
</head>
<body>
<?php
if (isset($message) && $message != "") {
?>
	<div id="message">
		<?php echo $message; ?>
	</div>
<?php
}

if (is_array($predictions)) {
?>
	<h2><?php echo $_SESSION['agent'];?></h2>
	<ul id="predictions">
<?php
	foreach ($predictions as $prediction) {
?>
		<li><?php echo $prediction['Badge'];?> (<?php echo $prediction['Name']; ?>)
			<ul>
				<li>Current: <span><?php echo $prediction['Current'];?></span></li>
				<li>Next: <span><?php echo $prediction['Next'];?></span></li>
				<li>Remaining: <span><?php echo $prediction['Remaining'];?></span></li>
				<li>Should take: <span><?php echo $prediction['Days'];?> days</span></li>
				<li>Historical Rate: <span><?php echo $prediction['Rate'];?> per day</span></li>
			</ul>
		</li>
<?php
	}
?>
	</ul>
<?php
}
?>

<form method="post">
	<table>
		<tr>
			<td>Agent Name</td>
			<td><?php
if (isset($_SESSION['agent'])) {
	echo "<strong>".$_SESSION['agent']."</strong>";
}
else {
		          ?><input type="text"
                                   name="agent"
				   size="15"
				   maxlength="15" />
			</td><?php
}
?>

		</tr>
<?php

foreach (StatTracker::getStatsFields() as $stat => $name) {
	if ($stat == "unique_visits") {
		$title = "Discovery";
	}
	else if ($stat == "hacks") {
		$title = "Building";
	}
	else if ($stat == "res_destroyed") {
		$title = "Combat";
	}
	else if ($stat == "distance_walked") {
		$title = "Health";
	}
	else if ($stat == "oldest_portal") {
		$title = "Defense";
	}

	if ($title != "") {
?>
		<tr>
			<th colspan="2"><?php echo $title; ?></th>
		</tr>
<?php
		$title = "";
	}

	

	if ($stat == "timestamp") {
	}
	else {
?>
		<tr>
			<td><?php echo $name;?></td>
			<td><input type="number"
				   name="<?php echo $stat; ?>"
				   value="" />
			</td>
		</tr>
<?php
	}
}
?>
		<tr>
			<td colspan="2"><input type="submit" value="Submit Statistics"/></td>
		</tr>
	</table>
</body>
</html><?php
if ($_SERVER['REQUEST_METHOD'] == "GET") {
	unset($_SESSION['agent']);
}
?>
