<?php
require_once("../config.php");
require_once("../dbaccess.php");

$data_source_text = "manual review";
$name_field = "name"; //the field containing the company name in the source table. 
$companies_table = "oilchange.companies"; 


$queries = array(
	'new'=>	array("newest to oldest", "select id,name,date_format(reviewed,'%Y-%m-%e') reviewed,notes from $companies_table where reviewed < date_sub(now(),interval 30 day) order by date_added desc limit 100"),
	'nimsp'=> array("review NIMSP", "select id,name,date_format(reviewed,'%Y-%m-%e') reviewed,notes, sum(amount) from $companies_table join contributions_dem on name = company_name where source like 'NIMSP%' and source not like 'NIMSP%Crp%' and reviewed = 0 group by company_name order by sum(amount) desc limit 100"),
	'nimsp_matching'=> array("review NIMSP new companies matching CRP", "select id,name,date_format(reviewed,'%Y-%m-%e') reviewed,notes, sum(c.amount) from $companies_table join contributions_dem c on name = company_name where source like 'NIMSP%CRP%' and reviewed = 0 group by company_name order by sum(c.amount) desc limit 100"),
	'unknown'=> array("unknown source", "select id,name,date_format(reviewed,'%Y-%m-%e') reviewed,notes from companies where source is null and cong_carbon_total+cong_oil_total+cong_coal_total > 0 and id=match_id and reviewed < date_sub(now(),interval 12 hour)"),
	'no_match_id'=> array("companies where match_id doesn't exist", "select a.id,a.name,date_format(a.reviewed,'%Y-%m-%e') reviewed,a.notes from companies a left join companies b on a.match_id = b.id where b.id is null and a.ignore_all_contribs = 0 and a.reviewed < date_sub(now(),interval 12 hour)"),
	'diff_parent'=> array("parent company has a different parent", "select a.id,a.name,date_format(a.reviewed,'%Y-%m-%e') reviewed,a.notes from companies a join companies b on a.match_id = b.id where b.match_id != b.id and  a.ignore_all_contribs = 0 and a.reviewed < date_sub(now(),interval 12 hour)")
);

$review_query_key = isset($_REQUEST['review_query']) ? $_REQUEST['review_query'] : 'new'; 
$review_query = $queries[$review_query_key][1];

$db = dbConnect();

$parent_id = -1;  // company id of parent that subsidiaries should be merged into

if (isset($_GET['parent_id'])){
		$parent_id =(int)$_GET['parent_id'];
}

if (isset($_GET['search_name'])){
		$search_name =$_GET['search_name'];
} else {
	$search_name = "example company%"; //example search name
}

if (isset($_GET['parent_name'])){
		$parent_name =$_GET['parent_name'];
} else {
	$parent_name = $search_name; //example search name
}



if(isset($_GET['insert_source'])){
	$insert_source = $_GET['insert_source'];
} else {
	$insert_source = $data_source_text;
}

if(isset($_GET['insert_name'])){
	$insert_name = $_GET['insert_name'];
} else {
	$insert_name = "";
}

if(isset($_GET['insert_cat'])){
	$insert_cat = $_GET['insert_cat'];
}

if(isset($_GET['notes'])){
	$notes = $_GET['notes'];
} else {
	$notes = "";
}

if(isset($_GET['review_notes'])){
	$review_notes = $_GET['review_notes'];
} else {
	$review_notes = "";
}

//figure out the list of selected companies, if any
//loop over tha params and record those statarting with "selected_id_"
$selected_companies = array();
foreach (array_keys($_GET) as $key){
	if(substr($key, 0, 12) === "selected_id_"){
		$selected_companies[] = substr($key,12);
	}
		
}

//run queries for searching lists of top-level companies
$parent_fields = "a.id, a.name,a.coal_related+a.oil_related+a.carbon_related dem_total,a.source, a.date_added,a.reviewed,a.notes";
//if parent name is an integer, assume it is a company id
//WARNING: this makes it impossible to look up any company that begins with a number
if (((int)$parent_name) > 0){
	$parent_search_query = "select $parent_fields from $companies_table a where a.id=".((int)$parent_name)." and a.id = a.match_id";
} else {
	$parent_search_query = "select $parent_fields from $companies_table a join $companies_table b on a.id = b.match_id where b.name like '".dbEscape($parent_name)."' and a.id = a.match_id group by a.id";
}
$parent_matches = dbLookupArray($parent_search_query);

