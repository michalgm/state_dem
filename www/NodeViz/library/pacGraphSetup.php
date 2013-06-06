<?php
include_once('../config.php');

function createGraph() {
	$graph = array(
		'nodetypes' => array('oilpacs'), //FIXME - add properties for types
		'edgetypes' => array(
			'oilpac' => array('oilpacs', 'oilpacs')
		),
		'properties' => array(
			'electionYear' => '08',
			'minCandidateAmount' =>'3000',
			'minCompanyAmount' => '3000',
			'minSize' => array('oilpacs' => '.5', 'oilpac' =>'6'),
			'maxSize' => array('oilpacs' => '5',  'oilpac' =>'400')
		),
		'graphvizProperties' => array(
			'graph'=> array('bgcolor'=>'#FFFFFF'),
			'node'=> array('label'=> ' ', 'fixedsize'=>0, 'style'=> 'setlinewidth(18), filled'),
			'edge'=>array('len'=>8, 'arrowhead'=>'none')
		)
	);
	return $graph;
}

#$graph = loadGraphData();
#print_r($graph);

function oilpacs_fetchNodes($graph) {
	$electionyear = $graph['properties']['electionYear'];
	$minCandidateAmount = $graph['properties']['minCandidateAmount'];
	$query ="select CommitteeID as id from oilpacs";
	$result = dbLookupArray($query);
	$graph['nodes']['oilpacs'] = $result;
	return $graph;
}



function oilpacs_nodeProperties($graph) {
	$idlist = arrayToInString($graph['nodes']['oilpacs']);
	$query ="select FilerId as id, CommitteeName,CommitteeParty,format(sum(amount),0) as cash from contribs_clean join oilpacs on (FilerId = CommitteeID) join `committees` using (CommitteeID) where FilerId in ($idlist) group by FilerId; ";
	$nodes = dbLookupArray($query);
	$graph['nodes']['oilpacs'] = $nodes;
	foreach($graph['nodes']['oilpacs'] as &$node) {
		$node['regular'] = 'true';
		$node['type'] = 'oilpac';
		$node['shape'] = 'diamond';
		$node['color'] = lookupPartyColor($node['CommitteeParty']);
		$node['onClick'] = "showInfo('".$node['id']."');";
		$node['onMouseover'] = " showTooltip('".cleanQuotes($node['CommitteeName'])."');";
		$node['onMouseout'] = "hideTooltip();";
		$node['label'] = "<<table border='0'><tr><td><img src='data/logos/".$node['id'].".png' /></td></tr></table>>";
		$node['tooltip'] = $node['CommitteeName'];
	}
	$graph = scaleSizes($graph, 'oilpacs', 'cash');
	return $graph;
}



//NEED TO HAVE COMMENTS GIVING THE NAMES OF THE PROPERTIES ADDED
//Edge Ids need to be unique so P0001_2_1,P0001_2_2 
//Need to add a 'fromID' and 'toID' property to each edge
function oilpac_fetchEdges($graph) {
	$oilpacIds = arrayToInString($graph['nodes']['oilpacs']);
	$query ="select concat(OtherId, '_', FilerId) as id,   FilerId as toId, OtherId as fromId, sum(Amount) as cash from contribs_clean where contribType='op' and OtherId in ($oilpacIds)  group by concat(FilerId, OtherId)";
	$result = dbLookupArray($query);
	$graph['edges']['oilpac'] = $result;	
	$graph = scaleSizes($graph, 'oilpac', 'cash');
	//foreach($graph['edges']['com2can'] as &$edge) {
	//	$edge['weight'] = $edge['cash'];
	//}
	return $graph;
}
$type = 'oilpacs';

function ajax_showoilpacInfo($graph) {
	global $types;
	$id = $_GET['id'];
	$campaignid = $graph['properties']['racecode'].$graph['properties']['electionyear'];
	$row = fetchRow("select b.Name, format(sum(a.amount),0) as contribs from contribs_clean a join oilcompanies b on a.CompanyID = b.id join candidates c using(candidateID) where a.CompanyID = '$id' and a.campaignid = '$campaignid' and c.campaignid =  '$campaignid' group by a.CompanyID");
	print "<table border='0'><tr><td><img src='data/logos/".$id.".png'></td><td><h3>".$row[0]."</h3>has contributed <b>$".$row[1]."</b> to 20".$graph['properties']['electionyear']." ".$types[$graph['properties']['racecode']]." races.</td><tr></table>";
}

