<?php
namespace BlueHerons\StatTracker;

class AuthenticationProvider {

	private static $instance;

	/**
	 * Gets the registered Authentication provider.
	 *
	 * @see 
	 *
	 * @return IAuthenticationProvider
	 */
	public static function getInstance() {
		if (self::$instance == null) {
			// Load all auth classes
			foreach (glob(__DIR__ ."/Authentication/*Provider.php") as $filename) {
				require_once($filename);
			}

			$allClasses = get_declared_classes();
			$authClasses = array();

			foreach ($allClasses as $class) {
				$reflector = new \ReflectionClass($class);
				if ($reflector->implementsInterface("\BlueHerons\StatTracker\Authentication\IAuthenticationProvider")) {
					$authClasses[] = $class;
				}
			}

			if (sizeof($authClasses) == 0) {
				die("No Authentication providers found");
				return null;
			}

			// Instantiate the first one found
			$class = $authClasses[0];
			self::$instance = new $class();
		}

		return self::$instance;
	}

	/**
	 * Sends the autorization code for the given email address to that address. The email includes
	 * instructions on how to complete the registration process as well.
	 *
	 * Most providers should generate an auth_code and use that as a challenge during the registration process. If
	 * that is not possible given a provider, then a rather generic email will be sent to the user, instructing
	 * to contact the specified ADMIN_AGENT.
	 *
	 * @param string $email_address The address to send the registration email to.
	 *
	 * @return void
	 */
	public static function sendRegistrationEmail($email_address) {
		global $app;
		global $db;

		$stmt = $db->prepare("SELECT auth_code FROM Agent WHERE email = ?;");
		$stmt->execute(array($email_address));
		$msg = "";

		// If no auth code is found, instruct user to contact the admin agent.
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

			$msg = sprintf($msg, $auth_code);
		}

		$transport = \Swift_SmtpTransport::newInstance(SMTP_HOST, SMTP_PORT, SMTP_ENCR)
				->setUsername(SMTP_USER)
				->setPassword(SMTP_PASS);

		$mailer = \Swift_Mailer::newInstance($transport);

		$message = \Swift_Message::newInstance('Stat Tracker Registration')
				->setFrom(array(GROUP_EMAIL => GROUP_NAME))
				->setTo(array($email_address))
				->setBody($msg, 'text/html', 'iso-8859-2');

		$mailer->send($message);
	}

}
