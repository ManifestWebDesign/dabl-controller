<?php
use Dabl\Controller\Controller;
use Dabl\Controller\ControllerRoute;

/**
 * @author dan
 */
class ControllerTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var Controller
	 */
	protected $controller;

	protected function setUp() {
		Controller::addDirectory(__DIR__ . '/controllers');

		$this->controller = new Controller();
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 */
	protected function tearDown() {
		Controller::clearDirectories();
	}

	public function testLoad() {
		$message = null;
		try {
			Controller::load(null);
		} catch (Exception $e) {
			$message = $e->getMessage();
		}

		$this->assertEquals('/index not found', $message);
	}

	function testRequestParams() {
		$request = array('foo' => 'bar');

		$route = new ControllerRoute('/', array(), $request);
		$this->controller->setRoute($route);

		$this->assertEquals($this->controller->getRequestParams(), $request);
	}

}
