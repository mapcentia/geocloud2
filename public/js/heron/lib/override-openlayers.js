/*
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/*
 * Overiding OpenLayers to add support for GML 3.2.1
 *
 */
OpenLayers.Util.extend(OpenLayers.Format.WFST.v1.prototype.namespaces,
{
    gml32: 'http://www.opengis.net/gml/3.2'
});

/**
 * Method: parseLocations
 * Parse the locations from an Atom entry or feed.
 *
 * Parameters:
 * node - {DOMElement} An Atom entry or feed node.
 *
 * Returns:
 * Array({<OpenLayers.Geometry>})
 */
OpenLayers.Format.Atom.prototype.parseLocations = function(node) {
	// NOTE: Just van den Broecke: 8.dec.2012. See
	// Fix for https://github.com/openlayers/openlayers/issues/789
	// on OpenLayers 2.12 (line 105 below)
     var georssns = this.namespaces.georss;

     var locations = {components: []};
     var where = this.getElementsByTagNameNS(node, georssns, "where");
     if (where && where.length > 0) {
         if (!this.gmlParser) {
             this.initGmlParser();
         }
         for (var i=0, ii=where.length; i<ii; i++) {
             this.gmlParser.readChildNodes(where[i], locations);
         }
     }

     var components = locations.components;
     var point = this.getElementsByTagNameNS(node, georssns, "point");
     if (point && point.length > 0) {
         for (var i=0, ii=point.length; i<ii; i++) {
             var xy = OpenLayers.String.trim(
                         point[i].firstChild.nodeValue
                         ).split(/\s+/);
             if (xy.length !=2) {
                 xy = OpenLayers.String.trim(
                             point[i].firstChild.nodeValue
                             ).split(/\s*,\s*/);
             }
             components.push(new OpenLayers.Geometry.Point(xy[1], xy[0]));
         }
     }

     var line = this.getElementsByTagNameNS(node, georssns, "line");
     if (line && line.length > 0) {
         var coords;
         var p;
         var points;
         for (var i=0, ii=line.length; i<ii; i++) {
             coords = OpenLayers.String.trim(
                             line[i].firstChild.nodeValue
                             ).split(/\s+/);
             points = [];
             for (var j=0, jj=coords.length; j<jj; j+=2) {
                 p = new OpenLayers.Geometry.Point(coords[j+1], coords[j]);
                 points.push(p);
             }
             components.push(
                 new OpenLayers.Geometry.LineString(points)
             );
         }
     }

     var polygon = this.getElementsByTagNameNS(node, georssns, "polygon");
     if (polygon && polygon.length > 0) {
         var coords;
         var p;
         var points;
         for (var i=0, ii=polygon.length; i<ii; i++) {
             coords = OpenLayers.String.trim(
                         polygon[i].firstChild.nodeValue
                         ).split(/\s+/);
             points = [];
             for (var j=0, jj=coords.length; j<jj; j+=2) {
                 p = new OpenLayers.Geometry.Point(coords[j+1], coords[j]);
                 points.push(p);
             }
             components.push(
                 new OpenLayers.Geometry.Polygon(
                     [new OpenLayers.Geometry.LinearRing(points)]
                 )
             );
         }
     }

     if (this.internalProjection && this.externalProjection) {
         for (var i=0, ii=components.length; i<ii; i++) {
             if (components[i]) {
                 components[i].transform(
                     this.externalProjection,
                     this.internalProjection
                 );
             }
         }
     }

     return components;
 };


/*OpenLayers.Util.extend(OpenLayers.Format.WFST.v1_1_0.prototype.readers,
 {"gml32": OpenLayers.Format.GML.v3.prototype.readers["gml"]});
 OpenLayers.Format.WFST.v1_0_0.prototype.namespaces.gml32 = 'http://www.opengis.net/gml/3.2';
 */

// do some overrides for OpenLayers to correctly use GetFeatureInfo from the TNO services
// http://trac.osgeo.org/openlayers/ticket/3176 (will be fixed in OL 2.11)

