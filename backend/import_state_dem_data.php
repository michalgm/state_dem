<?php
include_once('../config.php');
$companies_table = "oilchange.companies";

$fields = "cycle,transaction_id,is_amendment,amount,date,contributor_name,contributor_ext_id,contributor_type,contributor_occupation,contributor_employer,contributor_address,contributor_city,contributor_state,contributor_zipcode,contributor_category,organization_name,organization_ext_id,parent_organization_name,parent_organization_ext_id,recipient_ext_id,recipient_name,recipient_type,recipient_state,recipient_state_held,committee_name,committee_ext_id,committee_party,candidacy_status,district,district_held,seat,seat_held,seat_status,seat_result,race_id,donor_recipient_count,district_donor_recipient_count";

dbwrite("delete from contributions_dem");
dbwrite("ALTER TABLE contributions_dem DISABLE KEYS");

print "matching on parent_org\n";
dbwrite("insert ignore into contributions_dem ($fields, company_name, company_id, sitecode) select a.*, name, match_id, dem_type from contributions a join $companies_table b on parent_organization_name = name where contributor_category not like 'e11%' and  contributor_category != 'e1210'  and match_contribs_on_name = 1 and ignore_all_contribs = 0 and recipient_ext_id in (select nimsp_candidate_id from legislators)");

print "matching on org\n";
dbwrite("insert ignore into contributions_dem ($fields, company_name, company_id, sitecode) select a.*, name, match_id, dem_type from contributions a join $companies_table b on organization_name = name where contributor_category not like 'e11%' and  contributor_category != 'e1210'  and match_contribs_on_name = 1 and ignore_all_contribs = 0 and recipient_ext_id in (select nimsp_candidate_id from legislators)");

print "matching on employer\n";
dbwrite("insert ignore into contributions_dem ($fields, company_name, company_id, sitecode) select a.*, name, match_id, dem_type from contributions a join $companies_table b on contributor_employer = name where contributor_category not like 'e11%' and  contributor_category != 'e1210'  and match_contribs_on_name = 1 and ignore_all_contribs = 0 and recipient_ext_id in (select nimsp_candidate_id from legislators)");

print "matching on occupation\n";
dbwrite("insert ignore into contributions_dem ($fields, company_name, company_id, sitecode) select a.*, name, match_id, dem_type from contributions a join $companies_table b on contributor_occupation = name where contributor_category not like 'e11%' and  contributor_category != 'e1210'  and match_contribs_on_name = 1 and ignore_all_contribs = 0 and recipient_ext_id in (select nimsp_candidate_id from legislators)");

print "matching on code\n";
dbwrite("insert ignore into contributions_dem ($fields, sitecode) select a.*, if(contributor_category = 'E1210', 'coal', 'oil') from contributions a where (contributor_category like 'e11%' or contributor_category = 'e1210') and recipient_ext_id in (select nimsp_candidate_id from legislators)");
dbwrite("ALTER TABLE contributions_dem ENABLE KEYS");

print "filling in company_ids and names for contribs coded as DEM\n";
dbwrite("update contributions_dem a join $companies_table on parent_organization_name = name  set a.company_name = name, a.company_id = match_id where company_id is null and ignore_all_contribs = 0");
dbwrite("update contributions_dem a join $companies_table on organization_name = name  set a.company_name = name, a.company_id = match_id where company_id is null and ignore_all_contribs = 0");
dbwrite("update contributions_dem a join $companies_table on contributor_employer = name  set a.company_name = name, a.company_id = match_id where company_id is null and ignore_all_contribs = 0");
dbwrite("update contributions_dem a join $companies_table on contributor_occupation = name  set a.company_name = name, a.company_id = match_id where company_id is null and ignore_all_contribs = 0");
dbwrite("update contributions_dem set company_name = if(parent_organization_name != '', parent_organization_name, if(organization_name != '', organization_name, if(contributor_employer != '', contributor_employer, contributor_occupation))) where company_name = ''");

