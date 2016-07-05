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

	function testRequestParams() {
		$request = array('foo' => 'bar');

		$route = new ControllerRoute('/', array(), $request);
		$this->controller->setRoute($route);

		$this->assertEquals($this->controller->getRequestParams(), $request);
	}

}