/* --- Fixed in OL 2.11 ---

OpenLayers.Control.WMSGetFeatureInfo.prototype.buildWMSOptions = function(url, layers, clickPosition, format) {
    var layerNames = [], styleNames = [];
    var i;
    for (i = 0,len = layers.length; i < len; i++) {
        layerNames = layerNames.concat(layers[i].params.LAYERS);
        styleNames = styleNames.concat(this.getStyleNames(layers[i]));
    }
    var firstLayer = layers[0];
    // use the firstLayer's projection if it matches the map projection -
    // this assumes that all layers will be available in this projection
    var projection = this.map.getProjection();
    var layerProj = firstLayer.projection;
    if (layerProj && layerProj.equals(this.map.getProjectionObject())) {
        projection = layerProj.getCode();
    }
    var params = OpenLayers.Util.extend({
        service: "WMS",
        version: firstLayer.params.VERSION,
        request: "GetFeatureInfo",
        layers: layerNames,
        query_layers: layerNames,
        styles: styleNames,
        bbox: this.map.getExtent().toBBOX(null,
            firstLayer.reverseAxisOrder()),
        feature_count: this.maxFeatures,
        height: this.map.getSize().h,
        width: this.map.getSize().w,
        format: format,
        info_format: firstLayer.params.INFO_FORMAT || this.infoFormat
    }, (parseFloat(firstLayer.params.VERSION) >= 1.3) ?
    {
        crs: projection,
        i: parseInt(clickPosition.x, 10),
        j: parseInt(clickPosition.y, 10)
    } :
{
        srs: projection,
        x: parseInt(clickPosition.x, 10),
        y: parseInt(clickPosition.y, 10)
    }
    );
    OpenLayers.Util.applyDefaults(params, this.vendorParams);
    return {
        url: url,
        params: OpenLayers.Util.upperCaseObject(params),
        callback: function(request) {
            this.handleResponse(clickPosition, request);
        },
        scope: this
    };
};
*/

// http://trac.osgeo.org/openlayers/ticket/3177 (might be fixed in OL 2.11)

/* --- Fixed in OL 2.11 ---

OpenLayers.Format.WMSGetFeatureInfo.prototype.read_FeatureInfoResponse = function(data) {
    var response = [];
    var featureNodes = this.getElementsByTagNameNS(data, '*',
        'FIELDS');
    var i;
    var len;
    for (i = 0,len = featureNodes.length; i < len; i++) {
        var featureNode = featureNodes[i];
        var geom = null;

        // attributes can be actual attributes on the FIELDS tag, or FIELD children
        var attributes = {};
        var j;
        var jlen = featureNode.attributes.length;
        if (jlen > 0) {
            for (j = 0; j < jlen; j++) {
                var attribute = featureNode.attributes[j];
                attributes[attribute.nodeName] = attribute.nodeValue;
            }
        } else {
            var nodes = featureNode.childNodes;
            var _featureType = "";
            for (j = 0,jlen = nodes.length; j < jlen; ++j) {
                var node = nodes[j];
                if (node.nodeType !== 3) {
                    //Dirty fix for dino name needs to be stripped as it consists of 3 parts
                    var dino_name = node.getAttribute("name");
                    var _feat = dino_name.split(".");
                    if(_feat[0] === "DINO_DBA"){
                        attributes[_feat[2]] = node.getAttribute("value");
                        _featureType = _feat[1];
                    } else {
                        attributes[node.getAttribute("name")] = node.getAttribute("value");
                    }
                }
            }
        }
        _feature = new OpenLayers.Feature.Vector(geom, attributes, null);

        if(_featureType !== ""){
            // Dirty fix for dino to maintain reference to layer
            _feature.gml = {};
            _feature.gml.featureType = _featureType;
            _feature.fid = _featureType + "." + len;
            _feature.layer = _featureType;
        }
        response.push(_feature);
    }
    return response;
};
*/

/**
 * Method: read_FeatureInfoResponse
 * Parse FeatureInfoResponse nodes.
 *
 * Parameters:
 * data - {DOMElement}
 *
 * Returns:
 * {Array}
 */
/* OpenLayers.Format.WMSGetFeatureInfo.prototype.read_FeatureInfoResponse = function(data) {
    var response = [];
    var featureNodes = this.getElementsByTagNameNS(data, '*',
        'FIELDS');
	if (featureNodes.length == 0) {
		featureNodes = this.getElementsByTagNameNS(data, '*', 'Field');
	}
    for(var i=0, len=featureNodes.length;i<len;i++) {
        var featureNode = featureNodes[i];
        var geom = null;

        // attributes can be actual attributes on the FIELDS tag,
        // or FIELD children
        var attributes = {};
        var j;
        var jlen = featureNode.attributes.length;
        if (jlen > 0) {
            for(j=0; j<jlen; j++) {
                var attribute = featureNode.attributes[j];
                attributes[attribute.nodeName] = attribute.nodeValue;
            }
        } else {
            var nodes = featureNode.childNodes;
            for (j=0, jlen=nodes.length; j<jlen; ++j) {
                var node = nodes[j];
                if (node.nodeType != 3) {
                    attributes[node.getAttribute("name")] =
                        node.getAttribute("value");
                }
            }
        }

        response.push(
            new OpenLayers.Feature.Vector(geom, attributes, null)
        );
    }
    return response;
};
*/
// JvdB 11.05.2011 Taken from OpenLayers 2.10 to fix this issue:
// http://code.google.com/p/geoext-viewer/issues/detail?id=39

