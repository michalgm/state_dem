<?php
include_once('../config.php');

$db = dbconnect();

#dbwrite("delete from legislators");
#dbwrite("delete from legislator_terms");
$full_update = 0;

foreach(array_keys($states) as $state) {
	foreach(array('false', 'true') as $active) {
		$url = "http://openstates.org/api/v1/legislators/?apikey=$sunlightKey&state=$state&active=$active";
		$json = file_get_contents($url);
		$legs = json_decode($json, true);
		print "\n$state (".($active=='true' ? 'current' : 'past')."): ".count(array_values($legs))."\n";
		$x = 0;
		foreach(array_values($legs) as $leg) { 
			$fields = array('leg_id', 'full_name', 'first_name', 'last_name', 'middle_name', 'suffixes', 'url', 'email', 'photo_url', 'active', 'state', 'chamber', 'district', 'party', 'transparencydata_id');

			//set fields to blank if they don't exist
			foreach($fields as $key) { 
				if (! isset($leg[$key])) { 
					$leg[$key] = "";
				} elseif (is_string($leg[$key])) {
					$leg[$key] = dbEscape($leg[$key]);
				}
			}

			$candidate_exists = fetchRow("select last_name,transparencydata_id from legislators where leg_id = '$leg[leg_id]' or (transparencydata_id = '$leg[transparencydata_id]' and '$leg[transparencydata_id]' != '')");

			if (! $candidate_exists && ! $full_update) { 
				if (isset($leg['transparencydata_id']) && $leg['transparencydata_id']) { 
					$ent_url = "http://transparencydata.com/api/1.0/entities/$leg[transparencydata_id].json?apikey=$sunlightKey";
					$ent_json = file_get_contents($ent_url);
					if (! $ent_json) { 
						print "unable to fetch $leg[full_name] ($leg[transparencydata_id])\n";
					} else {
						$entity = json_decode($ent_json, true);
						foreach($entity['external_ids'] as $ext_id) { 
							if ($ext_id['namespace'] == 'urn:nimsp:recipient') { 
								$leg['nimsp_candidate_id'] = $ext_id['id'];
								break;
							}
						}
						if (isset($leg['nimsp_candidate_id'])) { 
							update_terms($leg, $entity['metadata']);
						}
						if(isset($entity['metadata']['photo_url'])) { 
							if (! $leg['photo_url']) { 
								$leg['photo_url'] = $entity['metadata']['photo_url'];
							} else { 
								$leg['photo_url2'] = $entity['metadata']['photo_url'];
							}
						}
					}
				}
				
				//We don't want to overwrite these in the db with blanks if they don't exist
				foreach(array('votesmart_id', 'nimsp_candidate_id') as $id) { 
					if (isset($leg[$id]) && $leg[$id]) { $fields[] = $id; }
				}
			} else { 
				$leg['transparencydata_id'] = $candidate_exists[1];
			}
			$values = [];
			foreach ($fields as $field) { 
				$values[] = $leg[$field];
			}
			print "\r\t".++$x;
			dbwrite("insert ignore into legislators (".implode(",", $fields).") values ('".implode("','", $values)."')");
		}
	}
}

$missing_ids = fetchCol("select recipient_ext_id from contributions a left join legislators b on recipient_ext_id = nimsp_candidate_id   where b.nimsp_candidate_id is null  and seat in ('state:lower', 'state:upper') and recipient_state in (".arrayToInString($states, 1).") and cycle >= $min_cycle and seat_result = 'W' group by recipient_ext_id");
print "\nUpdate ".count($missing_ids)." missing candidates from transparency data\n";
foreach ($missing_ids as $id) { 
	$entity = fetch_transparency_data($id);
	if ($entity) { 
		$leg = array('transparencydata_id'=>$entity['id'], 'nimsp_candidate_id'=>$id, 'leg_id'=>'', 'photo_url'=>'');
		if (isset($entity['metadata']['photo_url'])) { 
			$leg['photo_url'] = $entity['metadata']['photo_url'];
		}
		dbwrite("insert ignore into legislators (full_name, state, transparencydata_id, nimsp_candidate_id, photo_url) values ('$entity[name]', '".$entity['metadata']['state']."', '$leg[transparencydata_id]', '$id', '$leg[photo_url]')");
		update_terms($leg, $entity['metadata']);
		print "1";
	} else { 
		print "0";
	}
}
print "\nCleaning up names\n";
dbwrite("update legislators set full_name = replace(full_name, 'Representative ', '')  where full_name like 'representative %'");
dbwrite("update legislators set full_name = replace(full_name, 'Senator ', '')  where full_name like 'Senator %'");
dbwrite("update legislators set last_name = name_case(substring_index(full_name, ', ', 1)), first_name = name_case(substring_index(full_name, ', ', -1)) where full_name like '%, %' and full_name not rlike '[SJ]r\.?$' and last_name = ''");
dbwrite("update legislators set full_name = concat(first_name, ' ', last_name) where full_name like '%, %' and full_name not rlike '[SJ]r\.?$'");

