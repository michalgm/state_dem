$.Class("GraphList", {}, {
	init: function(NodeViz) {
		this.NodeViz = NodeViz;
		this.scrollList = 0;
		this.sort = false;
		$.extend(this, NodeViz.options.list);	
		//this.listdiv = this.NodeViz.listdiv;
	},
	reset: function() { 
		var data = this.NodeViz.data;
		var parent = '#'+this.listdiv;
		//TODO - there may be a slightly more efficient way of finding these nodes - gm - 12/28/10
		$(parent+' li', parent+' div').each(function(i, e) { 
			e.stopObserving();
		});
		$('#'+this.listdiv).empty();
	},
	appendOptions: function() {

	},
	// This function builds the html for the list view of the graph
	render: function(responseData) {
		//console.time('renderList');
		var data = this.NodeViz.data;
		this.nodeLists = {};
		$('#'+this.listdiv).hide();
		$('#'+this.listdiv).prepend($("<ul/>").attr('id', 'list_menu'));
		//Build seperate sub lists for each node type
		$(data.nodetypes).values().each( $.proxy(function(i, nodetype) {
			this.nodeLists[nodetype] = [];
			$('#list_menu').append($('<li/>').attr('id', nodetype+'_menu').html(toWordCase(nodetype)));
			$('#'+this.listdiv).append($('<div/>').attr('id', nodetype+'_list_container').attr('class', 'nodelist_container'));
			$('#'+nodetype+'_list_container').prepend($('<div/>').attr('id', nodetype+'_list_header').attr('class', 'nodelist_header').html(this.listHeader(nodetype)));

			//Create the search field for the top of the lsist
			var search = ' <label for="'+nodetype+'_search">Search</label> <input class="node_search" id="'+nodetype+'_search" autocomplete="off" size="20" type="text" value="" /> <div class="autocomplete node_search_list" id="'+nodetype+'_search_list" style="display:none"></div>';
			$('#'+nodetype+'_list_header').append($('<div/>').attr('id', nodetype+'_search_container').attr('class', 'node_search_container').html(search));
			if (this.sort && this.sort[nodetype]) { 
				$('#'+nodetype+'_list_header').append($('<div/>').attr('id', nodetype+'_sort_container').attr('class', 'node_sort_container'));
				$(this.sort[nodetype]).each($.proxy(function(i, sort_options) { 
					var label = sort_options.label;
					$('#'+nodetype+'_sort_container').append( $('<span/>')
							.addClass('node_sort_trigger')
							.html(label)
							.click($.proxy(function(e) { 
								var target = $(e.target);
								var reverse = (target.hasClass('current_sort') || sort_options.desc) && ! $(e.target).hasClass('reverse');
								$('#'+nodetype+'_sort_container .node_sort_trigger').removeClass('current_sort').removeClass('reverse');
								if (reverse) { target.addClass('reverse'); }
								target.addClass('current_sort');
								this.sortList(nodetype, sort_options.sort_values, reverse); 
							}, this))
					);
				}, this));
			}
			var nodelist = $('<ul/>').attr('id',nodetype+'_list').attr('class','nodelist');
			$('#'+nodetype+'_menu').click($.proxy(function(e) { this.displayList(nodetype); }, this));
			var odd = 'even';
			$(data.nodetypesindex[nodetype]).values().each( $.proxy(function(i, nodeid) {
				var node = data.nodes[nodeid];
				var label = '';
				if (node['label']) { 
					label = node['label'];
				} else if (node['id']) { 
					label = node['id'];
				}
				if (label != '') { 
					this.nodeLists[nodetype].push({label: label, value: nodeid, search_label:label});
				}
				odd = odd == 'odd' ? 'even ' :'odd';
				var nodelist_entry = $('<li/>').attr('id', 'list_'+nodeid).attr('class',odd);
				nodelist_entry.html(this.listNodeEntry(node));
				this.NodeViz.addEvents(nodelist_entry, node, 'node', 'list');
				nodelist.append(nodelist_entry);

				//setup more sub lists for each connected node type
				var sublists= $('<div/>').attr('id', nodeid+'_sublists').attr('class', 'sublists_container');
				$(data.edgetypes).keys().each( $.proxy(function(i, edgetype) {
					this.setupSubLists(node, edgetype, 'from', sublists); 
					this.setupSubLists(node, edgetype, 'to', sublists); 
				}, this));
				sublists.click($.proxy(function(e) { e.stopImmediatePropagation(); }, this));
				sublists.mouseover($.proxy(function(e) { e.stopImmediatePropagation(); }, this));
				nodelist_entry.append(sublists);
			}, this));
			$('#'+nodetype+'_list_container').append(nodelist);
			if ($(this.nodeLists[nodetype]).values()[0]) { 
				$('#'+nodetype+'_search').autocomplete({
					source: this.nodeLists[nodetype], 
					//appendTo:'#'+nodetype+'_search_list', 
					focus: function(e, ui) {
						$('#'+nodetype+'_search').val(ui.item.search_label);
						return false;
					}, 
					select: $.proxy(function(e, ui) {
						$('#'+nodetype+'_search').val(ui.item.search_label);
						this.NodeViz.selectNode(ui.item.value);
						return false;
					}, this)
				});
			}
		}, this));
			
		//Set the default sort order (if it exists)
		$.each(data.nodetypes, $.proxy(function(i, nodetype) {
			if (this.sort && this.sort[nodetype]) {
				var sort_index = 0;
				$.each(this.sort[nodetype], function(i, s) { 
					if(s['default']) { 
						sort = i; 
						return false;
					}
				})
				$('#'+nodetype+'_sort_container .node_sort_trigger')[sort].click();
			}
		}, this));
	
		this.displayList(data.nodetypes[0]);
		$('#'+this.listdiv).show();
		//console.timeEnd('renderList');
	},
	displayList: function(nodetype) { 
		var oldnodetype = this.NodeViz.current['nodetype'];
		if(oldnodetype != '') { 
			$('#'+oldnodetype+'_list_container').removeClass('selected');
			$('#'+oldnodetype+'_menu').removeClass('selected');
		}
		$('#'+nodetype+'_list_container').addClass('selected');
		$('#'+nodetype+'_menu').addClass('selected');
		this.NodeViz.current['nodetype'] = nodetype;
	},
	setupSubLists: function(node, edgetype, direction, sublists) { 
		var data = this.NodeViz.data;
		var dirindex = direction == 'from' ? 0 : 1; 
		var otherdir = direction == 'from' ? 'to' : 'from';
		var nodeid = node.id;
		var nodetype = node.type;
		var nodediv = 'list_'+nodeid;
		if (data.edgetypes[edgetype][dirindex] == nodetype) { 
			var nodes = [];
			$(node.relatedNodes).values().each( $.proxy(function(i, rnode) {
				var edgeid = rnode[0];
				var edge = data.edges[edgeid];
				if (edge['type'] == edgetype && edge[direction+'Id'] == nodeid) { 
					var snode = data.nodes[data.edges[edgeid][otherdir+'Id']];
					snode['edgeid'] = edgeid;
					nodes.push(snode);
				}
			}, this));
			if (nodes.length >= 1) {
				var sublistdiv = nodediv+'_'+edgetype+'_'+direction;
				var classes = edgetype+'_list '+direction+'_list nodesublist';
				var elem = $('<ul>').attr('id', sublistdiv).attr('class', classes);
				
				sublists.append(this.sublistHeader(node, edgetype, direction));
				var odd='even';
				$(nodes).each($.proxy(function(i, snode) {
					odd = odd == 'odd' ? 'even' : 'odd';
					var subelem = $('<li/>').attr('id', 'list_'+direction+'_'+snode['edgeid']).attr('class', odd);
					subelem.html(this.listSubNodeEntry(snode, node, edgetype, direction));
					this.NodeViz.addEvents(subelem, snode, 'node', 'list');
					elem.append(subelem);
				}, this));
				sublists.append(elem);
			}	
		}
	},
	listHeader: function(nodetype) {
		return '<span class="nodetype_label">'+nodetype+' Nodes</span>';
	},
	sublistHeader: function(node, edgetype, direction) {
		return '<span class="sublist_header">'+edgetype+' '+direction+' Nodes</span>';
	},
	listNodeEntry: function(node) {
		var label;
		var content = "";
		if (node.label) { 
			label = node.label;
		} else { 
			label = node.id;
		}
		return "<span>"+label+"</span>";
	},
	listSubNodeEntry: function(node, parentNode, edgetype, direction) { 
		var label;
		var node_class = direction == 'to' ? 'to_node_item' : 'from_node_item'; 
		if (node.label) { 
			label = node.label;
		} else { 
			label = node.id;
		}
		var link = "<span class='link_icon'>&#187;</span>";
		return link+" <span class='"+node_class+"'>"+label+"</span>";
	},
	highlightNode: function (id) { 
		var networkNode = this.NodeViz.data.nodes[this.NodeViz.current.network];
		var highlightNode = this.NodeViz.data.nodes[id];
		if(networkNode && networkNode.type != highlightNode.type && networkNode.type == this.NodeViz.current.nodetype) { 
			var other_id = this.NodeViz.current.network;
			$(highlightNode.relatedNodes[other_id]).values().each( $.proxy(function(i, edgeid) { 
				var edge = this.NodeViz.data.edges[edgeid];
				var type = edge.type;
				var dir = edge.toId == id ? 'from' : 'to';
				//var subnodeid = 'list_'+other_id+'_'+type+'_'+dir+'_'+id;
				var subnodeid = 'list_'+dir+'_'+edgeid;
				$('#'+subnodeid).addClass('highlight');
			}, this));
		} else {
			$('#list_'+id).addClass('highlight');
		}
	},
	unhighlightNode: function (id) { 
		var networkNode = this.NodeViz.data.nodes[this.NodeViz.current.network];
		var highlightNode = this.NodeViz.data.nodes[id];
		if(networkNode && networkNode.type != highlightNode.type && networkNode.type == this.NodeViz.current.nodetype) { 
			var other_id = this.NodeViz.current.network;
			$(highlightNode.relatedNodes[other_id]).values().each( $.proxy(function(i, edgeid) { 
				var edge = this.NodeViz.data.edges[edgeid];
				var type = edge.type;
				var dir = edge.toId == id ? 'from' : 'to';
				//var subnodeid = 'list_'+other_id+'_'+type+'_'+dir+'_'+id;
				var subnodeid = 'list_'+dir+'_'+edgeid;
				$('#'+subnodeid).removeClass('highlight');
			}, this));
		} else { 
			$('#list_'+id).removeClass('highlight');
		}
	},
	selectNode: function(id, noscroll) { 
		this.displayList(this.NodeViz.data.nodes[id].type);
		var elem = $('#list_'+id);
		elem.addClass('selected');
		$('#'+id+'_sublists').css({'display': 'block'});
		if (this.scrollList && ! noscroll) { 
			elem.parent().scrollTop(elem.position().top);
		}
	},
	unselectNode: function(id, fade, noscroll) { 
		var elem = $('#list_'+id);
		elem.removeClass('selected');
		$('#'+id+'_sublists').css({'display': 'none'});
		if (this.scrollList && ! noscroll) { 
			elem.parent().scrollTop(elem.position().top-10);
		}
	},
	sortList: function(nodetype, sort_values, reverse) { 
		var ids = $('#'+nodetype+'_list>li').map(function() { return this.id; }).get();
		ids.sort($.proxy(function(a, b) { 
			var node_a = this.NodeViz.data.nodes[a.replace('list_', '')]; 
			var node_b = this.NodeViz.data.nodes[b.replace('list_', '')]; 
			var value_a, value_b, x=0;
			while (value_a === value_b && sort_values[x]) { 
				var property, desc = 1; //note: desc = 1 by default so it's positive
				var sort_value = sort_values[x];
				if (sort_value instanceof Array) {
					property = sort_value[0], desc = sort_value[1] ? -1 : 1;
				} else { property = sort_value; }
				if (typeof(property) == 'function') {
					value_a = property(node_a);
					value_b = property(node_b);
				} else {
					value_a = node_a[property];
					value_b = node_b[property];
				}
				x++;
			}	
			return value_a > value_b ? 1 * desc : -1*desc ;
		}, this));
		if (reverse) {ids = ids.reverse(); } 
		var last;
	 	var parent = $('#'+nodetype+'_list');
		$(ids).each(function(i, v) { 
			var entry = $('#'+v);
			if(last) { 
				last.after(entry);
			} else {
				parent.prepend(entry);
			}
			last = entry;
		});
	}
});
