<?php
class StatTracker {

	private static $fields;

	/**
	 * Gets the list of all possible stats as Stat objects
	 *
	 * @return array of Stat objects - one for each possible stat
	 */
	public static function getStats() {
		if (!is_array(self::$fields)) {
			global $mysql;
			$sql = "SELECT stat, name, unit, ocr, graph, leaderboard FROM Stats ORDER BY `order` ASC;";
			$res = $mysql->query($sql);
			if (!is_object($res)) {
				die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
			}

			while ($row = $res->fetch_assoc()) {
				$stat = new Stat();
				$stat->stat = $row['stat'];
				$stat->name = $row['name'];
				$stat->unit = $row['unit'];
				$stat->ocr = $row['ocr'];
				$stat->graphable = $row['graph'];
				$stat->leaderboard = $row['leaderboard'];
				$stat->badges = array();

				$sql = "SELECT level, amount_required FROM Badges WHERE stat = '%s' ORDER BY `amount_required` ASC;";
				$sql = sprintf($sql, $stat->stat);
				$res2 = $mysql->query($sql);
				if (!is_object($res)) {
					die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
				}

				while ($row2 = $res2->fetch_assoc()) {
					$stat->badges[$row2['amount_required']] = $row2['level'];
				}

				self::$fields[$row['stat']] = $stat;
			}
		}

		return self::$fields;
	}

	/**
	 * Determines if the given parameter is a valid stat
	 *
	 * @param mixed $stat String of stat key, or Stat object
	 *
	 * @return true if valid stat, false otherwise
	 */
	public static function isValidStat($stat) {
		if (is_object($stat)) {
			return in_array($stat->stat, array_keys(StatTracker::getStats()));
		}
		else if (is_string($stat)) {
			return in_array($stat, array_keys(StatTracker::getStats()));
		}

		return false;
	}

