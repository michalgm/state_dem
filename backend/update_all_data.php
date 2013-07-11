<?php
if (! isset($argv[1])) { 
	print "Usage: php update_all_data.php <download_influence_explorer_data_boolean>\n"; exit;
}
if ($argv[1]) { 
	system("php download_influence_explorer_data.php 1"); #Fill in nimsp_offices and nimsp_ballots 
}
system("php fetch_nimsp_data.php"); #Fill in nimsp_offices and nimsp_ballots 
system("php fetch_sunlight_legislators.php"); #Fill in legislators and legistlator_terms
system("php fetch_candidate_images.php"); #Fill in legislators and legistlator_terms
system("php import_state_dem_data.php"); #Fill in legislators and legistlator_terms
