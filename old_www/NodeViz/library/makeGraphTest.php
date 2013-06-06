<?php 
include_once('graphvizExport.php');
$graphfile = $argv[1];
chop($graphfile);
include_once($graphfile);
$debug = 1;

$graph = createGraph();
$graph = loadGraphData();
if ($argv[2]) { 
	echo(createDot($graph));
} else {
	print_r($graph);
}
