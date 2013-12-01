<?php
include_once('../config.php');

$db = dbconnect();
dbwrite("delete from reports");
dbwrite("drop table if exists reports_temp");
dbwrite("create table reports_temp like reports");

foreach(array("candidates","companies") as $type) { 
	//Year queries
	$category = $type == 'candidates' ? 'sitecode' : 'party';
	$entity_id = $type == 'candidates' ? 'imsp_candidate_id' : "company_id";
	$category_table = $type == 'candidates' ? "contributions_dem" : "legislator_terms";	
	$entity_table = $type == 'candidates' ? "legislator_terms t" : " (select distinct company_id from contributions_dem) d join (select year as term from years) y join (select distinct seat from legislator_terms) t ";
	$category_lookup = "if($category='' or $category='I' or $category='N', 'I', $category)";

	$populate_query = "select 'year_report_$type', term as label, 0 as value, concat($entity_id,t.seat) as entity_id, category as category from $entity_table  join (select distinct $category_lookup as category from $category_table) b where term  between $min_cycle and $max_cycle ";
	$update_query = "select 'year_report_$type' as report, term as label, sum(if(amount, amount, 0)) as value, concat($entity_id, t.seat) as entity_id, $category_lookup as category
		from legislator_terms  t 
		join contributions_dem c on recipient_ext_id = imsp_candidate_id and term = cycle where t.term between $min_cycle and $max_cycle
		group by entity_id, term, category";
	update_reports($populate_query, $update_query);

	//Average queries
	$person_count = $type == 'candidates' ? " (select state, term as year, seat, count(*) as count from legislator_terms where term >= $min_cycle group by state, term, seat) c using(state, year) join " : "";
	$company_count = $type == 'candidates' ? '' : ", count(distinct company_id) as count ";
	$seat_join = $type == 'candidates' ? ', seat' : "";

	$populate_query = "select 'congress_average_$type', year as label, 0 as value, concat(state, seat) as entity_id, '' as category from states a join years b join (select distinct seat from legislator_terms) c";
	$update_query = "select 'congress_average_$type' as report, year as label, amount/count as value, concat(state, seat) as entity_id, '' as category from 
		states a join years b join $person_count
		(select sum(amount) as amount , seat, cycle as year, recipient_state as state $company_count from contributions_dem c group by cycle, seat, recipient_state) d using (state, year $seat_join)";
	update_reports($populate_query, $update_query);

	//Average queries for 'all' chambers
	$person_count = $type == 'candidates' ? " (select state, term as year, count(*) as count from legislator_terms where term >= $min_cycle and seat in ('state:upper', 'state:lower') group by state, term) c using(state, year) join " : "";
	$company_count = $type == 'candidates' ? '' : ", count(distinct company_id) as count ";
	$seat_join = $type == 'candidates' ? ', seat' : "";

	$populate_query = "select 'congress_average_$type', year as label, 0 as value, concat(state, 'state:all') as entity_id, '' as category from states a join years b";
	$update_query = "select 'congress_average_$type' as report, year as label, amount/count as value, concat(state, 'state:all') as entity_id, '' as category from 
		states a join years b join $person_count
		(select sum(amount) as amount , cycle as year, recipient_state as state $company_count from contributions_dem c where seat in ('state:upper', 'state:lower') group by cycle, recipient_state) d using (state, year)";
	update_reports($populate_query, $update_query);
}
dbwrite("drop table if exists reports_temp");


function update_reports($populate_query, $update_query) {
	dbwrite("insert into reports $populate_query");
	dbwrite("delete from reports_temp");
	dbwrite("insert into reports_temp $update_query");
	dbwrite("update reports a join reports_temp b using(report, label, entity_id, category) set a.value = b.value ");
}

