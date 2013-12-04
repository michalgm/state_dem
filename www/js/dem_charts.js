
/*	==========================================================================
	BAR CHART
	========================================================================== */

DEMBarChart = function(element) {
	var self = this;
	this.container = element;
	this.padding = 24;
	this.x = d3.scale.ordinal();
	this.y = d3.scale.linear();
	this.bar_width = .8;

//	this.width = $('#graphs-container').width();
//	this.height = ($(window).height() *.45) - 144;

	this.svg = d3.select(this.container+' svg>g');
	if (this.svg.empty()) {
		this.svg = d3.select(this.container).append('svg')
			//.style('height', this.height  + 'px')
			//.style('width', this.width + 'px')
			//.attr('width',width)
			//.attr('height',height)
			///.attr('viewBox', '0 0 '+width+' '+height)
			//.attr('perserveAspectRatio', "xMinYMid")
			.append('svg:g')
			//.attr('transform', "translate(0, "+(this.height-this.padding)+")");
	}

	this.line = d3.svg.line()
		.x(function(d, i) { return self.x(d.label) + self.x.rangeBand()/2; })
		.y(function(d) { return -self.y(d.average); })
	
	this.amountText = function(d, value) { 
		var text = '';
		if (self.y(d.y) > 14) {
			var aliases = {'carbon':'Miscellaneous', 'DEM':'Democrats', 'REP':'Republicans', 'IND':'Independants'};
			var label = aliases[d.label] ? aliases[d.label] : d.label;
			text = toWordCase(label)+': $' + commas(Math.floor(value));
		} 
		return text;
	}
	
	this.getWeight = function(x) { 
		return x == $('#cycle').val() ? 'bold' : 'normal';
	}

	this.setSize = function() {
		this.width = $('#graphs-container').width(),
		this.height = ($(window).height() *.45) - 144,

		this.x.rangeRoundBands([0,this.width], .05);

		var maxHeight = this.height - (this.padding*2);
		maxHeight = maxHeight > 0 ? maxHeight : 0;
		this.y.range([0, maxHeight]);

		this.svg.attr('transform', "translate(0, "+(this.height-this.padding)+")");

		d3.select(this.svg.node().parentNode)
			.style('height', this.height  + 'px')
			.style('width', this.width + 'px');
	}

	this.setSize();

	this.data = null;
}

