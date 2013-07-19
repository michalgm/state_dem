<?php
include_once('Graph.php');
require_once("$nodeViz_config[application_path]/config.php");
$global_edges = array();
$ballot = 0;
setlocale(LC_MONETARY, 'en_US.UTF-8');

$db = dbconnect();

/*
creates the graph data structure that will be used to pass data among components.  Structure must not change
*/
class StateDEM extends Graph { 

	function __construct() {
		parent::__construct();
			
		// gives the classes of nodes
		$this->data['nodetypes'] = array('candidates', 'donors'); //FIXME - add properties for types
		
	  	// gives the classes of edges, and which nodes types they will connect
		$this->data['edgetypes'] = array( 'donations' => array('donors', 'candidates'));
		
		// graph level properties
		$this->data['properties'] = array(
			//sets the scaling of the elements in the gui
			'minSize' => array('donors'=>'.5', 'candidates' => '.5', 'donations'=>'5'),
			'maxSize' => array('donors'=>'4', 'candidates' => '4', 'donations' =>'60'),
			'log_scaling' => 0,
			'state'=>'AL',
			'cycle'=>'2006',
			'chamber'=>'state:upper',
			'valueMin'=>'1',
			'edgeid'=>'',
			'candidate_ids'=>'',
			'company_ids'=>''
		);
		// special properties for controling layout software
		//BROKEN, USE GV PARAMS
		$this->data['graphvizProperties'] = array(
			'graph'=> array(
				'bgcolor'=>'#FFFFFF',
#				'size' => '6.52,6.52!',
				'fontsize'=>90,
				'splines'=>'curved',
				'fontcolor'=>'blue',
				'start'=>'15',
				'layoutEngine'=>'fdp',
				//'sep'=>"+10"
				//'maxiter' => '100000', //turning this off speeds things up, but does it mean that some might not converge?
			),
			'node'=> array('label'=> ' ', 'labelloc'=>'b', 'imagescale'=>'true','fixedsize'=>1, 'style'=> 'setlinewidth(10), filled', 'regular'=>'true', 'fontsize'=>15),
			'edge'=>array('arrowhead'=>'none', 'arrowsize'=>2, 'color'=>'#99999966', 'fontsize'=>15, 'len'=>4, 'minlen'=>4, 'style'=>'tapered')
		);
		srand(20); //Don't copy this - this just makes sure that we are generating the same 'random' values each time
	}


	/**
	There must be a  <nodetype_fetchNodes() function for each defined node type.  
	It returns the ids of the nodes for the type. 
	**/

	function candidates_fetchNodes() {
		$nodes = array();
		$props = $this->data['properties'];

		$where = "a.state='".$props['state']."' and a.term=".$props['cycle']." and a.seat = '$props[chamber]'";
		if ($props['candidate_ids']) {
			$canids = arrayToInString(explode(',', $props['candidate_ids']));
			$where = "a.imsp_candidate_id in ($canids)";
		}
		$query = "select imsp_candidate_id as id, a.state, a.term, a.district, a.party, full_name candidate_name, image from legislator_terms a join legislators b on imsp_candidate_id = nimsp_candidate_id where $where";
		writelog($query);
		foreach(dbLookupArray($query) as $can) {
			$can['total_dollars'] = 0;
			$nodes[$can['id']] = $can;
		}
		return $nodes;
	}

	function donors_fetchNodes() {
		$nodes = array();
		global $global_edges;
		global $ballot;
		$props = $this->data['properties'];
		$cans = $this->getNodesByType('candidates');
		$cycle = $props['candidate_ids'] ? "" : "cycle='$props[cycle]' and";
		$companies = $props['company_ids'] ?  "company_id in (".arrayToInString(explode(',', $props['company_ids'])).") and" : "";
		$basicContribQuery = "
			select transaction_id, company_id, c.name as company_name, sum(amount) as amount, recipient_ext_id, b.name as industry,  c.image_name as image, d.dem_type as sitecode, contributor_type from contributions_dem a join catcodes b on code = contributor_category join companies c on c.id = a.company_id join companies_state_details d using(company_id) where $cycle $companies recipient_ext_id in (".arrayToInString($cans, 1).") and  company_name != '' group by a.company_id, recipient_name order by sum(amount) desc 
			";
		writelog($basicContribQuery);
		writelog('before donor query');
		$contribs = dbLookupArray($basicContribQuery);
		writelog('after donor query');
		foreach ($contribs as $donor) { 
			$id = 'co-'.trim($donor['company_id']);
			if (isset($nodes[$id])) {
				$nodes[$id]['total_dollars'] += $donor['amount'];
			} else {
				$nodes[$id] = $donor;
				$nodes[$id]['id'] = $id;
				$nodes[$id]['total_dollars'] = $donor['amount'];
			}
			if ($nodes[$id]['image']) {
			   	if (! stristr($nodes[$id]['image'], 'com_images')) { 
					$nodes[$id]['image'] = "../www/com_images/c".$nodes[$id]['image'].".png";
				}
			} else {
				$nodes[$id]['image'] = "../www/com_images/cunknown_".$nodes[$id]['sitecode']."_co.png";
			}
			if(isset($this->data['nodes'][$donor['recipient_ext_id']])) {
				$this->data['nodes'][$donor['recipient_ext_id']]['total_dollars'] += $donor['amount'];
			}
			$edgeid = $donor['recipient_ext_id'].'_'.$id;
			if (isset($global_edges[$edgeid])) { 
				$global_edges[$edgeid]['value'] += $donor['amount'];
			} else {
				$global_edges[$edgeid] = array(
					'toId'=>$donor['recipient_ext_id'],
					'fromId'=>$id,
					'value'=>$donor['amount'],
					'id'=>$edgeid
				);
			}
			$id = null;
			$edgeid = null;
			$donor = null;
		}
		$contribs = null;
		return $nodes;
	}

