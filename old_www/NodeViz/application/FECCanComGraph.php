<?php
require_once('Graph.php');
require_once('oc_config.php');
$dbname = 'oilchange';
/*
creates the graph data structure that will be used to pass data among components.  Structure must not change
*/
class FECCanComGraph extends Graph { 

	function __construct() {
		parent::__construct();
		
		$this->racecode_filter_values = array(
			'P'=>" racecode='P' ",
			'S'=>" racecode='S' ",
			'H'=>" racecode='H' ",
			'C'=>" (racecode='H' or racecode='S') ",
		);

		// gives the classes of nodes
		$this->data['nodetypes'] = array('candidates', 'companies'); //FIXME - add properties for types
		
	  	// gives the classes of edges, and which nodes types they will connect
		$this->data['edgetypes'] = array( 'com2can' => array('companies', 'candidates'));
		
		// graph level properties
		$this->data['properties'] = array(
			'electionyear' => '00',
			'congress_num' => '110',
			'racecode' => 'S',
			'minCandidateAmount' =>'122750',
			'minCompanyAmount' => '31600',
			'minContribAmount' => '2500',
			'candidateFilterIndex' => -1,
			'companyFilterIndex' => -1,
			'contribFilterIndex' => -1,
			'candidateLimit' =>'',
			'companyLimit' =>'',
			'candidateids' => '',
			'companyids' => '',
			'allcandidates' => 0,
			//sets the scaling of the elements in the gui
			'minSize' => array('candidates' => '.25', 'companies' => '.25', 'com2can' =>'1'),
			'maxSize' => array('candidates' => '3', 'companies' => '3', 'com2can' =>'80'),
			'sitecode' => 'carbon',
			'log_scaling'=>0,
			'removeIsolates'=>1,
		);
		// special properties for controling layout software
		//BROKEN, USE GV PARAMS
		$this->data['graphvizProperties'] = array(
			'graph'=> array(
				'bgcolor'=>'#FFFFFF',
				//'size' => '6.52,6.52!'
			),
			'node'=> array('label'=> ' ', 'imagescale'=>'true','fixedsize'=>1, 'style'=> 'setlinewidth(7), filled', 'regular'=>'true'),
			'edge'=>array('len'=>8, 'arrowhead'=>'none', 'color'=>'#66666666')
		);
	}

	/**
	There must be a  <nodetype_fetchNodes() function for each defined node type.  
	It returns the ids of the nodes for the type. 
	**/

	function candidates_fetchNodes() {
		$graph = $this->data;
		if ($graph['properties']['candidateids']) {
			$nodes =  Array();
			foreach (explode(',', $graph['properties']['candidateids']) as $id) { 
				$nodes[$id] = Array();
				$nodes[$id]['id'] = $id;
			}
			return $nodes;
		}
		$congress_num = $graph['properties']['congress_num'];
		$racecode = $graph['properties']['racecode'];
		$racecode_filter = "";
		if (isset($this->racecode_filter_values[$racecode])) { 
			$racecode_filter = 'and '.$this->racecode_filter_values[$racecode];
		}
		$minCandidateAmount = $graph['properties']['minCandidateAmount'];
		$candidates = "congressmembers ";
		$congress = "a.congress_num = c.congress_num and ";
		if (is_numeric($congress_num) || $congress_num == 'pre') { 
			$congress_num = $congress_num == 'pre' ? 1 : $congress_num;
			$congress = "a.congress_num = '$congress_num' and c.congress_num = '$congress_num' and "; 
		} 
		if ($graph['properties']['allcandidates'] == 1 || $racecode == 'P') { $candidates = "pres_candidates"; }
		if ($graph['properties']['sitecode'] != 'carbon') { $sitecode = " and sitecode = '".$graph['properties']['sitecode']."' "; } else { $sitecode = ""; }

		$query ="select a.crpcandid as id from $candidates a join contributions c on a.crpcandid = c.recipient_id where $congress c.congress_num is not null $racecode_filter $sitecode group by a.crpcandid having sum(amount) >= $minCandidateAmount order by sum(amount) desc, a.crpcandid";
		$this->addquery('candidates', $query, $graph);

		$result = dbLookupArray($query);
		#$graph['nodes']['candidates'] = $result;
		return $result;
	}

