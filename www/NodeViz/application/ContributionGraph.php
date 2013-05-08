<?php
require_once('Graph.php');
require_once('oc_config.php');
/*
creates the graph data structure that will be used to pass data among components.  Structure must not change
*/
class ContributionGraph extends Graph { 

	function __construct() {
		parent::__construct();
		
		// gives the classes of nodes
		$this->data['nodetypes'] = array('companies','labels'); //FIXME - add properties for types
		
	  	// gives the classes of edges, and which nodes types they will connect
		$this->data['edgetypes'] = array( 'org2org' => array('companies', 'companies'),'orgOwnOrg' => array('companies', 'companies'),'labels'=>array('labels','companies'));
		
		// graph level properties
		$this->data['properties'] = array(
			'electionyear' => '10',
			'minOrgAmount' =>'1000',
			'minContribAmount' => '1000',
			'contribFilterIndex' => '',
			'minCanAmount' => '',
			'candidateLimit' =>'',
			'companyLimit' =>'',
			'candidateids' => '',
			'companyids' => '',
			'allcandidates' => 0,
			//sets the scaling of the elements in the gui
			'minSize' => array('companies' => '1', 'org2org' =>'10','orgOwnOrg' =>'4'),
			'maxSize' => array('companies' => '15', 'org2org' =>'250','orgOwnOrg' =>'4'),
			'sitecode' => 'prop23',
			'prop' => '23',
			'candidateFilterIndex' => 0,
			'removeIsolates' => 1,
			'log_scaling' => 0
		);
		// special properties for controling layout software
		//BROKEN, USE GV PARAMS
		$this->data['graphvizProperties'] = array(
			'graph'=> array(
				'bgcolor'=>'#FFFFFF',
				#'size' => '9.79,7.00!',
				'rankdir' => 'RL',
				'ranksep' => '30',
				'pad' => '1,5',
				'fontname' => 'Helvetica',
				'fontnames' => 'ps',
				'fontsize' => 220,
				'layoutEngine'=>'dot'
			)
		);
	}

	/**
	There must be a  <nodetype_fetchNodes() function for each defined node type.  
	It returns the ids of the nodes for the type. 
	**/

	function companies_fetchNodes() {
		$graph = $this->data;
		$precheck = "";
		$minOrgAmount = $graph['properties']['minOrgAmount'];
	
		$query ="select entityid as id from entities where (cash >= $minOrgAmount or cash = 0)";
		$this->addquery('companies', $query);

		$result = dbLookupArray($query);
		return $result;
	}
	
	function labels_fetchNodes() {
		return array(
			'oilLabel' => array(
				'id' => 'oilLabel',
			),
			'coalLabel' => array(
				'id' => 'coalLabel'
			)
		);
	}
	
