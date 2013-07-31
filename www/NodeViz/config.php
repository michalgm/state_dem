<?php
#config.php
# Use this file to specify file paths, set NodeViz options, and define available graph setup files. 
# You can also put any functions you'd like to have available in your application code in here.
#
# Note: all paths should be defined relative to the location of the nodeViz_path (which should be the directory containing this file, and, more importantly, request.php
#

$nodeViz_config = array(
	'nodeViz_path' => getcwd()."/",
	'web_path' => '../', #The *local filesystem* path where your index page is (and all web paths will be relative to)
	'application_path' => '../../', #The location of your application code (and your graph setupfiles)
	'library_path' => './library/', #The location of the NodeViz library code
	'log_path' => "../log/", # Where should we write logs (needs to be writable by your webserver)
	'cache_path' => "../cache/", # Where should we store graph cache files (needs to be writable by your webserver)
	'cache' => 2, # Should we use stored cache files, or regenerate graphs from scratch every time
	'debug' => 2, # Sets the debug level to determine what level of extra information should be written to logs
	'old_graphviz' => 0, #Set this to 1 if graphviz version < 2.24

	#setupfiles needs to be an associative array of the graph setup files your application will use. 
	'setupfiles' => array('StateDEM.php'=>1)
)

?>
