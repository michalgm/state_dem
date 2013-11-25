<?php

/**The Graph class is superclass that defines the abstract graph data structure and functions relating to elements of the graph data. This structure is used as a common denominator to move relational data and paramters around between the various parts of NodeViz. Most of the data is represented in a set of hierarchical arrays which makes it easy to convert to a JSON object when it is sent to the client.
<br><br>
To build a graph, the subclass must set properties to define the types of nodes and edges the graph will include.  It then must define functions corresponding to each type which can perform the operations necessary to assemble the relationship data and return it in array form to be included in the graph.  The these subclass functions are names <node_type>_fetchNodes(), <edge_type>_fetchEdges, <node_type>_nodeProperties() and <edge_type>_edgeProperties() and will be called during a graph assembly loop in loadGraphData().
<br><br>
For more information about configuring a GraphSetupFile subclass of Graph for your data, please check out the wiki documentation http://code.google.com/p/nodeviz/wiki/GraphSetupFile  and DemoGraph as an example.
*/
class Graph {

	var $data;
	var $name;

   /** The default constructor for the graph initializes the the graph data array. The array will have  empty sub-arrays for: <br/>
   	- nodetypes: gives names of types or "classes" of nodes 
   	- edgetypes: gives names of types or "classes" of edges, as well as the types of nodes they link 
   	- properties: lots of different kinds of graph properties and flags 
   	- nodes: arrays containing all the node objects (types mixed together)
   	- edges: arrays containing all the edge objects (types mixed together)
   	- subgraphs:  array containing any subgraph (cluster) declarations
   	- queries: optionally contains records of the queries used to construct a network for debugging<br/>
   	- nodetypesindex: indexing datastructure for looking up nodes by type
   	- edgetypesindex: indexing datastructure for looking up edges by type. <br/><br/>
   
   Subclasses of Graph should still call this super method if they override the constructor.
   
   @returns $this, an empty graph object, with arrays waiting eagerly to be filled with structure
   */
	function __construct() {
		//create empty graph structure;
		$data = array(
			'nodetypes'=> array(),
			'edgetypes'=> array(),
			'properties'=> array(),
			'nodes'=> array(),
			'edges'=> array(),
			'subgraphs' => array(),
			'queries'=> array(),
			'nodetypesindex'=> array(),
			'edgetypesindex'=> array(),
		);

		$this->width = 1;
		$this->height = 1;
		$this->prefix = null;
		$this->debug = 0;

		$this->data = $data;

		return $this;
	}


	/** Defines the graph's name if it isn't already set. Defaults to using a crc32() hash of the graph properties array, which are usually passed in by the request. When caching is enabled, graph files names are used to determine if the cached file can be returned, so you may want to overide this method in a subclass if you want to fine tune the caching. 
	@returns a string to be used as a graph file name.
	*/
	function graphname() {
		if (! $this->name) { 
			$this->name = crc32(serialize($this->data['properties']));
			//set the name
		}
		return $this->name;
	}


	/** Initializes the graph with any parameters past in by the http request. These will be added to the properties array of the graph.  Either creates a new graph using the loadGraph() function or loads it from cache on disk using the $graphname. If  width, height or prefix parameters are detected, they will be set in the graph.  Finally, this method will trigger any preProcessGraph() methods that have been defined in a subclass. This function is usually called by request.php.
	
	<br><em>NOTE: parameters are not escaped or checked for validity by default. You need to sanitize inputs before includeing them in a query!</em>
		@param $request_parameters  the php request parameters array
		@param $blank boolean, value of 0 means build graph from scratch, 1 means try to load from cache on disk.
		@returns $this, a reference to the graph object
	*/
	function setupGraph($request_parameters=array(), $blank=0) {
		global $nodeViz_config;
		$cache = $nodeViz_config['cache'];
		$datapath = $nodeViz_config['nodeViz_path'].'/'.$nodeViz_config['cache_path'];
		
		$this->input_parameters = $request_parameters;
		//Override defaults with input values if they exist
		foreach( array_keys($this->data['properties']) as $key) {
			if(isset($request_parameters[$key])) {
				//We can't call dbEscape - ain't part of framework - so be careful!
				//$this->data['properties'][$key]  = dbEscape($request_parameters[$key]);
				$this->data['properties'][$key]  = $request_parameters[$key];
			}
		}	
		if (isset($nodeViz_config['debug'])) {
			$this->debug = $nodeViz_config['debug'];
		}
		if (isset($request_parameters['graphWidth'])) {
			$this->width = $request_parameters['graphWidth'];
		}
		if (isset($request_parameters['graphHeight'])) {
			$this->height = $request_parameters['graphHeight'];
		}
		if (isset($request_parameters['prefix'])) {
			$this->prefix = $request_parameters['prefix'];
		}
		if (method_exists($this, 'preProcessGraph')) {
			$this->preProcessGraph();
		}	

		//Set the name
		$graphname = $this->graphname();

		if ($blank == 0 ) {
			//Either load graph data from cache, or use loadGraphData to load it
			if ($cache == 0 || ! is_readable("$datapath/$graphname/$graphname.graph")) { 
				$this->loadGraphData();
			} else { 
				if (is_readable("$datapath/$graphname/$graphname.graph")) {
					$data = unserialize(file_get_contents("$datapath/$graphname/$graphname.graph")); 
					$this->data = $data;
				} else {
					$this->data = "";
				}
			}
		}

		return $this;	
	}

