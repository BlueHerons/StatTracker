<?php
namespace BlueHerons\StatTracker\Authentication;

use BlueHerons\StatTracker\StatTracker;

use Exception;
use Frlnc\Slack\Core\Commander;
use Frlnc\Slack\Http\CurlInteractor;
use Frlnc\Slack\Http\SlackResponseFactory;
use Katzgrau\KLogger\Logger;
use PDOException;
use StdClass;

use BlueHerons\StatTracker\Agent;
use BlueHerons\StatTracker\AuthenticationProvider;

class SlackProvider implements IAuthenticationProvider {

    private $base_url;
    private $logger;
    private $plus;

    const AUTHORIZE_URL = "https://slack.com/oauth/authorize";

    public function __construct($base_url, Logger $logger) {
        $interactor = new CurlInteractor;
        $interactor->setResponseFactory(new SlackResponseFactory);
        $this->client = new Commander('xoxp-no-token', $interactor);
        $this->logger = $logger == null ? new Logger(LOG_DIR) : $logger;
        $this->base_url = $base_url;
    }

    public function login(StatTracker $StatTracker) {
        $response = new StdClass();
        $response->error = false;

        // Kick off the OAuth process
        if (empty($StatTracker['session']->get("token"))) {
            return AuthResponse::authenticationRequired($this);
        }

        // Slack tokens do not expire
        $this->client->setToken($StatTracker['session']->get("token"));

        if ($StatTracker['session']->get("agent") === null) {
            try {
                $resp = $this->client->execute("users.identity", [])->getBody();
                if (!$resp['ok']) {
                    $this->logger->error(sprintf("users.identity response: %s", print_r($resp, true)));
                    throw new Exception(sprintf("Slack identification failed users.identity: %s", $resp['error']));
                }

                $email_address = $resp['user']['email'];

                if (empty($email_address)) {
                    return AuthResponse::error("Slack did not provide an email address.");
                }

                $agent = Agent::lookupAgentName($email_address);

                if (!$agent->isValid()) {
                    // Could be no token, or new user.
                    if (!empty($agent->name) && $agent->name === "Agent") {
                        // We only need this if the user's email address is not in the ST database
                        $resp = $this->client->execute("auth.test", [])->getBody();
                        if (!$resp['ok']) {
                            if ($resp['error'] == "missing_scope") {
                                $this->second_auth_pass = true;
                                return AuthResponse::authenticationRequired($this);
                            }
                            else {
                                $this->logger->error(sprintf("auth.test response: %s", print_r($resp, true)));
                                throw new Exception(sprintf("Slack identification failed auth.test: %s", $resp['error']));
                            }
                        }

                        $agent->name = $resp['user'];
                        $this->createNewAgent($email_address, $agent->name);
                        $this->logger->info(sprintf("Created new agent %s for %s", $agent->name, $email_address));
                        $this->generateAPIToken($agent);
                        $agent = Agent::lookupAgentName($email_address);
                        if (!$agent->isValid()) {
                            $response = AuthResponse::error(sprintf("No agent associated with %s", $email_address));
                        }
                        else {
                            $StatTracker['session']->set("agent", $agent);
                            $response = AuthResponse::okay($agent);
                        }
                    }
                    else {
                        error_log(print_r($agent, true));
                        $response = AuthResponse::error("Not a valid or new agent");
                    }
                }
                else {
                    $StatTracker['session']->set("agent", $agent);
                    $response = AuthResponse::okay($agent);
                    $this->logger->info(sprintf("%s authenticated successfully", $agent->name));
                }


                // Now, this part is utterly stupid...cannot request identify scope (for auth.test) and identity.* 
                // scopes (for users.identity) at the same time. If we don't have the identity.* scopes, redirect and
                // request them.

                
                           }
            catch (Exception $e) {
                $response = AuthResponse::error($e->getMessage());
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
        $this->client->execute("auth.revoke", []);
        session_destroy();
        $response = AuthResponse::loggedOut();
        $this->logger->info(sprintf("%s logged out", $agent->name));
        return $response;
    }

    public function callback(StatTracker $StatTracker) {
        $code = isset($_REQUEST['code']) ? $_REQUEST['code'] : file_get_contents("php://input");
        $error = isset($_REQUEST['error']) ? $_REQUEST['error'] : null;

        try {
            if (!isset($code)) {
                throw new Exception("Slack responded incorrectly to the authentication request. Please try again later.");
            }

            if (isset($error)) {
                return;
            }

            $response = $this->client->execute("oauth.access", [
                'client_id' => SLACK_CLIENT_ID,
                'client_secret' => SLACK_CLIENT_SECRET,
                'code' => $code,
                'redirect_uri' => sprintf("%s/authenticate?action=callback", $this->base_url)
            ])->getBody();

            if ($response['ok']) {
                $StatTracker['session']->set("token", $response['access_token']);
            }
            else {
                $this->logger->error(sprintf("Slack oauth.access response: %s", print_r($response, true)));
                throw new Exception(sprintf("Slack authorization failed: %s", $response['error']));
            }
        }
        catch (Exception $e) {
            error_log("Slack authentication callback failure");
            error_log(print_r($e, true));
            throw $e;
        }
    }

    public function getRegistrationEmail($email_address) {
        return false;
    }

    public function getAuthenticationUrl() {
        if ($this->second_auth_pass) {
            $scopes = "identify";
        }
        else {
            $scopes = "identity.basic identity.email identity.avatar identity.team";
        }

        $query = http_build_query(array(
            "client_id" => SLACK_CLIENT_ID,
            "scope" => $scopes,
            "redirect_uri" => sprintf("%s/authenticate?action=callback", $this->base_url),
            "team" => SLACK_TEAM_ID
        ));
        return SlackProvider::AUTHORIZE_URL ."?" . $query;
    }

    public function getName() {
        return "Slack";
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
    private function createNewAgent($email_address, $agent_name) {
        try {
            $stmt = StatTracker::db()->prepare("INSERT INTO Agent (`email`, `agent`) VALUES (?, ?) ON DUPLICATE KEY UPDATE agent = VALUES(agent);");
            $stmt->execute(array($email_address, $agent_name));
            $stmt->closeCursor();
        }
        catch (PDOException $e) {
            // Failing to insert an auth code will cause a generic registration email to be sent to the user.
            error_log($e);
        }
    }

    private function generateAPIToken($agent) {
        $token = $agent->createToken(Agent::TOKEN_WEB);
        if ($token === false) {
            return false;
        }
        else {
            $agent->token = $token;
        }
    }
}
