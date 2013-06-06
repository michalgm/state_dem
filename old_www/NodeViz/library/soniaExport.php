<?php
//export graph into sonia format

//scale factor to convert sizes?
//sonia measures in pixels, so for now we assume the units are inches
$SCALE = 72;

function createSon($graph){
 global $SCALE;

	//make a string to contain the .son file
	$son = "//export from graph building code\n";
	//figure out what properties the nodes will have
	
	//write out the node headers
	$son .= "AlphaId\t".
			"Label\t".
			"NodeShape\t".
			"NodeSize\t".
			//"ColorName\t".
			//"BorderColor\t".
			//"LabelColor\t".
			//"X\t".
			//"Y\t".
			//"IconURL".
			//"StartTime\t".
			//"EndTime\n";
			"NodeType".  //user data for node type
			"\n";
			//should check for other attibutes and warn that they are not used or append as more columns
			
	//loop over nodes
	foreach ($graph['nodetypes'] as $nodetype){
	//for each node
		foreach ($graph['nodes'][$nodetype] as $node){
			//format the the string.  
			$son .= $node['id']."\t"; //id of node
			//if no label, use id as label
			if ($node['label'] == null) {
				$son .= $node['id']."\t"; 
			} else {
				$son .= $node['label']."\t"; 
			}
			//need to translate shape names?
			$son .= convertShapes($node['shape'])."\t";
			$son .= ($node['size'] * $SCALE)."\t";
			//$son .= $node['color']."\t";
			$son .= $nodetype."\t";
			$son.="\n";
	}
}
	//write out the edge headers
	$son.= "FromId\t".
			"ToId\t".
			"Weight\t".
			//"ColorName\t".
			"Width\t".
			"StartTime\t".
			"EndTime".
			"\n";
	//loop over edges
	foreach(array_keys($graph['edgetypes']) as $edgetype){
	//for each edge
		foreach($graph['edges'][$edgetype] as $edge ){
			//format the string
			$son .= $edge['fromId']."\t".$edge['toId']."\t".
					getWeightSN($edge['weight'])."\t".
					getWeightSN($edge['weight'])."\t".
					getWeightSN($edge['decyear'])."\t".
					getWeightSN($edge['decyear'])."\t".
					"\n";
			
			//also add properties
		}
	}
	//return the string
	return $son;
	
}

function convertShapes($shapeString){
	if ($shapeString == "box"){ return "square";}
	if ($shapeString == "square") {return "square";}
	if ($shapeString == "square") {return "square";}
	if ($shapeString == "rect") {return "square";}
	if ($shapeString == "rectangle") {return "square";}
	return "circle";
}

//if no value is set, returns 1
//use funny name 'cause method is not private
function getWeightSN($value){
  if ($value==null){
     return 1;
  }
  return $value;
}

?>