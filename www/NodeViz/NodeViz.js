$.Class("NodeViz", {}, {
	init: function(options) { 
		this.options = {
			timeOutLength : 100,
			errordiv : 'error',
			loadingdiv: 'loading',
			lightboxdiv : 'lightbox',
			lightboxscreen : 'lightboxscreen',
			optionsform : 'graphoptions',
			NodeVizPath : 'NodeViz/',
			disableAutoLoad : 0,
			functions:{},
			image: {},
			list: {}

		}
		$.extend(this.options, options);
		if (! this.prefix) { 

			if (typeof(NodeVizCounter) == 'undefined') { 
				NodeVizCounter = 1;
			} else {
				NodeVizCounter++;
			}
			this.options.prefix = 'nv'+NodeVizCounter+'_';
		}
		if (! $('#'+this.options.errordiv).length) { 
			$(document.body).prepend($("<div/>").attr('id', this.options.errordiv).hide());
		}
		if (! $('#'+this.options.loadingdiv).length) { 
			$(document.body).prepend($("<div/>").attr('id', this.options.loadingdiv).hide());
		}
		this.renderers = {};
		this.requests = [];
		this.previous_event = {};
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
			if ($('#'+this.options.optionsform).length) {
				$('#'+this.options.optionsform).change($.proxy(this.reloadGraph, this));
			}
			this.reloadGraph();
		}
		$(window).resize($.proxy(this.resize, this));
	},
	checkResponse: function(response) {
		var statusCode = null;
		var statusString = "Unknown response from server: ";  
		if (response.status == '200') { 
			var responseData;
			if (typeof(response.responseJSON) != 'undefined') {
				responseData = response.responseJSON;
			} else {
				try { 
					responseData = $.parseJSON(response.responseText); 
				} catch (e) { 
					responseData = response.responseText;
				} 
			}
			if ( typeof(responseData) == 'undefined' || ! responseData.statusCode){  //NO STATUS CODE
			   this.reportError(-1, statusString+' Response was <div class="code">'+this.escapeHTML(response.responseText)+'</div>');
			} else if(responseData.statusCode == 1) { //EVERYTHING OK
				return responseData.data;
			} else {  //STATUS INDICATES AN ERROR
			   this.reportError(responseData.statusCode, this.escapeHTML(responseData.statusString), responseData.data);
			}
		} else {
			this.reportError(response.status, this.escapeHTML(response.statusText));
		}
		this.killRequests();
		return 0;	
	},
	reportError: function(code, message, data) {
		$('.loading').parent().hide();
		this.hideLightbox();
		var error = "We're sorry, an error has occured: <span class='errorstring'>"+message+"</span><span class='errordetails'><span class='errorcode'>"+code+"</span>";
		if (data) {
			error += "<li>"+data['location'];
			error += "<li><a href='"+data['graphfile']+"'>Graph File</a></li>";
			error += "<li><a href='"+data['dot']+"'>Dot File</a></li>";
		}
		error += "</span>";
		if(! $('#'+this.options.errordiv + '#errormsg').length) { 
			$('#'+this.options.errordiv).append($("<div/>").attr('id', 'errormsg'));
		}
		$('#'+this.options.errordiv + ' #errormsg').html(error);
		$('#'+this.options.errordiv).show();
	},
	clearError: function() {
		$('#'+this.options.errordiv + ' #errormsg').empty();
		$('#'+this.options.errordiv).hide();
	},
	timeOutExceeded: function(request) {
		statusCode=10;
		statusString = "Server took too long to return data, it is either busy or there is a connection problem";
		request.abort();
		this.reportError(statusCode, statusString);
	},
	onLoading: function(div) {
		this.loading($('#'+div));
	},
	loading: function(element) {
		element.html("<span class='loading' style='display: block; text-align: center; margin: 20px; background-color: white;'><img class='loadingimage' src='images/loading.gif' alt='Loading Data' /><br /><span class='message'>Loading...</span></span>").show();
	},
	killRequests: function() {
		$(this.requests).each($.proxy(function(index,r) {
			if (! r) { return; }
			if (r.transport && r.transport.readyState != 4) { 
				r.abort();
			}
		}, this));
		this.requests.length=0;
		$('.loading').remove();
	},
	getGraphOptions: function() {
		this.params = $('#graphoptions').serializeArray();
		this.appendParam('prefix', this.prefix);
		this.invokeRenderers('appendOptions');
		return this.params;
	},
	resetGraph: function(params) {
		this.invokeRenderers('reset');
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
			$.proxy(function(responseData) {
				//console.timeEnd('fetch');
				this.data = responseData.graph.data;
				//console.time('render');
				this.invokeRenderers('render', [responseData]);
				if (this.graphLoaded) {
					this.graphLoaded();
				}
				//FIXME - do we need to 'defer' this?
				$('#'+this.options.loadingdiv).fadeOut();
				//console.timeEnd('render');
				//	console.timeEnd('load');
			}, this),
			$.proxy(function() { this.onLoading(this.options.loadingdiv); }, this)
		);
	},
	panToNode: function(id,level,offset) {
		//zooms graph to node if in svg mode
		if (this.options.useSVG ==1) {
			this.renderers['GraphImage'].panToNode(id, level, offset);
		}
	},
	zoom: function(zoomlevel) {
		//zooms graph if in svg mode
		if (this.options.useSVG ==1) {
			this.renderers['GraphImage'].zoom(zoomlevel);
		}
	},
	highlightNode: function(id, noshowtooltip, renderer) {
		id = id.toString();
		if (! id) { return; }
		//if(typeof this.data.nodes[id] == 'undefined') { id = this.current['node']; }
		if (this.data.nodes[id]) {
			this.unhighlightNode(this.current['node']);
			this.current['node'] = id;
			this.invokeRenderers('highlightNode', [id, noshowtooltip, renderer]);
		}
	},
	unhighlightNode: function(id) {
		if (typeof(id) == 'object') { id = this.current.node; }
		if (! id) { return; }
		id = id.toString();
		this.invokeRenderers('unhighlightNode', [id]);
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
		this.invokeRenderers('selectNode', [id]);
		this.current.network = id;
	},
	unselectNode: function(fade) {
		if (this.current.network == '') { return; }
		this.invokeRenderers('unselectNode', [this.current.network, fade]);
		//this.highlightNode(this.current.network);
		this.current.network = '';
	},
	selectEdge: function(id) {
		var params = this.getGraphOptions();
		this.appendParam('action', 'edgeDetails');
		this.appendParam('edgeid', id);
		this.showLightbox('');
		this.fetchRemoteData(params, $.proxy(this.showLightbox, this), $.proxy(function() { this.onLoading(this.options.lightboxdiv+'contents'); }, this));
	},
	unselectEdge: function() {
		this.hideLightbox();
	},
	showLightbox: function(contents) { 
		this.clearError();
		if (! $('#'+this.options.lightboxdiv).length) { 
			$(document.body).prepend($('<div/>').attr({'id': this.options.lightboxdiv}));
			$('#'+this.options.lightboxdiv).prepend($('<img/>').attr({'id': this.options.lightboxdiv+'close', 'src': 'images/close.png', 'alt': 'Close', 'class': 'close'}));
			$('#'+this.options.lightboxdiv+'close').click($.proxy(this.hideLightbox, this));
			$('#'+this.options.lightboxdiv).append($('<div>').attr({'id': this.options.lightboxdiv+'contents'}));
		}
		if (! $('#'+this.options.lightboxscreen).length) { 
			$(document.body).prepend($('<div/>').attr({'id': this.options.lightboxscreen}));
			$('#'+this.options.lightboxscreen).click($.proxy(this.hideLightbox, this));
		}
		$('#'+this.options.lightboxdiv+'contents').html(contents);
		$('#'+this.options.lightboxdiv).show();
		$('#'+this.options.lightboxscreen).show();
	},
	hideLightbox: function() {
		if (! $('#'+this.options.lightboxdiv)) {  return; }
		$('#'+this.options.lightboxdiv).hide();
		$('#'+this.options.lightboxscreen).hide();
		$('#'+this.options.lightboxdiv+'contents').empty();
	},
	fetchRemoteData: function(params, callback, loading) {
		var request = $.ajax(this.options.NodeVizPath+'/request.php', {
			data: params,
			timeout: this.options.timeOutLength*1000,
			beforeSend: loading,
			context: this,
			onTimeOut: this.timeOutExceeded,
			complete: function(json,response) {
				var responseData = this.checkResponse(json);
				if (responseData) {
					callback(responseData);
				}
			},
		});
		this.requests.push(request);
	},
	resize: function() {
		this.invokeRenderers('resize');
	},
	addEvents: function(dom_element, graph_element, element_type, renderer) {
		var eventslist = ['mouseover', 'mousemove','mouseout', 'mouseenter', 'mouseleave' , 'mouseup', 'mousedown', 'click', 'dblclick'];
		$(eventslist).each($.proxy(function(index,eventtype) {
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
			if (action != '' || (this.options.functions['pre_'+eventtype] || this.options.functions['post_'+eventtype])) {
				$(dom_element).on(eventtype, $.proxy(function(evt) { 
					//don't do the action if is immediately after the same action on the same graph element
					if(eventtype == 'click' || eventtype == 'dblclick' || eventtype == 'mousedown' || eventtype == 'mouseup') {
						if (this.previous_event[eventtype] && graph_element && graph_element.id == this.previous_event[eventtype].target) { 
								return false; 
						} else {
							this.previous_event[eventtype] = {'target': graph_element.id, 'action': eventtype};
							window.setTimeout($.proxy(function() { this.previous_event[eventtype] = {}; }, this), 200);
						}
					}
					//This is supposed to prevent triggering networks on drag, but doesn't seem to work? disabling
					/*if ((eventtype == 'click' || eventtype == 'mouseup') && renderer == 'svg') { //I can't remember what this is for
						var origin = this.renderers.GraphImage.getEventPoint(evt).matrixTransform(this.renderers.GraphImage.stateTf); 
						var state_origin = this.renderers.GraphImage.stateOrigin; 
						if (state_origin.x != origin.x || state_origin.y != origin.y) {
							return;
						}
					}*/
					this.do_function(this.options.functions['pre_'+eventtype], evt, dom_element, graph_element, element_type, renderer);
					this.do_function(action, evt, dom_element, graph_element, element_type, renderer);
					this.do_function(this.options.functions['post_'+eventtype], evt, dom_element, graph_element, element_type, renderer);

				}, this));
			}
		}, this));
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
	},
	escapeHTML: function(string) { 
		return $('<span/>').text(string).html();
	},
	invokeRenderers: function(method, args) { 
		$.map(this.renderers, function(o) { 
			$(['pre_', '', 'post_']).each(function(i, prefix) { 
				if (typeof(o[prefix+method]) == 'function') { 
					//console.time(o.Class.fullName+prefix+method);
					o[prefix+method].apply(o, args); 
					//console.timeEnd(o.Class.fullName+prefix+method);
				}
			});
		});
	},
	appendParam: function(key, value) { 
		this.params.push({name: key, value: value});
	},
	do_function: function(func, evt, dom_element, graph_element, element_type, renderer) {
		if (func) { 
			if ($.isFunction(func)) { 
				func.apply(this, [ evt, dom_element, graph_element, element_type, renderer]);
			} else {
				eval(func);
			}
		}
	}
	
});


