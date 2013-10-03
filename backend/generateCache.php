<?php
require_once("../config.php");
chdir("../www/NodeViz/");
require_once("NodeVizUtils.php");
set_include_path(get_include_path().PATH_SEPARATOR.$nodeViz_config['library_path'].PATH_SEPARATOR.$nodeViz_config['application_path']);
require_once("StateDEM.php");
require_once("GraphVizExporter.php");
require_once('cacheGenerationUtils.php');

$sections = array('summaries', 'infographics', 'races', 'companies', 'candidates', 'dollars', 'votes');
if (! isset($argv[1])) { print 'Usage: php generateCache.php FORCE_ALL_CACHE_REGEN ['.join($sections, ' | ')."] HIDE_PROGRESS\n"; exit; }

$debug = 0;
$nodeViz_config['cache'] = 0;
$nodeViz_config['debug'] = 9;

$worktypes = array('graph'=>'buildCache', 'cansummary'=>'canPlots', 'comsummary'=>'comPlots', 'congsummary'=>'congPlots', 'dollars'=>'generateDollar');

$edgestore_tag = date('Y-m-d H:i');
dbwrite("delete from edgestore where tag = '$edgestore_tag'");


if (! isset($argv[2]) || $argv[2] == 'all') {
	foreach($sections as $section) { 
		$function = 'cache_'.$section;
		$function();
	}
} else {
	$x = 0;
	foreach($argv as $arg) {
		$x++;
		if ($x < 3) { continue; }
		if (!in_array($arg, $sections)) {
			print "$arg not a valid section - skipping\n";
			continue;
		} else {
			$function = 'cache_'.$arg;
			$function();
		}
	}
}

function cache_races() {
	echo "caching races;\n";
	global $_REQUEST;
	global $cache;
	global $congresses;
	global $states;
	global $force;
	global $min_cycle;
	global $max_cycle;
	global $nodeViz_config;

	if ($force && $nodeViz_config['cache_path']) {
		system("rm -rf $nodeViz_config[cache_path]/*;");
	}
	$_REQUEST = array('candidateLimit' => 250, 'companyLimit'=>250, 'graphWidth'=>600, 'graphHeight'=>600);
	$count = (((($max_cycle - $min_cycle) /2)+1) *2 *(count($states)));
	$x = 0;
	foreach ($states as $_REQUEST['state']) {
		foreach (array('state:upper', 'state:lower') as $_REQUEST['chamber']) {
			$_REQUEST['cycle'] = $min_cycle;
			while ($_REQUEST['cycle'] <= $max_cycle) {
				$x = showProgress($x, $count);
				fork_work('graph', 'StateDEM');
				$_REQUEST['cycle'] += 2;
			}
		}
	}
	print "\n";
}

function cache_candidates() {
	echo "caching candidates;\n";
	global $_REQUEST;
	global $cache;
	global $congresses;
	global $force;
	global $current_congress;
	global $ui_current_congress;
	global $datapath;
	if ($force) {
		system("rm -rf $datapath/carbon/individuals/");
	}
	$_REQUEST = array('candidateLimit' => 250, 'companyLimit'=>250);
	$query = "select concat(a.CRPcandID, b.congress_num) as id, a.CRPcandID, b.congress_num, b.chamber as racecode from congressmembers a join congressmembers b using (CRPcandID) where a.CRPcandID is not null and a.congress_num = $current_congress and b.congress_num != 1 group by a.CRPcandID, b.congress_num 
				union select concat(CRPcandID, 'total') as id, CRPcandID, 'total', chamber from congressmembers where CRPcandID is not null and congress_num = $current_congress group by CRPcandID
				union select concat(a.recipient_id, 'pre') as id, a.recipient_id, 'pre', c.chamber from contributions a join congressmembers c on a.recipient_id = c.CRPcandID where a.congress_num = 1 and a.racecode != 'P' and c.congress_num = $current_congress group by a.recipient_id
				order by congress_num, CRPcandID	";
	$results = dbLookupArray($query);
	$count = count($results);
	$x = 0;
	foreach ($results as $can) {
		#if($can['CRPcandID'] != 'S8CO00172') { continue; }
		#$_REQUEST['racecode'] = $can['CRPcandID'][0];
		$_REQUEST['sitecode'] = 'carbon';
		$_REQUEST['congress_num'] = $can['congress_num'];
		$_REQUEST['candidateids'] = $can['CRPcandID'];
		$_REQUEST['racecode'] = 'C';
		$x = showProgress($x, $count);
		if ($can['congress_num'] > $ui_current_congress) { continue; }
		#print_r($_REQUEST);
		fork_work('graph');
	}
	print "\n";
}

