<?php
include_once('Graph.php');
$apiKey = "key=3420aea61e2f4cb1a8a925a0c738eaf0";
$ftmUrl = "http://api.followthemoney.org/";
$edges = array();
setlocale(LC_MONETARY, 'en_US.UTF-8');
/*
creates the graph data structure that will be used to pass data among components.  Structure must not change
*/
class Unfluence extends Graph { 

	function __construct() {
		parent::__construct();
			
		// gives the classes of nodes
		$this->data['nodetypes'] = array('candidates'); //FIXME - add properties for types
		
	  	// gives the classes of edges, and which nodes types they will connect
		$this->data['edgetypes'] = array( 'donations' => array('candidates', 'candidates'));
		
		// graph level properties
		$this->data['properties'] = array(
			//sets the scaling of the elements in the gui
			'minSize' => array('candidates' => '.5', 'donations'=>'5'),
			'maxSize' => array('candidates' => '3', 'donations' =>'30'),
			'log_scaling' => 0,
			'state'=>'AL',
			'year'=>'2006',
			'office'=>'G00',
			'interest'=>'0',
			'valueMin'=>'10000'

		);
		// special properties for controling layout software
		//BROKEN, USE GV PARAMS
		$this->data['graphvizProperties'] = array(
			'graph'=> array(
				'bgcolor'=>'#FFFFFF',
#				'size' => '6.52,6.52!',
				'fontsize'=>90,
				'splines'=>'true',
				'fontcolor'=>'blue',
				'start'=>'self',

			),
			'node'=> array('label'=> ' ', 'imagescale'=>'true','fixedsize'=>1, 'style'=> 'setlinewidth(7), filled', 'regular'=>'true', 'fontsize'=>15),
			'edge'=>array('arrowhead'=>'normal', 'arrowsize'=>3, 'color'=>'#99999966', 'fontsize'=>15, 'len'=>4, 'minlen'=>4)
		);
		srand(20); //Don't copy this - this just makes sure that we are generating the same 'random' values each time
	}


	/**
	There must be a  <nodetype_fetchNodes() function for each defined node type.  
	It returns the ids of the nodes for the type. 
	**/

	function candidates_fetchNodes() {
		$nodes = array();
		global $apiKey;
		global $ftmUrl;
		global $edges;
		$props = $this->data['properties'];
		if ($props['interest'] == 0) { $props['interest'] = ''; }
		$startQuery ="candidates.list.php?&state=".$props['state']."&year=".$props['year']."&office=".$props['office'];
		$basicContribQuery = $ftmUrl.'candidates.top_contributors.php?'.$apiKey."&imsp_industry_code=".$props['interest'].'&imsp_candidate_id=';
		$queryUrl = $ftmUrl.$startQuery.'&'.$apiKey;
		$xml = file_get_contents($queryUrl);
		if (! $xml) { die("unable to open url $queryUrl"); }
		$response = xml2array($xml);
		foreach($response as $can) {
			$can_id = $can['candidate_name'];
			$nodes[$can_id] = $can;
			$nodes[$can_id]['id'] = $can_id;
			$nodes[$can_id]['candidate'] = 1;
		}
		foreach($nodes as $can) {
			$can_id = $can['id'];
			//$nodes[$can_id]['total_dollars'] = 0;
			$contribQuery = $basicContribQuery.$can['imsp_candidate_id'];
			$donor_xml = file_get_contents($contribQuery);
			if (! $donor_xml) { die("unable to open url $queryUrl"); }
			$dresponse = xml2array($donor_xml);
			foreach($dresponse as $donor) {
				if ($donor['total_dollars'] < $this->data['properties']['valueMin']) {
					continue;
				}
				$id = $donor['contributor_name'];
				if (isset($nodes[$id])) {
					if (! isset($nodes[$id]['candidate_name']) ) { 
						$nodes[$id]['total_dollars'] += $donor['total_dollars'];
					}
				} else {
					$nodes[$id] = $donor;
					$nodes[$id]['id'] = $id;
				}
				$edgeid = $can_id.'_'.$id;
				$edges[$edgeid] = array(
					'toId'=>$can_id,
					'fromId'=>$id,
					'value'=>$donor['total_dollars'],
					'id'=>$edgeid
				);
			}

		}
		return $nodes;
	}

	function candidates_nodeProperties($nodes) {
		foreach ($nodes as &$node) {
			if ($node['total_dollars'] <= $this->data['properties']['valueMin']) { unset($nodes[$node['id']]); continue; }
			$node['label'] = $node['id'];
			$node['value'] = $node['total_dollars'];
			if (isset($node['candidate_name'])) {
				$node['color'] = colorize($node['party'])."33";
				$node['fillcolor'] = '#ffffff';
				$node['tooltip'] = $node['label']." (Recieved ".money_format('%.0n', $node['value']).")";
				#$node['fill-opacity'] = .5;
			} else {
				$node['color'] = 'cadetblue';
				$node['fillcolor'] = "#ffffff";
				$node['tooltip'] = $node['label']." (Gave ".money_format('%.0n', $node['value']).")";
			}
			$node['shape'] = 'circle';
			$node['click'] = "this.selectNode('".$node['id']."'); this.panToNode('".$node['id']."');";
		}
		$nodes = $this->scaleSizes($nodes, 'candidates', 'value');
		return $nodes;	
	}

	function donations_fetchEdges() {
		global $edges;
		return $edges;
	}

	function donations_edgeProperties($edges) {
		foreach ($edges as &$edge) {
			if (! isset($this->data['nodes'][$edge['toId']]) || ! isset($this->data['nodes'][$edge['fromId']])) {
				unset($edges[$edge['id']]);
				continue;
			}
			$edge['weight'] = $edge['value'];
			$edge['tooltip'] = money_format('%.0n', $edge['value'])." from ".$this->data['nodes'][$edge['fromId']]['label']." to ".$this->data['nodes'][$edge['toId']]['label'];
		}
		$edges = $this->scaleSizes($edges, 'donations', 'value');
		return $edges;
	}
}

function xml2array($xml) {
	$object = new SimpleXMLElement($xml);
	$array = array();
	foreach($object as $item) {
		$entry = array();
		foreach($item->attributes() as $prop=>$value) {
			$entry[$prop] = "".$value;
		}
		$array[] = $entry;
	}
	return $array;
}

function colorize($value){
	$color = "gray";
	if ($value == "REPUBLICAN"){$color = "#cc3333";}
	if ($value == "DEMOCRAT"){$color = "#3333cc";}
	if ($value == "GREEN"){$color = "#33cc33";}
	if ($value == "LIBERTARIAN "){$color = "#cc33cc";}
	if ($value == "PEACE & FREEDOM "){$color = "#33cccc";}
	return($color);
}
