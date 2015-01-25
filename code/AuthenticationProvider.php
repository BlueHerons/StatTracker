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
}
