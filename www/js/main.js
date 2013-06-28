// Document Ready

$(function(){

	initGraph();
	$('#infocard').hide();
	$('#infocard .close').click(function() { resetGraph(); });

	$('#pull').click(function(e){
		$('.navlist').slideToggle();
		e.preventDefault();
	});
	$(window).resize(function(){
		if ($(window).width() > 320 && $('.navlist').is(':hidden')) { $('.navlist').removeAttr('style'); }
	});



});

// Functions

function initGraph() {
	var params = $.url().param();
	$(params).keys().each(function(i, k) { 
		$('#graphoptions').append($('<input>').attr({type:'hidden', name:k, value:params[k]}));
	})
	var graphoptions = {
		setupfile: "StateDEM",
		image: {
			graphdiv: 'graphs',
			zoomlevels: '8',
			zoomSliderAxis: 'vertical',
			//zoom_delta:  

		},
		functions: {
			//You can define functions that execute before or after events occuring on graph elements - name them like 'pre_eventname' or 'post_eventname'. They can either be a string to be evaled, or a true function.  The functions take the following arguments:
			//evt: the event object of the triggered event
			//dom_element: the dom_element the event occured on
			//graph_element: the NodeViz graph element data object that the dom_element refers to
			//element_type: either 'node' or 'edge'
			//renderer: the render type that triggered the event (svg, raster, or list)
			pre_click: "console.log('pre_click:'+element_type+' '+renderer)", //this is a lame way to do this
			post_mouseenter: function(evt, dom_element, graph_element, element_type, renderer) { 
				var offset = this.renderers.GraphImage.tooltip.outerWidth() /2;
				this.renderers.GraphImage.tooltipOffsetX = -offset;
				this.renderers.GraphImage.tooltipOffsetY = -20;
			},
			post_click: function(evt, dom_element, graph_element, element_type, renderer) { 
				toggleInfocard(graph_element);
				// console.log(graph_element);
				// console.log(dom_element);
			}
		}
	};
	gf = new NodeViz(graphoptions);
}	

// Extensions

$.extend(NodeViz.prototype, {
	graphLoaded: function() {
	},
	afterInit: function() {
	}
});

$.extend(GraphList.prototype, {
	listNodeEntry: function(node) {
		var label;
		var content = "";
		if (node.label) { 
			label = node.label;
		} else { 
			label = node.id;
		}
		var info = '';
		var chip = "<span class='chip "+ (typeof(node.party) != 'undefined' ? node.party : ('contrib_'+node.contributor_type))+"'></span>";
		var image = (typeof(node.image) != 'undefined' && node.image != 'null') ?  "<img src='"+node.image+"' style='width: 20px; border: 1px solid #666;'/>" : "";

		if (node.type == 'candidates') {
			if (node.party) { 
				info = "<span class='info'>"+node.party+"</span>";
				info += "<div class='details'>\
					<a class='profile_link' href='?candidate_ids="+node['id']+"'><img class='link_icon' src='images/go-next.png'/>Profile</a>\
					<a class='nimsp_link' href='http://www.followthemoney.org/database/uniquecandidate.phtml?uc="+node['unique_candidate_id']+"' target='_new'><img class='link_icon' src='images/go-next.png'/>NIMSP Profile</a>\
				</div>";

			}
		} else {
			//if (node.contributor_type == 'I') { info = 'Individual'; }
			//else if (node.contributor_type == 'C') { info = 'PAC'; }
			//else { info = 'Uncategorized'; }
			//info = "<span class='info'>"+info+"</span>";
			var industry = 'Unknown';
			if (node.industry != '--') { 
				industry = node.industry;
			}
			info += "<a class='profile_link' href='?company_ids="+node['id']+"'><img class='link_icon' src='images/go-next.png'/>Profile</a>";
			info += "<span class='industry'>"+industry+"</span><br style='clear:both'/>";
		}
		return image+"<span class='label'>"+label+"</span><span class='amount'>$"+format(Math.round(node['total_dollars']))+'</span><br/>'+chip+info;
	},
	listSubNodeEntry: function(node, parentNode, edgetype, direction) { 
		var label;
		var node_class = direction == 'to' ? 'to_node_item' : 'from_node_item'; 
		if (node.label) { 
			label = node.label;
		} else { 
			label = node.id;
		}
		var info = 'Uncategorized';
		var edge = this.NodeViz.data.edges[node.relatedNodes[parentNode.id][0]];
		if (node.type == 'candidates') {
			if (node.party) { info = node.party; }
		} else if (node.contributor_type) {
			if (node.contributor_type == 'I') { info = 'Individual'; }
			else if (node.contributor_type == 'C') { info = 'PAC'; }
		}
		info = "<span class='info'>"+info+"</span>";
		var chip = "<span class='chip "+ (typeof(node.party) != 'undefined' ? node.party : ('contrib_'+node.contributor_type))+"'></span>";
		var link = "<span class='details_link'><img src='NodeViz/icons/magnifier.png' alt='View Details' title='View Details'/></span>";
		return "<span class='"+node_class+" label' onclick=\"gf.selectNode('"+node.id+"'); gf.panToNode('"+node.id+"');\">"+label+"</span><span class='amount'>$"+format(Math.round(edge['value']))+'</span><br/>'+chip+info+link;
	},
	sublistHeader: function(node, edgetype, direction) {
		var header = 'Recipients';
		if(direction == 'to') {
			header =  "Donors";
		}
		return "<div class='sublist_header'>"+header+"</div>"
	}
});