function cache_companies() {
	echo "caching companies:\n";
	global $_REQUEST;
	global $cache;
	global $force;
	global $datapath;
	global $ui_current_congress;
	if ($force) {
		system("rm -rf $datapath/carbon/companies/");
	}
	$_REQUEST = array('candidateLimit' => 250, 'companyLimit'=>250);
	$query = "select concat(a.companyid, a.congress_num) as id, a.companyid, if (a.congress_num = 1, 'pre', a.congress_num) as congress_num  from contributions a join congressmembers b on a.recipient_id = b.CRPcandID and a.congress_num = b.congress_num where racecode != 'P' group by a.companyid, a.congress_num
				union select concat(companyid, 'total') as id, companyid, 'total' from contributions where racecode != 'P' group by companyid

				order by congress_num, companyid";
	$results = dbLookupArray($query);
	$count = count($results);
	$x = 0;
	foreach ($results as $can) {
		$_REQUEST['sitecode'] = 'carbon';
		$_REQUEST['racecode'] = 'C';
		$_REQUEST['congress_num'] = $can['congress_num'];
		$_REQUEST['companyids'] = $can['companyid'];
		#buildCache();
		$x = showProgress($x, $count);
		if ($can['congress_num'] > $ui_current_congress) { continue; }
		fork_work('graph');
	}
	print "\n";
}

function cacheCongSummaryGraphics() {
	global $congresses;
	global $force;
	global $datapath;
	if($force) {
		system("rm -rf $datapath/summaryChart/congresses/*");
	}
	echo "caching congress summary graphics\n";
	$results = array_keys($congresses); //list of current congress numbers we have
	$count = count($results);
	$x = 0;
	foreach ($results as $cong) {
		$x = showProgress($x, $count);
		fork_work('congsummary', $cong);
	}
	print "\n";
}

function cacheCanSummaryGraphics() {
	global $force;
	global $datapath;
	global $current_congress;
	if($force) {
		system("rm -rf $datapath/summaryChart/candidates/*");
	}
	echo "caching candidate summary graphics\n";
	$query = "select distinct CRPcandID from congressmembers where CRPcandID != '' and congress_num = '$current_congress'";
	$results = dbLookupArray($query);
	$count = count($results);
	$x = 0;
	foreach ($results as $can) {
		$x = showProgress($x, $count);
		fork_work('cansummary', $can['CRPcandID']);
	}
	print "\n";
}

function cacheComSummaryGraphics() {
	global $force;
	global $datapath;
	if($force) {
		system("rm -rf $datapath/summaryChart/companies/*");
	}
	echo "caching company summary graphics\n";
	$query = "select distinct id from companies where id=match_id and ignore_all_contribs=0 and (cong_oil_total + cong_carbon_total + cong_coal_total) > 0";
	$results = dbLookupArray($query);
	$count = count($results);
	$x = 0;
	foreach ($results as $com) {
		$x = showProgress($x, $count);
		fork_work('comsummary', $com['id']);
	}
	print "\n";
}

function buildCSV() {
	global $_REQUEST;
	global $datapath;
	global $logdir;
	foreach(array('candidateFilterIndex', 'companyFilterIndex', 'contribFilterIndex', 'candidateLimit', 'companyLimit') as $key) {
		unset($_REQUEST[$key]);
	}
	$graph = new FECCanComGraph();
	$graph->setupGraph($_REQUEST, 1);
	include_once('../www/lib/csv.php');
	$csv = createCSV($graph);
	$graphname = $datapath.$graph->graphname().'.csv';
	$file = fopen("$graphname", 'w');
	fwrite($file, $csv);		
	fclose($file);
}


?>

