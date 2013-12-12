<?php
include_once('../config.php');
$fail_count = 0;
$test_count = 0;

$reports_query = "select sum(value) as amount, substring_index(entity_id, '|', 2) as Id, label as year, substring_index(entity_id, '|', -1) as chamber from reports where report = 'year_report_candidates' and entity_id not like '%governor' group by report, label, entity_id";
$edges_query = "select sum(value) as amount, graphname, concat(toId, '|',substring_index(graphname, '_',1)) as Id, substring(graphname, 4, 4) as year, substring_index(graphname, '_',-1) as chamber from edgestore where tag = (select max(tag) from edgestore) and graphname not like '%_all%' group by graphname, edgestore.toid";
test("select * from ($reports_query)  a join ($edges_query) b using (id, year, chamber) where abs(a.amount - b.amount) > 1", "check node amounts equal report amounts for candidates");
test("select * from (select count(*) as c1 from ($reports_query)a )  a join (select count(*) as c2 from ($edges_query) b)b  where c1 != c2", "check node counts equal report counts for candidates");

$reports_query = "select sum(value) as amount, concat('co-',substring(entity_id, 1, length(entity_id) - 11)) as id, label as year, substring(entity_id, -11) as chamber from reports where report = 'year_report_companies'  and entity_id not like '%governor' group by report, label, entity_id having amount != 0";
$edges_query = "select sum(value) as amount, concat(fromId, substring(graphname, 1,2)) as id, substring(graphname, 4, 4) as year, substring(graphname, -11) as chamber from edgestore where tag = (select max(tag) from edgestore) and graphname not like '%_all%' and type = 'donations' group by graphname, edgestore.fromid";
test("select * from ($reports_query)  a join ($edges_query) b using (id, year, chamber) where abs(a.amount - b.amount) > 1", "check node amounts equal report amounts for companies");
test("select * from (select count(*) as c1 from ($reports_query)a )  a join (select count(*) as c2 from ($edges_query) b)b  where c1 != c2", "check node counts equal report counts for candidates");

test("select * from (select concat(toId, '|', substring_index(graphname, '_', 1)) as id, sum(value) as amount from edgestore where tag = (select max(tag) from edgestore) and graphname like '%governor%' group by id) a join (SELECT substring_index(entity_id, '|', 2) id, sum(value) as amount FROM reports r where entity_id like '%governor' and report = 'year_report_candidates' group by id) b using (id) where abs(a.amount - b.amount) > 5", "governor reports vs graphs");

test("select * from legislators a join (select graphname, toId, sum(value) as val from edgestore a join (SELECT max(tag) as tag FROM edgestore e) b using (tag) where graphname not like '%_all_%' and graphname not like '%:all' group by toId) b on toId = nimsp_candidate_id where abs(val - lifetime_total) >1", "Edgestore total vs lifetime Total");

test("select * from (SELECT  nimsp_candidate_id, full_name as Legislator,   Cycle, sum(Amount) as amount FROM contributions_dem c join companies on company_id = id join legislators l on recipient_ext_id = nimsp_candidate_id join legislator_terms t on recipient_ext_id = imsp_candidate_id and cycle = term join states s on recipient_state = s.state group by nimsp_candidate_id, cycle order by nimsp_candidate_id, cycle) a join (select toid, substring(substring_index(graphname, '_', 2), -4) as cycle, sum(value) as amount from edgestore where tag = (select max(tag) from edgestore) and graphname not like '%_all_%' and graphname not like '%all' group by toId, substring(substring_index(graphname, '_', 2), -4) having amount != 0  order by toid, cycle) b on nimsp_candidate_id = toid and a.cycle = b.cycle and a.amount = b.amount where abs(a.amount - b.amount) > 1", "checking csvs");

if (! $fail_count) { 
	print "\nAll tests passed!\n";
} else {
	print "\n$fail_count out of $test_count test failed\n";
}

function test($query, $name) { 
	global $fail_count, $test_count;
	$test_count++;
	$results = dbLookupArray($query);
	if ($results) { 
		$fail_count++;
		print "!!! Test '$name' failed!\n$query\n";
		print_r($results);
	}
}

?>