DEMBarChart.prototype.draw = function (data) {
	var self = this;
	self.data = data;

	if (typeof(data[0]) == 'undefined') { // If there's no data, delete the svg and exit function
		self.svg.selectAll('*').remove();
		return;
	}

	var cats = $(data[0]).keys().map(function(i, d) { if (d != 'value' && d != 'label' && d!= 'average') { return d; }}).toArray();
	var categories = d3.layout.stack().offset('zero')(cats.map(function(cat) {
		return data.map(function(d) { 
			return {x: d.label, y: +d[cat], label: cat};
		});
	}));

	self.x.domain(categories[0].map(function(d) { return d.x; }));
	self.y.domain([0, d3.max(data, function(d) { return parseInt(d.value) > parseInt(d.average) ? parseInt(d.value) : parseInt(d.average); })]);

	var x = self.x, y=self.y, svg = self.svg;
	
	//The average lines
	if (! $('.averages').length) { 
		svg.append('g').attr('class', 'averages');
	}
	var averages = svg.selectAll('.averages');

	var lines = averages.selectAll("line")
		.data(data, function(d) { return d.label; })
		///.data(function(d) { return d; }, function(d) { return d.x; });

	//if ($('.average-line').length == 0) { 
	lines.enter().append('line')
		.attr('class', 'average-line')
		.style('opacity','0')
		.attr('x1', function(d) { return x(d.label); })
		.attr('x2', function(d) { return x(d.label) + x.rangeBand(); })
		.attr("y1", 0)
		.attr("y2", 0)
		.on("mouseover", function(d, e) { gf.renderers.GraphImage.showTooltip('Average: $'+format(d.average)); gf.renderers.GraphImage.mousemove(d3.event); })
		.on("mouseout", function() { gf.renderers.GraphImage.hideTooltip(); })
	//}

	lines.transition()
		.duration(1000)
		.attr('x1', function(d) { return x(d.label); })
		.attr('x2', function(d) { return x(d.label) + x.rangeBand(); })
		.attr("y1", function(d) { return - y(d.average); })
		.attr("y2", function(d) { return - y(d.average); })
		.style('opacity','1')

	lines.exit().transition()
		.duration(200)
		.style('opacity','0')
		.remove();


	//The category groups
	var category = svg.selectAll("g.category")
		.data(categories) 

	category.enter().append('svg:g')
		.attr('class', function(d) { return 'category '+d[0].label;})

	category.transition()
		.attr('class', function(d) { return 'category '+d[0].label;})

	//The filled rectangles
	var rect = category.selectAll("rect")
		.data(function(d) { return d; }, function(d) { return d.x; })

	rect.enter().append("svg:rect")
		.attr("x", function(d) { return x(d.x) + (x.rangeBand()*(1-self.bar_width))/2; })
		.attr("width", x.rangeBand()*self.bar_width)
		.attr("y", 0)
		.attr("height", 0)
		.style('opacity','0');

	rect.transition()
		.duration(1000)
		.delay(!rect.exit().empty()*200)
		.attr("x", function(d) { return x(d.x) + (x.rangeBand()*(1-self.bar_width))/2; })
		.attr("y", function(d) { return - y(d.y0) - y(d.y); })
		.attr("height", function(d) { return y(d.y); })
		.attr("width", x.rangeBand() *self.bar_width)
		.style('opacity','1');

	rect.exit().transition()
		.duration(200)
		.attr('height', 0)
		.attr('y', 0)
		.style('opacity','0')
		.remove();

	//The amount labels centered inside the bars (unless the associated band is < padding)
	var amounts_group = svg.selectAll('g.amounts')
		.data(categories)

	amounts_group.enter().append('svg:g')
		.attr('class', 'amounts')

	var amounts = amounts_group.selectAll('.amount')
		.data(function(d) { return d; }, function(d) { return d.x; });

	amounts.enter().append('text')
		.attr('class','chart-label amount')
		.attr('dominant-baseline', 'middle')
		.attr('text-anchor','middle')
		.attr('y', function(d) { return 0+(y(d.y)/2); } )
		.style('fill','#fff')
		.style('opacity','0')

	amounts.transition()
		.delay(!amounts.exit().empty()*200)
		.duration(1000)
		.style('opacity','1')
		.attr('x',function(d) { return x(d.x) + (x.rangeBand() / 2); })
		.attr('y',function(d) { return - y(d.y0) - y(d.y) + (y(d.y)/2); })
		.attr('width',function() { return x.rangeBand(); })
		.tween('text', function(d) { 
			var i = d3.interpolate(this.textContent.replace(/[^0-9]+/g, ''), d.y);
			return function(t) { 
				this.textContent = self.amountText(d, i(t));
			}
		})

	amounts.exit().transition()
		.duration(200)
		.attr('y', function(d) { return 0+(y(d.y)/2); } )
		.style('opacity','0')
		.remove();
	
	//The year labels below the bars
	var years = svg.selectAll('.year')
		.data(data, function(d) { return d.label; })

	years.enter().append('text')
		.attr('class','chart-label year')
		.attr('x',function(d, i) { return x(d.label) + x.rangeBand()/2; })
		.attr('y',0)
		.attr('width',function() { return x.rangeBand(); })
		.text(function(d, i) { return d.label; })
		.attr('text-anchor','middle')
		.style('opacity','0')
		.attr('dominant-baseline', 'text-before-edge')

	years.transition()
		.delay(!years.exit().empty()*200)
		.duration(1000)
		.attr('x',function(d, i) { return x(d.label) + x.rangeBand()/2; })
		.style('opacity','1')
		.style('font-weight', function(d) { return self.getWeight(d.label)});

	years.exit().transition()
		.duration(200)
		.style('opacity','0')
		.remove();

	//The total labels above the bars
	var totals = svg.selectAll('.total')
		.data(data, function(d) { return d.label; })

	totals.enter().append('text')
		.attr('class','chart-label total')
		.attr('x',function(d, i) { return x(d.label) + x.rangeBand()/2; })
		.attr('y', function(d) { return -y(d.value) -(self.padding); })
		.attr('width',function() { return x.rangeBand(); })
		.attr('dominant-baseline', 'text-before-edge')
		.attr('text-anchor','middle')
		.style('opacity','0')

	totals.transition()
		.delay(!totals.exit().empty()*200)
		.duration(1000)
		.attr('x',function(d, i) { return x(d.label) + x.rangeBand()/2; })
		.attr('y', function(d) { return -y(d.value) -(self.padding); })
		.style('opacity','1')
		.tween('text', function(d) { 
			var i = d3.interpolate(this.textContent.replace(/[^0-9]+/g, ''), d.value);
			return function(t) { 
				this.textContent = '$' + commas(Math.floor(i(t)));
			}
		})
		.style('font-weight', function(d) { return self.getWeight(d.label)});

	totals.exit().transition()
		.duration(200)
		.style('opacity','0')
		.remove();
}

DEMBarChart.prototype.resize = function() { 
	this.setSize();

	var self = this;
	var x = self.x, y=self.y, svg = self.svg;

	svg.selectAll('rect')
		.attr("height", function(d) { return y(d.y); })
		.attr("width", x.rangeBand() *self.bar_width)
		.attr("y", function(d) { return - y(d.y0) - y(d.y); })
		.attr("x", function(d) { return x(d.x) + (x.rangeBand()*(1-self.bar_width))/2; })

	svg.selectAll('.amount')
		.attr('x',function(d) { return x(d.x) + (x.rangeBand() / 2); })
		.attr('y',function(d) { return - y(d.y0) - y(d.y) + (y(d.y)/2); })
		.attr('width',function() { return x.rangeBand(); })
		.text(function(d) { return self.amountText(d, d.y); })
	
	svg.selectAll('.average-line')
		.attr('x1', function(d) { return x(d.label); })
		.attr('x2', function(d) { return x(d.label) + x.rangeBand(); })
		.attr("y1", function(d) { return - y(d.average); })
		.attr("y2", function(d) { return - y(d.average); })

	svg.selectAll('.year')
		.attr('x',function(d, i) { return x(d.label) + x.rangeBand()/2; })
		.attr('width',function() { return x.rangeBand(); })

	svg.selectAll('.total')
		.attr('x',function(d, i) { return x(d.label) + x.rangeBand()/2; })
		.attr('y', function(d) { return -y(d.value) -(self.padding); })
		.attr('width',function() { return x.rangeBand(); })
}

/*	==========================================================================
	PIE CHARTS
	========================================================================== */

function drawPieChart(data, container) {
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

	var svg = d3.select(this.container+' svg>g');
	if (svg.empty()) {
		svg = d3.select(this.container).append('svg')
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

