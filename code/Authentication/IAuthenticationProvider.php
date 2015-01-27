<?php
namespace BlueHerons\StatTracker\Authentication;

interface IAuthenticationProvider {
	
	/**
	 * Process a login request for Stat Tracker.
	 *
	 * This function MUST return a PHP object with the following properties:
	 * - "status": ["authentication_required", "registration_required", "okay"]
	 * 	- "authentication_required": When there is no session, and the user needs to log in.
	 *	- "registration_required": When the user has successfully authenticated, but has not completed
	 *	   registration.
	 *	- "okay": When the user exists, has completed registration, successfully  authenticated, and a session
	 * 	  has been created.
	 * - "email": The email address provided via the Authentication Provider
	 * - "error": true or false
	 *	- ONLY TRUE WHEN a application error occured, false otherwise
	 * - "url": URL to direct the user to for authentication.
	 *	- ONLY REQUIRED if "status" is "authentication_required"
	 * - "agent" Agent object
	 *	- ONLY REQUIRED if "status" is "okay".
	 *
	 * Examples (provided in JSON for readability)
	 *
	 * - User needs to authenticate
	 * {"error": false, "status": "authentication_required", "url": "http://account.google.com/login"}
	 *
	 * - User has successfuly authenticated, but they have not completed registration
	 * {"error": false, "status": "registration_required", "email": "agent_email@gmail.com"}
	 *
	 * - Some error occured, and the user cannot do anything to fix it
	 * {"error": true, "message": "Google isn't available"}
	 *
	 * - Successful authentication
	 * {"error": false, "status": "okay", "agent": { ... }}
	 */
	public function login();

	/**
	 * Process a logout request for Stat Tracker. The user session MUST be destroyed inside this method.
	 *
	 * This function MUST return a PHP object with the following properties:
	 * - "error": true or false
	 *	- true if a application error occured. The "message" property is also required in this case.
	 * - "message": <user description of error>
	 *	- Message that will be displayed to the user if "error" is true.
	 * - "status": "logged_out"
	 *	- ONLY if "error" is false.
	 *
	 * Examples (provided in JSON for readibility)
	 *
	 * - User successfully logged out
	 *	{"error": false, "status": "logged_out"}
	 *
	 * - Error during the logot process
	 *	{"error": true, "message": "Google didn't respond to the logout request"}
	 */
	public function logout();

	/**
	 * This method processes the callback from the Authentication provider. It should be passthrough, as the user
	 * will be redirected to a page that calls this method (/authenticate?action=callback). Ideally, save session
	 * info from the provider here, and process it via the login() method, which will be called automatically.
	 */
	public function callback();
}
