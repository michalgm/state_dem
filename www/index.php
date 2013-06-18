<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	
	<title>Dirty Energy Money - States</title>

	<link rel="stylesheet" href="/css/style.min.css"/>
	<link rel='stylesheet' href="//ajax.googleapis.com/ajax/libs/jqueryui/1.7.2/themes/ui-lightness/jquery-ui.css"/>
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

	<div id="infocard">
		<h3 id="node-title">{{TITLE}}</h3>
		<div id="node-image"><img src=""></div>
		<div id="node-barchart">{{BAR CHART}}</div>
		<div id="node-piechart1">{{PIE CHART 1}}</div>
		<div id="node-piechart2">{{PIE CHART 2}}</div>
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
	<script src="js/main.js"></script>
</body>
</html>