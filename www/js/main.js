// Document Ready

$(function(){

	initGraph();

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
				console.log('show the info card here');
				console.log(graph_element);
				console.log(dom_element);
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
