<?php

require_once("./publish_config.php");

print "Dumping Local DB\n";
system("mysqldump -u oilchange -poilchange $localdb $live_db_tables > db.sql;");
system("mysqldump -u oilchange -poilchange oilchange companies >> db.sql;");
print "Loading DB into Remote\n";
rsync("./db.sql", "$login:$live_dir/");
ssh_cmd("mysql -h db -u priceofoil $remotedb < $live_dir/db.sql;");
print "Update git repo\n";
ssh_cmd("cd $live_dir;git fetch origin; git checkout ui; git reset --hard origin/ui");
print "Update cache\n";
rsync("../../www/cache", "$login:$live_dir/www/");
update_config();

function ssh_cmd($cmd) { 
	global $login;
	global $port;

	$cmd = str_replace('"', '\"', $cmd);
	system("ssh $login -p $port \"$cmd\"");
}

function rsync($source, $destination) { 
	global $port;
	system("rsync -a --rsh=\"ssh -p$port\" --port $port $source $destination");
}

function update_config() {
	global $remotedb, $live_dir;
	print "Updating Config\n";
	ssh_cmd("sed -i -E \"s/NodeVizPath.*//\" ./$live_dir/www/js/main.js; 
	sed -i -E \"s/http:\/\/styrotopia.net.*request.php/request.php/\" ./$live_dir/www/js/main.js;	sed -i -E \"s/cache = .*$/cache = 2;/\" ./$live_dir/config.php;	sed -i -E \"s/debug = .*$/debug = 0;/\" ./$live_dir/config.php;	sed -i -E \"s/dbname = .*$/dbname = '$remotedb';/\" ./$live_dir/config.php;	sed -i -E \"s/dblogin = .*$/dblogin = 'priceofoil';/\" ./$live_dir/config.php;	sed -i -E \"s/dbpass = .*$/dbpass = 'eakViabAv0';/\" ./$live_dir/config.php;	sed -i -E \"s/dbhost = .*$/dbhost = 'db';/\" ./$live_dir/config.php;");
	return;

	ssh_cmd("sed -i -E \"s/NodeVizPath.*//\" ./$live_dir/www/js/main.js");
	ssh_cmd("sed -i -E \"s/http:\/\/styrotopia.net.*request.php/request.php/\" ./$live_dir/www/js/main.js");
	ssh_cmd("sed -i -E \"s/cache = .*$/cache = 2;/\" ./$live_dir/config.php");
	ssh_cmd("sed -i -E \"s/debug = .*$/debug = 0;/\" ./$live_dir/config.php");
	ssh_cmd("sed -i -E \"s/dbname = .*$/dbname = '$remotedb';/\" ./$live_dir/config.php");
	ssh_cmd("sed -i -E \"s/dblogin = .*$/dblogin = 'priceofoil';/\" ./$live_dir/config.php");
	ssh_cmd("sed -i -E \"s/dbpass = .*$/dbpass = 'eakViabAv0';/\" ./$live_dir/config.php");
	ssh_cmd("sed -i -E \"s/dbhost = .*$/dbhost = 'db';/\" ./$live_dir/config.php");

}
