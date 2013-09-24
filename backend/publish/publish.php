<?php
require_once("./publish_config.php");
if (! isset($argv[1])) { 
	print "Usage: php publish.php <check | skipcheck | rollback>\n"; exit;
}

$command = $argv[1];

if ($command == 'rollback') { 
	print "Rolling back live site to previous state\n";
	toggleSite();
	exit;
}

$date  = date('Y.m.d-h.i');

print "Tagging State\n";
system("git tag 'Publish_from_$date'");
system("git push");
system("git push --tags");

print "Update git repo\n\n";
ssh_cmd("cd $staging_dir;git fetch origin; git checkout master; git reset --hard origin/master");
print "Loading DB into Remote\n\n";
ssh_cmd("mysql -h db $remote_staging_db < $staging_dir/backend/publish/db.sql;");
print "Update cache\n\n";
rsync("../../www/cache", "$login:$staging_dir/www/");
update_config('staging');

if ($command == 'check') { 
	print "\nCheck http://dev.states.dirtyenergymoney.com - does it look okay? (Y/N): ";
	$toggle = readline("");
	if (strtolower($toggle) == 'y') {
		toggleSite();
	} else {
		print "Leaving live site alone. Current code remains on  http://dev.states.dirtyenergymoney.com\n\n";
	}
} else {
	print "Skipping site verification\n\n";
	toggleSite();
}

function ssh_cmd($cmd) { 
	global $login;
	global $port;

	$cmd = str_replace('"', '\"', $cmd);
	system("ssh $login -p $port \"$cmd\"");
}

function rsync($source, $destination) { 
	global $port;
	system("rsync -az --rsh=\"ssh -p$port\" --port $port $source $destination");
}

function update_config($type='live') {
	global $live_dir, $staging_dir, $remotedb, $remote_staging_db;

	$dir = $type == 'live' ? $live_dir : $staging_dir;
	$db = $type == 'live' ? $remotedb : $remote_staging_db;

	print "Updating Config\n\n";
	ssh_cmd("
		sed -i -E \"s/cache' => .*$/cache' => 2,/\" ./$dir/www/NodeViz/config.php;
		sed -i -E \"s/debug' => .*$/debug' => 0,/\" ./$dir/www/NodeViz/config.php;
		sed -i -E \"s/\\\\\\$"."cache = .*$/\\\\\\$"."cache = 2;/\" ./$dir/config.php;
		sed -i -E \"s/\\\\\\$"."debug = .*$/\\\\\\$"."debug = 0;/\" ./$dir/config.php;
		sed -i -E \"s/\\\\\\$"."dbname = .*$/\\\\\\$"."dbname = '$db';/\" ./$dir/config.php;
		sed -i -E \"s/\\\\\\$"."dblogin = .*$/\\\\\\$"."dblogin = 'priceofoil';/\" ./$dir/config.php;
		sed -i -E \"s/\\\\\\$"."dbpass = .*$/\\\\\\$"."dbpass = 'eakViabAv0';/\" ./$dir/config.php;
		sed -i -E \"s/\\\\\\$"."dbhost = .*$/\\\\\\$"."dbhost = 'db';/\" ./$dir/config.php;
	");
}

function toggleSite() {
	global $live_dir, $temp_dir, $staging_dir, $remote_staging_db, $remotedb;
	print "Transposig staging and live sites\n\n";
	print "\tTransposing staging dir and live dir\n";
	ssh_cmd("mv $live_dir $temp_dir; mv $staging_dir $live_dir; mv $temp_dir $staging_dir;");

	print "\tDumping new data to $live_dir/oc.sql\n";
	ssh_cmd("mysqldump -h db -u priceofoil $remote_staging_db > $live_dir/oc.sql");
	print "\tDumping old data to $staging_dir/oc.sql\n";
	ssh_cmd("mysqldump -h db -u priceofoil $remotedb > $staging_dir/oc.sql");

	print "\tLoading new data to live DB\n";
	ssh_cmd("mysql -h db -u priceofoil $remotedb < $live_dir/oc.sql");
	print "\tPointing live site to live db\n";
	ssh_cmd("sed -i -E \"s/dbname = .*$/dbname = '$remotedb';/\" ./$live_dir/config.php;");

	print "\tLoading old data to staging DB\n";
	ssh_cmd("mysql -h db -u priceofoil $remote_staging_db < $staging_dir/oc.sql");
	print "\tPointing staging site to staging db\n";
	ssh_cmd("sed -i -E \"s/dbname = .*$/dbname = '$remote_staging_db';/\" ./$staging_dir/config.php;");

}
