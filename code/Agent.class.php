<?php
class Agent {

	public $name;
	public $auth_code;
	public $faction;
	public $level;
	public $stats;

	/**
	 * Returns the registered Agent for the given email address. If no agent is found, a generic
	 * Agent object is returned.
	 *
	 * @param string $email_address 
	 *
	 * @return string Agent object
	 */
	public static function lookupAgentName($email_address) {
		global $mysql;

		$stmt = $mysql->prepare("SELECT agent, faction, auth_code FROM Agent WHERE email = ?;");
		$stmt->bind_param("s", $email_address);

		if (!$stmt->execute()) {
			die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $stmt->errno, $stmt->error));
		}

		$stmt->bind_result($agent, $faction, $auth_code);
		$stmt->fetch();
		$stmt->close();

		if (empty($agent)) {
			return new Agent();
		}
		else {
			$agent = new Agent($agent, $auth_code);
			$agent->faction = $faction;

			return $agent;
		}
	}

	/**
	 * Retruns the registered Agent for the given auth_code. If not agent is found, a generic
	 * Agent object is returned.
	 *
	 * @param string $auth_code
	 *
	 * @return object Agent object
	 */
	public static function lookupAgentByAuthCode($auth_code) {
		global $mysql;

		$stmt = $mysql->prepare("SELECT agent, faction FROM Agent WHERE auth_code = ?;");
		$stmt->bind_param("s", $auth_code);

		if (!$stmt->execute()) {
			die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $stmt->errno, $stmt->error));
		}

		$stmt->bind_result($agent, $faction);
		$stmt->fetch();
		$stmt->close();

		if (empty($agent)) {
			return new Agent();
		}
		else {
			$agent = new Agent($agent, $auth_code);
			$agent->faction = $faction;
	
			return $agent;
		}
	}

	/**
	 * Constructs a new Agent object for the given agent name. This object will include all information
	 * publicly visible from the "Agent Profile" screen in Ingress: Agent name, AP, and badges earned.
	 *
	 * @param string $agent the name of the agent. This name will be searched for in the database. If 
	 * it is not found, an exception will be thrown.  
	 *
	 * @return Agent object with public stats populated.
	 *
	 * @throws Exception if agent name is not found.
	 */
	public function __construct($agent = "Agent", $auth_code = null) {
		if (!is_string($agent)) {
			throw new Exception("Agent name must be a string");
		}

		$agent = self::sanitizeAgentName($agent);

		$this->name = $agent;
		$this->auth_code = $auth_code;

		if ($this->isValid()) {
			$this->getLevel();
			$this->hasSubmitted();
			$this->getLatestStat('ap');
			$this->getLatestUpdate();
		}
	}

	/**
	 * Determines if a valid name has been set for this agent.
	 *
	 * @return boolean true if agent is valid, false otherwise
	 */
	public function isValid() {
		return $this->name != "Agent" && !empty($this->auth_code);
	}

	/**
	 * Sanitizes the agent name.
`	 *
	 * If the agent name exists in the database, that name will also be retrieved.
	 *
	 * @param string $name the name to sanitize
	 *
	 * @return the sanitized name
	 */
	private static function sanitizeAgentName($name) {
		$name = filter_var($name, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);

		global $mysql;

		$sql = "SELECT agent FROM Agent WHERE agent = '%s';";
		$sql = sprintf($sql, $name);
		$res = $mysql->query($sql);

		if (!$res) {
			die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
		}
		
		if ($res->num_rows == 1) {
			return $res->fetch_assoc()['agent'];
		}

		$sql = "SELECT * FROM Data WHERE agent = '%s' LIMIT 1;";
		$sql = sprintf($sql, $name);
		$res = $mysql->query($sql);
	
		if (!$res) {
			die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
		}

		if ($res->num_rows == 1) {
			return $res->fetch_assoc()['agent'];
		}

		return $name;
	}

	/**
	 * Gets the current level for the Agent. Considers AP and badges.
	 *
	 * @returns int current Agent level
	 */
	public function getLevel() {
		if (!isset($this->level)) {
			global $mysql;

			$sql = sprintf("CALL GetCurrentLevel('%s');", $this->name);
			if (!$mysql->query($sql)) {
				die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
			}

			$sql = "SELECT level FROM CurrentLevel;";
			$res = $mysql->query($sql);
			if (!$res) {
				die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
			}

			$this->level = $res->fetch_assoc()['level'];
		}

		return $this->level;
	}

	/**
	 * Determines if the Agent has submitted to Stat Tracker
	 */
	public function hasSubmitted($refresh = false) {
		if (!isset($this->has_submitted) || $refresh) {
			global $mysql;

			$sql = "SELECT count(stat) > 0 AS result FROM Data WHERE stat = 'ap' AND agent = '%s';";
			$sql = sprintf($sql, $this->name);

			$res = $mysql->query($sql);
			if (!$res) {
				die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
			}

			$this->has_submitted = $res->fetch_assoc()['result'] == 1;

		}

		return $this->has_submitted;
	}

	/**
	 * Gets the last timestamp for then this agent's data was updated
	 */
	public function getLatestUpdate($refresh = false) {
		if (!isset($this->latest_update) || $refresh) {
			global $mysql;

			$sql = "SELECT UNIX_TIMESTAMP(MAX(updated)) `updated` FROM Data WHERE agent = '%s';";
			$sql = sprintf($sql, $this->name);

			$res = $mysql->query($sql);
			if (!$res) {
				die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
			}

			$this->latest_update = $res->fetch_assoc()['updated'];

		}

		return $this->latest_update;		
	}

	/**
	 * Gets the latest value of the specified stat.
	 *
	 * @param string|object If string, the stat's database key. If object, a Stat object for the class
	 * #param boolean $refresh whether or not to refresh the cached value
	 *
	 * @return the value for the stat
	 */
	public function getLatestStat($stat, $refresh = false) {
		if (!StatTracker::isValidStat($stat)) {
			throw new Exception(sprintf("'%s' is not a valid stat", $stat));
		}

		if (is_object($stat)) {
			$stat = $stat->stat;
		}
	
		if (!is_array($this->stats) || !isset($this->stats[$stat]) || $refresh) {
			global $mysql;
			
			$sql = "SELECT value, date FROM Data WHERE stat = '%s' AND agent ='%s' ORDER BY date DESC LIMIT 1;";
			$sql = sprintf($sql, $stat, $this->name);
			$res = $mysql->query($sql);
			$row = $res->fetch_assoc();
			
			$this->latest_entry = $row['date'];

			if (!is_array($this->stats)) {
				$this->stats = array();
			}
	
			$this->stats[$stat] = $row['value'];
		}

		return $this->stats[$stat];
	}

	/**
	 * Gets the latest entry for all stats for this agent
	 *
	 * @param boolean $refresh whether or not to refresh the cached value
	 *
	 * @return array with stat database key as the index
	 */
	public function getLatestStats($refresh = false) {
		if (!is_array($this->stats) || $refresh) {
			foreach (StatTracker::getStats() as $stat) {
				$this->getLatestStat($stat->stat, $refresh);
			}
		}

		return $this->stats;
	}

	/**
	 * Gets an array of badges for the current player. array index is the badge name, and the array value 
	 * is the level of the current badge
	 *
	 * @param boolean $refresh Whether or not to refresh the cached values
	 *
	 * @return array the array of current badges the Agent has earned
	 */
	public function getBadges($refresh = false) {
		if (!is_array($this->badges) || $refresh) {
			global $mysql;

			$sql = sprintf("CALL GetCurrentBadges('%s');", $this->name);
			if (!$mysql->query($sql)) {
				die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
			}

			$sql = "SELECT * FROM CurrentBadges;";
			$res = $mysql->query($sql);

			if (!$res) {
				die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
			}

			if (!is_array($this->badges)) {
				$this->badges = array();
			}
			
			while ($row = $res->fetch_assoc()) {
				$badge = str_replace(" ", "_", $row['badge']);
				$badge = strtolower($badge);

				$this->badges[$badge] = strtolower($row['level']);
			}

		}

		return $this->badges;
	}

	/**
	 * Gets the ratios of stats for the given agent.
	 *
	 * @return array top leve entries are a tatio "pair", with a sub array containing keys stat1, stat2, and ratio
	 */
	public function getRatios() {
		if (!is_array($this->ratios)) {
			global $mysql;

			$sql = sprintf("CALL GetRatiosForAgent('%s');", $this->name);
			if (!$mysql->query($sql)) {
				die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
			}
	
			$sql = sprintf("SELECT * FROM RatiosForAgent WHERE badge_1 IS NOT NULL AND badge_2 IS NOT NULL;", $this->name);
			$res = $mysql->query($sql);
			if (!$res) {
				die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
			}

			$this->ratios = array();
			
			while ($row = $res->fetch_assoc()) {
				$badge = str_replace(" ", "_", $row['badge']);
				$badge = strtolower($badge);

				$this->ratio[] = array(
					"stat1" => array(
						"stat" => $row['stat_1'],
						"badge" => strtolower(str_replace(" ", "_", $row['badge_1'])),
						"level" => strtolower($row['badge_1_level']),
						"name" => $row['stat_1_name']
					),
					"stat2" => array(
						"stat" => $row['stat_2'],
						"badge" => strtolower(str_replace(" ", "_", $row['badge_2'])),
						"level" => strtolower($row['badge_2_level']),
						"name" => $row['stat_2_name']
					),
					"ratio" => $row['ratio']
				);
			}
		}

		return $this->ratio;
	}

	/**
	 * Gets the next X badges for the agent, ordered by least time remaining
	 *
	 * @param int $limit number of badges to return, default 3
	 *
	 * @return array of badges
	 */
	public function getUpcomingBadges($limit = 4) {
		if (!is_array($this->upcoming_badges)) {
			global $mysql;

			$sql = sprintf("CALL GetUpcomingBadges('%s');", $this->name);
			if (!$mysql->query($sql)) {
				die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
			}

			$sql = sprintf("SELECT * FROM UpcomingBadges WHERE (days_remaining > 0 OR days_remaining IS NULL) ORDER BY days_remaining ASC LIMIT %s;", $limit);

			$res = $mysql->query($sql);

			if (!$res) {
				die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
			}

			if (!is_array($this->upcoming_badges)) {
				$this->upcoming_badges = array();
			}
			
			while ($row = $res->fetch_assoc()) {
				$badge = str_replace(" ", "_", $row['badge']);
				$badge = strtolower($badge);

				$this->upcoming_badges[] = array(
					"name" => $badge,
					"level" => strtolower($row['next']),
					"progress" => $row['progress'],
					"days_remaining" => $row['days_remaining']
				);
			}

		}

		return $this->upcoming_badges;
	}
}
?>
