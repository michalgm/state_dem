<?php
include_once('../config.php');

$db = dbconnect();
$update_candidates = 1;
$update_committees = 0;

dbwrite("delete from imsp_offices");
dbwrite("delete from imsp_ballots");

print "updating ballots and offices\n";
foreach ($states as $state) { 
	$args = "$apiKey&state=$state";
	$years = array();
	$years_array = xml2array(file_get_contents($ftmUrl."base_level.elections.year.list.php?$args"));
	foreach ($years_array[1] as $year) {
		$years[] = $year['year'];
	}

	$states = array();
	$page = 0;
	$next_page = 'yes';

	print "$state";
	while ($next_page == 'yes') {
		print ".";
		$offices_array = xml2array(file_get_contents($ftmUrl."states.offices.php?$args&page=$page"));
		$next_page = $offices_array[0]['next_page'];
		foreach ($offices_array[1] as $office_array) {
			extract($office_array);
			dbwrite("insert into imsp_offices (state, year, code, office, recipients, contributions, dollars, state_name) values('$state_postal_code', $year, '$imsp_office_code', '$office', $total_recipients, $total_contribution_records, $total_dollars, '$state_name')");
			continue;
			if ($total_recipients == 0 || $total_contribution_records == 0 || $office == 'BALLOT INITIATIVE DATA') {
				continue;
			} else {
				if (! isset($states[$state_postal_code])) {
					$states[$state_postal_code] = array(
						'name' => $state_name,
						'years' => array()
					);
				}
				if (! isset($states[$state_postal_code]['years'][$year])) {
					$states[$state_postal_code]['years'][$year] = array(
						'races' => array(),
						'ballots' => array()
					);
				}
				$states[$state_postal_code]['years'][$year]['races'][$imsp_office_code] = array(
					'name'=>ucwords(strtolower($office)), 'records'=>$total_contribution_records, 'recipients'=>$total_recipients, 'dollars'=>$total_dollars
				);
			}
		}
		$page++;
	}

	$page = 0;
	$next_page = 'yes';

	while ($next_page == 'yes') {
		print "0";
		$ballots_array = xml2array(file_get_contents($ftmUrl."ballot_measures.list.php?$args&page=$page"));
		$next_page = $ballots_array[0]['next_page'];
		foreach ($ballots_array[1] as $ballots) {
			extract($ballots);
			$short_description = dbEscape($short_description);
			$long_description = dbEscape($long_description);
			dbwrite("insert into imsp_ballots (imsp_id, state, year, name, status, short_desc, long_desc, pro_dollars, pro_contributions, con_dollars, con_contributions, total_dollars, total_contributions, state_name) values($imsp_ballot_measure_id, '$state_postal_code', $year, \"$ballot_measure_name\", '$ballot_measure_status', \"$short_description\", \"$long_description\", $total_pro_dollars, $total_pro_contribution_records, $total_con_dollars, $total_con_contribution_records, $total_dollars, $total_contribution_records, '$state_name')");
			continue;
			if ($total_contribution_records == 0) {
				continue;
			} else {
				if (! isset($states[$state_postal_code])) {
					$states[$state_postal_code] = array(
						'name' => $state_name,
						'years' => array()
					);
				}
				if (! isset($states[$state_postal_code]['years'][$year])) {
					$states[$state_postal_code]['years'][$year] = array(
						'races' => array(),
						'ballots' => array()
					);
				}
				$states[$state_postal_code]['years'][$year]['ballots'][$imsp_ballot_measure_id] = array(
					'name'=>ucwords(strtolower(preg_replace("/PROPOSITION /", "Prop. ", $ballot_measure_name))), 'status'=>$ballot_measure_status, 'short_desc'=>ucwords(strtolower($short_description)), 'dollars'=>$total_dollars
				);
			}
		}
		$page++;
	}
}

if ($update_candidates) { 
	print "updating candidates\n";
	dbwrite('delete from imsp_candidates');
	#$races = dbLookupArray("select id, state, year, code from imsp_offices where state = 'CA' and year = 2010");
	#$races = dbLookupArray("select a.id, a.state, a.year, a.code from imsp_offices a left join races b on a.state=b.state and a.year = b.cycle and a.code = b.office where cycle is null group by a.state, a.year, a.code order by a.state, a.year, a.code");
	#$races = dbLookupArray("select id, state, year, code from imsp_offices where state='IL' and year=2010 and code='G00'");
	$races = dbLookupArray("select id, state, year, code from imsp_offices where year >= $min_cycle");
	foreach ($races as $raceinfo) { 
		extract($raceinfo);	
		print "\n$state $year $code";
		$startQuery ="candidates.list.php?$apiKey&candidate_status=WON&state=".$state."&year=".$year."&office=".$code;
		$page = 0;
		$next_page = 'yes';

		while ($next_page == 'yes') {
			print '#';
			$queryUrl = $ftmUrl.$startQuery."&page=$page";
			$xml = file_get_contents($queryUrl);
			if (! $xml) { 
				print "unable to open url $queryUrl";
				continue;   
			}
			list ($meta, $response) = xml2array($xml);
			foreach($response as $can) {
				print '.';
				extract($can);
				dbwrite("insert into imsp_candidates values('$imsp_candidate_id', '$unique_candidate_id', '$state', '$year', '$candidate_name', '$candidate_status', '$candidate_ICO_code', '$party', '$code', '$district ','$total_in_state_dollars ','$total_out_of_state_dollars ','$total_unknown_state_dollars ','$party_committee_dollars ','$candidate_leadership_committee_dollars ','$candidate_money_dollars ','$individual_dollars ','$unitemized_donation_dollars ','$public_fund_dollars ','$non_contribution_income_dollars ','$institution_dollars ','$total_contribution_records ','$total_dollars', null, null, '')");

			}
			if (! isset($meta['next_page'])) {
				print "0";
				break;
				print_r($xml." - ".$queryUrl);
			}
			$next_page = $meta['next_page'];
			$page++;
		}
	}
}

if ($update_committees) { 
	print "updating committees\n";
	dbwrite('delete from imsp_committees');
	$ballots = dbLookupArray("select id, state, year, imsp_id from imsp_ballots");
	foreach ($ballots as $ballotinfo) {
		extract($ballotinfo);
		print "\n$state $year $imsp_id";
		$startQuery = "ballot_measures.committees.php?$apiKey&state=".$state."&year=".$year."&imsp_ballot_measure_id=".$imsp_id;
		$page = 0;
		$next_page = 'yes';

		while ($next_page == 'yes') {
			print '$';
			$queryUrl = $ftmUrl.$startQuery."&page=$page";
			$xml = file_get_contents($queryUrl);
			if (! $xml) { die("unable to open url $queryUrl"); }
			list ($meta, $response) = xml2array($xml);
			foreach($response as $com) {
				print '-';
				extract($com);
				dbwrite("insert into imsp_committees values('$imsp_committee_id', '$state', $year, $imsp_id, '$committee_name' ,'$ballot_measure_count' ,'$total_pro_dollars' ,'$total_con_dollars' ,'$total_dollars' ,'$total_contribution_records', null)"); 
			}
			if (! isset($meta['next_page'])) {
				print "0";
				break;
				print_r($xml." - ".$queryUrl); exit;
			}
			$next_page = $meta['next_page'];
			$page++;
		}
	}
}

?>
      
