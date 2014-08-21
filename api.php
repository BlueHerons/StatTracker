<?php
require_once("config.php");
require_once("code/StatTracker.class.php");
require_once("code/Agent.class.php");
require_once("code/Authentication.class.php");
require_once("vendor/autoload.php");

use Symfony\Component\HttpFoundation\Request;

$mysql = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysql->connect_errno) {
	die(sprintf("%s: %s", $mysql->connect_errno, $mysql->connect_error));
}

$app = new Silex\Application();

// Retrieve basic information about the agent
$app->get("/api/{auth_code}", function($auth_code) use ($app) {
	return $app->json(Agent::lookupAgentByAuthCode($auth_code));
});

// Retrieve badge information for the agent
$app->get("/api/{auth_code}/badges/{what}", function(Request $request, $auth_code, $what) use ($app) {
	$agent = Agent::lookupAgentByAuthCode($auth_code);
	$limit = is_numeric($request->query->get("limit")) ? (int)$request->query->get("limit") : 4;

	switch ($what) {
		case "upcoming":
			$data = $agent->getUpcomingBadges($limit);
			break;
		case "current":
			$data = $agent->getBadges();
			break;
	}
	return $app->json($data);
})->value("what", "current");

// Retrieve ratio information for the agent
$app->get("/api/{auth_code}/ratios", function($auth_code) use ($app) {
	$agent = Agent::lookupAgentByAuthCode($auth_code);
	$data = $agent->getRatios();
	return $app->json($data);
});

$app->run();
?>
