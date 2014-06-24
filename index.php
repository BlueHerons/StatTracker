<?php
session_start();

require_once("code/credentials.php");
require_once("code/StatTracker.class.php");
require_once("code/Agent.class.php");
require_once("code/Authentication.class.php");
require_once("vendor/autoload.php");

const ENL_GREEN = "#2BED1B";
const RES_BLUE = "#00BFFF";

use Symfony\Component\HttpFoundation\Response;

$mysql = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysql->connect_errno) {
	die(sprintf("%s: %s", $mysql->connect_errno, $mysql->connect_error));
}

$app = new Silex\Application();
$app['debug'] = true;
$app->register(new Silex\Provider\TwigServiceProvider(), array(
	'twig.path' => __DIR__ . "/views",
));

$agent = new Agent();
if (isset($_SESSION['agent'])) {
	$agent = unserialize($_SESSION['agent']);
}

// Default handler. Will match any alphnumeric string. If the page doesn't exist,
// 404
$app->match('/{page}', function ($page) use ($app) {
	if ($page == "dashboard" ||
	    $page == "my-stats" ||
	    $page == "leaderboards") {
	
		return $app['twig']->render("index.twig", array(
			"page" => $page
		));
	}
	else if ($page == "terms-of-use") {
		return $app['twig']->render("terms.html");
	}
	else if ($page == "authenticate") {
		switch ($_REQUEST['action']) {
			case "login":
				return $app->json(Authentication::getInstance()->login());
				break;
			case "callback":
				if (Authentication::getInstance()->callback()) {
					return $app->redirect("./dashboard");
				}
				else {
					$app->abort(500, "An error occured during authentication");
				}
				break;
			case "logout":
				return $app->json(Authentication::getInstance()->logout());
				break;
			default:
				$app->abort(405, "Invalid Authentication action");
		}
	}
	else {
		$app->abort(404);
	}
})->assert('page', '[a-z-]+')
  ->value('page', 'dashboard');

$app->get('/page/{page}', function($page) use ($app, $agent) {
	if ($page == "my-stats") {
		$agent->getLatestStats();
	}

	return $app['twig']->render($page.".twig", array(
		"agent" => $agent,
		"stats" => StatTracker::getStats(),
		"faction_class" => $agent->faction == "R" ? "resistance-agent" : "enlightened-agent",
		"faction_color" => $agent->faction == "R" ? RES_BLUE : ENL_GREEN,
	));
});

$app->get('/data/badges/{what}', function($what) use ($app, $agent) {
	switch ($what) {
		case "upcoming":
			$data = $agent->getUpcomingBadges();
			break;
		case "current":
		default:
			$data = $agent->getBadges();
			break;
	}
	return $app->json($data);
});

$app->get('/data/submissions', function() use ($app, $agent) {
	$data = $agent->getSubmissions();
	return json_encode($data);
});

$app->get('/data/level/{what}', function ($what) use ($app, $agent) {
	switch ($what) {
		case "remaining":
			$data = $agent->getRemainingLevelRequirements();
			break;
		default:
			$data = $agent->getLevel();
			break;
	}

	return $app->json($data);
});

$app->get('/data/{stat}/{view}/{when}', function($stat, $view, $when) use ($app, $agent) {
	$data = "";
	switch ($view) {
		case "breakdown":
			$data = StatTracker::getAPBreakdownJSON($agent);
			break;
		case "leaderboard":
			$data = StatTracker::getLeaderboardJSON($stat, $when);	
			break;
		case "prediction":
			$data = StatTracker::getPredictionJSON($agent, $stat);
			break;
		case "graph":
		default:
			$data = StatTracker::getGraphDataJSON($stat, $agent);
			break;
	}

	return $data;
})->value('when', 'all');

$app->post('/my-stats/submit', function () use ($app, $agent) {
	return StatTracker::handleAgentStatsPost($agent, $_POST);
});

$app->run();

