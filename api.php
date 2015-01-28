<?php
require_once("config.php");
require_once("code/autoload.php");
require_once("code/StatTracker.class.php");
require_once("code/Agent.class.php");
require_once("code/OCR.class.php");
require_once("vendor/autoload.php");

use Curl\Curl;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$db = new PDO(sprintf("mysql:host=%s;dbname=%s;charset=utf8", DB_HOST, DB_NAME), DB_USER, DB_PASS, array(
	PDO::ATTR_EMULATE_PREPARES   => false,
	PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
));

$app = new Silex\Application();
$app->register(new Silex\Provider\SessionServiceProvider());
$app['debug'] = true;

// Assert that auth_code and stat parameters, if present, match expected format
$validateRequest = function(Request $request, Silex\Application $app) {
	function validateParameter($param, $regex) {
		if (strlen($param) > 0) {
			return preg_match($regex, $param) === 1;
		}
		else {
			return true;
		}
	}

	// Ensure {auth_code} is 6 hexidecimal digits
	if (!validateParameter($request->get("auth_code"), "/^[a-f0-9]{6}$/")) { return $app->abort(400); }
	// Ensure {stat} is alpha characters and an underscore
	if (!validateParameter($request->get("stat"), "/^[a-z_]+$/")) { return $app->abort(400); }
};

// Pass-though call to GitHub to retrieve everyone how has contributed to the repository
$app->get("/api/contributors", function(Request $request) use ($app) {
	$url = sprintf("https://api.github.com/repos/%s/%s/contributors?per_page=%d", "BlueHerons", "StatTracker", 10);

	$request = new Curl();
	$request->setUserAgent(GROUP_NAME . " Stat Tracker/" . VERSION);
	$request->get($url);

	$response = $request->response;

	return $app->json($response);
});

$app->get("/api/{auth_code}/my-data/{when}.{format}", function($auth_code, $when, $format) use ($app) {
	$agent = Agent::lookupAgentByAuthCode($auth_code);

	$data = new stdClass;

	$data->agent = $agent->name;
	$data->data = array();
	
	// TODO: Refactor for fetching historical data

	$t = new stdClass;
	$t->timestamp = $agent->getLatestUpdate();
	$t->badges = $agent->getBadges();
	$t->stats = $agent->getLatestStats(true);

	$data->data[] = $t;
	
	switch ($format) {
		case "json":
			return $app->json($data);
			break;
	}
})->before($validateRequest)
  ->assert("format", "json")
  ->assert("when",   "latest")
  ->value ("format", "json")
  ->value ("when",   "latest");

// Retrieve basic information about the agent
$app->get("/api/{auth_code}", function($auth_code) use ($app) {
	$agent = Agent::lookupAgentByAuthCode($auth_code);

	if (!$agent->isValid()) {
		return $app->abort(404);
	}

	return $app->json($agent);
})->before($validateRequest);

// Retrieve badge information for the agent
$app->get("/api/{auth_code}/badges/{what}", function(Request $request, $auth_code, $what) use ($app) {
	$agent = Agent::lookupAgentByAuthCode($auth_code);
	
	if (!$agent->isValid()) {
		return $app->abort(404);
	}

	$limit = is_numeric($request->query->get("limit")) ? (int)$request->query->get("limit") : 4;

	switch ($what) {
		case "current":
			$data = $agent->getBadges();
			break;
		case "upcoming":
			$data = $agent->getUpcomingBadges($limit);
			break;
	}

	return $app->json($data);
})->before($validateRequest)
  ->assert("what", "current|upcoming")
  ->value("what", "current");

// Retrieve ratio information for the agent
$app->get("/api/{auth_code}/ratios", function($auth_code) use ($app) {
	$agent = Agent::lookupAgentByAuthCode($auth_code);

	if (!$agent->isValid()) {
		return $app->abort(404);
	}

	$data = $agent->getRatios();
	return $app->json($data);
})->before($validateRequest);

// Retrieve raw or compiled data for a single stat for the agent
$app->get("/api/{auth_code}/{stat}/{view}/{when}.{format}", function($auth_code, $stat, $view, $when, $format) use ($app) {
	$agent = Agent::lookupAgentByAuthCode($auth_code);

	if (!$agent->isValid()) {
		return $app->abort(404);
	}

	$data = "";
	switch ($view) {
		case "breakdown":
			$data = StatTracker::getAPBreakdown($agent);
			break;
		case "leaderboard":
			$data = StatTracker::getLeaderboard($stat, $when);
			break;
		case "prediction":
			$data = StatTracker::getPrediction($agent, $stat);
			break;
		case "trend":
			$data = StatTracker::getTrend($agent, $stat, $when);
			break;
		case "graph":
			$data = StatTracker::getGraphData($stat, $agent);
			break;
		case "raw":
			$agent->getLatestStat($stat);
			$data = new stdClass();
			$data->value = $agent->stats[$stat];
			$data->timestamp = $agent->latest_entry;
			break;
	}

	$response = JsonResponse::create();
	$response->setEncodingOptions($response->getEncodingOptions() | JSON_NUMERIC_CHECK);
	$response->setData($data);

	return $response;
})->before($validateRequest)
  ->assert("view", "breakdown|leaderboard|prediction|trend|graph")
  ->value("stat", "ap")
  ->value("view", "raw")
  ->value("when", "most-recent")
  ->value("format", "json");


// Allow agents to submit stats
$app->post("/api/{auth_code}/submit", function($auth_code) use ($app) {
	$agent = Agent::lookupAgentByAuthCode($auth_code);

	if (!$agent->isValid()) {
		return $app->abort(404);
	}

	$response = StatTracker::handleAgentStatsPost($agent, $_POST);
	$app['session']->set("agent", Agent::lookupAgentByAuthCode($auth_code));

	return $response;
})->before($validateRequest);

$app->post("/api/{auth_code}/ocr", function(Request $request, $auth_code) use ($app) {
	$agent = Agent::lookupAgentByAuthCode($auth_code);

	if (!$agent->isValid()) {
		return $app->abort(404);
	}

	$content_type = explode(";", $request->headers->get("content_type"))[0];
	$file = UPLOAD_DIR . OCR::getTempFileName();

	switch ($content_type) {
		case "application/x-www-form-urlencoded":
			// Not a file upload, but a POST of bytes
			$hndl = fopen($file, "w+");
			fwrite($hndl, file_get_contents("php://input"));
			fclose($hndl);
			break;
		case "multipart/form-data":
			// Typically an HTTP file upload
			move_uploaded_file($_FILES['screenshot']['tmp_name'], $file);
			break;
		default:
			return $app->abort(400, "Bad request of type " . $content_type);
			break;
	}

	$data = OCR::scanAgentProfile($file);

	return $app->json($data);

})->before($validateRequest);

$app->after(function (Request $request, Response $response) {
	$response->headers->set("Cache-control", "max-age=". (60 * 60 * 6) .", private");
	$response->headers->set("Expires", date("D, d M Y H:i:s e", time() + 60 * 60 * 6));
});

$app->run();
?>
