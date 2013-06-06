<?php 
 

function lookupPartyColor($party) {
	global $partycolor;
	$color = "#CCCCCC";
	if (isset($partycolor[strtoupper($party)])) { 
		$color = $partycolor[strtoupper($party)];
	}
	return $color;
}

function lookupIndustryColor($industry) {
	$color = "gray";
	if ($industry == "coal" ) { 
		$color = "#958d63";
	} else if ($industry == "oil"){
		$color = "#6d8f9d";
	} else if ($industry == "political"){
		$color = "#000000";
	}
	return $color;
}	

//insertclean: takes assoc. array and inserts data into contribs_clean db
function insertclean($row) {
	global $db;
	$query = "replace into contribs_clean set ";
	foreach (array_keys($row) as $key) { 
		$query .= " $key='".dbEscape($row[$key])."',";
	}
	$query = substr($query, 0, -1);
	$res = dbwrite($query);
	return $res;
}
	
//getCandidateId: looks up candidate id from filer (committee) id
function getCandidateId($filerid) {
	global $electionyear;
	$candid = fetchRow("select a.CandidateID, YearOfElection from candidates_$electionyear a where a.CandidateID = '$filerid'");
	if (! $candid[0]) { 
		$candid = fetchRow("select a.CandidateID, YearOfElection from committees_$electionyear a join candidates_$electionyear b using (CandidateID)  where a.CommitteeId = '$filerid'");
	}	
	return array($candid[0], $candid[1]);
}

//allContribsToCandidates: gets total contributions to each presidential candidate, returns assoc. array
function allContribsToCandidates() {
	global $electionyear;
	$query = "select CandidateName,  PartyDesignation1,sum(Amount) as cash from candidates_main a join committee_main b on a.CandidateID = b.CandidateID join individualcontrib_main c on b.CommitteeID = c.FilerID where a.CandidateID like 'P%' and YearOfElection = '$electionyear' group by a.CandidateID order by cash";
	return dbLookupArray($query);
}

//allContribsFromCompanies: gets total contributions from each oil company, return assoc. array
function allContribsFromCompanies() {
	$query = "select b.Name, sum(Amount) as cash from contribs_clean a join oilcompanies b on a.CompanyID = b.id group by a.COmpanyID order by cash";
	return dbLookupArray($query);
}

//oilContribsByCompany: gets total contrbutions to presidential candidates from  each oil company,
//returns assoc. array
function oilContribsByCompany() {
	$query = "select b.Name, sum(Amount) as cash from contribs_clean a join oilcompanies b on a.CompanyID = b.id join candidates_main c on a.CandidateID = c.CandidateID where a.CandidateID like 'P%' group by a.COmpanyID order by cash";
	return dbLookupArray($query);
}

//oilContribsByCandidate: gets total oil contributions to each presidential candidate, returns
//assoc. array
function oilContribsByCandidate() {
	global $electionyear;
	$query = "select CandidateName, PartyDesignation1, c.Name, Amount from contribs_clean a join candidates_main b on a.CandidateID = b.CandidateID join oilcompanies c on a.CompanyID = c.id where a.CandidateID like 'P%' and YearOfElection = '$electionyear' order by CandidateName, c.Name, Amount";
	return dbLookupArray($query);
}

//oilContribsSummary: gets total contributions by each oil company to each presidential candidate,
//returns assoc array 
function oilContribsSummary()  {
	global $electionyear;
	return dbLookupArray("select CandidateName, PartyDesignation1, sum(Amount) as cash, count(FEC_ID) as contribs from contribs_clean join candidates_main on contribs_clean.CandidateID = candidates_main.CandidateId join oilcompanies on contribs_clean.CompanyID = oilcompanies.id where YearOfElection = '$electionyear' and contribs_clean.CandidateID like 'P%' group by contribs_clean.CandidateID order by cash");
}

