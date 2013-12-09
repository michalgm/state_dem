<?php
include_once('../config.php');

$db = dbconnect();
$state_details = dbLookupArray("select * from states");
$fields = array('leg_id', 'full_name', 'first_name', 'last_name', 'middle_name', 'suffixes', 'url', 'email', 'photo_url', 'active', 'state', 'chamber', 'district', 'party', 'transparencydata_id', 'image_source', 'votesmart_id', 'nimsp_candidate_id', 'os_id');
$not_gov_ids = arrayValuesToInString(array(142074, 145282, 105213, 3157, 147252, 147253, 147301));

$terms_update = 0;
$full_update = 0;
if(isset($argv[1])) { 
	$terms_update = 1;
	if ($argv[1] == 'all') { 
		$full_update = 1;
		print "resetting legislators\n";
		dbwrite("delete from legislators");
	}
	print "resetting terms\n";
	dbwrite("delete from legislator_terms where seat != 'governor'");
}

print "Loading new/updated legislators\n";
//Update entries that had missing nimsp ids manually corrected.
dbwrite('insert into ocd_mistakes select leg_id, nimsp_candidate_id, concat("manually entered nimsp for ", full_name) from legislators_missing_nimsp where nimsp_candidate_id != 0 on duplicate key update nimsp_id = nimsp_candidate_id');
dbwrite("delete from legislators_missing_nimsp where nimsp_candidate_id != 0");
	
//Insert new entries from contribution records
dbwrite("insert into legislators (full_name, state, nimsp_candidate_id, chamber, district) select recipient_name, recipient_state, recipient_ext_id, seat, district from contributions where seat_result = 'W' and seat in ('state:upper', 'state:lower') and recipient_type = 'P' and cycle >= $min_cycle and recipient_ext_id not in (select distinct nimsp_candidate_id from legislators) group by recipient_ext_id");