function toggleInfocard(node) {
	// console.log(node);

	var card = $('#infocard'),
		node_id = card.data('data-node');

	if (card.is(':hidden') || node_id !== node.id) {
		card.data('data-node',node.id);
		$('#node-title').html(node.label+' <span class="district '+node.party+' ">'+node.district+'</span>');
		var image_url = node.image.split('www');
		$('#node-image img').attr('src',image_url[1]);

		var mockContributionsByYear = [
			{ 'year': 2008, 'contributions': 12345 },
			{ 'year': 2009, 'contributions': 23456 },
			{ 'year': 2010, 'contributions': 34567 },
			{ 'year': 2011, 'contributions': 45678 },
			{ 'year': 2012, 'contributions': 56789 }
		];
		var mockContributionsByIndustry = [
			{ 'group': 'pirates', 'label': 'Pirates', 'contributions': 123456 },
			{ 'group': 'ninja', 'label': 'Ninjas', 'contributions': 234567 },
			{ 'group': 'robots', 'label': 'Robots', 'contributions': 345678 }
		];
		var mockContributionsByParty = [
			{ 'group': 'D', 'label': 'Democrats', 'contributions': 123456 },
			{ 'group': 'R', 'label': 'Republicans', 'contributions': 234567 }
		];

		card.slideDown();

		drawPieChart(mockContributionsByIndustry,'#node-piechart1');
		drawPieChart(mockContributionsByParty,'#node-piechart2');
		drawBarChart(mockContributionsByYear,'#node-barchart');

		$('#masthead').hide();
		// Zoom and center graph
	} else {
		resetGraph();
	}
}

function resetGraph() {
	$('#infocard').slideUp();
	$('#masthead').fadeIn(2000);
	// Reset Zoom
}

/*	==========================================================================
	BAR CHART
	========================================================================== */

function drawBarChart(data,container) {
	var width = $(container).width(),
		height = $(container).height();

	var x = d3.scale.linear().domain([0, data.length]).range([0, width]);
	var y = d3.scale.linear().domain([0, d3.max(data, function(datum) { return datum.contributions; })]).rangeRound([0, height]);

	var svg = d3.select(container).append('svg')
		.attr('width',width)
		.attr('height',height);

	var bars = svg.selectAll('.bar')
		.data(data)
		.enter().append('rect')
			.attr('class','bar')
			.attr('x',function(d,i) { return x(i); })
			.attr('y',function(d) { return height - y(d.contributions); })
			.attr('height',function(d) { return y(d.contributions); })
			.attr('width',function() { return width / data.length * 0.9; });

	svg.selectAll('.amount')
		.data(data)
		.enter().append('text')
			.attr('class','amount')
			.attr('x',function(d,i) { return x(i) + (width / data.length * 0.9) / 2; })
			.attr('y',function(d) { return height - y(d.contributions); })
			.attr('dy','2em')
			.attr('width',function() { return width / data.length; })
			.attr('text-anchor','middle')
			.text(function(d) { return '$'+d.contributions; });

	svg.selectAll('.year')
		.data(data)
		.enter().append('text')
			.attr('class','year')
			.attr('x',function(d,i) { return x(i) + (width / data.length) / 2; })
			.attr('y',function(d) { return height; })
			.attr('dy','-1em')
			.attr('width',function() { return width / data.length; })
			.attr('text-anchor','middle')
			.text(function(d) { return d.year; });

}

/*	==========================================================================
	PIE CHARTS
	========================================================================== */

function drawPieChart(data,container) {
	var width = $(container).width(),
		height = $(container).height(),
		radius = Math.min(width,height) / 2;
	var color = d3.scale.category20();

	var pie = d3.layout.pie()
		.value(function(d) { return d.contributions; })
		.sort(null);

	var arc = d3.svg.arc()
		.innerRadius(radius * 0.25)
		.outerRadius(radius * 1.0);

	var svg = d3.select(container).append('svg')
		.attr('width',width)
		.attr('height',height)
		.append('g')
			.attr('transform','translate('+ width/2 + ',' + height/2 + ')');

	var arcs = svg.datum(data).selectAll('.arc')
		.data(pie)
		.enter().append('g')
			.attr('class','arc');

	arcs.append('path')
		.attr('fill',function(d,i) { return color(i); })
		.attr('d',arc)
		.each(function(d) { this._current = d; });

	arcs.append('text')
		.attr("transform", function(d) { return "translate(" + arc.centroid(d) + ")"; })
		.attr("dy", ".35em")
		.style("text-anchor", "middle")
		.text(function(d) { return d.data.label; });

	d3.selectAll('input')
		.on('change',change);

	function change() {
		pie.value(function(d) { return d[value]; });
		path = path.data(pie);
		path.transition().duration(750).attrTween('d',arcTween);
	}

	function arcTween(a) {
		var i = d3.interpolate(this._current, a);
		this._current = i(0);
		return function(t) {
			return arc(i(t));
		};
	}
}