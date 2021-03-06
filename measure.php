<?php
require_once "./vendor/autoload.php";

use turbulence\Logger;
use turbulence\Collector;
use turbulence\scm\Git;
use turbulence\calculator\Complexity;
use turbulence\calculator\Changes;
use turbulence\PlotBuilder;


$params = parse_params($_SERVER['argv']);

$repoDir    = realpath($params['repo']);
$outputDir  = rtrim($params['out'], '/');
$path       = $params['path'];
$ignore     = $params['ignore'];

$logger = new Logger();
$result = new Collector();
$scm    = new Git($repoDir);

if (!$scm->isRepo())
	die("\ninvalid repository: {$repoDir}\n\n");
if (!is_dir($outputDir))
	mkdir($outputDir);

$logger->log('calculating complexity...');
$complexity = new Complexity($repoDir, $path, $ignore);
$result     = $complexity->calculate($result);
$logger->log('calculating changes...');
$changes    = new Changes($scm, $path);
$result     = $changes->calculate($result);

$logger->log('writing results...');
$outFile = $outputDir.'/out.json';
$result->dumpJson($outFile);

$viewerFile  = $outputDir.'/viewer.html';
$plotBuilder = new PlotBuilder($viewerFile);
$result->dumpJsonWith($plotBuilder);



function register_autoloader() {
	$baseDir = __DIR__.'/../src/';
	if (realpath($baseDir)) {
		set_include_path(get_include_path().PATH_SEPARATOR.$baseDir);
	}
	spl_autoload_register(function($class) {
		if (0 === strpos($class, 'turbulence')) {
			require_once str_replace('\\', '/', $class).'.php';
			return class_exists($class, false) || interface_exists($class, false);
		}
		return false;
	});
}

function parse_params($args) {
	$params    = array();
	$usageText = <<<EOB

Usage: turbulence -repo=REPOSITORY_DIR -out=OUTPUT_DIR [-path=PATH_IN_THE_REPO] [-ignore=<DIR_IN_REPO[,...]>]


EOB;
	foreach (array_slice($args, 1) as $arg) {
		if (!preg_match('/^-(repo|path|out|ignore)=?(.*)$/', $arg, $match)) {
			die($usageText);
		}

		$params[$match[1]] = $match[2];
	}
	if (!isset($params['repo']) || !isset($params['out'])) {
		die($usageText);
	}

	$params['path'] = isset($params['path']) ? ltrim($params['path'], '/') : '';

	return $params;
}