$missing_ids = dbLookupArray("Select concat(leg_id, transparencydata_id) as id, leg_id, transparencydata_id, last_name, first_name, state, if(party = 'Democratic', 'Democrat', party) as party, if(district rlike '^[0-9]+$', lpad(district, 3, '0'), district) as district, chamber from legislators where nimsp_candidate_id = 0 order by state desc");
print "Trying to fetch ".count($missing_ids)." missing NIMSP ids from NIMSP - API #3!\n";
foreach ($missing_ids as $leg) {
	$url = "http://api.followthemoney.org/candidates.list.php?key=3420aea61e2f4cb1a8a925a0c738eaf0&candidate_name=$leg[last_name]&state=$leg[state]";
	if ($leg['party']) { $url .= "&party=$leg[party]"; }
	if ($leg['district']) { $url .= "&district=$leg[district]"; }
	$xml = file_get_contents($url);
	$data = xml2array($xml);
	print "\n$leg[id]:";
	if ($data && $data[1]) { 
		$results = $data[1];
		$ids = [];
		foreach($results as $res) {
			if (! preg_match("/^$leg[last_name], /i", $res['candidate_name'])) { continue; }
			$ids[] = $res['unique_candidate_id'];
		}
		$ids = array_unique($ids);
		if (count($ids) == 1) { 
			$exists = fetchValue("select leg_id from legislators where nimsp_candidate_id = '$ids[0]'");
			if ($exists) { 
				dbwrite("delete from legislators where nimsp_candidate_id = 0 and leg_id = '$leg[leg_id]'");
				print "Dupe! $exists";
			} else { 	
				dbwrite("update legislators set nimsp_candidate_id = '$ids[0]' where leg_id = '$leg[leg_id]' and transparencydata_id = '$leg[transparencydata_id]'");
				print "Yay! $ids[0]";
			}
		} else {
			print "Boo: (".implode(", ", $ids).")";
		}
	} else { print "Not found"; }
}

$legislators = dbLookupArray("select nimsp_candidate_id, a.state, leg_id from legislators a left join legislator_terms b on imsp_candidate_id = nimsp_candidate_id where imsp_candidate_id is null and nimsp_candidate_id != 0");
print "Fetching missing terms for ".count($legislators)." candidates\n";
foreach(array_keys($legislators) as $id) {
	$data = fetch_transparency_data($id);
	if ($data) {
		$leg=$legislators[$id];
		dbwrite("update legislators set transparencydata_id = '$data[id]' where nimsp_candidate_id = '$id'");
		update_terms($leg, $data['metadata']);
		print "+";
	} else { print "?"; }
}

print "Inserting missing terms\n";
dbwrite("insert into legislator_terms select nimsp_candidate_id, leg_id, recipient_state, cycle, a.district, substring(party, 1, 1), seat  from contributions a join legislators b on recipient_ext_id = nimsp_candidate_id  where recipient_ext_id in (select nimsp_candidate_id from legislators l left join legislator_terms on imsp_candidate_id = nimsp_candidate_id where imsp_candidate_id is null and nimsp_candidate_id != 0)  and seat in ('state:upper', 'state:lower') group by recipient_ext_id, cycle");

print "Running Checks\n";
$missing_nimsp = dbLookupArray("select  concat(leg_id, transparencydata_id) as id from legislators where nimsp_candidate_id = 0");
print count(array_keys($missing_ids)) ." candidates missing nimsp ids\n";
$dupe_nimsp = dbLookupArray("select concat(leg_id, transparencydata_id) as id, full_name, leg_id, transparencydata_id, nimsp_candidate_id, state, active, chamber from legislators where nimsp_candidate_id in  (select * from (select nimsp_candidate_id from legislators group by nimsp_candidate_id having count(*) > 1)a) and nimsp_candidate_id != 0 order by nimsp_candidate_id");
print count(array_keys($dupe_nimsp)) ." candidates sharing nimsp ids\n";
$dupe_os = dbLookupArray("select concat(leg_id, transparencydata_id) as id, full_name, leg_id, transparencydata_id, nimsp_candidate_id, state, active, chamber from legislators where leg_id in  (select * from (select leg_id from legislators group by leg_id having count(*) > 1)a) and leg_id != '' order by leg_id");
print count(array_keys($dupe_os)) ." candidates sharing open states ids\n";
$dupe_trans = dbLookupArray("select full_name, leg_id, transparencydata_id, nimsp_candidate_id, state, active, chamber from legislators where transparencydata_id in  (select * from (select transparencydata_id from legislators group by transparencydata_id having count(*) > 1)a) and transparencydata_id != '' order by transparencydata_id");
print count(array_keys($dupe_trans)) ." candidates sharing transparency data ids\n";

