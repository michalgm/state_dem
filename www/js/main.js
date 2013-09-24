// Document Ready

$(function(){

	current_network = '';
	$('#close_error').click(function() { $('#error').hide(); });
	if ( !document.implementation.hasFeature("http://www.w3.org/TR/SVG11/feature#BasicStructure", "1.1")) {
		$('#error').html("<h3>Unsupported Browser</h3> We're sorry - this website requires SVG, and it appears your web browser does not support it. Please consider updating to a modern, standards-compliant web browser, such as <a href='http://mozilla.org/firefox'>FireFox</a> or <a href='http://google.com/chrome'>Chrome</a>").show();
		return;
	}
	setupOptions();
	History.Adapter.bind(window,'statechange',setState);
	initGraph();
	
	$('#infocard').hide();
	$('#infocard .close').click(function() { resetGraph(); });
	$('#infocard .more').click(function() { $(this).parent().toggleClass('open'); });

	$('#pull').click(function(e){
		$('.navlist').slideToggle();
		e.preventDefault();
	});
	$(window).resize(function(){
		if ($(window).width() > 320 && $('.navlist').is(':hidden')) { $('.navlist').removeAttr('style'); }
	});
	
	var url_state = (window.location.search.substr(1)).split('/');
	if (url_state[0]) { 
		$('#state').val(url_state[0]);
		$('#chamber').val('state:'+url_state[1].toLowerCase());
		$('#cycle').val(url_state[2]);
		if (url_state[3]) { 
			current_network = url_state[3];
		}
	}

});

// Functions

