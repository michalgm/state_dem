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
	
	// $('#infocard').hide();
	$('#infocard .close').click(function() { resetGraph(); });
	$('#infocard .more').click(function() { toggleMore(); });
	$('#lists-container .toggle').click(function() { toggleLists(); });
	$('.aboutLink, .methodologyLink, .page-close').click(function() { togglePage(this); });

	$('#pull').click(function(e){
		$('.navlist').slideToggle();
		e.preventDefault();
	});
	
	$(window).resize(
		$.debounce(100, function(){
			if ($(window).width() > 320 && $('.navlist').is(':hidden')) { $('.navlist').removeAttr('style'); }
			barChart.resize();
		})
	);
	
	var url_state = (window.location.search.substr(1)).split('/');
	if (url_state[0]) { 
		$('#state').val(url_state[0]);
		updateOptions();
		$('#chamber').val('state:'+url_state[1].toLowerCase());
		updateOptions();
		$('#cycle').val(url_state[2]);
		$('#intro_screen').hide();
		$('#state').change();
		if (url_state[3]) {  //have to set this after change() because it gets cleared if you do it before
			current_network = url_state[3];
		}
	} else {
		$('#intro_screen').show();
		$('#graphoptions select').hide();
	}

	$('#intro_state').change(function(e) {
		$('#state').val($('#intro_state').val());

		var style = $('#intro_screen').offset();
		style.margin = 0;
		$('#intro_screen').css(style)
			.animate({height: 0, width: '20px', top: $('#navbar').position().top, left: 0}, 250, function() { 
				$(this).hide(); 
				$('#state').change();
			});
		$('#graphoptions select').fadeIn();
		$('#masthead').fadeOut();
	});

	barChart = new DEMBarChart('#node-barchart');

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
		disableAutoLoad : 1,
		image: {
			graphdiv: 'graphs',
			zoomlevels: '8',
			zoomSliderAxis: 'vertical',
			fadeTo: .2,
			center_tooltip: 1,
			tooltipOffsetY: -21,
			tooltipOffsetX: 0
			//zoom_delta:  

		},
		list: {
			listdiv: 'lists',
			sort: {
				'candidates': [{label: 'Name', sort_values: ['lastfirst']}, {label:'Amount', sort_values:['value'], 'default':true, desc:true}], 
				'donors': [{label: 'Name', sort_values: ['title']}, {label:'Amount', sort_values:['value'], 'default':true, desc:true}] 
			},
			scrollList: 1
		},
		functions: {
			//You can define functions that execute before or after events occuring on graph elements - name them like 'pre_eventname' or 'post_eventname'. They can either be a string to be evaled, or a true function.  The functions take the following arguments:
			//evt: the event object of the triggered event
			//dom_element: the dom_element the event occured on
			//graph_element: the NodeViz graph element data object that the dom_element refers to
			//element_type: either 'node' or 'edge'
			//renderer: the render type that triggered the event (svg, raster, or list)
			
			//pre_click: "console.log('pre_click:'+element_type+' '+renderer)", //this is an example using the eval method, which is lame
		}
	};
	if (remotecache) { 
		graphoptions['NodeVizPath'] = remotecache;
	}
	gf = new NodeViz(graphoptions);
	$('#graphoptions').change($.proxy(gf.reloadGraph,gf));
}	

// Extensions

$.extend(NodeViz.prototype, {
	graphLoaded: function() {
		$('#content').toggleClass('governor', gf.data.properties.chamber == 'state:governor');
		$('#content').removeClass('initial');
		$('.cluster').remove();
		$('.zerocontribs').remove();
		if(gf.data.nodes['zerocontribs']) { delete gf.data.nodes['zerocontribs']; }
		$.each($('.zero'), function(i, e) {
			if (gf.data.nodes[e.id]) {
				gf.data.nodes[e.id].relatedNodes = {};
			}
		});
		$('#infocard').hide();
		$('#masthead').fadeOut(2000);
		$('#about, #methodology').slideUp();
		$('#navbar').slideDown();
		$('#searchfield, .addthis_toolbox').fadeIn(500);
		if( $(window).width() > 639 ) { $('#lists-container').fadeIn(500); }

		$('#graphoptions select').blur();
	
		$('#legend-text').html('Contributions to the ' + gf.data.properties.state + ' ' + $('#chamber :selected').text() + ' in ' + gf.data.properties.cycle + ', scaled by amount.');

		$('#csvlink').hide().attr('href',function() {
			var url = (remotecache ? remotecache + '../' : '')+'request.php';
			return url+'?method=csv&type=chamber&state='+gf.data.properties.state+'&chamber='+gf.data.properties.chamber+'&cycle='+gf.data.properties.cycle;
		}).delay(1000).fadeIn();

		setupAutocomplete(); 

		if (current_network && gf.data.nodes[current_network]) { 
			$('#'+current_network).click();
		} else if($('#chamber').val() == 'state:governor') {
			$('#'+gf.data.nodetypesindex.candidates[0]).click();
		}
	}
});

