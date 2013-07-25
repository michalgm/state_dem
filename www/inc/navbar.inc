	<nav id="navbar">
		<form id="graphoptions" class="container navlist">
			<input type="hidden" name="setupfile" value="StateDEM"/>
			<select name="state" id="state" class="navitem">
				<option value="AK">Alaska</option>
				<option value="CA" selected="selected">California</option>
				<option value="CO">Colorado</option>
				<option value="NE">Nebraska</option>
				<option value="NY">New York</option>
				<option value="ND">North Dakota</option>
				<option value="OH">Ohio</option>
				<option value="PA">Pennsylvania</option>
				<option value="TX">Texas</option>
			</select>
			<select name="chamber" id="chamber" class="navitem">
				<option value="state:upper">Senate</option>
				<option value="state:lower">Assembly/House</option>
			</select>
			<select name="cycle" id="cycle" class="navitem">
				<option>2012</option>
				<option selected="selected">2010</option>
				<option>2008</option>
			</select>
		</form>
		<a href="#" id="pull">Menu</a>
		<div id='searchfield'>
			<label for="node_search">Search</label> <input class="node_search" id="node_search" autocomplete="off" size="20" type="text" value="" />
			<div class="autocomplete node_search_list" id="node_search_list" style=""></div>
		</div>
	</nav>
