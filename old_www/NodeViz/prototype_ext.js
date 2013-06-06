/**
* Extensions to Prototype. Tested with v1.6.0.3
*
* (c) 2009 Eric Nguyen
*
* Freely distributable under the same terms of an MIT-style license that
* Prototype uses. For details, see the Prototype web site:
* http://www.prototypejs.org/
*
* Includes parts of the following:
*
* - extensions to Form.Element.enable/disable for buttons
*
* - Cursor position code
*
* - getters and setters for form radio and checkbox inputs
*
*/

Object.extend( Form.Element.Methods, {
  
  getValue: function(element) {
    element = $(element);
    var method;
     // detect radio button and checkbox collections
    if ("length" in element && element[0] && "type" in element[0] && element[0].type == "radio") {
      method = "manyRadioSelector";
    } else if ("length" in element && element[0] && "type" in element[0] && element[0].type == "checkbox") {
        method = "manyCheckboxSelector";
    } else {
      method = element.tagName.toLowerCase();
    }
    return Form.Element.Serializers[method](element);
  },
  
  setValue: function(element, value) {
    element = $(element);
    var method;
    // detect radio button and checkbox collections
    if ("length" in element && "type" in element[0] && element[0].type == "radio") {
      method = "manyRadioSelector";
    } else if ("length" in element && "type" in element[0] && element[0].type == "checkbox") {
        method = "manyCheckboxSelector";
    } else {
      method = element.tagName.toLowerCase();
    }
    Form.Element.Serializers[method](element, value);
    return element;
  },
  
  disable: function(element) {
    element = $(element);
    element.disabled = true;
    if ( element.type == "button" ) {
      element.addClassName( "disabled" );
    }
    return element;
  },

  enable: function(element) {
    element = $(element);
    element.disabled = false;
    if ( element.type == "button" ) {
      element.removeClassName( "disabled" );
    }
    return element;
  },
  
  setCursorPosition: function(element, start, end) {
    if (end == null) end = start;
    if( element.setSelectionRange ) {
      element.setSelectionRange(start,end);
    } else if( element.createTextRange ) {
      var range = element.createTextRange();
      range.collapse(true);
      range.moveEnd('character',end);
      range.moveStart('character',start);
      range.select();
    }
    return element;
  },
  
  getCursorPosition: function(element) {
    if (element.setSelectionRange) {
      // Mozilla or FireFox Code
      return element.selectionStart;
      
    } else if (document.selection && document.selection.createRange) {
      // Internet Explorer Code
      // from http://stackoverflow.com/questions/164147/character-offset-in-an-internet-explorer-textrange
      function getRangeOffsetIE( r ) {
        var end = Math.abs( r.duplicate().moveEnd('character', -1000000) );
        // find the anchor element's offset
        var range = r.duplicate();
        r.collapse( false );
        var parentElm = range.parentElement();
        var children = parentElm.getElementsByTagName('*');
        for (var i = children.length - 1; i >= 0; i--) {
          range.moveToElementText( children[i] );
          if ( range.inRange(r) ) {
            parentElm = children[i];
            break;
          }
        }
        range.moveToElementText( parentElm );
        return end - Math.abs( range.moveStart('character', -1000000) );
      }
      return getRangeOffsetIE( document.selection.createRange() );
    }
  }
});

Object.extend( Form.Element.Serializers, {
  manyRadioSelector: function(elements, value) {
    if (Object.isUndefined(value)) {
      var checkedRadio = $A(elements).find(function(radio) { return radio.checked; });
      return (checkedRadio ? checkedRadio.value : null);
    } else {
      var checkedRadio = $A(elements).find(function(radio) { return radio.value == value; });
      if (checkedRadio) checkedRadio.checked = true;
    }
  },
  
  manyCheckboxSelector: function(elements, values) {
    if (Object.isUndefined(values)) {
      var checkedCbs = $A(elements).findAll(function(cb) { return cb.checked; });
      return (checkedCbs ? checkedCbs.pluck("value") : []);
    } else {
      return $A(elements).findAll( function(cb) {
       cb.checked = values.member(cb.value);
       return cb.checked;
      });
    }
  }
});

