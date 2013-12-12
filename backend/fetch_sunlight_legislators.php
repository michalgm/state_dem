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

//This is dumb, but transparency data is broken
dbwrite("update contributions set recipient_ext_id = 9358 where recipient_ext_id = 9368 and recipient_state = 'OH'");
dbwrite("update contributions set recipient_ext_id = 'fake_1' where recipient_ext_id = 99440 and recipient_state = 'OH' and district = 'OH-41'");

print "Loading new/updated legislators\n";
//Update entries that had missing nimsp ids manually corrected.
dbwrite('insert into ocd_mistakes select leg_id, nimsp_candidate_id, concat("manually entered nimsp for ", full_name) from legislators_missing_nimsp where nimsp_candidate_id != 0 on duplicate key update nimsp_id = nimsp_candidate_id');
dbwrite("delete from legislators_missing_nimsp where nimsp_candidate_id != 0");
	
//Insert new entries from contribution records
dbwrite("insert into legislators (full_name, state, nimsp_candidate_id, chamber, district) select recipient_name, recipient_state, recipient_ext_id, seat, district from contributions where seat_result = 'W' and seat in ('state:upper', 'state:lower') and recipient_type = 'P' and cycle >= $min_cycle and recipient_ext_id not in (select distinct nimsp_candidate_id from legislators) group by recipient_ext_id");

