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
		//FIXME later - this points to the styro backend:
		NodeVizPath: 'http://styrotopia.net/~dameat/state_dem/ui-branch/state_dem/www/NodeViz/',
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
			
			//pre_click: "console.log('pre_click:'+element_type+' '+renderer)", //this is an example using the eval method, which is lame
			post_mouseenter: function(evt, dom_element, graph_element, element_type, renderer) { 
				var offset = this.renderers.GraphImage.tooltip.outerWidth() /2;
				this.renderers.GraphImage.tooltipOffsetX = -offset;
				this.renderers.GraphImage.tooltipOffsetY = -20;
			},
		}
	};
	gf = new NodeViz(graphoptions);
}	

// Extensions

$.extend(NodeViz.prototype, {
	graphLoaded: function() {
		gf.nodeList = $.map(gf.data.nodes, function(n) { 
			return {label: n.label, value: n.id, search_label: n.label}
		});
		$('#node_search').autocomplete({
			source: gf.nodeList, 
			appendTo:'#node_search_list', 
			focus: function(e, ui) {
				$('#node_search').val(ui.item.search_label);
				return false;
			}, 
			select: function(e, ui) {
				$('#node_search').val(ui.item.search_label);
				selectNode(gf.data.nodes[ui.item.value]);
				return false;
			}
		})
		.data("ui-autocomplete")._renderItem = function(ul, item) {
			var n = gf.data.nodes[item.value];
			var label;
			var li = $("<li>");
			if(n.type == 'candidates') {
				li.addClass('politician');
				label = n.label+" <span class='"+n.party+" searchdetails'>("+n.party+' '+n.district+")</span>";
			} else {
				li.addClass('company');
				label = "<span>"+n.label+" <span class='"+n.sitecode+" searchdetails'>("+toWordCase(n.sitecode)+")</span></span>";
			}
			return li
			.append( "<a>" + label+ "</a>" )
			.appendTo( ul );
		};
	},
});

GraphImage.prototype.pre_render= function(responseData) {
	responseData.overlay = responseData.overlay.replace(/com_images/g, 'http://dirtyenergymoney.com/com_images');
}

NodeViz.prototype.default_events.edge.click = null;
NodeViz.prototype.default_events.node.click = function(evt, dom_element, graph_element, element_type, renderer) {
	selectNode(graph_element);
}

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

function updateInfocardData(node) { 
	$.getJSON('http://styrotopia.net/~dameat/state_dem/ui-branch/state_dem/www/request.php', {'method': 'chartData','type': node.type, 'id': node.id}, function(data, status, jxqhr) { 
		drawPieChart(data.contributionsByCategory,'#node-piechart1');
		//drawPieChart(data.contributionsByParty,'#node-piechart2');
		drawBarChart(data.contributionsByYear,'#node-barchart');
	});
}

function selectNode(node) {
	gf.selectNode(node.id);
	gf.panToNode(node.id, 5, {y:-Math.round(($('body').height()/4)), x:0});
	toggleInfocard(node);
}

function toggleInfocard(node) {
	// console.log(node);

	var card = $('#infocard'),
		node_id = card.data('data-node');

	updateInfocardData(node);

	if (card.is(':hidden') || node_id !== node.id) {
		card.data('data-node',node.id);
		$('#node-title').html(node.label+' <span class="district '+node.party+' ">'+node.district+'</span>');
		var image_url = node.image.split('www/');
		$('#node-image img').attr('src',image_url[1]);
		$('#node-csvlink a').attr('href','http://styrotopia.net/~dameat/state_dem/ui-branch/state_dem/www/request.php?method=csv&type='+node.type+'&id='+node.id);
		card.slideDown(500);

		$('#masthead, #zoomcontrols').hide();
	} else {
		resetGraph();
	}
}

function resetGraph() {
	$('#infocard').slideUp();
	$('#masthead, #zoomcontrols').fadeIn(2000);
	gf.zoom('reset');
	// Reset Zoom
}

/*	==========================================================================
	BAR CHART
	========================================================================== */

