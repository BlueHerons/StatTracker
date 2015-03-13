<?php
namespace BlueHerons\StatTracker;

use BlueHerons\StatTracker\Agent;
use Silex\Application;

use Exception;
use PDO;
use StdClass;

class StatTracker extends Application {

        private static $db;
	private static $stats;
        
        private $basedir;
        private $authProvider;

        public static function db() {
            if (!(self::$db instanceof PDO)) {
                self::$db = new PDO(sprintf("mysql:host=%s;dbname=%s;charset=utf8", DB_HOST, DB_NAME), DB_USER, DB_PASS, array(
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ));
            }

            return self::$db;
        }

        public function __construct() {
            $this->basedir = dirname(dirname(dirname(__DIR__)));

            parent::__construct();
            $this['debug'] = true;
            $this->register(new \Silex\Provider\SessionServiceProvider());
            $this->register(new \Silex\Provider\TwigServiceProvider(), array(
                'twig.path' => array(
                    $this->basedir . "/views",
                    $this->basedir . "/resources",
                    $this->basedir . "/resources/scripts",
                )
            ));
        }

        public function getAgent() {
            return $this['session']->get("agent") === null ? new Agent() : $this['session']->get("agent");
        }
	
        /**
	 * Gets the registered Authentication provider.
	 *
	 * @see 
	 *
	 * @return IAuthenticationProvider
	 */
        public function getAuthenticationProvider() {
            if ($this->authProvider === null) {
                // Load all auth classes
		foreach (glob(__DIR__ ."/Authentication/*Provider.php") as $filename) {
			require_once($filename);
		}

		$allClasses = get_declared_classes();
		$authClasses = array();

		foreach ($allClasses as $class) {
		    $reflector = new \ReflectionClass($class);
	            if ($reflector->implementsInterface("\BlueHerons\StatTracker\Authentication\IAuthenticationProvider")) {
		        $authClasses[] = $class;
		    }
		}

		if (sizeof($authClasses) == 0) {
		    die("No Authentication providers found");
		    return null;
		}

		// Instantiate the first one found
		$class = $authClasses[0];
                $this->authProvider = new $class;
            }

            return $this->authProvider;
        }

        public function scanAgentProfile($filename) {
            // TODO: Rewrite this implementation. This is just a hack to get around code restructuing
            OCR::scanAgentProfile($filename, $this->getStats());
        }

	/**
	 * Sends the autorization code for the given email address to that address. The email includes
	 * instructions on how to complete the registration process as well.
	 *
	 * Most providers should generate an auth_code and use that as a challenge during the registration process. If
	 * that is not possible given a provider, then a rather generic email will be sent to the user, instructing
	 * to contact the specified ADMIN_AGENT.
	 *
	 * @param string $email_address The address to send the registration email to.
	 *
	 * @return void
	 */
	public function sendRegistrationEmail($email_address) {
		$stmt = $this->db()->prepare("SELECT auth_code FROM Agent WHERE email = ?;");
		$stmt->execute(array($email_address));
		$msg = "";

		// If no auth code is found, instruct user to contact the admin agent.
		if ($stmt->rowCount() == 0) {
			$stmt->closeCursor();
			$msg = "Thanks for registering with " . GROUP_NAME . "'s Stat Tracker. In order to complete your " .
			       "registration, please contact <strong>" . ADMIN_AGENT . "</strong> through your secure chat ".
			       "and ask them to enable access for you.";
		}
		else {
			extract($stmt->fetch());
			$stmt->closeCursor();

			$msg = "Thanks for registering with " . GROUP_NAME . "'s Stat Tracker. In order to validate your " .
			       "identity, please message the following code to <strong>@" . ADMIN_AGENT . "</strong> in " .
			       "faction comms:".
			       "<p/>%s<p/>" .
			       "You will recieve a reply message once you have been activated. This may take up to " .
			       "24 hours. Once you recieve the reply, simply refresh Stat Tracker.".
			       "<p/>".
			       $_SERVER['HTTP_REFERER'];

			$msg = sprintf($msg, $auth_code);
		}

		$transport = \Swift_SmtpTransport::newInstance(SMTP_HOST, SMTP_PORT, SMTP_ENCR)
				->setUsername(SMTP_USER)
				->setPassword(SMTP_PASS);

		$mailer = \Swift_Mailer::newInstance($transport);

		$message = \Swift_Message::newInstance('Stat Tracker Registration')
				->setFrom(array(GROUP_EMAIL => GROUP_NAME))
				->setTo(array($email_address))
				->setBody($msg, 'text/html', 'iso-8859-2');

		$mailer->send($message);
	}

