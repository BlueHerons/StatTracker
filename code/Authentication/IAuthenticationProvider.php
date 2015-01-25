<?php
namespace BlueHerons\StatTracker\Authentication;

interface IAuthenticationProvider {
	
	public function login();

	public function logout();

	public function callback();
}
