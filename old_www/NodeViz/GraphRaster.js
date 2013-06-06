var GraphRaster = Class.create(GraphImage, {
	initialize: function($super, NodeViz) {
		$super(NodeViz);
		if (! $('highlight')) { 
			$('graphs').insert({ top: new Element('div', {'id': 'highlight'}) });
		}
		$('highlight').innerHTML += this.highlightImageHTML;
	},
	reset: function($super) {
		$super();
		if ($('G')) { 
			var data = this.NodeViz.data;
			$('G').descendants().each(function(a) { 
				if (data.edges[a.id] || data.nodes[a.id]) { 
					$(a).stopObserving();
				}
			});
			$('G').remove();
		}
		$('highlight').stopObserving();
		$('highlightimg').stopObserving();
	},
	render: function($super, responseData) { 
		$super();
		var image = responseData.image;
		var overlay = responseData.overlay;
		var map = " usemap='#G'";
		$('images').update("<img id='image' "+map+" border='0' src='"+image+"' />"+overlay);
		$('G').descendants().each(function(a) { 
			if (this.NodeViz.data.edges[a.id]) { 
				var edge = this.NodeViz.data.edges[a.id];
				this.NodeViz.addEvents($(a), edge, 'edge', 'raster');
				//Event.observe($(a), 'mouseout', this.hideTooltip.bind(this)); 
				Event.observe($(a), 'mousemove', this.mousemove.bind(this));
				//if (edge.onMouseover != '') { Event.observe($(a), 'mouseover', function(e) { eval(edge.onMouseover); }.bind(this)); }
				//if (edge.onClick != '') { Event.observe($(a), 'click', function(e) { eval(edge.onClick); }.bind(this)); }
			} else if (this.NodeViz.data.nodes[a.id]) { 
				var node = this.NodeViz.data.nodes[a.id];
				this.NodeViz.addEvents($(a), node, 'node', 'raster');
				//if (node.onMouseover != '') { Event.observe($(a), 'mouseover', function(e) { eval(node.onMouseover); }.bind(this)); }
				//if (node.onClick != '') { Event.observe($(a), 'click', function(e) { eval(node.onClick); }.bind(this)); }
				//Event.observe($(a), 'mouseout', function(e) { this.NodeViz.unhighlightNode(a.id); }.bind(this)); 
				var shape = $(a).getAttribute('shape').toLowerCase();
				var coords = [];
				if (shape == 'poly') {
					//If it's a polygon, we need to get the bounding box and just highlight that
					var allcoords = $(a).getAttribute('coords').split(/[, ]/);
					var xs = [];
					var ys = [];
					while(allcoords.length) {
						xs.push(allcoords.shift());
						ys.push(allcoords.shift());
					}
					coords = [Math.min.apply(Math, xs), Math.min.apply(Math, ys), Math.max.apply(Math, xs), Math.max.apply(Math, ys)];
				} else {
					coords = $(a).getAttribute('coords').split(',');
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
		}, this);
		this.setupListeners();
	},
	setupListeners: function($super) {
		$super();
		Event.observe($('highlight'), 'mousemove', this.mousemove.bind(this));
		Event.observe($('highlight'), 'click', this.NodeViz.selectNode.bind(this.NodeViz));
		//Event.observe($('highlightimg'), 'click', this.NodeViz.selectNode.bind(this.NodeViz));
		//Event.observe($('highlight'), 'mouseover', this.highlightNode);
		Event.observe($('highlightimg'), 'mouseout', this.NodeViz.unhighlightNode.bind(this.NodeViz));
		Event.observe($('highlight'), 'mouseout', this.NodeViz.unhighlightNode.bind(this.NodeViz));
	},
	highlightNode: function($super, id, text, noshowtooltip) {
		$super(id, text, noshowtooltip);
		var node = this.NodeViz.data.nodes[id];
		$('highlight').style.width = parseFloat(node['width']) +2 +'px';
		$('highlight').style.height = parseFloat(node['height']) +2 +'px';
		$('highlight').style.top = parseFloat(node['posy']) -1 + this.offsetY + 'px';
		$('highlight').style.left = parseFloat(node['posx']) -1 + this.offsetX + 'px';
		$('highlight').style.visibility = 'visible';
		if (node['shape'] != 'circle' && ! (node['shape'] == 'polygon' && node['sides'] > 5)) { 
			$('highlight').addClassName('selected');
			$('highlightimg').style.visibility = 'hidden';
		} else {
			$('highlight').removeAttribute('class');
			$('highlightimg').style.visibility = 'visible';
		}
	},
	unhighlightNode: function($super, id) {
		$super(id);
		$('highlight').style.visibility = 'hidden';
		$('highlightimg').style.visibility = 'hidden';
	},

	highlightImageHTML: "\
		<div id='highlight'><img id='highlightimg' alt='' src='images/highlight.gif' /></div>\
	"

});