$statii = array();
foreach($states as $state) {
	print "Updating $state legislators\n";
	print "\tFetching NIMSP data: ";
	for ($cycle = $min_cycle; $cycle <= $max_cycle; $cycle+=2) { 
		print "$cycle ";
		foreach(array('upper', 'lower') as $chamber) {
			$office = $state_details[$state][str_replace('state:', '', $chamber)."_name"];
			$page=0;
			$next_page = 1;
			while ($next_page) { 
				$url = "http://api.followthemoney.org/candidates.list.php?key=3420aea61e2f4cb1a8a925a0c738eaf0&state=$state&office=$office&year=$cycle&page=$page";
				$data = xml2array(file_get_contents($url));
				if ($data && $data[0]) { 
					if ($data[0]['next_page'] != 'yes') { $next_page = 0; }
					foreach($data[1] as $res) {
						$statii[$res['candidate_status']] = 1;
						if (! preg_match("/^WON/i", $res['candidate_status']) && $res['candidate_status'] != 'Not Up For Election') { continue; }
						if ($res['year'] < $min_cycle || $res['year'] > $max_cycle) { continue; }
						if ($state == 'OH' && $res['unique_candidate_id'] == 99440 && $res['district'] == '041') { 
							$res['unique_candidate_id'] = 'fake_1';
						}	
						$leg = array(
							'nimsp_candidate_id'=>$res['unique_candidate_id'],
							'full_name'=>$res['candidate_name'],
							'party'=>$res['party'],
							'chamber'=>"state:$chamber",
							'state'=>$state,
							'district' => "$state-".preg_replace("/^0*/", "", $res['district'])
						);
						foreach($fields as $key) { $leg[$key] = isset($leg[$key]) ? $leg[$key] : ''; }
						$term = array($cycle=>array(
							'district' => "$state-".preg_replace("/^0*/", "", $res['district']),
							'state'=>$state,
							'party'=>$res['party'],
							'seat'=>"state:$chamber"
						));
						$values_string = arrayToUpdateString($leg, $fields, 1);
						dbwrite("insert into legislators set $values_string on duplicate key update $values_string");
						update_terms($leg, $term);
					}
				}
				$page++;
			}
		}
	}
	print "\n";

	print "\tFetching OCD data: \n";
	$dir = "data/opencivicdata/dump/dump/ocd-jurisdiction/country:us/state:".strtolower($state)."/legislature/ocd-person/";
	$legfiles = scandir($dir);
	$x = 0;
	foreach($legfiles as $legfile) { 
		if ($legfile[0] == '.') { continue; }
		$x = showProgress($x, count($legfiles));
		$legdata = json_decode(file_get_contents($dir.$legfile), true);
		$leg = array();
		foreach($fields as $key) { $leg[$key] = ''; }

		$leg['leg_id'] = preg_replace("/ocd-person\//", '', $legdata['_id']);
		$leg['full_name'] = $legdata['name'];
		$leg['last_name'] = $legdata['extras']['last_name'];
		$leg['first_name'] = $legdata['extras']['first_name'];
		$leg['middle_name'] = isset($legdata['extras']['middle_name']) ? $legdata['extras']['middle_name'] : '';
		$chamber = isset($legdata['chamber']) ? 'state:'.$legdata['chamber'] : '';
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

		$exists_query = dbLookupArray("select nimsp_candidate_id, transparencydata_id, chamber, state, district, party from legislators where leg_id = '$leg[leg_id]'");
		if ($exists_query) { 
			$exists = array_values($exists_query)[0];
			if ($exists['nimsp_candidate_id']) { $leg['nimsp_candidate_id'] = $exists['nimsp_candidate_id']; }
			if ($exists['transparencydata_id']) { $leg['transparencydata_id'] = $exists['transparencydata_id']; }
			$leg['chamber'] = $exists['chamber'] ? $exists['chamber'] : $chamber;
			$leg['district'] = $exists['district'] ? $exists['district'] : (isset($legdata['extras']['+district']) ? $legdata['extras']['+district'] : '');
			$leg['party'] = $exists['party'] ? $exists['party'] : (isset($legdata['extras']['+party']) ? $legdata['extras']['+party'] : '');
		}

		$nimsp_lookup = 0;
		if (! $leg['nimsp_candidate_id']) { 
			print "looking for missing nimsp_candidate_id - $leg[full_name]: ";
			$nimsp_lookup = 1;
			$name = dbEscape(iconv('UTF-8','ASCII//TRANSLIT', $leg['last_name']));
			$id = fetchRow("select nimsp_candidate_id, count(*) from legislators where (full_name like '$name, %' or full_name like '% $name') and state='$state' and chamber='$chamber'");
		   	if ($id && $id[1] == 1) { $leg['nimsp_candidate_id'] = $id[0]; }
		}	
		if (! $leg['nimsp_candidate_id'] && $leg['transparencydata_id']) { 
			$entity =  json_decode(file_get_contents("http://transparencydata.com/api/1.0/entities/$leg[transparencydata_id].json?apikey=$sunlightKey"), true);
			foreach($entity['external_ids'] as $ext_id) { 
				if ($ext_id['namespace'] == 'urn:nimsp:recipient') { 
					$leg['nimsp_candidate_id'] = $ext_id['id'];
					break;
				//	dbwrite("delete from legislators where transparencydata_id = '$leg[transparencydata_id]'");
				}
			}
		}

		if (! $leg['nimsp_candidate_id']) { 
			$name = iconv('UTF-8','ASCII//TRANSLIT', $leg['last_name']);
			$url = "http://api.followthemoney.org/candidates.list.php?key=3420aea61e2f4cb1a8a925a0c738eaf0&candidate_name=$name&state=$leg[state]&candidate_status=WON";
			if ($leg['chamber']) { 
				$office = $state_details[$state][str_replace('state:', '', $leg['chamber'])."_name"];
				$url .= "&office=$office";
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

		if($leg['nimsp_candidate_id'] && ! $leg['transparencydata_id']) { 
			$leg['transparencydata_id'] = fetch_transparency_data_id($leg);
		}

		$table = $leg['nimsp_candidate_id'] ? 'legislators' : 'legislators_missing_nimsp';

		$values_string = arrayToUpdateString($leg, $fields, 1);
		dbwrite("insert into $table set $values_string on duplicate key update $values_string");
		//dbwrite("insert into legislators set $values_string");
	}
}
print "Updating Governors...\n";
dbwrite("insert ignore into legislators (nimsp_candidate_id, full_name, state, chamber) select distinct recipient_ext_id, recipient_name, recipient_state, 'state:governor' from contributions where  seat = 'state:governor' and recipient_type = 'P' and cycle >= ".($max_cycle -2)." and recipient_state in (".arrayValuesToInString($states).") and recipient_ext_id not in ($not_gov_ids) and seat_result = 'W'");

print "Updating Terms...\n";
$terms_query = "select nimsp_candidate_id, l.* from legislators l  where nimsp_candidate_id not in (select distinct imsp_candidate_id from legislator_terms)";
$terms_legs = dbLookupArray($terms_query);
$x = 0;
foreach($terms_legs as $leg) {		
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
print "\nTrying to fetch missing candidates/terms from NIMSP\n";
foreach($states as $state) {
	continue;
	print "\t$state: ";
	foreach(array('upper', 'lower') as $chamber) {
		print "$chamber ";
		$office = $state_details[$state][str_replace('state:', '', $chamber)."_name"];
		for ($cycle = $min_cycle; $cycle <= $max_cycle; $cycle+=2) { 
			print "$cycle ";
			$url = "http://api.followthemoney.org/candidates.list.php?key=3420aea61e2f4cb1a8a925a0c738eaf0&state=$state&candidate_status=WON&year=$cycle&office=$office";
			$data = xml2array(file_get_contents($url));
			$results = ($data && $data[1]) ? $data[1] : array();
			print "(".count($results).") ";
			foreach($results as $res) {
				$terms = array();
				$id = $res['unique_candidate_id'];
				$party = $res['party'][0];
				$district = $state."-".preg_replace("/^0*/", "", $res['district']);
				$term[$cycle] = array('district'=>$district, 'seat'=>"state:$chamber", 'party'=>$party, 'state'=>$state);
				$leg = array('os_id'=>'', 'nimsp_candidate_id'=>$id);
				update_terms($leg, $terms);
			}
		}
	}
	print "\n";
}

print "\nCleaning up names\n";
//Remove & and anything after
dbwrite("update legislators set full_name = substring_index(full_name, ' &', 1) where full_name like '%&%'"); 
//remove stuff in ()
dbwrite("update legislators set full_name = replace(full_name, SUBSTRING(full_name, LOCATE('(',full_name), LENGTH(full_name) - LOCATE(')', REVERSE(full_name)) - LOCATE('(', full_name) + 2), '') where full_name like '%(%)%'");
//Set suffixes
foreach (dbLookupArray("select nimsp_candidate_id, full_name, if(full_name rlike ' sr\.?$', 'Sr.', 'Jr.') as suffix from legislators where full_name rlike ' [js]r\.?$'") as $name) { 
	$name['full_name'] = preg_replace("/,? [js]r\.?$/i", "", $name['full_name']);
	dbwrite("update legislators set full_name = '".dbEscape($name['full_name'])."', suffixes = '$name[suffix]' where nimsp_candidate_id = '$name[nimsp_candidate_id]'");
}
//Remoev Representative
dbwrite("update legislators set full_name = replace(full_name, 'Representative ', '')  where full_name like 'representative %'");
//Remoev Senator
dbwrite("update legislators set full_name = replace(full_name, 'Senator ', '')  where full_name like 'Senator %'");
//Remove \
dbwrite("update legislators set full_name = replace(full_name, '\\\', '')  where full_name like '%\\\%'");
//Split out first and last names where full name split by comma
dbwrite("update legislators set last_name = name_case(substring_index(full_name, ', ', 1)), first_name = name_case(substring_index(full_name, ', ', -1)) where full_name like '%, %' and (last_name is null or last_name = '')");
//Split out first and last names where full name not split by comma
dbwrite("update legislators set last_name = name_case(substring_index(full_name, ' ', -1)), first_name = name_case(replace(full_name, concat(' ', substring_index(full_name, ' ', -1)), '')) where full_name not like '%, %' and (last_name is null or last_name = '')");
//Replace first name with nickname
dbwrite("update legislators set first_name = replace(substring_index(first_name, '\"', -2),'\"', '') where first_name like '%\"%'");
//Set full name to first last
dbwrite("update legislators set full_name = concat(first_name, ' ', last_name, if(suffixes!='', concat(' ', suffixes), ''))");

print "Inserting missing terms\n";
dbwrite("insert ignore into legislator_terms select nimsp_candidate_id, leg_id, recipient_state, cycle, a.district, substring(party, 1, 1), seat  from contributions a join legislators b on recipient_ext_id = nimsp_candidate_id  where recipient_ext_id in (select nimsp_candidate_id from legislators l left join legislator_terms on imsp_candidate_id = nimsp_candidate_id where imsp_candidate_id is null and nimsp_candidate_id != 0)  and seat in ('state:upper', 'state:lower') group by recipient_ext_id, cycle");

print "Inserting contact info\n";
dbwrite("update legislators a join state_legislators b on profile_link = nimsp_candidate_id set a.twitter = b.twitter, a.facebook = b.facebook, phone_toll_free = Phone__Toll_free, phone_session =  Phone__Session, phone_interim =  Phone__Interim, fax_session =  Fax__Session, fax_interim = Fax__Interim, contact_form = Email_Contact_form, a.email = b.email, a.office_address = b.office_address where Profile_Link != ''");

$district_check = dbLookupArray("select state, term, seat, max(cast(substring(District, 4) as unsigned)), count(distinct cast(substring(District, 4) as unsigned)) from legislator_terms where term between $min_cycle and $max_cycle and seat != 'state:governor' and (state != 'AK' or seat != 'state:upper') and state in (".arrayValuesToInString($states).")
	group by state, term, seat having max(cast(substring(District, 4) as unsigned)) != count(distinct cast(substring(District, 4) as unsigned))
	union
	select state, term, seat, ascii(max(district)), count(distinct district) from legislator_terms where term between $min_cycle and $max_cycle and seat != 'state:governor' and (state = 'AK' and seat = 'state:upper')
	group by state, term, seat having ascii(max(substring(district, 4))) - 64 != count(distinct district)");
if ($district_check) { 
	print "District Check failed: \n";
	print_r($district_check);
}

$missing_ids = fetchValue("select count(*) from legislators_missing_nimsp");
print "\rMissing $missing_ids nimsp ids - check legislators_missing_nimsp\n";

function update_terms($leg, $metadata) { 
	global $min_cycle, $max_cycle, $states;
	$party_aliases = array('REPUBLICAN'=>'REPUBLICAN', 'R'=>'REPUBLICAN', 'D'=>'DEMOCRAT', 'DEMOCRAT'=>'DEMOCRAT');
	foreach(array_keys($metadata) as $key) {
		if(preg_match("/^\d+$/", $key)) { 
			$role = $metadata[$key];
			if ($key < $min_cycle || $key > $max_cycle) { continue; }
			if (isset($role['seat_result']) && $role['seat_result'] == 'L') { continue; }
			if (! in_array($role['seat'], array('state:upper', 'state:lower', 'state:governor'))) { continue; }
			if (! in_array($role['state'], $states)) { continue; }
			$party = isset($party_aliases[$role['party']]) ? $party_aliases[$role['party']] : 'INDEPENDENT';
			$data = ['imsp_candidate_id'=>$leg['nimsp_candidate_id'], 'os_id'=>$leg['os_id'], 'state'=>strtoupper($role['state']), 'term'=>$key, 'district'=>strtoupper($role['district']),'party'=>$party,'seat'=>$role['seat']];
			$values_string = arrayToUpdateString($data);
			dbwrite("insert into legislator_terms set $values_string on duplicate key update $values_string");
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