// Re-bind form element methods
Element.addMethods();
Field = Form.Element;
$F = Form.Element.Methods.getValue;
var ext = {
	getClassName: function(element) { 
		className = element.className;
		if (typeof className.baseVal === 'undefined') { 
			return className;
		} else {
			return className.baseVal;
		}
	},
	setClassName: function(element, className) {
		if(typeof element.className.baseVal === 'undefined') {
			element.className = className;
		} else {
			element.className.baseVal = className;
		}
		return element;
	}
}
Element.addMethods(ext);
//maybe this will make svg animation work?
//copy(Element.Methods, SVGElement.prototype);
//SVGElement.prototype.setStyle = Element.setStyle;
//SVGElement.prototype.makePositioned = Element.makePositioned;
//SVGElement.prototype.getStyle = Element.getStyle;
if (typeof SVGElement !== 'undefined') { 
	hack_extend(Element.Methods, SVGElement.prototype);
	hack_extend(Effect.Methods, SVGElement.prototype);
	SVGElement.prototype.className = function() { this.getAttribute('class'); }
}
function hack_extend(methods, destination) {
    onlyIfAbsent = true;
    for (var property in methods) {
      var value = methods[property];
      if (!Object.isFunction(value)) continue;
      if (!onlyIfAbsent || !(property in destination))
        destination[property] = value.methodize();
    }
}

Effect.Translate = Class.create(Effect.Base, {
  initialize: function(element) {
    this.element = $(element);
    if (!this.element) throw(Effect._elementDoesNotExistError);
    var options = Object.extend({
      x:    0,
      y:    0,
	  ctm: this.element.getCTM()
    }, arguments[1] || { });
    this.start(options);
  },
  setup: function() {
	var matrix = this.options.ctm;
	var trans_matrix = matrix.translate(this.options.x, this.options.y);
    this.originalLeft = (matrix.e || '0');
    this.originalTop  = (matrix.f || '0');
	this.options.x = trans_matrix.e - this.originalLeft;
	this.options.y = trans_matrix.f - this.originalTop;
  },
  update: function(position) {
	var matrix = this.options.ctm;
	matrix.e = this.options.x*position+ this.originalLeft;
	matrix.f = this.options.y*position + this.originalTop;
	var s = "matrix(" + matrix.a + "," + matrix.b + "," + matrix.c + "," + matrix.d + "," + matrix.e + "," + matrix.f + ")";
	this.element.setAttribute('transform', s);
	$('underlay_'+this.element.id).setAttribute('transform', s);
  }
});


Effect.AnimateZoom = Class.create(Effect.Base, {
  initialize: function(element) {
    this.element = $(element);
    if (!this.element) throw(Effect._elementDoesNotExistError);
    var options = Object.extend({
      zoom:    0,
	  point:	0,
	  ctm: this.element.getCTM()
    }, arguments[1] || { });
    this.start(options);
  },
  setup: function() {
	//var matrix = this.options.ctm;
	//var trans_matrix = matrix.translate(this.options.x, this.options.y);
	//this.orig_zoom = matrix.a;
	//this.zoom_level = this.options.zoomctm.a - this.orig_zoom;
	this.options.zoom = this.options.zoom - 1;
  this.matrix = this.element.getCTM();
  },
  update: function(position) {
	var k = this.element.ownerSVGElement.createSVGMatrix().translate(this.options.point.x, this.options.point.y).scale(1 + (this.options.zoom*position)).translate(-this.options.point.x, -this.options.point.y);
	//console.log(this.options.zoom*position);
	var matrix = this.matrix.multiply(k);
	var s = "matrix(" + matrix.a + "," + matrix.b + "," + matrix.c + "," + matrix.d + "," + matrix.e + "," + matrix.f + ")";
	this.element.setAttribute('transform', s);
	$('underlay_'+this.element.id).setAttribute('transform', s);
  }
});

//Word-case strings - from http://www.mediacollege.com/internet/javascript/text/case-capitalize.html
Object.extend(String.prototype, {
	toWordCase: function(){
	   return this.toLowerCase().replace( /(^|\s)([a-z])/g , function(m,p1,p2){ return p1+p2.toUpperCase(); } );
	}
});

/* - Ajax.Request.abort
* (from http://blog.pothoven.net/2007/12/aborting-ajax-requests-for-prototypejs.html)
*/
Object.extend(Ajax.Request.prototype, {
	abort: function() {
		// prevent and state change callbacks from being issued
		this.transport.onreadystatechange = Prototype.emptyFunction;
		// abort the XHR
		this.transport.abort();
		// update the request counter
		Ajax.activeRequestCount--;
	}
});

Object.extend(Number.prototype, {
	format: function(num) {
		return this.toString().split( /(?=(?:\d{3})+(?:\.|$))/g ).join( "," );a
	}
});