/**
 * Function: modifyDOMElement
 *
 * Modifies many properties of a DOM element all at once.  Passing in
 * null to an individual parameter will avoid setting the attribute.
 *
 * Parameters:
 * id - {String} The element id attribute to set.
 * px - {<OpenLayers.Pixel>} The left and top style position.
 * sz - {<OpenLayers.Size>}  The width and height style attributes.
 * position - {String}       The position attribute.  eg: absolute,
 *                           relative, etc.
 * border - {String}         The style.border attribute.  eg:
 *                           solid black 2px
 * overflow - {String}       The style.overview attribute.
 * opacity - {Float}         Fractional value (0.0 - 1.0)
 */
OpenLayers.Util.modifyDOMElement = function(element, id, px, sz, position,
    border, overflow, opacity) {

    if (id) {
        element.id = id;
    }
    if (px) {
        if (!px.x) {
            // JvdB: fix for IE who cannot deal with NaN
            px.x = 0;
        }
        if (!px.y) {
            // JvdB: fix for IE who cannot deal with NaN
            px.y = 0;
        }
        element.style.left = px.x + "px";
        element.style.top = px.y + "px";
    }
    if (sz) {
        element.style.width = sz.w + "px";
        element.style.height = sz.h + "px";
    }
    if (position) {
        element.style.position = position;
    }
    if (border) {
        element.style.border = border;
    }
    if (overflow) {
        element.style.overflow = overflow;
    }
    if (parseFloat(opacity) >= 0.0 && parseFloat(opacity) < 1.0) {
        element.style.filter = 'alpha(opacity=' + (opacity * 100) + ')';
        element.style.opacity = opacity;
    } else if (parseFloat(opacity) == 1.0) {
        element.style.filter = '';
        element.style.opacity = '';
    }
};


/**
 * Donald Kerr's fix to label outlines (Halo's) in IE8
 * http://trac.osgeo.org/openlayers/ticket/2965
 https://github.com/openlayers/openlayers/pull/140
 */
// Later: does not seem to work now...


// https://code.google.com/p/geoext-viewer/issues/detail?id=185
// Integrate from OL patch.
// http://trac.osgeo.org/openlayers/ticket/3608
// Milestone: 2.13
// setOpacity won't work on a vector layer, if the vector layer is with other vector
// layers in a SelectFeature-Control (the SelectFeature-Control has than a Vect.rootContainer).
// With the following it will work:
// REMOVE/CHECK for OL 2.13 !!
OpenLayers.Layer.Vector.prototype.setOpacity = function(opacity) {
	if (opacity != this.opacity) {
		this.opacity = opacity;
		var element = this.renderer.root;
		OpenLayers.Util.modifyDOMElement(element, null, null, null, null,
				null, null, opacity);
		if (this.map != null) {
			this.map.events.triggerEvent("changelayer", {
				layer : this,
				property : "opacity"
			});
		}
	}
};

// 18.2.14 Solve issues with PrintPreview not cloning all properties for Vector features
// in particular not showing OLE text labels with renderIntent defaultLabel.
// https://code.google.com/p/geoext-viewer/issues/detail?id=331
/**
  * Method: clone
  * Create a clone of this vector feature.  Does not set any non-standard
  *     properties.
  *
  * Returns:
  * {<OpenLayers.Feature.Vector>} An exact clone of this vector feature.
  */
OpenLayers.Feature.Vector.prototype.clone = function () {
     var clone = new OpenLayers.Feature.Vector(
         this.geometry ? this.geometry.clone() : null,
         this.attributes,
         this.style);

    clone.renderIntent = this.renderIntent;
    return clone;
 };


// Solve issues with feature selection for features that have individual styles.
/**
 * Method: highlight
 * Redraw feature with the select style.
 *
 * Parameters:
 * feature - {<OpenLayers.Feature.Vector>}
 */
