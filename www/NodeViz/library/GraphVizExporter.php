<?php

//require('libgv-php5/gv.php');

/**
Interpreter file to take a Graph data structure and convert into a ".dot" formatted file than can be passed to GraphViz.  Manages launching and running GraphVis program to do the graph layout. Also includes functions to clean up SVG and imagemap files.
*/
class GraphVizExporter {
	
	/**Global vars that allow tweaking settings of sections in dot file and setting how
neato will run when it reads the file.  See http://www.graphviz.org/doc/info/attrs.html
for list of params and dfns. Used as default values but can be overridden in Graph setup files. 
*/
	function __construct($graph, $returnSVG=1, $rasterFormat='jpg') {
		global $nodeViz_config;
		$GV_PARAMS = array(
			'graph' => array(
				'outputorder' => 'edgesfirst',  /*! draw edges before drawing nodes !*/
				'truecolor' => 'true',
				//'maxiter' => '10000', //turning this off speeds things up, but does it mean that some might not converge?
				'size' => '9,9!',
				'dpi' => '96',
				//'sep=' => '0.2',
				'bgcolor' => 'transparent',
				'splines' => '1',
				'epsilon'=>'0.0',
				'layoutEngine'=>'neato',
				#'ratio'=>'fill'
			),
			'node' => array(
				'style' => 'setlinewidth(16), filled',
				'fontsize' => '10',
				'fixedsize' => 'true', 
				'label'=>' ',
				'imagescale'=>'true'
			),
			'edge' => array(
				'len' => '8',
				'style'=>'setlinewidth(2)',
				'labelfloat' => 'true'
			)
		);

		$this->GV_PARAMS = $GV_PARAMS;
		$this->graph = $graph;
		$this->rasterFormat = $rasterFormat;
		$this->returnSVG = $returnSVG;
		$this->cachePath = $nodeViz_config['cache_path'];
		$this->graphPath = $this->cachePath."/".$this->graph->graphname().'/';
		$this->renderPath = $this->graphPath."/".$this->graph->width."_".$this->graph->height.'/';
		$this->graphFileName = $this->graphPath."/".$this->graph->graphname();
		$this->renderFileName = $this->renderPath."/".$this->graph->graphname();
		$this->cacheLevel = $nodeViz_config['cache'];
		$this->dotString = null;
		$this->svgString = null;
		$this->imapString = null;

		return $this;
	}

