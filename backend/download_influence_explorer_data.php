<?php
include_once('../config.php');
$db = dbconnect();
if (isset($argv[1])) {
	$url = $argv[1];
	$size_cmd = 'unzip -l contributions.nimsp.csv.zip | grep -e "^[0-9]" -m1 | cut -f1 -d " "';
#	system("wget '$url'");
	system("unzip -p contributions.nimsp.csv.zip | pv -s `$size_cmd`  | ./csvfix find -f 30 -e ".implode(' -e ', array_keys($states))." |  sed -e 's/,,/,\\N,/g' -e 's/,,/,\\N,/g' > contributions.nimsp.csv");
	dbwrite("delete from contributions");
	dbwrite("load data local infile 'contributions.nimsp.csv' into table contributions  FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"' (@x, @x, cycle, @x, transaction_id, @x, @x, is_amendment, amount, date, contributor_name, contributor_ext_id, contributor_type, contributor_occupation, contributor_employer, @x, contributor_address, contributor_city, contributor_state, contributor_zipcode, contributor_category, organization_name, organization_ext_id, parent_organization_name, parent_organization_ext_id, recipient_name, recipient_ext_id, @x, recipient_type, recipient_state, recipient_state_held, @x, committee_name, committee_ext_id, committee_party, candidacy_status, district, district_held, seat, seat_held, seat_status, seat_result);");
}
dbwrite("optimize table contributions");

/*
dbwrite("delete from races");
dbwrite("update imsp_committees set race_id = null");
dbwrite("update imsp_candidates set race_id = null");
dbwrite("insert into races (cycle, state, office) SELECT distinct  year, state, imsp_ballot_measure_id FROM imsp_committees");
dbwrite("insert into races (cycle, state, office, district) SELECT distinct  year, state, office, district FROM imsp_candidates i where left(office, 1) = 'S' or left(office, 1) = 'R' ");
dbwrite("insert into races (cycle, state, office) SELECT distinct  year, state, office FROM imsp_candidates i where left(office, 1) != 'S' and left(office, 1) != 'R'");
dbwrite("update imsp_candidates a join races b on a.state = b.state and a.year = b.cycle and a.office = b.office and a.district = b.district set a.race_id = b.id where left(a.office, 1) = 'S' or left(a.office, 1) = 'R'");
dbwrite("update imsp_candidates a join races b on a.state = b.state and a.year = b.cycle and a.office = b.office set a.race_id = b.id where left(a.office, 1) != 'S' and left(a.office, 1) != 'R'");
dbwrite("update imsp_committees a join races b on a.state = b.state and a.year = b.cycle and a.imsp_ballot_measure_id = b.office set a.race_id = b.id");
//dbwrite("update contributions set recipient_ext_id =  '24659' where recipient_ext_id = 140582 and cycle  = 2010 and recipient_state = 'AR'");
//dbwrite("update contributions set recipient_ext_id =  '130223' where recipient_ext_id = 13022 and cycle  = 2010 and recipient_state = 'WV'");
//dbwrite("update contributions set recipient_ext_id =  '47' where recipient_ext_id = 20 and cycle  = 2010 and recipient_state = 'HI'");
//dbwrite("update contributions set recipient_ext_id =  '14877' where recipient_ext_id = 14878 and cycle  = 1998 and recipient_state = 'MA'");


foreach (fetchCol("select distinct cycle from contributions") as $year) { 
	print time() ." - $year\n";
	dbwrite("update contributions set race_id = null, donor_recipient_count =0, district_donor_recipient_count=0 where cycle = $year");

dbwrite("update contributions a join imsp_candidates b on a.recipient_state = b.state and a.cycle = b.year and left(b.office, 1) =  if(seat = 'state:governor', 'G', if(seat = 'state:office' , 'Z', if(seat = 'state:judicial', 'J', ''))) and a.recipient_ext_id = unique_candidate_id and seat!='state:lower' and seat != 'state:upper' and a.race_id is null set a.race_id = b.race_id where a.cycle = $year");

dbwrite("update contributions a join imsp_candidates b on a.recipient_state = b.state and a.cycle = b.year and left(b.office, 1) = if(seat = 'state:lower', 'R', if(seat = 'state:upper', 'S', '')) and a.district = concat(b.state, '-', trim(leading '0' from b.district)) and a.recipient_ext_id = unique_candidate_id and (seat='state:lower' or seat = 'state:upper') and a.race_id is null set a.race_id = b.race_id where a.cycle = $year");

dbwrite("update contributions a join imsp_candidates b on a.recipient_state = b.state and a.cycle = b.year and left(b.office, 1) = if(seat = 'state:lower', 'R', if(seat = 'state:upper', 'S', '')) and a.recipient_ext_id = unique_candidate_id and (seat='state:lower' or seat = 'state:upper')  set a.race_id = b.race_id where a.cycle = $year and a.race_id is null");

dbwrite("update contributions a join imsp_candidates b on a.recipient_state = b.state and a.cycle = b.year and left(b.office, 1) =  'P' and seat = 'state:office' and a.recipient_ext_id = unique_candidate_id and a.race_id is null set a.race_id = b.race_id where a.cycle = $year and a.race_id is null");

dbwrite("update contributions a join imsp_committees b on a.recipient_state = b.state and a.cycle = b.year and a.committee_ext_id = imsp_committee_id and a.race_id is null and a.seat is null set a.race_id = b.race_id where a.cycle = $year");

dbwrite("update contributions set recipient_ext_id = 1508 where  race_id is null and seat = 'state:governor' and cycle = 2010 and recipient_state = 'IL'");
	dbwrite("update contributions a join (select race_id, contributor_name, if(recipient_ext_id is not null, count(distinct recipient_ext_id),  count(distinct committee_ext_id))  as count from contributions e join races f on race_id = id  where race_id is not null and contributor_type is not null and contributor_name is not null and f.cycle = $year and e.district is not null and (left(office, 1) = 'R' or left(office, 1) = 'S') group by race_id, contributor_name having count(distinct committee_ext_id) > 1 or count(DISTINCT recipient_ext_id) > 1) b using (race_id, contributor_name) join races c on a.race_id = c.id and c.cycle = $year
set district_donor_recipient_count = b.count
where race_id is not null and contributor_type is not null and contributor_name is not null");

	dbwrite("update contributions a join (select race_id, contributor_name, if(recipient_ext_id is not null, count(distinct recipient_ext_id),  count(distinct committee_ext_id))  as count from contributions e  force index(count) join races f on race_id = id  where race_id is not null and contributor_type is not null and contributor_name is not null and f.cycle = $year group by f.cycle, f.state, f.office, contributor_name having count(distinct committee_ext_id) > 1 or count(DISTINCT recipient_ext_id) > 1) b using (race_id, contributor_name) join races c on a.race_id = c.id and c.cycle = $year
set donor_recipient_count = b.count
where race_id is not null and contributor_type is not null and contributor_name is not null");
}	

dbwrite("update races a join (select sum(total) as total, count(*) as num, state, year as cycle, office from imsp_candidates where office not like 'S%' and office not like 'R%' group by  state, year, office) b using (cycle, state, office) set a.contributions= num, a.total = b.total");
dbwrite("update races a join (select sum(total) as total, count(*) as num, state, year as cycle, office, district from imsp_candidates where (office like 'S%' or office like 'R%') group by  state, year, office, district) b using (cycle, state, office, district) set a.candidates= num, a.total = b.total");
dbwrite("update races a join (select sum(total_dollars) as total, count(*) as num, state, year as cycle, imsp_ballot_measure_id as office from imsp_committees group by  state, year, imsp_ballot_measure_id) b using (cycle, state, office) set a.candidates= num, a.total = b.total");
 */
?>