OpenLayers.Control.SelectFeature.prototype.highlight = function(feature) {
    var layer = feature.layer;
    var cont = this.events.triggerEvent("beforefeaturehighlighted", {
        feature : feature
    });
    if(cont !== false) {
        feature._prevHighlighter = feature._lastHighlighter;
        feature._lastHighlighter = this.id;

        // Solve issues with feature selection for features that have individual styles.
        // Use the Layer select style in that case
        if (feature.style && !this.selectStyle && layer.styleMap) {
            var styleMap = layer.styleMap;
            var selectStyle = styleMap.styles['select'];
            if (selectStyle) {
                var defaultStyle = styleMap.styles['default'].clone();
                this.selectStyle = OpenLayers.Util.extend(defaultStyle.defaultStyle, selectStyle.defaultStyle);
            }
        }
        var style = this.selectStyle || this.renderIntent;

        layer.drawFeature(feature, style);
        this.events.triggerEvent("featurehighlighted", {feature : feature});
    }
};


//
// 13.2.2014 - CHANGES for WMSGetFeatureInfo
//
// Version from GitHub (OL 2.12+2.13 are same for these funcs)
// * change: new config param 'requestPerLayer': do not bundle requests for same URL
// * change: allow per-layer vendor params
// Changes indicated on lines with 'JvdB'.
// Changes were required to allow  GFI for WMS "sublayers" based on CQL (or other query lang).
// See example: http://lib.heron-mc.org/heron/latest/examples/sublayers
//
/* Copyright (c) 2006-2012 by OpenLayers Contributors (see authors.txt for
 * full list of contributors). Published under the 2-clause BSD license.
 * See license.txt in the OpenLayers distribution or repository for the
 * full text of the license. */



/**
 * Class: OpenLayers.Control.WMSGetFeatureInfo
 * The WMSGetFeatureInfo control uses a WMS query to get information about a point on the map.  The
 * information may be in a display-friendly format such as HTML, or a machine-friendly format such
 * as GML, depending on the server's capabilities and the client's configuration.  This control
 * handles click or hover events, attempts to parse the results using an OpenLayers.Format, and
 * fires a 'getfeatureinfo' event with the click position, the raw body of the response, and an
 * array of features if it successfully read the response.
 *
 * Inherits from:
 *  - <OpenLayers.Control>
 */

/**
 * Method: buildWMSOptions
 * Build an object with the relevant WMS options for the GetFeatureInfo request
 *
 * Parameters:
 * url - {String} The url to be used for sending the request
 * layers - {Array(<OpenLayers.Layer.WMS)} An array of layers
 * clickPosition - {<OpenLayers.Pixel>} The position on the map where the mouse
 *     event occurred.
 * format - {String} The format from the corresponding GetMap request
 */
OpenLayers.Control.WMSGetFeatureInfo.prototype.buildWMSOptions = function (url, layers, clickPosition, format) {
    var layerNames = [], styleNames = [];
    for (var i = 0, len = layers.length; i < len; i++) {
        if (layers[i].params.LAYERS != null) {
            layerNames = layerNames.concat(layers[i].params.LAYERS);
            styleNames = styleNames.concat(this.getStyleNames(layers[i]));
        }
    }
    var firstLayer = layers[0];
    // use the firstLayer's projection if it matches the map projection -
    // this assumes that all layers will be available in this projection
    var projection = this.map.getProjection();
    var layerProj = firstLayer.projection;
    if (layerProj && layerProj.equals(this.map.getProjectionObject())) {
        projection = layerProj.getCode();
    }
    var params = OpenLayers.Util.extend({
            service: "WMS",
            version: firstLayer.params.VERSION,
            request: "GetFeatureInfo",
            exceptions: firstLayer.params.EXCEPTIONS,
            bbox: this.map.getExtent().toBBOX(null,
                firstLayer.reverseAxisOrder()),
            feature_count: this.maxFeatures,
            height: this.map.getSize().h,
            width: this.map.getSize().w,
            format: format,
            info_format: firstLayer.params.INFO_FORMAT || this.infoFormat
        }, (parseFloat(firstLayer.params.VERSION) >= 1.3) ?
    {
        crs: projection,
        i: parseInt(clickPosition.x),
        j: parseInt(clickPosition.y)
    } :
    {
        srs: projection,
        x: parseInt(clickPosition.x),
        y: parseInt(clickPosition.y)
    }
    );
    if (layerNames.length != 0) {
        params = OpenLayers.Util.extend({
            layers: layerNames,
            query_layers: layerNames,
            styles: styleNames
        }, params);
    }

    // JvdB : Apply per-layer vendor params like CQL if present
    OpenLayers.Util.applyDefaults(params, firstLayer.params.vendorParams);

    OpenLayers.Util.applyDefaults(params, this.vendorParams);
    return {
        url: url,
        params: OpenLayers.Util.upperCaseObject(params),
        callback: function (request) {
            this.handleResponse(clickPosition, request, url);
        },
        scope: this
    };
};

