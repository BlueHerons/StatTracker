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

    public function __construct($base_url, Logger $logger) {
        $this->client = new Google_Client();
        $this->client->setApplicationName(GOOGLE_APP_NAME);
        $this->client->setClientId(GOOGLE_CLIENT_ID);
        $this->client->setClientSecret(GOOGLE_CLIENT_SECRET);
        $this->client->setRedirectUri(sprintf("%s/authenticate?action=callback", $base_url));
        $this->client->setScopes("https://www.googleapis.com/auth/plus.profile.emails.read");
        $this->logger = $logger == null ? new Logger(LOG_DIR) : $logger;
        $this->plus = new \Google_Service_Plus($this->client);
    }

    public function login(StatTracker $StatTracker) {
        $response = new StdClass();
        $response->error = false;

        // Kick off the OAuth process
        if (empty($StatTracker['session']->get("token"))) {
            return AuthResponse::authenticationRequired($this);
        }

        $this->client->setAccessToken($StatTracker['session']->get("token"));

        if ($this->client->isAccessTokenExpired()) {
            return AuthResponse::authenticationRequired($this);
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
                    return AuthResponse::error("Google did not provide an email address.");
                }

                $agent = Agent::lookupAgentName($email_address);

                if (!$agent->isValid()) {
                    // Could be no token, or new user.
                    // If a name is present, they have been approved, so generate a token and proceed
                    if (!empty($agent->name)) {
                        $this->generateAPIToken($agent);
                        $agent = Agent::lookupAgentName($email_address);
                        if (!$agent->isValid()) {
                            $response = AuthResponse::error("Not a valid agent");
                        }
                        else {
                            $StatTracker['session']->set("agent", $agent);
                            $response = AuthResponse::okay($agent);
                        }
                    }
                    else {
                        // They need to register, this code is a challenge
                        $this->generateAuthCode($email_address);
                        $response= AuthResponse::registrationRequired(sprintf("An email has been sent to<br/><strong>%s</strong><br/>with steps to complete registration", $email_address), $email_address);
                        $this->logger->info(sprintf("Registration required for %s", $email_address));
                    }
                }
                else {
                    $StatTracker['session']->set("agent", $agent);
                    $response = AuthResponse::okay($agent);
                    $this->logger->info(sprintf("%s authenticated successfully", $agent->name));
                }
            }
            catch (Exception $e) {
                $response::error($e->getMessage());
                $this->logger->error(sprintf("EXCEPTION: %s\n%s:%s", $e->getMessage(), $e->getFile(), $e->getLine()));
                return $response;
            }
        }
        else {
            $agent = $StatTracker['session']->get("agent");

            // Ensure token is valid
            if (Agent::lookupAgentByToken($agent->getToken())->isValid()) {
                $response = AuthResponse::okay($agent);
            }
            else {
                $this->logger->info(sprintf("Expired token for %s. Logging out", $agent->name));
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
        $response = AuthResponse::loggedOut();
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

    public function token(StatTracker $StatTracker) {
        $access_token = isset($_REQUEST['token']) ? $_REQUEST['token'] : file_get_contents("php://input");
        try {
            if (!isset($access_token)) {
                throw new Exception("Google responded incorrectly to the authentication request. Please try again later.");
            }
            $time = time();
            $reqUrl = 'https://www.googleapis.com/oauth2/v1/tokeninfo?access_token='.$access_token;
            $req = new \Google_Http_Request($reqUrl);
            $tokenInfo = json_decode($this->client->getAuth()->authenticatedRequest($req)->getResponseBody());
            if (property_exists($tokenInfo, 'error')) {
                // This is not a valid token.
                throw new Exception("Invalid Access Token.");
            }
            else if (!property_exists($tokenInfo, 'audience') || $tokenInfo->audience != GOOGLE_APP_CLIENT_ID) {
                // This is not meant for this app. It is VERY important to check
                // the client ID in order to prevent man-in-the-middle attacks.
                throw new Exception("Access Token not meant for this app.");
            }

            $token = json_encode(array(
                "access_token" => $access_token,
                "created" => $time,
                "expires_in" => $tokenInfo->expires_in));
            $StatTracker['session']->set("token", $token);
        }
        catch (Exception $e) {
            error_log("Google authentication token failure");
            error_log(print_r($e, true));
        }
    }

    public function getRegistrationEmail($email_address) {
        $stmt = StatTracker::db()->prepare("SELECT auth_code AS `activation_code` FROM Agent WHERE email = ?;");
        $stmt->execute(array($email_address));
        $msg = "";

        // If no activation code is found, instruct user to contact the admin agent.
        if ($stmt->rowCount() == 0) {
            $stmt->closeCursor();
            $msg = "Thanks for registering with " . GROUP_NAME . "'s Stat Tracker. In order to complete your " .
                   "registration, please contact <strong>" . ADMIN_AGENT . "</strong> through your secure chat ".
                   "and ask them to enable access for you.";
        }
        else {
            extract($stmt->fetch());
            $stmt->closeCursor();

            $msg = "Thanks for registering with " . GROUP_NAME . "'s Stat Tracker. In order to validate your " .
                   "identity, please message the following code to <strong>@" . ADMIN_AGENT . "</strong> in " .
                   "faction comms:".
                   "<p/>%s<p/>" .
                   "You will recieve a reply message once you have been activated. This may take up to " .
                   "24 hours. Once you recieve the reply, simply refresh Stat Tracker.".
                   "<p/>".
                   $_SERVER['HTTP_REFERER'];

            $msg = sprintf($msg, $activation_code);
        }

        return $msg;
    }

    public function getAuthenticationUrl() {
        return $this->client->createAuthUrl();
    }

    public function getName() {
        return "Google";
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

    private function generateAPIToken($agent) {
        $token = $agent->createToken("API");
        if ($token === false) {
            return false;
        }
        else {
            $agent->token = $token;
        }
    }
}