	/** Fill in nodes and edges and their properties in the graph data structure. Works by searching for functions (implemented in a subclass) to return the appropriate data for each node type and edge type. Sets node and edge properties. Optionally trims isolate nodes.  Also calls any post processing functions if defined in a subclass. Called by setupGraph() For a more detailed explanation please see: http://code.google.com/p/nodeviz/wiki/GraphSetupFile
	*/
	function loadGraphData() {
		if ($this->debug) { $start = microtime(1); }

		writelog("before fetching nodes", 3);
		foreach ($this->data['nodetypes'] as $nodetype) {
			$function =  "$nodetype"."_fetchNodes";
			if(method_exists($this, $function)) {
				writelog("before $nodetype node fetch", 4);
				$nodes = $this->$function();
				writelog("after $nodetype node fetch", 4);
				$this->data['nodetypesindex'][$nodetype] = array_keys($nodes);
				foreach ($nodes as $node) { 
					if(isset($this->data['nodes'][$node['id']])) { trigger_error('Node id '.$node['id']." already exists", E_USER_ERROR); }
					$this->data['nodes'][$node['id']] = $node;
					$this->data['nodes'][$node['id']]['type'] = $nodetype;
				}
				$nodes = null;
			}
		}

		writelog("before fetching edges", 3);
		foreach (array_keys($this->data['edgetypes']) as $edgetype) {
			$function =  "$edgetype"."_fetchEdges";
			if(method_exists($this, $function)) {
				writelog("before $edgetype edge fetch", 4);
				$edges = $this->$function();
				writelog("after $edgetype edge fetch", 4);
				$this->data['edgetypesindex'][$edgetype] = array_keys($edges);
				foreach ($edges as $edge) { 
					if(! isset($edge['toId'])) { trigger_error("toId is not set for edge ".$edge['id'], E_USER_ERROR); }
					if(! isset($edge['fromId'])) { trigger_error("fromId is not set for edge ".$edge['id'], E_USER_ERROR); }
					if(isset($this->data['edges'][$edge['id']])) { trigger_error('Node id '.$edge['id']." already exists", E_USER_ERROR); }
					$this->data['edges'][$edge['id']] = $edge;
					$this->data['edges'][$edge['id']]['type'] = $edgetype;
				}
				$edges = null;
			}
		}

		$this->checkIsolates();
		writelog("before node properries", 3);

		foreach ($this->data['nodetypes'] as $nodetype) {
			$function =  "$nodetype"."_nodeProperties";
			if(method_exists($this, $function)) {
				$existing_nodes = $this->getNodesByType($nodetype);
				writelog("before fetching $nodetype node properties", 4);
				$nodes = $this->$function($existing_nodes);
				writelog("after fetching $nodetype node properties", 4);
				foreach (array_keys($existing_nodes) as $nodeid) { unset($this->data['nodes'][$nodeid]); }
				foreach ($nodes as $node) {
					$this->data['nodes'][$node['id']] = $node;
					$this->data['nodes'][$node['id']]['type'] = $nodetype;
					$this->data['nodes'][$node['id']]['relatedNodes'] = array();
				}
				$this->data['nodetypesindex'][$nodetype] = array_keys($nodes);
			}
		}

		$this->checkIsolates();

		writelog("before edge properries", 3);
		foreach (array_keys($this->data['edgetypes']) as $edgetype) {
			$function =  "$edgetype"."_edgeProperties";
			if(method_exists($this, $function)) {
				$existing_edges = $this->getEdgesByType($edgetype);
				writelog("before fetching $edgetype edge properties", 4);
				$edges = $this->$function($existing_edges);
				writelog("after fetching $edgetype edge properties", 4);
				foreach (array_keys($existing_edges) as $edgeid) { unset($this->data['edges'][$edgeid]); }
				foreach ($edges as $edge) {
					$this->data['edges'][$edge['id']] = $edge;
					$this->data['edges'][$edge['id']]['type'] = $edgetype;
				}
			}
			$this->data['edgetypesindex'][$edgetype] = array_keys($edges);
		}
		//should we check for edges linking to non-existant nodes?

		$this->checkIsolates();
	
		writelog("before related", 3);
		//Populate the related nodes fields for each node by stepping through the edges
		foreach ($this->data['edges'] as $edge) {
			//delete edges linking to non-existent nodes;
			if (! isset($this->data['nodes'][$edge['toId']]) || ! isset($this->data['nodes'][$edge['fromId']])) {
				unset($this->data['edges'][$edge['id']]);
				continue;
			}
			if (! $edge['toId']) { print 'none!'; print_r($edge); } 
			if ($this->data['nodes'][$edge['toId']]) {
				$this->data['nodes'][$edge['toId']]['relatedNodes'][$edge['fromId']][] = $edge['id'];
			}
			if ($this->data['nodes'][$edge['fromId']]) {
				$this->data['nodes'][$edge['fromId']]['relatedNodes'][$edge['toId']][] = $edge['id'];
			}
		}

		//load subgraphs if subgraph method is defined
		if (method_exists($this, 'getSubgraphs')) {
			$this->getSubgraphs();
		}	
		if ($this->debug) { $this->data['properties']['time'] = microtime(1) - $start; }	
		if (method_exists($this, "postProcessGraph")) { 
			$this->postProcessGraph();
		}
	}

	
	#-------------------------------------------------

