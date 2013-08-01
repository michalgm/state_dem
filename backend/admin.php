<?php

if (! $argv[1]) {
	print "
This script manages the backend of states.dirtyenergymoney.com.
Usage: php admin.php <COMMAND> [COMMAND] [url=TRANPARENCY_DATA_URL] [skipcheck]

Valid Commands:
  update	Updates all backend data - if url option is provided, will fetch new contrib data
  cache		Generate the cache files
  publish	Publish code, cache, and db to staging site, and optionally to live site
  		If skipcheck option is provided, site will automatically be pushed to live
  rollback	Roll back live site to previous state - this must be used on its own

";
}

$args = getArgs();

if (isset($args['update'])) { 
	$url = isset($args['url']) ? $args['url'] : 0; 
	passthru("php update_all_data.php $url");
}
if (isset($args['cache'])) { 
	passthru("php generateCache.php 1 races");
}
if (isset($args['publish'])) { 
	$check = isset($args['skipcheck']) ? 'skipcheck' : 'check';
	passthru("cd publish; php publish.php $check");
}
if (isset($args['rollback'])) { 
	passthru("cd publish; php publish.php rollback");
}
print "\n";

function getArgs() {
	global $argv;
	$commands = array('update', 'cache', 'publish', 'rollback', 'url=', 'skipcheck' );

	$args = array();

	array_shift($argv);
	foreach ($argv as $arg) {
		if($arg == 'rollback' && count($argv) > 1) { 
			print "rollback must be used on its own. Exiting\n";
			exit;
		}
		if (strstr($arg, 'url=')) { 
			$args['url'] = explode("=", $arg)[1];
		} else {
			if (! in_array($arg, $commands)) { 
				print "Invalid command: '$arg'. Exiting\n";
				exit;
			}
			$args[$arg] = 1;
		}
	}
	
	return $args;
}
?>
