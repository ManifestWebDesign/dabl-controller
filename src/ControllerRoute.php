<?php

/**
 * @link https://github.com/ManifestWebDesign/DABL
 * @link http://manifestwebdesign.com/redmine/projects/dabl
 * @author Manifest Web Design
 * @license    MIT License
 */

namespace Dabl\Controller;

/**
 * Represents a url string for loading a controller action.
 */
class ControllerRoute {

	/**
	 * @var string
	 */
	protected $route;

	/**
	 * @var array
	 */
	protected $segments = array();

	/**
	 * @var string
	 */
	protected $controllerDir;

	/**
	 * @var string
	 */
	protected $controllerClass;

	/**
	 * @var string
	 */
	protected $action;

	/**
	 * @var array
	 */
	protected $params = array();

	/**
	 * @var string
	 */
	protected $extension;

	/**
	 * @var string
	 */
	protected $jsonPCallback;

	/**
	 * @var bool
	 */
	protected $isPartial = false;

	/**
	 * @var bool
	 */
	protected $isRestful = false;

	protected $httpVerb;

	protected $headers = array();

	/**
	 * @var string
	 */
	protected $viewDir;

	/**
	 * @var array
	 */
	protected $requestParams;

	/**
	 * @param string|array $route
	 * @param array $headers
	 * @param array $request_params
	 */
	function __construct($route = '', array $headers = array(), array $request_params = array()) {
		$this->setRoute($route . '');

		if (!empty($headers)) {
			$this->setHeaders($headers);
		}

		if (!empty($request_params)) {
			$this->setRequestParams($request_params);
		}

		if ($this->httpVerb === null) {
			$this->httpVerb = 'GET';
		}
	}

	/**
	 * @return string
	 */
	public function __toString() {
		return $this->getRoute() . '';
	}

	/**
	 * @param string|array $route
	 * @param array $headers
	 * @param array $request_params
	 */
	static function load($route = '', $headers = array(), array $request_params = array()) {
		$controller_route = new self($route, $headers, $request_params);

		$controller = $controller_route->getController();

		if (null === $controller) {
			file_not_found($route);
		}

		$controller->doAction($controller_route->getAction(), $controller_route->getParams());
	}

	/**
	 * @param string|array $route
	 */
	function setRoute($route) {
		$this->controllerClass = null;
		$this->controllerDir = null;
		$this->viewDir = null;
		$this->action = null;
		$this->params = array();

		if (is_array($route)) {
			$this->route = implode('/', $route);
			$segments = $route;
		} else {
			$parts = explode('?', $route, 2);
			$this->route = $route = trim(str_replace('\\', '/', array_shift($parts)), '/');
//			$query_string = array_shift($parts);
			if ($route === '') {
				$segments = array();
			} else {
				$segments = explode('/', $route);
			}
		}

		$last = array_pop($segments);
		if (null !== $last) {
			$file_parts = explode('.', $last);
			if (count($file_parts) > 1) {
				$this->extension = array_pop($file_parts);
			}
			$segments[] = implode('.', $file_parts);
		}

		while (in_array(reset($segments), array('partial', 'rest'))){
			if ($segments[0] == 'partial') {
				$this->isPartial = true;
				array_shift($segments);
			} elseif ($segments[0] == 'rest') {
				$this->isRestful = true;
				array_shift($segments);
			}
		}

		$this->segments = $segments;

		// directory where controllers are found
		$found = false;
		$deepest_dir = '';
		$deepest_level = 0;
		$deepest_view_prefix = '';
		$deepest_segments = array();

		foreach (Controller::getDirectories() as $directory) {
			$view_prefix = '';
			$view_dir = '';
			$level = 1;
			$segments = $this->segments;

			$c_dir = $directory;
			foreach ($segments as $key => &$segment) {
				$c_class = ucwords(str_replace(array('_', '-'), ' ', $segment));
				$c_class = str_replace(' ', '', $c_class) . 'Controller';
				$c_class_file = $c_dir . $c_class . '.php';

				// check if file exists
				if (is_file($c_class_file)) {
					unset($segments[$key]);
					$view_dir = strtolower($segment);
					$this->controllerClass = $c_class;
					$this->controllerDir = $c_dir;
					$found = true;
					break;
				}

				// check if the segment matches directory name
				$t_dir = $c_dir . $segment . '/';
				if (is_dir($t_dir)) {
					++$level;
					unset($segments[$key]);
					$c_dir = $t_dir;
					$view_prefix .= $segment . '/';
				} else {
					break;
				}
			}

			if ($found) {
				$this->viewDir = $view_prefix . $view_dir . '/';
				$this->action = array_shift($segments);
				$this->params = $segments;
				break;
			} elseif ($level > $deepest_level) {
				// fallback check if default index exists in directory
				if (is_file($c_dir . 'IndexController.php')) {
					$deepest_dir = $c_dir;
					$deepest_level = $level;
					$deepest_view_prefix = $view_prefix;
					$deepest_segments = $segments;
				}
			}
		}

		if (!$found && $deepest_dir) {
			$this->controllerClass = 'IndexController';
			$this->controllerDir = $deepest_dir;
			$this->viewDir = $deepest_view_prefix;
			$this->action = array_shift($deepest_segments);
			$this->params = $deepest_segments;
		}
	}