$.extend(GraphList.prototype, {
	listNodeEntry: function(node) {

		var title = node.title ? node.title : node.id;
		var thumb = (typeof(node.image) != 'undefined' && node.image != 'null') ? "<img src='"+node.image+"'>" : "";
		var amount = (node.total_dollars > 0) ? '$'+format(Math.round(node.total_dollars)) : 'none';
		var label;

		if (node.type == 'candidates') {
			label = node.party ? node.party : '';
		} else {
			label = node.sitecode ? node.sitecode : ''; //(node.industry != '--') ? node.industry : 'unknown';
		}

		var entry =  '<div class="thumb">'+thumb+'</div>';
			entry += '<div class="title">'+title+' <span class="label '+label+'">'+label+'</span></div>';
			entry += '<div class="amount">'+amount+'</div>';

		return entry;
	},
	listSubNodeEntry: function(node, parentNode, edgetype, direction) {

		var title = node.title ? node.title : node.id;
		var edge = this.NodeViz.data.edges[node.relatedNodes[parentNode.id][0]];

		var amount = (node.total_dollars > 0) ? '$'+format(Math.round(edge.value)) : 'none';

		var subEntry =  '<div class="title">'+title+'</div>';
			subEntry += '<div class="amount">'+amount+'</div>';

		return subEntry;
	},
	listHeader: function(nodetype) {
		return '';
	},
	sublistHeader: function(node, edgetype, direction) {
		var header = 'Recipients';
		if(direction == 'to') {
			header =  "Donors";
		}
		return "<div class='sublist_header'>"+header+"</div>"
	},
	pre_render: function() {
		$.each(this.NodeViz.data.nodes, function(i, node) { 
			if (node.image) { node.image = node.image.replace(/..\/www\//, ''); }
		});
	}
});

GraphImage.prototype.pre_render= function(responseData) {
	$.each(this.NodeViz.data.nodes, function(i, node) { 
		if (node.image) { node.image = node.image.replace(/..\/www\//, ''); }
	});
}

NodeViz.prototype.default_events.edge.click = null;
NodeViz.prototype.default_events.node.click = function(evt, dom_element, graph_element, element_type, renderer) {
	selectNode(graph_element);
}

function updateInfocardData(node) { 
	var url = (remotecache ? remotecache + '../' : '')+'request.php';
	$.getJSON(url, {'method': 'chartData','type': node.type, 'id': node.id, 'state': gf.data.properties.state, 'chamber': gf.data.properties.chamber}, function(data, status, jxqhr) {
		barChart.draw(data.contributionsByYear);
		var total = 0;
		$.each(data.contributionsByYear, function(i, c) {
			total += c.value;
		});
		$('#node-total').html('Total: $' + commas(total));
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
		card.data('data-node',node.id);
		switch( node.type ) {
			case 'candidates':
				$('#node-title').html(node.title+' <span class="district '+node.party+'">'+node.district+'</span>');
				$('#node-amount').html('Received $'+commas(Math.floor(node.value))+' in '+ gf.data.properties.cycle);
				break;
			case 'donors':
				$('#node-title').html(node.title+' <span class="sector '+node.sitecode+'">'+node.sitecode+'</span>');
				$('#node-amount').html('Contributed $'+commas(Math.floor(node.value))+' to the '+gf.data.properties.state+' '+ $('#chamber :selected').text() +' in '+gf.data.properties.cycle);
				break;
		}
		var url = (remotecache ? remotecache + '../' : '')+'request.php';
		var label = node.type == 'donors' ? 'company' : ($('#chamber').val() != 'state:governor' ? 'legislator' : 'governor');
		$('#node-csvlink a').attr('href',url+'?method=csv&type='+node.type+'&id='+node.id+'&state='+gf.data.properties.state+'&chamber='+gf.data.properties.chamber).find('span').html(label);

		var nodeLinks  = '<a target="_blank" class="twitter" href="'+tweetLink(node)+'">Twitter</a>';
			nodeLinks += node.facebook		? '<a target="_blank" class="facebook" href="'+node.facebook+'">Facebook</a>' : '';
			nodeLinks += node.action_link	? '<a target="_blank" class="action" href="'+node.action_link+'">Take Action</a>' : '';

		$('#node-links').html(nodeLinks);

		card.slideDown(500);
		gf.panToNode(node.id, null, {y:-50, x:0});

		$('#legend').hide();
	} else if (node_id !== node.id) {
		resetGraph();
	}
}

function toggleMore() {
	$('#infocard').toggleClass('open');
	$('#node-amount, .node-more').fadeToggle();
}

function togglePage(el) {
	var page = $(el).attr('href') || '';
	$('.page').not(page).slideUp();
	$(page).slideToggle();
}

function toggleLists() {
	var lists = $('#lists-container'),
		graphs = $('#graphs-container');

	var callback = function() {
		barChart.resize();
		gf.resize();
	};

	if (parseInt(lists.css('width')) > 0) {
		lists.animate({width: '0%'},500);
		graphs.animate({width: '100%'},500,callback);
	} else {
		lists.animate({width: 320},500);
		graphs.animate({width: ($(window).width() - 320)},500,callback);
	}
	lists.toggleClass('open');
}

function resetGraph() {
	// Reset infocard
	$('#infocard').removeClass('open').slideUp(500,function(){
		$('#node-amount').show();
		$('.node-more').hide();

		current_network = '';
		if (gf.current.network) {
			gf.unselectNode(1);
		}
		gf.zoom('reset');		

	});

	$('#legend').fadeIn(2000);
}

function writeHash(network) {
	var hash_state = {
		state: $('#state').val(),
		chamber: toWordCase($('#chamber').val().replace('state:', '')),
		cycle: $('#cycle').val()
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

function setupOptions() {
	var keys = $.map(states, function(s, k) { return k; }).sort();
	var current_state = $('#state').val();
	$('#state').html('');
	$(keys).each(function(i, s) { 
		$('#state').append($('<option>').attr('value', s).html(states[s].name));
		$('#intro_state').append($('<option>').attr('value', s).html(states[s].name));
	});
	$('#state').val(current_state);

	$('#state').on('change', function() { updateOptions(); });
	$('#chamber').on('change', function() { updateCycle(); });
}

function updateOptions() {
	var state = states[$('#state').val()];
	var current_chamber = $('#chamber').val();

	$('#chamber').html('');
	$('#chamber').append($('<option>').attr('value', 'state:upper').html(state.upper_name));
	if (state.lower_name) { 
		$('#chamber').append($('<option>').attr('value', 'state:lower').html(state.lower_name));
	}
	$('#chamber').append($('<option>').attr('value', 'state:all').html('All'));
	$('#chamber').append($('<option>').attr('value', 'state:governor').html('Governor'));

	$('#chamber').val(current_chamber);
	updateCycle();
}

function updateCycle() {
	var state = states[$('#state').val()];
	var current_year = $('#cycle').val();
	var current_chamber = $('#chamber').val();

	$('#cycle').html('');
	$(state.years.split(',').reverse()).each(function(i, y) {
		$('#cycle').append($('<option>').html(y));	
	});	
	if (current_chamber != 'state:all') {
		$('#cycle').append($('<option>').attr('value', 'all').html('All'));	
	}
	if (current_chamber == 'state:governor') {
		$('#cycle').val('all');
		$('#cycle').hide();
	} else {
		$('#cycle').show();
		$('#cycle').val(current_year);
	}
}

function setupAutocomplete() {
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
}

function tweetLink(node) {
	var tweet,
		target = (node.twitter && node.twitter.replace(/\s+/g,'') !== '') ? node.twitter : node.title,
		year = (gf.data.properties.cycle != 'all') ? ' in ' + gf.data.properties.cycle : '';
		amount = (node.total_dollars > 0) ? '$' + format(Math.round(node.total_dollars)) : 'no contributions';

	if (node.type == 'candidates') {

		tweet = 'Did you know ' + target + ' accepted ' + amount + ' from dirty energy companies' + year + '? ' + window.location;
	} else {
		tweet = 'Did you know ' + target + ' paid ' + amount + ' to ' + gf.data.properties.state + ' legislators' + year + ' alone? ' + window.location;
	}
	return 'https://twitter.com/intent/tweet?&text=' + encodeURIComponent(tweet) + '&via=priceofoil';
}