<?php
class Authentication {

	private static $instance;
	private $client;
	private $plus;

	public static function getInstance() {
		if (self::$instance == null) {
			self::$instance = new Authentication();
		}

		return self::$instance;
	}

	/**
	 * Generates an authorization code for the given email address. If the email address is not
	 * already in the database, it will be inserted. If it already exists, the authorization code
	 * will be updated.
	 *
	 * @param string $email_address the email address retrieved from authentication
	 * @param bool   $newIfExists   Whether or not to issue a new auth code if one already exists
	 *
	 * @return void
	 */
	public static function generateAuthCode($email_address, $newIfExists = false) {
		global $mysql;
		$length = 6;

		$code = md5($email_address);
		$code = str_shuffle($code);
		$start = rand(0, strlen($code) - $length - 1);	
		$code = substr($code, $start, $length);

		if (!$newIfExists) {
			$stmt = $mysql->prepare("SELECT COUNT(*) FROM Agent WHERE email = ?;");
			$stmt->bind_param("s", $email_address);

			if (!$stmt->execute()) {
				die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $stmt->errno, $stmt->error));
			}

			$stmt->bind_result($num_rows);
			$stmt->fetch();
			$stmt->close();
		}

		if ($num_rows != 1 || $newIfExists) {
			$stmt = $mysql->prepare("INSERT INTO Agent (`email`, `auth_code`) VALUES (?, ?) ON DUPLICATE KEY UPDATE auth_code = VALUES(auth_code);");
			$stmt->bind_param("ss", $email_address, $code);

			if (!$stmt->execute()) {
				die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $stmt->errno, $stmt->error));
			}

			$stmt->close();
		}
	}

	/**
	 * Sends the autorization code for the given email address to that address. The email includes
	 * instructions on how to complete the registration process as well.
	 *
	 * @param string $email_address The address to send the respective authorization code to.
	 *
	 * @return void
	 */
	public static function sendAuthCode($email_address) {
		global $mysql;
		require_once("vendor/autoload.php");

		$stmt = $mysql->prepare("SELECT auth_code FROM Agent WHERE email = ?;");
		$stmt->bind_param("s", $email_address);
		
		if (!$stmt->execute()) {
			die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
		}
		
		$stmt->bind_result($auth_code);
		$stmt->fetch();
		$stmt->close();

		$msg = "Thanks for registering with the Blue Heron's Stat Tracker. In order to validate your " .
		       "identity, please message the following code to <strong>@CaptCynicism</strong> in " .
		       "faction comms:<p/>".
		       "<pre>%s</pre> " .
		       "<p/> ".
		       "You will recieve a reply message once you have been activated. This may take up to " .
		       "24 hours. Once you recieve the reply, simply refresh Stat Tracker.";

		$msg = sprintf($msg, $auth_code);

		$transport = Swift_SmtpTransport::newInstance(SMTP_HOST, SMTP_PORT, SMTP_ENCR)
				->setUsername(SMTP_USER)
				->setPassword(SMTP_PASS);

		$mailer = Swift_Mailer::newInstance($transport);

		$message = Swift_Message::newInstance('Stat Tracker Registration')
				->setFrom(array('stats@blueheronsreistance.com' => 'Blue Herons Resistance'))
				->setTo(array($email_address))
				->setBody($msg, 'text/html', 'iso-8859-2');

		$mailer->send($message);
	}

	/**
	 * Updates meta data about the user from the OAuth service on login
	 *
	 * @param string $email_address the primary identifier for the user
	 * @param string $profile_id    the G+ id of the user
	 */
	public static function updateUserMeta($email_address, $profile_id) {
		global $mysql;
		$stmt = $mysql->prepare("UPDATE Agent SET profile_id = ? WHERE email = ?;");
		$stmt->bind_param("ss", $profile_id, $email_address);

		if (!$stmt->execute()) {
			die(sprintf("%s:%s\n(%s) %s", __FILE__, __LINE__, $mysql->errno, $mysql->error));
		}
	}

	private function __construct() {
		$this->client = new Google_Client();
		$this->client->setApplicationName(GOOGLE_APP_NAME);
		$this->client->setClientId(GOOGLE_CLIENT_ID);
		$this->client->setClientSecret(GOOGLE_CLIENT_SECRET);
		$this->client->setRedirectUri(GOOGLE_REDIRECT_URL);

		$this->client->setScopes("https://www.googleapis.com/auth/plus.profile.emails.read");

		$this->plus = new Google_Service_Plus($this->client);
	}

	public function login() {
		$response = new StdClass();
		$response->error = false;

		// Kick off the OAuth process
		if (empty($_SESSION['token'])) {
			$response->status = "authentication_required";
			$response->url = $this->client->createAuthUrl();
			return $response;
		}

		$this->client->setAccessToken($_SESSION['token']);

		if ($this->client->isAccessTokenExpired()) {
			$response->status = "authentication_required";
			$response->url = $this->client->createAuthUrl();
			return $response;
		}

		if (!isset($_SESSION['agent'])) {
			try {
				$me = $this->plus->people->get('me');
				$email_address = "";
				foreach ($me->getEmails() as $email) {
					if ($email->type == "account") {
						$email_address = $email->value;
					}
				}

				if (empty($email_address)) {
					$response->error = true;
					$response->message = "No email address found";
					return $response;
				}

				$response->email = $email_address;
				$agent = Agent::lookupAgentName($email_address);
				if (empty($agent->name) || $agent->name == "Agent") {
					// They need to register
					self::generateAuthCode($email_address);
					self::updateUserMeta($email_address, $me->id);
					self::sendAuthCode($email_address);
					$response->status = "registration_required";
				}
				else {
					// Issue a new auth code
					self::generateAuthCode($email_address, true);
					$agent->getAuthCode(true);
					$_SESSION['agent'] = serialize($agent);
					$response->status = "okay";
					$response->agent = $agent;
				}
			}
			catch (Exception $e) {
				$response->error = true;
				$response->message = $e->getMessage();
				return $response;
			}
		}
		else {
			$agent = unserialize($_SESSION['agent']);
			$response->status = "okay";
			$response->agent = $agent;
		}

		return $response;
	}

	public function callback() {
		if (!isset($_REQUEST['code'])) {
			throw new Exception("Invalid callback parameters");
		}

		$token = $this->getToken();
		if (!$token) {
			throw new Exception("No token available");
		}
	
		return true;
	}

	public function logout() {
		$cookies = explode(';', $_SERVER['HTTP_COOKIE']);
		foreach($cookies as $cookie) {
			$parts = explode('=', $cookie);
			$name = trim($parts[0]);
			setcookie($name, '', time()-1000);
			setcookie($name, '', time()-1000, '/');
		}
		$this->client->revokeToken($_SESSION['token']);
		session_destroy();
		$response = new stdClass();
		$response->status = "logged_out";
		return $response;
	}

	private function getToken() {
		$code = "";
		if (isset($_REQUEST['code'])) {
			$code = $_REQUEST['code'];
		}
		else {
			$code = file_get_contents("php://input");
		}

		try {
			$this->client->authenticate($code);
			$_SESSION['token'] = $this->client->getAccessToken();
		}
		catch (Exception $e) {
			print_r("caught except retrieveing token");
			return $e;
		}

		return true;
	}
}