	/**  Loops through the passed graph structure and writes it to a text string in the .dot file format. All graph data will be written, even if it is not a valid GraphViz paramters.  When some important GraphViz params are not included, it uses the default values in $GV_PARAMS.
		@param $graph  the graph to be converted
		@returns $dot a string in .dot format ready to be written out
	
	*/
	protected function renderDot(){ //add more arguments to control dot file creation
		$graph = $this->graph;

		//Set the size if it was passed in
		if (isset($graph->width)) { 
			$size = ($graph->width/96).','.($graph->height/96)."!";
			$graph->data['graphvizProperties']['graph']['size'] = $size;
		}

		$graph->data['graphvizProperties']['graph']['size'] = "1000,1000!";

		//Merge any properties set in graphSetup into GV_PARAMS
		if (isset($graph->data['graphvizProperties'])) {
			$this->GV_PARAMS = array_merge_recursive_unique($this->GV_PARAMS, $graph->data['graphvizProperties']);
		} 

		$dot = "digraph G {\ngraph ["; 
		//get the graph-level params from the params object
		foreach (array_keys($this->GV_PARAMS['graph']) as $key) {
			if ($this->GV_PARAMS['graph'][$key] != null){
				$dot .= $key.'="'.$this->GV_PARAMS['graph'][$key].'" ';
			}
		}
		$dot .="];\n";

		

		//default formatting for nodes
		$dot .= "node [";
		//get the node-level params from the params object
		foreach (array_keys($this->GV_PARAMS['node']) as $key) {
			if ($this->GV_PARAMS['node'][$key] != null){
				$dot .= $key.'="'.$this->GV_PARAMS['node'][$key].'" ';
			}
		}
		$dot .= "];\n";

		//for each node
		foreach ($graph->data['nodes'] as $node){
			//We should probably log a warning if it has no size
			//if(! isset($node['size'])) { print_r($node); }
			//format the the string.  
			$dot .= "\"".$node['id'].'" ['; //id of node
			//write out properties
			if (isset($node['size'])) { 
				$dot .= 'width="'.$node['size'].'" ';
			}
			$dot .= 'href="a" ';
			if (! isset($node['target'])) { $node['target'] = $node['id']; }
			if (isset($node['label'])) { $dot .= 'label="'.$node['label'].'" '; }
			//Write out all other node properties (color, shape, onClick)
			foreach(array_keys($node) as $key) { 
				if (! in_array($key, array('size', 'label', 'relatedNodes'))) { //skip keys that we have to convert
					$dot .= "$key=\"".$node[$key].'" ';
				}
			}	
			$dot .= "];\n"; 
		//FIXME MOVE LOGO CODE TO GRAPHBUILDER

		}

		//default properties for edges
		$dot .= "edge [";
		//get the edge-level params from the params object
		foreach (array_keys($this->GV_PARAMS['edge']) as $key) {
			if ($this->GV_PARAMS['edge'][$key] != null){
				$dot .= $key.'="'.$this->GV_PARAMS['edge'][$key].'" ';
			}
		}
		$dot .="];\n";

		//for each edge
		foreach($graph->data['edges'] as $edge ){
			//format the string
			$dot .= '"'.$edge['fromId'].'" -> "'.$edge['toId']."\" [".
			'href="a" ';
			if (isset($edge['weight'])) {
				$dot .= 'weight="'.GraphVizExporter::getWeightGV($edge['weight']).'" ';
			}
			if(isset($edge['size'])) {
				$dot .= 'style="setlinewidth('.$edge['size'].')" ';
				if (isset($edge['arrowhead']) && $edge['arrowhead'] != 'none' && $this->GV_PARAMS['edge']['arrowhead'] != 'none') { 
					$dot .= 'arrowsize="'. ($edge['size']*5).'" ';
				}
			}
			if (! isset($edge['target'])) { $edge['target'] = $edge['id']; }
			//Write out all other node properties (color, shape, onClick)
			foreach(array_keys($edge) as $key) { 
				if (! in_array($key, array('href', 'weight', 'size'))) { //skip keys that we have to convert
					$dot .= "$key=\"".$edge[$key].'" ';
				}
			}
			$dot .= "];\n";
	
			//also add properties
		}
		
		//hack test subgraph function
		foreach (array_keys($graph->data['subgraphs']) as $sg_name){
			$dot .= "subgraph $sg_name {\n";
			$subgraph = $graph->data['subgraphs'][$sg_name];
			//add any properties
			if (isset($subgraph['properties'])){
				foreach (array_keys($subgraph['properties']) as $prop){
					$dot .= $prop."=".$subgraph['properties'][$prop].";\n";
				}
			}
			if (isset($subgraph['nodes'])){
				foreach ($subgraph['nodes'] as $node){
					$dot .= "$node;\n";
				}
			}
			$dot .= "}\n";
		}
		
		//terminate dot file
		$dot .= "}\n";

		//remove all newlines from dot, so GV doesn't choke
		$dot = str_replace("\n", " ", $dot);
		$this->dotString = $dot;
	}
	
	protected function processDot() {
		global $nodeViz_config;
		if ($nodeViz_config['debug']) { 
			$nicegraphfile = fopen($this->graphFileName.".nicegraph", "w");
			fwrite($nicegraphfile, print_r($this->graph, 1));
			fclose($nicegraphfile);
			$origdot = fopen($this->graphFileName."_orig.dot", "w");
			fwrite($origdot, $this->dotString);
			fclose($origdot);
		}
		$data = &$this->graph->data;
		if ($this->cacheLevel == 0 || ! file_exists($this->graphFileName.".graph")) {
			$graphfile = fopen($this->graphFileName.".graph", "w");
			fwrite($graphfile, serialize($data));
			fclose($graphfile);
		}

	}
	//if no value is set, returns 1
	//have to use funny names 'cause we can't declare as protected
	static function getWeightGV($value){
	  if ($value==null){
		 return 1;
	  }
	  return $value;
	}