/*
print "Filling in missing NIMSP ids\n";
//join on state, district, full name
dbwrite("update legislators a join (select leg_id, unique_candidate_id from legislators a join imsp_candidates b on a.state = b.state and a.district =  TRIM(LEADING '0' FROM b.district) and replace(concat(replace(last_name, \"'\", ''), ', ', first_name), '.', '') = candidate_name where nimsp_candidate_id = 0 and office rlike '^[rsg]'  and year >= 1999 and candidate_status not like 'lost%' group by leg_id having count(distinct unique_candidate_id) = 1) b using(leg_id) set a.nimsp_candidate_id = unique_candidate_id");
//join on state, district, last name
dbwrite("update legislators a join (select leg_id, unique_candidate_id from legislators a join imsp_candidates b on a.state = b.state and a.district =  TRIM(LEADING '0' FROM b.district) and replace(last_name, \"'\", '') = substring_index(candidate_name, ' ', 1) where nimsp_candidate_id = 0 and office rlike '^[rsg]'  and year >= 1999 and candidate_status not like 'lost%' group by leg_id having count(distinct unique_candidate_id) = 1) b using(leg_id) set a.nimsp_candidate_id = unique_candidate_id");
//join on state, last name
dbwrite("update legislators a join (select leg_id, unique_candidate_id from legislators a join imsp_candidates b on a.state = b.state and replace(last_name, \"'\", '') = substring_index(candidate_name, ',', 1) where nimsp_candidate_id = 0 and office rlike '^[rsg]'  and year >= 1999 and candidate_status not like 'lost%' group by leg_id having count(distinct unique_candidate_id) = 1) b using(leg_id) set a.nimsp_candidate_id = unique_candidate_id");
//join on state, full name
dbwrite("update legislators a join (select leg_id, unique_candidate_id from legislators a join imsp_candidates b on a.state = b.state and replace(concat(replace(last_name, \"'\", ''), ', ', first_name), '.', '') = candidate_name where nimsp_candidate_id = 0 and office rlike '^[rsg]'  and year >= 1999 and candidate_status not like 'lost%' group by leg_id having count(distinct unique_candidate_id) = 1) b using(leg_id) set a.nimsp_candidate_id = unique_candidate_id");
dbwrite("update legislators a join (select leg_id, unique_candidate_id from legislators a join imsp_candidates b on a.state = b.state and replace(last_name, \"'\", '') = substring_index(candidate_name, ' ', 1) where nimsp_candidate_id = 0 and office rlike '^[rsg]'  and year >= 1999 and candidate_status not like 'lost%' group by leg_id having count(distinct unique_candidate_id) = 1) b using(leg_id) set a.nimsp_candidate_id = unique_candidate_id");

dbwrite("update legislators a join (select leg_id, unique_candidate_id from legislators a join imsp_candidates b on a.state = b.state and a.district =  TRIM(LEADING '0' FROM b.district) and substring_index(replace(last_name, \"'\", ''), '-', 1) = substring_index(candidate_name, ',', 1) where nimsp_candidate_id = 0 and office rlike '^[rsg]'  and year >= 1999 and candidate_status not like 'lost%' group by leg_id having count(distinct unique_candidate_id) = 1) b using(leg_id) set a.nimsp_candidate_id = unique_candidate_id");

dbwrite("update legislators a join (select leg_id, unique_candidate_id from legislators a join imsp_candidates b on a.state = b.state and a.district =  TRIM(LEADING '0' FROM b.district) and replace(concat(replace(last_name, \"'\", ''), ', ', first_name), '.', '') = substring_index(candidate_name, ' ' , 2) where nimsp_candidate_id = 0 and office rlike '^[rsg]'  and year >= 1999 group by leg_id having count(distinct unique_candidate_id) = 1) b using(leg_id) set a.nimsp_candidate_id = unique_candidate_id");

dbwrite("update legislators a join  votesmart_id_matrix i using(nimsp_candidate_id)set votesmart_id = votesmart_candidate_id where votesmart_id is null ");
 */

function update_terms($leg, $metadata) { 
		foreach(array_keys($metadata) as $key) {
			if(preg_match("/^\d+$/", $key)) { 
				$role = $metadata[$key];
				dbwrite("insert ignore into legislator_terms values('$leg[nimsp_candidate_id]', '$leg[leg_id]', '$role[state]', '$key', '$role[district]', '$role[party]', '$role[seat]')");
			}
		}
}

function fetch_transparency_data($id) {
	global $sunlightKey;
	$id_json = json_decode(file_get_contents("http://transparencydata.com/api/1.0/entities/id_lookup.json?apikey=$sunlightKey&namespace=urn:nimsp:recipient&id=$id"), true);
	if ($id_json) { 
		$transparencydata_id = $id_json[0]['id'];
		$entity =  json_decode(file_get_contents("http://transparencydata.com/api/1.0/entities/$transparencydata_id.json?apikey=$sunlightKey"), true);
		return $entity;
	}
}
