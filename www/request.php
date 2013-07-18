<?php

header('Content-type: application/json');
include("../config.php");

$response = json_decode('{"contributionsByIndustry":[ { "group": "pirates", "label": "Pirates", "value": 123456 }, { "group": "ninja", "label": "Ninjas", "value": 234567 }, { "group": "robots", "label": "Robots", "value": 345678 } ], "value":[ { "group": "D", "label": "Democrats", "value": 123456 }, { "group": "R", "label": "Republicans", "value": 234567 } ] }', true);
$response['contributionsByYear'] = getContributionsByYear();
$response['contributionsByCategory'] = getContributionsByCategory();
print json_encode($response);

function getContributionsByYear() {
	global $min_cycle;
	$field = 'company_id';
	$where = " and cycle >= $min_cycle ";
	if ($_REQUEST['type'] == 'candidates') {
		$field = 'recipient_ext_id';
		$where = "";
	}
	return array_values(dbLookupArray("select cycle as label, sum(amount) as value from contributions_dem where $field = '".dbEscape(str_replace('co-', '', $_REQUEST['id']))."' $where group by cycle order by cycle"));
}

function getContributionsByCategory() {
	$category = $_REQUEST['type'] == 'candidates' ? 'sitecode' : 'party';
	return array_values(dbLookupArray("select $category as label, sum(amount) as value from contributions_dem c join legislator_terms on recipient_ext_id = imsp_candidate_id and term = cycle where ".($_REQUEST['type'] == 'candidates' ? 'recipient_ext_id' : 'company_id')." = '".dbEscape(str_replace('co-', '', $_REQUEST['id']))."' group by $category order by $category"));
}
