<?php

if (! isset($argv[1])) {
	print "
This script manages the backend of states.dirtyenergymoney.com.
Usage: php admin.php <COMMAND> [COMMAND] [skipcheck]

Valid Commands:
  update	Updates all backend data from various sources
  		Options: 
  		  [url=<TRANSPARENCY_DATA_URL>]	Download new transparency data & process
		  [reprocess]			Don't download data, but reprocess last download
		  No options re-imports existing transparency data db
  cache		Generate the cache files
  publish	Publish code, cache, and db to staging site, and optionally to live site
  		Options:
		  [skipcheck]	Don't wait for manual site verification before pushing live
  rollback	Roll back live site to previous state - this must be used on its own

";
}

$args = getArgs();

if (isset($args['update'])) { 
	$option = isset($args['url']) ? $args['url'] : (isset($args['reprocess']) ? 'reprocess' : 'quick'); 
	passthru("php update_all_data.php $option");
}
if (isset($args['cache'])) { 
	require_once("./publish/publish_config.php");
	print "Dumping Local DB\n";
	passthru("mysqldump -u oilchange -poilchange $localdb $live_db_tables > publish/db.sql;");
	passthru("mysqldump -u oilchange -poilchange oilchange companies >> publish/db.sql;");
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
	$commands = array('update', 'cache', 'publish', 'rollback', 'url=', 'skipcheck', 'reprocess' );

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