(function($){
  $.fn.clonePosition = function(element, options){
    var options = $.extend({
      cloneWidth: true,
      cloneHeight: true,
      offsetLeft: 0,
      offsetTop: 0
    }, (options || {}));
    
    var offsets = $(element).offset();
    
    $(this).css({
      position: 'absolute',
      top: (offsets.top + options.offsetTop) + 'px',
      left: (offsets.left + options.offsetLeft) + 'px'
    });
    
    if (options.cloneWidth) $(this).width($(element).width());
    if (options.cloneHeight) $(this).height($(element).height());
    
    return this;
  }
	$.fn.values = function(object) {
		if(! object) { object = this[0]; }
		var values = [];
		for(var key in object) {
			if(object.hasOwnProperty(key)) { 
				values.push(object[key]);
			}
		}
		return $(values);
	}

	$.fn.keys = function(o) {
		if(! o) { o = this[0]; }
		var hasOwnProperty = Object.prototype.hasOwnProperty,
			hasDontEnumBug = !{toString:null}.propertyIsEnumerable("toString"),
			DontEnums = [
				'toString',
				'toLocaleString',
				'valueOf',
				'hasOwnProperty',
				'isPrototypeOf',
				'propertyIsEnumerable',
				'constructor'
			],
			DontEnumsLength = DontEnums.length;
	  
		if (typeof o != "object" && typeof o != "function" || o === null)
			throw new TypeError("Object.keys called on a non-object");
	 
		var result = [];
		for (var name in o) {
			if (hasOwnProperty.call(o, name))
				result.push(name);
		}
	 
		if (hasDontEnumBug) {
			for (var i = 0; i < DontEnumsLength; i++) {
				if (hasOwnProperty.call(o, DontEnums[i]))
					result.push(DontEnums[i]);
			}  
		}
	 
		return $(result);
	}
}
)(jQuery);

function format(string) {
	return string ? string.toString().split( /(?=(?:\d{3})+(?:\.|$))/g ).join( "," ) : '';
}

function toWordCase(string){
	return string ? string.toLowerCase().replace( /(^|\s)([a-z])/g , function(m,p1,p2){ return p1+p2.toUpperCase(); } ) : '';
}