foreach($states as $state) {
//foreach(array('OH') as $state) {
	//continue; //FIXME
	print "Updating $state legislators\n";
	$dir = "data/opencivicdata/dump/dump/ocd-jurisdiction/country:us/state:".strtolower($state)."/legislature/ocd-person/";
	$legfiles = scandir($dir);
	$x = 0;
	foreach($legfiles as $legfile) { 
		if ($legfile[0] == '.') { continue; }
		$x = showProgress($x, count($legfiles));
		$legdata = json_decode(file_get_contents($dir.$legfile), true);
		$leg = array();
		//if ($legdata['_id'] != 'ocd-person/a1286f52-30fa-11e3-b9eb-1231391cd4ec') { continue; }
		foreach($fields as $key) { $leg[$key] = ''; }

		$leg['leg_id'] = preg_replace("/ocd-person\//", '', $legdata['_id']);
		$leg['full_name'] = $legdata['name'];
		$leg['last_name'] = $legdata['extras']['last_name'];
		$leg['first_name'] = $legdata['extras']['first_name'];
		$leg['middle_name'] = isset($legdata['extras']['middle_name']) ? $legdata['extras']['middle_name'] : '';
		$leg['chamber'] = isset($legdata['chamber']) ? 'state:'.$legdata['chamber'] : '';
		$leg['district'] = isset($legdata['extras']['+district']) ? $legdata['extras']['+district'] : '';
		$leg['party'] = isset($legdata['extras']['+party']) ? $legdata['extras']['+party'] : '';
		$leg['state'] = $state;

		if ($legdata['image']) { 
			$leg['photo_url'] = $legdata['image'];
			$leg['image_source'] = 'ocd';
		}
		foreach($legdata['identifiers'] as $id) { 
			switch($id['scheme']) {
				case 'openstates':
					$leg['os_id'] = $id['identifier'];
					break;
				case 'transparencydata-id':
					$leg['transparencydata_id'] = $id['identifier'];
					break;
				case 'votesmart-id':
					$leg['votesmart_id'] = $id['identifier'];
					break;
			}
		}	
		if (isset($legdata['extras']['nimsp_id'])) { 
			$leg['nimsp_candidate_id'] = $legdata['extras']['nimsp_id'];
		} 
		//Sometimes the OCD data is wrong!
		$nimsp_override = fetchValue("select nimsp_id from ocd_mistakes where leg_id = '$leg[leg_id]' and nimsp_id is not null and nimsp_id != ''");
		if ($nimsp_override) { $leg['nimsp_candidate_id'] = $nimsp_override; }

		$exists_query = dbLookupArray("select nimsp_candidate_id, transparencydata_id from legislators where leg_id = '$leg[leg_id]'");
		if ($exists_query) { 
			$exists = array_values($exists_query)[0];
			if ($exists['nimsp_candidate_id']) { $leg['nimsp_candidate_id'] = $exists['nimsp_candidate_id']; }
			if ($exists['transparencydata_id']) { $leg['transparencydata_id'] = $exists['transparencydata_id']; }
		}

		$nimsp_lookup = 0;
		if (! $leg['nimsp_candidate_id']) { 
			print "looking for missing nimsp_candidate_id - $leg[full_name]: ";
			$nimsp_lookup = 1;
			if ($leg['transparencydata_id']) { 
				$entity =  json_decode(file_get_contents("http://transparencydata.com/api/1.0/entities/$leg[transparencydata_id].json?apikey=$sunlightKey"), true);
				foreach($entity['external_ids'] as $ext_id) { 
					if ($ext_id['namespace'] == 'urn:nimsp:recipient') { 
						$leg['nimsp_candidate_id'] = $ext_id['id'];
						break;
					//	dbwrite("delete from legislators where transparencydata_id = '$leg[transparencydata_id]'");
					}
				}
			}
		}
		if (! $leg['nimsp_candidate_id']) { 
			$name = iconv('UTF-8','ASCII//TRANSLIT', $leg['last_name']);
			$url = "http://api.followthemoney.org/candidates.list.php?key=3420aea61e2f4cb1a8a925a0c738eaf0&candidate_name=$name&state=$leg[state]&candidate_status=WON";
			if ($leg['chamber']) { 
				$term = $state_details[$state][str_replace('state:', '', $leg['chamber'])."_name"];
				$url .= "&chamber=$term";
			}
			$data = xml2array(file_get_contents($url));
			$ids = [];
			if ($data && $data[1]) { 
				$results = $data[1];
				foreach($results as $res) {
					if ($res['year'] < $min_cycle || $res['year'] > $max_cycle) { continue; }
					if (! preg_match("/^".$name."[ ,] /i", $res['candidate_name'])) { continue; }
					$ids[] = $res['unique_candidate_id'];
				}
			}
			if (count(array_unique($ids)) == 1) { 
				$leg['nimsp_candidate_id'] = $ids[0];
			}
		}
		if ($leg['nimsp_candidate_id'] && ! $nimsp_override) { 
			$dupe_leg_id = fetchRow("select leg_id, full_name, transparencydata_id from legislators where nimsp_candidate_id = $leg[nimsp_candidate_id] and leg_id != '$leg[leg_id]' and leg_id != ''");
			if ($dupe_leg_id[0]) { 
				$note = "Two leg_ids for ($leg[nimsp_candidate_id]) $leg[full_name] - $leg[leg_id] $leg[transparencydata_id] vs $dupe_leg_id[1] - $dupe_leg_id[0] $dupe_leg_id[2]";
				print "\n$note\n"; 
				dbwrite("insert ignore into ocd_mistakes values('$leg[leg_id]', $leg[nimsp_candidate_id], '$note') on duplicate key update nimsp_id = $leg[nimsp_candidate_id], notes='$note'");
				dbwrite("insert ignore into ocd_mistakes values('$dupe_leg_id[0]', $leg[nimsp_candidate_id], '$note') on duplicate key update nimsp_id = $leg[nimsp_candidate_id], notes='$note'");
			}
		}

		if ($nimsp_lookup) { 
			if (! $leg['nimsp_candidate_id']) { 
				print " nope!\n";
			} else {
				print "yes!\n";
				if (!$full_update) { 
					dbwrite("insert ignore into ocd_mistakes values('$leg[leg_id]', null, '$leg[full_name] | $leg[state] | $leg[transparencydata_id] | $leg[nimsp_candidate_id]')");
				}
			}
		}

		$table = $leg['nimsp_candidate_id'] ? 'legislators' : 'legislators_missing_nimsp';

		$values_string = arrayToUpdateString($leg, $fields);
		dbwrite("insert into $table set $values_string on duplicate key update $values_string");
		//dbwrite("insert into legislators set $values_string");
	}
}

print "Updating Governors...\n";
print "insert ignore into legislators (nimsp_candidate_id, full_name, state, chamber) select distinct recipient_ext_id, recipient_name, recipient_state, 'state:governor' from contributions where  seat = 'state:governor' and recipient_type = 'P' and cycle >= ".($max_cycle -2)." and recipient_state in (".arrayValuesToInString($states).") and recipient_ext_id not in ($not_gov_ids) and seat_result = 'W'";
dbwrite("insert ignore into legislators (nimsp_candidate_id, full_name, state, chamber) select distinct recipient_ext_id, recipient_name, recipient_state, 'state:governor' from contributions where  seat = 'state:governor' and recipient_type = 'P' and cycle >= ".($max_cycle -2)." and recipient_state in (".arrayValuesToInString($states).") and recipient_ext_id not in ($not_gov_ids) and seat_result = 'W'");

