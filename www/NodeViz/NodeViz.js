MOUSEENTER_MOUSELEAVE_EVENTS_SUPPORTED = 0;
var NodeViz = Class.create();

NodeViz.prototype = {
	initialize: function(options) { 
		this.options = {
			timeOutLength : 100,
			errordiv : 'error',
			loadingdiv: 'loading',
			lightboxdiv : 'lightbox',
			lightboxscreen : 'lightboxscreen',
			optionsform : 'graphoptions',
			NodeVizPath : 'NodeViz/',
			disableAutoLoad : 0
		}
		Object.extend(this.options, options);
		if (! this.prefix) { 

			if (typeof(NodeVizCounter) == 'undefined') { 
				NodeVizCounter = 1;
			} else {
				NodeVizCounter++;
			}
			this.options.prefix = 'nv'+NodeVizCounter+'_';
		}
		if (! $(this.options.errordiv)) { 
			$(document.body).insert({ top: new Element('div', {'id': this.options.errordiv}) });
			$(this.options.errordiv).hide();
		}
		if (! $(this.options.loadingdiv)) { 
			$(document.body).insert({ top: new Element('div', {'id': this.options.loadingdiv}) });
			$(this.options.loadingdiv).hide();
		}
		this.renderers = {};
		this.requests = [];
		if (this.options.image.graphdiv) { 
			if ((typeof this.options.useSVG === 'undefined' && document.implementation.hasFeature("http://www.w3.org/TR/SVG11/feature#BasicStructure", "1.1")) || this.options.useSVG == 1) { 
				this.options.useSVG = 1;
				this.renderers['GraphImage'] = new GraphSVG(this);
			} else {
				this.options.useSVG = 0;
				this.renderers['GraphImage'] = new GraphRaster(this);
			}
		}
		if(this.options.list.listdiv) { 
			this.renderers['GraphList'] = new GraphList(this);
		}
		if (this.options.disableAutoLoad != 1 ) {
			if ($(this.options.optionsform)) {
				Element.observe($(this.options.optionsform), 'change', this.reloadGraph.bind(this));
			}
			this.reloadGraph();
		}
	},
	checkResponse: function(response) {
		var statusCode = null;
		var statusString = "Unknown response from server: ";  
		if (response.status == '200') { 
			var responseData;
			if (typeof(response.responseJSON) != 'undefined') {
				responseData = response.responseJSON;
			} else {
				responseData = response.responseText;
			}
			if ( typeof(responseData) == 'undefined' || ! responseData.statusCode){  //NO STATUS CODE
			   this.reportError(-1, statusString+' Response was <div class="code">'+response.responseText.escapeHTML()+'</div>');
			} else if(responseData.statusCode == 1) { //EVERYTHING OK
				return responseData.data;
			} else {  //STATUS INDICATES AN ERROR
			   this.reportError(responseData.statusCode, responseData.statusString.escapeHTML(), responseData.data);
			}
		} else {
			this.reportError(response.status, response.statusText.escapeHTML());
		}
		this.killRequests();
		return 0;	
	},
	reportError: function(code, message, data) {
		$$('.loading').each(function(l) { l.up().hide(); });
		this.hideLightbox();
		var error = "We're sorry, an error has occured: <span class='errorstring'>"+message+"</span><span class='errordetails'><span class='errorcode'>"+code+"</span>";
		if (data) {
			error += "<li>"+data['location'];
			error += "<li><a href='"+data['graphfile']+"'>Graph File</a></li>";
			error += "<li><a href='"+data['dot']+"'>Dot File</a></li>";
		}
		error += "</span>";
		$(this.options.errordiv).update(error);
		$(this.options.errordiv).show();
	},
	clearError: function() {
		$(this.options.errordiv).update('');
		$(this.options.errordiv).hide();
	},
	timeOutExceeded: function(request) {
		statusCode=10;
		statusString = "Server took too long to return data, it is either busy or there is a connection problem";
		request.abort();
		this.reportError(statusCode, statusString);
	},
	onLoading: function(div) {
		this.loading($(div));
	},
	loading: function(element) {
		$(element).innerHTML = "<span class='loading' style='display: block; text-align: center; margin: 20px; background-color: white;'><img class='loadingimage' src='images/loading.gif' alt='Loading Data' /><br /><span class='message'>Loading...</span></span>";
		$(element).show();
	},
	killRequests: function() {
		this.requests.each(function(r, index) {
			if (! r) { return; }
			if (r.transport && r.transport.readyState != 4) { 
				r.abort();
			}
		}, this);
		this.requests.length=0;
		$$('.loading').each(function (e) { e.remove(); });
	},
	getGraphOptions: function() {
		this.params = Form.serialize($('graphoptions'), true);
		this.params['prefix'] = this.prefix;
		$H(this.renderers).values().invoke('appendOptions');
		return this.params;
	},
	resetGraph: function(params) {
		$H(this.renderers).values().invoke('reset');
		this.current = {'zoom': 1, 'network': '', 'node': '', 'nodetype': ''};
		this.killRequests();
		this.data = [];
		this.clearError();
	},
	reloadGraph: function(params) {
		//console.time('load');
		//console.time('reset');
		this.resetGraph();
		//console.timeEnd('reset');

		var params = this.getGraphOptions();
		//console.time('fetch');
		this.fetchRemoteData(params, 
			function(responseData) {
				//console.timeEnd('fetch');
				this.data = responseData.graph.data;
				//console.time('render');
				$H(this.renderers).values().invoke('render', responseData);
				if (this.graphLoaded) {
					this.graphLoaded();
				}
				Effect.Fade.defer($(this.options.loadingdiv));
				//console.timeEnd('render');
				//	console.timeEnd('load');
			}.bind(this),
			function() { this.onLoading(this.options.loadingdiv); }.bind(this)
		);
	},
	panToNode: function(id,level) {
		//zooms graph if in svg mode
		if (this.options.useSVG ==1) {
			this.renderers['GraphImage'].panToNode(id, level);
		}
	},
	highlightNode: function(id, noshowtooltip, renderer) {
		id = id.toString();
		if (! id) { return; }
		//if(typeof this.data.nodes[id] == 'undefined') { id = this.current['node']; }
		if (this.data.nodes[id]) {
			this.unhighlightNode(this.current['node']);
			this.current['node'] = id;
			$H(this.renderers).values().invoke('highlightNode', id, noshowtooltip, renderer);
		}
	},
	unhighlightNode: function(id) {
		if (typeof(id) == 'object') { id = this.current.node; }
		if (! id) { return; }
		id = id.toString();
		$H(this.renderers).values().invoke('unhighlightNode', id);
		this.current['node'] = '';
	},
	selectNode: function(id, noscroll) { 
		if (typeof(id) == 'object') { id = this.current.node; }
		id = id.toString();
		if (id == this.current['network']) { 
			this.unselectNode(1);
			return;
		}
		this.unselectNode();
		if(typeof this.data.nodes[id] == 'undefined') { id = this.current['node']; }
		$H(this.renderers).values().invoke('selectNode', id);
		this.current.network = id;
	},
	unselectNode: function(fade) {
		if (this.current.network == '') { return; }
		$H(this.renderers).values().invoke('unselectNode', this.current.network, fade);
		//this.highlightNode(this.current.network);
		this.current.network = '';
	},
	selectEdge: function(id) {
		var params = this.getGraphOptions();
		params.action = 'edgeDetails';
		params.edgeid = id;
		this.showLightbox('');
		this.fetchRemoteData(params, this.showLightbox.bind(this), function() { this.onLoading(this.options.lightboxdiv+'contents'); }.bind(this));
	},
	unselectEdge: function() {
		$(this.options.lightboxdiv+'contents').update();
		$(this.options.lightboxdiv).hide();
		$(this.options.lightboxscreen).hide();
	},
	showLightbox: function(contents) { 
		this.clearError();
		if (! $(this.options.lightboxdiv)) { 
			$(document.body).insert({ top: new Element('div', {'id': this.options.lightboxdiv}) });
			$(this.options.lightboxdiv).insert({top: new Element('img', {'id': this.options.lightboxdiv+'close', 'src': 'images/close.png', 'alt': 'Close', 'class': 'close'}) });
			$(this.options.lightboxdiv+'close').observe('click', this.hideLightbox.bind(this));
			$(this.options.lightboxdiv).insert({ bottom: new Element('div', {'id': this.options.lightboxdiv+'contents'}) });
		}
		if (! $(this.options.lightboxscreen)) { 
			$(document.body).insert({ top: new Element('div', {'id': this.options.lightboxscreen}) });
			Event.observe($(this.options.lightboxscreen), 'click', this.hideLightbox.bind(this));
		}
		$(this.options.lightboxdiv+'contents').update(contents);
		$(this.options.lightboxdiv).show();
		$(this.options.lightboxscreen).show();
	},
	hideLightbox: function() {
		if (! $(this.options.lightboxdiv)) {  return; }
		$(this.options.lightboxdiv).hide();
		$(this.options.lightboxscreen).hide();
		$(this.options.lightboxdiv+'contents').update();
	},
	fetchRemoteData: function(params, callback, loading) {
		var request = new Ajax.Request(this.options.NodeVizPath+'/request.php', {
			parameters: params,
			timeOut: this.options.timeOutLength,
			onLoading: loading,
			onTimeOut: this.timeOutExceeded.bind(this),
			evalJS: true,
			sanitizeJSON: true,
			onComplete: function(response, json) {
				var responseData = this.checkResponse(response);
				if (responseData) {
					callback(responseData);
				}
			}.bind(this)
		});
		this.requests.push(request);
	},
	addEvents: function(dom_element, graph_element, element_type, renderer) {
		var eventslist = ['mouseover', 'mousemove','mouseout', 'mouseenter', 'mouseleave' , 'mouseup', 'mousedown', 'click', 'dblclick'];
		eventslist.each(function(eventtype) {
			var action = '';
			if (typeof graph_element[eventtype] != 'undefined') {
				action = graph_element[eventtype];	
			} else if (typeof this.default_events[element_type][eventtype] != 'undefined') {
				action = this.default_events[element_type][eventtype]; 
			}
			//We have to override mouseenter and leave events for raster type because IE 7 & 8 don't seem to support them on image maps
			if (renderer == 'raster') {
				if (eventtype == 'mouseenter') { eventtype = 'mouseover'; }
				else if (eventtype == 'mouseleave') { eventtype = 'mouseout'; }
			}
			if (action != '') {
				Event.observe(dom_element,eventtype, function(evt) { 
					if ((eventtype == 'click' || eventtype == 'mouseup') && renderer == 'svg') {
						origin = this.renderers.GraphImage.getEventPoint(evt).matrixTransform(this.renderers.GraphImage.stateTf); 
						state_origin = this.renderers.GraphImage.stateOrigin; 
						if (state_origin.x != origin.x || state_origin.y != origin.y) {
							return;
						}
					}
					eval(action);
				}.bind(this));
			}
		}.bind(this));
	},
	default_events: { 
		'node': {
			'mouseenter': "this.highlightNode(graph_element.id, 0, renderer);",
			'mouseleave': "if(renderer != 'raster') { this.unhighlightNode(graph_element.id); }",
			'click': "this.selectNode(graph_element.id); this.panToNode(graph_element.id);"
		},
		'edge': {
			'mouseenter': "if(renderer != 'list') { this.renderers.GraphImage.showTooltip(graph_element.tooltip); }",
			'mouseleave': "if(renderer != 'list') { this.renderers.GraphImage.hideTooltip(); }",
			'click': "if(renderer != 'list') { this.selectEdge(graph_element.id); }"
		}
	}
}