function initGraph() {
	var params = $.url().param();
	$(params).keys().each(function(i, k) { 
		$('#graphoptions').append($('<input>').attr({type:'hidden', name:k, value:params[k]}));
	})
	$('#graphoptions').change(writeHash);

	var graphoptions = {
		setupfile: "StateDEM",
		image: {
			graphdiv: 'graphs',
			zoomlevels: '8',
			zoomSliderAxis: 'vertical',
			fadeTo: .2
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
	if (remotecache) { 
		graphoptions['NodeVizPath'] = remotecache;
	}
	gf = new NodeViz(graphoptions);
}	

// Extensions

$.extend(NodeViz.prototype, {
	graphLoaded: function() {
		$('#infocard').hide();
		$('#masthead').fadeIn(2000);
		
		gf.nodeList = $.map(gf.data.nodes, function(n) { 
			return {label: n.title, value: n.id, search_label: n.title}
		});
		$("#node_search").keyup(function(e) { //don't submit the form when someone hits enter in search field
			if ($("#node_search").val() == '') { 
				$('#no_results_found').hide();
			}
		});
		$("#node_search").keypress(function(e) { //don't submit the form when someone hits enter in search field
			var code = (e.keyCode ? e.keyCode : e.which);
		    if(code == 13) { //Enter keycode
				if ($("#node_search").val().length) { 
					$('#node_search').autocomplete('search');
					$('#node_search').autocomplete('close');
				}
				return false;
			}
			if ($("#node_search").val() == '') { 
				$('#no_results_found').hide();
			}
		});
		$('#node_search').autocomplete({
			source: gf.nodeList, 
			autoFocus: true, 
			appendTo:'#node_search_list', 
			select: function(e, ui) {
				$('#node_search').val(ui.item.search_label);
				selectNode(gf.data.nodes[ui.item.value]);
				$('#node_search').autocomplete('close');
				return false;
			},
			response: function(e, ui) { 
				if (ui.content.length == 0 && $("#node_search").val().length ) {
					$('#no_results_found').show();
				} else {
					$('#no_results_found').hide();
				}	
			}
		})
		.data("ui-autocomplete")._renderItem = function(ul, item) {
			var n = gf.data.nodes[item.value];
			var label;
			var li = $("<li>");
			if(n.type == 'candidates') {
				li.addClass('politician');
				label = n.title+" <span class='"+n.party+" searchdetails'>("+n.party+' '+n.district+")</span>";
			} else {
				li.addClass('company');
				label = "<span>"+n.title+" <span class='"+n.sitecode+" searchdetails'>("+toWordCase(n.sitecode)+")</span></span>";
			}
			return li
			.append( "<a>" + label+ "</a>" )
			.appendTo( ul );
		};
		if (current_network && gf.data.nodes[current_network]) { 
			$('#'+current_network).click();
		}
	},
});

GraphImage.prototype.pre_render= function(responseData) {
	responseData.overlay = responseData.overlay.replace(/com_images/g, 'http://dirtyenergymoney.com/com_images');
}

NodeViz.prototype.default_events.edge.click = null;
NodeViz.prototype.default_events.node.click = function(evt, dom_element, graph_element, element_type, renderer) {
	selectNode(graph_element);
}

function updateInfocardData(node) { 
	var url = (remotecache ? remotecache + '../' : '')+'request.php';
	$.getJSON(url, {'method': 'chartData','type': node.type, 'id': node.id, 'state': gf.data.properties.state, 'chamber': gf.data.properties.chamber}, function(data, status, jxqhr) { 
		drawPieChart(data.contributionsByCategory,'#node-piechart');
		drawBarChart(data.contributionsByYear,'#node-barchart');
	});
}

function selectNode(node) {
	gf.selectNode(node.id);
	current_network = node.id;
	writeHash(1);
	toggleInfocard(node);
}

function toggleInfocard(node) {

	var card = $('#infocard'),
		node_id = card.data('data-node');

	updateInfocardData(node);

	if (card.is(':hidden') || node_id !== node.id) {
		gf.panToNode(node.id, 4, {y:-50, x:0});
		card.data('data-node',node.id);
		switch( node.type ) {
			case 'candidates':
				$('#node-title').html(node.title+' <span class="district '+node.party+'">'+node.district+'</span>');
				$('#node-amount').html('Received $'+commas(Math.floor(node.value))+' in '+ gf.data.properties.cycle + ' Ugly lifetime total: '+node.lifetime_total);
				break;
			case 'donors':
				$('#node-title').html(node.title+' <span class="sector '+node.sitecode+'">'+node.sitecode+'</span>');
				$('#node-amount').html('Contributed $'+commas(Math.floor(node.value))+' to the '+gf.data.properties.state+' '+ $('#chamber :selected').text() +' in '+gf.data.properties.cycle+ ' Ugly lifetime total: '+node.lifetime_total);
				break;
		}
		var url = (remotecache ? remotecache + '../' : '')+'request.php';
		$('#node-csvlink a').attr('href',url+'?method=csv&type='+node.type+'&id='+node.id+'&state='+gf.data.properties.state+'&chamber='+gf.data.properties.chamber);
		card.slideDown(500);
		$('#masthead').hide();
	} else {
		resetGraph();
	}
}

function resetGraph() {
	current_network = '';
	if (gf.current.network) { 
		gf.unselectNode(1);
	}
	$('#infocard').slideUp();
	$('#masthead').fadeIn(2000);
	gf.zoom('reset');
}

function writeHash(network) {
	var hash_state = {
		state: $('#state').val(),
		chamber: toWordCase($('#chamber').val().replace('state:', '')),
		cycle: $('#cycle').val(),
	}
	var states = [hash_state.state, hash_state.chamber, hash_state.cycle];
	if (network == 1) { 
		hash_state.network = gf.current.network;
		states.push(hash_state.network);
	} else {
		current_network = '';
	}
	History.pushState(hash_state, 'Dirty Energy Money - States - '+$('#state option:selected').text(),  '?'+(states).join('/'));
}

function setState() {
	var state = History.getState().data;
	if (! state.state) { 
		resetGraph();
	} else {
		state.chamber = 'state:'+state.chamber.toLowerCase();
		if ($('#state').val() != state.state || $('#chamber').val() != state.chamber || $('#cycle').val() != state.cycle) { 
			$('#state').val(state.state);
			$('#chamber').val(state.chamber);
			$('#cycle').val(state.cycle);
			$('#state').change();
		} else if (current_network != state.network || ! state.network) { 
			if (! state.network && current_network) { 
				resetGraph();
			} else if (state.network && gf.data.nodes[state.network]) { 
				current_network = state.network;
				$('#'+state.network).click();
			}
		}
	}
}


/*	==========================================================================
	BAR CHART
	========================================================================== */

function drawBarChart(data,container) {
	var width = $(window).width() / 2 * 0.9,
		height = $(window).height() / 2 * .675;

	var x = d3.scale.linear().domain([0, data.length]).range([0, width]);
	var y = d3.scale.linear().domain([0, d3.max(data, function(datum) { return parseInt(datum.value); })]).rangeRound([0, height * 0.8]);
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
			.text(function(d) { return '$' + commas(Math.floor(d.value)); });
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

	function arcTween(a) { var i = d3.interpolate(this._current, a);
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
		} else if(label == 'carbon') { 
			label= 'N';
		}
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

function commas(val){
	while (/(\d+)(\d{3})/.test(val.toString())){
		val = val.toString().replace(/(\d+)(\d{3})/, '$1'+','+'$2');
	}
	return val;
}

function setupOptions() {
	var keys = $.map(states, function(s, k) { return k; }).sort();
	var current_state = $('#state').val();
	$('#state').html('');
	$(keys).each(function(i, s) { 
		$('#state').append($('<option>').attr('value', s).html(states[s].name));
	});
	$('#state').val(current_state);

	$('#state').on('change', function(a, e, i , o) { 
		var state = states[a.target.value];
		var current_year = $('#cycle').val();
		var current_chamber = $('#chamber').val();

		$('#chamber').html('');
		$('#chamber').append($('<option>').attr('value', 'state:upper').html(state.upper_name));
		if (state.lower_name) { 
			$('#chamber').append($('<option>').attr('value', 'state:lower').html(state.lower_name));
		}

		$('#cycle').html('');
		$(state.years.split(',').reverse()).each(function(i, y) {
			$('#cycle').append($('<option>').html(y));	
		});	
		$('#cycle').val(current_year);
		$('#chamber').val(current_chamber);
	});
	$('#state').change();
}
