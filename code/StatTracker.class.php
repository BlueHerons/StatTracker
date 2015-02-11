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
			global $db;
			$stmt = $db->query("SELECT stat as `key`, name, `group`, unit, ocr, graph, leaderboard FROM Stats ORDER BY `order` ASC;");
			$rows = $stmt->fetchAll();

			foreach($rows as $row) {
				$stat = new Stat();
				extract($row);
				$stat->stat = $key;
				$stat->name = $name;
				$stat->group = $group;
				$stat->unit = $unit;
				$stat->ocr = $ocr;
				$stat->graphable = $graph;
				$stat->leaderboard = $leaderboard;
				$stat->badges = array();

				$stmt = $db->prepare("SELECT level, amount_required FROM Badges WHERE stat = ? ORDER BY `amount_required` ASC;");
				$stmt->execute(array($stat->stat));

				while ($row2 = $stmt->fetch()) {
					extract($row2);
					$stat->badges[$amount_required] = $level;
				}
				$stmt->closeCursor();

				self::$fields[$key] = $stat;
			}
			$stmt->closeCursor();
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
	 *
	 */
	public static function handleAgentStatsPOST($agent, $postdata) {
		global $db;
		$response = new StdClass();
		$response->error = false;

		if (!$agent->isValid()) {
			$response->error = true;
			$response->message = sprintf("Invalid agent: %s", $agent->name);
		}
		else {
			$stmt = $db->prepare("SELECT COALESCE(MIN(date), CAST(NOW() AS Date)) `min_date` FROM Data WHERE agent = ?");

			try {
				$stmt->execute(array($agent->name));
				extract($stmt->fetch());

				$ts = date("Y-m-d 00:00:00");
				$dt = $postdata['date'] == null ? date("Y-m-d") : $postdata['date'];
				$stmt = $db->prepare("INSERT INTO Data (agent, date, timepoint, stat, value) VALUES (?, ?, DATEDIFF(?, ?) + 1, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value);");

				foreach (self::getStats() as $stat) {
					if (!isset($postdata[$stat->stat])) {
						if ($stat->stat == "innovator") {
							$agent->getStats("latest", true);
							$postdata[$stat->stat] = $agent->stats[$stat->stat];
						}
						else {
							continue;
						}
					}

					$stat_key = $stat->stat;
					$value = filter_var($postdata[$stat->stat], FILTER_SANITIZE_NUMBER_INT);
					$value = !is_numeric($value) ? 0 : $value;

					$stmt->execute(array($agent->name, $dt, $dt, $min_date, $stat_key, $value));

					if ($response->error) {
						break;
					}
				}

				$stmt->closeCursor();

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
			catch (Exception $e) {
				$response->error = true;
				$response->message = sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $db->errorCode(), $db->errorInfo());
			}
			finally {
				$stmt->closeCursor();
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
		global $db;

		$stmt = $db->prepare("CALL GetAPBreakdown(?);");
		$stmt->execute(array($agent->name));
		$stmt->closeCursor();

		$stmt = $db->query("SELECT * FROM APBreakdown ORDER BY grouping, sequence ASC;");

		$data = array();
		$colors = array();

		while ($row = $stmt->fetch()) {
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
		$stmt->closeCursor();

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
		global $db;

		$data = new stdClass();
		if (StatTracker::isValidStat($stat)) {
			$stmt = $db->prepare("CALL GetBadgePrediction(?, ?);");
			$stmt->execute(array($agent->name, $stat));

			$stmt = $db->query("SELECT * FROM BadgePrediction");
			$data = self::buildPredictionResponse($stmt->fetch());
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
		global $db;
		$stmt = $db->prepare("CALL GetGraphForStat(?, ?);");
		$stmt->execute(array($agent->name, $stat));
	
		$stmt = $db->query("SELECT * FROM GraphDataForStat;");
		
		$data = array();
		while ($row = $stmt->fetch()) {
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
		$stmt->closeCursor();

		$response = new stdClass();
		$response->data = $data;
		$response->prediction = self::getPrediction($agent, $stat); // TODO: move elsewhere

		return $response;
	}

	public static function getTrend($agent, $stat, $when) {
		global $db;
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

		$stmt = $db->prepare("CALL GetDailyTrend(?, ?, ?, ?);");
		$stmt->execute(array($agent->name, $stat, $start, $end));
		$stmt->closeCursor();

		$stmt = $db->query("SELECT * FROM DailyTrend");
		
		$data = array();
		while ($row = $stmt->fetch()) {
			$data["dates"][] = $row["date"];
			$data["target"][] = $row["target"];
			$data["value"][] = $row["value"];
		}
		$stmt->closeCursor();

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
		global $db;
		$monday = strtotime('last monday', strtotime('tomorrow'));
		$stmt = null;
		switch ($when) {
			case "this-week":
				$thisweek = date("Y-m-d", $monday);
				$stmt = $db->prepare("CALL GetWeeklyLeaderboardForStat(?, ?);");
				$stmt->execute(array($stat, $thisweek));
				break;
			case "last-week":
				$lastweek = date("Y-m-d", strtotime('7 days ago', $monday));
				$stmt = $db->prepare("CALL GetWeeklyLeaderboardForStat(?, ?);");
				$stmt->execute(array($stat, $lastweek));
				break;
			case "alltime":
			default:
				$stmt = $db->prepare("CALL GetLeaderboardForStat(?);");
				$stmt->execute(array($stat));
				break;
		}
		$stmt->closeCursor();

		$stmt = $db->query("SELECT * FROM LeaderboardForStat;");

		while($row = $stmt->fetch()) {
			$results[] = array(
				"rank" => $row['rank'],
				"agent" => $row['agent'],
				"value" => number_format($row['value']),
				"age" => $row['age']
			);
		}
		$stmt->closeCursor();

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
	public $group;
	public $unit;
	public $graphable;
	public $leaderboard;

	public function hasLeaderboard() {
		return $this->leaderboard > 0;
	}

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
