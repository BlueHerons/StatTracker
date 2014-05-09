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
			$sql = "SELECT stat, name, unit, graph FROM Stats ORDER BY `order` ASC;";
			$res = $mysql->query($sql);
			if (!is_object($res)) {
				die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
			}

			while ($row = $res->fetch_assoc()) {
				$stat = new Stat();
				$stat->stat = $row['stat'];
				$stat->name = $row['name'];
				$stat->unit = $row['unit'];
				$stat->graphable = $row['graph'];
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

		$code = md5($email_address);
		$code = str_shuffle($code);
		$start = rand(0, strlen($code) - $length - 1);	
		$code = substr($code, $start, $length);

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

		$msg = "Thanks for registering with the Blue Heron's Stat Tracker. In order to validate your " .
		       "identity, please message the following code to <strong>@CaptCynicism</strong> in " .
		       "faction comms: ".
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
				->setFrom(array('stats@blueheronsreistance.com' => 'Blue Herons Resistance'))
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
			$ts = date("Y-m-d H:i:s");
			$stmt = $mysql->prepare("INSERT INTO Data VALUES (?, ?, ?, ?);");
			$stmt->bind_param("sssd", $agent_name, $ts, $stat_key, $value);

			foreach (self::getStats() as $stat) {
				if (!isset($postdata[$stat->stat])) {
					continue;
				}
	
				$agent_name = $agent->name;
				$stat_key = $stat->stat;

				$value = filter_var($postdata[$stat->stat], FILTER_SANITIZE_NUMBER_INT);
				$value = !is_numeric($value) ? 0 : $value;
	
				if (!$stmt->execute()) {
					$response->error = true;
					$response->message = sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error);
				}

				if ($response->error) {
					break;
				}
			}

			$stmt->close();

			if (!$response->error && $agent->getSubmissionCount() <= 1) {
				$response->message = "Your stats have been recieved. Since this was your first submission, predictions are not available. Submit again tomorrow to see your predictions.";
			}
			else if (!$response->error) {
				$response->message = "Your stats have been recieved.";
			}
		}

		return json_encode($response, JSON_NUMERIC_CHECK);
	}

	/**
	 * Generates JSON formatted data for use in a Google Visualization API pie chart.
	 *
	 * @param Agent agent the agent whose data should be used
	 *
	 * @return string JSON string
	 */
	public function getAPBreakdownJSON($agent) {
		global $mysql;
	
		$sql = sprintf("CALL GetRawStatsForAgent('%s');", $agent->name);
		if (!$mysql->query($sql)) {
			die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
                }
		
		$sql = "CALL GetAPBreakdown();";
		if (!$mysql->query($sql)) {
			die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
                }

		$sql = "SELECT * FROM APBreakdown;";
		$res = $mysql->query($sql);
		if (!$res) {
			die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
		}
		
		$data = array();
		$data[] = array("Action", "AP Gained");
		while ($row = $res->fetch_assoc()) {
			$data[] = array($row['name'], $row['ap_gained']);
		}

	 	return json_encode($data, JSON_NUMERIC_CHECK);
	}

	/**
	 * Gets the current badges for the specified agent
	 *
	 * @param Agent the agent
	 *
	 * @return JSON string
	 */
	public static function getBadgesJSON($agent) {
		$data = $agent->getBadges();
		return json_encode($data);
	}

	/**
	 * Gets the prediction line for a stat. If the stat has a badge associated with it, this will also
	 * retrieve the badge name, current level, next level, and percentage complete to attain the next
	 * badge level.
	 *
	 * @param Agent $agent Agent to retrieve prediction for
	 * @param string $stat Stat to retrieve prediction for
	 *
	 * @return JSON string
	 */
	public static function getPredictionJSON($agent, $stat) {
		global $mysql;

		$data = new stdClass();
		if (StatTracker::isValidStat($stat)) {
			$sql = sprintf("CALL GetRawStatsForAgent('%s');", $agent->name);
			if (!$mysql->query($sql)) {
				die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));	
			}

			$sql = sprintf("CALL GetBadgePrediction('%s');", $stat);
			if (!$mysql->query($sql)) {
				die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error    ));        
			}

			$sql = "SELECT * FROM BadgePrediction";
			$res = $mysql->query($sql);
			$row = $res->fetch_assoc();

			$data->stat = $row['Stat'];
			$data->name = $row['Name'];
			$data->unit = $row['unit'];
			$data->badge = $row['Badge'];
			$data->current = $row['Current'];
			$data->next = $row['Next'];
			$data->progress = $row['progress'];
			$data->amount_remaining = $row['Remaining'];
			$data->silver_remaining = $row['silver_remaining'];
			$data->gold_remaining = $row['gold_remaining'];
			$data->platinum_remaining = $row['platinum_remaining'];
			$data->onyx_remaining = $row['onyx_remaining'];
			$data->days_remaining = $row['Days'];
			$data->rate = $row['slope'];
		}

		return json_encode($data, JSON_NUMERIC_CHECK);
	}

	/**
	 * Generates JSON formatted data for use in a Google Visualization API Line graph.
	 *
	 * @param string $stat the stat to generate the data for
	 * @param Agent agent the agent whose data should be used
	 *
	 * @return string JSON string
	 */
	public function getGraphDataJSON($stat, $agent) {
		global $mysql;

		$sql = sprintf("CALL GetRawStatsForAgent('%s');", $agent->name);
		if (!$mysql->query($sql)) {
			die(sprintf("%s: (%s) %s", __LINE__, $mysql->errno, $mysql->error));
		}

		$sql = sprintf("CALL GetGraphDataForStat('%s');", $stat);
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
				$data[] = array_keys($row);
			}
				
			$data[] = array_values($row);
		}

		return json_encode($data, JSON_NUMERIC_CHECK);
	}

	/**
	 * Generates JSON formatted data for a leaderboard
	 *
	 * @param string $stat the stat to generate the leaderboard for
	 *
	 * @return string JSON string
	 */
	public static function getLeaderboardJSON($stat) {
		global $mysql;
		$sql = sprintf("CALL GetLeaderboardForStat('%s');", $stat);
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
				"value" => $row['value'],
				"age" => $row['age']
			);
		}

		return json_encode($results, JSON_NUMERIC_CHECK);
	}
}
?>