print "Updating Terms...\n";
$terms_query = "select nimsp_candidate_id, l.* from legislators l  where nimsp_candidate_id not in (select distinct imsp_candidate_id from legislator_terms)";
$terms_legs = dbLookupArray($terms_query);
$x = 0;
foreach($terms_legs as $leg) {		
	//continue; //FIXME
	if ($leg['leg_id']) { 
		$entity = json_decode(file_get_contents("data/opencivicdata/dump/dump/ocd-jurisdiction/country:us/state:".strtolower($leg['state'])."/legislature/ocd-person/$leg[leg_id]"), true);
		if ($entity['memberships']) { 
			$terms = array();
			foreach($entity['memberships'] as $m) { 
				if(isset($m['extras']['term'])) { 
					if (! isset($m['post_id']) || ! $m['post_id']) { continue; }
					$role = array();
					if (isset($m['end_date']) && $m['end_date']) {
						$year = $m['end_date'];
					} else { 
						$year = substr($m['extras']['term'], -4);
					}
					$role['seat'] = 'state:'.$m['chamber'];
					$role['district'] = $m['post_id'];
					$terms['year'] = $role;
				}
			}
			update_terms($leg, $terms);
		}
	}

	//$leg['transparencydata_id'] = '';
	$tid = fetch_transparency_data_id($leg); 
	if ($tid) { 
		$terms = array();
		$ent_url = "http://transparencydata.com/api/1.0/entities/$tid.json?apikey=$sunlightKey";
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
			if(isset($entity['metadata']['photo_url']) && $entity['metadata']['photo_url']) { 
				if (! $leg['photo_url']) { 
					$leg['photo_url'] = $entity['metadata']['photo_url'];
					if ($leg['photo_url']) { $leg['image_source'] = 'td'; }
				} else { 
					$leg['photo_url2'] = $entity['metadata']['photo_url'];
				}
				dbwrite("update legislators set photo_url = '$leg[photo_url]', photo_url2 = '$leg[photo_url2]' where nimsp_candidate_id = $leg[nimsp_candidate_id]");
			}
		}
	}

	if ($leg['os_id']) { 
		$details_url = "http://openstates.org/api/v1/legislators/$leg[os_id]/?apikey=$sunlightKey";
		$details_json = file_get_contents($details_url);
		if (! $details_json) { 
			print "unable to fetch details for $leg[full_name] ($leg[leg_id])\n";
		} else {
			$metadata = [];
			$details = json_decode($details_json,true);
			if (isset($details['old_roles'])) { 
				foreach($details['old_roles'] as $roles) { 
					foreach($roles as $role) { 
						if(isset($role['district'])) {
							$addrole = $role;
							$addrole['district'] = "$role[state]-$role[district]";
							$addrole['party'] = substr($role['party'], 0, 1);
							$addrole['seat'] = "state:$role[chamber]";
							$year = substr($role['term'], -4);
							$metadata[$year] = $addrole;
						}
					}
				}
			}
			foreach($details['roles'] as &$role) { 
				if(isset($role['district'])) {
					$role['district'] = "$role[state]-$role[district]";
					$role['party'] = substr($role['party'], 0, 1);
					$role['seat'] = "state:$role[chamber]";
					$year = substr($role['term'], -4);
					$metadata[$year] = $role;
				}
			}				
			if (! isset($leg['nimsp_candidate_id'])) { 
				$leg['nimsp_candidate_id'] = '';
			}
			update_terms($leg, $metadata);
		}
			//
//
		
	}
	$x = showProgress($x, count($terms_legs));
}
print "\n";