function lookupYears() {
	$tables = dbwrite("show tables like 'individualcontribs%'");
	while ($tablename = $tables->fetch_array()) {
		$tablename = $tablename[0];
		$years[] = substr($tablename, -2);
	}
	return $years;
}

function lookupCommitties($racecode) {
  $committees = dbLookupArray("select * from `cong_committees` where cong_committee_id like '".$racecode."%' or cong_committee_id like 'J%' ;");
	return $committees;
}

function canInfoHeader($id) {
	global $graph;
	global $current_congress;

	$congnum = $graph->data['properties']['congress_num'];
	$electionyear = substr((($congnum+894)*2),2,2);
	$racecode = $graph->data['properties']['racecode'];
	$sitecode = $graph->data['properties']['sitecode'];
	$node =  $graph->data['nodes']['candidates'][$id]; 
	if ($sitecode && $sitecode != 'carbon') { 
		$sitefilter = " and sitecode = '$sitecode' "; 
	} else { $sitefilter = ""; }

	$cash = $node['nicecash'];
	$party = substr($node['PartyDesignation1'],0,1);
	$links = getLinks($node);
	$oildollar = $links['oildollar'];
	$govtrack = $links['govtrack'];
	$profile = $links['profile'];
	$score = "";
	$pac_total = 0;
	$contact = "";
	$state = "";
	$district = "";
	$when = "";
	$details = "";
	$email = "";

	/*
	//Set PAC total
	if ($congnum != 'total') { 
		$conglimit = " and congress_num = $congnum ";
	} else { $conglimit = ""; }
	$pac_total = fetchRow("select format(sum(amount),0), sum(amount) from contributions where CandidateID = '$id' and candidateid like concat(racecode, '%') $conglimit and type = 'c' $sitefilter group by candidateid");
	$pac_total = $pac_total[0] ? $pac_total[0] : 0;
	 */
	
	//Set Vote Score
	if ($racecode != 'P') {
		#$score = getVoteScore($id, $congnum);
		//currently not used, 'cause based on 2008 votes
		if ($score){
			$score = " Supported $sitecode in <strong>$score%</strong> of selected votes. ";
		}
	}

	//Set Contact info
	if ($racecode != 'P' and ($congnum == $current_congress || $congnum == 'total')) {
		#$info = getContactInfo($id);
		if ( 0 && $info[0]) {
			$contact['mail'] = $info[0];
			$contact['phone'] = $info[1];
		}
		if ($congnum != 'total') { 
			$email = "<span class='email'><a href='http://salsa.democracyinaction.org/o/790/campaign.jsp?campaign_KEY=22235'>Send Email</a></span>"; 
		}
	}

	//Set State
	if ($racecode != 'P') {
		$state = "-".$node['campaignstate'];
	}

	//Set District
	if($racecode == 'H') {
		$district = $node['currentdistrict'];
	}
	
	if ($sitecode == 'carbon') { 
		//$details = " ($$node[coalcash] coal, $$node[oilcash] oil)";
	}
	
	$blurb = getBlurb($node, $cash);
	$output = "<div class='canheader header'>\n";
	#$output .= "$blurb";
	if (isset($contact['mail'])) {
		$output.="<div class='contact_info'>
		<small><strong>Mail:</strong>$contact[mail] <strong>Phone:</strong>$contact[phone]</small>
		</div>\n";
	}
	$output .= $profile;
	$output .= $oildollar;
	$output .= $govtrack;
	#$output .= $email;
	$output .= "</div>\n";
	return $output;
}
	
function comInfoHeader($id) {
	global $graph;

	$company = $graph->data['nodes'][$id];
	$email = "";
	$links = getLinks($company);
	$blurb = getBlurb($company, $company['nicecash']);
	//$crp = $links['crp'];
	//$croc = $links['croc'];
	$profile = $links['profile'];
	$links['notes'] = preg_replace("/&/", '&amp;', $links['notes']);

	$output = "
	   	<div class='comheader header'>
			$links[notes]
			$links[profile]
		</div>
		";
	return $output;	
}

