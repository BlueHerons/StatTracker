<?php
namespace BlueHerons\StatTracker;

use BlueHerons\StatTracker\Agent;
use Silex\Application;

use Exception;
use Katzgrau\KLogger\Logger;
use PDO;
use Psr\Log\LogLevel;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use StdClass;

class StatTracker extends Application {

    private static $db;
    private static $stats;

    private $authProvider;
    private $basedir;
    private $baseUrl;
    private $logger;

    public static function db() {
        if (!(self::$db instanceof PDO)) {
            self::$db = new PDO(sprintf("mysql:host=%s;dbname=%s;charset=%s", DATABASE_HOST, DATABASE_NAME, DATABASE_CHARSET), DATABASE_USER, DATABASE_PASS, array(
                  PDO::ATTR_EMULATE_PREPARES   => false
                , PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION
                , PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ));
        }

        return self::$db;
    }

    public function __construct() {
        parent::__construct();

        $this['debug'] = filter_var(StatTracker::getConstant("DEBUG", false), FILTER_VALIDATE_BOOLEAN);

        $this->basedir = dirname($_SERVER['SCRIPT_FILENAME']);
        $this->logger = new Logger(LOG_DIR, $this['debug'] ? LogLevel::DEBUG : LogLevel::INFO);

        $this->register(new \Silex\Provider\SessionServiceProvider());
        $this->register(new \Silex\Provider\TwigServiceProvider(), array(
            'twig.path' => array(
                $this->basedir . "/views",
                $this->basedir . "/resources",
                $this->basedir . "/resources/scripts",
            )
        ));

        $this['twig']->addFilter(new \Twig_SimpleFilter('name_sort', function($array) {
            usort($array, function($a, $b) {
                return strcmp($a->name, $b->name);
            });
            return $array;
        }));
    }

    public function getAgent() {
        return $this['session']->get("agent") === null ? new Agent() : $this['session']->get("agent");
    }

    /**
     * Gets the registered Authentication provider.
     *
     * @return IAuthenticationProvider
     */
    public function getAuthenticationProvider() {
        if ($this->authProvider === null) {
            // Load all auth classes
            $dir = new RecursiveDirectoryIterator(dirname($_SERVER['SCRIPT_FILENAME']) . "/src/");
            $itr = new RecursiveIteratorIterator($dir);
            $files = new RegexIterator($itr, "/.*Provider.php$/", RegexIterator::GET_MATCH);
            foreach ($files as $filename) {
                require_once($filename[0]);
            }

            $dir = new RecursiveDirectoryIterator(dirname(__DIR__));
            $itr = new RecursiveIteratorIterator($dir);
            $files = new RegexIterator($itr, "/.*Provider.php$/", RegexIterator::GET_MATCH);
            foreach ($files as $filename) {
                require_once($filename[0]);
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

            // If an AuthProvider is specfied in config, and it exists, use it
            if (defined("AUTH_PROVIDER")) {
                $this->logger->debug(sprintf("Searching for specified provider %s", constant("AUTH_PROVIDER")));
                foreach ($authClasses as $classname) {
                    $name = explode("\\", $classname);
                    if ($name[sizeof($name)-1] == constant("AUTH_PROVIDER")) {
                        $class = $classname;
                        break;
                    }
                }
            }
            else {
                $class = $authClasses[0];
            }

            $this->logger->debug(sprintf("Using %s as AuthenticationProvider", $class));
            $this->authProvider = new $class($this->getBaseURL(), $this->logger);
        }
        return $this->authProvider;
    }

    public function getBaseURL() {
        return $this->baseUrl;
    }

    public function getContributors() {
        $data = json_decode(file_get_contents($this->basedir . "/composer.json"));
        return $data->authors;
    }

    public function scanProfileScreenshot($filename, $async = true) {
        $ocr = new OCR($this->getStats(), $this->logger);
        return $ocr->scan($filename, $async);
    }

    public function getFileUploadError($code) {
        $message = "";
        if (!is_numeric($code)) {
            $message = "A unknown file upload error occured.";
        }
        else {
            $errors = array(
                0 => "There is no error, the file uploaded with success",
                1 => "The uploaded file exceeds the upload_max_filesize directive in php.ini",
                2 => "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form",
                3 => "The uploaded file was only partially uploaded",
                4 => "No file was uploaded",
                6 => "Missing a temporary folder",
                7 => "Failed to write file to disk",
                8 => "A PHP extension stopped the file upload"
            );

            $message = $errors[$code];
        }

        return $message;
    }

    public function getTeamStats() {
        $team_profile = array();
        $stmt = self::db()->prepare("SELECT GetStatSum(?) `value`");
        foreach ($this->getStats() as $stat) {
            $stmt->execute(array($stat->stat));
            while ($row = $stmt->fetch()) {
                extract($row);
                $team_profile['stats'][$stat->stat] = $value;
            }
        }
        $stmt->closeCursor();

        $stmt = self::db()->prepare("SELECT GetActiveAgentCount() `value`");
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            extract($row);
            $team_profile['agents'] = $value;
        }

        $team_profile['since'] = date('Y-m-d', strtotime("-30 days"));

        return $team_profile;
    }