print "\nCleaning up names\n";
//Remove & and anything after
dbwrite("update legislators set full_name = substring_index(full_name, ' &', 1) where full_name like '%&%'"); 
//remove stuff in ()
dbwrite("update legislators set full_name = replace(full_name, SUBSTRING(full_name, LOCATE('(',full_name), LENGTH(full_name) - LOCATE(')', REVERSE(full_name)) - LOCATE('(', full_name) + 2), '') where full_name like '%(%)%'");
//Remoev Representative
dbwrite("update legislators set full_name = replace(full_name, 'Representative ', '')  where full_name like 'representative %'");
//Remoev Senator
dbwrite("update legislators set full_name = replace(full_name, 'Senator ', '')  where full_name like 'Senator %'");
//Remove \
dbwrite("update legislators set full_name = replace(full_name, '\\\', '')  where full_name like '%\\\%'");
//Split out first and last names where full name split by comma
dbwrite("update legislators set last_name = name_case(substring_index(full_name, ', ', 1)), first_name = name_case(substring_index(full_name, ', ', -1)) where full_name like '%, %' and full_name not rlike '[SJ]r\.?$' and (last_name is null or last_name = '')");
//Split out first and last names where full name not split by comma
dbwrite("update legislators set last_name = name_case(substring_index(full_name, ' ', -1)), first_name = name_case(replace(full_name, concat(' ', substring_index(full_name, ' ', -1)), '')) where full_name not like '%, %' and full_name not rlike '[SJ]r\.?$' and (last_name is null or last_name = '')");
//Replace first name with nickname
dbwrite("update legislators set first_name = replace(substring_index(first_name, '\"', -2),'\"', '') where first_name like '%\"%'");
//Set full name to first last
dbwrite("update legislators set full_name = concat(first_name, ' ', last_name) where full_name like '%, %' and full_name not rlike '[SJ]r\.?$'");

print "Inserting missing terms\n";
dbwrite("insert ignore into legislator_terms select nimsp_candidate_id, leg_id, recipient_state, cycle, a.district, substring(party, 1, 1), seat  from contributions a join legislators b on recipient_ext_id = nimsp_candidate_id  where recipient_ext_id in (select nimsp_candidate_id from legislators l left join legislator_terms on imsp_candidate_id = nimsp_candidate_id where imsp_candidate_id is null and nimsp_candidate_id != 0)  and seat in ('state:upper', 'state:lower') group by recipient_ext_id, cycle");

$missing_ids = fetchValue("select count(*) from legislators_missing_nimsp");
print "\rMissing $missing_ids nimsp ids - check legislators_missing_nimsp\n";

exit;

if (0) { 
	if ($entity['memberships']) { 
		foreach($entity['memberships'] as $m) { 
			if(isset($m['extras']['term'])) { 
				$year = substr($m['extras']['term'], -4);
				$chamber = $m['chamber'];
			}
		}
	}
}

