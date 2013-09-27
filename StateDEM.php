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
		$this->data['edgetypes'] = array( 'donations' => array('donors', 'candidates'), 'zeroes'=>array('donors', 'donors'));
		
		// graph level properties
		$this->data['properties'] = array(
			//sets the scaling of the elements in the gui
			'minSize' => array('donors'=>'.5', 'candidates' => '.5', 'donations'=>'5'),
			'maxSize' => array('donors'=>'4', 'candidates' => '4', 'donations' =>'60'),
			'log_scaling' => 0,
			'state'=>'AL',
			'cycle'=>'2006',
			'chamber'=>'state:upper',
			'valueMin'=>'0',
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
				'fontsize'=>2,
				'splines'=>'curved',
				'fontcolor'=>'blue',
				'start'=>'15',
				'layoutEngine'=>'neato',
				'overlap'=>'vpsc'
				//'sep'=>"+10"
				//'maxiter' => '100000', //turning this off speeds things up, but does it mean that some might not converge?
			),
			'node'=> array('imagescale'=>'true','fixedsize'=>1, 'style'=> 'setlinewidth(4), filled', 'regular'=>'true','fontsize'=>2),
			'edge'=>array('arrowhead'=>'none', 'color'=>'#99999966', 'len'=>4, 'minlen'=>4, 'style'=>'tapered', 'tailclip'=>'false')
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
		$query = "select imsp_candidate_id as id, a.state, a.term, a.district, a.party, full_name candidate_name, image, lifetime_total from legislator_terms a join legislators b on imsp_candidate_id = nimsp_candidate_id where $where";
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
			select transaction_id, company_id, c.name as company_name, sum(amount) as amount, recipient_ext_id, b.name as industry,  c.image_name as image, d.dem_type as sitecode, contributor_type, (d.coal_related + d.oil_related + d.carbon_related) as lifetime_total from contributions_dem a join catcodes b on code = contributor_category join companies c on c.id = a.company_id join companies_state_details d using(company_id) where $cycle $companies recipient_ext_id in (".arrayToInString($cans, 1).") and  company_name != '' group by a.company_id, recipient_name order by sum(amount) desc 
			";
		$this->addquery('fetch_donors', $basicContribQuery);
		writelog('before donor query');
		$contribs = dbLookupArray($basicContribQuery);
		writelog('after donor query');
		foreach ($contribs as $donor) { 
			$donor['amount'] = round($donor['amount']);
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
			$node['color'] = colorize($node['party']);
			$node['title'] = $node['candidate_name'];
			$node['fillcolor'] = '#ffffffff';
			$node['tooltip'] = $node['title'];
			#$node['fill-opacity'] = .5;
			$node['shape'] = 'square';
			#$node['label_zoom_level'] = '10';
			//$node['click'] = "this.selectNode('".$node['id']."'); this.panToNode('".$node['id']."');";
			$node['image'] = $node['image'] ? "../www/can_images/$node[id].jpg" : "../www/can_images/unknownCandidate.jpg";
			if($node['value'] == 0) { $node['class'] = 'zero'; }
		}
		//$nodes = $this->scaleSizes($nodes, 'candidates', 'value');
		return $nodes;	
	}

	function donors_nodeProperties($nodes) {
		foreach ($nodes as $node) {
			//if ($node['total_dollars'] <= $this->data['properties']['valueMin']) { writelog($node['id']); unset($nodes[$node['id']]); continue; }
			$node['title'] = ucwords(trim($node['company_name']));
			$node['value'] = $node['total_dollars'];
			$node['dir'] = getcwd();
			//if ($node['contributor_type'] == 'I') { 
			$node['color'] = colorize($node['sitecode']);
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
			$node['tooltip'] = $node['title'];
			#$node['label_zoom_level'] = '9';
			//$node['click'] = "this.selectNode('".$node['id']."'); this.panToNode('".$node['id']."');";
			$nodes[$node['id']] = $node;
			$node = null;
		}
		//$nodes = $this->scaleSizes($nodes, 'donors', 'value');
		#$nodes['zerocontribs'] = array('id'=>'zerocontribs', 'value'=>0, 'shape'=>'circle', 'title'=>'Accepted $0', 'tooltip'=>'Accepted $0', 'type'=>'companies');

		return $nodes;	
	}


	function donations_fetchEdges() {
		global $global_edges;
		$edges = $global_edges;
		$global_edges = null;
		return $edges;
	}

	function zeroes_fetchEdges() { 
		return array();
	}

	function zeroes_edgeProperties() {
		$nodes = array();
		foreach ($this->data['nodes'] as $node) { 
			if ($node['value'] ==0) { $nodes[] = $node; }
		}
		$edges = array();
		foreach($nodes as $node) {
			foreach($nodes as $onode) {
				if ($node['id'] < $onode['id']) {
					$id = "$onode[id]_$node[id]";
					$edge = array(
						'id'=>$id,
						'toId'=>$node['id'],
						'fromId'=>$onode['id'],
						'len'=>3,
						'value'=>0,
						'weight'=>0,
						'style'=>'invis',
						'type'=>'zeroes'
					);
					$edges[$id] = $edge;
				}
			}
		}
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
			if (! $edge['value']) { continue; } //we don't need $0 edges
			$edge['weight'] = $edge['value'];
			$edge['tooltip'] = money_format('%.0n', $edge['value'])." from ".$this->data['nodes'][$edge['fromId']]['title']." to ".$this->data['nodes'][$edge['toId']]['title'];
			$edges[$edge['id']] = $edge;
			$x++;
		}
		#print "$x\n";
		#print "".memory_get_usage()." edgetype\n";
		//$edges = $this->scaleSizes($edges, 'donations', 'value');
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

		global $edgestore_tag;
		if($edgestore_tag != '') { 
			$name = $this->graphname();
			foreach(array_keys($this->data['edges']) as $edgeid) {
				$edge = $this->data['edges'][$edgeid];
				$setstring = "tag='$edgestore_tag', graphname='$name', type='$edge[type]', toid='$edge[toId]', fromid='$edge[fromId]', value=$edge[value]";
				dbwrite("insert into edgestore set $setstring on duplicate key update $setstring" );
			}
		}

	}

	function getSubgraphs() {
		$nodes = &$this->data['nodes'];
		$subnodes = array();
		foreach ($nodes as $node) {
			if ($node['value'] == 0) {
				$subnodes[] = $node['id'];
			}
		}
		$this->data['subgraphs']['cluster_zerocontribs'] = array();
		$this->data['subgraphs']['cluster_zerocontribs']['properties'] = array(
			'pad'=>'500',
			'rank'=>'min',
			'style'=>'rounded',
			//'label'=>"Accepted No DEM"
		);
		$this->data['subgraphs']['cluster_zerocontribs']['nodes'] = $subnodes;
	}

	function graphname() {
		$props = $this->data['properties'];
		return implode('_', array($props['state'], $props['cycle'], $props['chamber']));
	}
}

function colorize($value){
	$value = strtoupper(trim($value));
	$colors = array(
		"REPUBLICAN"=>"#cc6666ff",
		"R"=>"#cc6666ff",
		"DEMOCRAT"=>"#6699ccff",
		"D"=>"#6699ccff",
		"GREEN"=>"#33cc33ff",
		"G"=>"#33cc33ff",
		"LIBERTARIAN"=>"#cc33ccff",
		"L"=>"#cc33ccff",
		"PEACE & FREEDOM"=>"#33ccccff",
		'OIL'=>'#6d8f9dff',
		'COAL'=>'#958d63ff',
		'CARBON'=>'#666666ff'
	);
	$color = isset($colors[$value]) ? $colors[$value] : "#CCCC33ff";
	return($color);
}