//determine what type of action
$action = "searched";
$status = "nothing";
if (isset($_GET['remove_companies'])){
	$action = "removed";
	if($review_notes != ""){
		$review_notes = ", notes='".dbEscape($review_notes)."'";
	}
	if (count($selected_companies) > 0){
		$remove_query = "update $companies_table set match_id = 1,ignore_all_contribs=1,reviewed=now() $review_notes where id in (".arrayValuesToInString($selected_companies).")";
		dbWrite($remove_query);
		$status = "marked companies with ids ".arrayValuesToInString($selected_companies)." as non-DEM";
	} else {
		$status = "no companies were selected";
	}	
} else if (isset($_GET['mark_as_match_on_name'])){
	$action = "marked as match on name";
	if($review_notes != ""){
		$review_notes = ", notes='".dbEscape($review_notes)."'";
	}
	if (count($selected_companies) > 0){
		$match_query = "update $companies_table set match_contribs_on_name=1, reviewed=now() $review_notes where id in (".arrayValuesToInString($selected_companies).")";
		dbWrite($match_query);
		$status = "marked companies with ids ".arrayValuesToInString($selected_companies)." as match on name";
	} else {
		$status = "no companies were selected";
	}	
} else if (isset($_GET['reviewed_companies'])){
	$action = "reviewed";  //this action is used to indicate that a company has been checked at this date and is presumeably correct
	if($review_notes != ""){
		$review_notes = ", notes='".dbEscape($review_notes)."'";
	}
	if (count($selected_companies) > 0){
		$reviewed_query = "update $companies_table set reviewed=now() $review_notes where id in (".arrayValuesToInString($selected_companies).")";
		dbWrite($reviewed_query);
		$status = "marked companies with ids ".arrayValuesToInString($selected_companies)." as reviewed";
	} else {
		$status = "no companies were selected";
	}
	
} else if (isset($_GET['merge_companies'])){
	$action = "merged";
	if($review_notes != ""){
		$review_notes = ", notes='".dbEscape($review_notes)."'";
	}
	if ($parent_id > 0){
		if (count($selected_companies) > 0){
			$merge_query = "update $companies_table set reviewed=now(),match_id=$parent_id $review_notes where id in (".arrayValuesToInString($selected_companies).") or match_id in (".arrayValuesToInString($selected_companies).")";
			dbWrite($merge_query);
			$status = "companies with ids ".arrayValuesToInString($selected_companies)." into parent $parent_id";
		} else {
			$status = "no companies were selected";
		}
	} else {
		$status = "no parent is selected";
	}
} else if (isset($_GET['insert_companies'])){
	$action = "inserted";
	if($insert_name != '') {
		//check if it exists
		$name_count = fetchValue("select count(*) from $companies_table where name = '". dbEscape($insert_name)."'");
		if ($name_count < 1){
			$insert_query = "insert into $companies_table set name='". dbEscape($insert_name)."', source='".dbEscape($insert_source)."',dem_type='".dbEscape($insert_cat)."', notes='".dbEscape($notes)."',reviewed=now(),match_contribs_on_name=1";
			dbWrite($insert_query);
			//now need to update the match id to set to self
			$new_id = fetchValue("select id from $companies_table  where name = '". dbEscape($insert_name)."'");
			dbWrite("update $companies_table set match_id = id where id = $new_id");
			$status = " \"$insert_name\" into companies table";
		} else {
			$update_query = "update $companies_table set dem_type='".dbEscape($insert_cat)."', notes='".dbEscape($notes)."',reviewed=now(),match_contribs_on_name=1, ignore_all_contribs=0 where name = '".dbEscape($insert_name)."'";
			$id = dbWrite($update_query);
			$status = "'$insert_name' updated";
		}
	}
} else if (isset($_GET['submit'])){
	if ($_GET['submit'] === "search parent companies"){
		$action = "search parent";
		if(count($parent_matches) > 0) {
			//select the first one
			$row = reset($parent_matches);
			$parent_id = $row['id'];
			$status = "\"$parent_name\" and found ".count($parent_matches)." matches";
		} else {
		   //no matches, so make sure to unset
		   $parent_id = -1;	
		   $status = "\"$parent_name\" and found no matches";
		}
	}
} 



//queries for generating a list of names to review
//--------------------------

//newest to oldest
#$review_query = "select id,name,date_format(reviewed,'%Y-%m-%e') reviewed,notes from $companies_table where reviewed < date_sub(now(),interval 30 day) order by date_added desc limit 100";

