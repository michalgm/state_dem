<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1, maximum-scale=1">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	
	<title>Dirty Energy Money - States</title>

	<link rel='stylesheet' href="//ajax.googleapis.com/ajax/libs/jqueryui/1.7.2/themes/ui-lightness/jquery-ui.css"/>
	<link rel="stylesheet" href="/NodeViz/style.css">
	<link rel="stylesheet" href="/NodeViz/jquery.svg/jquery.svg.css">
	<link rel="stylesheet" href="/css/style.min.css"/>
	<style type="text/css">

		#svg_overlay .oselected polygon, #svg_overlay .oselected ellipse { fill: none; }
		#svg_overlay .selected polygon, #svg_overlay .selected ellipse { fill: none !important; }
		#svg_overlay path, #svg_overlay .edge polygon { stroke: none !important; fill: #9933ff !important; }		
		#svg_overlay .nhighlight, #svg_overlay .selected polygon.nhighlight, #svg_overlay .selected ellipse.nhighlight { fill: #ccbbdd !important; fill-opacity: 0.5 !important; }

		.REP, .R, .REPUBLICAN {color:#c66;}
		.DEM, .D, .DFL, .DEMOCRAT {color:#69c;}
		.IND, .I, .INDEPENDENT { color: #cc3; }
		.coal { color: rgb(149,141,99);}
		.oil { color: rgb(109,143,157); }
		.carbon { color: rgb(110,110,110); }
	
		svg .REP, svg .R, .REPUBLICAN {fill:#c66;}
		svg .DEM, svg .D, .DFL, .DEMOCRAT {fill:#69c;}
		svg .IND, svg .I, .INDEPENDENT { fill: #cc3; }
		svg .coal { fill: rgb(149,141,99);}
		svg .oil { fill: rgb(109,143,157); }
		svg .carbon { fill: rgb(110,110,110); }
			
		#masthead { z-index: 99; }
	
		#error { padding: 15px; margin-top: 170px; text-align: center; background: #eee; padding-top: 1; color: #c33; box-shadow: 0 0 1em rgba(0, 0, 0, 0.5); border: 1px solid #666; border-radius: 10px;}
		.errordetails { display: none; }
		#close_error { position: absolute; right: 5px; top: 5px; cursor: pointer;}
		.errorstring { background: transparent !important; }

		.zero { opacity: .5; }
		.average-line { fill: none; stroke: #999;  stroke-width: 5; stroke-dasharray:5;}

		#graphoptions { text-align: left !important; }
		#options_container { overflow: hidden; display: inline-block; margin-bottom: -3px;}
	</style>
</head>
<body>

<?php include 'inc/header.inc';?>
<?php include 'inc/navbar.inc';?>

	<div id="content" class="initial">

		<!-- Intro Screen -->

		<div id="masthead"><div class="container">
			<h1 class="site-title"><a href="/">Dirty Energy Money</a></h1>
			<p class="site-tagline">is an interactive tool that tracks the flow of oil, gas and coal industry contributions to your state legislature.</p>
		</div></div>

		<div id="intro_screen" class="container" style='display: none;'>
			<select id="intro_state">
				<option selected="selected" disabled="disabled">Select a state to begin</option>
			</select>
		</div>

		<!-- Main App -->
	
		<div id="lists-container">
			<div id="lists"></div>
			<a class="toggle"></a>
		</div>

		<div id="graphs-container">
			<div id="graphs"></div>
			<div id="legend"><div id="legend-text" class="container"></div></div>
			
			<div id="infocard" data-node="null" class="hide">
				<a class="close">&times;</a>
				<a class="more">&#9650;</a>
				<h3 id="node-title"></h3>
				<h4 id="node-amount"></h4>
				<div class="node-more hide">
					<div id="node-links">
						<a class="twitter" href="">Twitter</a>
						<a class="facebook" href="">Facebook</a>
						<a class="action" href="">Take Action</a>
					</div>
					<div id="node-barchart"></div>
					<div id="node-total"></div>
					<div id="node-csvlink"><a href="">Download data for this <span></span></a></div>
				</div>
			</div>
		</div>

		<!-- Overlays -->

		<div id='error' style='display: none;'>
			<img id='close_error' src='images/close.png'/>
		</div>

		<div id="about" class="page hide">
			<div class="page-close">&times;</div>
			<div class="container">
				<h2>About</h2>
				<p>The State Dirty Energy Money website provides an illustration of the network of funding relationships between fossil fuel companies and politicians. It shows which companies are dumping their dirty money into politics and which politicians are receiving it. We offer the best data available on contributions from the fossil fuel industry to decision-makers at the state level.</p>
				<p>You can use the interactive network map to explore our database of state-level campaign contribution relationships. We currently cover both houses of the state legislature (where applicable) in Alaska, California, Colorado, New York, Ohio, Pennsylvania, and Texas. The site is based on a robust database of contributions from fossil fuel industry employees and Political Action Committees (PACs) going back to 2006.</p>
				<p>Politicians and companies are positioned by their relationships, with their relative size reflecting the amount of money given or received. The size of the node and the tie indicate the strength of the relationship. (i.e. a company giving $15,000 to politicians will appear bigger than one giving $500). Think of it like a social networking site in which companies and politicians have become 'friends' by giving money.</p>
			</div>
		</div>

		<div id="methodology" class="page hide">
			<div class="page-close">&times;</div>
			<div class="container">
				<h2>Methodology</h2>
				<h3>How does the relationship map work?</h3>
		
				<p>We add up all the contributions from each filing and run this data through network visualization software to position the companies and politicians according to their ties. The size of the node (i.e. the circle with the company or the square image of a politician) and the strength of the tie (i.e. the line between actors) corresponds to the amount of money given or received. The nodes are then positioned according to the strength of their relationships.</p>

				<h3>Where did you get the data?</h3>

				<p>Anybody who runs for office is required to file reports with their state elections commission, detailing who they have accepted significant amounts of money from and in what amount.  We use data compiled by the National Institute on Money in State Politics (NIMSP) and the Sunlight Foundation which we further refine using our own custom-built software to match our specific expertise in the oil, gas and coal industries. We track all contributions from known employees and Political Action Committees (or PACs) from these companies back to 2006. We only look at the contributions that went to the selected group of elected officials (House/Assembly, Senate, etc.) while they were in office.</p>

				<p>We used tools, data, and information from the following sources:</p>

				<ul>
					<li>National Institute on Money in State Politics (NIMSP): categorized oil contributions and company names and provided assistance with data interpretation.</li>
					<li>Sunlight Foundation Projects:
						<ul>
							<li>Influence Explorer: provides bulk downloads of reformatted NIMSP data as well as an API for getting legislator information, including photo urls</li>
							<li>Open States API: provides additional legislator information, including lists of all members of state legislatures </li>
						</ul>
					</li>
					<li>Images of company logos were acquired from company websites without permission or authorization and may be under copyright of the respective companies. They are used here to refer to the companies and do not in any way indicate an endorsement or sponsorship of this project by any of these firms.</li>
				</ul>
			</div>
		</div>

	</div>

<?php include 'inc/footer.inc';?>

	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.js"></script>
	<script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.js"></script>
	<script src="js/hammer.js"></script>
	
	<script src="NodeViz/jquery.class.js" ></script>
	<script src="NodeViz/NodeViz.js" ></script>
	<script src="NodeViz/GraphImage.js" ></script>
	<script src="NodeViz/GraphSVG.js" ></script>
	<script src="NodeViz/GraphList.js" ></script>
	<script src="NodeViz/GraphRaster.js" ></script>
	<script src="NodeViz/jquery.svg/jquery.svg.js" ></script>
	<script src="NodeViz/jquery.svg/jquery.svgdom.js" ></script>
	<script src="NodeViz/jquery.svg/jquery.svganim.js" ></script>
	<script src="NodeViz/jquery.mousewheel.js" ></script>
	<script src="NodeViz/jquery.purl.js" ></script>
	<script src="NodeViz/jquery.ba-throttle-debounce.js" ></script>

	<script src="js/jquery.history.js"></script>
	<script src="js/d3.v3.js"></script>

	<script src="js/dem_charts.js"></script>
	<script src="js/main.js"></script>
	<script>
		var states = <?php 
			require("../config.php");
			echo json_encode(dbLookupArray("select * from states"));
		?>;
		<?= "var remotecache='$remotecache';" ?>
	</script>
</body>
</html>
