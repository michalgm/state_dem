GraphImage("GraphRaster", {}, {
	init: function(NodeViz) {
		this._super(NodeViz);
		if (! $('#highlight').length) { 
			$(document.body).prepend($('<div/>').attr('id', 'highlight'));
		}
		$('#highlight').html(this.highlightImageHTML);
	},
	reset: function() {
		this._super();
		if ($('#G')) { 
			var data = this.NodeViz.data;
			$('#G').children().each(function(i, a) { 
				if (data.edges[a.id] || data.nodes[a.id]) { 
					$(a).unbind();
				}
			});
			$('#G').remove();
		}
		$('#highlight').unbind();
		$('#highlightimg').unbind();
	},
	render: function(responseData) { 
		this._super();
		var image = responseData.image;
		var overlay = responseData.overlay;
		var map = " usemap='#G'";
		$('#images').html("<img id='image' "+map+" border='0' src='"+image+"' />"+overlay);
		$('#image').mousemove($.proxy(this.mousemove), this);
		$('#G').children().each($.proxy(function(i, a) { 
			if (this.NodeViz.data.edges[a.id]) { 
				var edge = this.NodeViz.data.edges[a.id];
				this.NodeViz.addEvents($(a), edge, 'edge', 'raster');
				//$(a).mouseout($.proxy(this.hideTooltip, this));
				//$(a).mousemove($.proxy(this.mousemove), this);
				//if (edge.onMouseover != '') { Event.observe($(a), 'mouseover', function(e) { eval(edge.onMouseover); }.bind(this)); }
				//if (edge.onClick != '') { Event.observe($(a), 'click', function(e) { eval(edge.onClick); }.bind(this)); }
			} else if (this.NodeViz.data.nodes[a.id]) { 
				var node = this.NodeViz.data.nodes[a.id];
				this.NodeViz.addEvents($(a), node, 'node', 'raster');
				//if (node.onMouseover != '') { Event.observe($(a), 'mouseover', function(e) { eval(node.onMouseover); }.bind(this)); }
				//if (node.onClick != '') { Event.observe($(a), 'click', function(e) { eval(node.onClick); }.bind(this)); }
				//$(a).mouseout($.proxy(function(e) { this.NodeViz.unhighlightNode(a.id); }, this));
				//Event.observe($(a), 'mouseout', function(e) { this.NodeViz.unhighlightNode(a.id); }.bind(this)); 
				var shape = $(a).attr('shape').toLowerCase();
				var coords = [];
				if (shape == 'poly') {
					//If it's a polygon, we need to get the bounding box and just highlight that
					var allcoords = $(a).attr('coords').split(/[, ]/);
					var xs = [];
					var ys = [];
					while(allcoords.length) {
						xs.push(allcoords.shift());
						ys.push(allcoords.shift());
					}
					coords = [Math.min.apply(Math, xs), Math.min.apply(Math, ys), Math.max.apply(Math, xs), Math.max.apply(Math, ys)];
				} else {
					coords = $(a).attr('coords').split(',');
				}
				if (shape == 'circle') {
					node['width'] = parseFloat(coords[2])*2;
					node['height'] = parseFloat(coords[2])*2;
					node['posx'] = parseFloat(coords[0]) - parseFloat(coords[2]);
					node['posy'] = parseFloat(coords[1]) - parseFloat(coords[2]);
				} else {
					node['width'] = parseFloat(coords[2]) - parseFloat(coords[0]);
					node['height'] = parseFloat(coords[3]) - parseFloat(coords[1]);
					node['posx'] = parseFloat(coords[0]);
					node['posy'] = parseFloat(coords[1]);
				}
			}
		}, this));
		this.setupListeners();
	},
	setupListeners: function() {
		this._super();
		$('#highlight').mousemove($.proxy(this.mousemove, this));
		$('#highlight').click($.proxy(this.NodeViz.selectNode, this.NodeViz));
		//Event.observe($('highlightimg'), 'click', this.NodeViz.selectNode.bind(this.NodeViz));
		//Event.observe($('highlight'), 'mouseover', this.highlightNode);
		$('#highlightimg').mouseout($.proxy(this.NodeViz.unhighlightNode, this.NodeViz));
		$('#highlight').mouseout($.proxy(this.NodeViz.unhighlightNode, this.NodeViz));
	},
	highlightNode: function(id, text, noshowtooltip) {
		this._super(id, text, noshowtooltip);
		var node = this.NodeViz.data.nodes[id];
		$('#highlight').css({
			width : parseFloat(node['width']) +2 +'px',
			height : parseFloat(node['height']) +2 +'px',
			top : parseFloat(node['posy']) -1 + this.offsetY + 'px',
			left : parseFloat(node['posx']) -1 + this.offsetX + 'px',
			visibility : 'visible'
		});
		if (node['shape'] != 'circle' && ! (node['shape'] == 'polygon' && node['sides'] > 5)) { 
			$('#highlight').addClass('selected');
			$('#highlightimg').css('visibility','hidden');
		} else {
			$('#highlight').removeClass('selected');
			$('#highlightimg').css('visibility', 'visible');
		}
	},
	unhighlightNode: function(id) {
		this._super(id);
		$('#highlight, #highlightimg').css('visibility', 'hidden');
	},

	highlightImageHTML: "\
		<div id='highlight'><img id='highlightimg' alt='' src='images/highlight.gif' /></div>\
	"

});
