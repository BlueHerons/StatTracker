<?php
session_start();

function debug($str) {
	echo "<!--";
	print_r($str);
	echo "-->";
}

$mysql = new mysqli("localhost", "SRStats", "LYdPNrbE3PVTDzVn", "SRStats");
if ($mysql->connect_errno) {
	die(sprintf("%s: %s", $mysql->connect_errno, $mysql_connect_error));
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
	$fields = array();
	if (isset($_SESSION['agent'])) {
		$_REQUEST['agent'] = $_SESSION['agent'];
	}

	foreach ($_REQUEST as $field => $value) {
		if ($field == "agent") {
			$fields[$field] = filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
			$_SESSION['agent'] = $fields[$field];
		}
		else {
			$fields[$field] = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
		}
	}
	

	$sql = "INSERT INTO AgentStats (%s) VALUES (%s);";
	$cols = "";
	$vals = "";

	foreach ($fields as $field => $value) {
		$cols .= "$field, ";
		$vals .= "'$value', ";
	}
	
	$cols .= "timestamp";
	$vals .= "'" . date("Y-m-d H:i:s")  . "'";

	$cols = trim($cols, ' ,');
	$vals = trim($vals, ' ,');

	$sql = sprintf($sql, $cols, $vals);
	$mysql->query($sql);

	$sql = "SELECT *
                FROM (SELECT CAST(timestamp as DATE) `date`,
                             agent,
                             ap,
                             portals_discovered,
                             unique_visits,
                             hacks,
                             res_deployed,
                             links_created,
                             fields_created,
                             xm_recharged,
                             portals_captured,
                             unique_captures,
                             res_destroyed,
                             oldest_portal
                      FROM `AgentStats`) stats
                WHERE `agent` = '". $_SESSION['agent'] ."' AND
                      NOT EXISTS (SELECT 1
                                  FROM (SELECT *, CAST(timestamp as DATE) `date2`
                                        FROM `AgentStats`) stats2
                                  WHERE stats2.`agent` = stats.`agent` AND
                                        stats2.date2 = stats.date AND
                                        stats2.ap > stats.ap
                                 )
                ORDER BY `date`;";

	$res = $mysql->query($sql);

	$message = "";

	if ($res->num_rows < 2) {
		$message = "Your stats have been recieved. Since this was your first submission, predictions are not available. Submit again tomorrow to see your predictions.";
	}
	else {
		$data_points = array();
		while ($row = $res->fetch_assoc()) {
			$data_points[] = $row;
		}

		$deltas = array();
		$stats = array();
		$days = 0;
	
		// Iterates over the list of fields
		foreach($data_points[0] as $field => $ignore) {
			$sumXY = 0;
			$sumX = 0;
			$sumY = 0;
			$sumX2 = 0;
			$lowest_ts = time();
			if ($field == "agent" || $field == "timestamp")
				continue;
			
			// Iterates of all values for a field
			foreach ($data_points as $i => $data) {
	
				if ($field == "date") {
					if ($i == sizeof($data_points) - 1) {
	
						$days = strtotime($data[$field]) - $lowest_ts;
						$days = $days / 86400; // seconds / seconds in a day
						$days += 1;
					}
					else {
						if (strtotime($data[$field]) < $lowest_ts) {
							$lowest_ts = strtotime($data[$field]);
						}
					}
				}
				else {
					$sumXY = $sumXY + ($data[$field] * ($i + 1));
					$sumX = $sumX + ($i + 1);
					$sumY = $sumY + ($data[$field]);
					$sumX2 = $sumX2 + (($i + 1) * ($i + 1));
				}
			}
	
			if ($field == "date")
				continue;
	
			$slope = ((sizeof($data_points) * $sumXY) - ($sumX * $sumY)) /
				 ((sizeof($data_points) * $sumX2) - ($sumX * $sumX));
	
			$deltas[$field] = $slope;
	
			$sql = "SELECT * FROM Badges WHERE stat = '$field' AND amount_required <= " . $fields[$field] . " ORDER BY amount_required DESC LIMIT 1;";
			$current_badge = $mysql->query($sql)->fetch_assoc();
			$sql = "SELECT * FROM Badges WHERE stat = '$field' AND amount_required > " . $fields[$field] . " ORDER BY amount_required ASC LIMIT 1;";
			$next_badge = $mysql->query($sql)->fetch_assoc();
	
	
			$stats[$field] = array(
				"name" => "",
				"current_amount" => $fields[$field],
				"datapoints" => sizeof($data_points),
				"days" => $days,
				"slope" => $slope,
				"badge_name" => $current_badge['name'],
				"badge_level" => $current_badge['level'],
				"badge_next_level" => $next_badge['level'],
				"badge_next_amount" => $next_badge['amount_required'],
				"badge_remaining_amount" => ($next_badge['amount_required'] - $fields[$field]),
				"badge_est_days" => round(($next_badge['amount_required'] - $fields[$field]) / $slope, 0)
			);
		}
?>
<h2>Results (<?php echo $days; ?> days)</h2>
	<ul>
<?php
	foreach ($deltas as $stat => $amount) {
		$sql = "SELECT * FROM Badges WHERE stat = '$stat' AND amount_required <= " . $fields[$stat] . " ORDER BY amount_required DESC LIMIT 1;";
		$current_badge_res = $mysql->query($sql);
		$sql = "SELECT * FROM Badges WHERE stat = '$stat' AND amount_required > " . $fields[$stat] . " ORDER BY amount_required ASC LIMIT 1;";
		$next_badge_res = $mysql->query($sql);

		$current_badge = $current_badge_res->fetch_assoc();
		$next_badge = $next_badge_res->fetch_assoc();
?>
		<li><?php echo $stat;?>
			<ul>
				<li>Badge Name: <?php echo $current_badge['name'];?></li>
				<li>Current: <?php echo $current_badge['level'];?></li>
				<li>Next: <?php echo $next_badge['level'];?></li>
				<li>Amount left: <?php echo ($next_badge['amount_required'] - $fields[$stat]);?></li>
				<li>Should take: <?php echo round((($next_badge['amount_required'] - $fields[$stat]) / $amount), 0);?> days (<?php echo $amount;?> / day)</li>
			</ul>
		</li>
<?php
	}
?>
	</ul>
<?php
	}
}
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
$sql = "SHOW FULL COLUMNS FROM AgentStats;";
$res = $mysql->query($sql);