	/**
	 * Generates an authorization code for the given email address. If the email address is not
	 * already in the database, it will be inserted. If it already exists, the authorization code
	 * will be updated.
	 *
	 * @param string $email_address the email address retrieved from authentication
	 *
	 * @return void
	 */
	public static function generateAuthCode($email_address) {
		global $mysql;
		$length = 6;

		$stmt = $mysql->prepare("SELECT COUNT(*) FROM Agent WHERE auth_code = ?;");
		$stmt->bind_param("s", $auth_code);
		$stmt->bind_result($num_rows);

		do {
			$code = md5($email_address);
			$code = str_shuffle($code);
			$start = rand(0, strlen($code) - $length - 1);
			$code = substr($code, $start, $length);

			$auth_code = $code;

			if (!$stmt->execute()) {
				die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $stmt->errno, $stmt->error));
			}
			$stmt->fetch();
		}
		while ($num_rows != 0);
		$stmt->close();

		$stmt = $mysql->prepare("SELECT COUNT(*) FROM Agent WHERE email = ?;");
		$stmt->bind_param("s", $email_address);
		
		if (!$stmt->execute()) {
			die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $stmt->errno, $stmt->error));
		}

		$stmt->bind_result($num_rows);
		$stmt->fetch();
		$stmt->close();

		if ($num_rows == 1) {
			$stmt = $mysql->prepare("UPDATE Agent SET auth_code = ? WHERE email = ?;");
			$stmt->bind_param("ss", $code, $email_address);
		}
		else {
			$stmt = $mysql->prepare("INSERT INTO Agent (`email`, `auth_code`) VALUES (?, ?);");
			$stmt->bind_param("ss", $email_address, $code);
		}

		if (!$stmt->execute()) {
			die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $stmt->errno, $stmt->error));
		}

		$stmt->close();
	}

	/**
	 * Sends the autorization code for the given email address to that address. The email includes
	 * instructions on how to complete the registration process as well.
	 *
	 * @param string $email_address The address to send the respective authorization code to.
	 *
	 * @return void
	 */
	public static function sendAuthCode($email_address) {
		global $mysql;
		require_once("vendor/autoload.php");

		$stmt = $mysql->prepare("SELECT auth_code FROM Agent WHERE email = ?;");
		$stmt->bind_param("s", $email_address);
		
		if (!$stmt->execute()) {
			die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
		}
		
		$stmt->bind_result($auth_code);
		$stmt->fetch();
		$stmt->close();

		$msg = "Thanks for registering with ". GROUP_NAME ."'s Stat Tracker. In order to validate your " .
		       "identity, please message the following code to <strong>@". ADMIN_AGENT ."</strong> in " .
		       "faction comms: ".
		       "<p/> ".
		       "<pre>%s</pre> " .
		       "<p/> ".
		       "You will recieve a reply message once you have been activated. This may take up to " .
		       "24 hours. ";

		$msg = sprintf($msg, $auth_code);

		$transport = Swift_SmtpTransport::newInstance(SMTP_HOST, SMTP_PORT, SMTP_ENCR)
				->setUsername(SMTP_USER)
				->setPassword(SMTP_PASS);

		$mailer = Swift_Mailer::newInstance($transport);

		$message = Swift_Message::newInstance('Stat Tracker Registration')
				->setFrom(array(GROUP_EMAIL => GROUP_NAME))
				->setTo(array($email_address))
				->setBody($msg, 'text/html', 'iso-8859-2');

		$mailer->send($message);
	}

	/**
	 *
	 */
	public static function handleAgentStatsPOST($agent, $postdata) {
		global $mysql;
		$response = new StdClass();
		$response->error = false;

		if (!$agent->isValid()) {
			$response->error = true;
			$response->message = sprintf("Invalid agent: %s", $agent->name);
		}
		else {
			$agent_name = $agent->name;
			$stmt = $mysql->prepare("SELECT COALESCE(MIN(date), CAST(NOW() AS Date)) FROM Data WHERE agent = ?");
			$stmt->bind_param("s", $agent_name);
			$stmt->bind_result($min_date);

			if (!$stmt->execute()) {
					$response->error = true;
					$response->message = sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error);
					return json_encode($response, JSON_NUMERIC_CHECK);
			}
			$stmt->fetch();
			$stmt->close();

			$ts = date("Y-m-d 00:00:00");
			$dt = date("Y-m-d");
			$stmt = $mysql->prepare("INSERT INTO Data (agent, date, timepoint, timestamp, stat, value) VALUES (?, ?, DATEDIFF(NOW(), ?) + 1, ?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value);");
			$stmt->bind_param("sssssd", $agent_name, $dt, $min_date, $ts, $stat_key, $value);

			foreach (self::getStats() as $stat) {
				if (!isset($postdata[$stat->stat])) {
					if ($stat->stat == "innovator") {
						$agent->getLatestStats(true);
						$postdata[$stat->stat] = $agent->stats[$stat->stat];
					}
					else {
						continue;
					}
				}
	
				$agent_name = $agent->name;
				$stat_key = $stat->stat;

				$value = filter_var($postdata[$stat->stat], FILTER_SANITIZE_NUMBER_INT);
				$value = !is_numeric($value) ? 0 : $value;
	
				if (!$stmt->execute()) {
					$response->error = true;
					$response->message = sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $stmt->errno, $stmt->error);
				}

				if ($response->error) {
					break;
				}
			}

			$stmt->close();

			// Need to refresh stored session data
			$agent = Agent::lookupAgentByAuthCode($agent->auth_code);
			$_SESSION['agent'] = serialize($agent);

			$ts = strtotime($dt);

			if (!$response->error) {
				$response->message = sprintf("Your stats for %s have been received.", date("l, F j", $ts));

				if (!$agent->hasSubmitted()) {
					$response->message .= " Since this was your first submission, predictions are not available. Submit again tomorrow to see your predictions.";
				}
			}
		}

		return json_encode($response, JSON_NUMERIC_CHECK);
	}

	/**
	 * Calculates the appropriate foreground color based on the given background color
	 *
	 * @param string $color hex color string
	 *
	 * @return string hex color string
	 */
	public static function getFGColor($color) {
		$matches = array();
		if (preg_match("/(#)?([A-Fa-f0-9]{6})/", $color, $matches)) {
			preg_match("/([A-Fa-f0-9]{2})([A-Fa-f0-9]{2})([A-Fa-f0-9]{2})/", $matches[2], $matches);
			$sum = (0.213 * hexdec($matches[1])) +
			       (0.715 * hexdec($matches[2])) +
			       (0.072 * hexdec($matches[3]));

			return $sum < 0.5 ? "#FFF" : "#000";
		}
		else {
			return "#FFF";
		}
	}

	/**
	 * Generates JSON formatted data for use in a Google Visualization API pie chart.
	 *
	 * @param Agent agent the agent whose data should be used
	 *
	 * @return string Object AP Breakdown object
	 */
	public function getAPBreakdown($agent) {
		global $mysql;
	
		$sql = sprintf("CALL GetAPBreakdown('%s');", $agent->name);
		if (!$mysql->query($sql)) {
			die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
                }

		$sql = "SELECT * FROM APBreakdown ORDER BY grouping, sequence ASC;";
		$res = $mysql->query($sql);
		if (!$res) {
			die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
		}
		
		$data = array();
		$colors = array();
		//$data[] = array("Action", "AP Gained");
		while ($row = $res->fetch_assoc()) {
			$data[] = array($row['name'], $row['ap_gained']);
			if ($row['grouping'] == 1) {
				$color =$agent->faction == "R" ? ENL_GREEN : RES_BLUE;
			}
			else if ($row['grouping'] == 3) {
				$color = $agent->faction == "R" ? RES_BLUE : ENL_GREEN;
			}
			else {
				$color = "#999";
			}
			$colors[] = $color;
		}

	 	return array("data" => $data, "slice_colors" => $colors);
	}

	/**
	 * Gets the prediction line for a stat. If the stat has a badge associated with it, this will also
	 * retrieve the badge name, current level, next level, and percentage complete to attain the next
	 * badge level.
	 *
	 * @param Agent $agent Agent to retrieve prediction for
	 * @param string $stat Stat to retrieve prediction for
	 *
	 * @return Object prediciton object
	 */
	public static function getPrediction($agent, $stat) {
		global $mysql;

		$data = new stdClass();
		if (StatTracker::isValidStat($stat)) {
			$sql = sprintf("CALL GetBadgePrediction('%s', '%s');", $agent->name, $stat);
			if (!$mysql->query($sql)) {
				die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error    ));        
			}

			$sql = "SELECT * FROM BadgePrediction";
			$res = $mysql->query($sql);
			$row = $res->fetch_assoc();

			$data = self::buildPredictionResponse($row);
		}

		return $data;
	}

	/**
	 * Generates JSON formatted data for use in a line graph.
	 *
	 * @param string $stat the stat to generate the data for
	 * @param Agent agent the agent whose data should be used
	 *
	 * @return string Object Graph Data object
	 */
	public static function getGraphData($stat, $agent) {
		global $mysql;

		$sql = sprintf("CALL GetGraphForStat('%s', '%s');", $agent->name, $stat);
		if (!$mysql->query($sql)) {
			die(sprintf("%s: (%s) %s", __LINE__, $mysql->errno, $mysql->error));
		}
	
		$sql = "SELECT * FROM GraphDataForStat;";
		$res = $mysql->query($sql);

		if (!$res) {
			die(sprintf("%s: (%s) %s", __LINE__, $mysql->errno, $mysql->error));
		}
		
		$data = array();
		while ($row = $res->fetch_assoc()) {
			if (sizeof($data) == 0) {
				foreach (array_keys($row) as $key) {
					$series = new stdClass();
					$series->name = $key;
					$series->data = array();
					$data[] = $series;
				}
			}

			$i = 0;
			foreach (array_values($row) as $value) {
				$data[$i]->data[] = $value;

				$i++;
			}
		}

		$response = new stdClass();
		$response->data = $data;
		$response->prediction = self::getPrediction($agent, $stat); // TODO: move elsewhere

		return $response;
	}

	public static function getTrend($agent, $stat, $when) {
		global $mysql;
		$start = "";
		$end = "";

		switch ($when) {
			case "last-week":
				$start = date("Y-m-d", strtotime("last monday", strtotime("6 days ago")));
				$end = date("Y-m-d", strtotime("next sunday", strtotime("8 days ago")));
				break;
			case "this-week":
			case "weekly":
			default:
				$start = date("Y-m-d", strtotime("last monday", strtotime("tomorrow")));
				$end = date("Y-m-d", strtotime("next sunday", strtotime("yesterday")));
				break;
		}

		$sql = sprintf("CALL GetDailyTrend('%s', '%s', '%s', '%s');", $agent->name, $stat, $start, $end);
		if (!$mysql->query($sql)) {
			die(sprintf("%s: (%s) %s", __LINE__, $mysql->errno, $mysql->error));
		}

		$sql = "SELECT * FROM DailyTrend";
		$res = $mysql->query($sql);

		if (!$res) {
			die(sprintf("%s: (%s) %s", __LINE__, $mysql->errno, $mysql->error));
		}
		
		$data = array();
		while ($row = $res->fetch_assoc()) {
			$data["dates"][] = $row["date"];
			$data["target"][] = $row["target"];
			$data["value"][] = $row["value"];
		}

		return $data;
	}

	/**
	 * Generates JSON formatted data for a leaderboard
	 *
	 * @param string $stat the stat to generate the leaderboard for
	 * @param string #when the timeframe to retrieve the leaderboard for
	 *
	 * @return string JSON string
	 */
	public static function getLeaderboard($stat, $when) {
		global $mysql;
		$monday = strtotime('last monday', strtotime('tomorrow'));
		switch ($when) {
			case "this-week":
				$thisweek = date("Y-m-d", $monday);
				$sql = sprintf("CALL GetWeeklyLeaderboardForStat('%s', '%s');", $stat, $thisweek);
				break;
			case "last-week":
				$lastweek = date("Y-m-d", strtotime('7 days ago', $monday));
				$sql = sprintf("CALL GetWeeklyLeaderboardForStat('%s', '%s');", $stat, $lastweek);
				break;
			case "alltime":
			default:
				$sql = sprintf("CALL GetLeaderboardForStat('%s');", $stat);
				break;
		}

		if (!$mysql->query($sql)) {
			die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
		}

		$sql = "SELECT * FROM LeaderboardForStat;";
		$res = $mysql->query($sql);
		$results = array();

		while($row = $res->fetch_assoc()) {
			$results[] = array(
				"rank" => $row['rank'],
				"agent" => $row['agent'],
				"value" => number_format($row['value']),
				"age" => $row['age']
			);
		}

		return $results;
	}

	private function buildPredictionResponse($row) {
		$data = new stdClass();

		$data->stat = $row['stat'];
		$data->name = $row['name'];
		$data->unit = $row['unit'];
		$data->badge = $row['badge'];
		$data->current = $row['current'];
		$data->next = $row['next'];
		$data->progress = $row['progress'];
		$data->amount_remaining = $row['remaining'];
		$data->silver_remaining = $row['silver_remaining'];
		$data->gold_remaining = $row['gold_remaining'];
		$data->platinum_remaining = $row['platinum_remaining'];
		$data->onyx_remaining = $row['onyx_remaining'];
		$data->days_remaining = $row['days'];
		$data->rate = $row['rate'];

		return $data;
	}
}

class Stat {

	public $stat;
	public $name;
	public $unit;
	public $graphable;
	public $leaderboard;

	public function hasAllTimeLeaderboard() {
		return ($this->leaderboard & 0x1) == 1;
	}

	public function hasMonthlyLeaderboard() {
		return ($this->leaderboard & 0x2) == 2;
	}

	public function hasWeeklyLeaderboard() {
		return ($this->leaderboard & 0x4) == 4;
	}
}
?>