	/**Utility function to scale the 'size' property of graph elements to a data value. Takes graph object, entity type, and key of entity to use for scaled values. Size parameters of nodes are set so that the area of the node will be proportional to the value. The maximum and minimum sizes that the elements will be scaled to are controlled by the graph's maxSize and minSize properties for each type. Scales slightly differently if it is a square or circle shape. 
		@param $array an indexed array of either nodes or edges which will be scaled relative to one another
		@param $type a string giving the type of the nodes that should be scaled
		@param $key a string giving the name of the property with the value to be scaled to
		@returns an array ... of node properties?
	*/
	function scaleSizes($array, $type, $key){
		$graph = $this->data;
		$maxSize = $graph['properties']['maxSize'][$type];
		$minSize = $graph['properties']['minSize'][$type];
		if (isset($graph['properties']['log_scaling'])) {
			$log = $graph['properties']['log_scaling'];
		} else {
			$log = 0;
		}

		$vals = array();
		
		reset($array);
		if (key($array)) { 
			$scale = $maxSize - $minSize;  //the range we actually want
			//load all the cash into an array
			foreach($array as $node) { 
				if ($type && $node['type'] != $type) { continue; }
				$vals[] =  $node[$key]; 
			}
			//This code was for reseting values < 0 to zero
			/*foreach($array as $node) { 
				if ($node[$key] > 0) { 
					$vals[] =  $node[$key];
				} else { $vals[] = 0; }
		   	}
			*/
			$min = min($vals);
			$max = max($vals);
			$adj_min = $min + abs($min)+1;
			$adj_max = $max + abs($min)+1;
			if ($log) {
				$diff = log($adj_max) - log($adj_min);  //figure out the data range
			} else {
				$diff = $max - $min;  //figure out the data range
			}
			foreach(array_keys($array) as $id) {
				if ($type && $array[$id]['type'] != $type) { continue; }
				if(isset($array[$id]['fromId'])) {
					$shape = 'edge';
				} else {
					$shape = $array[$id]['shape'];
				}
				$value = $array[$id][$key];
				//This code was for reseting values < 0 to zero
				//if ($value <= $min) { $normed = 0; } 
				if ($diff == 0) {
					$normed = 1;
				} else {	
					if ($log) { 
						$normed = (log($value+abs($min)+1) - log($adj_min)) / $diff; //normalize it to the range 0-1
					} else {
						$normed = ($value - $min) / $diff; //normalize it to the range 0-1
					}
				}
				//print "$value $min $diff $normed $scale\n";
				$area = $normed*$scale + $minSize;
				//now calculate appropriate with from area depending on shape
				if ($shape == 'edge') { 
					//$size = $area;  //adjust to value we want
					$array[$id]['penwidth'] = $area;
				} else {
					if ($shape == 'circle' || $shape == 'octagon' || $shape == 'polygon') { 
						//$area = ($normed * $scale) + pow($minSize,2)*pi();  //adjust to value we want
						$size = sqrt(abs($area)/pi())*2;  //get radius and multiple by 2 for diameter
					} else if ($shape == 'triangle') { 
						//$area = ($normed * $scale) + (sqrt(3)*pow($minSize,2))/4;  //adjust to value we want
						//$size = sqrt(abs($area)) / (sqrt(3)/4);
						$size = sqrt((4*abs($area)) / (sqrt(3)));
					} else if ($shape == 'diamond') { 
						//$area = ($normed * $scale) + pow($minSize,2)/2;
						$size = sqrt(pow(sqrt(abs($area)),2)*2);
						#$scale = sqrt(pow($maxSize,2)/2) - sqrt(pow($minSize,2)/2);
					} else {
						//$area = ($normed * $scale) + pow($minSize,2);  //adjust to value we want
						$size = sqrt(abs($area));
					}
					$array[$id]['scaled_area']	= $area;
					$array[$id]['width'] = $size;
				}
			}
		}

		//reorder the values for debugging
		$array = $this->subval_sort($array, $key);
		return $array;
	}