$fields = array();

while($row = $res->fetch_assoc()) {
	$type = "";
	preg_match("#([a-z]+)\(([0-9]+)\)#i", $row['Type'], $type);
	$fields[] = array(
		"name" => $row['Field'],
		"label" => $row['Comment'],
		"type" => $type[1] == "int" ? "number" : "text",
		"length" => $type[2]
	);
}
$mysql->close();

if (isset($message) && $message != "") {
?>
	<div id="message">
		<?php echo $message; ?>
	</div>
<?php
}
?>

<form method="post">
	<table>
<?php
foreach ($fields as $field) {
	if ($field['name'] == "unique_visits") {
		$title = "Discovery";
	}
	else if ($field['name'] == "hacks") {
		$title = "Building";
	}
	else if ($field['name'] == "res_destroyed") {
		$title = "Combat";
	}
	else if ($field['name'] == "distance_walked") {
		$title = "Health";
	}
	else if ($field['name'] == "oldest_portal") {
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

	

	if ($field['name'] == "timestamp") {
	}
	else {
?>
		<tr>
			<td><?php echo $field['label'];?></td>
			<td><?php
		if (isset($_SESSION['agent']) && ($field['name'] == "agent")) {
			echo "<strong>".$_SESSION['agent']."</strong>";
		}
		else {
			?><input type="<?php echo $field['type'];?>"
				   name="<?php echo $field['name']; ?>"
				   value="<?php echo $field['type'] == "number" ? "0" : "";?>"
				   <?php echo ($field['type'] == "number") ? "min=\"0\"\n" : "\n";?>
				   <?php echo ($field['type'] == "number") ? "max=\"".pow(10, $field['length'])."\"" : "maxlength=\"\"";?> />
<?php
		}
?>

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
</html>
