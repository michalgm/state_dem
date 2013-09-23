<?php
header('Access-Control-Allow-Origin: *');  
include("../config.php");

$id = str_replace('co-', '', dbEscape($_REQUEST['id']));
$id_field = $_REQUEST['type'] == 'candidates' ? 'recipient_ext_id' : 'company_id';
$state = dbEscape($_REQUEST['state']);
$chamber = dbEscape($_REQUEST['chamber']);
$where = " $id_field = '$id' and cycle >= $min_cycle and t.seat = '$chamber' and recipient_state = '$state' ";


switch($_REQUEST['method']) {
	case 'chartData': 
		header('Content-type: application/json');
		$response = array();
		$response['contributionsByYear'] = getContributionsByYear();
		$response['contributionsByCategory'] = getContributionsByCategory();

		print json_encode($response);
		break;
	case 'csv':
		$csv = "";

		$contribs = dbLookupArray("SELECT transaction_id, full_name as Legislator, recipient_state as State, if(c.seat='state:lower', s.lower_name, 'Senate') as Chamber, c.District as District, t.Party, contributor_name as Contributor, if(Contributor_type = 'C', 'PAC', 'Individual') as 'Contributor Type', companies.name as Company, date_format(Date, '%m/%d/%Y') as Date, Cycle, Amount FROM contributions_dem c join companies on company_id = id join legislators l on recipient_ext_id = nimsp_candidate_id join legislator_terms t on recipient_ext_id = imsp_candidate_id and cycle = term join states s on recipient_state = s.state where $where order by c.date asc");
		$csv = array2CSV(array_slice(array_keys(reset($contribs)), 1));
		foreach($contribs as $contrib) { 
			unset($contrib['transaction_id']);
			$csv .= array2CSV($contrib);
		}
		$first = reset($contribs);
		$filename = "Dirty Energy Contributions to ".$first[($_REQUEST['type'] == 'candidates' ? 'Legislator' : 'Company')];
		//if ($debug) { 
		//	print '<pre>'; 
		//} else {
		header('Content-type: text/csv');
		header("Content-disposition: attachment; filename=\"$filename.csv\"");
		//}
		print $csv;
		break;
}

function getContributionsByYear() {
	global $where;
	return array_values(dbLookupArray("select cycle as label, sum(amount) as value from contributions_dem c join legislator_terms t on recipient_ext_id = imsp_candidate_id and term = cycle where $where group by cycle order by cycle"));
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