if (0){ 		
	foreach(array('true', 'false') as $active) {
		$url = "http://openstates.org/api/v1/legislators/?apikey=$sunlightKey&state=$state&active=$active";
		$json = file_get_contents($url);
		$legs = json_decode($json, true);
		print "\n$state (".($active=='true' ? 'current' : 'past')."): ".count(array_values($legs))."\n";
		$x = 0;
		foreach(array_values($legs) as $leg) { 
			$fields = array('leg_id', 'full_name', 'first_name', 'last_name', 'middle_name', 'suffixes', 'url', 'email', 'photo_url', 'active', 'state', 'chamber', 'district', 'party', 'transparencydata_id', 'image_source');

			//set fields to blank if they don't exist
			foreach($fields as $key) { 
				if (! isset($leg[$key])) { 
					$leg[$key] = "";
				} elseif (is_string($leg[$key])) {
					$leg[$key] = dbEscape($leg[$key]);
				}
			}
			if ($leg['active'] == 0) { $leg['photo_url'] = ""; }

			$candidate_exists = fetchRow("select last_name,transparencydata_id from legislators where leg_id = '$leg[leg_id]' or (transparencydata_id = '$leg[transparencydata_id]' and '$leg[transparencydata_id]' != '')");

			if ($leg['photo_url']) { $leg['image_source'] = 'oc'; }


			if (! $candidate_exists || $full_update) { 
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
						if(isset($entity['metadata']['photo_url']) && $entity['metadata']['photo_url']) { 
							if (! $leg['photo_url']) { 
								$leg['photo_url'] = $entity['metadata']['photo_url'];
								if ($leg['photo_url']) { $leg['image_source'] = 'td'; }
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
			if ($active == 'true' || $full_update) { 
				$details_url = "http://openstates.org/api/v1/legislators/$leg[leg_id]/?apikey=$sunlightKey";
				$details_json = file_get_contents($details_url);
				if (! $details_json) { 
					print "unable to fetch details for $leg[full_name] ($leg[leg_id])\n";
				} else {
					$metadata = [];
					$details = json_decode($details_json,true);
					if (isset($details['old_roles'])) { 
						foreach($details['old_roles'] as $roles) { 
							foreach($roles as $role) { 
								if(isset($role['district'])) {
									$addrole = $role;
									$addrole['district'] = "$role[state]-$role[district]";
									$addrole['party'] = substr($role['party'], 0, 1);
									$addrole['seat'] = "state:$role[chamber]";
									$year = substr($role['term'], -4);
									$metadata[$year] = $addrole;
								}
							}
						}
					}
					foreach($details['roles'] as &$role) { 
						if(isset($role['district'])) {
							$role['district'] = "$role[state]-$role[district]";
							$role['party'] = substr($role['party'], 0, 1);
							$role['seat'] = "state:$role[chamber]";
							$year = substr($role['term'], -4);
							$metadata[$year] = $role;
						}
					}				
					if (! isset($leg['nimsp_candidate_id'])) { 
						$leg['nimsp_candidate_id'] = '';
					}
					update_terms($leg, $metadata);
				}
			}
			$values_string = arrayToUpdateString($leg, $fields);
			print "\r\t".++$x;

			dbwrite("insert into legislators set $values_string on duplicate key update $values_string");
		}
	}
}

dbwrite("update legislators a join (select * from (select a.full_name, b.leg_id, b.nimsp_candidate_id from legislators a join legislators b USING (full_name , state) where b.nimsp_candidate_id = 0 and a.nimsp_candidate_id != 0) b )b USING (leg_id) set a.nimsp_candidate_id = b.nimsp_candidate_id");

$missing_ids = fetchCol("select recipient_ext_id from contributions a left join legislators b on recipient_ext_id = nimsp_candidate_id   where b.nimsp_candidate_id is null  and seat in ('state:lower', 'state:upper') and recipient_state in (".arrayToInString($states, 1).") and cycle >= $min_cycle and seat_result = 'W' group by recipient_ext_id");
print "\nUpdate ".count($missing_ids)." missing candidates from transparency data\n";
foreach ($missing_ids as $id) { 
	$entity = fetch_transparency_data($id);
	if ($entity) { 
		$leg = array('transparencydata_id'=>$entity['id'], 'nimsp_candidate_id'=>$id, 'leg_id'=>'', 'photo_url'=>'', 'image_source'=>'', 'full_name'=>$entity['name'], 'state'=>$entity['metadata']['state']);
		if (isset($entity['metadata']['photo_url'])) { 
			$leg['photo_url'] = $entity['metadata']['photo_url'];
			if ($leg['photo_url']) { $leg['image_source'] = 'td'; }
		}
		$values_string = arrayToUpdateString($leg);
		dbwrite("insert into legislators set $values_string on duplicate key update $values_string");
		update_terms($leg, $entity['metadata']);
		print "1";
	} else { 
		print "0";
	}
}
print "\nCleaning up names\n";
dbwrite("update legislators set full_name = replace(full_name, 'Representative ', '')  where full_name like 'representative %'");
dbwrite("update legislators set full_name = replace(full_name, 'Senator ', '')  where full_name like 'Senator %'");
dbwrite("update legislators set full_name = replace(full_name, '\\\', '')  where full_name like '%\\\%'");
dbwrite("update legislators set full_name = replace(full_name, '\"', \"'\"), first_name = replace(first_name, '\"', \"'\") where full_name like '%\"%'");
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
			if (isset($role['seat_result']) && $role['seat_result'] == 'L') { continue; }
			if ($role['seat'] == 'state:upper' || $role['seat'] == 'state:lower' || $role['seat'] == 'state:governor') {
				$data = ['imsp_candidate_id'=>$leg['nimsp_candidate_id'], 'os_id'=>$leg['os_id'], 'state'=>strtoupper($role['state']), 'term'=>$key, 'district'=>strtoupper($role['district']),'party'=>$role['party'],'seat'=>$role['seat']];
				$values_string = arrayToUpdateString($data);
				dbwrite("insert into legislator_terms set $values_string on duplicate key update $values_string");
			}
		}
	}
}

function fetch_transparency_data_id($leg) {
	global $sunlightKey;
	$tid = '';
	if ($leg['transparencydata_id']) { 
		$tid = $leg['transparencydata_id'];
	} else {
		$id_json = json_decode(file_get_contents("http://transparencydata.com/api/1.0/entities/id_lookup.json?apikey=$sunlightKey&namespace=urn:nimsp:recipient&id=$leg[nimsp_candidate_id]"), true);
		if ($id_json) { 
			$tid = $id_json[0]['id']; 
			dbwrite("update legislators set transparencydata_id ='$tid' where nimsp_candidate_id = $leg[nimsp_candidate_id]");
		}
	}
	return $tid;
}

function showProgress($count, $total) { 
	global $hide_progress;
	if (! $hide_progress) { 
		$percent = ((($count+1)/$total)*100); 
		printf("\t%0.2f%% of %s \r", $percent, $total);	
	}
	return ++$count;
}

