<?php
require_once("config.php");
require_once("code/StatTracker.class.php");
require_once("code/Agent.class.php");
require_once("code/Authentication.class.php");
require_once("vendor/autoload.php");

use Symfony\Component\HttpFoundation\Response;

$mysql = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysql->connect_errno) {
	die(sprintf("%s: %s", $mysql->connect_errno, $mysql->connect_error));
}

$app = new Silex\Application();

$app->get("/api/{auth_code}", function($auth_code) use ($app) {
	return $app->json(Agent::lookupAgentByAuthCode($auth_code));
});

$app->run();
?>