	function candidates_nodeProperties($nodes) {
		global $ballot;
		foreach ($nodes as &$node) {
			//if ($node['total_dollars'] <= $this->data['properties']['valueMin']) { writelog($node['id']); unset($nodes[$node['id']]); continue; }
			$node['value'] = $node['total_dollars'];
			if ($ballot) {
				if ($node['total_pro_dollars'] > $node['total_con_dollars']) {
					$node['color'] = 'green';
				} else {
					$node['color'] = 'red';
				}
				$node['label'] = $node['committee_name'];
			} else {
				$node['color'] = colorize($node['party'])."33";
				$node['label'] = $node['candidate_name'];
			}
			$node['fillcolor'] = '#ffffffff';
			$node['tooltip'] = $node['label']." (Received ".money_format('%.0n', $node['value']).")";
			#$node['fill-opacity'] = .5;
			$node['shape'] = 'square';
			$node['label_zoom_level'] = '6';
			//$node['click'] = "this.selectNode('".$node['id']."'); this.panToNode('".$node['id']."');";
			$node['image'] = $node['image'] ? "../www/can_images/$node[id].jpg" : "../www/can_images/unknownCandidate.jpg";
			if($node['value'] == 0) { unset($nodes[$node['id']]); }
		}
		//$nodes = $this->scaleSizes($nodes, 'candidates', 'value');
		return $nodes;	
	}

	function donors_nodeProperties($nodes) {
		foreach ($nodes as $node) {
			if ($node['total_dollars'] <= $this->data['properties']['valueMin']) { writelog($node['id']); unset($nodes[$node['id']]); continue; }
			$node['label'] = ucwords(trim($node['company_name']));
			$node['value'] = $node['total_dollars'];
			$node['dir'] = getcwd();
			//if ($node['contributor_type'] == 'I') { 
				$node['color'] = 'cadetblue';
				$node['shape'] = 'circle';
			//} elseif ($node['contributor_type'] == 'C') {
			//	$node['color'] = 'purple';
			//	$node['shape'] = 'polygon';
			//	$node['sides'] = '9';
			//} else { 
			//	$node['color'] = 'orange';
			//	$node['shape'] = 'triangle';
		   	//}

			$node['fillcolor'] = "#ffffffff";
			$node['tooltip'] = $node['label']." (Gave ".money_format('%.0n', $node['value']).")";
			$node['label_zoom_level'] = '8';
			//$node['click'] = "this.selectNode('".$node['id']."'); this.panToNode('".$node['id']."');";
			$nodes[$node['id']] = $node;
			$node = null;
		}
		//$nodes = $this->scaleSizes($nodes, 'donors', 'value');
		return $nodes;	
	}


	function donations_fetchEdges() {
		global $global_edges;
		$edges = $global_edges;
		$global_edges = null;
		return $edges;
	}

	function donations_edgeProperties($edges) {
		#print "".memory_get_usage()." edgetype\n";
		$x = 0;
		foreach ($edges as $edge) {
			unset($edges[$edge['id']]);
			if (! isset($this->data['nodes'][$edge['toId']]) || ! isset($this->data['nodes'][$edge['fromId']])) {
				continue;
			}
			$edge['weight'] = $edge['value'];
			$edge['tooltip'] = money_format('%.0n', $edge['value'])." from ".$this->data['nodes'][$edge['fromId']]['label']." to ".$this->data['nodes'][$edge['toId']]['label'];
			$edges[$edge['id']] = $edge;
			$x++;
		}
		#print "$x\n";
		#print "".memory_get_usage()." edgetype\n";
		$edges = $this->scaleSizes($edges, 'donations', 'value');
		return $edges;
	}

	function preProcessGraph() {
		foreach ($this->data['properties'] as &$prop) { 
			if(gettype($prop) == "string") { 
				$prop = dbEscape($prop);
			}
		}
	}

	function postProcessGraph() {
		$nodes = $this->data['nodes'];
		#print "post ".memory_get_usage()."\n";
		$nodes = $this->scaleSizes($nodes, 'donors', 'value');
		$nodes = $this->scaleSizes($nodes, 'candidates', 'value');
		uasort($nodes, function($a, $b) { return $a['value'] > $b['value']; }) ;
		$this->data['nodes'] = $nodes;
		$edges = $this->data['edges'];
		$edges = $this->scaleSizes($edges, 'donations', 'value');
		uasort($edges, function($a, $b) { return $a['value'] > $b['value']; }) ;
		$this->data['edges'] = $edges;
	}

	function graphname() {
		$props = $this->data['properties'];
		return implode('_', array($props['state'], $props['cycle'], $props['chamber']));
	}
}

function colorize($value){
	$colors = array(
		"REPUBLICAN"=>"#cc3333ff",
		"R"=>"#cc3333ff",
		"DEMOCRAT"=>"#3333ccff",
		"D"=>"#3333ccff",
		"GREEN"=>"#33cc33ff",
		"G"=>"#33cc33ff",
		"LIBERTARIAN"=>"#cc33ccff",
		"L"=>"#cc33ccff",
		"PEACE & FREEDOM"=>"#33ccccff",
	);
	$color = isset($colors[trim($value)]) ? $colors[trim($value)] : "gray";
	return($color);
}
