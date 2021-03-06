<?php

/**
 * @link https://github.com/ManifestWebDesign/DABL
 * @link http://manifestwebdesign.com/redmine/projects/dabl
 * @author Manifest Web Design
 * @license    MIT License
 */
use Dabl\Controller\Exception\FileNotFoundException;

/**
 * @param string $file
 */
if (!function_exists('file_not_found')) {

	function file_not_found($file) {
		if (!headers_sent()) {
			header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
		}

		throw new FileNotFoundException("$file not found");
	}

}