	/**
	Manage the export of the Graph object, piping the .dot file to GraphViz to compute layouts, and cacheing images,etc. First creates graph file using createDot().  Uses neato network layout by default, other graphviz layouts can be specified iwht the layoutEngine parameter. Uses the Graph's graphname() function for the base of the filename. Normally writes out a .dot, .svg .png and .imap versions of the network.  If debugMode is set, it will also write out a human-readable dot file with a suffix .nicegraph.  Post-processes the .imap, .svg and ? file using the fuctions processImap(), processSVG() and processGraphData().
	@param $graph the Graph object to be exported
	@param $datapath the data working directory that the files should be witten to
	@param $format  string giving suffix for other image format to save graph images as. (i.e. ".jpg")
	@returns an array with $imap and $svg elements
	*/
	public function generateGraphFiles() {

		if ($this->cacheLevel < 2 || ! file_exists($this->graphFileName.".svg")) {
			$this->renderDot();
			$this->processDot();
			$this->renderGraphViz();
			$this->processImap();
			$this->processSVG();
			#chmod all our files
			foreach (array('.svg', '.svg.raw', '.dot', '.graph', '.nicegraph', '_orig.dot', '.imap') as $ext) {
				$this->setPermissions($this->graphFileName.$ext);
			}	
			$this->setPermissions($this->graphPath);
		} else {
			$this->svgString = file_get_contents($this->graphFileName.".svg");
			$this->imapString = file_get_contents($this->graphFileName.".imap");
		}

		if ($this->cacheLevel < 3) { 
			$this->processGraphData();
			$this->renderSVG();
			$this->renderImap();
			$this->renderRaster();
			foreach (array('.svg', '.imap', ".".$this->rasterFormat) as $ext) {
				$this->setPermissions($this->renderFileName.$ext);
			}	
			$this->setPermissions($this->renderPath);
		}
	}

	protected function setPermissions($file) {
		if (is_file($file) || is_dir($file)) {
			$perms = fileperms($file);
			if (decoct(fileperms($file)) != '100777') { 
				chmod($file, 0777) || trigger_error("can't chmod file: $file: ".decoct(fileperms($file)), E_USER_ERROR);
			}
		}
	}

	/**
	Determines if the network image files need to be generated or loaded from cache, and loads information into arrays to be returned to client. Contents of image and overly change depending if the browser making the request set useSVG request parameter.
	@param $graph  the Graph object, to be returned as JSON. 
	@param $datapath string giving the path to the cache directory
	@param $format  string indicating if it will be returning svg or png image for network?
	@param $returnsvg NOT USED?  seems to read request param instead.
	@returns an array with elements for the 'image', 'graph', 'overlay', and 'dot' data.
	*/
	public function export() {
		global $nodeViz_config;
		$graphname = $this->graph->graphname();
		$datapath = $this->cachePath;
		$this->checkPaths();
		
		//either write files to the cache, or load in the cached files
		$this->generateGraphFiles();

		if ($this->returnSVG) { 
			$overlay = "<div id='svg_overlay' style='display: none'>".$this->svgString."</div>";
			$format = 'svg';
		} else {
			$overlay = $this->imapString;
			$format = $this->rasterFormat;
		}

		$imagepath = preg_replace("|^".$nodeViz_config['web_path']."|", "", $this->renderFileName);
		$dotpath = preg_replace("|^".$nodeViz_config['web_path']."|", "", $this->graphFileName);
		$image = "$imagepath.$format";
		if ($nodeViz_config['debug']) { srand(); $image .= "?".(rand()%1000); } //append random # to image name to prevent browser caching
		$dot = "$dotpath.dot";
		return array('image'=>$image, 'graph'=>$this->graph, 'overlay'=>$overlay, 'dot'=>$dot);
	}

	protected function processImap() {
		$imap = file_get_contents($this->graphFileName.".imap");
		$imap = str_replace('<map id="G" name="G">', "", $imap);
		$imap = str_replace("</map>", "", $imap);
		$imap = preg_replace("/ (target|title|href|alt)=\"[^\"]*\"/", "", $imap);
		$imap = "<map id='G' name='G'>".join("\n", array_reverse(explode("\n", $imap)))."</map>";
		$imapfile = fopen ($this->graphFileName.".imap", 'w');
		fwrite($imapfile, $imap);
		fclose($imapfile);
		$this->imapString = $imap;
	}