function drawBarChart(data,container) {
	var width = $(window).width() / 2 * 0.9,
		height = $(window).height() / 2 * .675;

	var x = d3.scale.linear().domain([0, data.length]).range([0, width]);
	var y = d3.scale.linear().domain([0, d3.max(data, function(datum) { return parseInt(datum.value); })]).rangeRound([0, height * 0.9]);
	var svg = d3.select(container+' svg');
	if (svg.empty()) {
		svg = d3.select(container).append('svg')
			.attr('width',width)
			.attr('height',height);
	}

	var bars = svg.selectAll('.bar')
		.data(data, function(d) { return d.label; })

	bars.enter().append('rect')
			.attr('class','bar')
			.attr('x',function(d,i) { return x(i); })
			.attr('y',function(d) { return height; })
			.attr('width',function() { return width / data.length * 0.9; })
			.attr('height',function(d) { return 0; })

	bars.transition()
			.duration(1000)
			.attr('x',function(d,i) { return x(i); })
			.attr('y',function(d) { return height - y(d.value); })
			.attr('height',function(d) { return y(d.value); })
			.attr('width',function() { return width / data.length * 0.9; })
	bars.exit().transition()
			.duration(1000)
			.attr('y',function(d) { return height; })
			//.attr('x', function(d, i) )
			.remove();

	var amounts = svg.selectAll('.amount')
		.data(data, function(d) { return d.label; })
	amounts.enter().append('text')
			.attr('class','chart-label amount')
	amounts.transition()
			.duration(1000)
			.attr('x',function(d,i) { return x(i) + (width / data.length * 0.9) / 2; })
			.attr('y',function(d) { return height - y(d.value); })
			.attr('dy','-2em')
			.attr('width',function() { return width / data.length; })
			.attr('text-anchor','middle')
			.text(function(d) { return '$' + Math.floor(d.value); });
	amounts.exit().remove();

	var years = svg.selectAll('.year')
		.data(data, function(d) { return d.label; })
	years.enter().append('text')
			.attr('class','chart-label year')
	years.transition()
			.duration(1000)
			.attr('x',function(d,i) { return x(i) + (width / data.length * 0.9) / 2; })
			.attr('y',function(d) { return height; })
			.attr('dy','-0.5em')
			.attr('width',function() { return width / data.length; })
			.attr('text-anchor','middle')
			.text(function(d) { return d.label; });
	years.exit().remove();

}

/*	==========================================================================
	PIE CHARTS
	========================================================================== */

function drawPieChart(data,container) {
	var categories = {
		'oil': ['Oil','#6D8F9D'],
		'coal': ['Coal', '#958D63'],
		'carbon': ['Carbon', '#6E6E6E'],
		'R':['Republican', '#cc3333'],
		'D':['Democrat', '#3333cc'],
		'G':['Green', '#33cc33'],
		'L':['Libertarian', '#cc33cc'],
		'I':['Independant', '#cccc33'],
		'N':['Non-Partisan', '#cccccc'],
	}	

	var width = $(window).width() / 2 * 0.9,
		height = $(window).height() / 2 * 0.675,
		radius = Math.min(width,height) / 2;
	var color = d3.scale.category20();

	var pie = d3.layout.pie()
		.value(function(d) { return d.value; })
		.sort(key);

	var arc = d3.svg.arc()
		.innerRadius(radius * 0.25)
		.outerRadius(radius * 1.0);

	var getAngle = function (d) {
		var angle = (180 / Math.PI * (d.startAngle + d.endAngle) / 2 - 90);
		if ( angle > 90 ) { angle -= 180; }
		return angle;
	};

	var svg = d3.select(container+' svg>g');
	if (svg.empty()) {
		svg = d3.select(container).append('svg')
			.attr('width',width)
			.attr('height',height)
			.append('g')
				.attr('transform','translate('+ width/2 + ',' + height/2 + ')');
	}

	var arcs = svg.datum(data).selectAll('.arc')
		.data(pie, key)
		.enter().append('g')
		.attr('class','arc')

	arcs.append('path') 
		.attr('fill',function(d,i) { return categories[d.data.label][1]; })
		.attr('d',arc)
		.each(function(d) { this._current = d; })
		.on('mouseenter', brighten)
		.on('mouseleave', darken);

	arcs.append('text')
		.attr('class', 'chart-label')
		.attr("transform", function(d) { return "translate(" + arc.centroid(d) + ")" +
			"rotate(" + getAngle(d) + ")"; })
		.attr("dy", ".35em")
		.style("text-anchor", "middle")
		.text(function(d) { return d.data.label; });

	svg.datum(data).selectAll('path').data(pie, key).exit()
		.transition(750)
		.attr('fill', '#fff')

	svg.datum(data).selectAll('.arc').data(pie,key).exit().transition(750).remove();


	change();

	function change() {
		//var path = svg.datum(data).selectAll('path')
		//pie.value(function(d) { return d.value; });
		path = svg.datum(data).selectAll('path').data(pie, key);
		path.transition().duration(750).attrTween('d',arcTween)
			.attr('fill',function(d,i) { return categories[d.data.label][1]; })

		svg.datum(data).selectAll('text').data(pie,key)
			.attr("transform", function(d) { return "translate(" + arc.centroid(d) + ")" +
				"rotate(" + getAngle(d) + ")"; })
			.attr("dy", ".35em")
			.style("text-anchor", "middle")
			.text(function(d) { return categories[d.data.label][0]; });
	}

	function arcTween(a) {
		var i = d3.interpolate(this._current, a);
		this._current = i(0);
		return function(t) {
			return arc(i(t));
		};
	}
	
	function key(d) { 
		//trying to maintain consistency across companies and legislators in pie chart grouping
		var label = typeof(d.label) != 'undefined' ? d.label : d.data.label;
		if (label == 'coal') { 
			label= 'D';
		} else if(label == 'oil') { 
			label= 'R';
		}
		if (label != 'D' && label != 'R') { label= 'other'; }
		return label;
	}
}

function brighten() {
	var e = d3.select(this);
	e.attr('fill', d3.rgb(e.attr('fill')).brighter(.7));
}

function darken() {
	var e = d3.select(this);
	e.attr('fill', d3.rgb(e.attr('fill')).darker(.7));
}
