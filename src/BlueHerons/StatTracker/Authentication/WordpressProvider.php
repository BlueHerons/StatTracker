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

class WordpressProvider implements IAuthenticationProvider {

    private $base_url;
    private $logger;

    public function __construct($base_url, Logger $logger) {
        $this->base_url = $base_url;
        $this->logger = $logger;

        define("WP_USE_THEMES", false);
        define("ST_USER_AUTH_FILTER",  "stat_tracker_user_auth");
        define("ST_AGENT_NAME_FILTER", "stat_tracker_agent_name");

        require(WORDPRESS_ROOT_PATH . "wp-load.php");
    }

    public function login(StatTracker $app) {
        $response = null;

        if (wp_validate_auth_cookie('', 'logged_in')) {
            if ($app['session']->get("agent") === null) {
                $user = wp_get_current_user();
                // Allow a plugin to grant/deny this user. See wiki for details
                $user = apply_filters(ST_USER_AUTH_FILTER, $user);
                if (!($user instanceof \WP_User)) {
                    if (is_string($user)) {
                        $response = AuthResponse::registrationRequired($user);
                    }
                    else {                        
                        $response = AuthResponse::registrationRequired("Access was denied. Please contact @" . ADMIN_AGENT);
                    }
                    $this->logger->info(sprintf("Registration required for %s", $email_address));
                }
                else {
                    $agent = Agent::lookupAgentName($user->user_email);

                    if (!$agent->isValid()) {
                        $name = apply_filters(ST_AGENT_NAME_FILTER, $user->user_login);

                        $this->logger->info(sprintf("Adding new agent %s", $name));

                        $agent->name = $name;

                        // Insert them into the DB
                        $stmt = $app->db()->prepare("INSERT INTO Agent (email, agent) VALUES (?, ?) ON DUPLICATE KEY UPDATE agent = ?;");
                        $stmt->execute(array($user->user_email, $name, $name));
                        $stmt->closeCursor();
                   
                        // Generate an API token
                        $this->generateAPIToken($agent);

                        $agent = Agent::lookupAgentName($user->user_email);

                        if (!$agent->isValid()) {
                            $this->logger->error(sprintf("%s still not a valid agent", $agent->name));
                            return AuthResponse::error("An unrecoverable error has occured");
                        }
                    }

                    $app['session']->set("agent", $agent);
                    $response = AuthResponse::okay($agent);
                    $this->logger->info(sprintf("%s authenticated successfully", $agent->name));
                }
            }
            else {
                $agent = $app['session']->get("agent");

                if (Agent::lookupAgentByToken($agent->getToken())->isValid()) {
                    $response = AuthResponse::okay($agent);
                }
                else {
                    $this->logger->info(sprintf("Invalid token for %s. Logging out", $agent->name));
                    return $this->logout($app);
                }
            }

            return $response;
        }
        else {
            $app['session']->set("agent", null);
            $response = AuthResponse::authenticationRequired($this);
        }

        return $response;
    }

    public function logout(StatTracker $app) {
        wp_clear_auth_cookie();
        $app['session']->set("agent", null);
        session_destroy();
        $response = new stdClass();
        $response->status = "logged_out";
        $this->logger->info(sprintf("%s logged out", $agent->name));
        return $response;
    }

    public function callback(StatTracker $app) {
        // Nothing to do. Will forward to login()
    }

    public function getRegistrationEmail($email_address) {
        return false;
    }

    public function getAuthenticationUrl() {
        return wp_login_url(sprintf("%s/authenticate?action=callback", $this->base_url));
    }

    public function getName() {
        return "Wordpress";
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
?>