	/**
		Modifies the SVG rendering of the network produced by GraphViz to be ready to insert into the XHTML document, tweaks some elements to work better in the NodeViz interactive display. Writes out the new SVG file and (when not in debug mode) deltes the old SVG file.  Key changes to SVG are:
			- adding a large 'screen' which will be used to hide the graph when certain elements are hilited
			- changing the id of the svg element
			- removing svg document header
			- removing title tags
			- adding a zoom_level class to text elements to work with the css zooming
			- rewriting image paths from local to web paths
		@param $svgFile string with the name of the file containing the SVG content
		@param $datapath string with the location of the cache directory where files are location
		@param $graph the Graph object corresponding to the SVG image.
		@returns $svg string with modified SVG content
	*/
	protected function processSVG() {
		global $old_graphviz;
		global $nodeViz_config;
	
		#clean up the raw svg
		$svg = file_get_contents($this->graphFileName.".svg.raw");
		if ($old_graphviz) {
			$svg = preg_replace("/<!-- ([^ ]+) -->\n<g id=\"(node|edge)\d+\"/", "<g id=\"$1\"", $svg);
			$svg = preg_replace("/^.*fill:(black|white).*\n/m", "", $svg);
			$svg = str_replace("<polygon style=\"fill:#ffffff", "<polygon id='svgscreen' style=\"fill:#ffffff; opacity:0", $svg);
		} else {
			function shiftlabels($matches) { 
				return 'rx="'.$matches[1].'"'.$matches[2].'start'.$matches[3].(($matches[1])+$matches[4]+15).'"';
			}
			$svg = preg_replace_callback("/rx=\"([^\"]+)\"([^<]+<text text-anchor=\")[^\"]+(\" x=\")([^\"]+)\"/", "shiftlabels", $svg);
			#$svg = str_replace("font-size=\"10.00\"", "font-size=\"16.00\"", $svg);
			$svg = preg_replace("/^<\!-- .*? -->\n/m", "", $svg); //remove comments
			$svg = preg_replace("/^.*fill=\"(black|white).*\n/m", "", $svg);
			$svg = str_replace("G</title>\n<polygon fill=\"#ffffff", "G</title>\n<polygon id='svgscreen' style=\"opacity:0;\" fill=\"#ffffff", $svg); //set id and opacity on svgscreen
			$svg = preg_replace("/id=\"graph1/", "id=\"graph0", $svg); //rename svg object
			$svg = preg_replace("/viewBox=\"[^\"]*\"/", "", $svg); //remove viewbox
		}
		$svg = preg_replace("/^.*?<svg/s", "<svg", $svg); //Remove SVG Document header
		$svg = str_replace("&#45;&gt;", "_", $svg); //FIXME? convert HTML -> to _?
		$svg = str_replace("pt\"", "px\"", $svg); //convert points to pixels
		$svg = preg_replace("/<title>.*/m", "", $svg); //remove 'title' tags
		$svg = preg_replace("/^<\/?a.*\n/m", "", $svg); //FIXME? remove cruft after anchor tags
		$svg = preg_replace("/\.\.\/www\//", "", $svg); //FIXME change the local web path to be relative to http web path

		#write out the new svg
		$svgout = fopen($this->graphFileName.".svg", 'w');
		fwrite($svgout, $svg);
		fclose($svgout);
	
		#delete the raw svg
		if (! $nodeViz_config['debug']) { unlink($this->graphFileName.".svg.raw"); }
		$this->svgString = $svg;
	}

	protected function processGraphData() {
		$this->graph->data['name'] = $this->graph->graphname();
		$data = &$this->graph->data;
		unset($data['properties']['graphvizProperties']);
		unset($data['queries']);
		foreach (array_keys($data['nodes']) as $node) {
			foreach(array('mouseout', 'size', 'max', 'min', 'color', 'fillcolor', 'weight') as $key) { 
				unset($data['nodes'][$node][$key]); 
			}
		}
		foreach (array_keys($data['edges']) as $edge) {
			foreach(array('mouseout', 'size', 'max', 'min', 'color', 'fillcolor', 'weight', 'width') as $key) { 
				unset($data['edges'][$edge][$key]); 
			}
		}
	}

	protected function renderGraphViz() {
		require('libgv-php5/gv.php'); //load the graphviz php bindings
		global $nodeViz_config;
		$filename = $nodeViz_config['nodeViz_path'].$this->graphFileName; //Need to use absolute paths cuz we change to web dir

		//use gv.php to process dot string, apply layout, and generate outputs
		chdir($nodeViz_config['web_path']);
		ob_start();
		$gv = gv::readstring($this->dotString);
		gv::layout($gv, $this->GV_PARAMS['graph']['layoutEngine']);		
		gv::render($gv, 'svg', $filename.".svg.raw");
		//FIXME - we should be able to use 'renderresult' to write to string, but it breaks - why?
		gv::render($gv, 'cmapx', $filename.".imap");
		if($nodeViz_config['debug']) {
			gv::render($gv, 'dot', $filename.".dot");
		}
		
		$data = &$this->graph->data;
		$node = gv::firstnode($gv);
		$data['nodes'][gv::getv($node, 'id')]['pos'] = gv::getv($node, 'pos');
		$data['nodes'][gv::getv($node, 'id')]['height'] = gv::getv($node, 'height');
		while($node = gv::nextnode($gv, $node)) {
			$data['nodes'][gv::getv($node, 'id')]['pos'] = gv::getv($node, 'pos');
			$data['nodes'][gv::getv($node, 'id')]['height'] = gv::getv($node, 'height');
		}

		gv::rm($gv);
		if(ob_get_contents()) {
			ob_end_clean();
			trigger_error("GraphViz interpreter failed", E_USER_ERROR);
		}
		ob_end_clean();
		chdir($nodeViz_config['nodeViz_path']);
		

	}