	function companies_fetchNodes() {
		$graph = $this->data;
		if ($graph['properties']['companyids']) {
			$results = array();
			foreach (explode(',', $graph['properties']['companyids']) as $id) { 
				$results[$id] = Array();
				$results[$id]['id'] = $id;
			}
			return $results;
		}
		$congress_num = $graph['properties']['congress_num'];
		$racecode = $graph['properties']['racecode'];
		$racecode_filter = $this->racecode_filter_values[$racecode];
		$minCompanyAmount = $graph['properties']['minCompanyAmount'];
		$limit = "";
		$sitecode = "";
		$candidates = "congressmembers ";
		if ($graph['properties']['sitecode'] != 'carbon') { $sitecode = " and sitecode = '".$graph['properties']['sitecode']."' "; }
		$congress = "a.congress_num = c.congress_num and ";
		if (is_numeric($congress_num) || $congress_num == 'pre') { 
			$congress_num = $congress_num == 'pre' ? 1 : $congress_num;
			$congress = "a.congress_num = '$congress_num' and c.congress_num = '$congress_num' and "; 
		} 
		if ($graph['properties']['allcandidates'] == 1 || $racecode == 'P') { $candidates = "pres_candidates"; }
		$query ="select CompanyID as id from contributions a join $candidates c on a.recipient_id = c.crpcandid where $congress $racecode_filter and a.congress_num is not null $sitecode group by a.COmpanyID having sum(amount) >= $minCompanyAmount order by sum(amount) desc, CompanyID";
		$this->addquery('companies', $query, $graph);
		$result = dbLookupArray($query);
		//$graph['nodes']['companies'] = $result;
		return $result;
	}


	/**
	There must be a  <nodetype>_nodeProperties() function for each node class.
	It sets the properties of the nodes of that type. 
	**/
	function candidates_nodeProperties() {
		$graph = $this->data;
		global $candidate_images;
		global $current_congress;

		$congress_num = $graph['properties']['congress_num'];
		$racecode = $graph['properties']['racecode'];
		$racecode_filter = "";
		if (isset($this->racecode_filter_values[$racecode])) { 
			$racecode_filter = ' and '.$this->racecode_filter_values[$racecode];
		}
		$limit = "";
		$sitecode = "";
		$breakdown = "";
		if ($graph['properties']['sitecode'] != 'carbon') { 
			$sitecode = " and sitecode = '".$graph['properties']['sitecode']."' "; 
		} else { 
			$breakdown = ", format(sum(if(sitecode='coal', amount, 0)), 0) as coalcash, format(sum(if(sitecode='oil', amount, 0)),0) as oilcash ";
		}

		$congress = "a.congress_num = c.congress_num and ";
		if (is_numeric($congress_num) || $congress_num == 'pre') { 
			$congress_num = $congress_num == 'pre' ? 1 : $congress_num;
			$congress = "a.congress_num = '$congress_num' and c.congress_num = '$congress_num' and "; 
		} 
		if ($graph['properties']['companyids']) {
			$company_ids = " and c.Companyid in (".arrayToInString($graph['nodes']['companies']).") ";
		} else {
			$company_ids = "";
		}
		if ($graph['properties']['candidateLimit']) { $limit = " limit ".$graph['properties']['candidateLimit']; }
		$idlist = arrayToInString($graph['nodetypesindex']['candidates']);
		if ($graph['properties']['allcandidates'] || $racecode == 'P') { 
			$cantable = "select crpcandid as CandidateID, CandidateName, substr(PartyDesignation1,1,1) as PartyDesignation1, currentdistrict, campaignstate, congress_num from pres_candidates a where a.crpcandid in ($idlist) ";
		} else { 
			$cantable = "select crpcandid as candidateid, concat(lastname, ', ', firstname) as CandidateName, party as PartyDesignation1, district as currentdistrict, state_abbreviation as campaignstate, congress_num from congressmembers a where crpcandid in ($idlist)";
		}
		if (! is_numeric($congress_num) ) {
			$cantable .= ' group by CandidateID, congress_num ';
		}
		$query ="select a.CandidateID as id,  CandidateName as Name, PartyDesignation1, sum(Amount) as cash, format(sum(Amount),0) as nicecash ,max(Amount+0) as max, min(Amount+0) as min, currentdistrict, campaignstate, if(d.congress_num = $current_congress, 1, 0) as current_member $breakdown from ($cantable) a join contributions c on a.CandidateID = c.recipient_id left join (select crpcandid, max(congress_num) as congress_num from congressmembers group by crpcandid) d on d.crpcandid = a.CandidateID where $congress c.congress_num is not null $racecode_filter $company_ids $sitecode group by a.CandidateID order by cash desc, a.CandidateID $limit";
		$this->addquery('candidates_props', $query, $graph);
		$nodes = dbLookupArray($query);
		#$graph['nodes']['candidates'] = $nodes;
		foreach($nodes as &$node) {
			$node['shape'] = 'box';
			if ($node['campaignstate'] != '00' && $node['campaignstate'] != '') {
				$state = "-$node[campaignstate]";
			} else { $state = ""; }
			$node['tooltip'] = safeLabel(niceName($node['Name'])." (".$node['PartyDesignation1'][0]."$state)").'<br/>$'.$node['nicecash'];
			$node['FName'] = htmlspecialchars(niceName($node['Name']), ENT_QUOTES);
			$node['Name'] = htmlspecialchars(niceName($node['Name'], 1), ENT_QUOTES);
			$node['color'] = lookupPartyColor($node['PartyDesignation1']);
			$node['fillcolor'] = 'white';
			$image = "$candidate_images".$node['id'].".jpg";
			if (! file_exists($image)) { $image = "$candidate_images"."unknownCandidate.jpg"; }
			$node['image'] = $image;
			$node['image'] = 'images/carbon_round.png';
			$node['type'] = 'Can';
		}
		$nodes = $this->scaleSizes($nodes, 'candidates', 'cash');
		return $nodes;
	}