	/**
	 * Gets the list of all possible stats as Stat objects
	 *
	 * @return array of Stat objects - one for each possible stat
	 */
	public function getStats() {
		if (!is_array(self::$stats)) {
			$stmt = $this->db()->query("SELECT stat as `key`, name, `group`, unit, ocr, graph, leaderboard FROM Stats ORDER BY `order` ASC;");
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

				$stmt = $this->db()->prepare("SELECT level, amount_required FROM Badges WHERE stat = ? ORDER BY `amount_required` ASC;");
				$stmt->execute(array($stat->stat));

				while ($row2 = $stmt->fetch()) {
					extract($row2);
					$stat->badges[$amount_required] = $level;
				}
				$stmt->closeCursor();

				self::$stats[$key] = $stat;
			}
			$stmt->closeCursor();
		}

		return self::$stats;
	}

	/**
	 * Determines if the given string is a validly formatted date
	 *
	 * @param string $date String containing a potential date
	 *
	 * @return true if the string is a valid formatted date, false otherwise
	 */
	public function isValidDate($date) {
		return preg_match("/[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}/", $date);
	}

	/**
	 * Determines if the given parameter is a valid stat
	 *
	 * @param mixed $stat String of stat key, or Stat object
	 *
	 * @return true if valid stat, false otherwise
	 */
	public function isValidStat($stat) {
		if (is_object($stat)) {
			return in_array($stat->stat, array_keys($this->getStats()));
		}
		else if (is_string($stat)) {
			return in_array($stat, array_keys($this->getStats()));
		}

		return false;
	}

	/**
	 * Returns the value of a constant if it is defined, or null if it is not.
	 *
	 * @param string $name the name of the constant
	 * @param mixed $default default value to return if the named constant is not defined.
	 *
	 * @return value of the named constant if it is defined, null otherwise
	 */
	public static function getConstant($name, $default = null) {
		if ($name == "VERSION") {
			$file = dirname(dirname(dirname(__DIR__))) . "/VERSION";
			return file_exists($file) ? file($file)[0] : $default;
		}
		else {
			return (!defined($name) || empty(constant($name))) ? 
				$default :
				constant($name);
		}
	}

	/**
	 *
	 */
	public static function handleAgentStatsPOST($agent, $postdata) {
		$response = new StdClass();
		$response->error = false;

		if (!$agent->isValid()) {
			$response->error = true;
			$response->message = sprintf("Invalid agent: %s", $agent->name);
		}
		else {
			$stmt = $this->db()->prepare("SELECT COALESCE(MIN(date), CAST(NOW() AS Date)) `min_date` FROM Data WHERE agent = ?");

			try {
				$stmt->execute(array($agent->name));
				extract($stmt->fetch());

				$ts = date("Y-m-d 00:00:00");
				$dt = $postdata['date'] == null ? date("Y-m-d") : $postdata['date'];
				$stmt = $this->db()->prepare("INSERT INTO Data (agent, date, timepoint, stat, value) VALUES (?, ?, DATEDIFF(?, ?) + 1, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value);");

				foreach ($this->getStats() as $stat) {
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
				$response->message = sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $this->db()->errorCode(), $this->db()->errorInfo());
			}
			finally {
				$stmt->closeCursor();
			}

		}

		return $response;
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
	 * Generates JSON formatted data for a leaderboard
	 *
	 * @param string $stat the stat to generate the leaderboard for
	 * @param string #when the timeframe to retrieve the leaderboard for
	 *
	 * @return string JSON string
	 */
	public function getLeaderboard($stat, $when) {
		$monday = strtotime('last monday', strtotime('tomorrow'));
		$stmt = null;
		switch ($when) {
			case "this-week":
				$thisweek = date("Y-m-d", $monday);
				$stmt = $this->db()->prepare("CALL GetWeeklyLeaderboardForStat(?, ?);");
				$stmt->execute(array($stat, $thisweek));
				break;
			case "last-week":
				$lastweek = date("Y-m-d", strtotime('7 days ago', $monday));
				$stmt = $this->db()->prepare("CALL GetWeeklyLeaderboardForStat(?, ?);");
				$stmt->execute(array($stat, $lastweek));
				break;
			case "alltime":
			default:
				$stmt = $this->db()->prepare("CALL GetLeaderboardForStat(?);");
				$stmt->execute(array($stat));
				break;
		}
		$stmt->closeCursor();

		$stmt = $this->db()->query("SELECT * FROM LeaderboardForStat;");

		while($row = $stmt->fetch()) {
			$results[] = array(
				"rank" => $row['rank'],
				"agent" => $row['agent'],
				"faction" => $row['faction'],
				"value" => number_format($row['value']),
				"age" => $row['age']
			);
		}
		$stmt->closeCursor();

		return $results;
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
