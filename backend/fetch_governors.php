<?php
include_once('../config.php');

$db = dbconnect();

$lt_ids = arrayValuesToInString(array(142074, 145282, 105213, 3157));;
dbwrite("delete from governors");
dbwrite("insert into governors (full_name, transparencydata_id, nimsp_candidate_id, state)   (
	select recipient_name, recipient_ext_id, recipient_ext_id, recipient_state from contributions_dem_all_candidates
	where  seat = 'state:governor' and recipient_type = 'P' and cycle >= 2010 and recipient_state in ('CA', 'NY', 'CO', 'PA', 'AK', 'OH', 'TX') and recipient_ext_id not in ($lt_ids) and seat_result = 'W' group by recipient_ext_id 
	order by recipient_state, max(cycle) desc)");

$govs = dbLookupArray("select nimsp_candidate_id, full_name from governors");
foreach($govs as $gov) {
	$id = $gov['nimsp_candidate_id'];
	$name = $gov['full_name'];
	$name = preg_replace("/ \&.*$/", "", $name);
	$name = preg_replace("/ \(.*$/", "", $name);
	$lookup_url = "http://transparencydata.com/api/1.0/entities/id_lookup.json?apikey=$sunlightKey&namespace=urn:nimsp:recipient&id=$id";
	$result = json_decode(file_get_contents($lookup_url), true);
	$transparencydata_id = $result[0]['id'];
	$ent_url = "http://transparencydata.com/api/1.0/entities/$transparencydata_id.json?apikey=$sunlightKey";
	$ent_json = file_get_contents($ent_url);
	if (! $ent_json) { 
		print "unable to fetch $leg[full_name] ($leg[transparencydata_id])\n";
	} else {
		$entity = json_decode($ent_json, true);
		foreach($entity['external_ids'] as $ext_id) { 
			print "$ext_id[namespace]\n";
			//if ($ext_id['namespace'] == 'urn:nimsp:recipient') { 
			//	$leg['nimsp_candidate_id'] = $ext_id['id'];
			//	break;
			//}
		}
		$photo_url = (isset($entity['metadata']['photo_url']) && $entity['metadata']['photo_url']) ? $entity['metadata']['photo_url'] : '';
		$party = $entity['metadata']['party'];
		dbwrite("update governors set full_name = '$name', transparencydata_id='$transparencydata_id', photo_url='$photo_url', party='$party' where nimsp_candidate_id='$id'");
	}
	
}
print "\nCleaning up names\n";
dbwrite("update governors set full_name = replace(full_name, 'Representative ', '')  where full_name like 'representative %'");
dbwrite("update governors set full_name = replace(full_name, 'Senator ', '')  where full_name like 'Senator %'");
dbwrite("update governors set full_name = replace(full_name, '\\\', '')  where full_name like '%\\\%'");
#dbwrite("update governors set full_name = replace(full_name, '\"', \"'\"), first_name = replace(first_name, '\"', \"'\") where full_name like '%\"%'");
dbwrite("update governors set last_name = name_case(substring_index(full_name, ', ', 1)), first_name = name_case(substring_index(full_name, ', ', -1)) where full_name like '%, %' and full_name not rlike '[SJ]r\.?$' and last_name = ''");
dbwrite("update governors set full_name = concat(first_name, ' ', last_name) where full_name like '%, %' and full_name not rlike '[SJ]r\.?$'");



