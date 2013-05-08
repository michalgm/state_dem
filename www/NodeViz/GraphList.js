
var GraphList = Class.create();

GraphList.prototype = {

	initialize: function(NodeViz) {
		this.NodeViz = NodeViz;
		this.scrollList = 0;
		Object.extend(this, NodeViz.options.list);	
		//this.listdiv = this.NodeViz.listdiv;
	},
	reset: function() { 
		var data = this.NodeViz.data;
		var parent = '#'+this.listdiv;
		//TODO - there may be a slightly more efficient way of finding these nodes - gm - 12/28/10
		$$(parent+' li', parent+' div').each(function(e) { 
			e.stopObserving();
		});
		$(this.listdiv).update('');
	},
	appendOptions: function() {

	},
	// This function builds the html for the list view of the graph
	render: function(responseData) {
		//console.time('renderList');
		var data = this.NodeViz.data;
		this.nodeLists = new Hash();
		$(this.listdiv).hide();
		$(this.listdiv).insert({ top: new Element('ul', {'id': 'list_menu'}) });
		//Build seperate sub lists for each node type
		$H(data.nodetypes).values().each( function(nodetype) {
			this.nodeLists[nodetype] = new Hash();
			$('list_menu').insert({ bottom: new Element('li', {'id': nodetype+'_menu'}).update(nodetype.toWordCase())});
			$(this.listdiv).insert({ bottom: new Element('div', {'id': nodetype+'_list_container', 'class': 'nodelist_container'}) });
			$(nodetype+'_list_container').insert({top: new Element('div', {'id': nodetype+'_list_header', 'class': 'nodelist_header'}).update(this.listHeader(nodetype))});

			//Create the search field for the top of the lsist
			var search = ' <label for="'+nodetype+'_search">Search</label> <input class="node_search" id="'+nodetype+'_search" autocomplete="off" size="20" type="text" value="" /> <div class="autocomplete node_search_list" id="'+nodetype+'_search_list" style="display:none"></div>';
			$(nodetype+'_list_header').insert({bottom: new Element('div', {'id': nodetype+'_search_container', 'class': 'node_search_container'}).update(search)});
			
			var nodelist = new Element('ul', {'id': nodetype+'_list', 'class': 'nodelist'});
			Event.observe($(nodetype+'_menu'), 'click', function(e) { this.displayList(nodetype); }.bind(this));
			var odd = 'even';
			$H(data.nodetypesindex[nodetype]).values().each( function(nodeid) {
				var node = data.nodes[nodeid];
				var label = '';
				if (node['label']) { 
					label = node['label'];
				} else if (node['id']) { 
					label = node['id'];
				}
				if (label != '') { 
					this.nodeLists[nodetype].set(label, nodeid);
				}
				odd = odd == 'odd' ? 'even' : 'odd';
				var nodelist_entry = new Element('li', {'id': 'list_'+nodeid, 'class': odd});
				nodelist_entry.update(this.listNodeEntry(node));
				this.NodeViz.addEvents(nodelist_entry, node, 'node', 'list');
				nodelist.insert({ bottom: nodelist_entry});

				//setup more sub lists for each connected node type
				var sublists= new Element('div', {'id': nodeid+'_sublists', 'class': 'sublists_container'});
				$H(data.edgetypes).keys().each( function(edgetype) {
					this.setupSubLists(node, edgetype, 'from', sublists); 
					this.setupSubLists(node, edgetype, 'to', sublists); 
				}, this);
				Event.observe(sublists, 'click', function(e) { e.stop(); }.bind(this.NodeViz));
				Event.observe(sublists, 'mouseover', function(e) { e.stop(); }.bind(this.NodeViz));
				nodelist_entry.insert({ bottom: sublists});
			}, this);
			$(nodetype+'_list_container').insert({ bottom: nodelist });
			if (this.nodeLists[nodetype].keys()[0]) { 
				new Autocompleter.Local(nodetype+'_search', nodetype+'_search_list', this.nodeLists[nodetype].keys(), {
					'partialChars': 1, 
					'fullSearch': true, 
					afterUpdateElement: function (t, l) {
						if (t.value && this.nodeLists[this.NodeViz.current.nodetype].get(t.value)) { 
							this.NodeViz.selectNode(this.nodeLists[this.NodeViz.current.nodetype].get(t.value));
						}
						t.value = '';
					}.bind(this)
				}, this);
			}
		}, this);
		this.displayList(data.nodetypes[0]);
		$(this.listdiv).show();
		//console.timeEnd('renderList');
	},
	displayList: function(nodetype) { 
		var oldnodetype = this.NodeViz.current['nodetype'];
		if(oldnodetype != '') { 
			$(oldnodetype+'_list_container').removeClassName('selected');
			$(oldnodetype+'_menu').removeClassName('selected');
		}
		$(nodetype+'_list_container').addClassName('selected');
		$(nodetype+'_menu').addClassName('selected');
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
			$H(node.relatedNodes).each( function(rnode) {
				var edgeid = rnode[1][0];
				var edge = data.edges[edgeid];
				if (edge['type'] == edgetype && edge[direction+'Id'] == nodeid) { 
					var snode = data.nodes[data.edges[edgeid][otherdir+'Id']];
					snode['edgeid'] = edgeid;
					nodes.push(snode);
				}
			}, this);
			if (nodes.size() >= 1) {
				var sublistdiv = nodediv+'_'+edgetype+'_'+direction;
				var classes = edgetype+'_list '+direction+'_list nodesublist';
				var elem = new Element('ul', {'id': sublistdiv,'class':classes});
				
				sublists.insert({ bottom: this.sublistHeader(node, edgetype, direction) });
				var odd='even';
				nodes.each(function(snode) {
					odd = odd == 'odd' ? 'even' : 'odd';
					var subelem = new Element('li', {'id': 'list_'+direction+'_'+snode['edgeid'], 'class': odd});

					subelem.update(this.listSubNodeEntry(snode, node, edgetype, direction));
					this.NodeViz.addEvents(subelem, snode, 'node', 'list');
					elem.insert({ bottom: subelem });
				}, this);
				sublists.insert({ bottom: elem });
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
			$H(highlightNode.relatedNodes[other_id]).values().each( function(edgeid) { 
				var edge = this.NodeViz.data.edges[edgeid];
				var type = edge.type;
				var dir = edge.toId == id ? 'from' : 'to';
				//var subnodeid = 'list_'+other_id+'_'+type+'_'+dir+'_'+id;
				var subnodeid = 'list_'+dir+'_'+edgeid;
				$(subnodeid).addClassName('highlight');
			}, this);
		} else {
			$('list_'+id).addClassName('highlight');
		}
	},
	unhighlightNode: function (id) { 
		var networkNode = this.NodeViz.data.nodes[this.NodeViz.current.network];
		var highlightNode = this.NodeViz.data.nodes[id];
		if(networkNode && networkNode.type != highlightNode.type && networkNode.type == this.NodeViz.current.nodetype) { 
			var other_id = this.NodeViz.current.network;
			$H(highlightNode.relatedNodes[other_id]).values().each( function(edgeid) { 
				var edge = this.NodeViz.data.edges[edgeid];
				var type = edge.type;
				var dir = edge.toId == id ? 'from' : 'to';
				//var subnodeid = 'list_'+other_id+'_'+type+'_'+dir+'_'+id;
				var subnodeid = 'list_'+dir+'_'+edgeid;
				$(subnodeid).removeClassName('highlight');
			}, this);
		} else { 
			$('list_'+id).removeClassName('highlight');
		}
	},
	selectNode: function(id) { 
		this.displayList(this.NodeViz.data.nodes[id].type);
		var elem = $('list_'+id);
		elem.addClassName('selected');
		$(id+'_sublists').setStyle({'display': 'block'});
		if (this.scrollList) { 
			elem.up().scrollTop = elem.offsetTop;
		}
	},
	unselectNode: function(id, fade) { 
		$('list_'+id).removeClassName('selected');
		$(id+'_sublists').setStyle({'display': 'none'});
		if (this.scrollList) { 
			$('list_'+id).parentNode.scrollTop = $('list_'+id).offsetTop-10;
		}
	}
};