	function companies_nodeProperties() {
		$graph = $this->data;
		global $company_images;
		global $current_congress;
		$congress_num = $graph['properties']['congress_num'];
		$racecode = $graph['properties']['racecode'];
		$racecode_filter = "";
		if (isset($this->racecode_filter_values[$racecode])) { 
			$racecode_filter = ' and '.$this->racecode_filter_values[$racecode];
		}
		$company_ids = arrayToInString($graph['nodetypesindex']['companies']);
		$candidate_ids = "";
		$sitecode = "";
		$limit = "";
		$breakdown = "";
		if ($graph['properties']['sitecode'] != 'carbon') { 
			$sitecode = " and sitecode = '".$graph['properties']['sitecode']."' "; 
		} else { 
			$breakdown = ", format(sum(if(sitecode='coal', amount, 0)), 0) as coalcash, format(sum(if(sitecode='oil', amount, 0)),0) as oilcash ";
		}

		$congressmembers = "congressmembers";
		if ($racecode == 'P') { 
			$congressmembers = "pres_candidates";
		}
		if (is_numeric($congress_num)) { 
			$congress = "a.congress_num = '$congress_num' and c.congress_num = '$congress_num' and "; 
		} else {
			$congress = " a.congress_num = c.congress_num and ";
		}
		if ($congress_num == 'pre') { 
			$congress .= " a.congress_num = 1 and ";
		}
		if ($graph['properties']['candidateids']) {
			$candidate_ids = " and a.recipient_id in (".arrayToInString($graph['nodes']['candidates']).") ";
		} else { 
			$candidate_ids = "";
		}
		if ($graph['properties']['companyLimit']) { $limit = " limit ".$graph['properties']['companyLimit']; }
		
		$query ="select CompanyID as id,b.Name , sum(Amount) as cash, format(sum(Amount),0) as nicecash, max(0 + Amount) as max, min(0 + Amount) as min, image_name as image, if(oil_related < coal_related, 'coal', 'oil') as sitecode $breakdown from contributions a join companies b on a.CompanyID = b.id join $congressmembers c on a.recipient_id = c.crpcandid where $congress a.congress_num is not null and a.CompanyID in ($company_ids) $candidate_ids $racecode_filter $sitecode group by a.COmpanyID order by cash desc, CompanyID $limit";
		$this->addquery('companies_props', $query,$graph);
		$nodes = dbLookupArray($query);
		#$graph['nodes']['companies'] = $nodes;
		foreach($nodes as &$node) {
			$node['shape'] = 'circle';
			$node['tooltip'] = safeLabel($node['Name']).'<br/>$'.$node['nicecash'];
			$node['color'] = lookupPartyColor($node['sitecode']);
			//$node['fillcolor'] = 'white';
			$image = "$company_images"."c".$node['image'].".png";
			if (! file_exists($image)) { 
				if ($graph['properties']['sitecode'] == 'carbon') { 
					$image = "$company_images"."cunknown_".$node['sitecode']."_co.png"; 
				} else {
					$image = "$company_images"."cunknown_".$graph['properties']['sitecode']."_co.png"; 
				}
			}
			$node['image'] = 'images/coal_round.png';
			$node['type'] = 'Com';
			$node['Name'] = htmlspecialchars($node['Name'], ENT_QUOTES);
		}
		$nodes = $this->scaleSizes($nodes, 'companies', 'cash');
		return $nodes;
	}