	/**
	 * @param array $headers
	 */
	function setHeaders($headers) {
		$this->headers = $headers;

		// auto detect output format
		if (!empty($headers['Accept'])) {
			if (strpos($headers['Accept'], 'application/json') !== false) {
				$this->extension = 'json';
			} elseif ($headers['Accept'] == 'application/xml') {
				$this->extension = 'xml';
			}
		}

		// HTTP verb
 		if (!empty($headers['X-Http-Method-Override'])) {
			$this->httpVerb = strtoupper($headers['X-Http-Method-Override']);
		} elseif (!empty($headers['X-Http-Method'])) {
			$this->httpVerb = strtoupper($headers['X-Http-Method']);
		} elseif (!empty($headers['Method'])) {
			$this->httpVerb = strtoupper($headers['Method']);
		}

		// Ajax Request = partial (no layout)
		if (
			!empty($headers['X-Requested-With'])
			&& $headers['X-Requested-With'] == "XMLHttpRequest"
		) {
			$this->isPartial = true;
		}
	}

	/**
	 * @param array $request_params
	 */
	function setRequestParams(array $request_params = array()) {
		if (!empty($request_params['_method'])) {
			$this->httpVerb = $request_params['_method'];
			unset($request_params['_method']);
		}
		if (!empty($request_params['jsonp'])) {
			$this->extension = 'jsonp';
			$this->jsonPCallback = $request_params['jsonp'];
		} elseif (!empty($request_params['callback'])) {
			$this->extension = 'jsonp';
			$this->jsonPCallback = $request_params['callback'];
		}

		$this->requestParams = &$request_params;
	}

	/**
	 * @return array
	 */
	function getRequestParams() {
		return $this->requestParams;
	}

	/**
	 * @return string
	 */
	function getRoute() {
		$partial = $this->isPartial ? 'partial/' : '';
		$rest = $this->isRestful ? 'rest/' : '';
		$segments = implode('/', $this->segments);
		$extension = ($this->extension ? '.' . $this->extension : '');
		return $rest . $partial . $segments . $extension;
	}

	/**
	 * @return array
	 */
	function getSegments() {
		return $this->segments;
	}

	/**
	 * @return string
	 */
	function getExtension() {
		return $this->extension;
	}

	/**
	 * @return string
	 */
	function getJsonPCallback() {
		return $this->jsonPCallback;
	}

	/**
	 * @return string
	 */
	function getAction() {
		return $this->action;
	}

	/**
	 * @return array
	 */
	function getParams() {
		return $this->params;
	}

	/**
	 * @param bool $bool
	 */
	function setPartial($bool) {
		$this->isPartial = (bool) $bool;
	}

	/**
	 * @return bool
	 */
	function isPartial() {
		return $this->isPartial;
	}

	/**
	 * @param bool $bool
	 */
	function setRestful($bool) {
		$this->isRestful = (bool) $bool;
	}

	/**
	 * @return bool
	 */
	function isRestful() {
		return $this->isRestful;
	}

	/**
	 * @return string
	 */
	function getHttpVerb() {
		return $this->httpVerb;
	}

	/**
	 * @return string
	 */
	function getControllerDir() {
		return $this->controllerDir;
	}

	/**
	 * @return string
	 */
	function getControllerClass() {
		return $this->controllerClass;
	}

	/**
	 * @return string
	 */
	function getViewDir() {
		return $this->viewDir;
	}

	/**
	 * @return Controller
	 */
	function getController() {
		if (null === $this->controllerDir || null === $this->controllerClass) {
			return null;
		}

		require_once $this->controllerDir . '/' . $this->controllerClass . '.php';
		$instance = new $this->controllerClass($this);

		return $instance;
	}

}