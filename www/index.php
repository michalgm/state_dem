<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	
	<title>Dirty Energy Money - States</title>

	<link rel="stylesheet" href="css/style.min.css"/>
	<link rel='stylesheet' href="//ajax.googleapis.com/ajax/libs/jqueryui/1.7.2/themes/ui-lightness/jquery-ui.css"/>
	<style type="text/css">

		#svg_overlay .oselected polygon, #svg_overlay .oselected ellipse { fill: none; }
		#svg_overlay .selected polygon, #svg_overlay .selected ellipse { fill: none !important; }
		#svg_overlay path, #svg_overlay .edge polygon { stroke: none !important; }		
		#svg_overlay .nhighlight, #svg_overlay .selected polygon.nhighlight, #svg_overlay .selected ellipse.nhighlight { fill: #ccbbdd !important; fill-opacity: 0.5 !important; }

		.REP, .R {color:#c66;}
		.DEM, .D, .DFL {color:#69c;}
		.IND, .I { color: #cc3; }
		.coal { color: rgb(149,141,99);}
		.oil { color: rgb(109,143,157); }
		.carbon { color: rgb(110,110,110); }

		#masthead { z-index: 99; }
		
		#navbar #searchfield { position: relative; float: right; padding: 8px; border: none; }
		#searchfield .autocomplete {
			position: absolute;
			left: 50%;
			font-size: .8em;
			margin: 0 0 0 -90px; padding: 0;
			z-index: 200;
			width: 180px;
			background: #fff;
			box-shadow: none;
			border: none;
		}
		#searchfield .autocomplete ul {
			list-style: none;
			margin: 0; padding: 0;
			border-radius: 5px;
			z-index: 200;
			border: 1px solid #ccc;
		}
		#searchfield .autocomplete ul li {
			list-style-type:none;
			display:block;
			margin: 0; padding: 0;
			cursor:pointer;
			float: none;
			width: 100%;
			text-align: left;
			border: none;
		}
		#searchfield .autocomplete ul li a { 
			display: block; 
			padding: 5px 5px 5px 25px;
			text-align: left;
			border: none;
			line-height: 2em;
			background-position: 5px 50%;
			background-repeat: no-repeat;
		}
		#searchfield .autocomplete ul li a.ui-state-focus { background-color: #ccbbdd; background-repeat: no-repeat; background-position: 5px 50%;}
		#searchfield .ui-autocomplete .ui-state-focus { background: none; border:none; color: inherit; padding: none; }
		#searchfield .autocomplete ul li.politician a { background-image: url(images/politician.png); }
		#searchfield .autocomplete ul li.company a { background-image: url(images/company.png); }
		#searchfield .autocomplete .bold { font-weight: bold; }
		#searchfield .autocomplete .searchdetails { font-size: .8em; }
		#searchfield .autocomplete_img  { position: absolute; margin-left: 10px; }

	</style>
</head>
<body>

<?php include 'inc/header.inc';?>
<?php include 'inc/navbar.inc';?>

	<div id="masthead" class="container">
		<h1 class="site-title"><a href="/">Dirty Energy Money</a></h1>
		<p class="site-tagline">is an interactive tool that tracks the flow of oil, gas and coal industry contributions to the US Congress.</p>
	</div><!-- #masthead -->
	
	<!-- <div id="lists"></div> -->
	<div id="graphs"></div>

	<div id="infocard" data-node="null">
		<a class="close">&times;</a>
		<a class="more">&#9650;</a>
		<h3 id="node-title"></h3>
		<h4 id="node-amount"></h4>
		<div class="node-more">
			<div id="node-barchart"></div>
			<div id="node-piechart"></div>
			<div id="node-csvlink"><a href="">Download .CSV</a></div>
		</div>
	</div>

<?php include 'inc/footer.inc';?>

	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.js"></script>
	<script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/jquery-ui.js"></script>
	
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

	<script src="js/d3.v3.js"></script>

	<script src="js/main.js"></script>
</body>
</html>