	protected function renderSVG() {
		$svg = &$this->svgString;
		
		$width = $this->graph->width;
		$height = $this->graph->height;
		$size = $width > $height ? $height : $width;
		$ratio = $size / (1000*96);
		$svg = preg_replace('/<svg width="(\d+)px" height="(\d+)px"/em', "'<svg width=\"'.($1*$ratio).'px\" height=\"'.($2*$ratio).'px\"'", $svg);
		$svg = preg_replace("/transform=\"scale\(([\d\.]+) ([\d\.]+)\) /me", "'transform=\"scale('.($1* $ratio).' '.($2*$ratio).') '", $svg);

		$svgout = fopen($this->renderFileName.".svg", 'w');
		fwrite($svgout, $svg);
		fclose($svgout);
	}

	protected function renderImap() {
		$imap = &$this->imapString;

		$width = $this->graph->width;
		$height = $this->graph->height;
		$size = $width > $height ? $height : $width;
		$ratio = $size / (1000*96);
		$newimap = "";
		foreach (explode("\n", $imap) as $line) {
			if (preg_match("/coords=\"([\d, \.]+)\"/", $line, $coords)) {
				$sets = array();
				foreach(explode(' ', $coords[1]) as $set) {
					$nums = array();
					foreach(explode(',', $set) as $num) {
						$nums[] = $num * $ratio;
					}
					$sets[] = implode(',', $nums);
				}
				$newcoords = implode(' ', $sets);
				$newimap .= str_replace($coords[0], "coords=\"$newcoords\"", $line)."\n";
			} else {
				$newimap .= $line."\n";
			}
		}
		$imap = $newimap;
		$imapout = fopen($this->renderFileName.".imap", 'w');
		fwrite($imapout, $imap);
		fclose($imapout);
	}

	protected function renderRaster() {
		#Generate the raster version
		$im = new Imagick();
		$im->setFormat('svg');
		#remove labels from the raster version
		$im->readImageBlob(preg_replace('/<text.+\/text>/m', "", $this->svgString));
		$im->setImageFormat($this->rasterFormat);
		$im->setImageCompressionQuality(90);
		$im->writeImage($this->renderFileName.".".$this->rasterFormat);
		$im->clear();
		$im->destroy();
	}

	protected function checkPaths() {
		//check if cache directory is readable
		if (! is_dir($this->cachePath) || ! is_readable($this->cachePath)) {
			trigger_error("Unable to read cache directory '".$this->cachePath."'", E_USER_ERROR);
		}
		if ($this->cacheLevel < 3) {
			if ($this->cacheLevel < 2) {
				if (! is_writable($this->cachePath)) {
					trigger_error("Unable to write to cache directory '".$this->cachePath."'", E_USER_ERROR);
				}
			}
				if (! is_dir($this->graphPath)) {
					mkdir($this->graphPath) || trigger_error("Unable to create graph directory '".$this->graphPath."'", E_USER_ERROR);
				} elseif (! is_writeable($this->graphPath)) {
					trigger_error("Unable to write to graph directory '".$this->graphPath."'", E_USER_ERROR);
				}
			if (! is_dir($this->renderPath)) {
				mkdir($this->renderPath) || trigger_error("Unable to create render directory '".$this->renderPath."'", E_USER_ERROR);
			} elseif (! is_writeable($this->renderPath)) {
				trigger_error("Unable to write to render directory '".$this->renderPath."'", E_USER_ERROR);
			}
		} else {
			if (! is_readable($this->graphPath)) {
				trigger_error("Unable to read graph directory '".$this->graphPath."'", E_USER_ERROR);
			}
			if (! is_readable($this->renderPath)) {
				trigger_error("Unable to read render directory '".$this->renderPath."'", E_USER_ERROR);
			}
		}		
	}
}
?>