	//NEED TO HAVE COMMENTS GIVING THE NAMES OF THE PROPERTIES ADDED
	//Edge Ids need to be unique so P0001_2_1,P0001_2_2 
	//Need to add a 'fromID' and 'toID' property to each edge
	function com2can_fetchEdges() {
		dbwrite("SET group_concat_max_len := @@max_allowed_packet");
		$graph = $this->data;
		$congress_num = $graph['properties']['congress_num'];
		$candidateIds = arrayToInString($graph['nodetypesindex']['candidates']);
		$companyIds = arrayToInString($graph['nodetypesindex']['companies']);
		$minContribAmount = $graph['properties']['minContribAmount'];
		$limit = "";
		$sitecode = "";
		$congress = "";
		if ($graph['properties']['sitecode'] != 'carbon') { $sitecode = " and sitecode = '".$graph['properties']['sitecode']."' "; }
		if (is_numeric($congress_num)) { 
			$congress = "  a.congress_num  = '$congress_num' and"; 
		} elseif ($congress_num == 'pre') {
			$congress = " a.congress_num = 1 and "; 
		}
		$query ="select concat(a.CompanyID, '_', a.recipient_id) as id, a.recipient_id as toId, a.CompanyID as fromId, group_concat(concat(\"'\", a.crp_key, \"'\")) as ContribIDs from contributions a where a.recipient_id in ($candidateIds) and a.CompanyID in ($companyIds) and a.congress_num is not null and $congress amount >= $minContribAmount $sitecode group by concat(a.recipient_id, a.CompanyID) order by a.recipient_id, CompanyID desc";

		// Darrell:  recommend put the abs() around amount in the query above so that returned contributions also show up on the website.
		//	$query ="select concat(a.CompanyID, '_', a.CandidateId) as id, a.CandidateID as toId, a.CompanyID as fromId, group_concat(concat(\"'\", a.crp_key, \"'\")) as ContribIDs from contributions a  where a.CandidateID in ($candidateIds) and a.CompanyID in ($companyIds) and a.congress_num is not null and $congress abs(amount) >= $minContribAmount group by concat(a.CandidateID, a.CompanyID) order by a.CandidateId, CompanyID desc";
		$this->addquery('com2can', $query, $graph);
		$result = dbLookupArray($query);
		#$graph['edges']['com2can'] = $result;	
		return $result;
	}

