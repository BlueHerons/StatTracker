<?php
session_start();

require_once("code/credentials.php");
require_once("code/StatTracker.class.php");
require_once("code/Agent.class.php");
require_once("vendor/autoload.php");

$mysql = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysql->connect_errno) {
	die(sprintf("%s: %s", $mysql->connect_errno, $mysql->connect_error));
}

$client = new Google_Client();
$client->setApplicationName(GOOGLE_APP_NAME);
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri('postmessage');

$plus = new Google_Service_Plus($client);

$action = $_REQUEST['action'];

switch ($action) {
	case "login":
		$response = new stdClass();

		if (empty($_SESSION['token'])) {
			$code = file_get_contents("php://input");
			$client->authenticate($code);
			$token = "";
			try {
				$token = $client->getAccessToken();
			}
			catch (Exception $e) {
				print_r($e);
			}
			$_SESSION['token'] = $token;
		}
		else {
		}

		$client->setAccessToken($_SESSION['token']);

		if ($client->isAccessTokenExpired()) {
			$code = file_get_contents("php://input");
			$client->authenticate($code);
			$token = $client->getAccessToken();

			$_SESSION['token'] = $token;
		}

		$me = $plus->people->get('me');
		$email_address = "";
		foreach ($me->getEmails() as $email) {
			if ($email->type == "account") {
				$email_address = $email->value;
			}
		}

		if (empty($email_address)) {
			die("this shouldn't happen");
		}

		$response->email = $email_address;
		$agent = Agent::lookupAgentName($email_address);
		if (empty($agent->name) || $agent->name == "Agent") {
			// They need to registera
			StatTracker::generateAuthCode($email_address);
			StatTracker::sendAuthCode($email_address);
			$response->status = "registration_required";
		}
		else {
			$response->status = "okay";
			$response->agent = $agent;
			$_SESSION['agent'] = serialize($agent);
		}

		echo json_encode($response);		

		break;

	case "logout":
		$token = $_SESSION['token'];
		$client->revokeToken($token);
		$_SESSION['token'] = "";
		session_destroy();
		break;

	case "callback":

		break;
	default:
		die("invalid action");
		break;
}


?>