function getVoteScore($id, $congnum) {
	//FIXME: This need fixed to actually work, and maybe aggregate scores across congreses 
	$query = "select fec_id as candidateid, voting_score from congressmembers left join vote_scores on fec_id = candidateid where fec_id = '$id' ";
	$info = dbLookupArray($query);
	return;
}

function getContactInfo($id) {
	//FIXME: This need fixed to actually work, and maybe aggregate scores across congreses 
	$query = "select fec_id as candidateid, voting_score from congressmembers left join vote_scores on fec_id = candidateid where fec_id = '$id' ";
	$info = dbLookupArray($query);
	return;
}

function getCansFromDistrict($district) { 
	global $current_congress;
	$state = substr($district, 0, 2);
	$query = "select fec_id from congressmembers where concat(State_abbreviation, district) = '$district' and congress_num = '$current_congress' and chamber = 'H' and enddate > NOW()
		union select fec_id from congressmembers where State_abbreviation = '$state' and congress_num = '$current_congress' and chamber = 'S' and enddate > NOW()";
	$res = fetchCol($query);
	return $res;
}

function getLinks($node) {
	global $graph;
	global $type;
	global $current_congress;
	$links = array();
	$id = $node['id'];
	
	$dem = "";
	$crp = "";
	$type = 'companies';

	$dem_id = fetchRow("select  entityid,dem_id from entities where entityid = $id ");
	$profile = "";
	if (isset($dem_id[1])){
		$dem_id	= $dem_id[1];
		$profile = "<a href='http://dirtyenergymoney.com/view.php?type=search&amp;com=".$dem_id."'  title='Go to Dirty Energy Money Profile' class='profilelink go'>Profile</a>";
	
	} 
	//$links['croc'] = $croc;
	//$links['crp'] = $crp;

	$links['profile'] = $profile;

	$notes=fetchRow("select notes from entities where entityid = $id ");
	$notes=$notes[0];
	$links['notes'] = $notes;
	
	return $links;
}

function getBlurb($node, $amount=0) {
	global $graph;
	$blurb = "";
	$info = fetchRow("select entityid,notes,source from entities where entityid = $node[id] ");
	$blurb = "$info[1]. $node[Name] has contributed $$amount to the Proposition 23 campaign.";
	
	return "<div class='blurb'>$blurb</div>";
}



function getImage($image, $type, $small=0, $sitecode='') {
	$picurl = "";
	$prefix = "";
	if ($small) { 
		$prefix = 's';
	}
	$image = str_replace('.png', '.jpg', $image);
	if (substr($image, -4, 4) != '.jpg') { $image .= '.jpg'; }
	if ($type == 'can' || $type == 'Can' || $type == 'candidate') {
		global $candidate_images;
		$image = str_replace('../www/can_images/', '', $image);
		$canimagepath = str_replace('../www/', '', $candidate_images);
		if (file_exists("$candidate_images/$image")) {
			$picurl = "$canimagepath$prefix$image";
		} else { $picurl = "$canimagepath$prefix"."unknownCandidate.jpg"; }
	} else {
		global $company_images;
		$image = str_replace('../www/com_images/', '', $image);
		if (isset($image[0]) && $image[0] == 'c') { 
			$image = substr($image, 1);
		}
		$comimagepath = str_replace('../www/', '', $company_images);
		if (file_exists("$company_images/$image")) {
			$picurl = "$comimagepath$prefix$image";
		} elseif (file_exists("$company_images/unknown_$sitecode"."_co.jpg")) {
			$picurl = "$comimagepath$prefix"."unknown_$sitecode"."_co.jpg"; 
		} else {
			$picurl = "$comimagepath$prefix"."circle.jpg"; 
		}
	}
	return $picurl;
}
?>