#$review_query = "select id,name,date_format(reviewed,'%Y-%m-%e') reviewed,notes, cong_carbon_total from $companies_table where source like 'NIMSP%' and reviewed < date_sub(now(),interval 30 day) order by cong_carbon_total desc limit 100";
#$review_query = "select id,name,date_format(reviewed,'%Y-%m-%e') reviewed,notes, sum(amount) from $companies_table join contributions_dem on name = company_name where source like 'NIMSP%' and reviewed < date_sub(now(),interval 60 day) group by company_name order by sum(amount) desc limit 100";

//unknown source
//$review_query = "select id,name,date_format(reviewed,'%Y-%m-%e') reviewed,notes from companies where source is null and cong_carbon_total+cong_oil_total+cong_coal_total > 0 and id=match_id and reviewed < date_sub(now(),interval 12 hour)";

//companies where match_id doesn't exist
//$review_query = "select a.id,a.name,date_format(a.reviewed,'%Y-%m-%e') reviewed,a.notes from companies a left join companies b on a.match_id = b.id where b.id is null and a.ignore_all_contribs = 0 and a.reviewed < date_sub(now(),interval 12 hour)";

//and some where parent company has a different parent: 
//$review_query = "select a.id,a.name,date_format(a.reviewed,'%Y-%m-%e') reviewed,a.notes from companies a join companies b on a.match_id = b.id where b.match_id != b.id and  a.ignore_all_contribs = 0 and a.reviewed < date_sub(now(),interval 12 hour)";

$review_list = dbLookupArray($review_query);

$selected_parent_name="";
$children_list = array();
if (isset($parent_id)){
	$parent_query = "select name from $companies_table where id = $parent_id";
	$selected_parent_name = fetchValue($parent_query);
	$children_query = "select id, name,source, date_format(date_added,'%Y-%m-%e') date_added,notes from $companies_table where match_id = $parent_id";
	$children_list = dbLookupArray($children_query);
}


//if we are loading the next, use that as the search value
//if($next){
	//$search_name = $next_name;
//}

//queries for searching the general list of companies
$company_query = "select id,name as company,match_id, coal_related,oil_related,match_contribs_on_name as match_on_name,date_format(date_added,'%Y-%m-%e') date_added,source,date_format(reviewed,'%Y-%m-%e') reviewed,notes from $companies_table where name like '".dbEscape($search_name)."' order by id";
$company_matches = dbLookupArray($company_query);

//decide if doing crpmatches
$crp_matches = array();
$nimsp_matches = array();
if(isset($_GET['search_crp']) || isset($_GET['search_crp_2'])){
	//queries for searching the raw crp contribution tables
	$crp_search = isset($_GET['search_crp']) ? dbEscape($insert_name) : dbEscape($search_name);
	$crp_query = "select crp_key,companyname as company,donorName,Job,UltOrg,amount,cycle,indshort,catshort from crp.crp_contribs where companyname like '".$crp_search."'";

	$crp_matches = dbLookupArray($crp_query);

	$nimsp_query = "select transaction_id,contributor_name,contributor_occupation,contributor_employer,organization_name,parent_organization_name,amount,cycle,b.name,industry from state_dem.contributions a join state_dem.catcodes b on(contributor_category = code) where 
		parent_organization_name like '".$crp_search."' or
		organization_name like '".$crp_search."' or
		contributor_employer like '".$crp_search."' or
		contributor_name like '".$crp_search."'";

	$nimsp_matches = dbLookupArray($nimsp_query);


}




function match_tables($data) {
	$table = "<table border=0>";
	$first = 0;
	foreach ($data as $row) {
		if (! $first) {
			$table .="<tr>";
			foreach (array_keys($row) as $name) { $table.="<th>$name</th>\n"; }
			$first = 1;
			$table .="</tr>";
		}
		$table .="<tr>";
		$c=0;
		foreach($row as $col) { 
			if($c == 1){  //want to give company names a different class to style them
				$table .="<td class='companyname'>".$col."</td>\n";
			} else {
				$table .="<td>".$col."</td>\n";
			}
			$c++;
		}
		$table .="</tr>";
	}
	$table .="</table>";
	return $table;
}

