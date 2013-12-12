	<nav id="navbar">
		<form id="graphoptions" class="container navlist">
			<div id='options_container'>
				<input type="hidden" name="setupfile" value="StateDEM"/>
				<select name="state" id="state" class="navitem">
					<option>CA</option>
				</select>
				<select name="chamber" id="chamber" class="navitem">
					<option value="state:upper">Senate</option>
					<option value="state:lower">Assembly</option>
				</select>
				<select name="cycle" id="cycle" class="navitem"> </select>
				<a href="#" id="csvlink" class="hide">Download data</a>
			</div>
			<div id="searchfield" class="navitem">
				<input class="node_search" id="node_search" autocomplete="off" size="20" type="text" value="" />
				<div id="no_results_found" class="hide">No Results Found</div>
				<div class="autocomplete node_search_list" id="node_search_list" style=""></div>
			</div>
		</form>
		<a href="#" id="pull">Menu</a>
	</nav>