	function com2can_edgeProperties() {
		$graph = $this->data;
		global $debug;
		$congress_num = $graph['properties']['congress_num'];
		$candidateIds = arrayToInString($graph['nodetypesindex']['candidates']);
		$companyIds = arrayToInString($graph['nodetypesindex']['companies']);
		$sitecode = "";
		$congress = "";
		$breakdown = "";

		if ($graph['properties']['sitecode'] != 'carbon') { 
			$sitecode = " and sitecode = '".$graph['properties']['sitecode']."' "; 
		} else { 
			$breakdown = ", sum(if(a.sitecode='coal', amount, 0)) as coalcash, sum(if(a.sitecode='oil', amount, 0)) as oilcash ";
		}
		if (is_numeric($congress_num)) { 
			$congress = "  a.congress_num  = '$congress_num' and"; 
		} elseif ($congress_num == 'pre') {
			$congress = " a.congress_num = 1 and "; 
		}

		$racecode = $graph['properties']['racecode'];
		$racecode_filter = "";
		if (isset($this->racecode_filter_values[$racecode])) { 
			$racecode_filter = 'and '.$this->racecode_filter_values[$racecode];
		}

		#This will limit the edges returned by the query - useful for debugging, but redundant and possible slower
		$edgelimits = $debug ? " and concat(a.CompanyID, '_', a.recipient_id) in ($edgeIds) " : "";

		#We don't apply a filter here because we want the values to be the total values, not the filtered values
		$query ="select concat(a.CompanyID, '_', a.recipient_id) as id, sum(Amount) as cash, format(sum(Amount), 0) as nicecash $breakdown from contributions a where $congress a.congress_num is not null and a.recipient_id in ($candidateIds) and a.CompanyID in ($companyIds) $edgelimits $racecode_filter $sitecode group by concat(a.recipient_id, a.CompanyID)  order by cash desc, a.recipient_id, CompanyID desc";
		$this->addquery('com2can_props', $query, $graph);
		$edgeprops = dbLookupArray($query);
		$edges = array();
		//$graph['edges']['com2can'] = $nodes;  //don't use this, would replace the edges
		foreach($graph['edgetypesindex']['com2can'] as $key) {
		   	$edge = $graph['edges'][$key];
			if(! array_key_exists($edge['id'], $edgeprops)) { 
				//unset($graph['edges'][$key]); 
				continue;
			}
			$edge['click'] = "this.selectEdge('".$edge['id']."');";
			$edge['cash'] = $edgeprops[$edge['id']]['cash'];   //get the appropriate ammount properties
			$edge['nicecash'] = $edgeprops[$edge['id']]['nicecash']; 
			$edge['tooltip'] = $edgeprops[$edge['id']]['nicecash']; 
			$edge['Name'] = htmlspecialchars($graph['nodes'][$edge['fromId']]['Name'], ENT_QUOTES);
			$edge['CandidateName'] = $graph['nodes'][$edge['toId']]['Name'];
			$edge['weight'] = $edge['cash'];
			$edge['type'] = 'com2can';
			if (isset($edgeprops[$edge['id']]['coalcash'])) {
				$edge['coalcash'] = $edgeprops[$edge['id']]['coalcash'];
				$edge['oilcash'] = $edgeprops[$edge['id']]['oilcash'];
			}
			$edges[$key] = $edge;
		}
		$edges = $this->scaleSizes($edges, 'com2can', 'cash');
		return $edges;
	}

	function graphname() {
		if (! $this->name) { 
			$graph = $this->data;
			global $datapath;
			$props = $graph['properties'];
			$localdatapath = "$datapath$props[sitecode]";
			if (! is_dir("$localdatapath")) {
				if (is_writable($localdatapath)) { 
					mkdir("$localdatapath") || trigger_error("unable to create dir $localdatapath", E_USER_ERROR); 
					chmod("$localdatapath", 0777);
				} else {
					trigger_error("unable to write to dir $localdatapath", E_USER_ERROR); 
				}
			}	
			if ($props['candidateids'] != '') { 
				if( ! is_dir("$localdatapath/individuals")) { 
					mkdir("$localdatapath/individuals") || trigger_error("unable to create dir $localdatapath/individuals", E_USER_ERROR); 
					chmod("$localdatapath/individuals", 0777);
					clearstatcache();
				}
				$graphname = "individuals/$props[candidateids]_$props[congress_num]";
			} elseif ($props['companyids'] != '') { 
				if( ! is_dir("$localdatapath/companies")) { 
					mkdir("$localdatapath/companies") || trigger_error( "unable to create dir $localdatapath/companies", E_USER_ERROR); 
					chmod("$localdatapath/companies", 0777);
					clearstatcache();
				}
				$graphname = "companies/$props[companyids]_$props[congress_num]";
			} else {
				$dir = "$props[racecode]$props[congress_num]";
				if( ! is_dir("$localdatapath/$dir")) { 
					mkdir("$localdatapath/$dir") || trigger_error( "unable to create dir $localdatapath/$dir", E_USER_ERROR); 
					chmod("$localdatapath/$dir", 0777);
					clearstatcache();
				}
				if (isset($props['candidateFilterIndex']) && $props['candidateFilterIndex'] != '-1') {
					$graphname = "$dir/$props[candidateFilterIndex]_$props[companyFilterIndex]_$props[contribFilterIndex]";
				} else { 
					$graphname = "$dir/$props[racecode]$props[congress_num]";
				}
			}
			$this->name = $props['sitecode'].'/'.$graphname;
		}
		return $this->name;
	}


