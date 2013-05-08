<?php
system("php download_influence_explorer_data.php") #Fill in nimsp_offices and nimsp_ballots 
system("php fetch_nimsp_data.php") #Fill in nimsp_offices and nimsp_ballots 
system("php fetch_sunlight_legislators.php") #Fill in legislators and legistlator_terms
system("php fetch_candidate_images.php") #Fill in legislators and legistlator_terms
