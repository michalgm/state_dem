<?php

header('Content-type: application/json');
include("../config.php");

$reponse = json_decode('{"contributionsByIndustry":[ { "group": "pirates", "label": "Pirates", "contributions": 123456 }, { "group": "ninja", "label": "Ninjas", "contributions": 234567 }, { "group": "robots", "label": "Robots", "contributions": 345678 } ], "contributionsByParty":[ { "group": "D", "label": "Democrats", "contributions": 123456 }, { "group": "R", "label": "Republicans", "contributions": 234567 } ] }', true);
$reponse['contributionsByYear'] = getContributions();
print json_encode($reponse);

function getContributions() {
	return array_values(dbLookupArray("select cycle as year, sum(amount) as contributions from contributions_dem where ".($_REQUEST['type'] == 'candidates' ? 'recipient_ext_id' : 'company_id')." = '".dbEscape(str_replace('co-', '', $_REQUEST['id']))."' group by year order by year"));
}
