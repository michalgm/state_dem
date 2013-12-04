GraphImage("GraphSVG", {}, {
	init: function(NodeViz) {
		this.zoomlevels = 8;
		this.zoom_delta = 1.2;
		this.default_zoom = 1;
		this.current_zoom = this.default_zoom;
		this.zoomSliderAxis = 'horizontal';
		this.fadeTo = .3
		this._super(NodeViz);
		if (this.NodeViz.options.useSVG != 1 ) { return; } 
		
		$('#'+this.graphdiv).append(this.zoomControlsHTML);
		$('#zoomin').click($.proxy(function(e) { this.zoom('in'); }, this));
		$('#zoomout').click($.proxy(function(e) { this.zoom('out'); }, this));
		$('#zoomreset').click($.proxy(function(e) { this.zoom('reset'); }, this));
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
		$('head').append($("<style type='text/css'/>").html(css_string));

		//FIXME
		this.zoomSlider = {value: 1};
		$('#zoomSlider').slider({
			animate: "fast",
			orientation:this.zoomSliderAxis,
			max:this.zoomlevels,
			value: this.default_zoom,
			change: $.proxy(function(e, ui) { 
				this.setZoomValue(ui.value);
			}, this)
		});

		this.initial_scale = '';
	},
	reset: function() {
		this._super();
		this.state = '';
		this.stateOrigin = '';
		this.clickPoint = '';
		this.current_zoom = 1;
		this.previous_zoom = 1;
		if(this.root) { $(this.root).unbind(); }
		$('.node', '.edge', '#svg', '#graphs', '#svgscreen', '#images').unbind();

	},
	render: function(responseData) { 
		//console.time('renderSVG');
		this._super(responseData);
		var image = responseData.image;
		var overlay = responseData.overlay;
		try { 
			$.parseXML(overlay); 
		} catch(err) {
			this.NodeViz.reportError(900, "The SVG image is not valid XML");
			return;
		}
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
		$('#images').html("<div id='image'/>");
		$('#image').append($('#image')[0].ownerDocument.importNode(svgdoc.firstChild, true));

		//reset all the ids for the under-image
		$('#svgscreen').attr('id', 'underlay_svgscreen');
		$('#image>svg').svg();
		var svg = $('#image>svg').svg('get');
		$('#images g').each( function(index, g) { 
			$(g).attr('id', 'underlay_'+$(g).attr('id'));
		});
		
		//insert the svg image again as the overlay
		$('#images').append($('<div/>').attr('id','svg_overlay'));
		$('#svg_overlay').append($('#svg_overlay')[0].ownerDocument.importNode(svgdoc.firstChild, true));

		//remove anchor groups
		$('#image g, #svg_overlay g').each( function(index, g) { 
			var a = $('#a_'+$(g).attr('id'));
			if (a.length) { 
				a.contents().unwrap();
			}
		});

		//Center the svg images (using the original graph dimensions)
		this.root = $('#svg_overlay')[0].childNodes[0];
		this.updateCTM();

		var delta = this.root.createSVGPoint();
		delta.x = (this.graphDimensions.width - this.graphWidth)/2;
		delta.y = (this.graphDimensions.height - this.graphHeight)/2;
		delta.matrixTransform(this.stateTf);	
		var matrix = this.ctm; 
		matrix.e+= delta.x;
		matrix.f+= delta.y;
		var s = this.matrixToTransform(matrix);
		$('#graph0').attr('transform', s);
		$('#underlay_graph0').attr('transform', s);
		this.updateCTM();
		this.updateDefaultScale();

		//Show the Graphs
		$('#svg_overlay').css('position','absolute');
		$('#svg_overlay').css('top','0px');
		$('#graph0').css('opacity', '1');
		$('#svg_overlay').css('visibility', 'visible');
		$('#svg_overlay').css('display', 'block');
		this.setupListeners();

		//apply the default zoom
		this.zoom(this.default_zoom);
		if (this.default_zoom == 1) { 
			this.setZoomFilters();
		}
		//console.timeEnd('renderSVG');
	},
	setupListeners: function() {
		this._super();
		//Event.observe($('svgscreen'),'click', this.NodeViz.unselectNode.bind(this.NodeViz));
		$('#svg_overlay').mousemove($.proxy(this.mousemove, this));
		$('#graphs').mousemove($.proxy(this.mousemove, this));
		$('#svg_overlay .node').each($.proxy(function(index,n) {
			var nodeid = n.id;
			var node = this.NodeViz.data.nodes[n.id]
			$('#'+nodeid).addClass(node['type']);
			//this.addClassName($(nodeid), node['type']);
			$('#underlay_'+nodeid).addClass(node['type']);	
			if (typeof(node['zoom']) != 'undefined') { 
				$('#'+nodeid).addClass('zoom_'+node['zoom']);
				$('#underlay_'+nodeid).addClass('zoom_'+node['zoom']);
			}
			if (node['class']) { 
				$(node.class.split(' ')).each(function(i, c) { 
					$('#'+nodeid).addClass(c);
					$('#underlay_'+nodeid).addClass(c);	
				});
			}
			this.formatLabels(n, node);	
			if (n.childNodes[1]) {
				this.NodeViz.addEvents(n, node, 'node', 'svg');
			}
		}, this));
		$('#svg_overlay .edge').each($.proxy(function(index, e) {
			var edgeid = e.id;
			var edge = this.NodeViz.data.edges[edgeid];
			$('#'+edgeid).addClass(edge['type']);
			$('#underlay_'+edgeid).addClass(edge['type']);
			if (typeof(edge['zoom']) != 'undefined') { 
				$('#'+edgeid).addClass('zoom_'+edge['zoom']);
				$('#underlay_'+edgeid).addClass('zoom_'+edge['zoom']);
			}
			if (edge['class']) { 
				edge['class'].split(' ').each( function(index, c) { 
					$('#'+edgeid).addClass(c);
					$('#underlay_'+edgeid).addClass(c);	
				}, this);
			}
			this.formatLabels(e, edge);	
			this.NodeViz.addEvents(e, edge, 'edge', 'svg');
		}, this));
		this.setupZoomListeners(this.root);
	},
	highlightNode: function(id, text, noshowtooltip, renderer) {
		if (this.state != '') { return; }
		this._super(id, text, noshowtooltip, renderer);
		$('#'+id+', #underlay_'+id).children().filter('polygon, ellipse').addClass('nhighlight');
		if (this.NodeViz.current['network']) { 
			//$(id).parentNode.appendChild($(id));
		}
		this.showSVGElement($('#'+id));
	},

	unhighlightNode: function(id) {
		this._super(id);
		var NodeViz = this.NodeViz;
		var node = $('#'+id);
		$('#'+id+ ', #underlay_'+id).children().filter('polygon, ellipse').removeClass('nhighlight');
		if (NodeViz.current['network'] == id || (NodeViz.data.nodes[NodeViz.current['network']] && NodeViz.data.nodes[NodeViz.current['network']]['relatedNodes'][id])) {
			return;
		} else {
			this.hideSVGElement(node);
		}
	},

	showNetwork: function(id) {
		var node = $('#'+id);
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
			new Effect.Opacity('image', { from: 1, to: this.fadeTo, duration: .5});
		}
		this.showSVGElement(node);
		$H(nodelookup[node.id]['edges']).keys().each(function(index, e) {
			this.showSVGElement($('#'+e));
		}, this);
		$H(nodelookup[node.id]['lnodes']).keys().each(function(index, e) {
			this.showSVGElement($('#'+e));
		}, this);
		this.NodeViz.current['network'] = node.id	
	},

	showSVGElement: function(e) {
		if (!e) { return; }
		//FIXME - do we need !important (it was there pre-jquery)
		e.css('opacity', 1).css('display', 'block');
		return e;
	},

	hideSVGElement: function(e) {
		if (!e) { return; }
		e.css('opacity', '').css('display', '');
		return e;
	},
	selectNode: function(id) { 
		this._super(id);
		this.showSVGElement($('#'+id));
		$('#'+id).addClass('selected');
		$('#underlay_'+id).addClass('selected');
		if ($('#image').css('opacity') == 1) {
			$('#image').fadeTo(500, this.fadeTo);
		}
		$(Object.keys(this.NodeViz.data.nodes[id].relatedNodes)).each($.proxy(function(index, e) {
			this.showSVGElement($('#'+e));
			$('#'+e).addClass('oselected');
			$('#underlay_'+e).addClass('oselected');
			$(this.NodeViz.data.nodes[id].relatedNodes[e]).values().each($.proxy(function(index, edge) { 
				this.showSVGElement($('#'+edge));
			}, this));
		}, this));
	},
	findParents: function(id,found){
		$H(this.NodeViz.data.nodes[id].relatedNodes).keys().each(function(index, e) {
			$H(this.NodeViz.data.nodes[id].relatedNodes[e]).values().each( function(index, edge) { 
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
		$H(this.NodeViz.data.nodes[id].relatedNodes).keys().each(function(index, e) {
			$H(this.NodeViz.data.nodes[id].relatedNodes[e]).values().each( function(index, edge) { 
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
		$H(this.NodeViz.data.nodes[id].relatedNodes).keys().each(function(index, e) {
			$H(this.NodeViz.data.nodes[id].relatedNodes[e]).values().each( function(index, edge) { 
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
		$H(this.NodeViz.data.nodes[id].relatedNodes).keys().each(function(index, e) {
			$H(this.NodeViz.data.nodes[id].relatedNodes[e]).values().each( function(index, edge) { 
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
	unselectNode: function(id, fade) { 
		this._super(id, fade);
		var realunselectNode = $.proxy(function() {	
			var node = $('#'+id);
			node.removeClass('selected');
			$('#underlay_'+id).removeClass('selected');
			//removed selected class on neighboring edges
			$(this.NodeViz.data.nodes[id].relatedNodes).keys().each($.proxy(function(index, e) {
				var rnode = $('#'+e);
				this.hideSVGElement(rnode);
				rnode.removeClass('oselected');
				$('#underlay_'+e).removeClass('oselected');
				$(this.NodeViz.data.nodes[id].relatedNodes[e]).values().each($.proxy(function(index, edge) { 
					this.hideSVGElement($('#'+edge));
				}, this));
			}, this));
			this.hideSVGElement(node);
			this.unhighlightNode(id);
			$('#svg_overlay').css('opacity',1);
		}, this);
		if (fade) {
			$('#image').fadeTo(300, 1);
			$('#svg_overlay').fadeTo(300, 0, realunselectNode);
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
		var ctm = this.ctm;
	
  		//get bounding box for node 
		var box = $('#'+id).children()[0].getBBox();
  		var svg_p = this.root.createSVGPoint();
  		svg_p.x = box.width /2 + box.x;
		svg_p.y = box.height /2  + box.y;

		//use the transform to move the point to screen coords
		var dom_p = svg_p.matrixTransform(ctm);
		//correct for differences in screen coords when page is scrolled
		var offset = $('#svg_overlay').offset();
		dom_p.x -= offset.left;
		dom_p.y -= offset.top;

		//if no level was passed, default to 'in'
		level = typeof(level) != 'undefined' ? level : 'in';
  		this.zoom(level,dom_p);
	},

	getElementCenter: function(id) { 
		if(!$('#'+id).length) { return; } //should this throw an error?

		//Chrome doesn't like getting BBox for Groups, so we get the box for the first sub element
		var box = $('#'+id).children()[0].getBBox();
  		var node_center = this.root.createSVGPoint();
  		node_center.x = box.width /2 + box.x;
		node_center.y = box.height /2 + box.y;
		var center = this.calculateCenter();
		//The following line is for debugging the center point
		//$('#centertest').remove();
		//$('#images').append($('<div>').attr({'id': 'centertest', 'style': 'position: absolute; opacity: .2; background: pink; z-index: 1000; top: 0px; left: 0px; width:'+center.x+'px; height: '+center.y+'px;'}));
		//convert from dom pixels to svg units
		center = center.matrixTransform(this.stateTf);

		//now let's calculate the delta
		var delta = this.root.createSVGPoint();
		delta.x = (center.x - node_center.x);
		delta.y = (center.y - node_center.y);
		return delta;
	},
	panToNode: function(id, zoom, offset) {
		//re-center graph on center of a node, and optionally change zoom level

		var delta = this.getElementCenter(id);
		if (offset && typeof(offset.x) != 'undefined') { 
			var point = delta.matrixTransform(this.ctm);
			point.x += offset.x;
			point.y += offset.y;
			delta = point.matrixTransform(this.stateTf);
		}
		var k = this.matrixToTransform(this.ctm.translate(delta.x,delta.y));
		
		$('#graph0, #underlay_graph0').animate({svgTransform:k}, 1000, 
			$.proxy(function() {
				this.updateCTM();
				if (typeof(zoom) != 'undefined' && zoom != null) {
					if (offset && typeof(offset.x) != 'undefined') { 
						var center = this.calculateCenter();
						center.x += offset.x;
						center.y += offset.y;
						this.zoom(zoom,center);
					} else {
						this.zoom(zoom,this.calculateCenter());
					}
				}
			}, this)
		);
		//this.setCTM(g, $('graph0').getCTM().translate(delta.x, delta.y));
	},
	setupZoomListeners: function(root){
		Hammer(root, {transform_always_block: true} ).on('dragstart dragend drag transform doubletap', $.proxy(function(e) {
			e.gesture ? e.gesture.preventDefault() : e.preventDefault();
			switch(e.type) {
				case 'dragstart':
					this.handleMouseDown(e);
					break;
				case 'dragend':
					this.handleMouseUp(e);
					break;
				case 'drag':
					this.handleMouseMove(e);
					break;
				case 'transform':
					this.handleMouseWheel(e, e.gesture.scale);
					break;
				case 'doubletap':
					if(! $(e.target).closest('#graph0').length || e.target.id == 'svgscreen') { 
						this.zoom('in', this.getEventPoint(e));
					}
					break;
			}
			e.stopPropagation();
		}, this));
		$('#svgscreen').unbind('click');
		$('#images').mouseleave($.proxy(function(e) { this.handleMouseUp(e); }, this));
		$(root).mousewheel($.proxy(function(e, delta) { this.handleMouseWheel(e, delta); }, this));
		this.center = this.calculateCenter();
	},
/**
 * Instance an SVGPoint object with given event coordinates.
 */
	getEventPoint: function(evt) {
		var p = this.root.createSVGPoint();

		//If this is a gesture event, get the center from the gesture
		if (evt.gesture) { evt = evt.gesture.center; } 
		p.x = evt.pageX;
		p.y = evt.pageY;
		var offset = [$('#svg_overlay').offset().left, $('#svg_overlay').offset().top];
		p.x = p.x -offset[0];
		p.y = p.y  - offset[1];

		return p;
	},

/**
 * Sets the current transform matrix of an element.
 */
	setCTM: function(matrix) {
		var s = "matrix(" + matrix.a + "," + matrix.b + "," + matrix.c + "," + matrix.d + "," + matrix.e + "," + matrix.f + ")";

		$('#graph0, #underlay_graph0').attr("transform", s);
		if( this.zoomSlider.value != this.current_zoom) { 
			//FIXME this.zoomSlider.setValue(this.current_zoom);
			this.zoomSlider.value = this.current_zoom;
			this.setZoomValue(this.zoomSlider.value); //rmeove this
			this.setZoomFilters();
		}
		this.updateCTM();
	},
	
/**
 * Resets the zoom filters
 */
	setZoomFilters: function() {
		$('#image, #svg_overlay').removeClass('zoom_'+this.previous_zoom).addClass('zoom_'+this.zoomSlider.value);
		//FIXME $('svg_overlay').className = 'zoom_'+this.zoomSlider.value;
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
	handleMouseWheel: function(evt, delta) {
		if (this.state == 'zoom') { 
			return;
		}

		if(evt.preventDefault) {
			evt.preventDefault();
		}

		evt.returnValue = false;

		/*
		var delta;
		if(evt.wheelDelta)
			delta = evt.wheelDelta / 360; // Chrome/Safari
		else
			delta = evt.detail / -9; // Mozilla
		*/
		var p = this.getEventPoint(evt);
		var threshold = evt.gesture ? 1 : 0;
		if (delta > threshold) { 
			this.zoom('in', p);
		} else { 
			this.zoom('out', p);
		}
	},

/**
 * Handle mouse move event.
 */
	handleMouseMove: function(evt) {
		if(evt.preventDefault) {
			evt.preventDefault();
		}
		evt.returnValue = false;

		var g = $('#graph0');

		if(this.state == 'pan') {
			var p = this.getEventPoint(evt).matrixTransform(this.stateTf);
			this.setCTM(this.ctm.translate(p.x - this.stateOrigin.x, p.y - this.stateOrigin.y));
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
		var g = $('#graph0')[0];

		this.state = 'pan';
		$(evt.target).css('cursor','move');
		$('#svgscreen').css('cursor','move');
		//this.updateCTM();

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
			$('#graph0').css('display', '');
			$(evt.target).css('cursor', '');
			this.state = '';
		}
		// Quit pan mode
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
		//FIXME this.zoomSlider.setValue(d);
		this.setZoomValue(d, p);
	},
	setZoomValue: function(value, zoom_point) {
		if(this.state == 'zoom' || this.current_zoom == value) { return; }
		if (this.zoom_event) { 
			window.clearTimeout(this.zoom_event);
		}
		this.state = 'zoom';
		var do_zoom = window.setTimeout($.proxy(function() {
			$('#zoomSlider').slider('value', value);
			this.previous_zoom = this.current_zoom;
			this.current_zoom = value;
			this.zoomSlider.value = value;
			var scale = (Math.pow(this.zoom_delta, value - this.default_zoom)*this.initial_scale) /this.ctm.a;
			this.SVGzoom(scale, zoom_point);
		},this), 100);
	},
	SVGzoom: function(scale, center) { 
		$('#'+this.graphdiv).addClass('zooming');
		if (! center) { center = this.calculateCenter(); }
		var p = center.matrixTransform(this.stateTf);
		var k = this.matrixToTransform(this.ctm.translate(p.x, p.y).scale(scale).translate(-p.x, -p.y));
		//FIXME These should be combined, but callback only run 2nd time
		$('#graph0').animate({svgTransform:k},500);
		$('#underlay_graph0').animate({svgTransform:k},500, 
			$.proxy(function() {
				this.setZoomFilters();
				$('#'+this.graphdiv).removeClass('zooming');
				this.updateCTM();
				this.updateDefaultScale();
				if(this.NodeViz.afterZoom) {
					this.NodeViz.afterZoom();
				}
				this.state = '';
			},this)
		);
		this.zoom_point = null;
	},
	calculateCenter: function() {
  		var center = this.root.createSVGPoint();
		center.x = $('#svg_overlay').position().left + ($('#svg_overlay').width() /2);
		center.y = $('#svg_overlay').position().top + ($('#svg_overlay').height() /2);
		return center;
	},
	formatLabels: function(dom_element, graph_element) {
		var label = dom_element.getElementsByTagName('text')[0];
		var ulabel = $('#underlay_'+graph_element.id+' text')[0];
		
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
	resize: function() {
		if (this.state == 'resize') { return; }
		if (this.resize_event) { 
			window.clearTimeout(this.resize_event);
		}
		this.state = 'resize';
		this._parent_resize = this._super;
		this.resize_event = window.setTimeout($.proxy(function() {
			var old_dim = this.graphDimensions;
			var old_center = this.getElementCenter('graph0');
			this._parent_resize();
			$('#svg_overlay, #image').each($.proxy(function(index, e) {
				if (e) { 
					e.childNodes[0].setAttribute('height', this.graphDimensions.height);
					e.childNodes[0].setAttribute('width', this.graphDimensions.width);
				}
			}, this));

			//var testcenter = this.calculateCenter();
			//$('#centertest').remove();
			//$('#images').append($('<div>').attr({'id': 'centertest', 'style': 'position: absolute; opacity: .2; background: pink; z-index: 1000; top: 0px; left: 0px; width:'+testcenter.x+'px; height: '+testcenter.y+'px;'}));

			if ($('#graph0').length) { this.updateCTM(); }

			var new_dim = this.graphDimensions;
			var old_min = old_dim.width < old_dim.height ? 'width' : 'height';
			var new_min = new_dim.width < new_dim.height ? 'width' : 'height';
			var scale = new_dim[new_min]/old_dim[old_min]

			var p = this.getElementCenter('graph0');
			var k = this.setCTM(this.ctm.translate(p.x, p.y).scale(scale).translate(-p.x, -p.y));

			var new_center = this.getElementCenter('graph0');
			this.setCTM(this.ctm.translate(new_center.x - old_center.x, new_center.y - old_center.y))

			this.state = '';
			delete this._parent_resize;
		}, this), 200);
	},
	updateCTM: function() {
		var ctm = $('#graph0')[0].getCTM();
		if (ctm) { 
			this.ctm = ctm;
			this.stateTf = ctm.inverse();
		}
	},
	updateDefaultScale: function() {
		var original_size = $(gf.renderers.GraphImage.root).children('g')[0].getBBox();
		var sizing_dimension = this.graphDimensions.width > this.graphDimensions.height ? 'height' : 'width';
		this.initial_scale = this.graphDimensions[sizing_dimension] / original_size[sizing_dimension];
	},
	matrixToTransform: function(matrix) {
		return "matrix(" + matrix.a + "," + matrix.b + "," + matrix.c + "," + matrix.d + "," + matrix.e + "," + matrix.f + ")";
	},
	zoomControlsHTML: "\
		<div id='zoomcontrols'>\
			<span id='zoomin' class='zoomin' alt='Zoom In' title='Zoom In'>[+]</span>\
			<div id='zoomSlider' class='slider'></div>\
			<span id='zoomout' class='zoomout' alt='Zoom Out' title='Zoom Out'>[-]</span>\
			<span id='zoomreset' class='zoomreset' alt='Reset Zoom' title='Reset Zoom'>[0]</span>\
		</div>\
	"
}
);