function normForSearch($name){
	$name = str_replace("%","",$name);
	$name = str_replace(",Inc.","",$name);
	$name = str_replace(",INC.","",$name);
	$name = str_replace(" INC","",$name);
	$name = str_replace(",","",$name);
	$name = str_replace(".","",$name);
	$name = str_replace(" Corporation","",$name);
	$name = str_replace(" CORPORATION","",$name);
	$name = str_replace(" Corp","",$name);
	$name = str_replace(" CORP","",$name);
	$name = str_replace(" COMPANY","",$name);
	$name = str_replace(" LTD","",$name);
	$name = str_replace(" LP","",$name);
	$name = str_replace("LLC","",$name);
	$name = trim($name);
	$name .= "%";
	return($name);
}

?>
<head>
<title>DEM Companies Merge & Purge</title>
	<style type="text/css">
		h2 {color:#888888;}
		table {font-size: .9em;color: #555555;width:100%;}
		tr:nth-child(2n) {background-color: #ddffdd;}
		th {color: #CCCCCC;}
		tr:hover {background-color: #55ff55;}
		tr {cursor: pointer;}
		td {padding:2px;}
		
		.companyname {color:#000000;font-weight: bold;}
		
		#child_listing_table { height:200px;overflow: auto;}
		#parent_matches { height:200px;overflow: auto;}
		#review_list {height:100px; overflow:auto;}
		#crp_matches, #nimsp_matches {height:500px;overflow:auto;}
		
		#parent_search {width:45%; padding: 10px;border:5px solid #cccccc;float:right;}
		#general_search {width:45%;padding: 10px;border:5px solid #cccccc;margin-top:50px;}
		#general_search_list {height:150px;overflow:auto;border:1px CCCCCC;}
		#add_new_company {width:45%;margin-top:50px;padding: 10px; border:5px solid #cccccc;}
		#review_list_area {width:45%; padding: 10px;border:5px solid #cccccc;}
		#status {float:right;width:45%;}
		#crp_contributions, #nimsp_contributions {clear:both; padding: 10px; border:5px solid #cccccc;margin-top:50px;}
		#review_query_select { position: absolute; right: 5px; top: 5px; }	
		#general_search input[type="submit"] { display: inline; }	
	</style>
	<script language="javascript" type="text/javascript">
		function enterMatch(name) {
			name = name.split(' ')[0]+'%';
			document.search.search_name.value = name;
			document.getElementById('name_search_button').click();
			
		}
		
		function enterParent(name) {
			document.search.parent_name.value = name;
			document.search.insert_name.value = name;
			document.getElementById('parent_search_button').click();
		}
</script>
</head>
<body style="font-size:0.8em">

<h1>Tool for reviewing merges in main Oil Change companies table</h1>
<form id='review_query_select'>
Review Query: <select onChange="document.getElementById('review_query_select').submit();" name='review_query'>
<?php
foreach (array_keys($queries) as $key) { 
	$query = $queries[$key];
	$selected = $key == $review_query_key ? "selected='selected'" : "";
	print "<option $selected value='$key'>$query[0]</option>";
}
?>
</select>
</form>
<div id='status'>
<h2>Last action</h2>
	<h3>
	<?php 
	print($action.": ".$status);
	
	?>
	</h3>
</div>


<div id="review_list_area">
	<h2>List of <?php print(count($review_list));?> companies for review</h2>
	<div id="review_list">
	<?php
		$table = "<table border=0>";
	$first = 0;
	foreach ($review_list as $row) {
		if (! $first) {
			$table .="<tr>";
			foreach (array_keys($row) as $name) { $table.="<th>$name</th>\n"; }
			$first = 1;
			$table .="</tr>";
		}
		$table .="<tr>";
		$c=0;
		foreach($row as $col) { 
			if($c == 1){  //want to give company names a different class to style them
				$table .="<td class='companyname' onClick=\"enterMatch('$col');\">".$col."</td>\n";
			} else {
				$table .="<td>".$col."</td>\n";
			}
			$c++;
		}
		$table .="</tr>";
	}
	$table .="</table>";
	print($table);
	?>
	</div>
</div>

<div id="parent_search">
<h2>Parent company search</h2>	
	<form name="search">
	<input type='hidden' name='review_query' value='<?php echo $review_query_key; ?>'/>
	<input type="text" name="parent_name" value="<?php print($parent_name); ?>" style="width:400px;"/>
	<input id='parent_search_button' type="submit" name="parent_search_button" value="search parent companies" /><br />

	
	<?php
		//print out the parent search matches
		$table = "<table border=0>";
		$table .= "<tr><th></th><th>Parent name</th><th>id</th><th>Dem total</th></tr>";
		foreach($parent_matches as $row){
			$selected = '';
			if ($row['id'] == $parent_id){
				$selected = "checked";
			}
			$table .= "<tr>";
			$table .= "<td><input type='radio' onClick='this.form.submit();' name='parent_id' $selected value='".$row['id']."'></input></td><td class='companyname'>".$row['name']."</td><td>".$row['id']."</td><td>".$row['dem_total']."</td>";
			$table .="</tr>";
		}
		$table .="</table>";
		print($table);
	?>
	
	<div id="child_listing">
	
		<h2>Aliases of parent <?php print($selected_parent_name." (id:".$parent_id.")");?></h2>
	
		<div id="child_listing_table">
		<?php
			//print out the parent search matches
			print(match_tables($children_list));
		?>
		</div>
	</div>
</div>

<div id = "general_search">
<h2>General company search</h2>

		<input type="text" name="search_name" value="<?php print($search_name); ?>" style="width:300px;"/>
		<input id='name_search_button' type="submit" name="name_search_button" value="search companies" />
		<input type="submit" name="search_crp_2" value="search_raw_records" /><br />
	
		<a href="http://www.google.com/search?q=<?php print($search_name)?>" target="_">search google</a>
		<a href="http://en.wikipedia.org/wiki/<?php print($search_name)?>" target="_">search wikipedia</a>
		<h4>Matches to all companies (<?php print(count($company_matches)) ?>)</h4>
		<div id = "general_search_list">
<?php
	//print out the search matches
	$table = "<table border=0>";
	$first = 0;
	foreach ($company_matches as $row) {
		if (! $first) {
			$table .="<tr><th>selected</th>";
			foreach (array_keys($row) as $name) { $table.="<th>$name</th>\n"; }
			$first = 1;
			$table .="</tr>";
		}
		$table .="<tr><td><input type='checkbox' name='selected_id_".$row['id']."' value='1' /></td>";
		$c=0;
		foreach($row as $col) { 
			if($c == 1){  //want to give company names a different class to style them
				$table .="<td onClick=\"enterParent('$col');\" class='companyname'>".$col."</td>\n";
			} else {
				$table .="<td>".$col."</td>\n";
			}
			$c++;
		}
		$table .="</tr>";
	}
	$table .="</table>";
	print($table);
?>
	</div>
	<input type="submit" name="reviewed_companies" value="Mark selected as reviewed" /><br />
	<?php //only show merge option if parent selected
	if($parent_id > 0){
		print("<input type=\"submit\" name=\"merge_companies\" value=\"Merge selected into parent ".$selected_parent_name." (id:".$parent_id.")\" />");
	}
	?>
	
	<input type="submit" name="remove_companies" value="Remove selected (flag as non-DEM)" />
	<input type="submit" name="mark_as_match_on_name" value="Mark selected as match on name" /><br />
	<label for="review_notes">Notes:</label> 
	<input type="text" name="review_notes" value="" style="width:300px;">
	

</div>



<div id="add_new_company">
<h2>Add a new company name</h2>

<input type="text" id="insert_name" name="insert_name" value="<?php echo $insert_name?>" style="width:400px;"/>
	<input type="submit" name="search_crp" value="search_raw_records" />
	<input type="submit" name="insert_companies" value="add company" /><br />
	<label for="insert_source">data source</label>
	<input type="text" id="insert_source" name="insert_source" value="<?php echo($insert_source); ?>" /><br />
	<label for="notes">notes</label>
	<input type="text" id="notes" name="notes" value="" />
	<label for="insert_cat">category</label>
	<select name="insert_cat">
		<option value="oil">oil</option>
		<option value="coal">coal</option>
		<option value="carbon">carbon</option>
	</select>
	
</form>
</div>

<div id='crp_contributions'>
<h2>"<?php print($search_name); ?>" Matches to raw CRP contributions: <?php print(count($crp_matches)); ?></h2>
<div id="crp_matches">
	<?php print(match_tables($crp_matches)); ?>
</div>
</div>

<div id='nimsp_contributions'>
<h2>"<?php print($search_name); ?>" Matches to raw NIMSP contributions: <?php print(count($nimsp_matches)); ?></h2>
<div id="nimsp_matches">
	<?php print(match_tables($nimsp_matches)); ?>
</div>
</div>




</body>
