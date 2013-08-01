<?php
if (! isset($argv[1])) { 
	print "Usage: php update_all_data.php <download_influence_explorer_data_boolean>\n"; exit;
}
if ($argv[1]) { 
	passthru("php download_influence_explorer_data.php $argv[1]"); #Update contrib data, optionally fetching it from url 
}
passthru("php fetch_nimsp_data.php"); #Fill in nimsp_offices and nimsp_ballots 
passthru("php fetch_sunlight_legislators.php"); #Fill in legislators and legistlator_terms
passthru("php fetch_candidate_images.php"); #Fill in legislators and legistlator_terms
passthru("php import_state_dem_data.php"); #Fill in legislators and legistlator_terms
