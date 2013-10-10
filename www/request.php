<?php
header('Access-Control-Allow-Origin: *');  
include("../config.php");

$id = isset($_REQUEST['id']) ? str_replace('co-', '', dbEscape($_REQUEST['id'])) : '';
$id_field = $_REQUEST['type'] == 'candidates' ? 'imsp_candidate_id' : 'company_id';
$state = dbEscape($_REQUEST['state']);
$chamber = dbEscape($_REQUEST['chamber']);
$cycle = isset($_REQUEST['cycle']) ? dbEscape($_REQUEST['cycle']) : '';
$where = " $id_field = '$id' and term >= $min_cycle and t.seat = '$chamber' and t.state = '$state' ";


switch($_REQUEST['method']) {
	case 'chartData': 
		header('Content-type: application/json');
		$response = array();
		$response['contributionsByYear'] = getContributionsByYear();
		#$response['contributionsByCategory'] = getContributionsByCategory();

		print json_encode($response);
		break;
	case 'csv':
		$csv = "";

		if ($_REQUEST['type'] == 'chamber') { 
			$contribs = dbLookupArray("select imsp_candidate_id, term as Cycle, full_name as Name, if(t.seat='state:lower', s.lower_name, 'Senate') as Chamber, t.Party, t.District, concat('http://states.dirtyenergymoney.com/?',t.state, '/', if(t.seat='state:lower', s.lower_name, 'Senate'), '/', term, '/', imsp_candidate_id) as 'Profile Link', Lifetime_total as 'Lifetime Total', Lifetime_Oil as 'Lifetime Oil', Lifetime_Coal as 'Lifetime Coal', Lifetime_Carbon as 'Lifetime Misc',
				round(sum(if(sitecode = 'oil', amount, 0))) as '$cycle Oil',
				round(sum(if(sitecode = 'coal', amount, 0))) as '$cycle Coal',
				round(sum(if(sitecode = 'carbon', amount, 0))) as '$cycle Misc' 
				from legislator_terms t join legislators l on nimsp_candidate_id = imsp_candidate_id join states s on t.state = s.state
				left join contributions_dem a on  recipient_ext_id = nimsp_candidate_id and t.state = recipient_state and t.term = cycle and cycle = '$cycle' and recipient_state = '$state' and a.seat = '$chamber'
				where t.state = '$state' and term = '$cycle' and t.seat='$chamber' and s.state = '$state' group by imsp_candidate_id order by cast(substring(t.District, 4) as unsigned)", 1);
		} else {
			$contribs = dbLookupArray("SELECT transaction_id, full_name as Legislator, recipient_state as State, if(c.seat='state:lower', s.lower_name, 'Senate') as Chamber, c.District as District, t.Party, contributor_name as Contributor, if(Contributor_type = 'C', 'PAC', 'Individual') as 'Contributor Type', companies.name as Company, if(c.sitecode = 'oil', 'Oil', if(c.sitecode='coal', 'Coal', 'Misc')) as 'Company Industry', date_format(Date, '%m/%d/%Y') as Date, Cycle, Amount FROM contributions_dem c join companies on company_id = id join legislators l on recipient_ext_id = nimsp_candidate_id join legislator_terms t on recipient_ext_id = imsp_candidate_id and cycle = term join states s on recipient_state = s.state where $where order by c.date asc", 1);
		}
		$csv = array2CSV(array_keys(reset($contribs)));
		foreach($contribs as $contrib) { 
			$csv .= array2CSV($contrib);
		}
		if ($_REQUEST['type'] == 'chamber') { 
			$states = dbLookupArray("Select state, name, lower_name from states");
			$filename = "Dirty Energy Contributions to the $cycle ".$states[$state]['name']." ".($chamber == 'state:upper' ? 'Senate' : $states[$state]['lower_name']) ;
		} else {
			$first = reset($contribs);
			$filename = "Dirty Energy Contributions to ".$first[($_REQUEST['type'] == 'candidates' ? 'Legislator' : 'Company')] ;

		}
		if ($debug) { 
			print '<pre>'; 
		} else {
		header('Content-type: text/csv');
		header("Content-disposition: attachment; filename=\"$filename.csv\"");
		}
		print $csv;
		break;
}

function getContributionsByYear() {
	global $id, $min_cycle, $chamber, $state;
	$type = $_REQUEST['type'];
	$category = $type == 'candidates' ? 'sitecode' : 'party';
	$categories = $type == 'candidates' ? array('oil', 'coal', 'carbon') : array('D', 'R', 'I');
	$category_lookup = array();
	$aliases = array('D'=> 'DEM', 'R'=>'REP', 'I'=> 'IND');
	foreach($categories as $cat) {
		$label = $type == 'candidates' ? $cat : $aliases[$cat];
		if ($cat == 'I') {  //Include blank as independant
			$category_lookup[] = "sum(if(($category = '$cat' or $category = '' or $category = 'N'), amount, 0)) as $label";
		} else {
			$category_lookup[] = "sum(if($category = '$cat', amount, 0)) as $label";
		}
	}
	if ($type == 'candidates') { 
		$query = "select term as label, sum(if(amount, amount, 0)) as value, ".join(', ', $category_lookup)." 
			from legislator_terms t 
			left join contributions_dem c on recipient_ext_id = imsp_candidate_id and term = cycle 
			where imsp_candidate_id = '$id' and term >= $min_cycle and t.seat = '$chamber' and t.state = '$state'
			group by term order by term";
	} else {
		$query = "select year as label, sum(if(amount, amount, 0)) as value, ".join(', ', $category_lookup)." 
			from years y left join 
			legislator_terms t on year = term and t.seat = '$chamber' and t.state = '$state'
			left join contributions_dem c on recipient_ext_id = imsp_candidate_id and term = cycle and company_id = '$id'
			group by year order by year";
	}
	return array_values(dbLookupArray($query));
}

function getContributionsByCategory() {
	global $where;
	$category = $_REQUEST['type'] == 'candidates' ? 'sitecode' : 'party';
	return array_values(dbLookupArray("select $category as label, sum(amount) as value from contributions_dem c join legislator_terms t on recipient_ext_id = imsp_candidate_id and term = cycle where $where group by $category order by $category"));
}

function array2CSV (array $fields, $delimiter = ',', $enclosure = '"') {
    $delimiter_esc = preg_quote($delimiter, '/');
    $enclosure_esc = preg_quote($enclosure, '/');

    $output = array();
    foreach ($fields as $field) {
	//if (preg_match("/^[\d\.]+$/", $field)) { $field = number_format($field); } this would be nice, but it turns the nums into strings
        $output[] = preg_match("/(?:${delimiter_esc}|${enclosure_esc}|\s)/", $field) ? (
            $enclosure . str_replace($enclosure, $enclosure . $enclosure, $field) . $enclosure
        ) : $field;
    }
    return join($delimiter, $output) . "\n";
} 

