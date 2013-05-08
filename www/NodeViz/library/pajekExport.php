<?php

//takes a graph object and writes it as a pajek .net file

function createNet($graph){

//FIXME  added parameters making it possible to specify which
//attributes should be written as partition or vectors in the file

//setup an array of vertex ids to consecutive id numbers
$idLookup = array();
$idNum =1;
foreach ($graph['nodetypes'] as $nodetype){
	//for each node
	foreach ($graph['nodes'][$nodetype] as $node){
	//make sure it is not already on the list
	   if (!array_key_exists($node['id'], $idLookup)){
		   $idLookup[$node['id']] = $idNum; //id of node
		   $idNum++;
		} else {
			//warn about duplicate id
			echo("WARNING: duplicate ids ".$node['id']." when building pajek file, 2nd ignored\n");
		}
	}
}
//string to hold file data
$net = "Vertices    ".sizeof($idLookup)."\n";
//now loop over nodes to write them
foreach ($graph['nodetypes'] as $nodetype){
	//for each node
	foreach ($graph['nodes'][$nodetype] as $node){
	    $label = $node['label'];
	    if ($label == null){$label = $node['id'];}
	
		$net .= "   ".$idLookup[$node['id']]."  \"".$label.
				"\"   0.000  0.000 ic ".
				$node['color'].
				"  ".$node['shape'].
				"\n"; //id of node
	}
}
print_r($idLookup);

//now write edges data.  Assume they are directed, so write as arcs
$net .= "*Arcs \n";
//this will crash if there is an edge id not in node id
//for each edge type
foreach(array_keys($graph['edgetypes']) as $edgetype){
	//for each edge
	foreach($graph['edges'][$edgetype] as $edge ){
		//lookup the from and to id numbers
		$fromNum = $idLookup[$edge['fromId']];
		$toNum = $idLookup[$edge['toId']];
		if ($fromNum == null | $toNum == null){ //give a warning that this is a bad file
		   echo("ERROR building .net file: there is no node id (".$edge['fromId'].
		        " ".$edge['toId'].") for an id on edge id".$edge['id']."\n");
			return;
		}
		//write out the edge properties
		//FIXME add weight info
		$net .= "    ".$fromNum."    ".$toNum."    ".getWeightPJ($edge['weight'])." \n";
		//include more edge properties
	}
}

//add more graph properties as vectors and partitions
return ($net);	

}

//if no value is set, returns 1
//use funny name so it won't collide with others
function getWeightPJ($value){
  if ($value==null){
     return 1;
  }
  return $value;
}

?>