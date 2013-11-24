<?php
include_once('../config.php');

$db = dbconnect();

if (isset($argv[1])) { 
	dbwrite("delete from legislators where chamber = 'governor'");
	dbwrite("delete from legislator_terms where seat = 'state:governor'");
}

$not_gov_ids = arrayValuesToInString(array(142074, 145282, 105213, 3157));
$govs = dbLookupArray("select recipient_ext_id, recipient_name, recipient_state from contributions_dem_all_candidates
	where  seat = 'state:governor' and recipient_type = 'P' and cycle >= ".($max_cycle -2)." and recipient_state in (".arrayValuesToInString($states).") and recipient_ext_id not in ($not_gov_ids) and seat_result = 'W' group by recipient_ext_id 
	order by recipient_state, max(cycle) desc");

#$govs = dbLookupArray("select nimsp_candidate_id, full_name from governors");
foreach($govs as $gov) {
	$id = $gov['recipient_ext_id'];
	$name = $gov['recipient_name'];
	$name = preg_replace("/ \&.*$/", "", $name);
	$name = preg_replace("/ \(.*$/", "", $name);
	print "$name - $gov[recipient_state] :";
	$lookup_url = "http://transparencydata.com/api/1.0/entities/id_lookup.json?apikey=$sunlightKey&namespace=urn:nimsp:recipient&id=$id";
	$result = json_decode(file_get_contents($lookup_url), true);
	$transparencydata_id = $result[0]['id'];
	$ent_url = "http://transparencydata.com/api/1.0/entities/$transparencydata_id.json?apikey=$sunlightKey";
	$ent_json = file_get_contents($ent_url);
	if (! $ent_json) { 
		print "unable to fetch $leg[full_name] ($leg[transparencydata_id])\n";
	} else {
		$entity = json_decode($ent_json, true);
		#foreach($entity['external_ids'] as $ext_id) { 
			//if ($ext_id['namespace'] == 'urn:nimsp:recipient') { 
			//	$leg['nimsp_candidate_id'] = $ext_id['id'];
			//	break;
			//}
		#}
		$photo_url = (isset($entity['metadata']['photo_url']) && $entity['metadata']['photo_url']) ? $entity['metadata']['photo_url'] : '';
		$party = $entity['metadata']['party'];
		foreach(array_keys($entity['metadata']) as $key) {
			if (preg_match("/\d\d\d\d/", $key)) { 
				$year = $entity['metadata'][$key];
				if ($year['seat_result'] == 'W' && $year['seat'] == 'state:governor') {
					print_r($year);
					print "$key ";
					$query = "insert ignore into legislator_terms values('$id', null, '$year[state]', $key, '', '$year[party]', 'state:governor')";
					dbwrite($query);
				}
			}
		}
		dbwrite("insert ignore into legislators (full_name, transparencydata_id, photo_url, party, state, nimsp_candidate_id, chamber) values('$name', '$transparencydata_id', '$photo_url', '$party', '$gov[recipient_state]', $id, 'governor')");
		print "\n";
	}
	
}
print "\nCleaning up names\n";
dbwrite("update legislators set full_name = replace(full_name, 'Representative ', '')  where full_name like 'representative %'");
dbwrite("update legislators set full_name = replace(full_name, 'Senator ', '')  where full_name like 'Senator %'");
dbwrite("update legislators set full_name = replace(full_name, '\\\', '')  where full_name like '%\\\%'");
#dbwrite("update governors set full_name = replace(full_name, '\"', \"'\"), first_name = replace(first_name, '\"', \"'\") where full_name like '%\"%'");
dbwrite("update legislators set last_name = name_case(substring_index(full_name, ', ', 1)), first_name = name_case(substring_index(full_name, ', ', -1)) where full_name like '%, %' and full_name not rlike '[SJ]r\.?$' and last_name = ''");
dbwrite("update legislators set full_name = concat(first_name, ' ', last_name) where full_name like '%, %' and full_name not rlike '[SJ]r\.?$'");

