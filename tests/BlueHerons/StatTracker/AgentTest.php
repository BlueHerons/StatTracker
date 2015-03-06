<?php
use BlueHerons\StatTracker\Agent;

class AgentTest extends PHPUnit_Framework_TestCase {

	public function testConstructor() {
		$agent = new Agent();
		$this->assertEquals("Agent", $agent->name);

		$this->setExpectedException('Exception');
		$agent = new Agent(null);
		
	}

	public function testIsValid() {
		$agent = new Agent();
		$this->assertFalse($agent->isValid());

		$agent->name = "AgentName";
		$this->assertFalse($agent->isValid());

		$agent->auth_code = "abcdef";
		$this->assertTrue($agent->isValid());
	}
}
?>
