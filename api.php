<?php
require_once("config.php");
require_once("code/StatTracker.class.php");
require_once("code/Agent.class.php");
require_once("code/Authentication.class.php");
require_once("code/OCR.class.php");
require_once("vendor/autoload.php");

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

$mysql = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysql->connect_errno) {
	die(sprintf("%s: %s", $mysql->connect_errno, $mysql->connect_error));
}

$app = new Silex\Application();
$app->register(new Silex\Provider\SessionServiceProvider());
$app['debug'] = true;

$app->get("/api/{auth_code}/my-data/{when}.{format}", function($auth_code, $format) use ($app) {
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
})->assert("format", "json")
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
});

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
})->assert("what", "current|upcoming")
  ->value("what", "current");

// Retrieve ratio information for the agent
$app->get("/api/{auth_code}/ratios", function($auth_code) use ($app) {
	$agent = Agent::lookupAgentByAuthCode($auth_code);

	if (!$agent->isValid()) {
		return $app->abort(404);
	}

	$data = $agent->getRatios();
	return $app->json($data);
});

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
})->assert("view", "breakdown|leaderboard|prediction|trend|graph")
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
});

$app->post("/api/{auth_code}/ocr", function($auth_code) use ($app) {
	$agent = Agent::lookupAgentByAuthCode($auth_code);

	if (!$agent->isValid()) {
		return $app->abort(404);
	}

	if (empty($_FILES) || empty($_FILES['screenshot'])) {
		return $app->abort(400);
	}

	$file = UPLOAD_DIR . OCR::getTempFileName();
	move_uploaded_file($_FILES['screenshot']['tmp_name'], $file);
	$data = OCR::scanAgentProfile($file);

	return $app->json($data);

});

$app->after(function (Request $request, Response $response) {
	$response->headers->set("Cache-control", "max-age=". (60 * 60 * 6) .", private");
	$response->headers->set("Expires", date("D, d M Y H:i:s e", time() + 60 * 60 * 6));
});

$app->run();
?>
