var GraphSVG = Class.create(GraphImage, {
	initialize: function($super, NodeViz) {
		this.zoomlevels = 8;
		this.zoom_delta = 1.2;
		this.default_zoom = 1;
		this.current_zoom = this.default_zoom;
		this.zoomSliderAxis = 'horizontal';
		$super(NodeViz);
		if (this.NodeViz.options.useSVG != 1 ) { return; } 

		Event.observe(window, 'resize', function() {
			[$('svg_overlay'), $('image')].each(function(e) {
				if (e) { 
					e.childNodes[0].setAttribute('height', $(this.graphdiv).getHeight());
					e.childNodes[0].setAttribute('width', $(this.graphdiv).getWidth());
				}
			}.bind(this));
			this.stateTf = $('graph0').getCTM().inverse();
			this.ctm = $('graph0').getCTM();
			if ($('graph0')) {
				this.stateTf = $('graph0').getCTM().inverse();
				this.ctm = $('graph0').getCTM();
			}
		}.bind(this));

		$(this.graphdiv).innerHTML += this.zoomControlsHTML;
		Event.observe($('zoomin'), 'click', function(e) { this.zoom('in'); }.bind(this));
		Event.observe($('zoomout'), 'click', function(e) { this.zoom('out'); }.bind(this));
		Event.observe($('zoomreset'), 'click', function(e) { this.zoom('reset'); }.bind(this));
		this.zoomlevels = parseFloat(this.zoomlevels);
		//we need to build the zoomlevels css
		var x = 0;
		var values = new Array();
		var css_string = '';
		while (x <= this.zoomlevels) { 
			var y = x+1;
			while (y <= this.zoomlevels) { 
				css_string += '.zoom_'+x+' .zoom_'+y+', ';
				y++;
			}
			values.unshift(x);
			x++;
		}
		css_string = css_string.slice(0, -2);
		css_string += " { display: none; }";
		//insert the zoom stylesheet into the doc header
		$$('head')[0].appendChild(new Element('style', {'type': 'text/css'}).update(css_string));

		this.zoomSlider = new Control.Slider('zoomHandle', 'zoomSlider', {values: values, axis: this.zoomSliderAxis, range: $R(this.zoomlevels,0), sliderValue: 1,
			onChange: function(value) { 
				if(this.current_zoom != value) { 
					if (this.zoom_event) { 
						window.clearTimeout(this.zoom_event);
					}
					var do_zoom = function() {
						this.SVGzoom(value);
					}.bind(this);
					this.zoom_event = do_zoom.delay(.2);
				}
			}.bind(this)
		});
	},
	reset: function($super) {
		$super();
		this.state = '';
		this.stateOrigin = '';
		this.clickPoint = '';
		this.current_zoom = 1;
		this.previous_zoom = 1;
		this.zoom_point = null;
		if(this.root) { Event.stopObserving(this.root); }
		$$('.node').each(function(e) { Element.stopObserving(e); });
		$$('.edge', '#svg', '#graphs', '#svgscreen', '#images').each(function(e) { Element.stopObserving(e); });

	},
	render: function($super, responseData) { 
		//console.time('renderSVG');
		$super();
		var image = responseData.image;
		var overlay = responseData.overlay;
		overlay = overlay.replace("<div id='svg_overlay' style='display: none'>", '');
		overlay = overlay.replace("</div>", '');
		overlay.match(/<svg width=\"([\d\.]+)px\" height=\"([\d\.]+)px\"/);
		this.graphWidth = RegExp.$1;
		this.graphHeight = RegExp.$2;

		overlay = overlay.replace(/<svg width=\"[\d\.]+px\" height=\"[\d\.]+px\"/, "<svg width=\""+this.graphDimensions.width+"\" height=\""+this.graphDimensions.height+"\"");
		//parse the SVG into a new document
		var dsvg = new DOMParser();
		dsvg.async = false;
		var svgdoc = dsvg.parseFromString(overlay, 'text/xml');

		//insert the underlying image
		$('images').update(new Element('div', {'id': 'image'}));
		$('image').appendChild($('image').ownerDocument.importNode(svgdoc.firstChild, true));

		//reset all the ids for the under-image
		$('svgscreen').setAttribute('id', 'underlay_svgscreen');
		$A($('images').getElementsByTagName('g')).each( function(g) { 
			var id = $(g).getAttribute('id');
			$(g).setAttribute('id', 'underlay_'+id);
		});

		//insert the svg image again as the overlay
		$('images').insert(new Element('div', {'id': 'svg_overlay'}));
		$('svg_overlay').appendChild($('svg_overlay').ownerDocument.importNode(svgdoc.firstChild, true));

		//Center the svg images (using the original graph dimensions)
		this.root = $('svg_overlay').childNodes[0];
		this.stateTf = $('graph0').getCTM().inverse();
		this.ctm = $('graph0').getCTM();
		var delta = this.root.createSVGPoint();
		delta.x = (this.graphDimensions.width - this.graphWidth)/2;
		delta.y = (this.graphDimensions.height - this.graphHeight)/2;
		delta.matrixTransform(this.stateTf);	
		var matrix = this.ctm; 
		matrix.e+= delta.x;
		matrix.f+= delta.y;
		var s = "matrix(" + matrix.a + "," + matrix.b + "," + matrix.c + "," + matrix.d + "," + matrix.e + "," + matrix.f + ")";
		$('graph0').setAttribute('transform', s);
		$('underlay_graph0').setAttribute('transform', s);
		this.stateTf = $('graph0').getCTM().inverse();
		this.ctm = $('graph0').getCTM();

		//Show the Graphs
		$('svg_overlay').style.setProperty('position','absolute', '');
		$('svg_overlay').style.setProperty('top','0px', '');
		$('graph0').style.setProperty('opacity', '1', '');
		$('svg_overlay').style.setProperty('visibility', 'visible', '');
		$('svg_overlay').style.setProperty('display', 'block','');
		this.setupListeners();

		//apply the default zoom
		this.zoom(this.default_zoom);
		if (this.default_zoom == 1) { 
			this.setZoomFilters();
		}
		//console.timeEnd('renderSVG');
	},
	setupListeners: function($super) {
		$super();
		//Event.observe($('svgscreen'),'click', this.NodeViz.unselectNode.bind(this.NodeViz));
		Event.observe($('svg_overlay'), 'mousemove', this.mousemove.bind(this));
		Event.observe($('graphs'), 'mousemove', this.mousemove.bind(this));
		$$('#svg_overlay .node').each( function(n) {
			var nodeid = n.id;
			var node = this.NodeViz.data.nodes[n.id]
			this.addClassName($(nodeid), node['type']);
			this.addClassName($('underlay_'+nodeid), node['type']);	
			if (typeof(node['zoom']) != 'undefined') { 
				this.addClassName($(nodeid), 'zoom_'+node['zoom']);
				this.addClassName($('underlay_'+nodeid), 'zoom_'+node['zoom']);
			}
			if (node['class']) { 
				node['class'].split(' ').each( function(c) { 
					this.addClassName($(nodeid), c);	
					this.addClassName($('underlay_'+nodeid), c);	
				}, this);
			}
			this.formatLabels(n, node);	
			if (n.childNodes[1]) {
				this.NodeViz.addEvents(n, node, 'node', 'svg');
			}
		}, this);
		$$('#svg_overlay .edge').each( function(e) {
			var edgeid = e.id;
			var edge = this.NodeViz.data.edges[edgeid];
			this.addClassName($(edgeid), edge['type']);
			this.addClassName($('underlay_'+edgeid), edge['type']);
			if (edge['zoom']) { 
				this.addClassName($(edgeid), 'zoom_'+edge['zoom']);
				this.addClassName($('underlay_'+edgeid), 'zoom_'+edge['zoom']);
			}
			if (edge['class']) { 
				edge['class'].split(' ').each( function(c) { 
					this.addClassName($(edgeid), c);	
					this.addClassName($('underlay_'+edgeid), c);	
				}, this);
			}
			this.formatLabels(e, edge);	
			this.NodeViz.addEvents(e, edge, 'edge', 'svg');
		}, this);
		this.setupZoomListeners(this.root);
	},
	highlightNode: function($super, id, text, noshowtooltip, renderer) {
		if (this.state != '') { return; }
		$super(id, text, noshowtooltip, renderer);
		var classString = $A($(id).childNodes).reverse().detect(function(e) { return e.tagName == 'polygon' || e.tagName == 'ellipse'; }).getAttribute('class');
		if (classString != null){
			classes = classString.split(' ');
			if (classes.indexOf('nhighlight') < 0){
				classes.push('nhighlight');
				classString = classes.join(' ');
			}
		} else {
			classString = 'nhighlight';
		}
		$A($(id).childNodes).reverse().detect(function(e) { return e.tagName == 'polygon' || e.tagName == 'ellipse'; }).setAttribute('class', classString);
		$A($('underlay_'+id).childNodes).reverse().detect(function(e) { return e.tagName == 'polygon' || e.tagName == 'ellipse'; }).setAttribute('class', classString);
if (this.NodeViz.current['network']) { 
			//$(id).parentNode.appendChild($(id));
		}
		this.showSVGElement(id);
	},

	unhighlightNode: function($super, id) {
		$super(id);
		var NodeViz = this.NodeViz;
		var node = $(id);
		$A($(id).childNodes).reverse().detect(function(e) { return e.tagName == 'polygon' || e.tagName == 'ellipse'; }).removeAttribute('class');
		$A($('underlay_'+id).childNodes).reverse().detect(function(e) { return e.tagName == 'polygon' || e.tagName == 'ellipse'; }).removeAttribute('class');
		if (NodeViz.current['network'] == id || (NodeViz.data.nodes[NodeViz.current['network']] && NodeViz.data.nodes[NodeViz.current['network']]['relatedNodes'][id])) {
			return;
		} else {
			this.hideSVGElement(node);
		}
	},

	showNetwork: function(id) {
		var node = $(id);
		if (! node) { return; }
		if (this.NodeViz.current['network'] == node.id) { 
			this.hideNetwork();
			this.highlightNode(node.id);
			return;
		}
		if (this.NodeViz.current['edge']) { 
			this.hideEdge(1);
		}
		if (this.NodeViz.current['network']) { 
			this.hideNetwork(1);
		}
		if ($('image').getOpacity() == 1) {
			new Effect.Opacity('image', { from: 1, to: .3, duration: .5});
		}
		showSVGElement(node);
		$H(nodelookup[node.id]['edges']).keys().each(function(e) {
			this.showSVGElement(e);
		}, this);
		$H(nodelookup[node.id]['lnodes']).keys().each(function(e) {
			this.showSVGElement(e);
		}, this);
		this.NodeViz.current['network'] = node.id	
	},

	showSVGElement: function(e) {
		if (! $(e)) { return; }
		$(e).style.setProperty('opacity', '1', 'important');
		$(e).style.setProperty('display', 'block', 'important');
	},

	hideSVGElement: function(e) {
		if (! $(e)) { return; }
		$(e).style.removeProperty('opacity');
		$(e).style.removeProperty('display');
	},
	selectNode: function($super, id) { 
		$super();
		this.showSVGElement(id);
		this.addClassName($(id), 'selected');
		this.addClassName($('underlay_'+id), 'selected');
		if ($('image').getOpacity() == 1) {
			new Effect.Opacity('image', { from: 1, to: .3, duration: .5});
		}
		$H(this.NodeViz.data.nodes[id].relatedNodes).keys().each(function(e) {
			this.showSVGElement(e);
			this.addClassName($(e), 'oselected');
			this.addClassName($('underlay_'+e), 'oselected');
			$H(this.NodeViz.data.nodes[id].relatedNodes[e]).values().each( function(edge) { 
				this.showSVGElement(edge);
			}, this);
		}, this);
	},
	findParents: function(id,found){
		
		$H(this.NodeViz.data.nodes[id].relatedNodes).keys().each(function(e) {
			$H(this.NodeViz.data.nodes[id].relatedNodes[e]).values().each( function(edge) { 
				if(this.NodeViz.data.edges[edge].toId==id){
					//TODO: check if parent already found to avoid loop
					found.push(this.NodeViz.data.edges[edge].fromId);
					//recursively call function on parents
					found = this.NodeViz.renderers.GraphImage.findParents(this.NodeViz.data.edges[edge].fromId,found);			
				}
			}, this);
		}, this);
		return found;
	},
	findParentEdges: function(id,foundEdges){
		$H(this.NodeViz.data.nodes[id].relatedNodes).keys().each(function(e) {
			$H(this.NodeViz.data.nodes[id].relatedNodes[e]).values().each( function(edge) { 
				if(this.NodeViz.data.edges[edge].toId==id){
					//TODO: check if parent already found to avoid loop
					foundEdges.push(edge);
					//recursively call function on parents
					foundEdges = this.NodeViz.renderers.GraphImage.findParentEdges(this.NodeViz.data.edges[edge].fromId,foundEdges);			
				}

			}, this);
		}, this);
		return foundEdges;
	},
	findChildren: function(id,found){
		
		$H(this.NodeViz.data.nodes[id].relatedNodes).keys().each(function(e) {
			$H(this.NodeViz.data.nodes[id].relatedNodes[e]).values().each( function(edge) { 
				if(this.NodeViz.data.edges[edge].fromId==id){
					//TODO: check if child already found to avoid loop
					found.push(this.NodeViz.data.edges[edge].toId);
					//recursively call function on parents
					found = this.NodeViz.renderers.GraphImage.findChildren(this.NodeViz.data.edges[edge].toId,found);			
				}

			}, this);
		}, this);
		return found;
	},
	findChildEdges: function(id,foundEdges){
		$H(this.NodeViz.data.nodes[id].relatedNodes).keys().each(function(e) {
			$H(this.NodeViz.data.nodes[id].relatedNodes[e]).values().each( function(edge) { 
				if(this.NodeViz.data.edges[edge].fromId==id){
					//TODO: check if parent already found to avoid loop
					foundEdges.push(edge);
					
					//recursively call function on children
					foundEdges = this.NodeViz.renderers.GraphImage.findChildEdges(this.NodeViz.data.edges[edge].toId,foundEdges);			
				}
			}, this);
		}, this);
		return foundEdges;
	},
	unselectNode: function($super, id, fade) { 
		$super();
		var realunselectNode = function() {	
			this.removeClassName($(id), 'selected');
			this.removeClassName($('underlay_'+id), 'selected');
			//removed selected class on neighboring edges
			$H(this.NodeViz.data.nodes[id].relatedNodes).keys().each(function(e) {
				this.hideSVGElement(e);
				this.removeClassName($(e), 'oselected');
				this.removeClassName($('underlay_'+e), 'oselected');
				$H(this.NodeViz.data.nodes[id].relatedNodes[e]).values().each( function(edge) { 
					this.hideSVGElement(edge);
				}, this);
			}, this);
			this.hideSVGElement(id);
			this.unhighlightNode(id);
			$('svg_overlay').setStyle({opacity: 1});
		}.bind(this);
		if (fade) {
			new Effect.Opacity('image', { from: .3, to: 1, duration: .3});
			new Effect.Opacity('svg_overlay', { from: 1, to: 0, duration: .3, afterFinish: realunselectNode});
		} else {
			realunselectNode();
		}
	},
	hasClassName: function(element, className) {
		var elementClassName = element.getAttribute('class') || '';
		return (elementClassName.length > 0 && (elementClassName == className ||
		new RegExp("(^|\\s)" + className + "(\\s|$)").test(elementClassName)));
	},

	addClassName: function(element, className) {
		var elementClassName = element.getAttribute('class') || '';
		if (!this.hasClassName(element, className)) {
			elementClassName += (elementClassName ? ' ' : '') + className;
		}
		element.setAttribute('class', elementClassName);
		return element;
	},

	removeClassName: function(element, className) {
		elementClassName = element.getAttribute('class');
		elementClassName = elementClassName.replace(
		new RegExp("(^|\\s+)" + className + "(\\s+|$)"), ' ').strip();
		element.setAttribute('class', elementClassName);
		return element;
	},

	zoomToNode: function(id, level) {
		//Change graph zoom level, centered on center of a node

		if(!$(id)) { return; } //should this throw an error?
	
  		//get the transform of the svg coords to screen coords
  		//var ctm = $(id).getScreenCTM();
		var ctm = this.ctm;
	
  		//get bounding box for node 
  		var box = $(id).getBBox();
  		var svg_p = this.root.createSVGPoint();
  		svg_p.x = box.width /2 + box.x;
		svg_p.y = box.height /2  + box.y;

		//use the transform to move the point to screen coords
		var dom_p = svg_p.matrixTransform(ctm);
		//correct for differences in screen coords when page is scrolled
		var offset = $('svg_overlay').viewportOffset();
		dom_p.x = dom_p.x -offset[0];
		dom_p.y = dom_p.y -offset[1];

		//if no level was passed, default to 'in'
		level = level !== '' ? level : 'in';
  		this.zoom(level,dom_p);
	},

	panToNode: function(id, zoom) {
		//re-center graph on center of a node, and optionally change zoom level

		if(!$(id)) { return; } //should this throw an error?
	
  		//get the transform of the svg coords to screen coords
  		//var ctm = $(id).getScreenCTM();
		var ctm = this.ctm;
		var g = $('graph0');
		//this.stateTf = $('graph0').getCTM().inverse();

  		//get bounding box for node 
  		var box = $(id).getBBox();
  		var node_center = this.root.createSVGPoint();
  		node_center.x = box.width /2 + box.x;
		node_center.y = box.height /2 + box.y;

		var center = this.calculateCenter();
		//$('images').insert(new Element('div', {'id': 'centertest', 'style': 'position: absolute; opacity: .2; background: pink; z-index: 1000; top: 0px; left: 0px; width:'+center.x+'px; height: '+center.y+'px;'}));
		//convert from dom pixels to svg units
		center = center.matrixTransform(this.stateTf);

		//now let's calculate the delta
		var delta = this.root.createSVGPoint();
		delta.x = (center.x - node_center.x);
		delta.y = (center.y - node_center.y);

		new Effect.Translate($('graph0'), {x: delta.x, y: delta.y, afterFinish: function() {
				this.stateTf = $('graph0').getCTM().inverse();
				this.ctm = $('graph0').getCTM();
				if (typeof(zoom) != 'undefined') {
					//this.zoomToNode(id, zoom);
					this.zoom(zoom,this.calculateCenter());
				}
			}.bind(this)
		});
		//this.setCTM(g, $('graph0').getCTM().translate(delta.x, delta.y));
	},
	setupZoomListeners: function(root){
		Event.observe($('svgscreen'), 'mousedown', function(e) { this.handleMouseDown(e); }.bind(this));
		Event.observe($('svgscreen'), 'mousemove', function(e) { this.handleMouseMove(e); }.bind(this));
		//Event.observe($('svgscreen'), 'mouseup', function(e) { this.handleMouseUp(e); }.bind(this));
		Event.observe(root, 'mousedown', function(e) { this.handleMouseDown(e); }.bind(this));
		Event.observe(root, 'mousemove', function(e) { this.handleMouseMove(e); }.bind(this));
		Event.observe(root, 'mouseup', function(e) { this.handleMouseUp(e); }.bind(this));
		Event.stopObserving('svgscreen', 'click');
		//Event.observe($('svgscreen'), 'mouseup', function(e) { this.handleMouseUp(e); }.bind(this));
		Event.observe($('images'), 'mouseleave', function(e) { this.handleMouseUp(e); }.bind(this));
		if(navigator.userAgent.toLowerCase().indexOf('webkit') >= 0) {
			Event.observe(root, 'mousewheel', function(e) { this.handleMouseWheel(e); }.bind(this)); // Chrome/Safari
		} else {
			Event.observe(root, 'DOMMouseScroll', function(e) { this.handleMouseWheel(e); }.bind(this)); 
		}
		Event.observe(root, 'dblclick', function(e) { this.zoom('in', this.getEventPoint(e)); }.bind(this));
		this.center = this.calculateCenter();
	},
/**
 * Instance an SVGPoint object with given event coordinates.
 */
	getEventPoint: function(evt) {
		var p = this.root.createSVGPoint();

		p.x = evt.clientX;
		p.y = evt.clientY;
		var offset = $('svg_overlay').viewportOffset();
		p.x = p.x -offset[0];
		p.y = p.y  - offset[1];

		return p;
	},

/**
 * Sets the current transform matrix of an element.
 */
	setCTM: function(element, matrix) {
		var s = "matrix(" + matrix.a + "," + matrix.b + "," + matrix.c + "," + matrix.d + "," + matrix.e + "," + matrix.f + ")";

		element.setAttribute("transform", s);
		$('underlay_graph0').setAttribute("transform", s);
		//this.zoomSlider.setValue(this.current_zoom);
		this.setZoomFilters();
		this.stateTf = $('graph0').getCTM().inverse();
		this.ctm = $('graph0').getCTM();
	},
	
/**
 * Resets the zoom filters
 */
	setZoomFilters: function() {
		//$('image').removeClassName('zoom_'+this.previous_zoom);
		//$('svg_overlay').removeClassName('zoom_'+this.previous_zoom);
		$('image').className = 'zoom_'+this.zoomSlider.value;
		$('svg_overlay').className = 'zoom_'+this.zoomSlider.value;
	},

/**
 * Dumps a matrix to a string (useful for debug).
 */
	dumpMatrix: function(matrix) {
		var s = "[ " + matrix.a + ", " + matrix.c + ", " + matrix.e + "\n  " + matrix.b + ", " + matrix.d + ", " + matrix.f + "\n  0, 0, 1 ]";

		return s;
	},

/**
 * Sets attributes of an element.
 */
	setAttributes: function(element, attributes){
		for (i in attributes) {
			element.setAttributeNS(null, i, attributes[i]);
		}
	},

/**
 * Handle mouse wheel event.
 */
	handleMouseWheel: function(evt) {
		if (this.state == 'zoom') { 
			return;
		}
		this.state = 'zoom';

		if(evt.preventDefault) {
			evt.preventDefault();
		}

		evt.returnValue = false;

		var delta;
		if(evt.wheelDelta)
			delta = evt.wheelDelta / 360; // Chrome/Safari
		else
			delta = evt.detail / -9; // Mozilla

		var p = this.getEventPoint(evt);
		if (delta > 0) { 
			this.zoom('in', p);
		} else { 
			this.zoom('out', p);
		}
		this.state = '';
	},

/**
 * Handle mouse move event.
 */
	handleMouseMove: function(evt) {
		if(evt.preventDefault) {
			evt.preventDefault();
		}
		evt.returnValue = false;

		var g = $('graph0');

		if(this.state == 'pan') {
			var p = this.getEventPoint(evt).matrixTransform(this.stateTf);
			this.setCTM(g, this.stateTf.inverse().translate(p.x - this.stateOrigin.x, p.y - this.stateOrigin.y));
		}
	},

/**
 * Handle click event.
 */
	handleMouseDown: function(evt) {
		if(evt.preventDefault)
			evt.preventDefault();

		evt.returnValue = false;

		var svgDoc = this.root;
		//I'm removing this so that we can drag beyond edge of graph. Hopefully it didn't do anything?
		if (evt.target.id != 'svgscreen' && evt.target.tagName != 'svg_overlay' && evt.target.tagName != 'svg') { 
			//return;
		}
		var g = $('graph0');

		this.state = 'pan';
		evt.target.style.cursor = 'move';
		$('svgscreen').style.cursor = 'move';
		this.stateTf = g.getCTM().inverse();
		this.ctm = g.getCTM();

		this.stateOrigin = this.getEventPoint(evt).matrixTransform(this.stateTf);
		this.clickPoint = this.getEventPoint(evt);
	},

/**
 * Handle mouse button release event.
 */
	handleMouseUp: function(evt) {
		if(evt.preventDefault) {
			evt.preventDefault();
		}
		evt.returnValue = false;

		var origin = this.getEventPoint(evt);
		if (this.clickPoint.x == origin.x & this.clickPoint.y == origin.y & (evt.target.id == 'svgscreen' || evt.target.tagName == 'svg_overlay' || evt.target.tagName == 'svg')) { 
			this.NodeViz.unselectNode(1);
		}

		if(this.state == 'pan') {
			$('graph0').style.removeProperty('display');
			evt.target.style.removeProperty('cursor');
			// Quit pan mode
		}
		this.state = '';
		//evt.stop();
	},
	zoom: function(d, p) {
		if (d == 'in') { 
			d = this.zoomSlider.value+ 1;
		} else if (d == 'out') { 
			d = this.zoomSlider.value- 1;
		} else if (d == 'reset') {
			this.NodeViz.panToNode('graph0', this.default_zoom);
			return;
		}
		if (d < 0 || d > this.zoomlevels) {
			return;
		}
		if (p) { 
			this.zoom_point = p;
		}
		this.zoomSlider.setValue(d);
	},
	SVGzoom: function(d) { 
		$(this.graphdiv).addClassName('zooming');
		this.previous_zoom = this.current_zoom;
		this.current_zoom = d;
		var zoom_amount = d - this.previous_zoom;
		//var delta = 0.3333333333333333;
		//var z = Math.pow(1 + this.zoom_delta, delta);
		var z = this.zoom_delta;
		var g = $("graph0");
		var center = '';
		if (! this.zoom_point) { 
			center = this.calculateCenter();
		} else { 
			center = this.zoom_point;
		}
		var p = center.matrixTransform(g.getCTM().inverse());
		z = Math.pow(z, zoom_amount);
		//z = z*zoom_amount;
		//var k = this.root.createSVGMatrix().translate(p.x, p.y).scale(z).translate(-p.x, -p.y);
		new Effect.AnimateZoom($('graph0'), {point: p, zoom: z, queue: {'position': 'end', 'scope': 'zoom'}, duration: .5, afterFinish: function() {
				this.setZoomFilters();
				$(this.graphdiv).removeClassName('zooming');
				this.stateTf = $('graph0').getCTM().inverse();
				this.ctm = $('graph0').getCTM();
				if(this.NodeViz.afterZoom) {
					this.NodeViz.afterZoom();
				}
			}.bind(this)
		});
		//new Effect.AnimateZoom($('underlay_graph0'), {point: p, zoom: z});
		//this.setCTM(g, g.getCTM().multiply(k));
		//if(! this.stateTf) { 
			//this.stateTf = $('graph0').getCTM().inverse();
		//}
		//this.stateTf = this.stateTf.multiply(k.inverse());
		this.zoom_point = null;

	},
	calculateCenter: function() {
  		var center = this.root.createSVGPoint();
		center.x = $('svg_overlay').positionedOffset()[0] + ($('svg_overlay').getWidth() /2);
		center.y = $('svg_overlay').positionedOffset()[1] + ($('svg_overlay').getHeight() /2);
		return center;
	},
	formatLabels: function(dom_element, graph_element) {
		var label = dom_element.getElementsByTagName('text')[0];
		var ulabel = $('underlay_'+graph_element.id).getElementsByTagName('text')[0];
		
		if (label) {
			this.addClassName(label, 'label');
			this.addClassName(ulabel, 'label');
			if (typeof(graph_element['label_zoom_level']) != 'undefined') {
				this.addClassName(label, 'zoom_'+graph_element['label_zoom_level']);
				this.addClassName(ulabel, 'zoom_'+graph_element['label_zoom_level']);
			}
			if (typeof(graph_element['label_offset_x']) != 'undefined') {
				var x = parseFloat(graph_element['label_offset_x'] * this.stateTf.a) + parseFloat(label.getAttribute('x'));
				label.setAttribute('x', x);
				ulabel.setAttribute('x', x);
			}
			if (typeof(graph_element['label_offset_y']) != 'undefined') {
				var y = parseFloat(graph_element['label_offset_y'] * this.stateTf.d) + parseFloat(label.getAttribute('y'));
				label.setAttribute('y', y);
				ulabel.setAttribute('y', y);
			}
			if (typeof(graph_element['label_text_anchor']) != 'undefined') {
				label.setAttribute('text-anchor', graph_element['label_text_anchor']);
				ulabel.setAttribute('text-anchor', graph_element['label_text_anchor']);
			}	
		}
	},
	zoomControlsHTML: "\
		<div id='zoomcontrols'>\
			<span id='zoomin' class='zoomin' alt='Zoom In' title='Zoom In'>[+]</span>\
			<div id='zoomSlider' class='slider'><div id='zoomHandle' class='handle'></div></div>\
			<span id='zoomout' class='zoomout' alt='Zoom Out' title='Zoom Out'>[-]</span>\
			<span id='zoomreset' class='zoomreset' alt='Reset Zoom' title='Reset Zoom'>[0]</span>\
		</div>\
	"
}
);
