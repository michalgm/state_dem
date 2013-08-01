<?php
include_once('../config.php');
$companies_table = "oilchange.companies";

$fields = "cycle,transaction_id,is_amendment,amount,date,contributor_name,contributor_ext_id,contributor_type,contributor_occupation,contributor_employer,contributor_address,contributor_city,contributor_state,contributor_zipcode,contributor_category,organization_name,organization_ext_id,parent_organization_name,parent_organization_ext_id,recipient_ext_id,recipient_name,recipient_type,recipient_state,recipient_state_held,committee_name,committee_ext_id,committee_party,candidacy_status,district,district_held,seat,seat_held,seat_status,seat_result,race_id,donor_recipient_count,district_donor_recipient_count";

dbwrite("delete from contributions_dem");
dbwrite("ALTER TABLE contributions_dem DISABLE KEYS");

foreach(array('parent_organization_name', 'organization_name', 'contributor_employer', 'contributor_occupation') as $type) { 
	print "matching on $type\n";
	dbwrite("insert ignore into contributions_dem ($fields, company_name, company_id, sitecode) select a.*, name, match_id, dem_type from contributions a join $companies_table b on $type = name where $type != '' and contributor_category not like 'e11%' and  contributor_category != 'e1210' and recipient_type = 'P' and match_contribs_on_name = 1 and ignore_all_contribs = 0 and non_company_name = 0 and recipient_ext_id in (select nimsp_candidate_id from legislators)");
}

print "matching on code\n";
dbwrite("insert ignore into contributions_dem ($fields, sitecode) select a.*, if(contributor_category = 'E1210', 'coal', 'oil') from contributions a where (contributor_category like 'e11%' or contributor_category = 'e1210') and recipient_ext_id in (select nimsp_candidate_id from legislators)");
dbwrite("ALTER TABLE contributions_dem ENABLE KEYS");

print "filling in company_ids and names for contribs coded as DEM\n";
foreach(array('parent_organization_name', 'organization_name', 'contributor_employer', 'contributor_occupation') as $type) { 
	dbwrite("update contributions_dem a join $companies_table on $type = name  set a.company_name = name, a.company_id = match_id where company_id is null and non_company_name = 0 and $type != ''");
}
foreach(array('parent_organization_name', 'organization_name', 'contributor_employer', 'contributor_occupation') as $type) { 
	dbwrite("update contributions_dem set company_name = $type where $type != '' and $type not in (select name from $companies_table where non_company_name = 1) and company_name = ''");
}

print "Filling in missing company names based on contributor_name\n";
#dbwrite("update contributions_dem a join (select contributor_name, contributor_zipcode,  SUBSTRING_INDEX(GROUP_CONCAT(company_name ORDER BY num DESC SEPARATOR ':::'), ':::', 1) AS company_name from (select contributor_name, contributor_zipcode,company_name, count(*) as num from contributions_dem where company_name != '' and (contributor_category = 'e1210' or contributor_category like 'e11%') group by contributor_name, contributor_zipcode, company_name) a group by contributor_name ) b using (contributor_name, contributor_zipcode) set a.company_name = b.company_name where a.company_name = ''");
dbwrite("update contributions_dem a join (select contributor_name, contributor_zipcode, cycle,  SUBSTRING_INDEX(GROUP_CONCAT(organization_name ORDER BY num DESC SEPARATOR ':::'), ':::', 1) AS company_name from (select contributor_name, contributor_zipcode,cycle, b.organization_name, count(*) as num  from contributions_dem a join contributions b using(contributor_name, contributor_zipcode, cycle) where a.company_name = '' and b.organization_name != '' group by contributor_name, contributor_zipcode, cycle, b.organization_name ) a group by contributor_name, cycle) b using (contributor_name, contributor_zipcode, cycle) set a.company_name = b.company_name where a.company_name = ''");
dbwrite("update contributions_dem a join (select contributor_name, contributor_zipcode,  SUBSTRING_INDEX(GROUP_CONCAT(organization_name ORDER BY num DESC SEPARATOR ':::'), ':::', 1) AS company_name from (select contributor_name, contributor_zipcode, b.organization_name, count(*) as num  from contributions_dem a join contributions b using(contributor_name, contributor_zipcode) where a.company_name = '' and b.organization_name != '' group by contributor_name, contributor_zipcode, b.organization_name ) a group by contributor_name) b using (contributor_name, contributor_zipcode) set a.company_name = b.company_name where a.company_name = ''");
dbwrite("update contributions_dem a join $companies_table b on name = company_name set a.company_id = b.match_id where a.company_id is null and company_name != ''");

