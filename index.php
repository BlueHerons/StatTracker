<?php
session_start();

require_once("code/credentials.php");
require_once("code/classes/stattracker.class.php");
require_once("code/classes/stat.class.php");
require_once("code/classes/agent.class.php");
require_once("vendor/autoload.php");

const ENL_GREEN = "#00F673";
const RES_BLUE = "#00C4FF";

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

$app->get('/', function () use ($app) {
	return $app->redirect('dashboard');
});

$app->get('/terms-of-use', function () use ($app) {
	return $app['twig']->render("terms.html");
});

$app->get('/page/{page}', function($page) use ($app, $agent) {
	if ($page == "my-stats") {
		$agent->getLatestStats();
	}

	return $app['twig']->render($page.".twig", array(
		"agent" => $agent,
		"stats" => StatTracker::getStats(),
		"faction_color" => $agent->faction == "R" ? RES_BLUE : ENL_GREEN,
	));
});

$app->get('/data/badges', function() use ($app, $agent) {
	$data = StatTracker::getBadgesJSON($agent);
	return $data;
});

$app->get('/data/{stat}/{view}', function($stat, $view) use ($app, $agent) {
	$data = "";
	switch ($view) {
		case "breakdown":
			$data = StatTracker::getAPBreakdownJSON($agent);
			break;
		case "leaderboard":
			$data = StatTracker::getLeaderboardJSON($stat);	
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
			
});

$app->get('/dashboard', function () use ($app) {
	return $app['twig']->render("index.twig", array(
		"page" => "dashboard"
	));
});

$app->get('/my-stats', function () use ($app) {
	return $app['twig']->render("index.twig", array(
		"page" => "my-stats"
	));
});

$app->post('/my-stats/submit', function () use ($app, $agent) {
	return StatTracker::handleAgentStatsPost($agent, $_POST);
});

$app->get('/leaderboards', function () use ($app) {
	return $app['twig']->render("index.twig", array(
		"page" => "leaderboards"
	));
});

$app->run();

