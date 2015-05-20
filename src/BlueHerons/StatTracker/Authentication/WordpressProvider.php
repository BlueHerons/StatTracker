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
                    $this->generateAuthCode($user->user_email);
                    $agent = Agent::lookupAgentName($user->user_email);

                    if (!$agent->isValid()) {
                        $name = apply_filters(ST_AGENT_NAME_FILTER, $user->user_login);

                        $this->logger->info(sprintf("Adding new user %s", $name));

                        // Insert them into the DB
                        $stmt = $app->db()->prepare("UPDATE Agent SET agent = ? WHERE email = ?");
                        $stmt->execute(array($name, $user->user_email));
                        $stmt->closeCursor();
                    
                        $agent = Agent::lookupAgentName($user->user_email);
                    }

                    $app['session']->set("agent", $agent);
                    $response = AuthResponse::okay($agent);
                    $this->logger->info(sprintf("%s authenticated successfully", $agent->name));
                }
            }
            else {
                $agent = $app['session']->get("agent");

                if (Agent::lookupAgentByAuthCode($agent->getAuthCode())->isValid()) {
                    $response = AuthResponse::okay($agent);
                }
                else {
                    $this->logger->info(sprintf("Expired auth_code for %s. Logging out", $agent->name));
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
}
?>
