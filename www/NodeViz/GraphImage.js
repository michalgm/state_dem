$.Class("GraphImage", {}, {
	init: function(NodeViz) {
		this.NodeViz = NodeViz;
		$.extend(this, NodeViz.options.image);	
		if (! $('#tooltip').length) { 
			$(document.body).prepend($('<div/>').attr('id', 'tooltip'));
		}
		this.tooltip = $('#tooltip');
		$('#'+this.graphdiv).html("<div id='images'></div><div id='imagescreen' style='display:none;'></div>");
		this.graphDimensions = this.getDimensions();
		$('#imagescreen').clonePosition($('#'+this.graphdiv));
	},
	reset: function() {
		$('#image').unbind();
		this.graphDimensions = this.getDimensions();
	},
	render: function(responseData) {
	},
	appendOptions: function() {
		this.NodeViz.appendParam('useSVG', this.NodeViz.options.useSVG);
		this.NodeViz.appendParam('graphWidth', this.graphDimensions.width);
		this.NodeViz.appendParam('graphHeight', this.graphDimensions.height);
	},
	setOffsets: function() { 
		if ($('#image')) {
			this.offsetX = $('#image').offset().left || 0;
			this.offsetY = $('#image').offset().top;
			//this.tooltipOffsetX = Position.cumulativeOffset(this.graphdiv)[0] - 15;
			//tooltipOffsetY = Position.cumulativeOffset($('graphs'))[1];
			this.tooltipOffsetX = 5;
			this.tooltipOffsetY = -5;
		}
		$('#imagescreen').css({top: $('#'+this.graphdiv).offset().top, left: $('#'+this.graphdiv).offset().left});
	},
	
	//Catches mousemove events
	mousemove: function(e) {
		if(this.tooltip.css('visibility') == 'visible' || this.tooltip.css('left') == '0px') { 
			var mousepos = { 'x': e.pageX, 'y': e.pageY };
			this.moveTooltip(mousepos);
		}
	},
	setupListeners: function() { 
		$('#image').mousemove($.proxy(this.mousemove, this));
		this.tooltip.mousemove($.proxy(this.mousemove, this));
		this.tooltip.mouseout($.proxy(this.hideTooltip, this));
	},
	resize: function() {
		this.graphDimensions = this.getDimensions();
		this.setOffsets();
	},
	moveTooltip: function(mousepos) { 
		if(this.tooltip.html() == '') { this.tooltip.html('1'); } //if it's empty, it won't be positioned right
		if(typeof(this.tooltipOffsetY) == 'undefined') { this.setOffsets(); }
		this.tooltip.css({'top': (mousepos['y']+ this.tooltipOffsetY - this.tooltip.outerHeight()) + 'px', 'left': (mousepos['x']  + this.tooltipOffsetX) + 'px'});

	},
	showTooltip: function(label) {
		if (typeof(this.state) != 'undefined' && this.state !== '') { return; }
		if(label != '') { 
			if (this.tooltip.position().left) {
				this.tooltip.html(label);
				this.tooltip.css({visibility:'visible'}); //show the tooltip
			}
		}
	},

	hideTooltip: function() { 
		this.tooltip.css('visibility','hidden'); //hide the tooltip first - somehow this makes it faster
		$('#images').css('cursor','default');	
	},
	highlightNode: function(id, noshowtooltip, renderer) {
		if(typeof(this.tooltipOffsetY) == 'undefined') { this.setOffsets(); }
		if (! noshowtooltip) { 
			id = id.toString();
			if (renderer == 'list') { 
				if (this.NodeViz.options.useSVG == 1) { 
					var elem = $('#'+id).children('polygon, ellipse')[0];
					var box = elem.getBBox();
					var svgp = this.root.createSVGPoint();
					svgp.x = box.x + box.width;
					svgp.y = box.y;
					var point = svgp.matrixTransform(this.ctm);
					point.x += this.offsetX;
					point.y += this.offsetY;
					this.moveTooltip(point);
				} else {
					var elem = $('#'+id);
					var coords = elem.attr('coords').split(',');
					var offset = $('#image').offset();
					this.moveTooltip({'x': parseInt(coords[2]) + offset.left, 'y': parseInt(coords[1])});
				}
			}
			this.showTooltip(this.NodeViz.data.nodes[id].tooltip); //Set the tooltip contents
		}
	},
	unhighlightNode: function(id) { 
		this.hideTooltip();
	},
	selectNode: function(id) {
	},
	unselectNode: function(id) {
	},
	getDimensions: function() { 
		return {
			height: $('#'+this.graphdiv).height(),
			width: $('#'+this.graphdiv).width()
		};
	}
});