print "Filling in missing company names based on contributor_name\n";
#dbwrite("update contributions_dem a join (select contributor_name, contributor_zipcode,  SUBSTRING_INDEX(GROUP_CONCAT(company_name ORDER BY num DESC SEPARATOR ':::'), ':::', 1) AS company_name from (select contributor_name, contributor_zipcode,company_name, count(*) as num from contributions_dem where company_name != '' and (contributor_category = 'e1210' or contributor_category like 'e11%') group by contributor_name, contributor_zipcode, company_name) a group by contributor_name ) b using (contributor_name, contributor_zipcode) set a.company_name = b.company_name where a.company_name = ''");
dbwrite("update contributions_dem a join (select contributor_name, contributor_zipcode, cycle,  SUBSTRING_INDEX(GROUP_CONCAT(organization_name ORDER BY num DESC SEPARATOR ':::'), ':::', 1) AS company_name from (select contributor_name, contributor_zipcode,cycle, b.organization_name, count(*) as num  from contributions_dem a join contributions b using(contributor_name, contributor_zipcode, cycle) where a.company_name = '' and b.organization_name != '' group by contributor_name, contributor_zipcode, cycle, b.organization_name ) a group by contributor_name, cycle) b using (contributor_name, contributor_zipcode, cycle) set a.company_name = b.company_name where a.company_name = ''");
dbwrite("update contributions_dem a join (select contributor_name, contributor_zipcode,  SUBSTRING_INDEX(GROUP_CONCAT(organization_name ORDER BY num DESC SEPARATOR ':::'), ':::', 1) AS company_name from (select contributor_name, contributor_zipcode, b.organization_name, count(*) as num  from contributions_dem a join contributions b using(contributor_name, contributor_zipcode) where a.company_name = '' and b.organization_name != '' group by contributor_name, contributor_zipcode, b.organization_name ) a group by contributor_name) b using (contributor_name, contributor_zipcode) set a.company_name = b.company_name where a.company_name = ''");
dbwrite("update contributions_dem a join $companies_table b on name = company_name set a.company_id = b.match_id where a.company_id is null and company_name != ''");

print "Removing contributions for unknown candidates, or old contribs to non-current legislators\n";
dbwrite("delete from contributions_dem where recipient_ext_id not in (select imsp_candidate_id from legislator_terms where term >= 2008) or (recipient_ext_id not in (select imsp_candidate_id from legislator_terms where term = 2012) and cycle < 2008)");
#dbwrite("delete from contributions_dem where company_id in (select company_id from oilchange.companies where ignore_all_contribs = 1)");
dbwrite("delete from contributions_dem where company_name = ''");

print "Creating temporary tables to handle new companies\n";
dbwrite("drop table if exists companies_crp");
dbwrite("drop table if exists companies_nimsp");
dbwrite("CREATE TABLE  `state_dem`.`companies_crp` ( `name` varchar(600) NOT NULL, KEY `new_index` (`name`)) ENGINE=MyISAM DEFAULT CHARSET=utf8");
dbwrite("create table companies_nimsp like companies_crp");
print "--Loading unmatched crp company names";
dbwrite("insert into companies_crp select distinct company from crp.crp_contribs where companyid = ''");
print "--Loading unmatched nimsp company names";
dbwrite("insert into companies_nimsp select distinct company_name from contributions_dem where company_id is null");
print "--Loading unmatched NIMSP DEM-coded companies that match CRP into companies table\n";
dbwrite("insert into companies (name, match_name, source, dem_type, match_contribs_on_name) select * from (select name, name as name2, 'NIMSP DEM-coded - matches CRP', sitecode, -1 from companies_nimsp join companies_crp using (name) join contributions_dem on name = company_name group by name) a");
print "--Filling in company ids\n";
dbwrite("update companies set match_id = id where match_id is null");
dbwrite("update contributions_dem a join companies b on company_name = match_name set a.company_id = b.match_id where a.company_id is null");
print "--Loading unmatched NIMSP DEM-coded companies into companies table\n";
dbwrite("insert into companies (name, match_name, source, dem_type, match_contribs_on_name) select * from (select company_name, company_name as name2, 'NIMSP DEM-coded', sitecode, -1 from contributions_dem where company_id is null group by company_name) a");
print "--Filling in company ids\n";
dbwrite("update companies set match_id = id where match_id is null");
dbwrite("update contributions_dem a join companies b on company_name = match_name set a.company_id = b.match_id where a.company_id is null");
dbwrite("drop table if exists companies_crp");
dbwrite("drop table if exists companies_nimsp");
//exit;
print "Setting up State Companies metadata";
dbwrite("delete from companies_state_details");
dbwrite("insert into companies_state_details (company_id, name) select company_id, name from contributions_dem a join companies on company_id = id group by company_id"); 
dbwrite("update companies_state_details join (select sum(amount) as amount, company_id from contributions_dem where sitecode='oil' group by company_id) b using(company_id) set oil_related = amount");
dbwrite("update companies_state_details join (select sum(amount) as amount, company_id from contributions_dem where sitecode='coal' group by company_id) b using(company_id) set coal_related = amount");
dbwrite("update companies_state_details join (select sum(amount) as amount, company_id from contributions_dem where sitecode='carbon' group by company_id) b using(company_id) set carbon_related = amount");
dbwrite("update contributions_dem join companies_state_details using (company_id) set sitecode = dem_type where sitecode is null and dem_type is not null");
dbwrite("update companies_state_details set dem_type = if(coal_related+oil_related = 0, 'carbon', if(oil_related < coal_related, 'coal', 'oil')) where dem_type is null");
dbwrite("update contributions_dem join companies_state_details using (company_id) set sitecode = dem_type where sitecode is null");