	/**
	UI Functions for displaying info on graph ui.   fixme: need standard naming  convention.  ajax_<nodetype>_showInfo()  ?
	**/

	

	function ajax_showCanInfo() {
		$types = array('P'=>'President', 'H'=>'House', 'S'=>'Senate');
		$id = dbEscape($_GET['id']);
		$output = "";
		$output .= canInfoHeader($id)."<a href='#' onclick=\"toggleDisplay('tables'); return false;\">Show contributions</a> ";
		//use a differnt url if it is a prez candidate
		//if (substr($id,0,1) != 'P'){
		if ($_GET['racecode'] != 'P'){
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

	function ajax_displayEdge($graph) {
		$id = dbEscape($_REQUEST['edgeid']);
		$edge = $graph->data['edges'][$id];
		$ids = $edge['ContribIDs'];
		$query = "select * from contributions where crp_key in ($ids)";
		$results = dbLookupArray($query);
		foreach($results as &$contrib) { 
			foreach($contrib as &$value) {
				$value = htmlspecialchars($value);
			}
			unset($contrib['fec_id']);
		}
		return $results;
	}

	function ajax_showComInfo() {
		$types = array('P'=>'President', 'H'=>'House', 'S'=>'Senate');
		$id = dbEscape($_GET['id']);
		$output = comInfoHeader($id);
		$output.= "<a href='#' onclick=\"toggleDisplay('cotables'); return false;\">Show contributions</a>";
		return $output;
	}

	function ajax_showcom2canInfo($graph) {
		require_once('../www/lib/graphtable.php');
		$types = array('P'=>'President', 'H'=>'House', 'S'=>'Senate');
		$id = dbEscape($_GET['id']);
		$ids = $graph->data['edges']['com2can'][$id]['ContribIDs'];
		$nodes = explode('_', $id);
		$output = "<h2>Contributions from ".$graph->data['nodes']['companies'][$nodes[0]]['Name'];
		$output.= " to ".$graph->data['nodes']['candidates'][$nodes[1]]['Name']."</h2>";
		$output .= createContributionTable($graph, $nodes[0], $nodes[1]);
		return $output;
	}

	function preProcessGraph() {
		#Set True Filter Values
		$props = &$this->data['properties'];
		if ($props['candidateFilterIndex'] != -1) { 
			$options_js = file_get_contents('../www/optionDefaults.js');
			$options_js = str_replace('advanced_opts = ', '', $options_js);
			$options_array = json_decode($options_js, true);
			$opts = $options_array[$props['sitecode']][$props['congress_num']][$props['racecode']];
			$props['minContribAmount'] = $opts['minContribAmount'][$props['contribFilterIndex']];
			$props['minCompanyAmount'] = $opts['minCompanyAmount'][$props['companyFilterIndex']];
			$props['minCandidateAmount'] = $opts['minCandidateAmount'][$props['candidateFilterIndex']];
		}
	}

	function postProcessGraph() {
		global $edgestore_tag;
		return;
		#$edgestore_tag = date('Y-m-d H:i');
		if($edgestore_tag != '') { 
			$edgetypes = $this->data['edges'];
			$name = $this->graphname();
			foreach(array_keys($edgetypes) as $edgetype) {
				foreach(array_keys($this->data['edges'][$edgetype]) as $edgeid) {
					$edge = $this->data['edges'][$edgetype][$edgeid];
					$setstring = "tag='$edgestore_tag', graphname='$name', type='$edgetype', toid='$edge[toId]', fromid='$edge[fromId]', value=$edge[cash]";
					dbwrite("insert into edgestore set $setstring on duplicate key update $setstring" );
				}
			}
		}
	}
}