print "Removing contributions for unknown candidates, or old contribs to non-current legislators\n";
dbwrite("delete from contributions_dem where transaction_id in (select * from (select transaction_id from contributions_dem a left join legislator_terms b on recipient_ext_id = imsp_candidate_id and cycle = term and recipient_state = state where imsp_candidate_id is null) a ) or (recipient_ext_id not in (select imsp_candidate_id from legislator_terms where term = $max_cycle) and cycle < $min_cycle) or recipient_type != 'P' or company_id = 1");
dbwrite("delete from contributions_dem where company_id in (select id from companies where ignore_all_contribs = 1)");
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
print "--Deleting records for bad company names\n";
#dbwrite("delete from contributions_dem where company_id = 1");
#dbwrite("delete from contributions_dem where company_name in (select name from companies where ignore_all_contribs = 1)");

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

print "Cleaning company names\n";
$names = fetchCol("select name from oilchange.companies where cast(name as binary) rlike '^[^a-z]*$'");
foreach ($names as $name) {
	$new_words = [];
	$new_name = preg_replace_callback("/\b(.+?)\b/", "fixWord", $name);
	if ($new_name && $name) { 
		dbwrite("update oilchange.companies set match_name = '".dbEscape($new_name)."', name='".dbEscape($new_name)."' where name = '".dbEscape($name)."'");
	}
}

print "Updating State Years\n";
dbwrite("update states a join (select recipient_state as state, group_concat(distinct cycle order by cycle) as years from contributions_dem where cycle >= $min_cycle and cycle <= $max_cycle and cycle %2 = 0 group by recipient_state) b on a.state = b.state set a.years = b.years");

function fixWord($words) {
	$abbreviations = array('AA','AAA','AB','ABB','ABC','ABH','AC','ACT','AEI','AEL','AEP','AEPPSO','AES','AG','AGF','AGL','AJ','AK','AL','ALLETE','AM','AMPM','AN','ANR','AR','AS','AT','ATP','AW','AZ','AZTX','BB','BBF','BC','BD','BDH','BG','BHP','BJ','BLK','BNB','BOC','BP','BPA','BTA','BTT','C','CA','CAMAC','CBI','CBS','CCS','CDX','CE','CH','CHC','CHS','CI','CIPS','CIS','CITGO','CK','CL','CMP','CMS','CNG','CNO','CNX','CONSOL','CPS','CRC','CSS','CSX','CT','DAG','DBA','DC','DE','DH','DHJ','DK','DLB','DPL','DR','DTE','DWJ','EC','ECC','EIL','EJ','ELM','EN','ENI','ENIII','ENS','EO','EONAG','EQT','ET','EXL','FH','FL','FM','FMC','FMF','FPL','FSD','FW','FX','GA','GE','GF','GFI','GIANT','GL','GM','GMX','GP','GU','GW','HC','HD','HI','HNG','HS','HT','IA','IBEX','ICC','ID','IDC','IH','IHRDC','III','IL','IMTT','IN','IPALCO','IRI','ITT','IWC','JA','JCE','JCN','JD','JEC','JF','JG','JH','JJ','JM','JMWLLC','JOB','JP','JR','JS','JT','JTOD','JW','JWW','KBC','KBR','KC','KGEN','KLT','KN','KP','KPI','KS','KY','LA','LG','LGE','LI','LLC','LLOG','LLP','LM','LMP','LNG','LO','LP','LPC','LPG','LS','LTD','MA','MAP','MB','MC','MCC','MCR','MD','MDU','ME','MFA','MGS','MH','MHA','MI','MJ','MJH','MN','MO','MOC','MP','MPG','MS','MSC','MSL','MT','MTL','MTM','MV','MVP','MWI','MWJ','NACCO','NANA','NC','ND','NE','NH','NI','NIC','NIPSCO','NJ','NL','NM','NOV','NRG','NRPLP','NV','NW','NY','OCTGLLP','OGE','OH','OK','OMI','ONEOK','OR','OSI','PA','PAC','PAMD','PBF','PBGH','PBS','PDVSA','PEBA','PECO','PES','PFE','PGE','PK','PMR','PNERC','PNM','POG','PPG','PPL','PR','PSEG','PSI','PW','QEP','RAG','RC','RCP','RI','RII','RIM','RK','RKA','RL','RLM','RLU','RP','RPC','RPM','RSA','RSI','SA','SC','SCF','SD','SDG','SDS','SER','SES','SGS','SIGECO','SJ','SK','SOCO','SP','SPT','SPY','SR','SSI','SSL','SW','TB','TC','TD','TDC','TECO','TELO','TG','TGS','TH','TN','TRB','TRT','TRZ','TSP','TSS','TU','TX','TXU','UAF','UE','UGI','UMC','UNEV','UOPLLC','URS','US','USA','USLLC','USX','UT','UTI','VA','VF','VI','VK','VL','VP','VT','VTI','WA','WB','WC','WCLIII','WCS','WE','WG','WH','WI','WL','WM','WPS','WT','WV','WW','WY','XTO','XXI');
	$lowercase = array('OF', 'AND');
	$word = $words[0];
	if (in_array($word, $lowercase)) {
		$word = strtolower($word); 
	} else if (! in_array($word, $abbreviations)) { 
		$word = ucfirst(strtolower($word));
	}
	return $word;
}