	function subval_sort($a,$subkey) {
		if (count($a) == 0) { return $a; }
		foreach($a as $k=>$v) {
			$b[$k] = strtolower($v[$subkey]);
		}
		asort($b);
		foreach($b as $key=>$val) {
			$c[$key] = $a[$key];
		}
		return $c;
	}	

	/** Returns the node data array corresponding to an id
		@param $id the string id of the node to fetch
	*/
	function lookupNodeID($id) {
		$graph = $this->data;
		if (isset($graph['nodes'][$id])) { 
			return $graph['nodes'][$id];
		}
		if (isset($graph['edges'][$id])) { 
			return $graph['edges'][$id];
		}
	}

    /** Does crude checks that the graph object is valid enough to be returned to the browser.  Currently calls trigger_error() if the graph is blank (perhaps indicating a cache problem) or the graph contains no nodes.
    
    */
	function checkGraph() {
		$graph = $this->data;
		if ($graph == "") {
			 trigger_error("We're sorry. The files needed to display these options are missing. Please contact the site administrator.", E_USER_ERROR);
		}
		if ($graph['nodes'] == "" || sizeof($graph['nodes']) == 0 || gettype(current($graph['nodes'])) != 'array') {
			 trigger_error('These options return no relationship.', E_USER_ERROR);
		}
	}


	/** Adds a query string as an data property on the graph for debugging. This is for record-keeping purposes when dealing with complex parameters and queries, this is not where queries are added to construct the graph.
		@param $name a label for the query, e.g. 'my_node_id_query'
		@param $query the query string, e.g. 'SELECT id FROM myTable ...'
	*/
	function addquery($name, $query) {
		if ($this->debug) { $this->data['queries'][$name] = $query; }	
	}


   /** If the removeIsolates graph property is set, it this function will removed any isolate (unconnected) nodes.  Called several times during graph construction as node, edges, and properties are being added. Useful in case query mismatch creates cruft nodes.
   */

	function checkIsolates() {
		//Get rid of any isolated nodes if retainIsolates is set to 0
		if (isset($this->data['properties']['removeIsolates'])) {
			foreach (array_keys($this->data['nodes']) as $id) {
				$has_edges = 0;
				foreach (array_keys($this->data['edges']) as $edgeid) {
					//if (! isset($this->data['edges'][$edgeid]['toId'])) { print_r($this->data['edges'][$edgeid]); print $edgeid;}
					if ($this->data['edges'][$edgeid]['toId'] == $id || $this->data['edges'][$edgeid]['fromId'] == $id) { 
						$has_edges = 1; 
						continue;
					}
				}
				if (! $has_edges) { 
					//This node is not associated with any edges, so we remove it from the nodes and nodetypesindex arrays
					$nodetype = $this->data['nodes'][$id]['type'];
					unset($this->data['nodes'][$id]); 
					$index = array_search($id, $this->data['nodetypesindex'][$nodetype]);
					if (isset($index)) {
						unset($this->data['nodetypesindex'][$nodetype][$index]);
					}
				}
			}
		}
	}

   /** Returns an array containing the ids of all the nodes that have the given type.  Used as a shortcut for looping over the nodetypesindex. 
   	@param $type string giving the name of the type of nodes to be returned. 
   */
	function getNodesByType($type) {
		$nodes = array();
		foreach($this->data['nodetypesindex'][$type] as $id) {
			$nodes[$id] = $this->data['nodes'][$id];
		}
		return $nodes;	
	}
	
	/** Returns an array containing the ids of all the edges that have the given type. Used as a shortcut for looping over the edgetypesindex. 
		@param $type string giving the name of the type of edges to be returned. 
	*/
	function getEdgesByType($type) {
		$edges = array();
		foreach($this->data['edgetypesindex'][$type] as $id) {
			$edges[$id] = $this->data['edges'][$id];
		}
		return $edges;	
	}

	function ajax_edgeDetails() {
		global $_REQUEST;
		$props = $this->data['properties'];
		$edge = $this->data['edges'][$props['edgeid']];
		$output = "<ul>";
		foreach (array_keys($edge) as $key) {
			$output .= "<li><span style='font-weight: bold;'>".ucwords($key)."</span> - $edge[$key]";
		}
		$output .= "</ul>";
		return $output;
	}
}
