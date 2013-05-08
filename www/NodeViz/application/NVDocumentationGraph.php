<?php
include_once('Graph.php');

/*
This example shows constructing a simple graph using built in arrays.  Its actually a bit hard, because
*/
class NVDocumentationGraph extends Graph { 

	function __construct() {
		parent::__construct();
			
		// gives the classes of nodes
		$this->data['nodetypes'] = array('classes'); //FIXME - add properties for types
		
	  	// gives the classes of edges, and which nodes types they will connect
		$this->data['edgetypes'] = array( 'flows' => array('classes', 'classes'));
		
		// graph level properties
		$this->data['properties'] = array(
			//sets the scaling of the elements in the gui
			'minSize' => array('classes' => '1',  'flows'=>'10'),
			'maxSize' => array('classes' => '3',  'flows' =>'40'),

			'log_scaling' => 0

		);
		// special properties for controling layout software
		//BROKEN, USE GV PARAMS
		$this->data['graphvizProperties'] = array(
			'graph'=> array(
				'bgcolor'=>'#FFFFFF',
#				'size' => '6.52,6.52!',
				'fontsize'=>90,
				'splines'=>'true',  //use edges that can curve around nodes
				'fontcolor'=>'blue',
				'layoutEngine'=>'dot', //ask to render with hierarchical layout
				'rankdir' => 'LR' //change direction which lodes are layout out across page
			),
			//Graphviz default properties for nodes and edges
			'node'=> array('style'=> 'filled',  'fontsize'=>12),
			'edge'=>array('arrowhead'=>'normal', 'color'=>'#99339966', 'fontsize'=>50,'penwidth'=>5)
		);
	}

	/**
	There must be a  <nodetype_fetchNodes() function for each defined node type.  
	It returns the ids of the nodes for the type. 
	**/

	function classes_fetchNodes() {
		$classes = array('client web page','Graph.js','request.php','graphSetupFile.php','Graph.php','GraphVizExporter.php','GraphViz process','Database','SVG image of graph','JSON data of graph');
		$graph = &$this->data;
		$nodes = array();
		foreach ($classes as $id) {
			$nodes[$id] = array('id'=>$id);
		}
		return $nodes;
	}

	

	function classes_nodeProperties() {
		$nodes = $this->getNodesByType('classes');
		foreach ($nodes as &$node) {
			$node['label'] = $node['id'];
			$node['tooltip'] = $node['id'];
			$node['click'] = "this.selectNode('".$node['id']."'); this.panToNode('".$node['id']."');";
		}
		
		return $nodes;	
	}

	

	function flows_fetchEdges() {
		
		//an array of relationships used to build edges
		$flows = array(
			array('client web page','Graph.js'),
			array('Graph.js','request.php'),
			array('request.php','graphSetupFile.php'),
			array('graphSetupFile.php','Database'),
			array('Database','graphSetupFile.php'),
			array('graphSetupFile.php','Graph.php'),
			array('Graph.php','GraphVizExporter.php'),
			array('GraphVizExporter.php','GraphViz process'),
			array('Graph.php','JSON data of graph'),
			array('JSON data of graph','request.php'),
			array('SVG image of graph','request.php'),
			array('request.php','Graph.js'),
			array('Graph.js','client web page'),
			array('GraphViz process','SVG image of graph')
			
			
		);
		
		$graph = &$this->data;

		$edges = array();
		
		foreach($flows as $edge){
				$id = $edge[0]."_".$edge[1];
				$edges[$id] = array('id'=> $id,'fromId' => $edge[0],'toId' => $edge[1]);
		}
		
		//$edges[0] = array('id' => '0','fromId' => 'Client Browser','toId' => 'Graph.js');

		return $edges;
	}
	
	function flows_edgeProperties() {
		$edges = $this->getEdgesByType('flows');
		
		//assuming array is the same order, probably not safe and very hard to keep in order with edges array when writing by hand
		$labels = array(
			'loads and instantiates',
			'makes AJAX request with graph parameters to',
			'checks graph type and loads',
			'converts graph parameters to SQL query',
			'returns lists of nodes and edges and their properties',
			'assembles and checks',
			'is sent to',
			'exports graph in .dot format to be sent to',
			'is written out as',
			'is (optionally) cached to disk and returned to',
			'is tweaked, (optionally) cached to disk and returned to',
			'replies to AJAX request with graph JSON and SVG data',
			'inserts SVG and JavaScript events',
			'computes positions of nodes and edges and renders out',
		);
		
		$l=0;
		foreach ($edges as &$edge){
			//add select event for click
			$edge['click']="this.selectEdge('".$edge['id']."')";
			$l=$l+1;
		}	
		return $edges;
	}
	
	

}