/**
 * Method: request
 * Sends a GetFeatureInfo request to the WMS
 *
 * Parameters:
 * clickPosition - {<OpenLayers.Pixel>} The position on the map where the
 *     mouse event occurred.
 * options - {Object} additional options for this method.
 *
 * Valid options:
 * - *hover* {Boolean} true if we do the request for the hover handler
 */
OpenLayers.Control.WMSGetFeatureInfo.prototype.request = function (clickPosition, options) {
    var layers = this.findLayers();
    if (layers.length == 0) {
        this.events.triggerEvent("nogetfeatureinfo");
        // Reset the cursor.
        OpenLayers.Element.removeClass(this.map.viewPortDiv, "olCursorWait");
        return;
    }

    options = options || {};
    if (this.drillDown === false) {
        var wmsOptions = this.buildWMSOptions(this.url, layers,
            clickPosition, layers[0].params.FORMAT);
        var request = OpenLayers.Request.GET(wmsOptions);

        if (options.hover === true) {
            this.hoverRequest = request;
        }
    } else {
        this._requestCount = 0;
        this._numRequests = 0;
        this.features = [];
        // group according to service url to combine requests
        var services = {}, url;
        for (var i = 0, len = layers.length; i < len; i++) {
            var layer = layers[i];
            var service, found = false;
            url = OpenLayers.Util.isArray(layer.url) ? layer.url[0] : layer.url;
            if (url in services) {
                services[url].push(layer);
            } else {
                this._numRequests++;
                services[url] = [layer];
            }
        }
        var layers;
        for (var url in services) {
            layers = services[url];
            // JvdB: in some sames the client does not want to bundle requests
            // for multiple Layers from same server, e.g. with CQL-based requests
            // the responses need to be tied to the CQL sublayer.
            if (this.requestPerLayer) {
                for (var l = 0, len = layers.length; l < len; l++) {
                    var wmsOptions = this.buildWMSOptions(url, [layers[l]],
                        clickPosition, layers[0].params.FORMAT);
                    var req = OpenLayers.Request.GET(wmsOptions);

                    // Tie the Layer to the request as we can determine
                    // to which Layer a response belongs
                    req.layer = layers[l];
                }
                // Increment request-count as we had 1 req per url above
                this._numRequests += layers.length - 1;
            } else {
                var wmsOptions = this.buildWMSOptions(url, layers,
                    clickPosition, layers[0].params.FORMAT);
                OpenLayers.Request.GET(wmsOptions);
            }
        }
    }
};


/**
 * Method: handleResponse
 * Handler for the GetFeatureInfo response.
 *
 * Parameters:
 * xy - {<OpenLayers.Pixel>} The position on the map where the
 *     mouse event occurred.
 * request - {XMLHttpRequest} The request object.
 * url - {String} The url which was used for this request.
 */
OpenLayers.Control.WMSGetFeatureInfo.prototype.handleResponse = function (xy, request, url) {

    var doc = request.responseXML;
    if (!doc || !doc.documentElement) {
        doc = request.responseText;
    }
    var features = this.format.read(doc);

    // JvdB remember the Layer e.g. to discern Layer subsets (via CQL)
    if (request.layer && features) {
        for (var f = 0; f < features.length; f++) {
            features[f].layer = request.layer;
        }
    }
    if (this.drillDown === false) {
        this.triggerGetFeatureInfo(request, xy, features);
    } else {
        this._requestCount++;
        if (this.output === "object") {
            this._features = (this._features || []).concat(
                {url: url, features: features}
            );
        } else {
            this._features = (this._features || []).concat(features);
        }
        if (this._requestCount === this._numRequests) {
            this.triggerGetFeatureInfo(request, xy, this._features.concat());
            delete this._features;
            delete this._requestCount;
            delete this._numRequests;
        }
    }
};
