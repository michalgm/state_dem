<?php

require_once("./publish_config.php");
$date  = date('Y.m.d-h.i');

print "Dumping Local DB\n";
system("mysqldump -u oilchange -poilchange $localdb $live_db_tables > db.sql;");
system("mysqldump -u oilchange -poilchange oilchange companies >> db.sql;");
system("git commit db.sql -m 'Frontend database dump from $date'");
system("git tag 'Publish_from_$date'");
system("git push");
system("git push --tags");

print "Update git repo\n";
ssh_cmd("cd $staging_dir;git fetch origin; git checkout master; git reset --hard origin/master");
print "Loading DB into Remote\n";
ssh_cmd("mysql -h db $remote_staging_db < $staging_dir/backend/publish/db.sql;");
print "Update cache\n";
rsync("../../www/cache", "$login:$staging_dir/www/");
update_config('staging');

#ssh_cmd("rsync --delete -a -q -r -P $live_dir/ $staging_dir;")
#ssh_cmd("mysql -h db -u priceofoil $remotedb < $staging_dir/db.sql;");
#update_config('staging');

$toggle = readline("Check http://dev.states.dirtyenergymoney.com - does it look okay? (Y/N)");

if (strtolower($toggle) == 'y') {
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

	print "Updating Config\n";
	ssh_cmd("sed -i -E \"s/NodeVizPath.*//\" ./$dir/www/js/main.js; 
	sed -i -E \"s/http:\/\/styrotopia.net.*request.php/request.php/\" ./$dir/www/js/main.js;
	sed -i -E \"s/cache = .*$/cache = 2;/\" ./$dir/config.php;
	sed -i -E \"s/debug = .*$/debug = 0;/\" ./$dir/config.php;
	sed -i -E \"s/dbname = .*$/dbname = '$db';/\" ./$dir/config.php;
	sed -i -E \"s/dblogin = .*$/dblogin = 'priceofoil';/\" ./$dir/config.php;
	sed -i -E \"s/dbpass = .*$/dbpass = 'eakViabAv0';/\" ./$dir/config.php;
	sed -i -E \"s/dbhost = .*$/dbhost = 'db';/\" ./$dir/config.php;");
}

function toggleSite() {
	global $live_dir, $temp_dir, $staging_dir, $remote_staging_db, $remotedb;
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
