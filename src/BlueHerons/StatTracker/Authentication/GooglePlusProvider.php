<?php
namespace BlueHerons\StatTracker\Authentication;

use BlueHerons\StatTracker\StatTracker;

use Exception;
use Katzgrau\KLogger\Logger;
use Google_Client;
use PDOException;
use StdClass;

use BlueHerons\StatTracker\Agent;
use BlueHerons\StatTracker\AuthenticationProvider;

class GooglePlusProvider implements IAuthenticationProvider {

	private $client;
        private $logger;
	private $plus;

	public function __construct(Logger $logger = null) {
		$this->client = new Google_Client();
		$this->client->setApplicationName(GOOGLE_APP_NAME);
		$this->client->setClientId(GOOGLE_CLIENT_ID);
		$this->client->setClientSecret(GOOGLE_CLIENT_SECRET);
		$this->client->setRedirectUri(GOOGLE_REDIRECT_URL);

		$this->client->setScopes("https://www.googleapis.com/auth/plus.profile.emails.read");

                $this->logger = $logger == null ? new Logger(LOG_DIR) : $logger;

		$this->plus = new \Google_Service_Plus($this->client);
	}

	public function login(StatTracker $StatTracker) {
		$response = new StdClass();
		$response->error = false;

		// Kick off the OAuth process
		if (empty($StatTracker['session']->get("token"))) {
			$response->status = "authentication_required";
			$response->url = $this->client->createAuthUrl();
			return $response;
		}

		$this->client->setAccessToken($StatTracker['session']->get("token"));

		if ($this->client->isAccessTokenExpired()) {
			$response->status = "authentication_required";
			$response->url = $this->client->createAuthUrl();
			return $response;
		}

		if ($StatTracker['session']->get("agent") === null) {
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
					$response->message = "Google did not provide an email address.";
					return $response;
				}

				$response->email = $email_address;
				$agent = Agent::lookupAgentName($email_address);
	
				if (!$agent->isValid()) {
					// They need to register
					$this->generateAuthCode($email_address);
					$this->updateUserMeta($email_address, $me->id);
					$response->status = "registration_required";
                                        $this->logger->info(sprintf("Registration required for %s", $email_address));
				}
				else {
					// Issue a new auth code
					$this->generateAuthCode($email_address, true);
					$this->updateUserMeta($email_address, $me->id);
					$agent->getAuthCode(true);
					$StatTracker['session']->set("agent", $agent);
					$response->status = "okay";
					$response->agent = $agent;
                                        $this->logger->info(sprintf("%s authenticated successfully", $agent->name));
				}
			}
			catch (Exception $e) {
				$response->error = true;
				$response->message = $e->getMessage();
                                $this->logger->error(sprintf("EXCEPTION: %s\n%s:%s", $e->getMessage(), $e->getFile(), $e->getLine()));
				return $response;
			}
		}
		else {
			$agent = $StatTracker['session']->get("agent");

			// Ensure auth_code is valid
			if (Agent::lookupAgentByAuthCode($agent->getAuthCode())->isValid()) {
				$response->status = "okay";
				$response->agent = $agent;
			}
			else {
                                $this->logger->info(sprintf("Expired auth_code for %s. Logging out", $agent->name));
				return $this->logout($StatTracker);
			}
		}

		return $response;
	}

	public function logout(StatTracker $StatTracker) {
                $agent = $StatTracker['session']->get("agent");
		$cookies = explode(';', $_SERVER['HTTP_COOKIE']);
		foreach($cookies as $cookie) {
			$parts = explode('=', $cookie);
			$name = trim($parts[0]);
			setcookie($name, '', time()-1000);
			setcookie($name, '', time()-1000, '/');
		}
		$this->client->revokeToken($StatTracker['session']->get("token"));
		session_destroy();
		$response = new stdClass();
		$response->status = "logged_out";
                $this->logger->info(sprintf("%s logged out", $agent->name));
		return $response;
	}

	public function callback(StatTracker $StatTracker) {
		$code = isset($_REQUEST['code']) ? $_REQUEST['code'] : file_get_contents("php://input");

		try {
			if (!isset($code)) {
				throw new Exception("Google responded incorrectly to the authentication request. Please try again later.");
			}

			$this->client->authenticate($code);
			$StatTracker['session']->set("token", $this->client->getAccessToken());
		}
		catch (Exception $e) {
			error_log("Google authentication callback failure");
			error_log(print_r($e, true));
		}
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
	private function generateAuthCode($email_address, $newIfExists = false) {
		$length = 6;

		$code = md5($email_address);
		$code = str_shuffle($code);
		$start = rand(0, strlen($code) - $length - 1);	
		$code = substr($code, $start, $length);
		$num_rows = 0;

		if (!$newIfExists) {
			$stmt = StatTracker::db()->prepare("SELECT agent FROM Agent WHERE email = ?;");
			$stmt->execute(array($email_address));
			$num_rows = $stmt->rowCount();
			$stmt->closeCursor();
		}

		if ($num_rows != 1 || $newIfExists) {
			try {
				$stmt = StatTracker::db()->prepare("INSERT INTO Agent (`email`, `auth_code`) VALUES (?, ?) ON DUPLICATE KEY UPDATE auth_code = VALUES(auth_code);");
				$stmt->execute(array($email_address, $code));
				$stmt->closeCursor();
			}
			catch (PDOException $e) {
				// Failing to insert an auth code will cause a generic registration email to be sent to the user.
				error_log($e);
			}
		}
	}

	/**
	 * Updates meta data about the user from the OAuth service on login. This isn't used by the app, but helps to
	 * validate who a registering user is.
	 *
	 * @param string $email_address the primary identifier for the user
	 * @param string $profile_id    the G+ id of the user
	 */
	public function updateUserMeta($email_address, $profile_id) {
		try {
			$stmt = StatTracker::db()->prepare("UPDATE Agent SET profile_id = ? WHERE email = ?;");
			$stmt->execute(array($profile_id, $email_address));
			$stmt->closeCursor();
		}
		catch (PDOException $e) {
			// This exception is not vital to functionality, so eat it.
			error_log($e);
		}
	}
}