	function labels_nodeProperties() {
		$graph = $this->data;
		$propView = $graph['properties']['prop'];
		$nodes = array();
		if ($propView ==23){
			//get sector totals
			$totals = fetchRow("select format(sum(if(entities.type='oil',amount,0)),0) oil,
format(sum(if(entities.type='coal',amount,0)),0) coal,
format(sum(amount)-(sum(if(entities.type='oil',amount,0))+if(entities.type='coal',amount,0)),0) other 
from relationships join entities on from_id = entityid  and view = 'prop_23' and to_id = 257 ");
		
			$nodes['oilLabel']['id'] = 'oilLabel';
			$nodes['oilLabel']['shape'] = 'box';
			$nodes['oilLabel']['size'] = 20;
			$nodes['oilLabel']['fontsize'] ="180";
			$nodes['oilLabel']['label'] = "Oil &amp; Gas Companies: $".$totals[0];
			$nodes['oilLabel']['fontcolor'] ="#6d8f9d";
			$nodes['oilLabel']['color'] ="#ffffff";
			$nodes['oilLabel']['fontname'] ="Arial, Helvetica, sans-serif";
			
			$nodes['coalLabel']['id'] = 'coalLabel';
			$nodes['coalLabel']['shape'] = 'box';
			$nodes['coalLabel']['size'] = 20;
			$nodes['coalLabel']['fontsize'] ="100";
			$nodes['coalLabel']['label'] = "Coal Companies: $".$totals[1];
			$nodes['coalLabel']['fontcolor'] ="#958d63";
			$nodes['coalLabel']['color'] ="#ffffff";
			$nodes['coalLabel']['fontname'] ="Arial, Helvetica, sans-serif";
			
			$nodes['otherLabel']['id'] = 'otherLabel';
			$nodes['otherLabel']['shape'] = 'box';
			$nodes['otherLabel']['size'] = 20;
			$nodes['otherLabel']['fontsize'] ="120";
			$nodes['otherLabel']['label'] = "Other Companies: $".$totals[2];
			$nodes['otherLabel']['fontcolor'] ="gray";
			$nodes['otherLabel']['color'] ="#ffffff";
			$nodes['otherLabel']['fontname'] ="Arial, Helvetica, sans-serif";
		} else if ($propView == 26){
			$totals = fetchRow("select format(sum(if(entities.type='oil',amount,0)),0) oil,
format(sum(if(entities.type='coal',amount,0)),0) coal,
format(sum(amount)-(sum(if(entities.type='oil',amount,0))+if(entities.type='coal',amount,0)),0) other 
from relationships join entities on from_id = entityid  and view = 'prop_25_26' and to_id in (376,377) ");

			$nodes['oilLabel']['id'] = 'oilLabel';
			$nodes['oilLabel']['shape'] = 'box';
			//$nodes['oilLabel']['cash'] = 100000;
			$nodes['oilLabel']['size'] = 20;
			$nodes['oilLabel']['fontsize'] ="150";
			$nodes['oilLabel']['label'] = "Oil &amp; Gas Companies: $".$totals[0];
			$nodes['oilLabel']['fontcolor'] ="#6d8f9d";
			$nodes['oilLabel']['color'] ="#ffffff";
			$nodes['oilLabel']['fontname'] ="Arial, Helvetica, sans-serif";
			
			$nodes['otherLabel']['id'] = 'otherLabel';
			$nodes['otherLabel']['shape'] = 'box';
			//$nodes['oilLabel']['cash'] = 100000;
			$nodes['otherLabel']['size'] = 20;
			$nodes['otherLabel']['fontsize'] ="180";
			$nodes['otherLabel']['label'] = "Other Companies: $".$totals[2];
			$nodes['otherLabel']['fontcolor'] ="gray";
			$nodes['otherLabel']['color'] ="#ffffff";
			$nodes['otherLabel']['fontname'] ="Arial, Helvetica, sans-serif";
		}
		return $nodes;
	}
	
	function labels_fetchEdges(){
		$graph = $this->data;
		//$graph['edges']['labels']['coalLabel'] = array();
		$propView = $graph['properties']['prop'];

		$edges = array();
		
		if ($propView ==23){
			$edges['oilLabel']['id'] = 'oilLabel';
			$edges['oilLabel']['fromId'] = 'oilLabel';
			$edges['oilLabel']['toId'] = '262';
			$edges['oilLabel']['cash'] = 30000;
			$edges['oilLabel']['weight'] = 0;
			$edges['oilLabel']['size'] = 0;
			$edges['oilLabel']['weight'] = 0;
			$edges['oilLabel']['style'] = "invis";
			
			$edges['coalLabel']['id'] = 'coalLabel';
			$edges['coalLabel']['fromId'] = 'coalLabel';
			$edges['coalLabel']['toId'] = '793';
			$edges['coalLabel']['cash'] = 30000;
			$edges['coalLabel']['weight'] = 0;
			$edges['coalLabel']['size'] = 0;
			$edges['coalLabel']['weight'] = 0;
			$edges['coalLabel']['style'] = "invis";
			
			$edges['otherLabel']['id'] = 'otherLabel';
			$edges['otherLabel']['fromId'] = 'otherLabel';
			$edges['otherLabel']['toId'] = '357';
			$edges['otherLabel']['cash'] = 30000;
			$edges['otherLabel']['weight'] = 0;
			$edges['otherLabel']['size'] = 0;
			$edges['otherLabel']['weight'] = 0;
			$edges['otherLabel']['style'] = "invis";
		} else if ($propView == 26) {
			$edges['oilLabel']['id'] = 'oilLabel';
			$edges['oilLabel']['fromId'] = 'oilLabel';
			$edges['oilLabel']['toId'] = '261';
			$edges['oilLabel']['cash'] = 30000;
			$edges['oilLabel']['weight'] = 0;
			$edges['oilLabel']['size'] = 0;
			$edges['oilLabel']['weight'] = 0;
			$edges['oilLabel']['style'] = "invis";
			
			$edges['otherLabel']['id'] = 'otherLabel';
			$edges['otherLabel']['fromId'] = 'otherLabel';
			$edges['otherLabel']['toId'] = '792';
			$edges['otherLabel']['cash'] = 30000;
			$edges['otherLabel']['weight'] = 0;
			$edges['otherLabel']['size'] = 0;
			$edges['otherLabel']['weight'] = 0;
			$edges['otherLabel']['style'] = "invis";
		}
		return $edges; 
	}



	/**
	There must be a  <nodetype>_nodeProperties() function for each node class.
	It sets the properties of the nodes of that type. 
	**/
	
	function companies_nodeProperties() {
		$graph = $this->data;
		global $company_images;
		global $current_congress;
		$racecode_filter = "";
		$org_ids = arrayToInString($graph['nodetypesindex']['companies']);
		$propView = $graph['properties']['prop'];
		$cashType = "prop23_cash";
		$sortType = "type desc";
		if ($propView == 26){
			$cashType = "prop26_cash";
			$sortType = "type";
		}
		$query ="select concat(entityid) as id,label as Name,$cashType as cash,format(sum($cashType),0) as nicecash,image_name as image,type as industry, state from entities where entityid in ($org_ids) group by entityid order by $sortType, $cashType desc;";
		$this->addquery('companies_props', $query,$graph);
		$nodes = dbLookupArray($query);
		foreach($nodes as &$node) {
			$node['shape'] = 'circle';
			$nodeamount = '$'.$node['nicecash'];
			if ($node['cash'] == 0){
				$nodeamount = "(amount not disclosed)";
			}
			$node['tooltip'] = safeLabel($node['Name']).'<br/>'.$nodeamount;
			$node['color'] = lookupIndustryColor($node['industry']);
			$node['fillcolor'] = '#c0c0c0';
			$node['tileimage'] = "$company_images"."c".$node['image'].".png";
			$image = "$company_images"."c".$node['image'].".png";
			//show oil or coal icon on graph
			if (! file_exists($image)) { 
				//if ($graph['properties']['type'] == 'carbon') { 
				//	$image = "$company_images"."cunknown_".$node['sitecode']."_co.png"; 
				//} else {
				//check if there is an "unknown" image for that type
				$image = "$company_images"."cunknown_".$node['industry']."_co.png"; 
				if (! file_exists($image)) {
					$image = "$company_images"."ccircle.png"; 
				}

			//special image from prop23 node
			//if ($node['id'] == 257){
			//	$image = "$company_images"."c".$node['image'].".png";
			}
				//}
				//$image = '';
			//}
			$node['image'] = '../www/images/carbon_round.png';
			
			$node['Name'] = htmlspecialchars($node['Name'], ENT_QUOTES);
			$node['label'] = $node['Name'];
			$node['fontname'] ="Arial, Helvetica, sans-serif";
		}
		$nodes = $this->scaleSizes($nodes, 'companies', 'cash');
		return $nodes;
	}

	//NEED TO HAVE COMMENTS GIVING THE NAMES OF THE PROPERTIES ADDED
	//Edge Ids need to be unique so P0001_2_1,P0001_2_2 
	//Need to add a 'fromID' and 'toID' property to each edge
	function org2org_fetchEdges() {
		dbwrite("SET group_concat_max_len := @@max_allowed_packet");
		$graph = $this->data;
		$orgIds = arrayToInString($graph['nodetypesindex']['companies']);
		$minContribAmount = $graph['properties']['minContribAmount'];
		
		//decide which edges to include based on which view
		$propView = $graph['properties']['prop'];
		$otherRestrict = '257';
		$view = "and (view in ('prop_23','both') or view is null)";
		if($propView == "26"){
			$view = "and (view in ('prop_25_26','both') or view is null)";
			$otherRestrict = '376';
		}
		$query = "select concat(from_id, '_', to_id) as id, to_id toId, from_id fromId, group_concat(transaction_number) as ContribIDs from (select from_id,to_id,amount cash,concat(\"'\", transaction_id, \"'\") transaction_number from relationships where type in ('cal','fed_c','fed_i') $view  union all select '322' from_id,to_id,sum(amount) cash,group_concat(concat(\"'\",transaction_id,\"'\")) from relationships  where type in ('cal','fed_c','fed_i') and amount < $minContribAmount $view and to_id = $otherRestrict) edges where from_id in ($orgIds) and to_id in ($orgIds) and cash >= $minContribAmount group by concat(from_id,to_id) order by from_id, to_id desc";

		
		$this->addquery('org2org', $query, $graph);
		$result = dbLookupArray($query);
		return $result;
	}
	
	function orgOwnOrg_fetchEdges() {
		dbwrite("SET group_concat_max_len := @@max_allowed_packet");
		$graph = $this->data;
		//decide which edges to include based on which view
		$propView = $graph['properties']['prop'];
		$view = "and (view in ('prop_23','both') or view is null)";
		if($propView == "26"){
			$view = "and (view in ('prop_25_26','both') or view is null)";
		}
		$orgIds = arrayToInString($graph['nodetypesindex']['companies']);
		$minContribAmount = $graph['properties']['minContribAmount'];
		
		$query = "select concat('m_',from_id, '_', to_id ) as id, from_id fromId, to_id as toId,  group_concat(concat(\"'\", transaction_id, \"'\")) as ContribIDs from relationships where type = 'member' and from_id in ($orgIds) and to_id in ($orgIds) $view group by from_id, to_id";

		
		$this->addquery('orgOwnOrg', $query, $graph);
		$result = dbLookupArray($query);
		return $result;
	}

function org2org_edgeProperties() {
		$graph = $this->data;
		$orgIds = arrayToInString($graph['nodetypesindex']['companies']);
		$sitecode = "";
		$congress = "";
		$precheck = "";
		$breakdown = "";
		//decide which edges to include based on which view
		$propView = $graph['properties']['prop'];
		$otherRestrict = '257';
		$view = "and (view in ('prop_23','both') or view is null)";
		if($propView == "26"){
			$view = "and (view in ('prop_25_26','both') or view is null)";
			$otherRestrict = '376';
		}

		$query = "select concat(from_id, '_', to_id) as id, sum(edges.cash) as cash, format(sum(edges.cash),0) as nicecash,type as industry from (select from_id,to_id,amount cash,transaction_id from relationships where type in ('cal','fed_c','fed_i') $view UNION all select '322' fromId,to_id,sum(amount) cash,group_concat(transaction_id) transaction_number from relationships where type in ('cal','fed_c','fed_i') and amount < 1000 and to_id = $otherRestrict $view) edges join entities on from_id = entityid where from_id in ($orgIds) and from_id in ($orgIds) group by concat(from_id,to_id) order by from_id, to_id desc";
		$this->addquery('org2org_props', $query, $graph);
		$edgeprops = dbLookupArray($query);
		//$graph['edges']['com2can'] = $nodes;  //don't use this, would replace the edges
		$edges = array();	
		foreach($graph['edgetypesindex']['org2org'] as $key) {
			$edge = $graph['edges'][$key];
			if(! array_key_exists($edge['id'], $edgeprops)) { 
				unset($graph['edges'][$key]); 
				unset($graph['edgetypesindex']['org2org'][$key]); 
				continue;
			}
			$edge['cash'] = $edgeprops[$edge['id']]['cash'];   //get the appropriate ammount properties
			$edge['nicecash'] = $edgeprops[$edge['id']]['nicecash']; 
			$edge['Name'] = htmlspecialchars($graph['nodes'][$edge['fromId']]['Name'], ENT_QUOTES);
			$edge['OrganizationName'] = $graph['nodes'][$edge['toId']]['Name'];
			$edge['weight'] = $edge['cash'];
			$edge['tooltip'] = '$'.$edge['nicecash'];
			$edge['type'] = 'org2org';
			$edge['color'] = lookupIndustryColor($edgeprops[$edge['id']]['industry']);
			$edge['class'] = 'level2';
			$edges[$key] = $edge;
		}
		$edges = $this->scaleSizes($edges, 'org2org', 'cash');
		return $edges;
	}
	
	//MEMBERSHIP EDGES
function orgOwnOrg_edgeProperties() {
		$graph = &$this->data;
		$orgIds = arrayToInString($graph['nodetypesindex']['companies']);
		$sitecode = "";
		$congress = "";
		$precheck = "";
		$breakdown = "";

		$query = "select concat('m_',from_id, '_', to_id ) as id, 40 as weight, details nicecash from  relationships where type = 'member' and to_id in ($orgIds) and from_id in ($orgIds)";
		$this->addquery('orgOwnOrg_props', $query, $graph);
		$edgeprops = dbLookupArray($query);
		//$graph['edges']['com2can'] = $nodes;  //don't use this, would replace the edges
		
		$edges = array();
		foreach($graph['edgetypesindex']['orgOwnOrg'] as $key) {
			$edge = $graph['edges'][$key];
			if(! array_key_exists($edge['id'], $edgeprops)) { 
				unset($graph['edges']['orgOwnOrg'][$key]); 
				continue;
			}
			$edge['click'] = "this.selectEdge('".$edge['id']."');";
			$edge['weight'] = $edgeprops[$edge['id']]['weight'];   //get the appropriate ammount properties
			$edge['nicecash'] = $edgeprops[$edge['id']]['nicecash']; 
			$edge['Name'] = htmlspecialchars($graph['nodes'][$edge['fromId']]['Name'], ENT_QUOTES);
			$edge['OrganizationName'] = $graph['nodes'][$edge['toId']]['Name'];
			//$edge['weight'] = $edge['cash'];
			$edge['type'] = 'orgOwnOrg';
			$edge['color'] = '#CCCCCC';
			//$edge['style'] = 'dashed';
			$edge['size'] = 10;
			$edges[$key] = $edge;
		}
		return $edges;
		//$this->scaleSizes('orgOwnOrg', 'weight');
	}
	
	//add subgraphs to group the various industries together
	/*
	function getSubgraphs() {
		$graph = &$this->data;
		$orgIds = arrayToInString($graph['nodes']['companies']);
		$prop23 = fetchCol("select distinct from_id from relationships where to_id = 257 and from_id in ($orgIds)");
		$graph['subgraphs']['cluster_prop23']['nodes'] = $prop23;
		$graph['subgraphs']['cluster_prop23']['properties'] = array('rank' => 'min');
		//$graph['subgraphs']['propositions']['properties'] = array('rank' => 'max');
		//get the list of props
		//$propositions = fetchCol("select entityid from entities where type = 'political' and entityid in ($orgIds)");
		//$graph['subgraphs']['propositions']['nodes'] = $propositions;
		//oil
		//$oilcomps = fetchCol("select entityid from entities where type = 'oil' and entityid in ($orgIds)");
		//$graph['subgraphs']['oil']['nodes'] = $oilcomps;
	}
	*/

	/**
	UI Functions for displaying info on graph ui.   fixme: need standard naming  convention.  ajax_<nodetype>_showInfo()  ?
	**/

	

	function ajax_showCanInfo() {
		$types = array('P'=>'President', 'H'=>'House', 'S'=>'Senate');
		$id = dbEscape($_GET['id']);
		$output = "";
		$output .= canInfoHeader($id)."<a href='#' onclick=\"toggleDisplay('tables'); return false;\">Show contributions</a> ";
		//use a differnt url if it is a prez candidate
		if (substr($id,0,1) != 'P'){
			$output .= " <a class='email_button' href='http://priceofoil.org/action/'>Send Email</a>"; 
			//echo " | <a href ='voteTables.php?chamber=".substr($id,0,1) ."#$id'>Voting profile</a></div> ";
		} else {
			 //lookup the appropriate url
			 //DISABLED 'CAUSE PREZ CAMPAIGN INACTIVE
			 /*
			$url = fetchRow("select DIA_form_url from presidential_candidates where candidateid ='$id';");
			if ($url[0]){
				$output .= " | <a href='".htmlspecialchars($url[0])."'>Send Email </a>";
				//$output .= "</div> ";
			}
			*/
		}
		return $output;
	}

	function ajax_showOrgInfo() {
		$id = dbEscape($_GET['id']);
		$output = comInfoHeader($id);
		$output.= "<a href='#' onclick=\"toggleDisplay('cotables'); return false;\">Show contributions</a>";
		return $output;
	}

	function ajax_displayEdge($graph) {
		//require_once('graphtable.php');
		$id = dbEscape($_REQUEST['edgeid']);
		$edge = $graph->data['edges'][$id];
		$ids = $edge['ContribIDs'];
		$nodes = array($edge['fromId'], $edge['toId']);
		if ($edge['type'] == 'org2org') { 
			$query = "select transaction_id as id,from_name as 'Donor Name', Details, f.state 'State', f.zip 'Zip',transaction_date Date, concat('$',format(amount,0)) as Contribution, concat('<a href=\"', r.source, '\">Source</a>') as URL from relationships r join entities f on from_id = f.entityid where transaction_id in ($ids)";
		} else {
			$query ="select transaction_id as id,from_name as 'Owner/Member Name', Details, f.state 'State', f.zip 'Zip',transaction_date Date, 'Unknown amount' as Contribution, concat('<a href=\"', r.source, '\">Source</a>') as URL from relationships r join entities f on from_id = f.entityid where transaction_id in ($ids)";
		}
		$output = dbLookupArray($query);
		foreach($output as &$contrib) { 
			$contrib['URL'] = "<a href='".htmlspecialchars($contrib['URL'])."'>URL</a>";
			unset($contrib['id']);
		}
		return $output;
	}

	function preProcessGraph() {
		$props = &$this->data['properties'];
		if($props['candidateFilterIndex']) { 
			$props['prop'] = 26;
		} else { 
			$props['prop'] = 23;
		}
	}
	
}