    /**
     * Sends the autorization code for the given email address to that address. The email includes
     * instructions on how to complete the registration process as well.
     *
     * Most providers should generate an code and use that as a challenge during the registration process. If
     * that is not possible given a provider, then a rather generic email will be sent to the user, instructing
     * to contact the specified ADMIN_AGENT. Providers can also opt to not send any email.
     *
     * @param string $email_address The address to send the registration email to.
     *
     * @return void
     */
    public function sendRegistrationEmail($email_address) {
        $body = $this->getAuthenticationProvider()->getRegistrationEmail($email_address);

        if ($body === false) {
            // Explicit false means no email should be sent
            return;
        }
        else {
            $this->logger->info(sprintf("Sending registration email to %s", $email_address));

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
    }

    public function setBaseURL($request) {
        $this->baseUrl = sprintf("%s://%s%s", $request->getScheme(), $request->getHttpHost(), $request->getBaseUrl());
    }

    /**
     * Gets the list of all possible stats as Stat objects
     *
     * @return array of Stat objects - one for each possible stat
     */
    public static function getStats() {
        if (!is_array(self::$stats)) {
            $stmt = self::db()->query("SELECT stat as `key`, name, `nickname`, `group`, unit, ocr, graph, leaderboard FROM Stats ORDER BY `order` ASC;");
            $rows = $stmt->fetchAll();

            foreach($rows as $row) {
                $stat = new Stat();
                extract($row);
                $stat->stat = $key;
                $stat->name = $name;
                $stat->nickname = $nickname;
                $stat->group = $group;
                $stat->unit = $unit;
                $stat->ocr = $ocr;
                $stat->graphable = $graph;
                $stat->leaderboard = $leaderboard;
                $stat->badges = array();

                $stmt = self::db()->prepare("SELECT level, amount_required FROM Badges WHERE stat = ? ORDER BY `amount_required` ASC;");
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
            $revision_file = dirname(dirname(dirname(__DIR__))) . "/.revision";
            $branch_file = dirname(dirname(dirname(__DIR__))) . "/.branch";

            if (file_exists($revision_file) && file_exists($branch_file)) {
                return substr(file($revision_file)[0], 0, 7) . "-" . file($branch_file)[0];
            }
            else {
                return $default;
            }

            return file_exists($file) ? file($file)[0] : $default;
        }
        else {
            return (!defined($name) || empty(constant($name))) ?
                $default :
                constant($name);
        }
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
            case "two-weeks-ago":
                $twoweeksago = date("Y-m-d", strtotime('14 days ago', $monday));
                $stmt = $this->db()->prepare("CALL GetWeeklyLeaderboardForStat(?, ?);");
                $stmt->execute(array($stat, $twoweeksago));
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

        if ($when == "this-week" || $when == "last-week") {
            $prior = ($when == "this-week") ? self::getLeaderboard($stat, "last-week") : self::getLeaderboard($stat, "two-weeks-ago");
            for ($i = 0; $i < sizeof($results); $i++) {
                for ($j = 0; $j < sizeof($prior); $j++) {
                    if ($results[$i]['agent'] == $prior[$j]['agent']) {
                        $results[$i]['change'] = $prior[$j]['rank'] -  $results[$i]['rank'];
                    }
                }
            }
        }

        return $results;
    }

    public function getDistribution($stat1, $stat2, $factor) {
        if (!$this->isValidStat($stat1) || !$this->isValidStat($stat2) || !is_numeric($factor)) {
            return new \stdClass();
        }
        else {
            $stmt = $this->db()->prepare("CALL GetDistributionForRatio(?, ?, ?);");
            $stmt->execute(array($stat1, $stat2, $factor));
            $stmt = $this->db()->query("SELECT * FROM DistributionForRatio;");

            $dist = new \stdClass();
            $dist->stat = array(
                "stat1" => $stat1,
                "stat2" => $stat2,
                "factor" => $factor);
            
            while ($row = $stmt->fetch()) {
                extract($row);
                
                $dist->distribution->steps[] = $step;
                $dist->distribution->count[] = $count;
            }

            $start = 0;// - $factor;
            $steps = array();
            $counts = array();
            $breaks = array();

            // If dealing with a factor >= 1, always use ints.
            if ($factor >= 1) {
                for ($i = 0; $i < sizeof($dist->distribution->steps); $i++) {
                    $dist->distribution->steps[$i] = intval($dist->distribution->steps[$i]);
                }
            }

            if ($dist->distribution->steps[0] !== 0 &&
                floatval($dist->distribution->steps[0]) !== floatval(0)) {
                    array_unshift($dist->distribution->steps, 0);
                    array_unshift($dist->distribution->count, 0);
            }

            // Add 1 step to the end
            $last = $dist->distribution->steps[count($dist->distribution->steps) - 1];
            array_push($dist->distribution->steps, $last + $factor);
            array_push($dist->distribution->count, 0);

            // Make sure "steps" (each increament of factor) is sequential. If there are more than $THRESHOLD
            // increments with no value, save it as a "break" in continuity
            $THRESHOLD = 5;
            foreach ($dist->distribution->steps as $step) {
                if (($step - $factor) > $start) {
                    if (($step - $start) > ($factor * $THRESHOLD)) {
                        $breaks[] = array($start, $step);
                        array_push($steps, $step);
                        $start = $step + ($factor * $THRESHOLD);
                    }
                    else {
                        foreach (range($start + $factor, $step, $factor) as $n) {
                            array_push($steps, $n);
                            $start = $n;
                        }
                    }
                    
                }
                else {
                    array_push($steps, $step);
                    $start = $step;
                }
            }

            // custom array_search function for precision
            function search_array($value, &$array) {
                for ($i = 0; $i < sizeof($array); $i++) {
                    if (bccomp(floatval($value), floatval($array[$i]), 1) === 0) { 
                        return $i;
                    }
                }
                return false;
            };

            // insert 0 for steps that where previously created
            foreach ($steps as $step) {
                $step = floatval($step);
                $i = search_array($step, $dist->distribution->steps);
                if ($i !== false) {
                    $value = $dist->distribution->count[$i];
                    array_push($counts, $value);
                }
                else {
                    array_push($counts, 0);
                }
            }

            $dist->distribution->breaks = $breaks;
            $dist->distribution->steps = $steps;
            $dist->distribution->count = $counts;

            return $dist;
        }
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
