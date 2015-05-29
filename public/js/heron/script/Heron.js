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

Ext.namespace("Heron.i18n");
function __(string) {
    var dict = Heron.i18n.dict;
    if (typeof(dict) != 'undefined' && dict[string]) {
        return dict[string];
    }
    return string;
}
Ext.namespace("gxp");
if (!gxp.QueryPanel) {
    gxp.QueryPanel = function () {
    };
} else {
    Ext.namespace("gxp.data");
    gxp.data.WFSProtocolProxy = Ext.extend(GeoExt.data.ProtocolProxy, {setFilter: function (filter) {
        this.protocol.filter = filter;
        this.protocol.options.filter = filter;
    }, constructor: function (config) {
        Ext.applyIf(config, {version: "1.0.0"});
        if (!(this.protocol && this.protocol instanceof OpenLayers.Protocol)) {
            config.protocol = new OpenLayers.Protocol.WFS(Ext.apply({version: config.version, srsName: config.srsName, url: config.url, featureType: config.featureType, featureNS: config.featureNS, geometryName: config.geometryName, schema: config.schema, filter: config.filter, maxFeatures: config.maxFeatures, outputFormat: config.url.indexOf('nationaalgeoregister') > 0 ? 'GML2' : undefined, multi: config.multi}, config.protocol));
        }
        gxp.data.WFSProtocolProxy.superclass.constructor.apply(this, arguments);
    }, doRequest: function (action, records, params, reader, callback, scope, arg) {
        delete params.xaction;
        if (action === Ext.data.Api.actions.read) {
            this.load(params, reader, callback, scope, arg);
        } else {
            if (!(records instanceof Array)) {
                records = [records];
            }
            var features = new Array(records.length), feature;
            Ext.each(records, function (r, i) {
                features[i] = r.getFeature();
                feature = features[i];
                feature.modified = Ext.apply(feature.modified || {}, {attributes: Ext.apply((feature.modified && feature.modified.attributes) || {}, r.modified)});
            }, this);
            var o = {action: action, records: records, callback: callback, scope: scope};
            var options = {callback: function (response) {
                this.onProtocolCommit(response, o);
            }, scope: this};
            Ext.applyIf(options, params);
            this.protocol.commit(features, options);
        }
    }, onProtocolCommit: function (response, o) {
        if (response.success()) {
            var features = response.reqFeatures;
            var state, feature;
            var destroys = [];
            var insertIds = response.insertIds || [];
            var i, len, j = 0;
            for (i = 0, len = features.length; i < len; ++i) {
                feature = features[i];
                state = feature.state;
                if (state) {
                    if (state == OpenLayers.State.DELETE) {
                        destroys.push(feature);
                    } else if (state == OpenLayers.State.INSERT) {
                        feature.fid = insertIds[j];
                        ++j;
                    } else if (feature.modified) {
                        feature.modified = {};
                    }
                    feature.state = null;
                }
            }
            for (i = 0, len = destroys.length; i < len; ++i) {
                feature = destroys[i];
                feature.layer && feature.layer.destroyFeatures([feature]);
            }
            len = features.length;
            var data = new Array(len);
            var f;
            for (i = 0; i < len; ++i) {
                f = features[i];
                data[i] = {id: f.id, feature: f, state: null};
                var fields = o.records[i].fields;
                for (var a in f.attributes) {
                    if (fields.containsKey(a)) {
                        data[i][a] = f.attributes[a];
                    }
                }
            }
            o.callback.call(o.scope, data, response.priv, true);
        } else {
            var request = response.priv;
            if (request.status >= 200 && request.status < 300) {
                this.fireEvent("exception", this, "remote", o.action, o, response.error, o.records);
            } else {
                this.fireEvent("exception", this, "response", o.action, o, request);
            }
            o.callback.call(o.scope, [], request, false);
        }
    }});
}
if (gxp.ColorManager) {
    Ext.override(gxp.ColorManager, {fieldFocus: function (field) {
        if (!gxp.ColorManager.pickerWin) {
            gxp.ColorManager.picker = new Ext.ColorPalette();
            gxp.ColorManager.pickerWin = new Ext.Window({title: "Color Picker", closeAction: "hide", autoWidth: Ext.isIE ? false : true, autoHeight: Ext.isIE ? false : true});
        } else {
            gxp.ColorManager.picker.purgeListeners();
        }
        var listenerCfg = {select: this.setFieldValue, scope: this};
        var value = this.getPickerValue();
        if (value) {
            var colors = [].concat(gxp.ColorManager.picker.colors);
            if (!~colors.indexOf(value)) {
                if (gxp.ColorManager.picker.ownerCt) {
                    gxp.ColorManager.pickerWin.remove(gxp.ColorManager.picker);
                    gxp.ColorManager.picker = new Ext.ColorPalette();
                }
                colors.push(value);
                gxp.ColorManager.picker.colors = colors;
            }
            gxp.ColorManager.pickerWin.add(gxp.ColorManager.picker);
            if (Ext.isIE) {
                gxp.ColorManager.pickerWin.setSize(456 + 10, 248 + 36);
            }
            gxp.ColorManager.pickerWin.doLayout();
            if (gxp.ColorManager.picker.rendered) {
                gxp.ColorManager.picker.select(value);
            } else {
                listenerCfg.afterrender = function () {
                    gxp.ColorManager.picker.select(value);
                };
            }
        }
        gxp.ColorManager.picker.on(listenerCfg);
        gxp.ColorManager.pickerWin.show();
    }});
}
if (!Ext.grid.GridView.prototype.templates) {
    Ext.grid.GridView.prototype.templates = {};
}
Ext.grid.GridView.prototype.templates.cell = new Ext.Template('<td class="x-grid3-col x-grid3-cell x-grid3-td-{id} x-selectable {css}" style="{style}" tabIndex="0" {cellAttr}>', '<div class="x-grid3-cell-inner x-grid3-col-{id}" {attr}>{value}</div>', '</td>');
(function () {
    var createComplete = function (fn, cb) {
        return function (request) {
            if (cb && cb[fn]) {
                cb[fn].call(cb.scope || window, Ext.applyIf({argument: cb.argument}, request));
            }
        };
    };
    Ext.apply(Ext.lib.Ajax, {request: function (method, uri, cb, data, options) {
        options = options || {};
        method = method || options.method;
        var hs = options.headers;
        if (options.xmlData) {
            if (!hs || !hs["Content-Type"]) {
                hs = hs || {};
                hs["Content-Type"] = "text/xml";
            }
            method = method || "POST";
            data = options.xmlData;
        } else if (options.jsonData) {
            if (!hs || !hs["Content-Type"]) {
                hs = hs || {};
                hs["Content-Type"] = "application/json";
            }
            method = method || "POST";
            data = typeof options.jsonData == "object" ? Ext.encode(options.jsonData) : options.jsonData;
        }
        if ((method && method.toLowerCase() == "post") && (options.form || options.params) && (!hs || !hs["Content-Type"])) {
            hs = hs || {};
            hs["Content-Type"] = "application/x-www-form-urlencoded";
        }
        return OpenLayers.Request.issue({success: createComplete("success", cb), failure: createComplete("failure", cb), method: method, headers: hs, data: data, url: uri});
    }, isCallInProgress: function (request) {
        return true;
    }, abort: function (request) {
        request.abort();
    }});
})();
Ext.override(Ext.ColorPalette, {colors: ['FBEFEF', 'FBF2EF', 'FBF5EF', 'FBF8EF', 'FBFBEF', 'F8FBEF', 'F5FBEF', 'F2FBEF', 'EFFBEF', 'EFFBF2', 'EFFBF5', 'EFFBF8', 'EFFBFB', 'EFF8FB', 'EFF5FB', 'EFF2FB', 'EFEFFB', 'F2EFFB', 'F5EFFB', 'F8EFFB', 'FBEFFB', 'FBEFF8', 'FBEFF5', 'FBEFF2', 'FFFFFF', 'F8E0E0', 'F8E6E0', 'F8ECE0', 'F7F2E0', 'F7F8E0', 'F1F8E0', 'ECF8E0', 'E6F8E0', 'E0F8E0', 'E0F8E6', 'E0F8EC', 'E0F8F1', 'E0F8F7', 'E0F2F7', 'E0ECF8', 'E0E6F8', 'E0E0F8', 'E6E0F8', 'ECE0F8', 'F2E0F7', 'F8E0F7', 'F8E0F1', 'F8E0EC', 'F8E0E6', 'FAFAFA', 'F6CECE', 'F6D8CE', 'F6E3CE', 'F5ECCE', 'F5F6CE', 'ECF6CE', 'E3F6CE', 'D8F6CE', 'CEF6CE', 'CEF6D8', 'CEF6E3', 'CEF6EC', 'CEF6F5', 'CEECF5', 'CEE3F6', 'CED8F6', 'CECEF6', 'D8CEF6', 'E3CEF6', 'ECCEF5', 'F6CEF5', 'F6CEEC', 'F6CEE3', 'F6CED8', 'F2F2F2', 'F5A9A9', 'F5BCA9', 'F5D0A9', 'F3E2A9', 'F2F5A9', 'E1F5A9', 'D0F5A9', 'BCF5A9', 'A9F5A9', 'A9F5BC', 'A9F5D0', 'A9F5E1', 'A9F5F2', 'A9E2F3', 'A9D0F5', 'A9BCF5', 'A9A9F5', 'BCA9F5', 'D0A9F5', 'E2A9F3', 'F5A9F2', 'F5A9E1', 'F5A9D0', 'F5A9BC', 'E6E6E6', 'F78181', 'F79F81', 'F7BE81', 'F5DA81', 'F3F781', 'D8F781', 'BEF781', '9FF781', '81F781', '81F79F', '81F7BE', '81F7D8', '81F7F3', '81DAF5', '81BEF7', '819FF7', '8181F7', '9F81F7', 'BE81F7', 'DA81F5', 'F781F3', 'F781D8', 'F781BE', 'F7819F', 'D8D8D8', 'FA5858', 'FA8258', 'FAAC58', 'F7D358', 'F4FA58', 'D0FA58', 'ACFA58', '82FA58', '58FA58', '58FA82', '58FAAC', '58FAD0', '58FAF4', '58D3F7', '58ACFA', '5882FA', '5858FA', '8258FA', 'AC58FA', 'D358F7', 'FA58F4', 'FA58D0', 'FA58AC', 'FA5882', 'BDBDBD', 'FE2E2E', 'FE642E', 'FE9A2E', 'FACC2E', 'F7FE2E', 'C8FE2E', '9AFE2E', '64FE2E', '2EFE2E', '2EFE64', '2EFE9A', '2EFEC8', '2EFEF7', '2ECCFA', '2E9AFE', '2E64FE', '2E2EFE', '642EFE', '9A2EFE', 'CC2EFA', 'FE2EF7', 'FE2EC8', 'FE2E9A', 'FE2E64', 'A4A4A4', 'FF0000', 'FF4000', 'FF8000', 'FFBF00', 'FFFF00', 'BFFF00', '80FF00', '40FF00', '00FF00', '00FF40', '00FF80', '00FFBF', '00FFFF', '00BFFF', '0080FF', '0040FF', '0000FF', '4000FF', '8000FF', 'BF00FF', 'FF00FF', 'FF00BF', 'FF0080', 'FF0040', '848484', 'DF0101', 'DF3A01', 'DF7401', 'DBA901', 'D7DF01', 'A5DF00', '74DF00', '3ADF00', '01DF01', '01DF3A', '01DF74', '01DFA5', '01DFD7', '01A9DB', '0174DF', '013ADF', '0101DF', '3A01DF', '7401DF', 'A901DB', 'DF01D7', 'DF01A5', 'DF0174', 'DF013A', '6E6E6E', 'B40404', 'B43104', 'B45F04', 'B18904', 'AEB404', '86B404', '5FB404', '31B404', '04B404', '04B431', '04B45F', '04B486', '04B4AE', '0489B1', '045FB4', '0431B4', '0404B4', '3104B4', '5F04B4', '8904B1', 'B404AE', 'B40486', 'B4045F', 'B40431', '585858', '8A0808', '8A2908', '8A4B08', '886A08', '868A08', '688A08', '4B8A08', '298A08', '088A08', '088A29', '088A4B', '088A68', '088A85', '086A87', '084B8A', '08298A', '08088A', '29088A', '4B088A', '6A0888', '8A0886', '8A0868', '8A084B', '8A0829', '424242', '610B0B', '61210B', '61380B', '5F4C0B', '5E610B', '4B610B', '38610B', '21610B', '0B610B', '0B6121', '0B6138', '0B614B', '0B615E', '0B4C5F', '0B3861', '0B2161', '0B0B61', '210B61', '380B61', '4C0B5F', '610B5E', '610B4B', '610B38', '610B21', '2E2E2E', '190707', '190B07', '191007', '181407', '181907', '141907', '101907', '0B1907', '071907', '07190B', '071910', '071914', '071918', '071418', '071019', '070B19', '070719', '0B0719', '100719', '140718', '190718', '190714', '190710', '19070B', '000000']});
OpenLayers.Util.extend(OpenLayers.Format.WFST.v1.prototype.namespaces, {gml32: 'http://www.opengis.net/gml/3.2'});
OpenLayers.Format.Atom.prototype.parseLocations = function (node) {
    var georssns = this.namespaces.georss;
    var locations = {components: []};
    var where = this.getElementsByTagNameNS(node, georssns, "where");
    if (where && where.length > 0) {
        if (!this.gmlParser) {
            this.initGmlParser();
        }
        for (var i = 0, ii = where.length; i < ii; i++) {
            this.gmlParser.readChildNodes(where[i], locations);
        }
    }
    var components = locations.components;
    var point = this.getElementsByTagNameNS(node, georssns, "point");
    if (point && point.length > 0) {
        for (var i = 0, ii = point.length; i < ii; i++) {
            var xy = OpenLayers.String.trim(point[i].firstChild.nodeValue).split(/\s+/);
            if (xy.length != 2) {
                xy = OpenLayers.String.trim(point[i].firstChild.nodeValue).split(/\s*,\s*/);
            }
            components.push(new OpenLayers.Geometry.Point(xy[1], xy[0]));
        }
    }
    var line = this.getElementsByTagNameNS(node, georssns, "line");
    if (line && line.length > 0) {
        var coords;
        var p;
        var points;
        for (var i = 0, ii = line.length; i < ii; i++) {
            coords = OpenLayers.String.trim(line[i].firstChild.nodeValue).split(/\s+/);
            points = [];
            for (var j = 0, jj = coords.length; j < jj; j += 2) {
                p = new OpenLayers.Geometry.Point(coords[j + 1], coords[j]);
                points.push(p);
            }
            components.push(new OpenLayers.Geometry.LineString(points));
        }
    }
    var polygon = this.getElementsByTagNameNS(node, georssns, "polygon");
    if (polygon && polygon.length > 0) {
        var coords;
        var p;
        var points;
        for (var i = 0, ii = polygon.length; i < ii; i++) {
            coords = OpenLayers.String.trim(polygon[i].firstChild.nodeValue).split(/\s+/);
            points = [];
            for (var j = 0, jj = coords.length; j < jj; j += 2) {
                p = new OpenLayers.Geometry.Point(coords[j + 1], coords[j]);
                points.push(p);
            }
            components.push(new OpenLayers.Geometry.Polygon([new OpenLayers.Geometry.LinearRing(points)]));
        }
    }
    if (this.internalProjection && this.externalProjection) {
        for (var i = 0, ii = components.length; i < ii; i++) {
            if (components[i]) {
                components[i].transform(this.externalProjection, this.internalProjection);
            }
        }
    }
    return components;
};
OpenLayers.Util.modifyDOMElement = function (element, id, px, sz, position, border, overflow, opacity) {
    if (id) {
        element.id = id;
    }
    if (px) {
        if (!px.x) {
            px.x = 0;
        }
        if (!px.y) {
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
OpenLayers.Layer.Vector.prototype.setOpacity = function (opacity) {
    if (opacity != this.opacity) {
        this.opacity = opacity;
        var element = this.renderer.root;
        OpenLayers.Util.modifyDOMElement(element, null, null, null, null, null, null, opacity);
        if (this.map != null) {
            this.map.events.triggerEvent("changelayer", {layer: this, property: "opacity"});
        }
    }
};
OpenLayers.Feature.Vector.prototype.clone = function () {
    var clone = new OpenLayers.Feature.Vector(this.geometry ? this.geometry.clone() : null, this.attributes, this.style);
    clone.renderIntent = this.renderIntent;
    return clone;
};
OpenLayers.Control.SelectFeature.prototype.highlight = function (feature) {
    var layer = feature.layer;
    var cont = this.events.triggerEvent("beforefeaturehighlighted", {feature: feature});
    if (cont !== false) {
        feature._prevHighlighter = feature._lastHighlighter;
        feature._lastHighlighter = this.id;
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
        this.events.triggerEvent("featurehighlighted", {feature: feature});
    }
};
OpenLayers.Control.WMSGetFeatureInfo.prototype.buildWMSOptions = function (url, layers, clickPosition, format) {
    var layerNames = [], styleNames = [];
    for (var i = 0, len = layers.length; i < len; i++) {
        if (layers[i].params.LAYERS != null) {
            layerNames = layerNames.concat(layers[i].params.LAYERS);
            styleNames = styleNames.concat(this.getStyleNames(layers[i]));
        }
    }
    var firstLayer = layers[0];
    var projection = this.map.getProjection();
    var layerProj = firstLayer.projection;
    if (layerProj && layerProj.equals(this.map.getProjectionObject())) {
        projection = layerProj.getCode();
    }
    var params = OpenLayers.Util.extend({service: "WMS", version: firstLayer.params.VERSION, request: "GetFeatureInfo", exceptions: firstLayer.params.EXCEPTIONS, bbox: this.map.getExtent().toBBOX(null, firstLayer.reverseAxisOrder()), feature_count: this.maxFeatures, height: this.map.getSize().h, width: this.map.getSize().w, format: format, info_format: firstLayer.params.INFO_FORMAT || this.infoFormat}, (parseFloat(firstLayer.params.VERSION) >= 1.3) ? {crs: projection, i: parseInt(clickPosition.x), j: parseInt(clickPosition.y)} : {srs: projection, x: parseInt(clickPosition.x), y: parseInt(clickPosition.y)});
    if (layerNames.length != 0) {
        params = OpenLayers.Util.extend({layers: layerNames, query_layers: layerNames, styles: styleNames}, params);
    }
    OpenLayers.Util.applyDefaults(params, firstLayer.params.vendorParams);
    OpenLayers.Util.applyDefaults(params, this.vendorParams);
    return{url: url, params: OpenLayers.Util.upperCaseObject(params), callback: function (request) {
        this.handleResponse(clickPosition, request, url);
    }, scope: this};
};
OpenLayers.Control.WMSGetFeatureInfo.prototype.request = function (clickPosition, options) {
    var layers = this.findLayers();
    if (layers.length == 0) {
        this.events.triggerEvent("nogetfeatureinfo");
        OpenLayers.Element.removeClass(this.map.viewPortDiv, "olCursorWait");
        return;
    }
    options = options || {};
    if (this.drillDown === false) {
        var wmsOptions = this.buildWMSOptions(this.url, layers, clickPosition, layers[0].params.FORMAT);
        var request = OpenLayers.Request.GET(wmsOptions);
        if (options.hover === true) {
            this.hoverRequest = request;
        }
    } else {
        this._requestCount = 0;
        this._numRequests = 0;
        this.features = [];
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
            if (this.requestPerLayer) {
                for (var l = 0, len = layers.length; l < len; l++) {
                    var wmsOptions = this.buildWMSOptions(url, [layers[l]], clickPosition, layers[0].params.FORMAT);
                    var req = OpenLayers.Request.GET(wmsOptions);
                    req.layer = layers[l];
                }
                this._numRequests += layers.length - 1;
            } else {
                var wmsOptions = this.buildWMSOptions(url, layers, clickPosition, layers[0].params.FORMAT);
                OpenLayers.Request.GET(wmsOptions);
            }
        }
    }
};
OpenLayers.Control.WMSGetFeatureInfo.prototype.handleResponse = function (xy, request, url) {
    var doc = request.responseXML;
    if (!doc || !doc.documentElement) {
        doc = request.responseText;
    }
    var features = this.format.read(doc);
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
            this._features = (this._features || []).concat({url: url, features: features});
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
Ext.override(GeoExt.WMSLegend, {getLegendUrl: function (layerName, layerNames) {
    var rec = this.layerRecord;
    var url;
    var styles = rec && rec.get("styles");
    var layer = rec.getLayer();
    layerNames = layerNames || [layer.params.LAYERS].join(",").split(",");
    var styleNames = layer.params.STYLES && [layer.params.STYLES].join(",").split(",");
    var idx = layerNames.indexOf(layerName);
    var styleName = styleNames && styleNames[idx];
    if (styles && styles.length > 0) {
        if (styleName) {
            Ext.each(styles, function (s) {
                url = (s.name == styleName && s.legend) && s.legend.href;
                return!url;
            });
        } else if (this.defaultStyleIsFirst === true && !styleNames && !layer.params.SLD && !layer.params.SLD_BODY) {
            url = styles[0].legend && styles[0].legend.href;
        }
    }
    if (!url) {
        url = layer.getFullRequestString({REQUEST: "GetLegendGraphic", WIDTH: null, HEIGHT: null, EXCEPTIONS: "application/vnd.ogc.se_xml", LAYER: layerName, LAYERS: null, STYLE: (styleName !== '') ? styleName : null, STYLES: null, SRS: null, FORMAT: null, TIME: null});
    }
    var params = Ext.apply({}, this.baseParams);
    if (layer.params._OLSALT) {
        params._OLSALT = layer.params._OLSALT;
    }
    url = Ext.urlAppend(url, Ext.urlEncode(params));
    if (url.toLowerCase().indexOf("request=getlegendgraphic") != -1) {
        if (url.toLowerCase().indexOf("format=") == -1) {
            url = Ext.urlAppend(url, "FORMAT=image%2Fgif");
        }
        if (this.useScaleParameter === true) {
            var scale = layer.map.getScale();
            url = Ext.urlAppend(url, "SCALE=" + scale);
        }
    }
    return url;
}});
Ext.override(GeoExt.tree.LayerNodeUI, {enforceOneVisible: function () {
    var attributes = this.node.attributes;
    var group = attributes.checkedGroup;
    if (group && group !== "gx_baselayer") {
        var layer = this.node.layer;
        if (attributes.checked) {
            var checkedNodes = this.node.getOwnerTree().getChecked();
            var checkedCount = 0;
            Ext.each(checkedNodes, function (n) {
                var l = n.layer;
                if (!n.hidden && n.attributes.checkedGroup === group) {
                    checkedCount++;
                    if (l != layer && attributes.checked) {
                        l.setVisibility(false);
                    }
                }
            });
            if (checkedCount === 0 && attributes.checked == false) {
                layer.setVisibility(true);
            }
        }
    }
}});
Ext.override(GeoExt.tree.LayerNode, {renderX: function (bulkRender) {
    var layer = this.layer instanceof OpenLayers.Layer && this.layer;
    if (!layer) {
        if (!this.layerStore || this.layerStore == "auto") {
            this.layerStore = GeoExt.MapPanel.guess().layers;
        }
        var i = this.layerStore.findBy(function (o) {
            return o.get("title") == this.layer;
        }, this);
        if (i != -1) {
            layer = this.layerStore.getAt(i).getLayer();
        }
    }
    if (!this.rendered || !layer) {
        var ui = this.getUI();
        if (layer) {
            this.layer = layer;
            if (layer.isBaseLayer) {
                this.draggable = false;
                this.disabled = true;
            }
            this.autoDisable = !(this.autoDisable === false || this.layer.isBaseLayer || this.layer.alwaysInRange);
            if (!this.text) {
                this.text = layer.name;
            }
            ui.show();
            this.addVisibilityEventHandlers();
        } else {
            ui.hide();
        }
        if (this.layerStore instanceof GeoExt.data.LayerStore) {
            this.addStoreEventHandlers(layer);
        }
    }
    GeoExt.tree.LayerNode.superclass.render.apply(this, arguments);
}});
Ext.override(GeoExt.form.SearchAction, {run: function () {
    var o = this.options;
    var f = GeoExt.form.toFilter(this.form, o);
    if (o.clientValidation === false || this.form.isValid()) {
        if (o.abortPrevious && this.form.prevResponse) {
            o.protocol.abort(this.form.prevResponse);
        }
        this.form.prevResponse = o.protocol.read(Ext.applyIf({filter: f, callback: this.handleResponse, scope: this}, o));
    } else if (o.clientValidation !== false) {
        this.failureType = Ext.form.Action.CLIENT_INVALID;
        this.form.afterAction(this, false);
    }
}});
GeoExt.form.toFilter = function (form, options) {
    var wildcard = options.wildcard;
    var logicalOp = options.logicalOp;
    var matchCase = options.matchCase;
    if (form instanceof Ext.form.FormPanel) {
        form = form.getForm();
    }
    var filters = [], values = form.getValues(false);
    for (var prop in values) {
        var s = prop.split("__");
        var value = values[prop], type;
        if (s.length > 1 && (type = GeoExt.form.toFilter.FILTER_MAP[s[1]]) !== undefined) {
            prop = s[0];
        } else {
            type = OpenLayers.Filter.Comparison.EQUAL_TO;
        }
        if (type === OpenLayers.Filter.Comparison.LIKE) {
            if (wildcard && (!value || value.length == 0)) {
                continue;
            }
            switch (wildcard) {
                case GeoExt.form.ENDS_WITH:
                    value = '.*' + value;
                    break;
                case GeoExt.form.STARTS_WITH:
                    value += '.*';
                    break;
                case GeoExt.form.CONTAINS:
                    value = '.*' + value + '.*';
                    break;
                default:
                    break;
            }
        }
        filters.push(new OpenLayers.Filter.Comparison({type: type, value: value, property: prop, matchCase: matchCase}));
    }
    return filters.length == 1 && logicalOp != OpenLayers.Filter.Logical.NOT ? filters[0] : new OpenLayers.Filter.Logical({type: logicalOp || OpenLayers.Filter.Logical.AND, filters: filters});
};
GeoExt.form.toFilter.FILTER_MAP = {"eq": OpenLayers.Filter.Comparison.EQUAL_TO, "ne": OpenLayers.Filter.Comparison.NOT_EQUAL_TO, "lt": OpenLayers.Filter.Comparison.LESS_THAN, "le": OpenLayers.Filter.Comparison.LESS_THAN_OR_EQUAL_TO, "gt": OpenLayers.Filter.Comparison.GREATER_THAN, "ge": OpenLayers.Filter.Comparison.GREATER_THAN_OR_EQUAL_TO, "like": OpenLayers.Filter.Comparison.LIKE};
GeoExt.form.ENDS_WITH = 1;
GeoExt.form.STARTS_WITH = 2;
GeoExt.form.CONTAINS = 3;
Ext.override(GeoExt.PrintMapPanel, {initComponent: function () {
    if (this.sourceMap instanceof GeoExt.MapPanel) {
        this.sourceMap = this.sourceMap.map;
    }
    if (!this.map) {
        this.map = {};
    }
    Ext.applyIf(this.map, {numZoomLevels: this.sourceMap.getNumZoomLevels(), projection: this.sourceMap.getProjection(), maxExtent: this.sourceMap.getMaxExtent(), maxResolution: this.sourceMap.getMaxResolution(), resolutions: this.sourceMap.resolutions ? this.sourceMap.resolutions.slice(0) : this.map.resolutions, units: this.sourceMap.getUnits()});
    if (!(this.printProvider instanceof GeoExt.data.PrintProvider)) {
        this.printProvider = new GeoExt.data.PrintProvider(this.printProvider);
    }
    this.printPage = new GeoExt.data.PrintPage({printProvider: this.printProvider});
    this.previewScales = new Ext.data.Store();
    this.previewScales.add(this.printProvider.scales.getRange());
    this.layers = [];
    var layer, clonedLayer;
    Ext.each(this.sourceMap.layers, function (layer) {
        if (layer.getVisibility() === true) {
            if (layer.protocol) {
                layer.protocol.autoDestroy = false;
            }
            clonedLayer = layer.clone();
            if (layer.styleMap && layer.styleMap.styles) {
                clonedLayer.styleMap = new OpenLayers.StyleMap(layer.styleMap.styles);
            }
            this.layers.push(clonedLayer);
        }
    }, this);
    this.extent = this.sourceMap.getExtent();
    GeoExt.PrintMapPanel.superclass.initComponent.call(this);
}});
Ext.namespace("GeoExt.data");
GeoExt.data.PrintProvider = Ext.extend(Ext.util.Observable, {url: null, capabilities: null, method: "POST", encoding: document.charset || document.characterSet || "UTF-8", timeout: 30000, customParams: null, scales: null, dpis: null, outputFormats: null, outputFormatsEnabled: false, layouts: null, dpi: null, layout: null, outputFormat: null, defaultOutputFormatName: 'pdf', constructor: function (config) {
    this.initialConfig = config;
    Ext.apply(this, config);
    if (!this.customParams) {
        this.customParams = {};
    }
    this.addEvents("loadcapabilities", "layoutchange", "dpichange", "outputformatchange", "beforeprint", "print", "printexception", "beforeencodelayer", "encodelayer", "beforedownload", "beforeencodelegend");
    GeoExt.data.PrintProvider.superclass.constructor.apply(this, arguments);
    this.scales = new Ext.data.JsonStore({root: "scales", sortInfo: {field: "value", direction: "DESC"}, fields: ["name", {name: "value", type: "float"}]});
    this.dpis = new Ext.data.JsonStore({root: "dpis", fields: ["name", {name: "value", type: "float"}]});
    if (this.outputFormatsEnabled === true) {
        this.outputFormats = new Ext.data.JsonStore({root: "outputFormats", sortInfo: {field: "name", direction: "ASC"}, fields: ["name"]});
    }
    this.layouts = new Ext.data.JsonStore({root: "layouts", fields: ["name", {name: "size", mapping: "map"}, {name: "rotation", type: "boolean"}]});
    if (config.capabilities) {
        this.loadStores();
    } else {
        if (this.url.split("/").pop()) {
            this.url += "/";
        }
        this.initialConfig.autoLoad && this.loadCapabilities();
    }
}, setLayout: function (layout) {
    this.layout = layout;
    this.fireEvent("layoutchange", this, layout);
}, setDpi: function (dpi) {
    this.dpi = dpi;
    this.fireEvent("dpichange", this, dpi);
}, setOutputFormat: function (outputFormat) {
    this.outputFormat = outputFormat;
    this.fireEvent("outputformatchange", this, outputFormat);
}, print: function (map, pages, options) {
    if (map instanceof GeoExt.MapPanel) {
        map = map.map;
    }
    pages = pages instanceof Array ? pages : [pages];
    options = options || {};
    if (this.fireEvent("beforeprint", this, map, pages, options) === false) {
        return;
    }
    var jsonData = Ext.apply({units: map.getUnits(), srs: map.baseLayer.projection.getCode(), layout: this.layout.get("name"), dpi: this.dpi.get("value"), outputFormat: this.outputFormat ? this.outputFormat.get("name") : this.defaultOutputFormatName}, this.customParams);
    var pagesLayer = pages[0].feature.layer;
    var encodedLayers = [];
    var layers = map.layers.concat();
    layers.remove(map.baseLayer);
    layers.unshift(map.baseLayer);
    Ext.each(layers, function (layer) {
        if (layer !== pagesLayer && layer.getVisibility() === true) {
            var enc = this.encodeLayer(layer);
            enc && encodedLayers.push(enc);
        }
    }, this);
    jsonData.layers = encodedLayers;
    var encodedPages = [];
    Ext.each(pages, function (page) {
        encodedPages.push(Ext.apply({center: [page.center.lon, page.center.lat], scale: page.scale.get("value"), rotation: page.rotation}, page.customParams));
    }, this);
    jsonData.pages = encodedPages;
    if (options.overview) {
        var encodedOverviewLayers = [];
        Ext.each(options.overview.layers, function (layer) {
            var enc = this.encodeLayer(layer);
            enc && encodedOverviewLayers.push(enc);
        }, this);
        jsonData.overviewLayers = encodedOverviewLayers;
    }
    if (options.legend && !(this.fireEvent("beforeencodelegend", this, jsonData, options.legend) === false)) {
        var legend = options.legend;
        var rendered = legend.rendered;
        if (!rendered) {
            legend = legend.cloneConfig({renderTo: document.body, hidden: true});
        }
        var encodedLegends = [];
        legend.items && legend.items.each(function (cmp) {
            if (!cmp.hidden) {
                var encFn = this.encoders.legends[cmp.getXType()];
                encodedLegends = encodedLegends.concat(encFn.call(this, cmp, jsonData.pages[0].scale));
            }
        }, this);
        if (!rendered) {
            legend.destroy();
        }
        jsonData.legends = encodedLegends;
    }
    if (this.method === "GET") {
        var url = Ext.urlAppend(this.capabilities.printURL, "spec=" + encodeURIComponent(Ext.encode(jsonData)));
        this.download(url);
    } else {
        Ext.Ajax.request({url: this.capabilities.createURL, timeout: this.timeout, jsonData: jsonData, headers: {"Content-Type": "application/json; charset=" + this.encoding}, success: function (response) {
            var url = Ext.decode(response.responseText).getURL;
            this.download(url);
        }, failure: function (response) {
            this.fireEvent("printexception", this, response);
        }, params: this.initialConfig.baseParams, scope: this});
    }
}, download: function (url) {
    if (this.fireEvent("beforedownload", this, url) !== false) {
        if (Ext.isOpera) {
            window.open(url);
        } else {
            window.location.href = url;
        }
    }
    this.fireEvent("print", this, url);
}, loadCapabilities: function () {
    if (!this.url) {
        return;
    }
    var url = this.url + "info.json";
    Ext.Ajax.request({url: url, method: "GET", disableCaching: false, success: function (response) {
        this.capabilities = Ext.decode(response.responseText);
        this.loadStores();
    }, params: this.initialConfig.baseParams, scope: this});
}, loadStores: function () {
    this.scales.loadData(this.capabilities);
    this.dpis.loadData(this.capabilities);
    this.layouts.loadData(this.capabilities);
    this.setLayout(this.layouts.getAt(0));
    this.setDpi(this.dpis.getAt(0));
    if (this.outputFormatsEnabled && this.capabilities.outputFormats) {
        this.outputFormats.loadData(this.capabilities);
        var defaultOutputIndex = this.outputFormats.find('name', this.defaultOutputFormatName);
        this.setOutputFormat(defaultOutputIndex > -1 ? this.outputFormats.getAt(defaultOutputIndex) : this.outputFormats.getAt(0));
    }
    this.fireEvent("loadcapabilities", this, this.capabilities);
}, encodeLayer: function (layer) {
    var encLayer;
    for (var c in this.encoders.layers) {
        if (OpenLayers.Layer[c] && layer instanceof OpenLayers.Layer[c]) {
            if (this.fireEvent("beforeencodelayer", this, layer) === false) {
                return;
            }
            encLayer = this.encoders.layers[c].call(this, layer);
            this.fireEvent("encodelayer", this, layer, encLayer);
            break;
        }
    }
    return(encLayer && encLayer.type) ? encLayer : null;
}, getAbsoluteUrl: function (url) {
    if (Ext.isSafari) {
        url = url.replace(/{/g, '%7B');
        url = url.replace(/}/g, '%7D');
    }
    var a;
    if (Ext.isIE6 || Ext.isIE7 || Ext.isIE8) {
        a = document.createElement("<a href='" + url + "'/>");
        a.style.display = "none";
        document.body.appendChild(a);
        a.href = a.href;
        document.body.removeChild(a);
    } else {
        a = document.createElement("a");
        a.href = url;
    }
    return a.href;
}, encoders: {"layers": {"Layer": function (layer) {
    var enc = {};
    if (layer.options && layer.options.maxScale) {
        enc.minScaleDenominator = layer.options.maxScale;
    }
    if (layer.options && layer.options.minScale) {
        enc.maxScaleDenominator = layer.options.minScale;
    }
    return enc;
}, "WMS": function (layer) {
    var enc = this.encoders.layers.HTTPRequest.call(this, layer);
    enc.singleTile = layer.singleTile;
    Ext.apply(enc, {type: 'WMS', layers: [layer.params.LAYERS].join(",").split(","), format: layer.params.FORMAT, styles: [layer.params.STYLES].join(",").split(","), singleTile: layer.singleTile});
    var param;
    for (var p in layer.params) {
        param = p.toLowerCase();
        if (layer.params[p] != null && !layer.DEFAULT_PARAMS[param] && "layers,styles,width,height,srs".indexOf(param) == -1) {
            if (!enc.customParams) {
                enc.customParams = {};
            }
            enc.customParams[p] = layer.params[p];
        }
    }
    return enc;
}, "OSM": function (layer) {
    var enc = this.encoders.layers.TileCache.call(this, layer);
    return Ext.apply(enc, {type: 'OSM', baseURL: enc.baseURL.substr(0, enc.baseURL.indexOf("$")), extension: "png"});
}, "XYZ": function (layer) {
    var enc = this.encoders.layers.TileCache.call(this, layer);
    return Ext.apply(enc, {type: 'XYZ', baseURL: enc.baseURL.substr(0, enc.baseURL.indexOf("$")), extension: enc.baseURL.substr(enc.baseURL.lastIndexOf("$")).split(".").pop(), tileOriginCorner: layer.tileOriginCorner});
}, "TMS": function (layer) {
    var enc = this.encoders.layers.TileCache.call(this, layer);
    return Ext.apply(enc, {type: 'TMS', format: layer.type});
}, "TileCache": function (layer) {
    var enc = this.encoders.layers.HTTPRequest.call(this, layer);
    var maxExtent = layer.maxExtent.toArray();
    var tileOriginX = layer.tileOrigin ? layer.tileOrigin.lon : maxExtent[0];
    var tileOriginY = layer.tileOrigin ? layer.tileOrigin.lat : maxExtent[1];
    return Ext.apply(enc, {type: 'TileCache', layer: layer.layername, maxExtent: maxExtent, tileOrigin: {x: tileOriginX, y: tileOriginY}, tileSize: [layer.tileSize.w, layer.tileSize.h], extension: layer.extension, resolutions: layer.serverResolutions || layer.resolutions});
}, "WMTS": function (layer) {
    var enc = this.encoders.layers.HTTPRequest.call(this, layer);
    enc = Ext.apply(enc, {type: 'WMTS', layer: layer.layer, version: layer.version, requestEncoding: layer.requestEncoding, style: layer.style, dimensions: layer.dimensions, params: layer.params, matrixSet: layer.matrixSet});
    if (layer.matrixIds) {
        if (layer.requestEncoding == "KVP") {
            enc.format = layer.format;
        }
        enc.matrixIds = []
        Ext.each(layer.matrixIds, function (matrixId) {
            enc.matrixIds.push({identifier: matrixId.identifier, matrixSize: [matrixId.matrixWidth, matrixId.matrixHeight], resolution: matrixId.scaleDenominator * 0.28E-3 / OpenLayers.METERS_PER_INCH / OpenLayers.INCHES_PER_UNIT[layer.units], tileSize: [matrixId.tileWidth, matrixId.tileHeight], topLeftCorner: [matrixId.topLeftCorner.lon, matrixId.topLeftCorner.lat]});
        })
        return enc;
    }
    else {
        return Ext.apply(enc, {formatSuffix: layer.formatSuffix, tileOrigin: [layer.tileOrigin.lon, layer.tileOrigin.lat], tileSize: [layer.tileSize.w, layer.tileSize.h], maxExtent: (layer.tileFullExtent != null) ? layer.tileFullExtent.toArray() : layer.maxExtent.toArray(), zoomOffset: layer.zoomOffset, resolutions: layer.serverResolutions || layer.resolutions});
    }
}, "KaMapCache": function (layer) {
    var enc = this.encoders.layers.KaMap.call(this, layer);
    return Ext.apply(enc, {type: 'KaMapCache', group: layer.params['g'], metaTileWidth: layer.params['metaTileSize']['w'], metaTileHeight: layer.params['metaTileSize']['h']});
}, "KaMap": function (layer) {
    var enc = this.encoders.layers.HTTPRequest.call(this, layer);
    return Ext.apply(enc, {type: 'KaMap', map: layer.params['map'], extension: layer.params['i'], group: layer.params['g'] || "", maxExtent: layer.maxExtent.toArray(), tileSize: [layer.tileSize.w, layer.tileSize.h], resolutions: layer.serverResolutions || layer.resolutions});
}, "HTTPRequest": function (layer) {
    var enc = this.encoders.layers.Layer.call(this, layer);
    return Ext.apply(enc, {baseURL: this.getAbsoluteUrl(layer.url instanceof Array ? layer.url[0] : layer.url), opacity: (layer.opacity != null) ? layer.opacity : 1.0});
}, "Image": function (layer) {
    var enc = this.encoders.layers.Layer.call(this, layer);
    return Ext.apply(enc, {type: 'Image', baseURL: this.getAbsoluteUrl(layer.getURL(layer.extent)), opacity: (layer.opacity != null) ? layer.opacity : 1.0, extent: layer.extent.toArray(), pixelSize: [layer.size.w, layer.size.h], name: layer.name});
}, "Vector": function (layer) {
    if (!layer.features.length) {
        return;
    }
    var encFeatures = [];
    var encStyles = {};
    var features = layer.features;
    var featureFormat = new OpenLayers.Format.GeoJSON();
    var styleFormat = new OpenLayers.Format.JSON();
    var nextId = 1;
    var styleDict = {};
    var feature, style, dictKey, dictItem, styleName;
    for (var i = 0, len = features.length; i < len; ++i) {
        feature = features[i];
        style = feature.style || layer.style || layer.styleMap.createSymbolizer(feature, feature.renderIntent);
        if (style.display == 'none') {
            continue;
        }
        if (style.strokeDashstyle) {
            if (style.strokeDashstyle == '4 4') {
                style.strokeDashstyle = 'dash';
            } else if (style.strokeDashstyle == '2 4') {
                style.strokeDashstyle = 'dot';
            }
        }
        dictKey = styleFormat.write(style);
        dictItem = styleDict[dictKey];
        if (dictItem) {
            styleName = dictItem;
        } else {
            styleDict[dictKey] = styleName = nextId++;
            if (style.externalGraphic) {
                encStyles[styleName] = Ext.applyIf({externalGraphic: this.getAbsoluteUrl(style.externalGraphic)}, style);
            } else {
                encStyles[styleName] = style;
            }
        }
        var featureGeoJson = featureFormat.extract.feature.call(featureFormat, feature);
        featureGeoJson.properties = OpenLayers.Util.extend({_gx_style: styleName}, featureGeoJson.properties);
        encFeatures.push(featureGeoJson);
    }
    var enc = this.encoders.layers.Layer.call(this, layer);
    return Ext.apply(enc, {type: 'Vector', styles: encStyles, styleProperty: '_gx_style', geoJson: {type: "FeatureCollection", features: encFeatures}, name: layer.name, opacity: (layer.opacity != null) ? layer.opacity : 1.0});
}, "Markers": function (layer) {
    var features = [];
    for (var i = 0, len = layer.markers.length; i < len; i++) {
        var marker = layer.markers[i];
        var geometry = new OpenLayers.Geometry.Point(marker.lonlat.lon, marker.lonlat.lat);
        var style = {externalGraphic: marker.icon.url, graphicWidth: marker.icon.size.w, graphicHeight: marker.icon.size.h, graphicXOffset: marker.icon.offset.x, graphicYOffset: marker.icon.offset.y};
        var feature = new OpenLayers.Feature.Vector(geometry, {}, style);
        features.push(feature);
    }
    var vector = new OpenLayers.Layer.Vector(layer.name);
    vector.addFeatures(features);
    var output = this.encoders.layers.Vector.call(this, vector);
    vector.destroy();
    return output;
}}, "legends": {"gx_wmslegend": function (legend, scale) {
    var enc = this.encoders.legends.base.call(this, legend);
    var icons = [];
    for (var i = 1, len = legend.items.getCount(); i < len; ++i) {
        var url = legend.items.get(i).url;
        if (legend.useScaleParameter === true && url.toLowerCase().indexOf('request=getlegendgraphic') != -1) {
            var split = url.split("?");
            var params = Ext.urlDecode(split[1]);
            params['SCALE'] = scale;
            url = split[0] + "?" + Ext.urlEncode(params);
        }
        icons.push(this.getAbsoluteUrl(url));
    }
    enc[0].classes[0] = {name: "", icons: icons};
    return enc;
}, "gx_wmtslegend": function (legend, scale) {
    return this.encoders.legends.gx_urllegend.call(this, legend);
}, "gx_urllegend": function (legend) {
    var enc = this.encoders.legends.base.call(this, legend);
    enc[0].classes.push({name: "", icon: this.getAbsoluteUrl(legend.items.get(1).url)});
    return enc;
}, "base": function (legend) {
    return[
        {name: legend.getLabel(), classes: []}
    ];
}}}});
Ext.namespace("GeoExt.plugins");
GeoExt.plugins.PrintProviderField = Ext.extend(Ext.util.Observable, {target: null, constructor: function (config) {
    this.initialConfig = config;
    Ext.apply(this, config);
    GeoExt.plugins.PrintProviderField.superclass.constructor.apply(this, arguments);
}, init: function (target) {
    this.target = target;
    var onCfg = {scope: this, "render": this.onRender, "beforedestroy": this.onBeforeDestroy};
    onCfg[target instanceof Ext.form.ComboBox ? "select" : "valid"] = this.onFieldChange;
    target.on(onCfg);
}, onRender: function (field) {
    var printProvider = this.printProvider || field.ownerCt.printProvider;
    if (field.store === printProvider.layouts) {
        field.setValue(printProvider.layout.get(field.displayField));
        printProvider.on({"layoutchange": this.onProviderChange, scope: this});
    } else if (field.store === printProvider.dpis) {
        field.setValue(printProvider.dpi.get(field.displayField));
        printProvider.on({"dpichange": this.onProviderChange, scope: this});
    } else if (field.store === printProvider.outputFormats) {
        if (printProvider.outputFormat) {
            field.setValue(printProvider.outputFormat.get(field.displayField));
            printProvider.on({"outputformatchange": this.onProviderChange, scope: this});
        } else {
            field.setValue(printProvider.defaultOutputFormatName);
            field.disable();
        }
    } else if (field.initialConfig.value === undefined) {
        field.setValue(printProvider.customParams[field.name]);
    }
}, onFieldChange: function (field, record) {
    var printProvider = this.printProvider || field.ownerCt.printProvider;
    var value = field.getValue();
    this._updating = true;
    if (record) {
        switch (field.store) {
            case printProvider.layouts:
                printProvider.setLayout(record);
                break;
            case printProvider.dpis:
                printProvider.setDpi(record);
                break;
            case printProvider.outputFormats:
                printProvider.setOutputFormat(record);
        }
    } else {
        printProvider.customParams[field.name] = value;
    }
    delete this._updating;
}, onProviderChange: function (printProvider, rec) {
    if (!this._updating) {
        this.target.setValue(rec.get(this.target.displayField));
    }
}, onBeforeDestroy: function () {
    var target = this.target;
    target.un("beforedestroy", this.onBeforeDestroy, this);
    target.un("render", this.onRender, this);
    target.un("select", this.onFieldChange, this);
    target.un("valid", this.onFieldChange, this);
    var printProvider = this.printProvider || target.ownerCt.printProvider;
    printProvider.un("layoutchange", this.onProviderChange, this);
    printProvider.un("dpichange", this.onProviderChange, this);
    printProvider.un("outputformatchange", this.onProviderChange, this);
}});
Ext.override(GeoExt.VectorLegend, {styleChanged: function () {
    var layer = this.layer;
    if (!layer || !layer.features || layer.features.length == 0) {
        return;
    }
    var feature = layer.features[0].clone();
    feature.attributes = {};
    this.feature = feature;
    this.symbolType = this.symbolTypeFromFeature(this.feature);
    this.setRules();
    this.update();
}, update: function () {
    if (this.layer && !this.layer.events.listeners['stylechanged']) {
        this.layer.events.on({stylechanged: this.styleChanged, scope: this});
    }
    GeoExt.VectorLegend.superclass.update.apply(this, arguments);
    if (this.symbolType && this.rules) {
        if (this.rulesContainer.items) {
            var comp;
            for (var i = this.rulesContainer.items.length - 1; i >= 0; --i) {
                comp = this.rulesContainer.getComponent(i);
                this.rulesContainer.remove(comp, true);
            }
        }
        for (var i = 0, ii = this.rules.length; i < ii; ++i) {
            this.addRuleEntry(this.rules[i], true);
        }
        this.doLayout();
        if (this.selectedRule) {
            this.getRuleEntry(this.selectedRule).body.addClass("x-grid3-row-selected");
        }
    }
}});
Ext.namespace("Heron");
Heron.singleFile = true;
Ext.namespace("Heron");
Ext.namespace("Heron.globals");
Heron.globals = {serviceUrl: '/cgi-bin/heron.cgi', version: '1.0.1', imagePath: undefined};
try {
    Proj4js.defs["EPSG:28992"] = "+proj=sterea +lat_0=52.15616055555555 +lon_0=5.38763888888889 +k=0.999908 +x_0=155000 +y_0=463000 +ellps=bessel +units=m +towgs84=565.2369,50.0087,465.658,-0.406857330322398,0.350732676542563,-1.8703473836068,4.0812 +no_defs";
} catch (err) {
}
Ext.namespace("Heron.App");
Heron.App = function () {
    return{create: function () {
        Ext.QuickTips.init();
        if (Heron.layout.renderTo || Heron.layout.xtype == 'window') {
            Heron.App.topComponent = Ext.create(Heron.layout);
        } else {
            Heron.App.topComponent = new Ext.Viewport({id: "hr-topComponent", layout: "fit", hideBorders: true, items: [Heron.layout]});
        }
    }, show: function () {
        Heron.App.topComponent.show();
    }, getMap: function () {
        return Heron.App.map;
    }, setMap: function (aMap) {
        Heron.App.map = aMap;
    }, getMapPanel: function () {
        return Heron.App.mapPanel;
    }, setMapPanel: function (aMapPanel) {
        Heron.App.mapPanel = aMapPanel;
    }};
}();
Ext.namespace("Heron");
Ext.onReady(function () {
    if (typeof console === 'undefined') {
        console = {log: function (s) {
        }}
    }
    console.log('Starting Heron v' + Heron.globals.version + ' - Proxy URL="' + OpenLayers.ProxyHost + '" - Service URL="' + Heron.globals.serviceUrl + '"');
    if (!Heron.noAutoLaunch) {
        Heron.App.create();
        Heron.App.show();
    }
}, Heron.App);
Ext.namespace("Heron.Utils");
Ext.namespace("Heron.globals");
Heron.Utils = (function () {
    var browserWindows = new Array();
    var openMsgURL = 'http://extjs.cachefly.net/ext-3.4.0/resources/images/default/s.gif';
    var instance = {createOLObject: function (argArr) {
        var clazz = eval(argArr[0]);
        var args = [].slice.call(argArr, 1);

        function F() {
        }

        F.prototype = clazz.prototype;
        var instance = new F();
        instance.initialize.apply(instance, args);
        return instance;
    }, getScriptLocation: function () {
        if (!Heron.globals.scriptLoc) {
            Heron.globals.scriptLoc = '';
            var scriptName = (!Heron.singleFile) ? "lib/DynLoader.js" : "script/Heron.js";
            var r = new RegExp("(^|(.*?\\/))(" + scriptName + ")(\\?|$)"), scripts = document.getElementsByTagName('script'), src = "";
            for (var i = 0, len = scripts.length; i < len; i++) {
                src = scripts[i].getAttribute('src');
                if (src) {
                    var m = src.match(r);
                    if (m) {
                        Heron.globals.scriptLoc = m[1];
                        break;
                    }
                }
            }
        }
        return Heron.globals.scriptLoc;
    }, getImagesLocation: function () {
        return Heron.globals.imagePath || (Heron.Utils.getScriptLocation() + "resources/images/");
    }, getImageLocation: function (image) {
        return Heron.Utils.getImagesLocation() + image;
    }, rand: function (min, max) {
        return Math.floor(Math.random() * ((max - min) + 1) + min);
    }, randArrayElm: function (arr) {
        return arr[Heron.Utils.rand(0, arr.length - 1)];
    }, formatXml: function (xml, htmlEscape) {
        var reg = /(>)(<)(\/*)/g;
        var wsexp = / *(.*) +\n/g;
        var contexp = /(<.+>)(.+\n)/g;
        xml = xml.replace(reg, '$1\n$2$3').replace(wsexp, '$1\n').replace(contexp, '$1\n$2');
        var pad = 0;
        var formatted = '';
        var lines = xml.split('\n');
        var indent = 0;
        var lastType = 'other';
        var transitions = {'single->single': 0, 'single->closing': -1, 'single->opening': 0, 'single->other': 0, 'closing->single': 0, 'closing->closing': -1, 'closing->opening': 0, 'closing->other': 0, 'opening->single': 1, 'opening->closing': 0, 'opening->opening': 1, 'opening->other': 1, 'other->single': 0, 'other->closing': -1, 'other->opening': 0, 'other->other': 0};
        for (var i = 0; i < lines.length; i++) {
            var ln = lines[i];
            var single = Boolean(ln.match(/<.+\/>/));
            var closing = Boolean(ln.match(/<\/.+>/));
            var opening = Boolean(ln.match(/<[^!].*>/));
            var type = single ? 'single' : closing ? 'closing' : opening ? 'opening' : 'other';
            var fromTo = lastType + '->' + type;
            lastType = type;
            var padding = '';
            indent += transitions[fromTo];
            for (var j = 0; j < indent; j++) {
                padding += '    ';
            }
            if (htmlEscape) {
                ln = ln.replace('<', '&lt;');
                ln = ln.replace('>', '&gt;');
            }
            formatted += padding + ln + '\n';
        }
        return formatted;
    }, openBrowserWindow: function (winName, bReopen, theURL, hasMenubar, hasToolbar, hasAddressbar, hasStatusbar, hasScrollbars, isResizable, hasPos, xPos, yPos, hasSize, wSize, hSize) {
        var x, y;
        var options = "";
        var pwin = null;
        if (hasMenubar) {
            options += "menubar=yes";
        } else {
            options += "menubar=no";
        }
        if (hasToolbar) {
            options += ",toolbar=yes";
        } else {
            options += ",toolbar=no";
        }
        if (hasAddressbar) {
            options += ",location=yes";
        } else {
            options += ",location=no";
        }
        if (hasStatusbar) {
            options += ",status=yes";
        } else {
            options += ",status=no";
        }
        if (hasScrollbars) {
            options += ",scrollbars=yes";
        } else {
            options += ",scrollbars=no";
        }
        if (isResizable) {
            options += ",resizable=yes";
        } else {
            options += ",resizable=no";
        }
        if (!hasSize) {
            wSize = 640;
            hSize = 480;
        }
        options += ",width=" + wSize + ",innerWidth=" + wSize;
        options += ",height=" + hSize + ",innerHeight=" + hSize;
        if (!hasPos) {
            xPos = (screen.width - 700) / 2;
            yPos = 75;
        }
        options += ",left=" + xPos + ",top=" + yPos;
        if (bReopen) {
            browserWindows[winName] = window.open(theURL, winName, options);
        } else {
            if (!browserWindows[winName] || browserWindows[winName].closed) {
                browserWindows[winName] = window.open(theURL, winName, options);
            } else {
                browserWindows[winName].location.href = theURL;
            }
        }
        browserWindows[winName].focus();
    }};
    return(instance);
})();
Ext.ns('Ext.ux.form');
Ext.ux.form.Spacer = Ext.extend(Ext.BoxComponent, {height: 12, autoEl: 'div'});
Ext.reg('spacer', Ext.ux.form.Spacer);
Ext.namespace("Heron.data");
Heron.data.OpenLS_XLSReader = function (meta, recordType) {
    meta = meta || {};
    Ext.applyIf(meta, {idProperty: meta.idProperty || meta.idPath || meta.id, successProperty: meta.successProperty || meta.success});
    Heron.data.OpenLS_XLSReader.superclass.constructor.call(this, meta, recordType || meta.fields);
};
Ext.extend(Heron.data.OpenLS_XLSReader, Ext.data.XmlReader, {addOptXlsText: function (format, text, node, tagname, sep) {
    var elms = format.getElementsByTagNameNS(node, "http://www.opengis.net/xls", tagname);
    if (elms) {
        Ext.each(elms, function (elm, index) {
            var str = format.getChildValue(elm);
            if (str) {
                text = text + sep + str;
            }
        });
    }
    return text;
}, readRecords: function (doc) {
    this.xmlData = doc;
    var root = doc.documentElement || doc;
    var records = this.extractData(root);
    return{success: true, records: records, totalRecords: records.length};
}, extractData: function (root) {
    var opts = {namespaces: {gml: "http://www.opengis.net/gml", xls: "http://www.opengis.net/xls"}};
    var records = [];
    var format = new OpenLayers.Format.XML(opts);
    var addresses = format.getElementsByTagNameNS(root, "http://www.opengis.net/xls", 'GeocodedAddress');
    var recordType = Ext.data.Record.create([
        {name: "lon", type: "number"},
        {name: "lat", type: "number"},
        "text"
    ]);
    var reader = this;
    Ext.each(addresses, function (address, index) {
        var pos = format.getElementsByTagNameNS(address, "http://www.opengis.net/gml", 'pos');
        var xy = '';
        if (pos && pos[0]) {
            xy = format.getChildValue(pos[0]);
        }
        var xyArr = xy.split(' ');
        var text = '';
        text = reader.addOptXlsText(format, text, address, 'Street', '');
        text = reader.addOptXlsText(format, text, address, 'Place', ',');
        var values = {lon: parseFloat(xyArr[0]), lat: parseFloat(xyArr[1]), text: text};
        var record = new recordType(values, index);
        records.push(record);
    });
    return records;
}});
Ext.namespace("Heron.data");
Heron.data.DataExporter = {formatStore: function (store, config) {
    var formatter = new Ext.ux.Exporter[config.formatter]();
    var data = formatter.format(store, config);
    if (config.encoding && config.encoding == 'base64') {
        data = Base64.encode(data);
    }
    return data;
}, download: function (data, config) {
    try {
        Ext.destroy(Ext.get('hr_uploadForm'));
    }
    catch (e) {
    }
    var formFields = [
        {tag: 'input', type: 'hidden', name: 'data', value: data},
        {tag: 'input', type: 'hidden', name: 'filename', value: config.fileName},
        {tag: 'input', type: 'hidden', name: 'mime', value: config.mimeType}
    ];
    if (config.format) {
        var format = config.format instanceof OpenLayers.Format ? config.format.CLASS_NAME.split(".") : config.format.split(".");
        format = format.length == 4 ? format[2] : format.pop();
        formFields.push({tag: 'input', type: 'hidden', name: 'source_format', value: format});
    }
    if (config.encoding) {
        formFields.push({tag: 'input', type: 'hidden', name: 'encoding', value: config.encoding});
    }
    if (config.targetFormat) {
        formFields.push({tag: 'input', type: 'hidden', name: 'target_format', value: config.targetFormat});
    }
    if (config.assignSrs) {
        formFields.push({tag: 'input', type: 'hidden', name: 'assign_srs', value: config.assignSrs});
        formFields.push({tag: 'input', type: 'hidden', name: 'source_srs', value: config.assignSrs});
    }
    if (config.sourceSrs) {
        formFields.push({tag: 'input', type: 'hidden', name: 'source_srs', value: config.sourceSrs});
    }
    if (config.targetSrs) {
        formFields.push({tag: 'input', type: 'hidden', name: 'target_srs', value: config.targetSrs});
    }
    var form = Ext.DomHelper.append(document.body, {tag: 'form', id: 'hr_uploadForm', method: 'post', action: Heron.globals.serviceUrl, children: formFields});
    document.body.appendChild(form);
    form.submit();
}, directDownload: function (url) {
    try {
        Ext.destroy(Ext.get('hr_directdownload'));
    }
    catch (e) {
    }
    var iframe = Ext.DomHelper.append(document.body, {tag: 'iframe', id: 'hr_directdownload', name: 'hr_directdownload', width: '0px', height: '0px', border: '0px', style: 'width: 0; height: 0; border: none;', src: url});
    document.body.appendChild(iframe);
}};
Ext.namespace("Heron.data");
Heron.data.MapContext = {prefix: "heron:", xmlns: 'xmlns:heron="http://heron-mc.org/context"', oldNodes: null, initComponent: function () {
    Heron.data.MapContext.superclass.initComponent.call(this);
}, saveContext: function (mapPanel, options) {
    var self = this;
    var data = self.writeContext(mapPanel);
    data = this.formatXml(data);
    data = Base64.encode(data);
    try {
        Ext.destroy(Ext.get('hr_downloadForm'));
    }
    catch (e) {
    }
    var formFields = [
        {tag: 'input', type: 'hidden', name: 'data', value: data},
        {tag: 'input', type: 'hidden', name: 'filename', value: options.fileName + options.fileExt},
        {tag: 'input', type: 'hidden', name: 'mime', value: 'text/xml'},
        {tag: 'input', type: 'hidden', name: 'encoding', value: 'base64'},
        {tag: 'input', type: 'hidden', name: 'action', value: 'download'},
    ];
    var form = Ext.DomHelper.append(document.body, {tag: 'form', id: 'hr_downloadForm', method: 'post', action: Heron.globals.serviceUrl, children: formFields});
    document.body.appendChild(form);
    form.submit();
}, openContext: function (mapPanel, options) {
    var self = this;
    var data = null;
    try {
        Ext.destroy(Ext.get('hr_uploadForm'));
    }
    catch (e) {
    }
    var uploadForm = new Ext.form.FormPanel({id: 'hr_uploadForm', fileUpload: true, width: 300, autoHeight: true, bodyStyle: 'padding: 10px 10px 10px 10px;', labelWidth: 5, defaults: {anchor: '95%', allowBlank: false, msgTarget: 'side'}, items: [
        {xtype: 'field', id: 'mapfile', name: 'file', inputType: 'file'}
    ], buttons: [
        {text: __('Upload'), handler: function () {
            if (uploadForm.getForm().isValid()) {
                var fileField = uploadForm.getForm().findField('mapfile');
                var selectedFile = fileField.getValue();
                if (!selectedFile) {
                    Ext.Msg.alert(__('Warning'), __('No file specified.'));
                    return;
                }
                uploadForm.getForm().submit({url: Heron.globals.serviceUrl, mime: 'text/html', params: {action: 'upload', mime: 'text/html', encoding: 'base64'}, waitMsg: __('Uploading file...'), success: function (form, action) {
                    data = Base64.decode(action.response.responseText);
                    self.loadContext(mapPanel, data);
                    uploadWindow.close();
                }, failure: function (form, action) {
                    data = Base64.decode(action.response.responseText);
                    self.loadContext(mapPanel, data);
                    uploadWindow.close();
                }});
            }
        }},
        {text: __('Cancel'), handler: function () {
            uploadWindow.close();
        }}
    ]});
    var uploadWindow = new Ext.Window({id: 'hr_uploadWindow', title: 'Upload', closable: true, width: 400, height: 120, plain: true, layout: 'fit', items: uploadForm, listeners: {show: function () {
        var form = this.items.get(0);
        form.getForm().load();
    }}});
    uploadWindow.show();
}, writeContext: function (mapPanel) {
    var map = mapPanel.getMap();
    var format = new OpenLayers.Format.WMC();
    var data = format.write(map);
    var objMap = {units: map.units, xy_precision: map.xy_precision, projection: map.projection, zoom: map.zoom, resolutions: map.resolutions, resolution: map.resolution, maxExtent: {bottom: map.maxExtent.bottom, left: map.maxExtent.left, right: map.maxExtent.right, top: map.maxExtent.top}};
    var jsonMap = (Ext.encode(objMap));
    jsonMap = this.formatJson(jsonMap);
    var mapOptions = "<Extension><" + this.prefix + "mapOptions " + this.xmlns + ">\n" +
        jsonMap + "</" + this.prefix + "mapOptions></Extension>";
    data = data.replace("</LayerList>", "</LayerList>" + mapOptions);
    var treePanel = Heron.App.topComponent.findByType('hr_layertreepanel')[0];
    if (treePanel && treePanel.jsonTreeConfig != null) {
        var jsonTree = treePanel.jsonTreeConfig;
        var tree = "<Extension><" + this.prefix + "treeConfig " + this.xmlns + ">" +
            jsonTree + "</" + this.prefix + "treeConfig></Extension>";
        data = data.replace("</LayerList>", "</LayerList>" + tree);
    }
    var arrTmsLayers = new Array();
    arrTmsLayers = map.getLayersBy("id", /OpenLayers.Layer.TMS/);
    var jsonTmsLayers = '';
    for (var i = 0; i < arrTmsLayers.length; i++) {
        var tmsLayer = arrTmsLayers[i];
        var objTmsOptions = {layername: tmsLayer.layername, type: tmsLayer.type, isBaseLayer: tmsLayer.isBaseLayer, transparent: tmsLayer.transparent, bgcolor: tmsLayer.bgcolor, visibility: tmsLayer.visibility, singleTile: tmsLayer.singleTile, alpha: tmsLayer.alpha, opacity: tmsLayer.opacity, minResolution: tmsLayer.minResolution, maxResolution: tmsLayer.maxResolution, projection: tmsLayer.projection.projCode, units: tmsLayer.units, transitionEffect: tmsLayer.transitionEffect}
        var objTms = {name: tmsLayer.name, url: tmsLayer.url, options: objTmsOptions};
        var jsonTms = (Ext.encode(objTms));
        if (jsonTmsLayers == '')
            jsonTmsLayers += jsonTms
        else
            jsonTmsLayers = jsonTmsLayers + ',' + jsonTms;
    }
    if (jsonTmsLayers != '') {
        jsonTmsLayers = this.formatJson(jsonTmsLayers);
        var tms = "<Extension><" + this.prefix + "tmsLayers " + this.xmlns + ">\n[" +
            jsonTmsLayers + "]\n</" + this.prefix + "tmsLayers></Extension>";
        data = data.replace("</LayerList>", "</LayerList>" + tms);
    }
    var arrImgLayers = new Array();
    arrImgLayers = map.getLayersBy("id", /OpenLayers.Layer.Image/);
    var jsonImgLayers = '';
    for (i = 0; i < arrImgLayers.length; i++) {
        var imgLayer = arrImgLayers[i];
        var objImgOptions = {layername: imgLayer.layername, type: imgLayer.type, isBaseLayer: imgLayer.isBaseLayer, transparent: imgLayer.transparent, bgcolor: imgLayer.bgcolor, visibility: imgLayer.visibility, alpha: imgLayer.alpha, opacity: imgLayer.opacity, minResolution: imgLayer.minResolution, maxResolution: imgLayer.maxResolution, projection: imgLayer.projection.projCode, units: imgLayer.units, transitionEffect: imgLayer.transitionEffect, size: imgLayer.size, extent: imgLayer.extent}
        var objImg = {name: imgLayer.name, url: imgLayer.url, options: objImgOptions};
        var jsonImg = (Ext.encode(objImg));
        if (jsonImgLayers == '')
            jsonImgLayers += jsonImg
        else
            jsonImgLayers = jsonImgLayers + ',' + jsonImg;
    }
    if (jsonImgLayers != '') {
        jsonImgLayers = this.formatJson(jsonImgLayers);
        var img = "<Extension><" + this.prefix + "imageLayers " + this.xmlns + ">\n[" +
            jsonImgLayers + "]\n</" + this.prefix + "imageLayers></Extension>";
        data = data.replace("</LayerList>", "</LayerList>" + img);
    }
    return data;
}, loadContext: function (mapPanel, data) {
    var map = mapPanel.getMap();
    var format = new OpenLayers.Format.WMC();
    var num;
    var objLayer;
    var newLayer;
    var isBaseLayerInFile = false;
    var oldNodes = new Array();
    var treePanel = Heron.App.topComponent.findByType('hr_layertreepanel')[0];
    if (treePanel) {
        var treeRoot = treePanel.getRootNode();
    }
    var strTagStart = "<" + this.prefix + 'treeConfig ' + this.xmlns + ">"
    var strTagEnd = "</" + this.prefix + 'treeConfig' + ">"
    var posStart = data.indexOf(strTagStart);
    var posEnd = data.indexOf(strTagEnd);
    var newTreeConfig = null;
    if (posStart > 0) {
        posStart = data.indexOf(strTagStart) + strTagStart.length;
        newTreeConfig = data.substring(posStart, posEnd);
    }
    strTagStart = "<" + this.prefix + 'mapOptions ' + this.xmlns + ">"
    strTagEnd = "</" + this.prefix + 'mapOptions' + ">"
    posStart = data.indexOf(strTagStart);
    posEnd = data.indexOf(strTagEnd);
    var newMapOptions = null;
    if (posStart > 0) {
        posStart = data.indexOf(strTagStart) + strTagStart.length;
        newMapOptions = data.substring(posStart, posEnd);
    }
    strTagStart = "<" + this.prefix + 'tmsLayers ' + this.xmlns + ">"
    strTagEnd = "</" + this.prefix + 'tmsLayers' + ">"
    posStart = data.indexOf(strTagStart);
    posEnd = data.indexOf(strTagEnd);
    var tmsLayers = null;
    if (posStart > 0) {
        posStart = data.indexOf(strTagStart) + strTagStart.length;
        tmsLayers = data.substring(posStart, posEnd);
    }
    strTagStart = "<" + this.prefix + 'imageLayers ' + this.xmlns + ">"
    strTagEnd = "</" + this.prefix + 'imageLayers' + ">"
    posStart = data.indexOf(strTagStart);
    posEnd = data.indexOf(strTagEnd);
    var imgLayers = null;
    if (posStart > 0) {
        posStart = data.indexOf(strTagStart) + strTagStart.length;
        imgLayers = data.substring(posStart, posEnd);
    }
    try {
        var testMap = new OpenLayers.Map();
        testMap = format.read(data, {map: testMap});
        num = testMap.getNumLayers();
        var i = 0;
        do {
            isBaseLayerInFile = testMap.layers[i].isBaseLayer;
            i++;
        } while (!isBaseLayerInFile && i < num)
        testMap.destroy();
    } catch (err) {
        Ext.Msg.alert(__('Error reading map file, map has not been loaded.'));
        console.log("Error while testing WMC file: " + err.message);
        testMap.destroy();
        return;
    }
    if (treePanel) {
        treePanel.getLoader().doPreload(treeRoot);
        for (i = 0; i < treeRoot.childNodes.length; i++) {
            oldNodes.push(treeRoot.childNodes[i]);
            treeRoot.childNodes[i].cascade(function (node) {
                oldNodes.push(node);
            }, null, null);
        }
    }
    num = map.getNumLayers();
    for (i = num - 1; i >= 0; i--) {
        var strLayer = null;
        try {
            strLayer = map.layers[i].name;
            map.removeLayer(map.layers[i], false);
        } catch (err) {
            Ext.Msg.alert(__('Error on removing layers.'));
            console.log("Problem with removing layers before loading map: " + err.message);
            console.log("Layer[" + i + "]: " + strLayer);
        }
    }
    if (treePanel) {
        while (oldNodes.length > 0) {
            var oldNode = oldNodes.pop()
            if (oldNode) {
                this.removeTreeNode(oldNode);
            }
        }
    }
    var mapOptions = Ext.decode(newMapOptions);
    var maxExtent = mapOptions.maxExtent;
    var bounds = new OpenLayers.Bounds(maxExtent.left, maxExtent.bottom, maxExtent.right, maxExtent.top);
    delete mapOptions.maxExtent;
    map.setOptions(mapOptions);
    map.setOptions({maxExtent: bounds});
    map.allOverlays = !isBaseLayerInFile
    if (tmsLayers != null) {
        tmsLayers = Ext.decode(tmsLayers);
        for (i = 0; i < tmsLayers.length; i++) {
            objLayer = tmsLayers[i];
            newLayer = new OpenLayers.Layer.TMS(objLayer.name, objLayer.url, objLayer.options);
            if (newLayer.isBaseLayer && !isBaseLayerInFile) {
                isBaseLayerInFile = true;
                map.allOverlays = false;
            }
            map.addLayer(newLayer);
            if (objLayer.options.isBaseLayer && objLayer.options.visibility) {
                map.setBaseLayer(newLayer);
            }
        }
    }
    if (imgLayers != null) {
        imgLayers = Ext.decode(imgLayers);
        for (i = 0; i < imgLayers.length; i++) {
            objLayer = imgLayers[i];
            var imgExtent = objLayer.options.extent;
            delete objLayer.options.extent;
            var objExtent = new OpenLayers.Bounds(imgExtent.left, imgExtent.bottom, imgExtent.right, imgExtent.top);
            newLayer = new OpenLayers.Layer.Image(objLayer.name, objLayer.url, objExtent, objLayer.options.size, objLayer.options);
            if (newLayer.isBaseLayer && !isBaseLayerInFile) {
                isBaseLayerInFile = true;
                map.allOverlays = false;
            }
            map.addLayer(newLayer);
            if (objLayer.options.isBaseLayer && objLayer.options.visibility) {
                map.setBaseLayer(newLayer);
            }
        }
    }
    try {
        map = format.read(data, {map: map});
    } catch (err) {
        Ext.Msg.alert(__('Error loading map file.'));
        console.log("Error loading map file: " + err.message);
    }
    if (treePanel && newTreeConfig) {
        treeRoot.attributes.children = Ext.decode(newTreeConfig);
        try {
            treePanel.getLoader().load(treeRoot);
            treePanel.jsonTreeConfig = newTreeConfig;
        } catch (err) {
            Ext.Msg.alert(__('Error reading layer tree.'));
            console.log("Error on loading tree: " + err.message);
        }
    }
    var epsgTxt = map.getProjection();
    if (epsgTxt) {
        var epsg = Ext.getCmp("map-panel-epsg");
        if (epsg) {
            epsg.setText(epsgTxt);
        }
    }
    num = format.context.layersContext.length;
    for (i = num - 1; i >= 0; i--) {
        if ((format.context.layersContext[i].isBaseLayer == true) && (format.context.layersContext[i].visibility == true)) {
            var strActiveBaseLayer = format.context.layersContext[i].title;
            var newBaseLayer = map.getLayersByName(strActiveBaseLayer)[0];
            if (newBaseLayer) {
                try {
                    map.setBaseLayer(newBaseLayer);
                } catch (err) {
                    console.log("Error on setting Baselayer: " + err.message);
                }
            }
        }
    }
    map.zoomToExtent(format.context.bounds);
}, removeTreeNode: function (node) {
    if (node.childNodes && node.childNodes.length > 0) {
        for (var i = 0; i < node.childNodes.length; i++) {
            this.removeTreeNode(node.childNodes[i]);
        }
    } else {
        node.remove(true);
    }
}, formatXml: function (xml) {
    var formatted = '';
    var reg = /(>)(<)(\/*)/g;
    xml = xml.replace(reg, '$1\n$2$3');
    var arrSplit = xml.split('\n');
    var pad = 0;
    for (var intNode = 0; intNode < arrSplit.length; intNode++) {
        var node = arrSplit[intNode];
        var indent = 0;
        if (node.match(/.+<\/\w[^>]*>$/)) {
            indent = 0;
        } else if (node.match(/^<\/\w/)) {
            if (pad != 0) {
                pad -= 1;
            }
        } else if (node.match(/^<\w[^>]*[^\/]>.*$/)) {
            indent = 1;
        } else {
            indent = 0;
        }
        var padding = '';
        for (var i = 0; i < pad; i++) {
            padding += '    ';
        }
        formatted += padding + node + '\n';
        pad += indent;
    }
    return formatted;
}, formatJson: function (json) {
    var formatted = '';
    json = json.replace(/({)/g, '$1\n');
    json = json.replace(/(})({)/g, '$1\n$2');
    json = json.replace(/(:)({)/g, '$1\n$2');
    json = json.replace(/(,)/g, '$1\n');
    json = json.replace(/(})/g, '\n$1');
    var arrSplit = json.split('\n');
    var pad = 0;
    for (var intNode = 0; intNode < arrSplit.length; intNode++) {
        var node = arrSplit[intNode];
        var indent = 0;
        if (node.match(/}/)) {
            if (pad != 0) {
                pad -= 1;
            }
        } else if (node.match(/{/)) {
            indent = 1;
        } else {
            indent = 0;
        }
        var padding = '';
        for (var i = 0; i < pad; i++) {
            padding += '    ';
        }
        formatted += padding + node + '\n';
        pad += indent;
    }
    return formatted;
}};
var Base64 = (function () {
    var keyStr = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";

    function utf8Encode(string) {
        string = string.replace(/\r\n/g, "\n");
        var utftext = "";
        for (var n = 0; n < string.length; n++) {
            var c = string.charCodeAt(n);
            if (c < 128) {
                utftext += String.fromCharCode(c);
            }
            else if ((c > 127) && (c < 2048)) {
                utftext += String.fromCharCode((c >> 6) | 192);
                utftext += String.fromCharCode((c & 63) | 128);
            }
            else {
                utftext += String.fromCharCode((c >> 12) | 224);
                utftext += String.fromCharCode(((c >> 6) & 63) | 128);
                utftext += String.fromCharCode((c & 63) | 128);
            }
        }
        return utftext;
    }

    return{encode: function (input) {
        var output = "";
        var chr1, chr2, chr3, enc1, enc2, enc3, enc4;
        var i = 0;
        input = utf8Encode(input);
        while (i < input.length) {
            chr1 = input.charCodeAt(i++);
            chr2 = input.charCodeAt(i++);
            chr3 = input.charCodeAt(i++);
            enc1 = chr1 >> 2;
            enc2 = ((chr1 & 3) << 4) | (chr2 >> 4);
            enc3 = ((chr2 & 15) << 2) | (chr3 >> 6);
            enc4 = chr3 & 63;
            if (isNaN(chr2)) {
                enc3 = enc4 = 64;
            } else if (isNaN(chr3)) {
                enc4 = 64;
            }
            output = output +
                keyStr.charAt(enc1) + keyStr.charAt(enc2) +
                keyStr.charAt(enc3) + keyStr.charAt(enc4);
        }
        return output;
    }};
})();
Ext.ux.Exporter = function () {
    return{exportGrid: function (grid, formatter, config) {
        config = config || {};
        formatter = formatter || new Ext.ux.Exporter.CSVFormatter();
        Ext.applyIf(config, {title: grid.title});
        return formatter.format(grid.store, config);
    }, exportStore: function (store, formatter, config) {
        config = config || {};
        formatter = formatter || new Ext.ux.Exporter.ExcelFormatter();
        Ext.applyIf(config, {columns: config.store.fields.items});
        return Base64.encode(formatter.format(store, config));
    }, exportTree: function (tree, formatter, config) {
        config = config || {};
        formatter = formatter || new Ext.ux.Exporter.ExcelFormatter();
        var store = tree.store || config.store;
        Ext.applyIf(config, {title: tree.title});
        return Base64.encode(formatter.format(store, config));
    }};
}();
Ext.ux.Exporter.Button = Ext.extend(Ext.Button, {constructor: function (config) {
    config = config || {};
    Ext.applyIf(config, {formatter: 'CSVFormatter', fileName: 'heron_export.csv', mimeType: 'text/csv', exportFunction: 'exportGrid', disabled: true, text: 'Export', cls: 'download'});
    if (config.store == undefined && config.component != undefined) {
        Ext.applyIf(config, {store: config.component.store});
    } else {
        Ext.applyIf(config, {component: {store: config.store}});
    }
    Ext.ux.Exporter.Button.superclass.constructor.call(this, config);
    if (this.store && Ext.isFunction(this.store.on)) {
        var self = this;
        self.store = this.store;
        var setLink = function () {
            var link = this.getEl().child('a', true);
            var buttonFun = function () {
                var formatter = new Ext.ux.Exporter[config.formatter]();
                var data = formatter.format(self.store, config);
                data = Base64.encode(data);
                Heron.data.DataExporter.download(data, config.fileName, config.mimeType)
            };
            link.href = '#';
            link.onclick = buttonFun;
            this.enable();
        }
        if (this.el) {
            setLink.call(this);
        } else {
            this.on('render', setLink, this);
        }
        this.store.on('load', setLink, this);
    }
}, template: new Ext.Template('<table border="0" cellpadding="0" cellspacing="0" class="x-btn-wrap"><tbody><tr>', '<td class="x-btn-left"><i> </i></td><td class="x-btn-center"><a class="x-btn-text" href="{1}" target="{2}">{0}</a></td><td class="x-btn-right"><i> </i></td>', "</tr></tbody></table>"), onRender: function (ct, position) {
    var btn, targs = [this.text || ' ', this.href, this.target || "_self"];
    if (position) {
        btn = this.template.insertBefore(position, targs, true);
    } else {
        btn = this.template.append(ct, targs, true);
    }
    var btnEl = btn.child("a:first");
    this.btnEl = btnEl;
    btnEl.on('focus', this.onFocus, this);
    btnEl.on('blur', this.onBlur, this);
    this.initButtonEl(btn, btnEl);
    Ext.ButtonToggleMgr.register(this);
}, onClick: function (e) {
    if (e.button != 0)return;
    if (!this.disabled) {
        this.fireEvent("click", this, e);
        if (this.handler)this.handler.call(this.scope || this, this, e);
    }
}});
Ext.reg('exportbutton', Ext.ux.Exporter.Button);
Ext.ux.Exporter.Formatter = function (config) {
    config = config || {};
    Ext.applyIf(config, {});
};
Ext.ux.Exporter.Formatter.prototype = {format: Ext.emptyFn};
Ext.ux.Exporter.OpenLayersFormatter = Ext.extend(Ext.ux.Exporter.Formatter, {format: function (store, config) {
    var formatter = config.format;
    if (typeof formatter == 'string') {
        formatter = eval('new ' + formatter + '()');
    }
    if (config.fileProjection) {
        formatter.internalProjection = config.mapProjection;
        formatter.externalProjection = config.fileProjection;
    }
    formatter.srsName = formatter.externalProjection ? formatter.externalProjection.getCode() : config.assignSrs;
    var features = store.layer ? store.layer.features : null;
    if (!features) {
        features = [];
        store.each(function (record) {
            features.push(record.getFeature());
        });
    }
    return formatter.write(features);
}});
Ext.ux.Exporter.ExcelFormatter = Ext.extend(Ext.ux.Exporter.Formatter, {format: function (store, config) {
    var workbook = new Ext.ux.Exporter.ExcelFormatter.Workbook(config);
    workbook.addWorksheet(store, config || {});
    return workbook.render();
}});
Ext.ux.Exporter.CSVFormatter = Ext.extend(Ext.ux.Exporter.Formatter, {extend: "Ext.ux.exporter.Formatter", contentType: 'data:text/csv;base64,', separator: ";", extension: "csv", format: function (store, config) {
    this.columns = config.columns || (store.fields ? store.fields.items : store.model.prototype.fields.items);
    return this.getHeaders(store) + "\n" + this.getRows(store);
}, getHeaders: function (store) {
    var columns = [];
    Ext.each(this.columns, function (col) {
        var title;
        if (col.text != undefined) {
            title = col.text;
        } else if (col.name) {
            title = col.name.replace(/_/g, " ");
        }
        columns.push(title);
    }, this);
    return columns.join(this.separator);
}, getRows: function (store) {
    var rows = [];
    store.each(function (record, index) {
        rows.push(this.getCell(record, index));
    }, this);
    return rows.join("\n");
}, getCell: function (record, index) {
    var cells = [];
    Ext.each(this.columns, function (col) {
        var name = col.name || col.dataIndex;
        if (name) {
            if (Ext.isFunction(col.renderer)) {
                var value = col.renderer(record.get(name), null, record);
            } else {
                var value = record.get(name);
            }
            cells.push(value);
        }
    });
    return cells.join(this.separator);
}});
Ext.ux.Exporter.ExcelFormatter.Workbook = Ext.extend(Object, {constructor: function (config) {
    config = config || {};
    Ext.apply(this, config, {title: "Workbook", worksheets: [], compiledWorksheets: [], cellBorderColor: "#e4e4e4", styles: [], compiledStyles: [], hasDefaultStyle: true, hasStripeStyles: true, windowHeight: 9000, windowWidth: 50000, protectStructure: false, protectWindows: false});
    if (this.hasDefaultStyle)this.addDefaultStyle();
    if (this.hasStripeStyles)this.addStripedStyles();
    this.addTitleStyle();
    this.addHeaderStyle();
}, render: function () {
    this.compileStyles();
    this.joinedCompiledStyles = this.compiledStyles.join("");
    this.compileWorksheets();
    this.joinedWorksheets = this.compiledWorksheets.join("");
    return this.tpl.apply(this);
}, addWorksheet: function (store, config) {
    var worksheet = new Ext.ux.Exporter.ExcelFormatter.Worksheet(store, config);
    this.worksheets.push(worksheet);
    return worksheet;
}, addStyle: function (config) {
    var style = new Ext.ux.Exporter.ExcelFormatter.Style(config || {});
    this.styles.push(style);
    return style;
}, compileStyles: function () {
    this.compiledStyles = [];
    Ext.each(this.styles, function (style) {
        this.compiledStyles.push(style.render());
    }, this);
    return this.compiledStyles;
}, compileWorksheets: function () {
    this.compiledWorksheets = [];
    Ext.each(this.worksheets, function (worksheet) {
        this.compiledWorksheets.push(worksheet.render());
    }, this);
    return this.compiledWorksheets;
}, tpl: new Ext.XTemplate('<?xml version="1.0" encoding="utf-8"?>', '<ss:Workbook xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:o="urn:schemas-microsoft-com:office:office">', '<o:DocumentProperties>', '<o:Title>{title}</o:Title>', '</o:DocumentProperties>', '<ss:ExcelWorkbook>', '<ss:WindowHeight>{windowHeight}</ss:WindowHeight>', '<ss:WindowWidth>{windowWidth}</ss:WindowWidth>', '<ss:ProtectStructure>{protectStructure}</ss:ProtectStructure>', '<ss:ProtectWindows>{protectWindows}</ss:ProtectWindows>', '</ss:ExcelWorkbook>', '<ss:Styles>', '{joinedCompiledStyles}', '</ss:Styles>', '{joinedWorksheets}', '</ss:Workbook>'), addDefaultStyle: function () {
    var borderProperties = [
        {name: "Color", value: this.cellBorderColor},
        {name: "Weight", value: "1"},
        {name: "LineStyle", value: "Continuous"}
    ];
    this.addStyle({id: 'Default', attributes: [
        {name: "Alignment", properties: [
            {name: "Vertical", value: "Top"},
            {name: "WrapText", value: "1"}
        ]},
        {name: "Font", properties: [
            {name: "FontName", value: "arial"},
            {name: "Size", value: "10"}
        ]},
        {name: "Interior"},
        {name: "NumberFormat"},
        {name: "Protection"},
        {name: "Borders", children: [
            {name: "Border", properties: [
                {name: "Position", value: "Top"}
            ].concat(borderProperties)},
            {name: "Border", properties: [
                {name: "Position", value: "Bottom"}
            ].concat(borderProperties)},
            {name: "Border", properties: [
                {name: "Position", value: "Left"}
            ].concat(borderProperties)},
            {name: "Border", properties: [
                {name: "Position", value: "Right"}
            ].concat(borderProperties)}
        ]}
    ]});
}, addTitleStyle: function () {
    this.addStyle({id: "title", attributes: [
        {name: "Borders"},
        {name: "Font"},
        {name: "NumberFormat", properties: [
            {name: "Format", value: "@"}
        ]},
        {name: "Alignment", properties: [
            {name: "WrapText", value: "1"},
            {name: "Horizontal", value: "Center"},
            {name: "Vertical", value: "Center"}
        ]}
    ]});
}, addHeaderStyle: function () {
    this.addStyle({id: "headercell", attributes: [
        {name: "Font", properties: [
            {name: "Bold", value: "1"},
            {name: "Size", value: "10"}
        ]},
        {name: "Interior", properties: [
            {name: "Pattern", value: "Solid"},
            {name: "Color", value: "#A3C9F1"}
        ]},
        {name: "Alignment", properties: [
            {name: "WrapText", value: "1"},
            {name: "Horizontal", value: "Center"}
        ]}
    ]});
}, addStripedStyles: function () {
    this.addStyle({id: "even", attributes: [
        {name: "Interior", properties: [
            {name: "Pattern", value: "Solid"},
            {name: "Color", value: "#CCFFFF"}
        ]}
    ]});
    this.addStyle({id: "odd", attributes: [
        {name: "Interior", properties: [
            {name: "Pattern", value: "Solid"},
            {name: "Color", value: "#CCCCFF"}
        ]}
    ]});
    Ext.each(['even', 'odd'], function (parentStyle) {
        this.addChildNumberFormatStyle(parentStyle, parentStyle + 'date', "[ENG][$-409]dd\-mmm\-yyyy;@");
        this.addChildNumberFormatStyle(parentStyle, parentStyle + 'int', "0");
        this.addChildNumberFormatStyle(parentStyle, parentStyle + 'float', "0.00");
    }, this);
}, addChildNumberFormatStyle: function (parentStyle, id, value) {
    this.addStyle({id: id, parentStyle: "even", attributes: [
        {name: "NumberFormat", properties: [
            {name: "Format", value: value}
        ]}
    ]});
}});
Ext.ux.Exporter.ExcelFormatter.Worksheet = Ext.extend(Object, {constructor: function (store, config) {
    config = config || {};
    this.store = store;
    Ext.applyIf(config, {hasTitle: true, hasHeadings: true, stripeRows: true, title: "Workbook", columns: store.fields == undefined ? {} : store.fields.items});
    Ext.apply(this, config);
    Ext.ux.Exporter.ExcelFormatter.Worksheet.superclass.constructor.apply(this, arguments);
}, dateFormatString: "Y-m-d", worksheetTpl: new Ext.XTemplate('<ss:Worksheet ss:Name="{title}">', '<ss:Names>', '<ss:NamedRange ss:Name="Print_Titles" ss:RefersTo="=\'{title}\'!R1:R2" />', '</ss:Names>', '<ss:Table x:FullRows="1" x:FullColumns="1" ss:ExpandedColumnCount="{colCount}" ss:ExpandedRowCount="{rowCount}">', '{columns}', '<ss:Row ss:Height="38">', '<ss:Cell ss:StyleID="title" ss:MergeAcross="{colCount - 1}">', '<ss:Data xmlns:html="http://www.w3.org/TR/REC-html40" ss:Type="String">', '<html:B><html:U><html:Font html:Size="15">{title}', '</html:Font></html:U></html:B></ss:Data><ss:NamedCell ss:Name="Print_Titles" />', '</ss:Cell>', '</ss:Row>', '<ss:Row ss:AutoFitHeight="1">', '{header}', '</ss:Row>', '{rows}', '</ss:Table>', '<x:WorksheetOptions>', '<x:PageSetup>', '<x:Layout x:CenterHorizontal="1" x:Orientation="Landscape" />', '<x:Footer x:Data="Page &amp;P of &amp;N" x:Margin="0.5" />', '<x:PageMargins x:Top="0.5" x:Right="0.5" x:Left="0.5" x:Bottom="0.8" />', '</x:PageSetup>', '<x:FitToPage />', '<x:Print>', '<x:PrintErrors>Blank</x:PrintErrors>', '<x:FitWidth>1</x:FitWidth>', '<x:FitHeight>32767</x:FitHeight>', '<x:ValidPrinterInfo />', '<x:VerticalResolution>600</x:VerticalResolution>', '</x:Print>', '<x:Selected />', '<x:DoNotDisplayGridlines />', '<x:ProtectObjects>False</x:ProtectObjects>', '<x:ProtectScenarios>False</x:ProtectScenarios>', '</x:WorksheetOptions>', '</ss:Worksheet>'), render: function (store) {
    return this.worksheetTpl.apply({header: this.buildHeader(), columns: this.buildColumns().join(""), rows: this.buildRows().join(""), colCount: this.columns.length, rowCount: this.store.getCount() + 2, title: this.title});
}, buildColumns: function () {
    var cols = [];
    Ext.each(this.columns, function (column) {
        cols.push(this.buildColumn());
    }, this);
    return cols;
}, buildColumn: function (width) {
    return String.format('<ss:Column ss:AutoFitWidth="1" ss:Width="{0}" />', width || 164);
}, buildRows: function () {
    var rows = [];
    this.store.each(function (record, index) {
        rows.push(this.buildRow(record, index));
    }, this);
    return rows;
}, buildHeader: function () {
    var cells = [];
    Ext.each(this.columns, function (col) {
        var title;
        if (col.header != undefined) {
            title = col.header;
        } else {
            title = col.name.replace(/_/g, " ");
        }
        cells.push(String.format('<ss:Cell ss:StyleID="headercell"><ss:Data ss:Type="String">{0}</ss:Data><ss:NamedCell ss:Name="Print_Titles" /></ss:Cell>', title));
    }, this);
    return cells.join("");
}, buildRow: function (record, index) {
    var style, cells = [];
    if (this.stripeRows === true)style = index % 2 == 0 ? 'even' : 'odd';
    Ext.each(this.columns, function (col) {
        var name = col.name || col.dataIndex;
        if (Ext.isFunction(col.renderer)) {
            var value = col.renderer(record.get(name), null, record), type = "String";
        } else {
            var value = record.get(name), type = this.typeMappings[col.type || record.fields.item(name).type];
        }
        cells.push(this.buildCell(value, type, style).render());
    }, this);
    return String.format("<ss:Row>{0}</ss:Row>", cells.join(""));
}, buildCell: function (value, type, style) {
    if (type == "DateTime" && Ext.isFunction(value.format))value = value.format(this.dateFormatString);
    return new Ext.ux.Exporter.ExcelFormatter.Cell({value: value, type: type, style: style});
}, typeMappings: {'int': "Number", 'string': "String", 'float': "Number", 'date': "DateTime"}});
Ext.ux.Exporter.ExcelFormatter.Cell = Ext.extend(Object, {constructor: function (config) {
    Ext.applyIf(config, {type: "String"});
    Ext.apply(this, config);
    Ext.ux.Exporter.ExcelFormatter.Cell.superclass.constructor.apply(this, arguments);
}, render: function () {
    return this.tpl.apply(this);
}, tpl: new Ext.XTemplate('<ss:Cell ss:StyleID="{style}">', '<ss:Data ss:Type="{type}"><![CDATA[{value}]]></ss:Data>', '</ss:Cell>')});
Ext.ux.Exporter.ExcelFormatter.Style = Ext.extend(Object, {constructor: function (config) {
    config = config || {};
    Ext.apply(this, config, {parentStyle: '', attributes: []});
    Ext.ux.Exporter.ExcelFormatter.Style.superclass.constructor.apply(this, arguments);
    if (this.id == undefined)throw new Error("An ID must be provided to Style");
    this.preparePropertyStrings();
}, preparePropertyStrings: function () {
    Ext.each(this.attributes, function (attr, index) {
        this.attributes[index].propertiesString = this.buildPropertyString(attr);
        this.attributes[index].children = attr.children || [];
        Ext.each(attr.children, function (child, childIndex) {
            this.attributes[index].children[childIndex].propertiesString = this.buildPropertyString(child);
        }, this);
    }, this);
}, buildPropertyString: function (attribute) {
    var propertiesString = "";
    Ext.each(attribute.properties || [], function (property) {
        propertiesString += String.format('ss:{0}="{1}" ', property.name, property.value);
    }, this);
    return propertiesString;
}, render: function () {
    return this.tpl.apply(this);
}, tpl: new Ext.XTemplate('<tpl if="parentStyle.length == 0">', '<ss:Style ss:ID="{id}">', '</tpl>', '<tpl if="parentStyle.length != 0">', '<ss:Style ss:ID="{id}" ss:Parent="{parentStyle}">', '</tpl>', '<tpl for="attributes">', '<tpl if="children.length == 0">', '<ss:{name} {propertiesString} />', '</tpl>', '<tpl if="children.length > 0">', '<ss:{name} {propertiesString}>', '<tpl for="children">', '<ss:{name} {propertiesString} />', '</tpl>', '</ss:{name}>', '</tpl>', '</tpl>', '</ss:Style>')});
var Base64 = {_keyStr: "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=", encode: function (input) {
    var output = "";
    var chr1, chr2, chr3, enc1, enc2, enc3, enc4;
    var i = 0;
    input = Base64._utf8_encode(input);
    while (i < input.length) {
        chr1 = input.charCodeAt(i++);
        chr2 = input.charCodeAt(i++);
        chr3 = input.charCodeAt(i++);
        enc1 = chr1 >> 2;
        enc2 = ((chr1 & 3) << 4) | (chr2 >> 4);
        enc3 = ((chr2 & 15) << 2) | (chr3 >> 6);
        enc4 = chr3 & 63;
        if (isNaN(chr2)) {
            enc3 = enc4 = 64;
        } else if (isNaN(chr3)) {
            enc4 = 64;
        }
        output = output +
            this._keyStr.charAt(enc1) + this._keyStr.charAt(enc2) +
            this._keyStr.charAt(enc3) + this._keyStr.charAt(enc4);
    }
    return output;
}, decode: function (input) {
    var output = "";
    var chr1, chr2, chr3;
    var enc1, enc2, enc3, enc4;
    var i = 0;
    input = input.replace(/[^A-Za-z0-9\+\/\=]/g, "");
    while (i < input.length) {
        enc1 = this._keyStr.indexOf(input.charAt(i++));
        enc2 = this._keyStr.indexOf(input.charAt(i++));
        enc3 = this._keyStr.indexOf(input.charAt(i++));
        enc4 = this._keyStr.indexOf(input.charAt(i++));
        chr1 = (enc1 << 2) | (enc2 >> 4);
        chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
        chr3 = ((enc3 & 3) << 6) | enc4;
        output = output + String.fromCharCode(chr1);
        if (enc3 != 64) {
            output = output + String.fromCharCode(chr2);
        }
        if (enc4 != 64) {
            output = output + String.fromCharCode(chr3);
        }
    }
    output = Base64._utf8_decode(output);
    return output;
}, _utf8_encode: function (string) {
    string = string.replace(/\r\n/g, "\n");
    var utftext = "";
    for (var n = 0; n < string.length; n++) {
        var c = string.charCodeAt(n);
        if (c < 128) {
            utftext += String.fromCharCode(c);
        }
        else if ((c > 127) && (c < 2048)) {
            utftext += String.fromCharCode((c >> 6) | 192);
            utftext += String.fromCharCode((c & 63) | 128);
        }
        else {
            utftext += String.fromCharCode((c >> 12) | 224);
            utftext += String.fromCharCode(((c >> 6) & 63) | 128);
            utftext += String.fromCharCode((c & 63) | 128);
        }
    }
    return utftext;
}, _utf8_decode: function (utftext) {
    var string = "";
    var i = 0;
    var c = c1 = c2 = 0;
    while (i < utftext.length) {
        c = utftext.charCodeAt(i);
        if (c < 128) {
            string += String.fromCharCode(c);
            i++;
        }
        else if ((c > 191) && (c < 224)) {
            c2 = utftext.charCodeAt(i + 1);
            string += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
            i += 2;
        }
        else {
            c2 = utftext.charCodeAt(i + 1);
            c3 = utftext.charCodeAt(i + 2);
            string += String.fromCharCode(((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
            i += 3;
        }
    }
    return string;
}}
Ext.namespace("Heron.widgets.LayerNodeMenuItem");
Heron.widgets.LayerNodeMenuItem = Ext.extend(Ext.menu.Item, {isApplicable: function (node) {
    return true;
}});
Heron.widgets.LayerNodeMenuItem.Style = Ext.extend(Heron.widgets.LayerNodeMenuItem, {text: __('Edit Layer Style'), iconCls: "icon-palette", disabled: false, listeners: {'activate': function (menuItem, event) {
    var node = menuItem.ownerCt.contextNode;
    if (!node || !node.layer) {
    }
}, scope: this}, initComponent: function () {
    Heron.widgets.LayerNodeMenuItem.Style.superclass.initComponent.call(this);
}, handler: function (menuItem, event) {
    var node = menuItem.ownerCt.contextNode;
    if (!node || !node.layer) {
        return;
    }
    if (node.layer.CLASS_NAME != 'OpenLayers.Layer.Vector') {
        Ext.Msg.alert(__('Warning'), __('Sorry, Layer style editing is only available for Vector Layers'));
        return;
    }
    if (!gxp.VectorStylesDialog) {
        Ext.Msg.alert(__('Warning'), __('Vector Layer style editing requires GXP with VectorStylesDialog'));
        return;
    }
    var layerRecord = Heron.App.getMapPanel().layers.getByLayer(node.layer);
    new Ext.Window({layout: 'auto', resizable: false, autoHeight: true, pageX: 100, pageY: 200, width: 400, closeAction: 'hide', title: __('Style Editor (Vector)'), items: [gxp.VectorStylesDialog.createVectorStylerConfig(layerRecord)]}).show();
}, isApplicable: function (node) {
    return node.layer.CLASS_NAME == 'OpenLayers.Layer.Vector';
}});
Ext.reg('hr_layernodemenustyle', Heron.widgets.LayerNodeMenuItem.Style);
Heron.widgets.LayerNodeMenuItem.ZoomExtent = Ext.extend(Heron.widgets.LayerNodeMenuItem, {text: __('Zoom to Layer Extent'), iconCls: "icon-zoom-visible", initComponent: function () {
    Heron.widgets.LayerNodeMenuItem.ZoomExtent.superclass.initComponent.call(this);
}, handler: function (menuItem, event) {
    // HACK. This handler is hacked to use GC2 extent API
    var node = menuItem.ownerCt.contextNode;
    if (!node || !node.layer) {
        return;
    }
    var layer = node.layer;
    Ext.Ajax.request({
        url: '/api/v1/extent/' + layer.db + '/' + layer.name + "." + layer.geomField + '/900913',
        method: 'get',
        headers: {
            'Content-Type': 'application/json; charset=utf-8'
        },
        success: function (response) {
            var ext = Ext.decode(response.responseText).extent;
            layer.map.zoomToExtent([ext.xmin, ext.ymin, ext.xmax, ext.ymax]);
        },
        failure: function (response) {
            Ext.MessageBox.show({
                title: 'Failure',
                msg: __(Ext.decode(response.responseText).message),
                buttons: Ext.MessageBox.OK,
                width: 400,
                height: 300,
                icon: Ext.MessageBox.ERROR
            });
        }
    });
}, isApplicable: function (node) {
    this.hasMaxExtent = node.layer.maxExtent && !node.layer.maxExtent.equals(node.layer.map.maxExtent);
    return node.layer.getDataExtent() || this.hasMaxExtent || true; // HACK
}});
Ext.reg('hr_layernodemenuzoomextent', Heron.widgets.LayerNodeMenuItem.ZoomExtent);
Heron.widgets.LayerNodeMenuItem.LayerInfo = Ext.extend(Heron.widgets.LayerNodeMenuItem, {text: __('Get Layer information'), iconCls: "icon-information", initComponent: function () {
    Heron.widgets.LayerNodeMenuItem.LayerInfo.superclass.initComponent.call(this);
}, handler: function (menuItem, event) {
    var node = menuItem.ownerCt.contextNode;
    if (!node || !node.layer) {
        return;
    }
    var layer = node.layer;
    var layerType = layer.CLASS_NAME.split(".").pop();
    var isVector = layerType == 'Vector';
    var isWFS = layer.protocol && layer.protocol.CLASS_NAME.indexOf('WFS') > 0;
    layerType = isWFS ? 'Vector (WFS)' : layerType;
    var tiled = layer.singleTile || isVector ? 'No' : 'Yes';
    var hasWFS = layer.metadata.wfs || isWFS ? 'Yes' : 'No';
    var hasFeatureInfo = isVector || layer.featureInfoFormat ? 'Yes' : 'No';
    Ext.MessageBox.show({title: String.format('Info for Layer "{0}"', layer.name), msg: String.format('Placeholder: should become more extensive with infos, metadata, etc.!<br>' + "<br>Name: {0}" + "<br>Type: {1}" + "<br>Tiled: {2}" + "<br>Has feature info: {3}" + "<br>Has WFS: {4}", layer.name, layerType, tiled, hasFeatureInfo, hasWFS), buttons: Ext.Msg.OK, fn: function (btn) {
        if (btn == 'ok') {
        }
    }, icon: Ext.MessageBox.INFO, maxWidth: 300});
}});
Ext.reg('hr_layernodemenulayerinfo', Heron.widgets.LayerNodeMenuItem.LayerInfo);
Heron.widgets.LayerNodeMenuItem.OpacitySlider = Ext.extend(Heron.widgets.LayerNodeMenuItem, {text: __('Change Layer opacity'), iconCls: 'icon-opacity', initComponent: function () {
    Heron.widgets.LayerNodeMenuItem.OpacitySlider.superclass.initComponent.call(this);
}, handler: function (menuItem, event) {
    var node = menuItem.ownerCt.contextNode;
    if (!node || !node.layer) {
        return;
    }
    var layer = node.layer;
    var cmp = Ext.getCmp('WinOpacity-' + layer.id);
    var xy = event.getXY();
    xy[0] = xy[0] + 40;
    xy[1] = xy[1] + 0;
    if (!cmp) {
        cmp = new Ext.Window({title: __('Opacity'), id: 'WinOpacity-' + layer.id, x: xy[0], y: xy[1], width: 200, resizable: false, constrain: true, bodyStyle: 'padding:2px 4px', closeAction: 'hide', listeners: {hide: function () {
            cmp.x = xy[0];
            cmp.y = xy[1];
        }, show: function () {
            cmp.show();
            cmp.focus();
        }}, items: [
            {xtype: 'label', text: layer.name, height: 20},
            {xtype: "gx_opacityslider", showTitle: false, plugins: new GeoExt.LayerOpacitySliderTip(), vertical: false, inverse: false, aggressive: false, layer: layer}
        ]});
        cmp.show();
    } else {
        if (cmp.isVisible()) {
            cmp.hide();
        } else {
            cmp.setPosition(xy[0], xy[1]);
            cmp.show();
            cmp.focus();
        }
    }
}});
Ext.reg('hr_layernodemenuopacityslider', Heron.widgets.LayerNodeMenuItem.OpacitySlider);
Ext.namespace("Heron.widgets");
Heron.widgets.LayerNodeContextMenu = Ext.extend(Ext.menu.Menu, {listeners: {beforeshow: function (cm) {
    var node = cm.contextNode;
    cm.items.each(function (item) {
        item.setDisabled(!item.isApplicable(node));
    })
}, scope: this}, initComponent: function () {
    this.initialConfig = this.items ? this.items : [
        {xtype: 'hr_layernodemenulayerinfo'},
        {xtype: 'hr_layernodemenuzoomextent'},
        {xtype: 'hr_layernodemenuopacityslider'},
        {xtype: 'hr_layernodemenustyle'}
    ];
    this.items = undefined;
    Heron.widgets.LayerNodeContextMenu.superclass.initComponent.call(this);
}});
Ext.reg('hr_layernodecontextmenu', Heron.widgets.LayerNodeContextMenu);
Ext.namespace("Heron.widgets");
Heron.widgets.GridCellRenderer = (function () {
    var instance = {substituteAttrValues: function (template, options, record) {
        if (!options.attrNames) {
            options.attrNames = new Array();
            var inAttrName = false;
            var attrName = '';
            for (var i = 0; i < template.length; i++) {
                var s = template.charAt(i);
                if (s == '{') {
                    inAttrName = true;
                    attrName = '';
                } else if (s == '}') {
                    options.attrNames.push(attrName)
                    inAttrName = false;
                } else if (inAttrName) {
                    attrName += s;
                }
            }
        }
        var result = template;
        for (var j = 0; j < options.attrNames.length; j++) {
            var name = options.attrNames[j];
            var value = record.data[name];
            if (!value) {
                value = '';
            }
            var valueTemplate = '{' + name + '}';
            result = result.replace(valueTemplate, value);
        }
        return result;
    }, directLink: function (value, metaData, record, rowIndex, colIndex, store) {
        if (!this.options) {
            return value;
        }
        var options = this.options;
        var url = options.url;
        if (!url) {
            return value;
        }
        url = Heron.widgets.GridCellRenderer.substituteAttrValues(url, options, record);
        var result = '<a href="' + url + '" target="{target}">' + value + '</a>';
        var target = options.target ? options.target : '_new';
        var targetTemplate = '{target}';
        return result.replace(targetTemplate, target);
    }, browserPopupLink: function (value, metaData, record, rowIndex, colIndex, store) {
        if (!this.options) {
            return value;
        }
        var options = this.options;
        var templateURL = options.url;
        if (!templateURL) {
            return value;
        }
        var BrowserParam = '\'' + (options.winName ? options.winName : 'herongridcellpopup') + '\''
            + ', ' + (options.bReopen ? options.bReopen : false)
            + ', \'' + (Heron.widgets.GridCellRenderer.substituteAttrValues(templateURL, options, record)) + '\''
            + ', ' + (options.hasMenubar ? options.hasMenubar : false)
            + ', ' + (options.hasToolbar ? options.hasToolbar : false)
            + ', ' + (options.hasAddressbar ? options.hasAddressbar : false)
            + ', ' + (options.hasStatusbar ? options.hasStatusbar : false)
            + ', ' + (options.hasScrollbars ? options.hasScrollbars : false)
            + ', ' + (options.isResizable ? options.isResizable : false)
            + ', ' + (options.hasPos ? options.hasPos : false)
            + ', ' + (options.xPos ? options.xPos : 0)
            + ', ' + (options.yPos ? options.yPos : 0)
            + ', ' + (options.hasSize ? options.hasSize : false)
            + ', ' + (options.wSize ? options.wSize : 200)
            + ', ' + (options.hSize ? options.hSize : 100);
        return(options.attrPreTxt ? options.attrPreTxt : "") + '<a href="#" onclick="' + 'Heron.Utils.openBrowserWindow(' + BrowserParam + '); return false">' + value + '</a>';
    }, valueSubstitutor: function (value, metaData, record, rowIndex, colIndex, store) {
        if (!this.options) {
            return value;
        }
        var options = this.options;
        var template = options.template;
        if (!template) {
            return value;
        }
        return Heron.widgets.GridCellRenderer.substituteAttrValues(template, options, record);
    }};
    return(instance);
})();
Ext.namespace("Heron.widgets");
var ActiveLayerNodeUI = Ext.extend(GeoExt.tree.LayerNodeUI, new GeoExt.tree.TreeNodeUIEventMixin());
Heron.widgets.ActiveLayerNode = Ext.extend(GeoExt.tree.LayerNode, {render: function (bulkRender) {
    var layer = this.layer instanceof OpenLayers.Layer && this.layer;
    if (layer && this.attributes && this.attributes.component && this.attributes.component.xtype == "gx_opacityslider") {
        this.attributes.component.layer = layer;
        if (layer.opacity >= 1.0) {
            layer.setOpacity(1.0);
        }
        else if (layer.opacity < 0.0) {
            layer.setOpacity(0.0);
        }
        this.attributes.component.value = parseInt(layer.opacity * 100);
    }
    Heron.widgets.ActiveLayerNode.superclass.renderX.call(this, bulkRender);
    if (layer && this.attributes && this.attributes.component && this.attributes.component.xtype == "gx_opacityslider") {
        if (layer.opacity >= 1.0) {
            layer.setOpacity(0.999);
            layer.setOpacity(1.0);
        }
        else if (layer.opacity >= 0.001) {
            layer.setOpacity(layer.opacity - 0.001);
            layer.setOpacity(layer.opacity + 0.001);
        } else {
            layer.setOpacity(0.001);
            layer.setOpacity(0.0);
        }
        this.attributes.component.value = parseInt(layer.opacity * 100);
    }
}});
Ext.tree.TreePanel.nodeTypes.hr_activelayer = Heron.widgets.ActiveLayerNode;
Heron.widgets.ActiveLayersPanel = Ext.extend(Ext.tree.TreePanel, {title: __('Active Layers'), contextMenu: null, applyStandardNodeOpts: function (opts, layer) {
    if (opts.component) {
        opts.component.layer = layer;
    }
    opts.layerId = layer.id;
}, initComponent: function () {
    var self = this;
    var options = {title: this.title, autoScroll: true, enableDD: true, plugins: [
        {ptype: "gx_treenodecomponent"}
    ], root: {nodeType: "gx_layercontainer", text: __('Layers'), loader: {applyLoader: false, baseAttrs: {uiProvider: ActiveLayerNodeUI, iconCls: 'gx-activelayer-drag-icon'}, createNode: function (attr) {
        return self.createNode(self, {layer: attr.layer});
    }, filter: function (record) {
        var layer = record.getLayer();
        return layer.getVisibility() && layer.displayInLayerSwitcher;
    }}}, rootVisible: false, lines: false, listeners: {contextmenu: function (node, e) {
        node.select();
        var cm = this.contextMenu;
        if (cm) {
            cm.contextNode = node;
            cm.showAt(e.getXY());
        }
    }, scope: this}};
    if (this.contextMenu) {
        var cmArgs = this.contextMenu instanceof Array ? {items: this.contextMenu} : {};
        this.contextMenu = new Heron.widgets.LayerNodeContextMenu(cmArgs);
    }
    Ext.apply(this, options);
    Heron.widgets.ActiveLayersPanel.superclass.initComponent.call(this);
    this.addListener("afterrender", this.onAfterRender);
    this.addListener("beforedblclick", this.onBeforeDblClick);
    this.addListener("beforenodedrop", this.onBeforeNodeDrop);
}, createNode: function (self, attr) {
    if (self.hropts) {
        Ext.apply(attr, self.hropts);
    } else {
        Ext.apply(attr, {});
    }
    self.applyStandardNodeOpts(attr, attr.layer);
    attr.uiProvider = ActiveLayerNodeUI;
    attr.nodeType = "hr_activelayer";
    attr.iconCls = 'gx-activelayer-drag-icon';
    return GeoExt.tree.LayerLoader.prototype.createNode.call(self, attr);
}, onBeforeDblClick: function (node, evt) {
    return false;
}, onBeforeNodeDrop: function (dropEvt) {
    if (dropEvt) {
        switch (dropEvt.point) {
            case"above":
                return true;
                break;
            case"below":
                var layer = dropEvt.target.layer;
                if (!layer.isBaseLayer) {
                    return true;
                }
                break;
        }
    }
    return false;
}, onAfterRender: function () {
    var self = this;
    var map = Heron.App.getMap();
    map.events.register('changelayer', null, function (evt) {
        var layer = evt.layer;
        var rootNode = self.getRootNode();
        var layerNode = rootNode.findChild('layerId', evt.layer.id);
        if (evt.property === "visibility") {
            if (evt.layer.getVisibility() && !layerNode) {
                var newNode = self.createNode(self, {layer: layer});
                var newLayerId = layer.map.getLayerIndex(layer);
                if (layer.isBaseLayer) {
                    var bottomLayer;
                    var bottomLayerId;
                    if (rootNode.lastChild) {
                        bottomLayer = rootNode.lastChild.layer;
                        if (bottomLayer) {
                            bottomLayerId = bottomLayer.map.getLayerIndex(bottomLayer);
                        }
                    }
                    rootNode.appendChild(newNode);
                    if (bottomLayer) {
                        if (newLayerId > bottomLayerId) {
                            layer.map.raiseLayer(layer, bottomLayerId - newLayerId);
                        }
                    }
                } else {
                    var topLayer;
                    var topLayerId;
                    if (rootNode.firstChild) {
                        topLayer = rootNode.firstChild.layer;
                        if (topLayer) {
                            topLayerId = topLayer.map.getLayerIndex(topLayer);
                        }
                    }
                    rootNode.insertBefore(newNode, rootNode.firstChild);
                    if (topLayer) {
                        if (topLayerId > newLayerId) {
                            layer.map.raiseLayer(layer, topLayerId - newLayerId);
                        }
                    }
                }
                rootNode.reload();
            } else if (!evt.layer.getVisibility() && layerNode) {
                layerNode.un("move", self.onChildMove, self);
                layerNode.remove();
            }
        }
    });
}, onListenerDoLayout: function (node) {
    if (node && node.hropts && node.hropts.component && node.hropts.component.xtype == "gx_opacityslider") {
        var rootNode = node.getRootNode();
        rootNode.cascade(function (n) {
            if (n.layer) {
                n.component.setValue(parseInt(n.layer.opacity * 100));
                n.component.syncThumb();
            }
        });
        rootNode.reload();
        node.doLayout();
    }
}, listeners: {activate: function (node) {
    this.onListenerDoLayout(this);
}, expand: function (node) {
    this.onListenerDoLayout(this);
}}});
Ext.reg('hr_activelayerspanel', Heron.widgets.ActiveLayersPanel);
Ext.namespace("Heron.widgets");
var ActiveThemeNodeUI = Ext.extend(GeoExt.tree.LayerNodeUI, new GeoExt.tree.TreeNodeUIEventMixin());
Heron.widgets.ActiveThemeNode = Ext.extend(GeoExt.tree.LayerNode, {render: function (bulkRender) {
    var layer = this.layer instanceof OpenLayers.Layer && this.layer;
    Heron.widgets.ActiveThemeNode.superclass.renderX.call(this, bulkRender);
}});
Ext.tree.TreePanel.nodeTypes.hr_activetheme = Heron.widgets.ActiveThemeNode;
Heron.widgets.ActiveThemesPanel = Ext.extend(Ext.tree.TreePanel, {title: __('Active Themes'), qtip_up: __('Move up'), qtip_down: __('Move down'), qtip_opacity: __('Opacity'), qtip_remove: __('Remove layer from list'), qtip_tools: __('Tools'), contextMenu: null, applyStandardNodeOpts: function (opts, layer) {
    if (opts.component) {
        opts.component.layer = layer;
    }
    opts.layerId = layer.id;
}, initComponent: function () {
    var self = this;
    var options = {title: this.title, autoScroll: true, enableDD: true, plugins: [
        {ptype: "gx_treenodeactions", listeners: {action: this.onAction}}
    ], root: {nodeType: "gx_layercontainer", loader: {applyLoader: false, baseAttrs: {radioGroup: "radiogroup", uiProvider: ActiveThemeNodeUI}, createNode: function (attr) {
        return self.createNode(self, {layer: attr.layer});
    }, filter: function (record) {
        var layer = record.getLayer();
        return layer.getVisibility() && layer.displayInLayerSwitcher;
    }}}, rootVisible: false, lines: false, listeners: {contextmenu: function (node, e) {
        node.select();
        var cm = this.contextMenu;
        if (cm) {
            cm.contextNode = node;
            cm.showAt(e.getXY());
        }
    }, scope: this}};
    if (this.contextMenu) {
        var cmArgs = this.contextMenu instanceof Array ? {items: this.contextMenu} : {};
        this.contextMenu = new Heron.widgets.LayerNodeContextMenu(cmArgs);
    }
    Ext.apply(this, options);
    Heron.widgets.ActiveThemesPanel.superclass.initComponent.call(this);
    this.addListener("afterrender", this.onAfterRender);
    this.addListener("beforedblclick", this.onBeforeDblClick);
    this.addListener("beforenodedrop", this.onBeforeNodeDrop);
}, createNode: function (self, attr) {
    if (self.hropts) {
        Ext.apply(attr, self.hropts);
    } else {
        Ext.apply(attr, {showOpacity: false, showTools: false, showRemove: false});
    }
    self.applyStandardNodeOpts(attr, attr.layer);
    attr.uiProvider = ActiveThemeNodeUI;
    attr.nodeType = "hr_activetheme";
    attr.iconCls = 'gx-activethemes-drag-icon';
    attr.actions = [
        {action: "up", qtip: this.qtip_up, update: function (el) {
            var layer = this.layer, map = layer.map;
            if (map.getLayerIndex(layer) == map.layers.length - 1) {
                el.addClass('disabled');
            } else {
                el.removeClass('disabled');
            }
        }},
        {action: "down", qtip: this.qtip_down, update: function (el) {
            var layer = this.layer, map = layer.map;
            if (map.getLayerIndex(layer) == 1) {
               el.addClass('disabled');
            } else {
                el.removeClass('disabled');
            }
        }},
        {action: "opacity", qtip: this.qtip_opacity, update: function (el) {
            var layer = this.layer, map = layer.map;
        }},
        {action: "tools", qtip: this.qtip_tools, update: function (el) {
            var layer = this.layer, map = layer.map;
        }},
        {action: "remove", qtip: this.qtip_remove}
    ];
    attr.actionsNum = attr.actions.length - 1;
    if (!self.hropts.showRemove) {
        attr.actions.remove(attr.actions[attr.actionsNum]);
    }
    attr.actionsNum = attr.actionsNum - 1;
    if (!self.hropts.showTools) {
        attr.actions.remove(attr.actions[attr.actionsNum]);
    }
    attr.actionsNum = attr.actionsNum - 1;
    if (!self.hropts.showOpacity) {
        attr.actions.remove(attr.actions[attr.actionsNum]);
    }
    attr.actionsNum = attr.actionsNum - 1;
    return GeoExt.tree.LayerLoader.prototype.createNode.call(self, attr);
}, onBeforeDblClick: function (node, evt) {
    return false;
}, onBeforeNodeDrop: function (dropEvt) {
    if (dropEvt) {
        switch (dropEvt.point) {
            case"above":
                return true;
                break;
            case"below":
                var layer = dropEvt.target.layer;
                if (!layer.isBaseLayer) {
                    return true;
                }
                break;
        }
    }
    return false;
}, onAction: function (node, action, evt) {
    var layer = node.layer;
    var actLayerId = layer.map.getLayerIndex(layer);
    switch (action) {
        case"up":
            if (!layer.isBaseLayer) {
                var prevNode = node.previousSibling;
                if (prevNode) {
                    var prevLayer = prevNode.layer;
                    var prevLayerId = prevLayer.map.getLayerIndex(prevLayer);
                    if (prevLayerId > actLayerId) {
                        layer.map.raiseLayer(layer, prevLayerId - actLayerId);
                    }
                }
            }
            break;
        case"down":
            if (!layer.isBaseLayer) {
                var nextNode = node.nextSibling;
                if (nextNode) {
                    var nextLayer = nextNode.layer;
                    var nextLayerId = nextLayer.map.getLayerIndex(nextLayer);
                    if (nextLayerId < actLayerId) {
                        if (!nextLayer.isBaseLayer) {
                            layer.map.raiseLayer(layer, nextLayerId - actLayerId);
                        }
                    }
                }
            }
            break;
        case"remove":
            if (!layer.isBaseLayer) {
                Ext.MessageBox.getDialog().defaultButton = 2;
                Ext.MessageBox.show({title: String.format(__('Removing') + ' "{0}"', layer.name), msg: String.format(__('Are you sure you want to remove the layer from your list of layers?'), '<i><b>' + layer.name + '</b></i>'), buttons: Ext.Msg.YESNO, fn: function (btn) {
                    if (btn == 'yes') {
                        layer.setVisibility(false);
                        layer.destroy();
                    }
                }, scope: this, icon: Ext.MessageBox.QUESTION, maxWidth: 300});
            } else {
                Ext.MessageBox.show({title: String.format(__('Removing') + ' "{0}"', layer.name), msg: String.format(__('You are not allowed to remove the baselayer from your list of layers!'), '<i><b>' + layer.name + '</b></i>'), buttons: Ext.Msg.OK, fn: function (btn) {
                    if (btn == 'ok') {
                    }
                }, icon: Ext.MessageBox.ERROR, maxWidth: 300});
            }
            break;
        case"opacity":
            var cmp = Ext.getCmp('WinOpacity-' + layer.id);
            var xy = evt.getXY();
            xy[0] = xy[0] + 40;
            xy[1] = xy[1] + 0;
            if (!cmp) {
                cmp = new Ext.Window({title: __('Opacity'), id: 'WinOpacity-' + layer.id, x: xy[0], y: xy[1], width: 200, resizable: false, constrain: true, bodyStyle: 'padding:2px 4px', closeAction: 'hide', listeners: {hide: function () {
                    cmp.x = xy[0];
                    cmp.y = xy[1];
                }, show: function () {
                    cmp.show();
                    cmp.focus();
                }}, items: [
                    {xtype: 'label', text: layer.name, height: 20},
                    {xtype: "gx_opacityslider", showTitle: false, plugins: new GeoExt.LayerOpacitySliderTip(), vertical: false, inverse: false, aggressive: false, layer: layer}
                ]});
                cmp.show();
            } else {
                if (cmp.isVisible()) {
                    cmp.hide();
                } else {
                    cmp.setPosition(xy[0], xy[1]);
                    cmp.show();
                    cmp.focus();
                }
            }
            break;
        case"tools":
            var id = layer.map.getLayerIndex(layer);
            var num_id = layer.map.getNumLayers();
            Ext.MessageBox.show({title: String.format('Tools "{0}"', layer.name), msg: String.format('Here should be a form for "{0}" containing' + ' infos, etc.!<br>' + "<br>Layer: " + node + "<br>" + layer.name + "<br>" + layer.id + "<br>OL-LayerId: " + id + " (" + num_id + ")", '<i><b>' + layer.name + '</b></i>'), buttons: Ext.Msg.OK, fn: function (btn) {
                if (btn == 'ok') {
                }
            }, icon: Ext.MessageBox.INFO, maxWidth: 300});
            break;
    }
}, onAfterRender: function () {
    var self = this;
    var map = Heron.App.getMap();
    map.events.register('changelayer', null, function (evt) {
        var layer = evt.layer;
        var rootNode = self.getRootNode();
        var layerNode = rootNode.findChild('layerId', evt.layer.id);
        if (evt.property === "visibility") {
            if (evt.layer.getVisibility() && !layerNode) {
                var newNode = self.createNode(self, {layer: layer});
                var newLayerId = layer.map.getLayerIndex(layer);
                if (layer.isBaseLayer) {
                    var bottomLayer;
                    var bottomLayerId;
                    if (rootNode.lastChild) {
                        bottomLayer = rootNode.lastChild.layer;
                        if (bottomLayer) {
                            bottomLayerId = bottomLayer.map.getLayerIndex(bottomLayer);
                        }
                    }
                    rootNode.appendChild(newNode);
                    if (bottomLayer) {
                        if (newLayerId > bottomLayerId) {
                            layer.map.raiseLayer(layer, bottomLayerId - newLayerId);
                        }
                    }
                } else {
                    var topLayer;
                    var topLayerId;
                    if (rootNode.firstChild) {
                        topLayer = rootNode.firstChild.layer;
                        if (topLayer) {
                            topLayerId = topLayer.map.getLayerIndex(topLayer);
                        }
                    }
                    rootNode.insertBefore(newNode, rootNode.firstChild);
                    if (topLayer) {
                        if (topLayerId > newLayerId) {
                            layer.map.raiseLayer(layer, topLayerId - newLayerId);
                        }
                    }
                }
                rootNode.reload();
            } else if (!evt.layer.getVisibility() && layerNode) {
                var opacityWin = Ext.getCmp('WinOpacity-' + layer.id);
                if (opacityWin) {
                    opacityWin.hide();
                }
                layerNode.un("move", self.onChildMove, self);
                layerNode.remove();
            }
        }
    });
}});
Ext.reg('hr_activethemespanel', Heron.widgets.ActiveThemesPanel);
Ext.namespace("Heron.widgets");
Heron.widgets.CapabilitiesTreePanel = Ext.extend(Ext.tree.TreePanel, {initComponent: function () {
    var layerOptions = Ext.apply({buffer: 0, singleTile: true, ratio: 1}, this.hropts.layerOptions);
    var layerParams = Ext.apply({'TRANSPARENT': 'TRUE'}, this.hropts.layerParams);
    var root = new Ext.tree.AsyncTreeNode({text: this.hropts.text, expanded: this.hropts.preload, loader: new GeoExt.tree.WMSCapabilitiesLoader({url: this.hropts.url, layerOptions: layerOptions, layerParams: layerParams, createNode: function (attr) {
        attr.checked = attr.leaf ? false : undefined;
        return GeoExt.tree.WMSCapabilitiesLoader.prototype.createNode.apply(this, [attr]);
    }})});
    this.options = {root: root, listeners: {'checkchange': function (node, checked) {
        var map = Heron.App.getMap();
        if (!map) {
            return;
        }
        var layer = node.attributes.layer;
        if (checked === true) {
            map.addLayer(layer);
        } else {
            map.removeLayer(layer);
        }
    }}};
    Ext.apply(this, this.options);
    Heron.widgets.CapabilitiesTreePanel.superclass.initComponent.call(this);
}});
Ext.reg('hr_capabilitiestreepanel', Heron.widgets.CapabilitiesTreePanel);
Ext.namespace("Heron.widgets");
Heron.widgets.CascadingTreeNode = Ext.extend(Ext.tree.AsyncTreeNode, {checked: false, constructor: function () {
    if (arguments[0].checked === undefined) {
        arguments[0].checked = this.checked;
    }
    Heron.widgets.CascadingTreeNode.superclass.constructor.apply(this, arguments);
    this.on({"append": this.onAppend, "remove": this.onRemove, "checkchange": this.onCheckChange, scope: this});
}, onAppend: function (panel, self, child) {
    child.on({"checkchange": this.onChildCheckChange, scope: this});
}, onRemove: function (panel, self, child) {
    child.un({"checkchange": this.onChildCheckChange, scope: this});
}, onChildCheckChange: function (child, checked) {
    if (this.childCheckChange) {
        return;
    }
    var childrenTotal = 0, childrenChecked = 0;
    var self = this;
    this.cascade(function (child) {
        if (child == self || (child.hasChildNodes() && !child.attributes.xtype == 'hr_cascader')) {
            return;
        }
        childrenTotal++;
        if (child.ui.isChecked()) {
            childrenChecked++;
        }
    }, null, null);
    this.childCheckChange = true;
    this.ui.toggleCheck(childrenTotal == childrenChecked);
    this.childCheckChange = false;
}, onCheckChange: function (node, checked) {
    node.cascade(function (child) {
        if (child == node || node.childCheckChange) {
            return;
        }
        if (child.ui.rendered) {
            child.ui.toggleCheck(checked);
        } else {
            child.attributes.checked = checked;
        }
    }, null, null);
}});
Ext.tree.TreePanel.nodeTypes.hr_cascader = Heron.widgets.CascadingTreeNode;
Ext.namespace("Heron.widgets.search");
Heron.widgets.search.CoordSearchPanel = Ext.extend(Ext.form.FormPanel, {title: __('Go to coordinates'), titleDescription: null, titleDescriptionStyle: null, bodyBaseCls: 'x-panel', bodyItemCls: null, bodyCls: null, fieldMaxWidth: 150, fieldLabelWidth: '', fieldStyle: null, fieldLabelStyle: null, layerName: __('Location'), onProjectionIndex: 0, onZoomLevel: -1, showProjection: false, showZoom: false, showAddMarkers: false, checkAddMarkers: false, showHideMarkers: false, checkHideMarkers: false, showResultMarker: false, fieldResultMarkerStyle: null, fieldResultMarkerText: __('Marker position: '), fieldResultMarkerSeparator: ' , ', fieldResultMarkerPrecision: 2, removeMarkersOnClose: false, showRemoveMarkersBtn: false, buttonAlign: 'left', hropts: null, initComponent: function () {
    var self = this;
    var map = Heron.App.getMap();
    this.arrProj = new Ext.data.ArrayStore({fields: [
        {name: 'id'},
        {name: 'idLast'},
        {name: 'idX'},
        {name: 'idY'},
        {name: 'idB'},
        {name: 'projEpsg'},
        {name: 'projDesc'},
        {name: 'fieldLabelX'},
        {name: 'fieldLabelY'},
        {name: 'fieldEmptyTextX'},
        {name: 'fieldEmptyTextY'},
        {name: 'fieldMinX'},
        {name: 'fieldMinY'},
        {name: 'fieldMaxX'},
        {name: 'fieldMaxY'},
        {name: 'fieldDecPrecision'},
        {name: 'iconWidth'},
        {name: 'iconHeight'},
        {name: 'localIconFile'},
        {name: 'iconUrl'},
        {name: 'iconOL'}
    ]});
    var idX = Ext.id();
    var idY = Ext.id();
    var idB = Ext.id();
    var contexts = this.hropts;
    if (contexts && typeof(contexts) !== "undefined") {
        for (var i = 0; i < contexts.length; i++) {
            var recArrPrj = this.arrProj.recordType;
            var recSrc = new recArrPrj({id: i, idLast: this.onProjectionIndex, idX: idX, idY: idY, idB: idB, projEpsg: contexts[i].projEpsg, projDesc: contexts[i].projDesc ? contexts[i].projDesc : (contexts[i].projEpsg ? contexts[i].projEpsg : __('Map system')), fieldLabelX: contexts[i].fieldLabelX ? contexts[i].fieldLabelX : __('X'), fieldLabelY: contexts[i].fieldLabelY ? contexts[i].fieldLabelY : __('Y'), fieldEmptyTextX: contexts[i].fieldEmptyTextX ? contexts[i].fieldEmptyTextX : __('Enter X-coordinate...'), fieldEmptyTextY: contexts[i].fieldEmptyTextY ? contexts[i].fieldEmptyTextY : __('Enter Y-coordinate...'), fieldMinX: contexts[i].fieldMinX ? contexts[i].fieldMinX : null, fieldMinY: contexts[i].fieldMinY ? contexts[i].fieldMinY : null, fieldMaxX: contexts[i].fieldMaxX ? contexts[i].fieldMaxX : null, fieldMaxY: contexts[i].fieldMaxY ? contexts[i].fieldMaxY : null, fieldDecPrecision: contexts[i].fieldDecPrecision ? contexts[i].fieldDecPrecision : 2, iconWidth: contexts[i].iconWidth ? contexts[i].iconWidth : 32, iconHeight: contexts[i].iconHeight ? contexts[i].iconHeight : 32, localIconFile: contexts[i].localIconFile ? contexts[i].localIconFile : 'redpin.png', iconUrl: contexts[i].iconUrl ? contexts[i].iconUrl : null, iconOL: null});
            this.arrProj.add(recSrc);
        }
    } else {
        var recArrPrj = this.arrProj.recordType;
        var recSrc = new recArrPrj({id: 0, idLast: 0, idX: idX, idY: idY, idB: idB, projEpsg: null, projDesc: __('Map system'), fieldLabelX: __('X'), fieldLabelY: __('Y'), fieldEmptyTextX: __('Enter X-coordinate...'), fieldEmptyTextY: __('Enter Y-coordinate...'), fieldMinX: null, fieldMinY: null, fieldMaxX: null, fieldMaxY: null, fieldDecPrecision: 2, iconWidth: 32, iconHeight: 32, localIconFile: 'redpin.png', iconUrl: null, iconOL: null});
        this.arrProj.add(recSrc);
    }
    this.pCombo = new Ext.form.ComboBox({fieldLabel: __('Input system'), emptyText: __('Choose input system...'), tooltip: __('Input system'), anchor: '100%', boxMaxWidth: this.fieldMaxWidth, itemCls: this.bodyItemCls, cls: this.bodyCls, style: this.fieldStyle, labelStyle: this.fieldLabelStyle, editable: false, triggerAction: 'all', mode: 'local', store: this.arrProj, displayField: 'projDesc', valueField: 'id', value: this.onProjectionIndex, hidden: ((!this.showProjection) || ((this.arrProj.data.length <= 1) && (!this.arrProj.getAt(0).data.projEpsg))) ? true : false, listeners: {render: function (c) {
        c.el.set({qtip: this.tooltip});
        c.trigger.set({qtip: this.tooltip});
    }, select: function (combo, record, index) {
        var idLast = combo.store.data.items[index].data.idLast;
        if (idLast != index) {
            var p = combo.store.data.items[index].data;
            var pX = Ext.getCmp(p.idX);
            var pY = Ext.getCmp(p.idY);
            var pB = Ext.getCmp(p.idB);
            if (record.data.fieldLabelX) {
                pX.label.update(record.data.fieldLabelX);
            }
            if (record.data.fieldEmptyTextX) {
                Ext.getCmp(idX).emptyText = record.data.fieldEmptyTextX;
            }
            pX.decimalPrecision = record.data.fieldDecPrecision;
            pX.setValue('');
            pX.show();
            if (record.data.fieldLabelY) {
                pY.label.update(record.data.fieldLabelY);
            }
            if (record.data.fieldEmptyTextY) {
                Ext.getCmp(idY).emptyText = record.data.fieldEmptyTextY;
            }
            pY.decimalPrecision = record.data.fieldDecPrecision;
            pY.setValue('');
            pY.show();
            pB.disable();
            pB.show();
            this.rLabel.setText(this.fieldResultMarkerText);
            for (var i = 0; i < combo.store.data.length; i++) {
                combo.store.data.items[i].data.idLast = index;
            }
        }
    }, scope: this}});
    this.tLabel = new Ext.form.Label({html: this.titleDescription, style: this.titleDescriptionStyle});
    this.xField = new Ext.form.NumberField({id: idX, fieldLabel: this.arrProj.getAt(this.onProjectionIndex).data.fieldLabelX, emptyText: this.arrProj.getAt(this.onProjectionIndex).data.fieldEmptyTextX, anchor: '100%', boxMaxWidth: this.fieldMaxWidth, itemCls: this.bodyItemCls, cls: this.bodyCls, style: this.fieldStyle, labelStyle: this.fieldLabelStyle, decimalPrecision: this.arrProj.getAt(this.onProjectionIndex).data.fieldDecPrecision, enableKeyEvents: true, listeners: {keyup: function (numberfield, ev) {
        this.onNumberKeyUp(numberfield, ev);
    }, keydown: function (numberfield, ev) {
        this.rLabel.setText(this.fieldResultMarkerText);
    }, scope: this}});
    this.yField = new Ext.form.NumberField({id: idY, fieldLabel: this.arrProj.getAt(this.onProjectionIndex).data.fieldLabelY, emptyText: this.arrProj.getAt(this.onProjectionIndex).data.fieldEmptyTextY, anchor: '100%', boxMaxWidth: this.fieldMaxWidth, itemCls: this.bodyItemCls, cls: this.bodyCls, style: this.fieldStyle, labelStyle: this.fieldLabelStyle, decimalPrecision: this.arrProj.getAt(this.onProjectionIndex).data.fieldDecPrecision, enableKeyEvents: true, listeners: {keyup: function (numberfield, ev) {
        this.onNumberKeyUp(numberfield, ev);
    }, keydown: function (numberfield, ev) {
        this.rLabel.setText(this.fieldResultMarkerText);
    }, scope: this}});
    this.storeZoom = new GeoExt.data.ScaleStore({map: map});
    this.arrZoom = new Ext.data.ArrayStore({fields: [
        {name: 'zoom', type: 'string'},
        {name: 'scale', type: 'string'}
    ], data: [
        ['-1', __('no zoom')]
    ]});
    for (var i = 0; i < this.storeZoom.getCount(); i++) {
        var recArrZoom = this.arrZoom.recordType;
        var rec = new recArrZoom({zoom: this.storeZoom.getAt(i).data.level, scale: '1 : ' + parseInt(this.storeZoom.getAt(i).data.scale + 0.5)});
        this.arrZoom.add(rec);
    }
    this.sCombo = new Ext.form.ComboBox({fieldLabel: __('Zoom'), emptyText: __('Choose scale...'), tooltip: __('Scale'), anchor: '100%', boxMaxWidth: this.fieldMaxWidth, itemCls: this.bodyItemCls, cls: this.bodyCls, style: this.fieldStyle, labelStyle: this.fieldLabelStyle, editable: false, hidden: this.showZoom ? false : true, triggerAction: 'all', mode: 'local', store: this.arrZoom, displayField: 'scale', valueField: 'zoom', value: (this.onZoomLevel < 0) ? -1 : this.onZoomLevel, listeners: {render: function (c) {
        c.el.set({qtip: this.tooltip});
        c.trigger.set({qtip: this.tooltip});
    }}});
    this.mCheckbox = new Ext.form.Checkbox({fieldLabel: __('Mode'), boxLabel: __('Remember locations'), anchor: '100%', boxMaxWidth: this.arrProj.getAt(0).data.fieldMaxWidth, itemCls: this.bodyItemCls, cls: this.bodyCls, labelStyle: this.fieldLabelStyle, checked: this.checkAddMarkers ? true : false, hidden: this.showAddMarkers ? false : true});
    this.cCheckbox = new Ext.form.Checkbox({fieldLabel: this.mCheckbox.hidden ? __('Mode') : '', boxLabel: this.removeMarkersOnClose ? __('Remove markers on close') : __('Hide markers on close'), anchor: '100%', boxMaxWidth: this.arrProj.getAt(0).data.fieldMaxWidth, itemCls: this.bodyItemCls, cls: this.bodyCls, labelStyle: this.fieldLabelStyle, checked: this.checkHideMarkers ? true : false, hidden: this.showHideMarkers ? false : true});
    this.rLabel = new Ext.form.Label({anchor: '100%', html: this.fieldResultMarkerText, itemCls: this.bodyItemCls, cls: this.bodyCls, style: this.fieldResultMarkerStyle, hidden: this.showResultMarker ? false : true});
    this.rButton = new Ext.Button({text: __('Remove markers'), minWidth: 90, autoHeight: true, flex: 1, hidden: this.showRemoveMarkersBtn ? false : true, handler: function () {
        self.removeMarkers(self);
        self.rLabel.setText(self.fieldResultMarkerText);
    }});
    this.gButton = new Ext.Button({id: idB, text: __('Go!'), align: 'right', tooltip: __('Pan and zoom to location'), minWidth: 90, autoHeight: true, disabled: true, flex: 1, handler: function () {
        self.panAndZoom(self);
    }});
    this.items = [
        {layout: 'form', border: false, baseCls: this.bodyBaseCls, labelWidth: this.fieldLabelWidth, padding: 5, items: [self.tLabel, self.pCombo, self.xField, self.yField, self.sCombo, self.mCheckbox, self.cCheckbox, self.rLabel], buttonAlign: this.buttonAlign, buttons: [this.rButton, this.gButton]}
    ];
    this.keys = [
        {key: [Ext.EventObject.ENTER], handler: function () {
            if (!self.gButton.disabled) {
                self.panAndZoom(self);
            }
        }}
    ];
    Heron.widgets.search.CoordSearchPanel.superclass.initComponent.call(this);
    this.addListener("afterrender", this.onPanelRendered, this);
}, onPanelRendered: function () {
    if (this.ownerCt) {
        this.ownerCt.addListener("hide", this.onParentHide, this);
        this.ownerCt.addListener("show", this.onParentShow, this);
    }
}, onParentShow: function () {
    var map = Heron.App.getMap();
    var markerLayer = map.getLayersByName(this.layerName);
    if (markerLayer[0]) {
        markerLayer[0].setVisibility(true);
    }
}, onParentHide: function () {
    if (this.cCheckbox.checked) {
        if (this.removeMarkersOnClose) {
            this.removeMarkers(this);
        }
        var map = Heron.App.getMap();
        var markerLayer = map.getLayersByName(this.layerName);
        if (markerLayer[0]) {
            markerLayer[0].setVisibility(false);
        }
    }
}, onNumberKeyUp: function (numberfield, ev) {
    var valueX = parseFloat(this.xField.getValue());
    var valueY = parseFloat(this.yField.getValue());
    var fieldMinX = this.arrProj.getAt(this.pCombo.getValue()).data.fieldMinX;
    var fieldMinY = this.arrProj.getAt(this.pCombo.getValue()).data.fieldMinY;
    var fieldMaxX = this.arrProj.getAt(this.pCombo.getValue()).data.fieldMaxX;
    var fieldMaxY = this.arrProj.getAt(this.pCombo.getValue()).data.fieldMaxY;
    if (valueX && valueY) {
        if (fieldMinX && fieldMinY && fieldMaxX && fieldMaxY) {
            if (((valueX >= parseFloat(fieldMinX)) && (valueX <= parseFloat(fieldMaxX))) && ((valueY >= parseFloat(fieldMinY)) && (valueY <= parseFloat(fieldMaxY)))) {
                this.gButton.enable();
            } else {
                this.gButton.disable();
            }
        } else {
            this.gButton.enable();
        }
    }
    else {
        this.gButton.disable();
    }
}, removeMarkers: function (self) {
    var map = Heron.App.getMap();
    var markerLayer = map.getLayersByName(this.layerName);
    if (markerLayer[0]) {
        markerLayer[0].clearMarkers();
        map.removeLayer(markerLayer[0]);
    }
}, panAndZoom: function (self) {
    var map = Heron.App.getMap();
    var markerLayer = map.getLayersByName(this.layerName);
    if (markerLayer[0]) {
        if (!self.mCheckbox.checked) {
            markerLayer[0].clearMarkers();
        }
    }
    var x = self.xField.getValue();
    var y = self.yField.getValue();
    var zoom = (self.sCombo.value >= 0) ? self.sCombo.value : map.getZoom();
    var position = new OpenLayers.LonLat(x, y);
    var selectedEpsg = this.arrProj.getAt(self.pCombo.value).data.projEpsg;
    if (selectedEpsg && (selectedEpsg != map.getProjection())) {
        this.olProjection = null;
        this.olProjection = new OpenLayers.Projection(selectedEpsg);
        if (this.olProjection) {
            position.transform(this.olProjection, map.getProjectionObject());
        }
    }
    map.setCenter(position, zoom);
    this.rLabel.setText(this.fieldResultMarkerText + position.lon.toFixed(this.fieldResultMarkerPrecision) + this.fieldResultMarkerSeparator + position.lat.toFixed(this.fieldResultMarkerPrecision));
    if (!markerLayer[0]) {
        this.layer = new OpenLayers.Layer.Markers(this.layerName);
        map.addLayer(this.layer);
        markerLayer = map.getLayersByName(this.layerName);
    }
    if (!this.arrProj.getAt(self.pCombo.value).data.iconOL) {
        var iconUrl = Heron.Utils.getImageLocation(this.arrProj.getAt(self.pCombo.value).data.localIconFile);
        var iconWidth = this.arrProj.getAt(self.pCombo.value).data.iconWidth;
        var iconHeight = this.arrProj.getAt(self.pCombo.value).data.iconHeight;
        var size = new OpenLayers.Size(iconWidth, iconHeight);
        var offset = new OpenLayers.Pixel(-(size.w / 2), -size.h);
        this.arrProj.getAt(self.pCombo.value).data.iconOL = new OpenLayers.Icon(iconUrl, size, offset);
    }
    var marker = new OpenLayers.Marker(position, this.arrProj.getAt(self.pCombo.value).data.iconOL.clone());
    markerLayer[0].addMarker(marker);
    markerLayer[0].setVisibility(true);
}});
Ext.reg("hr_coordsearchpanel", Heron.widgets.search.CoordSearchPanel);
Ext.namespace("Heron.widgets.search");
Ext.namespace("Heron.utils");
Heron.widgets.search.FeatureInfoPanel = Ext.extend(Ext.Panel, {title: __('Feature Info'), maxFeatures: 5, displayPanels: ['Table'], exportFormats: ['CSV', 'XLS', 'GMLv2', 'GeoJSON', 'WellKnownText'], infoFormat: 'application/vnd.ogc.gml', hover: false, drillDown: true, layer: "", discardStylesForDups: false, showTopToolbar: true, showGeometries: true, featureSelection: true, columnCapitalize: true, gridCellRenderers: null, gridColumns: null, autoConfigMaxSniff: 40, hideColumns: [], columnFixedWidth: 100, autoMaxWidth: 300, autoMinWidth: 45, pop: null, map: null, displayPanel: null, lastEvt: null, olControl: null, tb: null, initComponent: function () {
    var self = this;
    Ext.apply(this, {layout: "fit"});
    this.display = this.displayFeatureInfo;
    Heron.widgets.search.FeatureInfoPanel.superclass.initComponent.call(this);
    this.map = Heron.App.getMap();
    if (!this.olControl) {
        var controls = this.map.getControlsByClass("OpenLayers.Control.WMSGetFeatureInfo");
        if (controls && controls.length > 0) {
            for (var index = 0; index < controls.length; index++) {
                if (controls[index].id !== "hr-feature-info-hover") {
                    this.olControl = controls[index];
                    this.olControl.infoFormat = this.infoFormat;
                    this.olControl.maxFeatures = this.maxFeatures;
                    this.olControl.hover = this.hover;
                    this.olControl.drillDown = this.drillDown;
                    break;
                }
            }
        }
        if (!this.olControl) {
            this.olControl = new OpenLayers.Control.WMSGetFeatureInfo({maxFeatures: this.maxFeatures, queryVisible: true, infoFormat: this.infoFormat, hover: this.hover, drillDown: this.drillDown});
            this.map.addControl(this.olControl);
        }
    }
    this.olControl.events.register("getfeatureinfo", this, this.handleGetFeatureInfo);
    this.olControl.events.register("beforegetfeatureinfo", this, this.handleBeforeGetFeatureInfo);
    this.olControl.events.register("nogetfeatureinfo", this, this.handleNoGetFeatureInfo);
    this.addListener("afterrender", this.onPanelRendered, this);
    this.addListener("render", this.onPanelRender, this);
    this.addListener("show", this.onPanelShow, this);
    this.addListener("hide", this.onPanelHide, this);
}, onPanelRender: function () {
    this.mask = new Ext.LoadMask(this.body, {msg: __('Loading...')});
}, onPanelRendered: function () {
    if (this.ownerCt) {
        this.ownerCt.addListener("hide", this.onPanelHide, this);
        this.ownerCt.addListener("show", this.onPanelShow, this);
    }
}, onPanelShow: function () {
    if (this.tabPanel) {
        this.tabPanel.items.each(function (item) {
            return item.showLayer ? item.showLayer() : true;
        }, this);
    }
}, onPanelHide: function () {
    if (this.tabPanel) {
        this.tabPanel.items.each(function (item) {
            return item.hideLayer ? item.hideLayer() : true;
        }, this);
    }
}, initPanel: function () {
    this.lastEvt = null;
    this.expand();
    if (this.tabPanel) {
        this.tabPanel.items.each(function (item) {
            this.tabPanel.remove(item);
            return item.cleanup ? item.cleanup() : true;
        }, this);
    }
    if (this.displayPanel) {
        this.remove(this.displayPanel);
        this.displayPanel = null;
    }
    this.displayOn = false;
}, handleBeforeGetFeatureInfo: function (evt) {
    if (evt.object !== this.olControl) {
        return;
    }
    this.olControl.layers = [];
    this.olControl.url = null;
    this.olControl.drillDown = this.drillDown;
    var layer;
    if (this.layer) {
        var layers = this.map.getLayersByName(this.layer);
        if (layers) {
            layer = layers[0];
            this.olControl.layers.push(layer);
        }
    }
    if (this.olControl.layers.length == 0) {
        this.layerDups = {};
        for (var index = 0; index < this.map.layers.length; index++) {
            layer = this.map.layers[index];
            if (!layer instanceof OpenLayers.Layer.WMS || !layer.params) {
                continue;
            }
            if (layer.visibility && (layer.featureInfoFormat || layer.params.INFO_FORMAT)) {
                if (!layer.params.INFO_FORMAT && layer.featureInfoFormat) {
                    layer.params.INFO_FORMAT = layer.featureInfoFormat;
                }
                if (layer.params.CQL_FILTER) {
                    this.olControl.requestPerLayer = true;
                    layer.params.vendorParams = {CQL_FILTER: layer.params.CQL_FILTER};
                }
                if (this.layerDups[layer.params.LAYERS] && !this.olControl.requestPerLayer) {
                    if (this.discardStylesForDups) {
                        var dupLayer = this.layerDups[layer.params.LAYERS];
                        dupLayer.savedStyles = dupLayer.params.STYLES;
                        dupLayer.params.STYLES = "";
                    }
                    continue;
                }
                this.olControl.layers.push(layer);
                this.layerDups[layer.params.LAYERS] = layer;
            }
        }
    }
    this.initPanel();
    if (this.mask) {
        this.mask.show();
    }
    this.fireEvent('beforefeatureinfo', evt);
    this.handleVectorFeatureInfo(evt.object.handler.evt);
    if (this.olControl.layers.length == 0 && this.features == null) {
        this.handleNoGetFeatureInfo();
    }
}, handleGetFeatureInfo: function (evt) {
    var layers = this.olControl.layers;
    if (layers) {
        for (var i = 0, len = layers.length; i < len; i++) {
            layers[i].params.vendorParams = null;
        }
    }
    if (this.discardStylesForDups) {
        for (var layerName in this.layerDups) {
            var layerDup = this.layerDups[layerName];
            if (layerDup.savedStyles) {
                layerDup.params.STYLES = layerDup.savedStyles;
                layerDup.savedStyles = null;
            }
        }
    }
    if (evt && evt.object !== this.olControl) {
        return;
    }
    if (this.mask) {
        this.mask.hide();
    }
    if (evt) {
        this.lastEvt = evt;
    }
    if (!this.lastEvt) {
        return;
    }
    this.displayFeatures(this.lastEvt);
}, handleVectorFeatureInfo: function (evt) {
    this.vectorFeaturesFound = false;
    var screenX = Ext.isIE ? Ext.EventObject.xy[0] : evt.clientX;
    var screenY = Ext.isIE ? Ext.EventObject.xy[1] : evt.clientY;
    this.features = this.getFeaturesByXY(screenX, screenY);
    if (this.mask) {
        this.mask.hide();
    }
    evt.features = this.features;
    if (evt.features && evt.features.length > 0) {
        this.vectorFeaturesFound = true;
        this.displayFeatures(evt);
    }
}, handleNoGetFeatureInfo: function () {
    if (!this.visibleVectorLayers) {
        Ext.Msg.alert(__('Warning'), __('Feature Info unavailable (you may need to make some layers visible)'));
    }
}, getFeaturesByXY: function (x, y) {
    this.visibleVectorLayers = false;
    var features = [], targets = [], layers = [];
    var layer, target, feature, i, len;
    for (i = this.map.layers.length - 1; i >= 0; --i) {
        layer = this.map.layers[i];
        if (layer.div.style.display !== "none") {
            if (layer instanceof OpenLayers.Layer.Vector) {
                target = document.elementFromPoint(x, y);
                while (target && target._featureId) {
                    feature = layer.getFeatureById(target._featureId);
                    if (feature) {
                        var featureClone = feature.clone();
                        featureClone.type = layer.name;
                        featureClone.layer = layer;
                        features.push(featureClone);
                        target.style.display = "none";
                        targets.push(target);
                        target = document.elementFromPoint(x, y);
                        this.visibleVectorLayers = true;
                    } else {
                        target = false;
                    }
                }
            }
            layers.push(layer);
            layer.div.style.display = "none";
        }
    }
    for (i = 0, len = targets.length; i < len; ++i) {
        targets[i].style.display = "";
    }
    for (i = layers.length - 1; i >= 0; --i) {
        layers[i].div.style.display = "block";
    }
    return features;
}, getFeatureType: function (feature) {
    if (feature.gml && feature.gml.featureType) {
        return feature.gml.featureType;
    }
    if (feature.fid && feature.fid.indexOf('undefined') < 0) {
        var featureType = /[^\.]*/.exec(feature.fid);
        return(featureType[0] != "null") ? featureType[0] : null;
    }
    if (feature.type) {
        return feature.type;
    }
    if (feature.attributes['_LAYERID_']) {
        return feature.attributes['_LAYERID_'];
    }
    if (feature.attributes['DINO_DBA.MAP_SDE_GWS_WELL_W_HEADS_VW.DINO_NR']) {
        return'TNO_DINO_WELLS';
    }
    if (feature.attributes['DINO_DBA.MAP_SDE_BRH_BOREHOLE_RD_VW.DINO_NR']) {
        return'TNO_DINO_BOREHOLES';
    }
    return __('Unknown');
}, getFeatureTitle: function (feature, featureType) {
    if (feature.layer) {
        return feature.layer.name;
    }
    var featureTitle = feature.layer ? feature.layer.name : featureType;
    var layers = this.map.layers;
    for (var l = 0; l < layers.length; l++) {
        var nextLayer = layers[l];
        if (!nextLayer.params || !nextLayer.visibility) {
            continue;
        }
        if (featureType.toLowerCase() == /([^:]*$)/.exec(nextLayer.params.LAYERS)[0].toLowerCase()) {
            featureTitle = nextLayer.name;
            break;
        }
    }
    return featureTitle;
}, displayFeatures: function (evt) {
    if (this.olControl.requestPerLayer) {
    }
    if (evt.features && evt.features.length > 0) {
        if (!this.vectorFeaturesFound && this.displayPanel) {
            this.remove(this.displayPanel);
            this.displayPanel = null;
            this.displayOn = false;
        }
        this.displayPanel = this.display(evt);
    } else if (!this.vectorFeaturesFound) {
        this.displayPanel = this.displayInfo(__('No features found'));
    }
    if (this.displayPanel && !this.displayOn) {
        this.add(this.displayPanel);
        this.displayPanel.doLayout();
    }
    if (this.getLayout()instanceof Object && !this.displayOn) {
        this.getLayout().runLayout();
    }
    this.displayOn = true;
    this.fireEvent('featureinfo', evt);
}, displayFeatureInfo: function (evt) {
    var featureSets = {}, featureSet, featureType, featureTitle, featureSetKey;
    for (var index = 0; index < evt.features.length; index++) {
        var feature = evt.features[index];
        featureType = this.getFeatureType(feature);
        featureTitle = this.getFeatureTitle(feature, featureType);
        featureSetKey = featureType + featureTitle;
        if (!featureSets[featureSetKey]) {
            featureSet = {featureType: featureType, title: featureTitle, features: []};
            featureSets[featureSetKey] = featureSet;
        }
        for (var attrName in feature.attributes) {
            var attrValue = feature.attributes[attrName];
            if (attrValue && typeof attrValue == 'string' && attrValue.indexOf("http://") >= 0) {
                feature.attributes[attrName] = '<a href="' + attrValue + '" target="_new">' + attrValue + '</a>';
            }
            if (attrName.indexOf(".") >= 0) {
                var newAttrName = attrName.replace(/\./g, "_");
                feature.attributes[newAttrName] = feature.attributes[attrName];
                if (attrName != newAttrName) {
                    delete feature.attributes[attrName];
                }
            }
        }
        featureSet.features.push(feature);
    }
    if (this.tabPanel != null && !this.displayOn) {
        this.remove(this.tabPanel);
        this.tabPanel = null;
    }
    for (featureSetKey in featureSets) {
        featureSet = featureSets[featureSetKey];
        if (featureSet.features.length == 0) {
            continue;
        }
        var autoConfig = true;
        var columns = null;
        if (this.gridColumns) {
            for (var c = 0; c < this.gridColumns.length; c++) {
                if (this.gridColumns[c].featureType == featureSet.featureType) {
                    autoConfig = false;
                    columns = this.gridColumns[c].columns;
                    break;
                }
            }
        }
        var panel = new Heron.widgets.search.FeaturePanel({title: featureSet.title, featureType: featureSet.featureType, featureSetKey: featureSetKey, header: false, features: featureSet.features, autoConfig: autoConfig, autoConfigMaxSniff: this.autoConfigMaxSniff, hideColumns: this.hideColumns, columnFixedWidth: this.columnFixedWidth, autoMaxWidth: this.autoMaxWidth, autoMinWidth: this.autoMinWidth, columnCapitalize: this.columnCapitalize, showGeometries: this.showGeometries, featureSelection: this.featureSelection, gridCellRenderers: this.gridCellRenderers, columns: columns, showTopToolbar: this.showTopToolbar, exportFormats: this.exportFormats, displayPanels: this.displayPanels, hropts: {zoomOnRowDoubleClick: true, zoomOnFeatureSelect: false, zoomLevelPointSelect: 8}});
        if (!this.tabPanel) {
            this.tabPanel = new Ext.TabPanel({border: false, autoDestroy: true, enableTabScroll: true, items: [panel], activeTab: 0});
        } else {
            this.tabPanel.add(panel);
            this.tabPanel.setActiveTab(0);
        }
        panel.loadFeatures(featureSet.features, featureSet.featureType);
    }
    return this.tabPanel;
}, displayTree: function (evt) {
    var panel = new Heron.widgets.XMLTreePanel();
    panel.xmlTreeFromText(panel, evt.text);
    return panel;
}, displayXML: function (evt) {
    var opts = {html: '<div class="hr-html-panel-body"><pre>' + Heron.Utils.formatXml(evt.text, true) + '</pre></div>', preventBodyReset: true, autoScroll: true};
    return new Ext.Panel(opts);
}, displayInfo: function (infoStr) {
    var opts = {html: '<div class="hr-html-panel-body"><pre>' + infoStr + '</pre></div>', preventBodyReset: true, autoScroll: true};
    return new Ext.Panel(opts);
}});
Ext.reg('hr_featureinfopanel', Heron.widgets.search.FeatureInfoPanel);
Ext.namespace("Heron.widgets.search");
Ext.namespace("Heron.utils");
Heron.widgets.search.FeatureInfoPopup = Ext.extend(GeoExt.Popup, {title: __('FeatureInfo popup'), layout: 'fit', resizable: true, width: 320, height: 200, anchorPosition: "auto", panIn: false, draggable: true, unpinnable: false, maximizable: false, collapsible: false, closeAction: 'hide', olControl: null, anchored: true, hideonmove: false, layer: null, initComponent: function () {
    this.map = Heron.App.getMap();
    if (this.hideonmove) {
        this.anchorPosition = "bottom-left";
    }
    this.fiPanel = this.createFeatureInfoPanel();
    this.fiPanel.addListener('beforefeatureinfo', this.onBeforeFeatureInfo, this);
    this.fiPanel.addListener('featureinfo', this.onFeatureInfo, this);
    var self = this;
    this.olControl = this.fiPanel.olControl;
    if (this.hideonmove && this.olControl.handler && this.olControl.handler.callbacks.move) {
        this.olControl.handler.callbacks.move = function () {
            self.olControl.cancelHover();
            self.hide();
        }
    }
    this.items = [this.fiPanel];
    Heron.widgets.search.FeatureInfoPopup.superclass.initComponent.call(this);
}, createFeatureInfoPanel: function () {
    var defaultConfig = {title: null, header: false, border: false, showTopToolbar: false, exportFormats: [], maxFeatures: 8, hover: false, drillDown: true, infoFormat: 'application/vnd.ogc.gml', layer: this.layer, olControl: this.olControl};
    var config = Ext.apply(defaultConfig, this.featureInfoPanel);
    return new Heron.widgets.search.FeatureInfoPanel(config);
}, onBeforeFeatureInfo: function (evt) {
    this.hide();
}, onFeatureInfo: function (evt) {
    this.location = this.map.getLonLatFromPixel(evt.xy);
    this.show();
}, deactivate: function () {
    this.hide();
}});
Ext.reg('hr_featureinfopopup', Heron.widgets.search.FeatureInfoPopup);
Ext.namespace("Heron.widgets");
Heron.widgets.XMLTreePanel = Ext.extend(Ext.tree.TreePanel, {initComponent: function () {
    Ext.apply(this, {autoScroll: true, rootVisible: false, root: this.root ? this.root : {nodeType: 'async', text: 'Ext JS', draggable: false, id: 'source'}});
    Heron.widgets.XMLTreePanel.superclass.initComponent.apply(this, arguments);
}, xmlTreeFromUrl: function (url) {
    var self = this;
    Ext.Ajax.request({url: url, method: 'GET', params: null, success: function (result, request) {
        self.xmlTreeFromDoc(self, result.responseXML);
    }, failure: function (result, request) {
        alert('error in ajax request');
    }});
}, xmlTreeFromText: function (self, text) {
    var doc = new OpenLayers.Format.XML().read(text);
    self.xmlTreeFromDoc(self, doc);
    return doc;
}, xmlTreeFromDoc: function (self, doc) {
    self.setRootNode(self.treeNodeFromXml(self, doc.documentElement || doc));
}, treeNodeFromXml: function (self, XmlEl) {
    var t = ((XmlEl.nodeType == 3) ? XmlEl.nodeValue : XmlEl.tagName);
    if (t.replace(/\s/g, '').length == 0) {
        return null;
    }
    var result = new Ext.tree.TreeNode({text: t});
    var xmlns = 'xmlns', xsi = 'xsi';
    if (XmlEl.nodeType == 1) {
        Ext.each(XmlEl.attributes, function (a) {
            var nodeName = a.nodeName;
            if (!(XmlEl.parentNode.nodeType == 9 && (nodeName.substring(0, xmlns.length) === xmlns || nodeName.substring(0, xsi.length) === xsi))) {
                var c = new Ext.tree.TreeNode({text: a.nodeName});
                c.appendChild(new Ext.tree.TreeNode({text: a.nodeValue}));
                result.appendChild(c);
            }
        });
        Ext.each(XmlEl.childNodes, function (el) {
            if ((el.nodeType == 1) || (el.nodeType == 3)) {
                var c = self.treeNodeFromXml(self, el);
                if (c) {
                    result.appendChild(c);
                }
            }
        });
    }
    return result;
}});
Ext.reg('hr_xmltreepanel', Heron.widgets.XMLTreePanel);
Ext.namespace("Heron.widgets");
Heron.widgets.HTMLPanel = Ext.extend(Ext.Panel, {initComponent: function () {
    Heron.widgets.HTMLPanel.superclass.initComponent.call(this);
    this.addListener('render', function () {
        this.loadMask = new Ext.LoadMask(this.body, {msg: __('Loading...')})
    });
}});
Ext.reg('hr_htmlpanel', Heron.widgets.HTMLPanel);
Ext.namespace("Heron.widgets");
Heron.widgets.Bookmarks = (function () {
    var contexts = undefined;
    var map = undefined;
    var bookmarksPanel = undefined;
    var instance = {init: function (hroptions) {
    }, setMapContext: function (contextid, id) {
        var elmm = Ext.getCmp(contextid);
        contexts = elmm.hropts;
        if (contexts) {
            var map = Heron.App.getMap();
            for (var i = 0; i < contexts.length; i++) {
                if (contexts[i].id == id) {
                    if (contexts[i].x && contexts[i].y && contexts[i].zoom) {
                        map.setCenter(new OpenLayers.LonLat(contexts[i].x, contexts[i].y), contexts[i].zoom, false, true);
                    }
                    else if (contexts[i].x && contexts[i].y && !contexts[i].zoom) {
                        map.setCenter(new OpenLayers.LonLat(contexts[i].x, contexts[i].y), map.getZoom(), false, true);
                    }
                    else if (!(contexts[i].x && contexts[i].y) && contexts[i].zoom) {
                        map.setCenter(new OpenLayers.LonLat(map.center.lon, map.center.lat), contexts[i].zoom, false, true);
                    }
                    if (contexts[i].layers) {
                        var mapLayers = map.layers;
                        var ctxLayers = contexts[i].layers;
                        var ctxName = contexts[i].name;
                        if ((ctxLayers.length) || (!ctxLayers.length && ctxName.length)) {
                            if (!contexts[i].addLayers) {
                                for (var n = 0; n < mapLayers.length; n++) {
                                    if (mapLayers[n].getVisibility()) {
                                        if (!mapLayers[n].isBaseLayer) {
                                            mapLayers[n].setVisibility(false);
                                        }
                                    }
                                }
                            }
                            for (var m = 0; m < ctxLayers.length; m++) {
                                for (n = 0; n < mapLayers.length; n++) {
                                    if (mapLayers[n].name == ctxLayers[m]) {
                                        if (mapLayers[n].isBaseLayer) {
                                            map.setBaseLayer(mapLayers[n]);
                                        }
                                        mapLayers[n].setVisibility(true);
                                    }
                                }
                            }
                            if (map.baseLayer) {
                                map.setBaseLayer(map.baseLayer);
                            }
                        }
                    }
                }
            }
        }
    }, removeBookmark: function (contextid, id) {
        var elmm = Ext.getCmp(contextid);
        elmm.removeBookmark(id);
    }, setBookmarksPanel: function (abookmarksPanel) {
        bookmarksPanel = abookmarksPanel;
    }, getBookmarksPanel: function () {
        return bookmarksPanel;
    }};
    return(instance);
})();
Heron.widgets.BookmarksPanel = Ext.extend(Heron.widgets.HTMLPanel, {title: __('Bookmarks'), titleDescription: null, titleBookmarkProject: __("Project bookmarks"), titleBookmarkUser: __("Your bookmarks"), showProjectBookmarks: true, showUserBookmarks: true, autoProjectBookmarksTitle: true, autoUserBookmarksTitle: true, appBookmarkSign: null, autoScroll: true, bodyStyle: {overflow: 'auto'}, initComponent: function () {
    this.version = 1;
    this.signature = this.appBookmarkSign;
    Heron.widgets.BookmarksPanel.superclass.initComponent.call(this);
    if (!this.titleDescription) {
        this.titleDescription = '';
    }
    if (!this.titleBookmarkProject) {
        this.titleBookmarkProject = '';
    }
    if (!this.titleBookmarkUser) {
        this.titleBookmarkUser = '';
    }
    var contexts = undefined;
    var localStorageBookmarks = this.getlocalStorageBookmarks();
    if (localStorageBookmarks) {
        contexts = this.hropts.concat(localStorageBookmarks);
    }
    else {
        contexts = this.hropts;
    }
    this.hropts = contexts;
    Heron.widgets.Bookmarks.init(contexts);
    if (this.showUserBookmarks) {
        Heron.widgets.Bookmarks.setBookmarksPanel(this);
    }
    this.createAddBookmarkWindow();
    this.addListener("afterrender", this.afterrender);
}, afterrender: function () {
    this.updateHtml(this.getHtml());
}, getHtml: function () {
    var firstProjectContext = true;
    var firstUserContext = true;
    var htmllines = '<div class="hr-bookmark-panel-body">';
    var remove = __("Remove bookmark:");
    var restore = __("Restore map context:");
    var removeTooltip = "";
    var restoreTooltip = "";
    var divWidth = 210;
    if (this.titleDescription.length) {
        htmllines += '<div class="hr-bookmark-title-description">' + this.titleDescription + '</div>';
    }
    if (this.el !== undefined) {
        divWidth = this.getInnerWidth() - 60;
    }
    var contexts = this.hropts;
    if (typeof(contexts) !== "undefined") {
        for (var i = 0; i < contexts.length; i++) {
            if (contexts[i].id.substr(0, 11) == "hr_bookmark") {
                if (this.showUserBookmarks) {
                    if (firstUserContext) {
                        if (!firstProjectContext) {
                            htmllines += '<div class="hr-bookmark-title-hr"><hr></div>';
                        }
                        htmllines += '<div class="hr-bookmark-title-header">' + this.titleBookmarkUser + '</div>';
                        firstUserContext = false;
                        removeTooltip = remove;
                    }
                    if (this.isValidBookmark(contexts[i])) {
                        restoreTooltip = restore + " '";
                        if (contexts[i].desc + '' != '') {
                            restoreTooltip += contexts[i].desc;
                        } else {
                            restoreTooltip += contexts[i].name;
                        }
                        restoreTooltip += "'";
                        htmllines += '<div class="hr-bookmark-link-user" style="width: 80%;"><a href="#" id="' + contexts[i].id + '" title="' + restoreTooltip + '" onclick="Heron.widgets.Bookmarks.setMapContext(\'' + this.id + "','" + contexts[i].id + '\'); return false;">' + contexts[i].name + '</a></div>';
                    }
                    else {
                        htmllines += '<div class="hr-bookmark-link-invalid" style="width: 80%;">' + contexts[i].name + '</div>';
                    }
                    htmllines += '<div class="x-tool hr-bookmark-close-icon" title="' + removeTooltip + ' \'' + contexts[i].name + '\'" onclick="Heron.widgets.Bookmarks.removeBookmark(\'' + this.id + "','" + contexts[i].id + '\')">&nbsp;</div>';
                }
            }
            else {
                if (this.showProjectBookmarks) {
                    if (firstProjectContext) {
                        htmllines += '<div class="hr-bookmark-title-header">' + this.titleBookmarkProject + '</div>';
                        firstProjectContext = false;
                    }
                    if (contexts[i].desc.length) {
                        htmllines += '<div class="hr-bookmark-link-project"><a href="#" id="' + contexts[i].id + '" title="' + contexts[i].desc + '" onclick="Heron.widgets.Bookmarks.setMapContext(\'' + this.id + "','" + contexts[i].id + '\'); return false;">' + contexts[i].name + '</a></div>';
                    } else {
                        htmllines += '<div class="hr-bookmark-link-project">&nbsp;</div>';
                    }
                }
            }
        }
    }
    if (this.showProjectBookmarks) {
        if (firstProjectContext) {
            if (!this.autoProjectBookmarksTitle) {
                htmllines += '<div class="hr-bookmark-title-header">' + this.titleBookmarkProject + '</div>';
                firstProjectContext = false;
            }
        }
    }
    if (this.showUserBookmarks) {
        if (firstUserContext) {
            if (!this.autoUserBookmarksTitle) {
                if (!firstProjectContext) {
                    htmllines += '<div class="hr-bookmark-title-hr"><hr></div>';
                }
                htmllines += '<div class="hr-bookmark-title-header">' + this.titleBookmarkUser + '</div>';
            }
        }
    }
    htmllines += '</div>';
    return htmllines;
}, updateHtml: function () {
    this.update(this.getHtml());
}, onAddBookmark: function () {
    if (this.supportsHtml5Storage()) {
        this.AddBookmarkWindow.show();
    }
    else {
        alert(__('Your browser does not support local storage for user-defined bookmarks'));
    }
}, addBookmark: function () {
    var strBookmarkMaxNr = localStorage.getItem("hr_bookmarkMax");
    if (strBookmarkMaxNr) {
        var bookmarkmaxNr = Number(strBookmarkMaxNr);
        if (bookmarkmaxNr !== NaN) {
            bookmarkmaxNr += 1;
        }
        else {
            bookmarkmaxNr = 1;
        }
    }
    else {
        bookmarkmaxNr = 1;
    }
    this.scId = 'hr_bookmark' + bookmarkmaxNr;
    this.scName = this.edName.getValue();
    this.scDesc = this.edDesc.getValue();
    if (!this.scName || this.scName.length == 0) {
        Ext.Msg.alert(__('Warning'), __('Bookmark name cannot be empty'));
        return false;
    }
    this.getMapContent();
    var newbookmark = {id: this.scId, version: this.version, signature: this.signature, type: 'bookmark', name: this.scName, desc: this.scDesc, layers: this.scvisibleLayers, x: this.scX, y: this.scY, zoom: this.scZoom, units: this.scUnits, projection: this.scProjection};
    var newbookmarkJSON = Ext.encode(newbookmark);
    localStorage.setItem(this.scId, newbookmarkJSON);
    localStorage.setItem("hr_bookmarkMax", bookmarkmaxNr);
    this.hropts.push(newbookmark);
    this.updateHtml();
    return true;
}, removeBookmark: function (id) {
    localStorage.removeItem(id);
    var strBookmarkMaxNr = localStorage.getItem("hr_bookmarkMax")
    var bookmarkmaxNr = Number(strBookmarkMaxNr)
    if (bookmarkmaxNr == Number(id.substr(4))) {
        bookmarkmaxNr -= 1
        localStorage.setItem("hr_bookmarkMax", bookmarkmaxNr)
    }
    var contexts = this.hropts;
    var newcontexts = new Array();
    for (var i = 0; i < contexts.length; i++) {
        if (contexts[i].id !== id) {
            newcontexts.push(contexts[i]);
        }
    }
    this.hropts = newcontexts;
    this.updateHtml();
}, getlocalStorageBookmarks: function () {
    if (!this.supportsHtml5Storage()) {
        return null;
    }
    var bookmarkmaxNr = localStorage.getItem("hr_bookmarkMax");
    if (bookmarkmaxNr) {
        var bookmarks = new Array();
        for (var index = 1; index <= bookmarkmaxNr; index++) {
            var bookmarkJSON = localStorage.getItem("hr_bookmark" + index);
            if (bookmarkJSON) {
                try {
                    var bookmark = Ext.decode(bookmarkJSON)
                    if (bookmark.signature) {
                        if (bookmark.signature == this.appBookmarkSign) {
                            bookmarks.push(bookmark);
                        }
                    } else {
                        if (!this.appBookmarkSign) {
                            bookmarks.push(bookmark);
                        }
                    }
                } catch (err) {
                }
            }
        }
        return bookmarks;
    }
    return null;
}, isValidBookmark: function (context) {
    var map = Heron.App.getMap();
    if (context.layers) {
        var mapLayers = map.layers;
        var ctxLayers = context.layers;
        for (var m = 0; m < ctxLayers.length; m++) {
            var layerPresent = false;
            for (var n = 0; n < mapLayers.length; n++) {
                if (mapLayers[n].name == ctxLayers[m]) {
                    layerPresent = true;
                    break;
                }
            }
            if (!layerPresent) {
                return false;
            }
        }
    }
    if (context.projection !== map.getProjection()) {
        return false;
    }
    if (context.units !== map.units) {
        return false;
    }
    var maxExtent = map.maxExtent;
    if (context.x < maxExtent.left && context.x > maxExtent.right) {
        return false;
    }
    if (context.y < maxExtent.bottom && context.y > maxExtent.top) {
        return false;
    }
    if (context.zoom > map.numZoomLevels) {
        return false;
    }
    return true;
}, getMapContent: function () {
    var map = Heron.App.getMap();
    var mapCenter = map.getCenter();
    this.scUnits = map.units;
    this.scProjection = map.getProjection();
    this.scX = mapCenter.lon;
    this.scY = mapCenter.lat;
    this.scZoom = map.getZoom();
    var mapLayers = map.layers;
    this.scvisibleLayers = new Array();
    for (var n = 0; n < mapLayers.length; n++) {
        if (mapLayers[n].getVisibility() && mapLayers[n].CLASS_NAME != 'OpenLayers.Layer.Vector.RootContainer') {
            this.scvisibleLayers.push(mapLayers[n].name);
        }
    }
}, createAddBookmarkWindow: function () {
    var labelWidth = 80;
    var fieldWidth = 300;
    var formPanel = new Ext.form.FormPanel({title: "", baseCls: 'x-plain', autoHeight: true, defaultType: "textfield", labelWidth: labelWidth, anchor: "100%", items: [
        {id: "ed_name", fieldLabel: __("Name"), displayField: "Name", width: fieldWidth, enableKeyEvents: true, listeners: {keyup: function (textfield, ev) {
            this.onNameKeyUp(textfield, ev);
        }, scope: this}},
        {id: "ed_desc", fieldLabel: __("Description"), displayField: "Decription", width: fieldWidth}
    ]});
    this.AddBookmarkWindow = new Ext.Window({title: __("Add a bookmark"), width: 420, autoHeight: true, plain: true, statefull: true, stateId: "ZoomToWindow", bodyStyle: "padding: 5px;", buttonAlign: "center", resizable: false, closeAction: "hide", items: [formPanel], listeners: {show: function () {
        this.onShowWindow();
    }, scope: this}, buttons: [
        {id: "btn_add", text: __("Add"), disabled: true, handler: function () {
            if (this.addBookmark()) {
                this.AddBookmarkWindow.hide();
            }
        }, scope: this},
        {name: "btn_cancel", text: __("Cancel"), handler: function () {
            this.AddBookmarkWindow.hide();
        }, scope: this}
    ]});
    this.edName = Ext.getCmp("ed_name");
    this.edDesc = Ext.getCmp("ed_desc");
    this.btnAdd = Ext.getCmp("btn_add");
}, onNameKeyUp: function (textfield, ev) {
    var value = this.edName.getValue();
    if (value && OpenLayers.String.trim(value).length > 0) {
        this.btnAdd.enable();
    }
    else {
        this.btnAdd.disable();
    }
}, onShowWindow: function () {
    this.edName.setValue('');
    this.edDesc.setValue('');
    this.edName.focus(false, 200);
}, supportsHtml5Storage: function () {
    try {
        return'localStorage'in window && window['localStorage'] !== null;
    } catch (e) {
        return false;
    }
}});
Ext.reg('hr_bookmarkspanel', Heron.widgets.BookmarksPanel);
Ext.reg('hr_contextbrowserpanel', Heron.widgets.BookmarksPanel);
Ext.namespace("Heron.widgets");
Heron.widgets.LayerTreePanel = Ext.extend(Ext.tree.TreePanel, {title: __('Layers'), textbaselayers: __('Base Layers'), textoverlays: __('Overlays'), lines: false, ordering: 'none', layerIcons: 'bylayertype', layerResolutions: {}, appliedResolution: 0.0, autoScroll: true, plugins: [
    {ptype: "gx_treenodecomponent"}
], contextMenu: null, blnCustomLayerTree: false, jsonTreeConfig: null, initComponent: function () {
    var layerTreePanel = this;
    var treeConfig;
    if (this.hropts && this.hropts.tree) {
        this.blnCustomLayerTree = true;
        treeConfig = this.hropts.tree;
    } else {
        treeConfig = [
            {nodeType: "gx_baselayercontainer", text: this.textbaselayers, expanded: true},
            {nodeType: "gx_overlaylayercontainer", text: this.textoverlays}
        ]
    }
    this.jsonTreeConfig = new OpenLayers.Format.JSON().write(treeConfig, true);
    var layerTree = this;
    var LayerNodeUI = Ext.extend(GeoExt.tree.LayerNodeUI, new GeoExt.tree.TreeNodeUIEventMixin());
    var options = {title: this.title, autoScroll: true, containerScroll: true, loader: new Ext.tree.TreeLoader({applyLoader: false, uiProviders: {"custom_ui": LayerNodeUI}, createNode: function (attr) {
        return layerTreePanel.createNode(this, attr);
    }}), root: {nodeType: "async", baseAttrs: {uiProvider: "custom_ui"}, children: Ext.decode(this.jsonTreeConfig)}, rootVisible: false, enableDD: true, lines: this.lines, listeners: {contextmenu: function (node, e) {
        node.select();
        var cm = this.contextMenu;
        if (cm) {
            cm.contextNode = node;
            cm.showAt(e.getXY());
        }
    }, movenode: function (tree, node, oldParent, newParent, index) {
        if ((this.blnCustomLayerTree == true) && (this.ordering == 'TopBottom' || this.ordering == 'BottomTop')) {
            if (node.layer != undefined) {
                this.setLayerOrder(node);
            } else {
                this.setLayerOrderFolder(node);
            }
        }
    }, checkchange: function (node, checked) {
        if ((this.blnCustomLayerTree == true) && (this.ordering == 'TopBottom' || this.ordering == 'BottomTop')) {
            this.setLayerOrder(node);
        }
    }, scope: this}};
    if (this.contextMenu) {
        var cmArgs = this.contextMenu instanceof Array ? {items: this.contextMenu} : {};
        this.contextMenu = new Heron.widgets.LayerNodeContextMenu(cmArgs);
    }
    Ext.apply(this, options);
    Heron.widgets.LayerTreePanel.superclass.initComponent.call(this);
    this.addListener("beforedblclick", this.onBeforeDblClick);
    this.addListener("afterrender", this.onAfterRender);
    this.addListener("expandnode", this.onExpandNode);
}, createNode: function (treeLoader, attr) {
    var mapPanel = Heron.App.getMapPanel();
    if (!mapPanel || !attr.layer || (this.layerIcons == 'default' && !attr.legend)) {
        return Ext.tree.TreeLoader.prototype.createNode.call(treeLoader, attr);
    }
    var layer = undefined;
    if (mapPanel && mapPanel.layers instanceof GeoExt.data.LayerStore) {
        var layerStore = mapPanel.layers;
        var layerIndex = layerStore.findExact('title', attr.layer);
        if (layerIndex >= 0) {
            var layerRecord = layerStore.getAt(layerIndex);
            layer = layerRecord.getLayer();
        }
    }
    if (this.layerIcons == 'none') {
        attr.iconCls = 'hr-tree-node-icon-none';
    }
    if (layer) {
        var layerType = layer.CLASS_NAME.split('.').slice(-1)[0];
        if (this.layerIcons == 'bylayertype' && !(attr.iconCls || attr.cls || attr.icon)) {
            var layerKind = 'raster';
            if (layerType == 'Vector') {
                layerKind = 'vector';
            } else if (layerType == 'Atom') {
                layerKind = 'atom';
            }
            attr.iconCls = 'hr-tree-node-icon-layer-' + layerKind;
        }
        if (attr.legend) {
            attr.uiProvider = "custom_ui";
            var xtype = layerType == 'Vector' ? 'gx_vectorlegend' : 'gx_wmslegend';
            attr.component = {xtype: xtype, layerRecord: layerRecord, showTitle: false, cls: "hr-treenode-legend", hidden: !layer.getVisibility()}
        }
    }
    return Ext.tree.TreeLoader.prototype.createNode.call(treeLoader, attr);
}, onBeforeDblClick: function (node, evt) {
    return false;
}, onExpandNode: function (node) {
    for (var i = 0; i < node.childNodes.length; i++) {
        var child = node.childNodes[i];
        if (child.leaf) {
            this.setNodeEnabling(child, Heron.App.getMap());
        }
    }
}, onAfterRender: function () {
    var self = this;
    var map = Heron.App.getMap();
    self.applyMapMoveEnd();
    map.events.register('moveend', null, function (evt) {
        self.applyMapMoveEnd();
    });
}, applyMapMoveEnd: function () {
    var map = Heron.App.getMap();
    if (map) {
        if (map.resolution != this.appliedResolution) {
            this.setNodeEnabling(this.getRootNode(), map);
            this.appliedResolution = map.resolution;
        }
    }
}, setNodeEnabling: function (rootNode, map) {
    rootNode.cascade(function (node) {
        var layer = node.layer;
        if (!layer) {
            return;
        }
        var layerMinResolution = layer.minResolution ? layer.minResolution : map.resolutions[map.resolutions.length - 1];
        var layerMaxResolution = layer.maxResolution ? layer.maxResolution : map.resolutions[0];
        node.enable();
        if (map.resolution < layerMinResolution || map.resolution > layerMaxResolution) {
            //HACK
           // node.disable();
        }
    });
}, setLayerOrder: function (node) {
    var map = Heron.App.getMap();
    var intLayerNr = this.getLayerNrInTree(node.layer.name);
    if (this.ordering == 'TopBottom') {
        intLayerNr = Heron.App.getMap().layers.length - intLayerNr - 1;
    }
    if (intLayerNr > 0) {
        map.setLayerIndex(node.layer, intLayerNr);
    }
}, setLayerOrderFolder: function (node) {
    if (node.attributes.layer != undefined) {
        this.setLayerOrder(node)
    } else {
        for (var i = 0; i < node.childNodes.length; i++) {
            this.setLayerOrderFolder(node.childNodes[i]);
        }
    }
}, getLayerNrInTree: function (layerName) {
    var treePanel = Heron.App.topComponent.findByType('hr_layertreepanel')[0];
    this.intLayer = -1;
    var blnFound = false;
    if (treePanel != null) {
        var treeRoot = treePanel.root;
        if (treeRoot.childNodes.length > 0) {
            for (var intTree = 0; intTree < treeRoot.childNodes.length; intTree++) {
                if (blnFound == false) {
                    blnFound = this.findLayerInNode(layerName, treeRoot.childNodes[intTree], blnFound)
                }
            }
        }
    }
    return blnFound ? this.intLayer : -1;
}, findLayerInNode: function (layerName, node, blnFound) {
    if (blnFound == false) {
        if (node.attributes.layer != undefined) {
            this.intLayer++;
            if (node.attributes.layer == layerName) {
                blnFound = true;
            }
        } else {
            for (var i = 0; i < node.childNodes.length; i++) {
                blnFound = this.findLayerInNode(layerName, node.childNodes[i], blnFound);
            }
        }
    }
    return blnFound;
}});
Ext.reg('hr_layertreepanel', Heron.widgets.LayerTreePanel);
Ext.namespace("Heron.widgets");
Heron.widgets.LayerCombo = Ext.extend(Ext.form.ComboBox, {map: null, store: null, emptyText: __('Choose a Layer'), tooltip: __('Choose a Layer'), sortOrder: 'ASC', selectFirst: false, hideTrigger: false, layerFilter: function (map) {
    return map.layers;
}, displayField: 'name', forceSelection: true, triggerAction: 'all', mode: 'local', editable: false, initComponent: function () {
    if (!this.map) {
        this.map = Heron.App.getMap();
    }
    this.store = this.createLayerStore(this.layerFilter(this.map));
    this.displayField = this.store.fields.keys[1];
    if (this.selectFirst) {
        var record = this.store.getAt(0);
        if (record) {
            this.selectedLayer = record.getLayer();
            this.value = record.get('title');
        }
    }
    if (!this.width) {
        this.width = this.listWidth = 'auto';
    }
    if (Ext.isIE && this.listWidth == 'auto') {
        this.listWidth = 160;
    }
    Heron.widgets.LayerCombo.superclass.initComponent.apply(this, arguments);
    this.addEvents({'selectlayer': true});
    if (this.initialValue) {
        this.setValue(this.initialValue);
    }
    this.on('select', function (combo, record, idx) {
        this.selectedLayer = record.getLayer(idx);
        this.fireEvent('selectlayer', this.selectedLayer);
    }, this);
}, createLayerStore: function (layers) {
    return new GeoExt.data.LayerStore({layers: layers, sortInfo: this.sortOrder ? {field: 'title', direction: this.sortOrder} : null});
}, setLayers: function (layers) {
    var store = this.createLayerStore(layers);
    this.bindStore(store, false);
}, resizeToFitContent: function () {
    if (!this.elMetrics) {
        this.elMetrics = Ext.util.TextMetrics.createInstance(this.getEl());
    }
    var m = this.elMetrics, width = 0, el = this.el, s = this.getSize();
    this.store.each(function (r) {
        var text = r.get(this.displayField);
        width = Math.max(width, m.getWidth(text));
    }, this);
    if (el) {
        width += el.getBorderWidth('lr');
        width += el.getPadding('lr');
    }
    if (this.trigger) {
        width += this.trigger.getWidth();
    }
    s.width = width;
    this.setSize(s);
    this.store.on({'datachange': this.resizeToFitContent, 'add': this.resizeToFitContent, 'remove': this.resizeToFitContent, 'load': this.resizeToFitContent, 'update': this.resizeToFitContent, buffer: 10, scope: this});
}, listeners: {render: function (c) {
    c.el.set({qtip: this.tooltip});
    c.trigger.set({qtip: this.tooltip});
    if (this.width == 'auto') {
        c.resizeToFitContent();
    }
}}});
Ext.reg('hr_layercombo', Heron.widgets.LayerCombo);
Ext.namespace("Heron.widgets");
Heron.widgets.BaseLayerCombo = Ext.extend(Heron.widgets.LayerCombo, {emptyText: __('Choose a Base Layer'), tooltip: __('BaseMaps'), layerFilter: function (map) {
    return map.getLayersBy('isBaseLayer', true);
}, initComponent: function () {
    if (this.initialConfig.map !== null && this.initialConfig.map instanceof OpenLayers.Map && this.initialConfig.map.allOverlays === false) {
        this.map = this.initialConfig.map;
        this.on('selectlayer', function (layer) {
            this.map.setBaseLayer(layer);
        }, this);
        this.map.events.register('changebaselayer', this, function (obj) {
            this.setValue(obj.layer.name);
        });
        this.initialValue = this.map.baseLayer.name;
    }
    Heron.widgets.BaseLayerCombo.superclass.initComponent.apply(this, arguments);
}});
Ext.reg('hr_baselayer_combobox', Heron.widgets.BaseLayerCombo);
Ext.namespace("Heron.widgets");
Heron.widgets.LayerLegendPanel = Ext.extend(GeoExt.LegendPanel, {title: __('Legend'), bodyStyle: 'padding:5px', autoScroll: true, defaults: {useScaleParameter: false, baseParams: {}}, dynamic: true, initComponent: function () {
    if (this.hropts) {
        this.prefetchLegends = this.hropts.prefetchLegends;
    }
    Heron.widgets.LayerLegendPanel.superclass.initComponent.call(this);
}, onRender: function () {
    Heron.widgets.LayerLegendPanel.superclass.onRender.apply(this, arguments);
    this.layerStore.addListener("update", this.onUpdateLayerStore, this);
}, onUpdateLayerStore: function (store, record, index) {
    this.addLegend(record, index);
}, addLegend: function (record, index) {
    record.store = this.layerStore;
    var layer = record.getLayer();
    if (!layer.metadata.legend) {
        layer.metadata.legend = {};
    }
    var layerLegendMD = layer.metadata.legend;
    if (layer.noLegend) {
        layer.hideInLegend = layerLegendMD.hideInLegend = true;
    }
    if (layerLegendMD.hideInLegend && !record.get('hideInLegend')) {
        record.set('hideInLegend', true);
    }
    if (layer.legendURL) {
        layerLegendMD.legendURL = layer.legendURL;
    }
    if (layerLegendMD.legendURL && !record.get('legendURL')) {
        record.set('legendURL', layerLegendMD.legendURL);
    }
    var legend = undefined;
    if (this.items) {
        legend = this.getComponent(this.getIdForLayer(layer));
    }
    if ((this.prefetchLegends && !legend) || (((layer.map && layer.visibility) || layer.getVisibility()) && !legend && !layerLegendMD.hideInLegend)) {
        Heron.widgets.LayerLegendPanel.superclass.addLegend.apply(this, arguments);
        this.doLayout();
    }
    this.doLayout();
}, onListenerDoLayout: function (node) {
    node.doLayout();
}, listeners: {activate: function (node) {
    this.onListenerDoLayout(this);
}, expand: function (node) {
    this.onListenerDoLayout(this);
}}});
Ext.reg('hr_layerlegendpanel', Heron.widgets.LayerLegendPanel);
OpenLayers.Control.LoadingPanel = OpenLayers.Class(OpenLayers.Control, {counter: 0, maximized: false, visible: true, initialize: function (options) {
    OpenLayers.Control.prototype.initialize.apply(this, [options]);
}, setVisible: function (visible) {
    this.visible = visible;
    if (visible) {
        OpenLayers.Element.show(this.div);
    } else {
        OpenLayers.Element.hide(this.div);
    }
}, getVisible: function () {
    return this.visible;
}, hide: function () {
    this.setVisible(false);
}, show: function () {
    this.setVisible(true);
}, toggle: function () {
    this.setVisible(!this.getVisible());
}, addLayer: function (evt) {
    if (evt.layer) {
        evt.layer.events.register('loadstart', this, this.increaseCounter);
        evt.layer.events.register('loadend', this, this.decreaseCounter);
    }
}, removeLayer: function (evt) {
    if (evt.layer) {
        evt.layer.events.unregister('loadstart', this, this.increaseCounter);
        evt.layer.events.unregister('loadend', this, this.decreaseCounter);
    }
}, getWaitText: function () {
    return __("Waiting for") + ' ' + this.counter + ' ' + (this.counter <= 1 ? __('service') : __('services'));
}, setMap: function (map) {
    OpenLayers.Control.prototype.setMap.apply(this, arguments);
    this.map.events.register('preaddlayer', this, this.addLayer);
    this.map.events.register('removelayer', this, this.removeLayer);
    for (var i = 0; i < this.map.layers.length; i++) {
        var layer = this.map.layers[i];
        layer.events.register('loadstart', this, this.increaseCounter);
        layer.events.register('loadend', this, this.decreaseCounter);
    }
}, increaseCounter: function () {
    this.counter++;
    if (this.counter > 0) {
        this.div.innerHTML = this.getWaitText();
        if (!this.maximized && this.visible) {
            this.maximizeControl();
        }
    }
}, decreaseCounter: function () {
    if (this.counter > 0) {
        this.div.innerHTML = this.getWaitText();
        this.counter--;
    }
    if (this.counter == 0) {
        if (this.maximized && this.visible) {
            this.minimizeControl();
        }
    }
}, draw: function () {
    OpenLayers.Control.prototype.draw.apply(this, arguments);
    return this.div;
}, minimizeControl: function (evt) {
    this.div.style.display = "none";
    this.maximized = false;
    if (evt != null) {
        OpenLayers.Event.stop(evt);
    }
}, maximizeControl: function (evt) {
    this.div.style.display = "block";
    this.maximized = true;
    if (evt != null) {
        OpenLayers.Event.stop(evt);
    }
}, destroy: function () {
    if (this.map) {
        this.map.events.unregister('preaddlayer', this, this.addLayer);
        if (this.map.layers) {
            for (var i = 0; i < this.map.layers.length; i++) {
                var layer = this.map.layers[i];
                layer.events.unregister('loadstart', this, this.increaseCounter);
                layer.events.unregister('loadend', this, this.decreaseCounter);
            }
        }
    }
    OpenLayers.Control.prototype.destroy.apply(this, arguments);
}, CLASS_NAME: "OpenLayers.Control.LoadingPanel"});
OpenLayers.Control.StyleFeature = OpenLayers.Class(OpenLayers.Control.Button, {layer: null, initialize: function (layer, options) {
    this.layer = layer;
    this.options = options ? options : {};
    this.title = __('Change feature styles');
    OpenLayers.Control.Button.prototype.initialize.apply(this, [options]);
    this.trigger = this.toggleStyleEditor;
    this.displayClass = "oleControlEnabled " + this.displayClass;
}, toggleStyleEditor: function () {
    if (!gxp || !gxp.VectorStylesDialog) {
        Ext.Msg.alert(__('Warning'), __('Vector Layer style editing requires GXP with VectorStylesDialog'));
        return;
    }
    var layerRecord = Heron.App.getMapPanel().layers.getByLayer(this.layer);
    if (!this.styleEditor) {
        this.styleEditor = new Ext.Window({layout: 'auto', resizable: false, autoHeight: true, pageX: this.options.pageX ? this.options.pageX : 100, pageY: this.options.pageY ? this.options.pageY : 200, width: this.options.width ? this.options.width : 400, height: this.options.height ? this.options.height : undefined, closeAction: 'hide', title: __('Style Editor (Vector)'), items: [gxp.VectorStylesDialog.createVectorStylerConfig(layerRecord)]});
    }
    if (!this.styleEditor.isVisible()) {
        this.styleEditor.show();
    } else {
        this.styleEditor.hide();
    }
}, CLASS_NAME: "OpenLayers.Control.StyleFeature"});
if (OpenLayers.Editor && OpenLayers.Editor.Control) {
    OpenLayers.Editor.Control.StyleFeature = OpenLayers.Class(OpenLayers.Control.StyleFeature, {initialize: function (layer, options) {
        OpenLayers.Control.StyleFeature.prototype.initialize.apply(this, [layer, options]);
    }, CLASS_NAME: "OpenLayers.Editor.Control.StyleFeature"});
}
Ext.namespace("Heron.widgets");
Heron.widgets.MapPanelOptsDefaults = {center: '0,0', map: {units: 'degrees', maxExtent: '-180,-90,180,90', extent: '-180,-90,180,90', maxResolution: 0.703125, numZoomLevels: 20, zoom: 1, allOverlays: false, fractionalZoom: false, permalinks: {paramPrefix: 'map', encodeType: false, prettyLayerNames: true}, controls: [new OpenLayers.Control.Attribution(), new OpenLayers.Control.ZoomBox(), new OpenLayers.Control.Navigation({dragPanOptions: {enableKinetic: true}}), new OpenLayers.Control.LoadingPanel(), new OpenLayers.Control.PanPanel(), new OpenLayers.Control.ZoomPanel()]}};
Heron.widgets.MapPanel = Ext.extend(GeoExt.MapPanel, {initComponent: function () {
    var gxMapPanelOptions = {id: "gx-map-panel", split: false, layers: this.hropts.layers, items: this.items ? this.items : [
        {xtype: "gx_zoomslider", vertical: true, height: 150, x: 18, y: 85, aggressive: false, plugins: new GeoExt.ZoomSliderTip({template: __("Scale") + ": 1 : {scale}<br>" +
            __("Resolution") + ": {resolution}<br>" +
            __("Zoom") + ": {zoom}"})}
    ], statusbar: [
        {type: "epsgpanel"},
        {type: "-"},
        {type: "xcoord"},
        {type: "ycoord"},
        {type: "-"},
        {type: "measurepanel"}
    ], tbar: new Ext.Toolbar({enableOverflow: true, items: []}), bbar: new Ext.Toolbar({enableOverflow: true, items: []})};
    if (this.hropts.hasOwnProperty("statusbar")) {
        if (this.hropts.statusbar) {
            Ext.apply(gxMapPanelOptions.statusbar, this.hropts.statusbar);
        } else {
            gxMapPanelOptions.statusbar = {};
        }
    }
    Ext.apply(gxMapPanelOptions, Heron.widgets.MapPanelOptsDefaults);
    if (this.hropts.settings) {
        Ext.apply(gxMapPanelOptions.map, this.hropts.settings);
    }
    if (gxMapPanelOptions.map.controls && typeof gxMapPanelOptions.map.controls == "string") {
        gxMapPanelOptions.map.controls = undefined;
    }
    if (typeof gxMapPanelOptions.map.maxExtent == "string") {
        gxMapPanelOptions.map.maxExtent = OpenLayers.Bounds.fromString(gxMapPanelOptions.map.maxExtent);
        gxMapPanelOptions.maxExtent = gxMapPanelOptions.map.maxExtent;
    }
    if (typeof gxMapPanelOptions.map.extent == "string") {
        gxMapPanelOptions.map.extent = OpenLayers.Bounds.fromString(gxMapPanelOptions.map.extent);
        gxMapPanelOptions.extent = gxMapPanelOptions.map.extent;
    }
    if (!gxMapPanelOptions.map.center) {
        gxMapPanelOptions.map.center = OpenLayers.LonLat.fromString('0,0');
    } else if (typeof gxMapPanelOptions.map.center == "string") {
        gxMapPanelOptions.map.center = OpenLayers.LonLat.fromString(gxMapPanelOptions.map.center);
    }
    gxMapPanelOptions.center = gxMapPanelOptions.map.center;
    if (gxMapPanelOptions.map.zoom) {
        gxMapPanelOptions.zoom = gxMapPanelOptions.map.zoom;
    }
    if (gxMapPanelOptions.map.controls) {
        gxMapPanelOptions.controls = gxMapPanelOptions.map.controls;
    }
    gxMapPanelOptions.map.layers = this.hropts.layers;
    Ext.apply(this, gxMapPanelOptions);
    if (this.layers) {
        for (var i = 0; i < this.layers.length; i++) {
            if (this.layers[i]instanceof Array) {
                try {
                    this.layers[i] = Heron.Utils.createOLObject(this.layers[i]);
                } catch (err) {
                    alert("Error creating Layer num=" + i + " msg=" + err.message + " args=" + this.layers[i]);
                }
            }
        }
    }
    if (this.map.permalinks) {
        this.prettyStateKeys = this.map.permalinks.prettyLayerNames;
        this.stateId = this.map.permalinks.paramPrefix;
        this.permalinkProvider = new GeoExt.state.PermalinkProvider({encodeType: this.map.permalinks.encodeType});
        Ext.state.Manager.setProvider(this.permalinkProvider);
    }
    Heron.widgets.MapPanel.superclass.initComponent.call(this);
    if (this.hropts.settings && this.hropts.settings.formatX) {
        this.formatX = this.hropts.settings.formatX;
    }
    if (this.hropts.settings && this.hropts.settings.formatY) {
        this.formatY = this.hropts.settings.formatY;
    }
    Heron.App.setMap(this.getMap());
    Heron.App.setMapPanel(this);
    Heron.widgets.ToolbarBuilder.build(this, this.hropts.toolbar, this.getTopToolbar());
    Heron.widgets.ToolbarBuilder.build(this, gxMapPanelOptions.statusbar, this.getBottomToolbar());
}, formatX: function (lon, precision) {
    return"X: " + lon.toFixed(precision);
}, formatY: function (lat, precision) {
    return"Y: " + lat.toFixed(precision);
}, getPermalink: function () {
    return this.permalinkProvider.getLink();
}, getMap: function () {
    return this.map;
}, afterRender: function () {
    Heron.widgets.MapPanel.superclass.afterRender.apply(this, arguments);
    var xy_precision = 3;
    if (this.hropts && this.hropts.settings && this.hropts.settings.hasOwnProperty('xy_precision')) {
        xy_precision = this.hropts.settings.xy_precision;
    }
    var formatX = this.formatX;
    var formatY = this.formatY;
    var onMouseMove = function (e) {
        var lonLat = this.getLonLatFromPixel(e.xy);
        if (!lonLat) {
            return;
        }
        if (this.displayProjection) {
            lonLat.transform(this.getProjectionObject(), this.displayProjection);
        }
        var xcoord = Ext.getCmp("x-coord");
        if (xcoord) {
            xcoord.setText(formatX(lonLat.lon, xy_precision));
        }
        var ycoord = Ext.getCmp("y-coord");
        if (ycoord) {
            ycoord.setText(formatY(lonLat.lat, xy_precision));
        }
    };
    var map = this.getMap();
    map.events.register("mousemove", map, onMouseMove);
    var epsgTxt = map.getProjection();
    if (epsgTxt) {
        var epsg = Ext.getCmp("map-panel-epsg");
        if (epsg) {
            epsg.setText(epsgTxt);
        }
    }
}});
Ext.reg('hr_mappanel', Heron.widgets.MapPanel);
Ext.namespace("Heron.widgets");
Heron.widgets.MenuHandler = (function () {
    var options = null;

    function getContainer() {
        return Ext.getCmp(options.pageContainer);
    }

    function loadPage(page) {
        var container = Ext.getCmp(options.pageContainer);
        if (page && container && options.pageRoot) {
            container.load(options.pageRoot + '/' + page + '.html?t=' + new Date().getMilliseconds());
        }
    }

    function loadURL(url) {
        var container = Ext.getCmp(options.pageContainer);
        if (url && container) {
            container.load({url: url, nocache: true, scripts: true});
        }
    }

    function setActiveCard(card) {
        if (card && options.cardContainer) {
            Ext.getCmp(options.cardContainer).getLayout().setActiveItem(card);
        }
    }

    var instance = {init: function (hroptions) {
        if (hroptions && !options) {
            options = hroptions;
            setActiveCard(options.defaultCard);
            loadPage(options.defaultPage);
        }
    }, onSelect: function (item) {
        setActiveCard(item.card);
        if (item.page) {
            loadPage(item.page);
        } else if (item.url) {
            loadURL(item.url)
        }
    }, onLinkSelect: function (card, page) {
        if (card) {
            setActiveCard(card);
        }
        if (page) {
            loadPage(page);
        }
    }};
    return(instance);
})();
Heron.widgets.MenuPanel = Ext.extend(Ext.Panel, {initComponent: function () {
    this.addListener('afterrender', function () {
        if (this.hropts) {
            Heron.widgets.MenuHandler.init(this.hropts);
        }
    });
    Heron.widgets.MenuPanel.superclass.initComponent.apply(this, arguments);
}});
Ext.reg('hr_menupanel', Heron.widgets.MenuPanel);
Ext.namespace("Heron.widgets");
Heron.widgets.MultiLayerNode = Ext.extend(GeoExt.tree.LayerNode, {layerNames: [], layers: [], constructor: function (config) {
    if (config.layers) {
        this.layerNames = config.layers.split(",");
        if (this.layerNames[0]) {
            arguments[0].layer = this.layerNames[0];
        }
    }
    for (var i = 0; i < this.layerNames.length; i++) {
        if (!this.layerStore || this.layerStore == "auto") {
            this.layerStore = GeoExt.MapPanel.guess().layers;
        }
        var j = this.layerStore.findBy(function (o) {
            return o.get("title") == this.layerNames[i];
        }, this);
        if (j != -1) {
            this.layers[i] = this.layerStore.getAt(j).getLayer();
        }
    }
    Heron.widgets.MultiLayerNode.superclass.constructor.apply(this, arguments);
}, render: function (bulkRender) {
    this.layer = this.layers[0];
    Heron.widgets.MultiLayerNode.superclass.render.apply(this, arguments);
}, onLayerVisibilityChanged: function () {
    this.layer = this.layers[0];
    Heron.widgets.MultiLayerNode.superclass.onLayerVisibilityChanged.apply(this, arguments);
}, onCheckChange: function (node, checked) {
    for (var i = 0; i < this.layers.length; i++) {
        this.layer = this.layers[i];
        Heron.widgets.MultiLayerNode.superclass.onCheckChange.apply(this, arguments);
    }
}, onStoreAdd: function (store, records, index) {
    for (var i = 0; i < this.layers.length; i++) {
        this.layer = this.layers[i];
        Heron.widgets.MultiLayerNode.superclass.onStoreAdd.apply(this, arguments);
    }
}, onStoreRemove: function (store, record, index) {
    for (var i = 0; i < this.layers.length; i++) {
        this.layer = this.layers[i];
        Heron.widgets.MultiLayerNode.superclass.onStoreRemove.apply(this, arguments);
    }
}, onStoreUpdate: function (store, record, operation) {
    for (var i = 0; i < this.layers.length; i++) {
        this.layer = this.layers[i];
        Heron.widgets.MultiLayerNode.superclass.onStoreUpdate.apply(this, arguments);
    }
}, destroy: function () {
    for (var i = 0; i < this.layers.length; i++) {
        this.layer = this.layers[i];
        Heron.widgets.MultiLayerNode.superclass.destroy.apply(this, arguments);
    }
}});
Ext.tree.TreePanel.nodeTypes.hr_multilayer = Heron.widgets.MultiLayerNode;
Ext.namespace("Heron.widgets.search");
Heron.widgets.search.OpenLSSearchCombo = Ext.extend(Ext.form.ComboBox, {map: null, width: 240, listWidth: 400, loadingText: __('Searching...'), emptyText: __('Search with OpenLS'), zoom: 8, minChars: 4, queryDelay: 200, maxRows: '10', url: 'http://geodata.nationaalgeoregister.nl/geocoder/Geocoder?', hideTrigger: true, displayField: 'text', forceSelection: false, autoSelect: false, queryParam: 'zoekterm', initComponent: function () {
    Heron.widgets.search.OpenLSSearchCombo.superclass.initComponent.apply(this, arguments);
    this.store = new Ext.data.Store({proxy: new Ext.data.HttpProxy({url: this.url, method: 'GET'}), fields: [
        {name: "lon", type: "number"},
        {name: "lat", type: "number"},
        "text"
    ], reader: new Heron.data.OpenLS_XLSReader()});
    if (this.zoom > 0) {
        this.on("select", function (combo, record, index) {
            this.setValue(record.data.text);
            var position = new OpenLayers.LonLat(record.data.lon, record.data.lat);
            position.transform(new OpenLayers.Projection("EPSG:28992"), this.map.getProjectionObject());
            this.map.setCenter(position, this.zoom);
            this.collapse();
        }, this);
    }
}});
Ext.reg('hr_openlssearchcombo', Heron.widgets.search.OpenLSSearchCombo);
Ext.namespace("Heron.widgets.search");
Heron.widgets.search.NominatimSearchCombo = Ext.extend(Ext.form.ComboBox, {map: null, width: 240, listWidth: 400, loadingText: __('Searching...'), emptyText: __('Search Nominatim'), zoom: 8, minChars: 4, queryDelay: 50, maxRows: '10', url: 'http://open.mapquestapi.com/nominatim/v1/search?format=json&addressdetails=1', storeFields: ["place_id", "display_name", {name: "address", type: "Object"}, "boundingbox", {name: "lat", type: "number"}, {name: "lon", type: "number"}], tpl: '<tpl for="."><tpl for="address"><div class="x-combo-list-item">{road} {postcode} {city} {country}</div></tpl></tpl>', displayTpl: '<tpl for="."><tpl for="address">{road} {city} {country}</tpl></tpl>', lang: 'en', charset: 'UTF8', hideTrigger: true, displayField: 'display_name', forceSelection: true, queryParam: 'q', initComponent: function () {
    if (this.displayTpl) {
        this.displayTplObj = new Ext.XTemplate(this.displayTpl);
    }
    Heron.widgets.search.NominatimSearchCombo.superclass.initComponent.apply(this, arguments);
    this.store = new Ext.data.JsonStore({proxy: new Ext.data.HttpProxy({url: this.url, method: 'GET'}), idProperty: 'place_id', successProperty: null, totalProperty: null, fields: this.storeFields});
    if (this.zoom > 0) {
        this.on("select", function (combo, record, index) {
            var result = record.data;
            var value = result[this.displayField];
            if (this.displayTplObj) {
                value = this.displayTplObj.apply(result);
            }
            this.setValue(value);
            var lonlat1 = new OpenLayers.LonLat(result.boundingbox[2], result.boundingbox[0]);
            var lonlat2 = new OpenLayers.LonLat(result.boundingbox[3], result.boundingbox[1]);
            lonlat1.transform(new OpenLayers.Projection("EPSG:4326"), this.map.getProjectionObject());
            lonlat2.transform(new OpenLayers.Projection("EPSG:4326"), this.map.getProjectionObject());
            var bounds = new OpenLayers.Bounds();
            bounds.extend(lonlat1);
            bounds.extend(lonlat2);
            this.map.zoomToExtent(bounds);
            this.collapse();
        }, this);
    }
}});
Ext.reg('hr_nominatimsearchcombo', Heron.widgets.search.NominatimSearchCombo);
Ext.namespace("Heron.widgets");
Heron.widgets.PrintPreviewWindow = Ext.extend(Ext.Window, {title: __('Print Preview'), printCapabilities: null, modal: true, border: false, resizable: false, width: 400, autoHeight: true, layout: 'fit', method: 'POST', showTitle: true, mapTitle: null, mapTitleYAML: "mapTitle", showComment: true, mapComment: null, mapCommentYAML: "mapComment", showFooter: true, mapFooter: null, mapFooterYAML: "mapFooter", printAttribution: true, mapAttribution: null, mapAttributionYAML: "mapAttribution", showRotation: true, showOutputFormats: false, showLegend: true, mapLegend: null, showLegendChecked: false, mapLimitScales: true, mapPreviewAutoHeight: true, mapPreviewHeight: 300, excludeLayers: ['OpenLayers.Handler.Polygon', 'OpenLayers.Handler.RegularPolygon', 'OpenLayers.Handler.Path', 'OpenLayers.Handler.Point'], legendDefaults: {useScaleParameter: true, baseParams: {FORMAT: "image/png"}}, initComponent: function () {
    if (this.hropts) {
        Ext.apply(this, this.hropts);
    }
    if (!this.url) {
        alert(__('No print provider url property passed in hropts.'));
        return;
    }
    var busyMask = new Ext.LoadMask(Ext.getBody(), {msg: __('Loading print data...')});
    busyMask.show();
    var self = this;
    Ext.Ajax.request({url: this.url + '/info.json', method: 'GET', params: null, success: function (result, request) {
        self.printCapabilities = Ext.decode(result.responseText);
        self.addItems();
        busyMask.hide();
    }, failure: function (result, request) {
        busyMask.hide();
        alert(__('Error getting Print options from server: ') + this.url);
    }});
    Heron.widgets.PrintPreviewWindow.superclass.initComponent.call(this);
}, addItems: function () {
    var legendPanel = new Heron.widgets.LayerLegendPanel({renderTo: document.body, hidden: true, defaults: this.legendDefaults});
    var self = this;
    var item = new GeoExt.ux.PrintPreview({autoHeight: true, printMapPanel: {limitScales: this.mapLimitScales, map: {controls: [new OpenLayers.Control.Navigation({zoomBoxEnabled: false, zoomWheelEnabled: (this.showRotation) ? true : false}), new OpenLayers.Control.Zoom()]}}, printProvider: {method: this.method, capabilities: this.printCapabilities, outputFormatsEnabled: this.showOutputFormats, listeners: {"print": function () {
        self.close();
    }, "printexception": function (printProvider, result) {
        alert(__('Error from Print server: ') + result.statusText);
    }, "beforeencodelayer": function (printProvider, layer) {
        for (var i = 0; i < self.excludeLayers.length; i++) {
            if (layer.name == self.excludeLayers[i]) {
                return false;
            }
        }
        return true;
    }}}, sourceMap: this.mapPanel, showTitle: this.showTitle, mapTitle: this.mapTitle, mapTitleYAML: this.mapTitleYAML, showComment: this.showComment, mapComment: this.mapComment, mapCommentYAML: this.mapCommentYAML, showFooter: this.showFooter, mapFooter: this.mapFooter, mapFooterYAML: this.mapFooterYAML, printAttribution: this.printAttribution, mapAttribution: this.mapAttribution, mapAttributionYAML: this.mapAttributionYAML, showRotation: this.showRotation, showOutputFormats: this.showOutputFormats, showLegend: this.showLegend, mapLegend: (this.showLegend) ? legendPanel : null, showLegendChecked: this.showLegendChecked, mapPreviewAutoHeight: this.mapPreviewAutoHeight, mapPreviewHeight: this.mapPreviewHeight});
    if (this.showRotation) {
        var ctrlPanel = new OpenLayers.Control.Zoom();
        item.printMapPanel.map.addControl(ctrlPanel);
    }
    this.add(item);
    this.doLayout();
    this.show();
    this.center();
}, listeners: {show: function (node) {
}}});
Ext.reg('hr_printpreviewwindow', Heron.widgets.PrintPreviewWindow);
Ext.namespace("Heron.widgets.search");
Heron.widgets.search.FeaturePanel = Ext.extend(Ext.Panel, {downloadable: true, displayPanels: ['Table'], exportFormats: ['CSV', 'XLS', 'GMLv2', 'GeoJSON', 'Shapefile', 'WellKnownText'], columnCapitalize: true, showTopToolbar: true, showGeometries: true, featureSelection: true, loadMask: true, exportConfigs: {CSV: {name: 'Comma Separated Values (CSV)', formatter: 'CSVFormatter', fileExt: '.csv', mimeType: 'text/csv'}, XLS: {name: 'Excel (XLS)', formatter: 'ExcelFormatter', fileExt: '.xls', mimeType: 'application/vnd.ms-excel'}, GMLv2: {name: 'GML v2', formatter: 'OpenLayersFormatter', format: new OpenLayers.Format.GML.v2({featureType: 'heronfeat', featureNS: 'http://heron-mc.org'}), fileExt: '.gml', mimeType: 'text/xml'}, GeoJSON: {name: 'GeoJSON', formatter: 'OpenLayersFormatter', format: 'OpenLayers.Format.GeoJSON', fileExt: '.json', mimeType: 'text/plain'}, Shapefile: {name: 'Esri Shapefile', formatter: 'OpenLayersFormatter', format: 'OpenLayers.Format.GeoJSON', targetFormat: 'ESRI Shapefile', fileExt: '.zip', mimeType: 'application/zip'}, WellKnownText: {name: 'Well-known Text (WKT)', formatter: 'OpenLayersFormatter', format: 'OpenLayers.Format.WKT', fileExt: '.wkt', mimeType: 'text/plain'}}, separateSelectionLayer: false, zoomOnFeatureSelect: false, zoomOnRowDoubleClick: true, zoomLevelPointSelect: 10, zoomLevelPoint: 10, zoomToDataExtent: false, autoConfig: true, autoConfigMaxSniff: 40, hideColumns: [], columnFixedWidth: 100, autoMaxWidth: 300, autoMinWidth: 45, vectorLayerOptions: {noLegend: true, displayInLayerSwitcher: false}, tableGrid: null, propGrid: null, mainPanel: null, store: null, initComponent: function () {
    Ext.apply(this, {layout: "fit"});
    if (this.columns) {
        this.autoConfig = false;
    }
    Ext.apply(this, this.hropts);
    if (this.featureSelection) {
        this.showGeometries = true;
    }
    if (this.showGeometries) {
        var layer = this.layer = new OpenLayers.Layer.Vector(this.title, this.vectorLayerOptions);
        this.map = Heron.App.getMap();
        this.map.addLayer(this.layer);
        var self = this;
        if (this.featureSelection && this.zoomOnFeatureSelect) {
            layer.events.on({"featureselected": function (e) {
                self.zoomToFeature(self, e.feature.geometry);
            }, "dblclick": function (e) {
                self.zoomToFeature(self, e.feature.geometry);
            }, "scope": layer});
        }
        if (this.separateSelectionLayer) {
            this.selLayer = new OpenLayers.Layer.Vector(this.title + '_Sel', {noLegend: true, displayInLayerSwitcher: false});
            this.selLayer.styleMap.styles['default'] = layer.styleMap.styles['select'];
            this.selLayer.style = this.selLayer.styleMap.styles['default'].defaultStyle;
            layer.styleMap.styles['select'] = layer.styleMap.styles['default'].clone();
            layer.styleMap.styles['select'].defaultStyle.fillColor = 'white';
            layer.styleMap.styles['select'].defaultStyle.fillOpacity = 0.0;
            this.map.addLayer(this.selLayer);
            this.map.setLayerIndex(this.selLayer, this.map.layers.length - 1);
            this.layer.events.on({featureselected: this.updateSelectionLayer, featureunselected: this.updateSelectionLayer, scope: this});
        }
    }
    this.setupStore(this.features);
    if (this.featureSelection && !this.sm) {
        this.sm = new GeoExt.grid.FeatureSelectionModel();
    }
    if (this.showTopToolbar) {
        this.tbar = this.createTopToolbar();
    }
    Heron.widgets.search.FeaturePanel.superclass.initComponent.call(this);
    this.tableGrid = new Ext.grid.GridPanel({id: 'grd_Table' + '_' + this.featureSetKey, store: this.store, title: this.title, autoScroll: true, featureType: this.featureType, header: false, features: this.features, autoConfig: this.autoConfig, autoConfigMaxSniff: this.autoConfigMaxSniff, hideColumns: this.hideColumns, columnFixedWidth: this.columnFixedWidth, autoMaxWidth: this.autoMaxWidth, autoMinWidth: this.autoMinWidth, columnCapitalize: this.columnCapitalize, showGeometries: this.showGeometries, featureSelection: this.featureSelection, gridCellRenderers: this.gridCellRenderers, columns: this.columns, showTopToolbar: this.showTopToolbar, exportFormats: this.exportFormats, hropts: {zoomOnRowDoubleClick: true, zoomOnFeatureSelect: false, zoomLevelPointSelect: 8}, sm: this.sm});
    if (this.zoomOnRowDoubleClick) {
        this.tableGrid.on('celldblclick', function (grid, rowIndex, columnIndex, e) {
            var record = grid.getStore().getAt(rowIndex);
            var feature = record.getFeature();
            self.zoomToFeature(self, feature.geometry);
        });
    }
    if ((this.displayPanels.indexOf('Table') >= 0) && (this.displayPanels.indexOf('Detail') >= 0)) {
        this.tableGrid.on('cellclick', function (grid, rowIndex, columnIndex, e) {
            if (columnIndex == 0) {
                self.displayVertical('goto', rowIndex);
            }
        });
    }
    if (this.displayPanels.indexOf('Detail') >= 0) {
        this.propGrid = new Ext.grid.PropertyGrid({id: 'grd_Detail' + '_' + this.featureSetKey, listeners: {'beforeedit': function (e) {
            return false;
        }}, title: this.title, featureType: this.featureType, header: false, features: this.features, autoConfig: this.autoConfig, autoConfigMaxSniff: this.autoConfigMaxSniff, autoHeight: false, hideColumns: this.hideColumns, columnFixedWidth: this.columnFixedWidth, autoMaxWidth: this.autoMaxWidth, autoMinWidth: this.autoMinWidth, columnCapitalize: this.columnCapitalize, showGeometries: this.showGeometries, featureSelection: this.featureSelection, gridCellRenderers: this.gridCellRenderers, columns: this.columns, showTopToolbar: this.showTopToolbar, exportFormats: this.exportFormats, curRecordNr: 0, hropts: {zoomOnRowDoubleClick: true, zoomOnFeatureSelect: false, zoomLevelPointSelect: 8}});
    }
    this.cardPanels = [];
    if (this.tableGrid)
        this.cardPanels.push(this.tableGrid);
    if (this.propGrid)
        this.cardPanels.push(this.propGrid);
    var activeItem = 0;
    if (this.displayPanels.length > 0) {
        activeItem = 'grd_' + this.displayPanels[0] + '_' + this.featureSetKey;
    }
    this.mainPanel = new Ext.Panel({border: false, activeItem: activeItem, layout: "card", items: this.cardPanels});
    this.add(this.mainPanel);
    if ((this.showTopToolbar) && (this.displayPanels.indexOf('Table') >= 0) && (this.displayPanels.indexOf('Detail') >= 0)) {
        this.tableGrid.addListener("activate", this.onActivateTable, this);
        this.propGrid.addListener("activate", this.onActivateDetail, this);
        this.tableGrid.addListener("afterlayout", this.onAfterlayoutTable, this);
        this.propGrid.addListener("afterlayout", this.onAfterlayoutDetail, this);
        this.topToolbar.addListener("afterlayout", this.onAfterlayoutTopToolbar, this);
    }
    this.addListener("afterrender", this.onPanelRendered, this);
    this.addListener("show", this.onPanelShow, this);
    this.addListener("hide", this.onPanelHide, this);
}, activateDisplayPanel: function (name) {
    if (!this.mainPanel.getLayout().setActiveItem) {
        return;
    }
    this.mainPanel.getLayout().setActiveItem("grd_" + name);
}, createTopToolbar: function () {
    var tbarItems = [this.tbarText = new Ext.Toolbar.TextItem({itemId: 'result', text: __(' ')})];
    tbarItems.push('->');
    if (this.downloadable) {
        var downloadMenuItems = [];
        var item;
        for (var j = 0; j < this.exportFormats.length; j++) {
            var exportFormat = this.exportFormats[j];
            var exportFormatConfig = exportFormat instanceof Object ? exportFormat : this.exportConfigs[exportFormat];
            if (!exportFormatConfig) {
                Ext.Msg.alert(__('Warning'), __('Invalid export format configured: ' + exportFormat));
                continue;
            }
            item = {text: __('as') + ' ' + exportFormatConfig.name, cls: 'x-btn', iconCls: 'icon-table-export', scope: this, exportFormatConfig: exportFormatConfig, handler: function (evt) {
                this.exportData(evt.exportFormatConfig);
            }};
            downloadMenuItems.push(item);
        }
        if (this.downloadInfo && this.downloadInfo.downloadFormats) {
            var downloadFormats = this.downloadInfo.downloadFormats;
            for (var k = 0; k < downloadFormats.length; k++) {
                var downloadFormat = downloadFormats[k];
                item = {text: __('as') + ' ' + downloadFormat.name, cls: 'x-btn', iconCls: 'icon-table-export', downloadFormat: downloadFormat.outputFormat, fileExt: downloadFormat.fileExt, scope: this, handler: function (evt) {
                    this.downloadData(evt.downloadFormat, evt.fileExt);
                }};
                downloadMenuItems.push(item);
            }
        }
        if (downloadMenuItems.length > 0) {
            tbarItems.push({itemId: 'download', text: __('Download'), cls: 'x-btn-text-icon', iconCls: 'icon-table-save', tooltip: __('Choose a Download Format'), menu: new Ext.menu.Menu({style: {overflow: 'visible'}, items: downloadMenuItems})});
        }
    }
    if ((this.showTopToolbar) && (this.displayPanels.indexOf('Table') >= 0) && (this.displayPanels.indexOf('Detail') >= 0)) {
        var blnTable = (this.displayPanels.indexOf('Detail') == 0);
        tbarItems.push('->');
        tbarItems.push({itemId: 'table-detail', text: (blnTable) ? __('Table') : __('Detail'), cls: 'x-btn-text-icon', iconCls: (blnTable) ? 'icon-table' : 'icon-detail', tooltip: (blnTable) ? __('Show record(s) in a table grid') : __('Show single record'), enableToggle: true, pressed: false, scope: this, handler: function (btn) {
            if (btn.pressed) {
                if (btn.iconCls == 'icon-table') {
                    this.displayGrid();
                } else {
                    var selRecord = Ext.data.Record;
                    selRecord = this.tableGrid.selModel.getSelected();
                    if (selRecord) {
                        var selIndex = this.tableGrid.store.indexOf(selRecord);
                        this.displayVertical('goto', selIndex);
                    }
                    else {
                        this.displayVertical('first');
                    }
                }
                btn.toggle(false, false);
            }
        }});
    }
    tbarItems.push('->');
    tbarItems.push({itemId: 'clear', text: __('Clear'), cls: 'x-btn-text-icon', iconCls: 'icon-table-clear', tooltip: __('Remove all results'), scope: this, handler: function () {
        this.removeFeatures();
    }});
    if ((this.showTopToolbar) && (this.displayPanels.indexOf('Detail') >= 0)) {
        tbarItems.push('->');
        tbarItems.push({itemId: 'nextrec', text: __(''), cls: 'x-btn-text-icon', iconCls: 'icon-arrow-right', tooltip: __('Show next record'), scope: this, visible: false, disabled: true, handler: function () {
            this.displayVertical('next');
        }});
        tbarItems.push('->');
        tbarItems.push({itemId: 'prevrec', text: __(''), cls: 'x-btn-text-icon', iconCls: 'icon-arrow-left', tooltip: __('Show previous record'), scope: this, hidden: true, disabled: true, handler: function () {
            this.displayVertical('previous');
        }});
    }
    return new Ext.Toolbar({enableOverflow: true, items: tbarItems});
}, displayGrid: function () {
    this.activateDisplayPanel('Table' + '_' + this.featureSetKey);
    this.updateTbarText('table');
}, displayVertical: function (action, intRecNew) {
    var column;
    var objCount = this.tableGrid.store ? this.tableGrid.store.getCount() : 0;
    if (objCount > 0) {
        switch (action) {
            case'first':
                this.propGrid.curRecordNr = 0;
                break;
            case'goto':
                this.propGrid.curRecordNr = intRecNew;
                break;
            case'previous':
                this.propGrid.curRecordNr--;
                break;
            case'next':
                this.propGrid.curRecordNr++;
                break;
        }
        var sourceStore = this.mainPanel.items.items[0].store.data.items[this.propGrid.curRecordNr].data.feature.attributes;
        this.propGrid.store.removeAll();
        for (var c = 0; c < this.columns.length; c++) {
            column = this.columns[c];
            if (column.dataIndex) {
                var rec = new Ext.grid.PropertyRecord({name: column.header, value: sourceStore[column.dataIndex]});
                this.propGrid.store.add(rec);
            }
        }
        if (action != 'first') {
            this.tableGrid.selModel.selectRow(this.propGrid.curRecordNr, false);
        }
    }
    if (objCount > 1) {
        this.topToolbar.items.get('prevrec').show();
        this.topToolbar.items.get('nextrec').show();
        if (this.propGrid.curRecordNr == objCount - 1) {
            this.topToolbar.items.get('prevrec').setDisabled(false);
            this.topToolbar.items.get('nextrec').setDisabled(true);
        }
        else if (this.propGrid.curRecordNr == 0) {
            this.topToolbar.items.get('prevrec').setDisabled(true);
            this.topToolbar.items.get('nextrec').setDisabled(false);
        }
        else {
            this.topToolbar.items.get('prevrec').setDisabled(false);
            this.topToolbar.items.get('nextrec').setDisabled(false);
        }
    } else {
        this.topToolbar.items.get('prevrec').hide();
        this.topToolbar.items.get('nextrec').hide();
    }
    this.activateDisplayPanel('Detail' + '_' + this.featureSetKey);
    this.updateTbarText('detail');
}, loadFeatures: function (features, featureType) {
    this.removeFeatures();
    this.featureType = featureType;
    if (!features || features.length == 0) {
        return;
    }
    this.showLayer();
    this.store.loadData(features);
    if (this.zoomToDataExtent) {
        if (features.length == 1 && features[0].geometry.CLASS_NAME == "OpenLayers.Geometry.Point") {
            var point = features[0].geometry.getCentroid();
            this.map.setCenter(new OpenLayers.LonLat(point.x, point.y), this.zoomLevelPoint);
        } else if (this.layer) {
            this.map.zoomToExtent(this.layer.getDataExtent());
        }
    }
    if (this.displayPanels.length > 0) {
        if (this.displayPanels[0] == 'Table')
            this.displayGrid(); else if (this.displayPanels[0] == 'Detail') {
            this.displayVertical('first');
        }
    }
}, hasFeatures: function () {
    return this.store && this.store.getCount() > 0;
}, removeFeatures: function () {
    if (this.store) {
        this.store.removeAll(false);
    }
    if ((this.propGrid) && (this.propGrid.store)) {
        this.propGrid.store.removeAll();
    }
    if (this.selLayer) {
        this.selLayer.removeAllFeatures({silent: true});
    }
    this.updateTbarText();
    if ((this.topToolbar) && (this.topToolbar.items.get('prevrec'))) {
        this.topToolbar.items.get('prevrec').hide();
        this.topToolbar.items.get('nextrec').hide();
    }
}, showLayer: function () {
    if (this.layer) {
        if (this.selLayer) {
            this.map.setLayerIndex(this.selLayer, this.map.layers.length - 1);
            this.map.setLayerIndex(this.layer, this.map.layers.length - 2);
        } else {
            this.map.setLayerIndex(this.layer, this.map.layers.length - 1);
        }
        if (!this.layer.getVisibility()) {
            this.layer.setVisibility(true);
        }
        if (this.selLayer && !this.selLayer.getVisibility()) {
            this.selLayer.setVisibility(true);
        }
    }
}, hideLayer: function () {
    if (this.layer && this.layer.getVisibility()) {
        this.layer.setVisibility(false);
    }
    if (this.selLayer && this.selLayer.getVisibility()) {
        this.selLayer.setVisibility(false);
    }
}, zoomToFeature: function (self, geometry) {
    if (!geometry) {
        return;
    }
    if (geometry.getVertices().length == 1) {
        var point = geometry.getCentroid();
        self.map.setCenter(new OpenLayers.LonLat(point.x, point.y), self.zoomLevelPointSelect);
    } else {
        self.map.zoomToExtent(geometry.getBounds());
    }
}, zoomButtonRenderer: function () {
    var id = Ext.id();
    (function () {
        new Ext.Button({renderTo: id, text: 'Zoom'});
    }).defer(25);
    return(String.format('<div id="{0}"></div>', id));
}, setupStore: function (features) {
    if (this.store && !this.autoConfig) {
        return;
    }
    var storeFields = [];
    var column;
    this.columns = this.columns == null ? [] : this.columns;
    var blnBtnExists = false;
    if ((this.columns[0] != null) && (this.columns[0].id == 'btn_detail'))
        blnBtnExists = true;
    if ((this.showTopToolbar) && (this.displayPanels.indexOf('Detail') >= 0) && (blnBtnExists == false)) {
        var columnDetail = new Ext.grid.Column({id: 'btn_detail', header: '', width: 20, tooltip: __('Show single record'), renderer: function (value, metadata, record, rowindex) {
            return('+');
        }});
        this.columns.splice(0, 0, columnDetail);
    }
    if (this.autoConfig && features) {
        var columnsFound = {};
        var columnsWidth = {};
        var suppressColumns = this.hideColumns.toString().toLowerCase();
        var defaultColumnWidth = this.columnFixedWidth;
        var autoMaxWidth = this.autoMaxWidth;
        var autoMinWidth = this.autoMinWidth;
        var arrLen = features.length <= this.autoConfigMaxSniff ? features.length : this.autoConfigMaxSniff;
        var pixelsPerCharacter = 7
        for (var i = 0; i < arrLen; i++) {
            var feature = features[i];
            var fieldName;
            var position = -1;
            for (fieldName in feature.attributes) {
                if (i > 0) {
                    position++;
                }
                if (columnsFound[fieldName] || suppressColumns.indexOf(fieldName.toLowerCase()) !== -1) {
                    continue;
                }
                column = {header: this.columnCapitalize ? fieldName.substr(0, 1).toUpperCase() + fieldName.substr(1).toLowerCase() : fieldName, width: defaultColumnWidth, dataIndex: fieldName, sortable: true};
                if (this.gridCellRenderers && this.featureType) {
                    var gridCellRenderer;
                    for (var k = 0; k < this.gridCellRenderers.length; k++) {
                        gridCellRenderer = this.gridCellRenderers[k];
                        if (gridCellRenderer.attrName && fieldName == gridCellRenderer.attrName) {
                            if (gridCellRenderer.featureType && this.featureType == gridCellRenderer.featureType || !gridCellRenderer.featureType) {
                                column.options = gridCellRenderer.renderer.options;
                                column.renderer = gridCellRenderer.renderer.fn;
                            }
                        }
                    }
                }
                if (autoMaxWidth > 0) {
                    if (!(fieldName in columnsWidth)) {
                        columnsWidth[fieldName] = fieldName.length * pixelsPerCharacter;
                        if (columnsWidth[fieldName] < autoMinWidth) {
                            columnsWidth[fieldName] = autoMinWidth;
                        }
                    }
                    if (feature.attributes[fieldName]) {
                        var columnWidth = feature.attributes[fieldName].length;
                        if (typeof(column.options) !== "undefined" && (typeof(column.options.attrPreTxt) !== "undefined")) {
                            columnWidth = columnWidth + column.options.attrPreTxt.length
                        }
                        columnWidth = columnWidth * pixelsPerCharacter;
                        if (columnWidth > columnsWidth[fieldName] && columnWidth <= autoMaxWidth) {
                            columnsWidth[fieldName] = columnWidth;
                        }
                    }
                }
                if (position >= 0 && position < this.columns.length) {
                    this.columns.splice(position, 0, column);
                } else {
                    this.columns.push(column);
                }
                storeFields.push({name: column.dataIndex});
                columnsFound[fieldName] = fieldName;
            }
        }
        if (autoMaxWidth > 0) {
            for (var key in this.columns) {
                if (columnsWidth[this.columns[key].dataIndex])
                    this.columns[key].width = columnsWidth[this.columns[key].dataIndex];
            }
        }
    } else {
        for (var c = 0; c < this.columns.length; c++) {
            column = this.columns[c];
            if (column.dataIndex) {
                storeFields.push({name: column.dataIndex, type: column.type});
            }
            column.sortable = true;
        }
    }
    var storeConfig = {layer: this.layer, fields: storeFields};
    Ext.apply(storeConfig, this.hropts.storeOpts);
    this.store = new GeoExt.data.FeatureStore(storeConfig);
}, updateSelectionLayer: function (evt) {
    if (!this.showGeometries) {
        return;
    }
    this.selLayer.removeAllFeatures({silent: true});
    var features = this.layer.selectedFeatures;
    for (var i = 0; i < features.length; i++) {
        var feature = features[i].clone();
        this.selLayer.addFeatures(feature);
    }
}, onActivateTable: function () {
    this.topToolbar.items.get('prevrec').hide();
    this.topToolbar.items.get('nextrec').hide();
    var btn = this.topToolbar.items.get('table-detail');
    btn.setText(__('Detail'));
    btn.setIconClass('icon-detail');
    btn.setTooltip(__('Show single record'));
}, onActivateDetail: function () {
    var btn = this.topToolbar.items.get('table-detail');
    btn.setText(__('Table'))
    btn.setIconClass('icon-table');
    btn.setTooltip(__('Show record(s) in a table grid'));
    this.tableGrid.selModel.selectRow(this.propGrid.curRecordNr, false);
}, onAfterlayoutTable: function () {
    this.activePanel = 'Table';
}, onAfterlayoutDetail: function () {
    this.activePanel = 'Detail';
}, onAfterlayoutTopToolbar: function () {
    var objCount = this.tableGrid.store ? this.tableGrid.store.getCount() : 0;
    if ((this.activePanel == 'Table') || (objCount <= 1)) {
        this.topToolbar.items.get('prevrec').hide();
        this.topToolbar.items.get('nextrec').hide();
    } else {
        this.topToolbar.items.get('prevrec').show();
        this.topToolbar.items.get('nextrec').show();
    }
}, onPanelRendered: function () {
    if (this.ownerCt) {
        this.ownerCt.addListener("parenthide", this.onParentHide, this);
        this.ownerCt.addListener("parentshow", this.onParentShow, this);
    }
}, onPanelShow: function () {
    if (this.selModel && this.selModel.selectControl) {
        this.selModel.selectControl.activate();
    }
}, onPanelHide: function () {
    if (this.selModel && this.selModel.selectControl) {
        this.selModel.selectControl.deactivate();
    }
}, onParentShow: function () {
    this.showLayer();
}, onParentHide: function () {
    this.removeFeatures();
    this.hideLayer();
}, cleanup: function () {
    this.removeFeatures();
    if (this.selModel && this.selModel.selectControl) {
        this.selModel.selectControl.deactivate();
        this.selModel = null;
    }
    if (this.layer) {
        this.map.removeLayer(this.layer);
    }
    if (this.selLayer) {
        this.map.removeLayer(this.selLayer);
    }
    return true;
}, updateTbarText: function (type) {
    if (!this.tbarText) {
        return;
    }
    var objCount = this.store ? this.store.getCount() : 0;
    if ((type) && (type == 'detail') && (objCount > 0))
        this.tbarText.setText(__('Result') + ' ' + (this.propGrid.curRecordNr + 1) + ' ' + __('of') + ' ' + objCount); else
        this.tbarText.setText(objCount + ' ' + (objCount != 1 ? __('Results') : __('Result')));
}, exportData: function (config) {
    var store = this.store;
    var featureType = this.featureType ? this.featureType : 'heron';
    config.fileName = featureType + config.fileExt;
    config.columns = (store.fields && store.fields.items && store.fields.items.length > 3) ? store.fields.items.slice(3) : null;
    if (store.layer && store.layer.projection) {
        config.assignSrs = store.layer.projection.getCode();
    }
    config.encoding = 'base64';
    var data = Heron.data.DataExporter.formatStore(store, config);
    Heron.data.DataExporter.download(data, config);
}, downloadData: function (downloadFormat, fileExt) {
    var downloadInfo = this.downloadInfo;
    downloadInfo.params.outputFormat = downloadFormat;
    downloadInfo.params.filename = downloadInfo.params.typename + fileExt;
    var paramStr = OpenLayers.Util.getParameterString(downloadInfo.params);
    var url = OpenLayers.Util.urlAppend(downloadInfo.url, paramStr);
    if (url.length > 2048) {
        Ext.Msg.alert(__('Warning'), __('Download URL string too long (max 2048 chars): ') + url.length);
        return;
    }
    Heron.data.DataExporter.directDownload(url);
}});
Ext.reg('hr_featurepanel', Heron.widgets.search.FeaturePanel);
Ext.reg('hr_featuregridpanel', Heron.widgets.search.FeaturePanel);
Ext.reg('hr_featselgridpanel', Heron.widgets.search.FeaturePanel);
Ext.namespace("Heron.widgets.search");
Heron.widgets.search.SearchCenterPanel = Ext.extend(Ext.Panel, {initComponent: function () {
    var self = this;
    Ext.apply(this, {layout: 'card', activeItem: 0, bbar: [
        {text: __('< Search'), ref: '../prevButton', disabled: true, handler: function () {
            self.showSearchPanel(self);
        }},
        '->',
        {text: __('Result >'), ref: '../nextButton', disabled: true, handler: function () {
            self.showResultGridPanel(self);
        }}
    ]});
    if (!this.items) {
        this.items = [this.hropts.searchPanel];
    }
    if (this.ownerCt) {
        this.ownerCt.addListener("hide", this.onParentHide, this);
        this.ownerCt.addListener("show", this.onParentShow, this);
        this.addEvents({"parenthide": true, "parentshow": true});
    }
    Heron.widgets.search.SearchCenterPanel.superclass.initComponent.call(this);
    this.addListener("afterrender", this.onRendered, this);
}, showSearchPanel: function (self) {
    self.getLayout().setActiveItem(this.searchPanel);
    self.prevButton.disable();
    self.nextButton.disable();
    if (this.resultPanel && this.resultPanel.hasFeatures()) {
        self.nextButton.enable();
    }
}, showResultGridPanel: function (self) {
    self.getLayout().setActiveItem(this.resultPanel);
    self.prevButton.enable();
    self.nextButton.disable();
}, onRendered: function () {
    this.searchPanel = this.getComponent(0);
    if (this.searchPanel) {
        this.searchPanel.addListener('searchissued', this.onSearchIssued, this);
        this.searchPanel.addListener('searchsuccess', this.onSearchSuccess, this);
        this.searchPanel.addListener('searchcomplete', this.onSearchComplete, this);
        this.searchPanel.addListener('searchreset', this.onSearchReset, this);
    }
}, onSearchIssued: function (searchPanel) {
    this.showSearchPanel(this);
    this.nextButton.disable();
}, onSearchComplete: function (searchPanel) {
}, onSearchReset: function (searchPanel) {
    if (this.resultPanel) {
        this.resultPanel.removeFeatures();
    }
}, onSearchSuccess: function (searchPanel, result) {
    if (this.hropts.resultPanel.autoConfig && this.resultPanel) {
        this.resultPanel.cleanup();
        this.remove(this.resultPanel);
        this.resultPanel = null;
    }
    var features = result.olResponse.features;
    if (!this.resultPanel) {
        this.hropts.resultPanel.features = features;
        this.hropts.resultPanel.downloadInfo = result.downloadInfo;
        this.hropts.resultPanel.featureType = searchPanel.getFeatureType();
        this.resultPanel = new Heron.widgets.search.FeaturePanel(this.hropts.resultPanel);
        this.add(this.resultPanel);
    }
    this.resultPanel.loadFeatures(features, searchPanel.getFeatureType());
    if (features && features.length > 0) {
        this.showResultGridPanel(this);
    }
}, onParentShow: function () {
    if (this.resultPanel) {
        this.showSearchPanel(this);
    }
    this.fireEvent('parentshow');
}, onParentHide: function () {
    this.fireEvent('parenthide');
}});
Ext.reg('hr_searchcenterpanel', Heron.widgets.search.SearchCenterPanel);
Ext.reg('hr_featselsearchpanel', Heron.widgets.search.SearchCenterPanel);
Ext.namespace("Heron.widgets.search");
Heron.widgets.search.MultiSearchCenterPanel = Ext.extend(Heron.widgets.search.SearchCenterPanel, {config: [], initComponent: function () {
    this.config = this.hropts;
    var searchNames = [];
    Ext.each(this.config, function (item) {
        searchNames.push(item.searchPanel.name ? item.searchPanel.name : __('Undefined (check your config)'));
    });
    this.combo = new Ext.form.ComboBox({store: searchNames, value: searchNames[0], editable: false, typeAhead: false, triggerAction: 'all', emptyText: __('Select a search...'), selectOnFocus: true, width: 250, listeners: {scope: this, 'select': this.onSearchSelect}});
    this.tbar = [
        {'text': __('Search') + ': '},
        this.combo
    ];
    this.setPanels(this.config[0].searchPanel, this.config[0].resultPanel);
    Heron.widgets.search.MultiSearchCenterPanel.superclass.initComponent.call(this);
}, onSearchSelect: function (comboBox) {
    var self = this;
    Ext.each(this.config, function (item) {
        if (item.searchPanel.name == comboBox.value) {
            self.switchPanels(item.searchPanel, item.resultPanel);
        }
    });
    this.showSearchPanel(this);
}, onSearchSuccess: function (searchPanel, result) {
    Heron.widgets.search.MultiSearchCenterPanel.superclass.onSearchSuccess.call(this, searchPanel, result);
    this.lastResultFeatures = result.features;
}, setPanels: function (searchPanel, resultPanel) {
    if (this.hropts.searchPanel && this.hropts.searchPanel.name === searchPanel.name) {
        return false;
    }
    this.hropts.searchPanel = searchPanel;
    this.hropts.resultPanel = resultPanel;
    return true;
}, switchPanels: function (searchPanel, resultPanel) {
    if (!this.setPanels(searchPanel, resultPanel)) {
        return;
    }
    if (this.searchPanel) {
        this.lastSearchName = this.searchPanel.name;
        this.remove(this.searchPanel, true);
    }
    if (this.resultPanel) {
        this.resultPanel.cleanup();
        this.remove(this.resultPanel, true);
        this.resultPanel = null;
    }
    if (this.hropts.searchPanel.hropts && this.hropts.searchPanel.hropts.fromLastResult) {
        this.hropts.searchPanel.hropts.filterFeatures = this.lastResultFeatures;
        this.hropts.searchPanel.hropts.lastSearchName = this.lastSearchName;
    }
    this.searchPanel = Ext.create(this.hropts.searchPanel);
    this.add(this.searchPanel);
    this.searchPanel.show();
    this.getLayout().setActiveItem(this.searchPanel);
    this.onRendered();
}});
Ext.reg('hr_multisearchcenterpanel', Heron.widgets.search.MultiSearchCenterPanel);
Ext.namespace("Heron.widgets.search");
Heron.widgets.search.FormSearchPanel = Ext.extend(GeoExt.form.FormPanel, {onSearchCompleteZoom: 11, autoWildCardAttach: false, caseInsensitiveMatch: false, logicalOperator: OpenLayers.Filter.Logical.AND, layerOpts: undefined, statusPanelOpts: {html: '&nbsp;', height: 132, preventBodyReset: true, bodyCfg: {style: {padding: '6px', border: '0px'}}, style: {marginTop: '24px', paddingTop: '24px', fontFamily: 'Verdana, Arial, Helvetica, sans-serif', fontSize: '11px', color: '#0000C0'}}, progressMessages: [__('Working on it...'), __('Still searching, please be patient...')], header: true, bodyStyle: 'padding: 6px', style: {fontFamily: 'Verdana, Arial, Helvetica, sans-serif', fontSize: '12px'}, downloadFormats: [], defaults: {enableKeyEvents: true, listeners: {specialKey: function (field, el) {
    if (el.getKey() == Ext.EventObject.ENTER) {
        var form = this.ownerCt;
        if (!form && !form.search) {
            return;
        }
        form.action = null;
        form.search();
    }
}}}, initComponent: function () {
    this.addEvents({"searchcomplete": true, "searchcanceled": true, "searchfailed": true, "searchsuccess": true});
    Ext.apply(this, this.hropts);
    Heron.widgets.search.FormSearchPanel.superclass.initComponent.call(this);
    if (this.protocol && this.protocol.url instanceof Array) {
        this.protocol.url = Heron.Utils.randArrayElm(this.protocol.url);
        this.protocol.options.url = this.protocol.url;
    }
    var items = [this.createStatusPanel(), this.createActionButtons()];
    this.add(items);
    this.addListener("beforeaction", this.onSearchIssued, this);
    this.addListener("searchcanceled", this.onSearchCanceled, this);
    this.addListener("actioncomplete", this.onSearchComplete, this);
    this.addListener("actionfailed", this.onSearchFailed, this);
}, createActionButtons: function () {
    this.searchButton = new Ext.Button({text: __('Search'), tooltip: __('Search'), disabled: false, handler: function () {
        this.search();
    }, scope: this});
    this.cancelButton = new Ext.Button({text: 'Cancel', tooltip: __('Cancel ongoing search'), disabled: true, handler: function () {
        this.fireEvent('searchcanceled', this);
    }, scope: this});
    return this.actionButtons = new Ext.ButtonGroup({fieldLabel: null, labelSeparator: '', anchor: "-50", title: null, border: false, bodyBorder: false, items: [this.cancelButton, this.searchButton]});
}, createStatusPanel: function () {
    return this.statusPanel = new Heron.widgets.HTMLPanel(this.statusPanelOpts);
}, updateStatusPanel: function (text) {
    if (!text) {
        text = '&nbsp;';
    }
    if (this.statusPanel.body) {
        this.statusPanel.body.update(text);
    } else {
        this.statusPanel.html = text;
    }
}, getFeatureType: function () {
    return this.protocol ? this.protocol.featureType : 'heron';
}, onSearchIssued: function (form, action) {
    this.protocol = action.form.protocol;
    this.searchState = "searchissued";
    this.features = null;
    this.updateStatusPanel(__('Searching...'));
    this.cancelButton.enable();
    this.searchButton.disable();
    var self = this;
    var startTime = new Date().getTime() / 1000;
    this.timer = setInterval(function () {
        if (self.searchState != 'searchissued') {
            return;
        }
        self.updateStatusPanel(Math.floor(new Date().getTime() / 1000 - startTime) + ' ' + __('Seconds') + ' - ' +
            Heron.Utils.randArrayElm(self.progressMessages));
    }, 4000);
}, onSearchFailed: function (form, action) {
    this.searchAbort();
}, onSearchCanceled: function (searchPanel) {
    this.searchState = 'searchcanceled';
    this.searchAbort();
    this.updateStatusPanel(__('Search Canceled'));
}, onSearchComplete: function (form, action) {
    if (this.timer) {
        clearInterval(this.timer);
        this.timer = null;
    }
    this.cancelButton.disable();
    this.searchButton.enable();
    if (this.searchState == 'searchcanceled') {
        this.searchState = null;
        return;
    }
    this.searchState = "searchcomplete";
    var result = {olResponse: action.response};
    this.fireEvent('searchcomplete', this, result);
    if (action && action.response && action.response.success()) {
        var features = this.features = action.response.features;
        var featureCount = features ? features.length : 0;
        this.updateStatusPanel(__('Search Completed: ') + featureCount + ' ' + (featureCount != 1 ? __('Results') : __('Result')));
        var filter = GeoExt.form.toFilter(action.form, action.options);
        var filterFormat = new OpenLayers.Format.Filter.v1_0_0({srsName: this.protocol.srsName});
        var filterStr = OpenLayers.Format.XML.prototype.write.apply(filterFormat, [filterFormat.write(filter)]);
        result.downloadInfo = {type: 'wfs', url: this.protocol.options.url, downloadFormats: this.downloadFormats, params: {typename: this.protocol.featureType, maxFeatures: this.protocol.maxFeatures, "Content-Disposition": "attachment", filename: this.protocol.featureType, srsName: this.protocol.srsName, service: "WFS", version: "1.0.0", request: "GetFeature", filter: filterStr}};
        if (this.onSearchCompleteAction) {
            var lropts = this.layerOpts;
            if (lropts) {
                var map = Heron.App.getMap();
                for (var l = 0; l < lropts.length; l++) {
                    if (lropts[l]['layerOn']) {
                        var mapLayers = map.getLayersByName(lropts[l]['layerOn']);
                        for (var n = 0; n < mapLayers.length; n++) {
                            if (mapLayers[n].isBaseLayer) {
                                map.setBaseLayer(mapLayers[n]);
                            } else {
                                mapLayers[n].setVisibility(true);
                            }
                            if (lropts[l]['layerOpacity']) {
                                mapLayers[n].setOpacity(lropts[l]['layerOpacity']);
                            }
                        }
                    }
                }
            }
            this.onSearchCompleteAction(result);
        }
        this.fireEvent('searchsuccess', this, result);
    } else {
        this.fireEvent('searchfailed', this, action.response);
        this.updateStatusPanel(__('Search Failed') + ' details: ' + action.response.priv.responseText);
    }
}, onSearchCompleteAction: function (result) {
    var features = result.olResponse.features;
    if (!features || features.length == 0) {
        return;
    }
    var map = Heron.App.getMap();
    if (features.length == 1 && features[0].geometry.CLASS_NAME == "OpenLayers.Geometry.Point" && this.onSearchCompleteZoom) {
        var point = features[0].geometry.getCentroid();
        map.setCenter(new OpenLayers.LonLat(point.x, point.y), this.onSearchCompleteZoom);
    } else {
        var geometryCollection = new OpenLayers.Geometry.Collection();
        for (var i = 0; i < features.length; i++) {
            geometryCollection.addComponent(features[i].geometry);
        }
        geometryCollection.calculateBounds();
        map.zoomToExtent(geometryCollection.getBounds());
    }
}, search: function () {
    this.action = null;
    Heron.widgets.search.FormSearchPanel.superclass.search.call(this, {wildcard: this.autoWildCardAttach ? GeoExt.form.CONTAINS : -1, matchCase: !this.caseInsensitiveMatch, logicalOp: this.logicalOperator});
}, searchAbort: function () {
    if (this.timer) {
        clearInterval(this.timer);
        this.timer = null;
    }
    if (this.protocol) {
        this.protocol.abort(this.response);
    }
    this.protocol = null;
    this.searchButton.enable();
    this.cancelButton.disable();
    this.updateStatusPanel(__('Search aborted'));
}});
Ext.reg('hr_formsearchpanel', Heron.widgets.search.FormSearchPanel);
Ext.reg('hr_searchpanel', Heron.widgets.search.FormSearchPanel);
Ext.namespace("Heron.widgets.search");
Heron.widgets.search.SpatialSearchPanel = Ext.extend(Ext.Panel, {layout: 'form', bodyStyle: 'padding: 24px 12px 12px 12px', border: false, name: __('Spatial Search'), description: '', fromLastResult: false, lastSearchName: null, filterFeatures: null, showFilterFeatures: true, maxFilterGeometries: 24, selectLayerStyle: {pointRadius: 10, strokeColor: "#dd0000", strokeWidth: 1, fillOpacity: 0.4, fillColor: "#cc0000"}, layerSortOrder: 'ASC', downloadFormats: null, layerFilter: function (map) {
    return map.getLayersBy('metadata', {test: function (metadata) {
        return metadata && metadata.wfs;
    }})
}, progressMessages: [__('Working on it...'), __('Still searching, please be patient...'), __('Still searching, have you selected an area with too many objects?')], initComponent: function () {
    this.addEvents(this.getEvents());
    Heron.widgets.search.SpatialSearchPanel.superclass.initComponent.call(this);
    this.map = Heron.App.getMap();
    this.addSelectionLayer();
    this.addListener("selectionlayerupdate", this.onSelectionLayerUpdate, this);
    this.addListener("targetlayerselected", this.onTargetLayerSelected, this);
    this.addListener("drawingcomplete", this.onDrawingComplete, this);
    this.addListener("searchissued", this.onSearchIssued, this);
    this.addListener("searchcomplete", this.onSearchComplete, this);
    this.addListener("searchcanceled", this.onSearchCanceled, this);
    this.addListener("beforehide", this.onBeforeHide, this);
    this.addListener("beforeshow", this.onBeforeShow, this);
    this.addListener("beforedestroy", this.onBeforeDestroy, this);
    this.addListener("afterrender", this.onPanelRendered, this);
    if (this.ownerCt) {
        this.ownerCt.addListener("parenthide", this.onParentHide, this);
        this.ownerCt.addListener("parentshow", this.onParentShow, this);
    }
}, addSelectionLayer: function () {
    if (this.selectionLayer) {
        return;
    }
    this.selectionLayer = new OpenLayers.Layer.Vector(__('Selection'), {style: this.selectLayerStyle, displayInLayerSwitcher: false, hideInLegend: false, isBaseLayer: false});
    this.map.addLayers([this.selectionLayer]);
}, getEvents: function () {
    return{"drawcontroladded": true, "selectionlayerupdate": true, "targetlayerselected": true, "drawingcomplete": true, "searchissued": true, "searchcomplete": true, "searchcanceled": true, "searchfailed": true, "searchsuccess": true, "searchreset": true};
}, createStatusPanel: function () {
    var infoText = __('Select the Layer to query') + '<p>' + this.description + '</p>';
    if (this.lastSearchName) {
        infoText += '<p>' + __('Using geometries from the result: ') + '<br/>' + this.lastSearchName;
        if (this.filterFeatures) {
            infoText += '<br/>' + __('with') + ' ' + this.filterFeatures.length + ' ' + __('features');
        }
        infoText += '</p>';
    }
    this.statusPanel = new Heron.widgets.HTMLPanel({html: infoText, preventBodyReset: true, bodyCfg: {style: {padding: '6px', border: '1px'}}, style: {marginTop: '10px', marginBottom: '10px', fontFamily: 'Verdana, Arial, Helvetica, sans-serif', fontSize: '11px', color: '#0000C0'}});
    return this.statusPanel;
}, createDrawToolPanel: function (config) {
    var defaultConfig = {html: '<div class="olControlEditingToolbar olControlNoSelect">&nbsp;</div>', preventBodyReset: true, style: {marginTop: '32px', marginBottom: '24px'}, activateControl: true, listeners: {afterrender: function (htmlPanel) {
        var div = htmlPanel.body.dom.firstChild;
        if (!div) {
            Ext.Msg.alert('Warning', 'Cannot render draw controls');
            return;
        }
        this.addDrawControls(div);
        if (this.activateControl) {
            this.activateDrawControl();
        }
    }, scope: this}};
    config = Ext.apply(defaultConfig, config);
    return this.drawToolPanel = new Heron.widgets.HTMLPanel(config);
}, addDrawControls: function (div) {
    this.drawControl = new OpenLayers.Control.EditingToolbar(this.selectionLayer, {div: div});
    this.drawControl.controls[0].panel_div.title = __('Return to map navigation');
    this.drawControl.controls[1].panel_div.title = __('Draw point');
    this.drawControl.controls[2].panel_div.title = __('Draw line');
    this.drawControl.controls[3].panel_div.title = __('Draw polygon');
    var drawCircleControl = new OpenLayers.Control.DrawFeature(this.selectionLayer, OpenLayers.Handler.RegularPolygon, {title: __('Draw circle (click and drag)'), displayClass: 'olControlDrawCircle', handlerOptions: {citeCompliant: this.drawControl.citeCompliant, sides: 30, irregular: false}});
    this.drawControl.addControls([drawCircleControl]);
    var drawRectangleControl = new OpenLayers.Control.DrawFeature(this.selectionLayer, OpenLayers.Handler.RegularPolygon, {displayClass: 'olControlDrawRectangle', title: __('Draw Rectangle (click and drag)'), handlerOptions: {citeCompliant: this.drawControl.citeCompliant, sides: 4, irregular: true}});
    this.drawControl.addControls([drawRectangleControl]);
    this.map.addControl(this.drawControl);
    this.activeControl = drawRectangleControl;
    this.fireEvent('drawcontroladded');
}, removeDrawControls: function () {
    if (this.drawControl) {
        var self = this;
        Ext.each(this.drawControl.controls, function (control) {
            self.map.removeControl(control);
        });
        this.map.removeControl(this.drawControl);
        this.drawControl = null;
    }
}, activateDrawControl: function () {
    if (!this.drawControl || this.drawControlActive) {
        return;
    }
    var self = this;
    Ext.each(this.drawControl.controls, function (control) {
        control.events.register('featureadded', self, self.onFeatureDrawn);
        control.deactivate();
        if (self.activeControl && control == self.activeControl) {
            control.activate();
        }
    });
    this.drawControlActive = true;
}, deactivateDrawControl: function () {
    if (!this.drawControl) {
        return;
    }
    var self = this;
    Ext.each(this.drawControl.controls, function (control) {
        control.events.unregister('featureadded', self, self.onFeatureDrawn);
        if (control.active) {
            self.activeControl = control;
        }
        control.deactivate();
    });
    this.updateStatusPanel();
    this.drawControlActive = false;
}, onFeatureDrawn: function () {
}, createTargetLayerCombo: function (config) {
    var defaultConfig = {fieldLabel: __('Search in'), sortOrder: this.layerSortOrder, layerFilter: this.layerFilter, selectFirst: true, listeners: {selectlayer: function (layer) {
        this.targetLayer = layer;
        this.fireEvent('targetlayerselected');
    }, scope: this}};
    config = Ext.apply(defaultConfig, config);
    return this.targetLayerCombo = new Heron.widgets.LayerCombo(config);
}, getFeatureType: function () {
    return this.lastFeatureType ? this.lastFeatureType : (this.targetLayer ? this.targetLayer.name : 'heron');
}, updateStatusPanel: function (text) {
    if (!text) {
        text = '&nbsp;';
    }
    if (this.statusPanel.body) {
        this.statusPanel.body.update(text);
    } else {
        this.statusPanel.html = text;
    }
}, onBeforeHide: function () {
    if (this.selectionLayer) {
        this.selectionLayer.setVisibility(false);
    }
}, onBeforeShow: function () {
    if (this.selectionLayer) {
        this.selectionLayer.setVisibility(true);
    }
}, onBeforeDestroy: function () {
    this.deactivateDrawControl();
    if (this.selectionLayer) {
        this.selectionLayer.removeAllFeatures();
        this.map.removeLayer(this.selectionLayer);
    }
}, onDrawingComplete: function (searchPanel, selectionLayer) {
}, onTargetLayerSelected: function () {
}, onSelectionLayerUpdate: function () {
}, onSearchIssued: function () {
    this.searchState = "searchissued";
    this.response = null;
    this.features = null;
    this.updateStatusPanel(__('Searching...'));
    var self = this;
    var startTime = new Date().getTime() / 1000;
    this.timer = setInterval(function () {
        if (self.searchState != 'searchissued') {
            return;
        }
        self.updateStatusPanel(Math.floor(new Date().getTime() / 1000 - startTime) + ' ' + __('Seconds') + ' - ' +
            Heron.Utils.randArrayElm(self.progressMessages));
    }, 4000);
}, onSearchCanceled: function (searchPanel) {
    this.searchState = 'searchcanceled';
    this.searchAbort();
    this.updateStatusPanel(__('Search Canceled'));
}, onSearchComplete: function (searchPanel, result) {
    this.protocol = null;
    if (this.timer) {
        clearInterval(this.timer);
        this.timer = null;
    }
    if (this.sketch) {
        this.selectionLayer.removeAllFeatures();
        this.sketch = false;
    }
    this.fireEvent('selectionlayerupdate');
    if (this.searchState == 'searchcanceled') {
        this.fireEvent('searchfailed', searchPanel, null);
        return;
    }
    this.searchState = "searchcomplete";
    var olResponse = result.olResponse;
    if (!olResponse || !olResponse.success() || olResponse.priv.responseText.indexOf('ExceptionReport') > 0) {
        this.fireEvent('searchfailed', searchPanel, olResponse);
        this.updateStatusPanel(__('Search Failed') + ' details: ' + olResponse.priv.responseText);
        return;
    }
    this.onSearchSuccess(searchPanel, result);
}, onSearchSuccess: function (searchPanel, result) {
    var features = this.features = this.filterFeatures = result.olResponse.features;
    var featureCount = features ? features.length : 0;
    this.updateStatusPanel(__('Search Completed: ') + featureCount + ' ' + (featureCount != 1 ? __('Results') : __('Result')));
    this.fireEvent('searchsuccess', searchPanel, result);
}, search: function (geometries, options) {
    var targetLayer = options.targetLayer;
    var wfsOptions = targetLayer.metadata.wfs;
    if (wfsOptions.protocol == 'fromWMSLayer') {
        this.protocol = OpenLayers.Protocol.WFS.fromWMSLayer(targetLayer, {outputFormat: 'GML2'});
        if (this.protocol.url instanceof Array) {
            this.protocol.url = Heron.Utils.randArrayElm(this.protocol.url);
            this.protocol.options.url = this.protocol.url;
        }
    } else {
        this.protocol = wfsOptions.protocol;
    }
    this.lastFeatureType = this.protocol.featureType;
    var geometry = geometries[0];
    var spatialFilterType = options.spatialFilterType || OpenLayers.Filter.Spatial.INTERSECTS;
    var filter = new OpenLayers.Filter.Spatial({type: spatialFilterType, value: geometry});
    if (geometries.length > 1) {
        var filters = [];
        geometry = new OpenLayers.Geometry.Collection();
        Ext.each(geometries, function (g) {
            geometry.addComponent(g);
            filters.push(new OpenLayers.Filter.Spatial({type: OpenLayers.Filter.Spatial.INTERSECTS, value: g}));
        });
        filter = new OpenLayers.Filter.Logical({type: OpenLayers.Filter.Logical.OR, filters: filters});
    }
    if (geometry.CLASS_NAME.indexOf('LineString') > 0 && wfsOptions.maxQueryLength) {
        var length = Math.round(geometry.getGeodesicLength(options.projection));
        if (length > wfsOptions.maxQueryLength) {
            this.selectionLayer.removeAllFeatures();
            var units = options.units;
            Ext.Msg.alert(__('Warning - Line Length is ') + length + units, __('You drew a line with length above the layer-maximum of ') + wfsOptions.maxQueryLength + units);
            return false;
        }
    }
    if (geometry.CLASS_NAME.indexOf('Polygon') > 0 && wfsOptions.maxQueryArea) {
        var area = Math.round(geometry.getGeodesicArea(options.projection));
        if (area > wfsOptions.maxQueryArea) {
            this.selectionLayer.removeAllFeatures();
            var areaUnits = options.units + '2';
            Ext.Msg.alert(__('Warning - Area is ') + area + areaUnits, __('You selected an area for this layer above its maximum of ') + wfsOptions.maxQueryArea + areaUnits);
            return false;
        }
    }
    var filterFormat = new OpenLayers.Format.Filter.v1_0_0({srsName: this.protocol.srsName});
    var filterStr = OpenLayers.Format.XML.prototype.write.apply(filterFormat, [filterFormat.write(filter)]);
    var maxFeatures = this.single == true ? this.maxFeatures : undefined;
    this.response = this.protocol.read({maxFeatures: maxFeatures, filter: filter, callback: function (olResponse) {
        if (!this.protocol) {
            return;
        }
        var downloadInfo = {type: 'wfs', url: this.protocol.options.url, downloadFormats: this.downloadFormats ? this.downloadFormats : wfsOptions.downloadFormats, params: {typename: this.protocol.featureType, maxFeatures: maxFeatures, "Content-Disposition": "attachment", filename: targetLayer.name, srsName: this.protocol.srsName, service: "WFS", version: "1.0.0", request: "GetFeature", filter: filterStr}};
        var result = {olResponse: olResponse, downloadInfo: downloadInfo, layer: targetLayer};
        this.fireEvent('searchcomplete', this, result);
    }, scope: this});
    this.fireEvent('searchissued', this);
    return true;
}, searchAbort: function () {
    if (this.protocol) {
        this.protocol.abort(this.response);
    }
    this.protocol = null;
    if (this.timer) {
        clearInterval(this.timer);
        this.timer = null;
    }
}});
Ext.reg('hr_spatialsearchpanel', Heron.widgets.search.SpatialSearchPanel);
Ext.namespace("Heron.widgets.search");
Heron.widgets.search.SearchByDrawPanel = Ext.extend(Heron.widgets.search.SpatialSearchPanel, {name: __('Search by Drawing'), initComponent: function () {
    this.items = [this.createTargetLayerCombo(), this.createDrawToolPanel(), this.createStatusPanel(), this.createActionButtons()];
    Heron.widgets.search.SearchByDrawPanel.superclass.initComponent.call(this);
    this.addListener("drawcontroladded", this.activateDrawControl, this);
}, createActionButtons: function () {
    return this.cancelButton = new Ext.Button({text: __('Cancel'), tooltip: __('Cancel ongoing search'), disabled: true, handler: function () {
        this.fireEvent('searchcanceled', this);
    }, scope: this});
}, onDrawingComplete: function (searchPanel, selectionLayer) {
    this.searchFromSketch();
}, onFeatureDrawn: function () {
    this.fireEvent('drawingcomplete', this, this.selectionLayer);
}, onPanelRendered: function () {
    this.updateStatusPanel(__('Select a drawing tool and draw to search immediately'));
    this.targetLayer = this.targetLayerCombo.selectedLayer;
    if (this.targetLayer) {
        this.fireEvent('targetlayerselected', this.targetLayer);
    }
}, onParentShow: function () {
    this.activateDrawControl();
}, onParentHide: function () {
    this.deactivateDrawControl();
}, onSearchCanceled: function (searchPanel) {
    Heron.widgets.search.SearchByFeaturePanel.superclass.onSearchCanceled.call(this);
    this.cancelButton.disable();
    if (this.selectionLayer) {
        this.selectionLayer.removeAllFeatures();
    }
}, onSearchComplete: function (searchPanel, result) {
    Heron.widgets.search.SearchByFeaturePanel.superclass.onSearchComplete.call(this, searchPanel, result);
    this.cancelButton.disable();
}, searchFromSketch: function () {
    var selectionLayer = this.selectionLayer;
    var geometry = selectionLayer.features[0].geometry;
    if (!this.search([geometry], {projection: selectionLayer.projection, units: selectionLayer.units, targetLayer: this.targetLayer})) {
    }
    this.sketch = true;
    this.cancelButton.enable();
}});
Ext.reg('hr_searchbydrawpanel', Heron.widgets.search.SearchByDrawPanel);
Ext.namespace("Heron.widgets.search");
Heron.widgets.search.SearchByFeaturePanel = Ext.extend(Heron.widgets.search.SpatialSearchPanel, {name: __('Search by Feature Selection'), targetLayerFilter: function (map) {
    return map.getLayersBy('metadata', {test: function (metadata) {
        return metadata && metadata.wfs && !metadata.isSourceLayer;
    }})
}, initComponent: function () {
    this.resetButton = new Ext.Button({anchor: "20%", text: 'Reset', tooltip: __('Start a new search'), listeners: {click: function () {
        this.resetForm();
    }, scope: this}});
    this.items = [this.createSourceLayerCombo(), this.createDrawFieldSet(), this.createTargetLayerCombo({selectFirst: false}), this.createSearchTypeCombo(), this.createActionButtons(), this.createStatusPanel(), this.resetButton];
    Heron.widgets.search.SearchByFeaturePanel.superclass.initComponent.call(this);
}, activateSearchByFeature: function () {
    this.deactivateSearchByFeature();
    this.sourceLayerCombo.addListener('selectlayer', this.onSourceLayerSelect, this);
    this.selectionLayer.events.register('featureadded', this, this.onSelectionLayerUpdate);
    this.selectionLayer.events.register('featuresadded', this, this.onSelectionLayerUpdate);
    this.selectionLayer.events.register('featureremoved', this, this.onSelectionLayerUpdate);
    this.selectionLayer.events.register('featuresremoved', this, this.onSelectionLayerUpdate);
}, deactivateSearchByFeature: function () {
    this.sourceLayerCombo.removeListener('selectlayer', this.onSourceLayerSelect, this);
    this.selectionLayer.events.unregister('featureadded', this, this.onSelectionLayerUpdate);
    this.selectionLayer.events.unregister('featuresadded', this, this.onSelectionLayerUpdate);
    this.selectionLayer.events.unregister('featureremoved', this, this.onSelectionLayerUpdate);
    this.selectionLayer.events.unregister('featuresremoved', this, this.onSelectionLayerUpdate);
}, resetForm: function () {
    this.selectionLayer.removeAllFeatures();
    this.searchButton.disable();
    this.sourceLayerCombo.reset();
    this.targetLayerCombo.reset();
    this.spatialFilterType = OpenLayers.Filter.Spatial.INTERSECTS;
    this.drawFieldSet.hide();
    this.deactivateDrawControl();
    this.selectionStatusField.hide();
    this.targetLayerCombo.hide();
    this.searchTypeCombo.hide();
    this.actionButtons.hide();
    this.updateStatusPanel(__('Select a source Layer and then draw to select objects from that layer. <br/>Then select a target Layer to search in using the geometries of the selected objects.'));
    this.fireEvent('searchreset');
}, createActionButtons: function () {
    this.searchButton = new Ext.Button({text: __('Search'), tooltip: __('Search in target layer using the feature-geometries from the selection'), disabled: true, handler: function () {
        this.searchFromFeatures();
    }, scope: this});
    this.cancelButton = new Ext.Button({text: 'Cancel', tooltip: __('Cancel ongoing search'), disabled: true, listeners: {click: function () {
        this.fireEvent('searchcanceled', this);
    }, scope: this}});
    return this.actionButtons = new Ext.ButtonGroup({fieldLabel: __('Actions'), anchor: "100%", title: null, border: false, items: [this.cancelButton, this.searchButton]});
}, createDrawFieldSet: function () {
    this.selectionStatusField = new Heron.widgets.HTMLPanel({html: __('No objects selected'), preventBodyReset: true, bodyCfg: {style: {padding: '2px', border: '0px'}}, style: {marginTop: '2px', marginBottom: '2px', fontFamily: 'Verdana, Arial, Helvetica, sans-serif', fontSize: '11px', color: '#0000C0'}});
    return this.drawFieldSet = new Ext.form.FieldSet({xtype: "fieldset", title: null, anchor: "100%", items: [this.createDrawToolPanel({style: {marginTop: '12px', marginBottom: '12px'}, activateControl: false}), this.selectionStatusField]});
}, createSearchTypeCombo: function () {
    var store = new Ext.data.ArrayStore({fields: ['name', 'value'], data: [
        ['INTERSECTS (default)', OpenLayers.Filter.Spatial.INTERSECTS],
        ['WITHIN', OpenLayers.Filter.Spatial.WITHIN],
        ['CONTAINS', OpenLayers.Filter.Spatial.CONTAINS]
    ]});
    return this.searchTypeCombo = new Ext.form.ComboBox({mode: 'local', listWidth: 160, value: store.getAt(0).get("name"), fieldLabel: __('Type of Search'), store: store, displayField: 'name', valueField: 'value', forceSelection: true, triggerAction: 'all', editable: false, listeners: {select: function (cb, record) {
        this.spatialFilterType = record.data['value'];
    }, scope: this}});
}, createSourceLayerCombo: function () {
    return this.sourceLayerCombo = new Heron.widgets.LayerCombo({fieldLabel: __('Choose Layer to select with'), sortOrder: this.layerSortOrder, layerFilter: this.layerFilter});
}, updateSelectionStatusField: function (text) {
    if (this.selectionStatusField.body) {
        this.selectionStatusField.body.update(text);
    } else {
        this.selectionStatusField.html = text;
    }
}, onFeatureDrawn: function (evt) {
    var selectionLayer = this.selectionLayer;
    selectionLayer.removeAllFeatures();
    selectionLayer.addFeatures(evt.feature);
    var geometries = [selectionLayer.features[0].geometry];
    if (!this.search(geometries, {targetLayer: this.sourceLayer, projection: this.selectionLayer.projection, units: this.selectionLayer.units})) {
        return;
    }
    this.searchSelect = true;
    this.searchButton.enable();
    this.cancelButton.disable();
}, onSourceLayerSelect: function (layer) {
    if (this.sourceLayer && this.sourceLayer.metadata) {
        this.sourceLayer.metadata.isSourceLayer = false;
    }
    this.sourceLayer = layer;
    if (this.sourceLayer && this.sourceLayer.metadata) {
        this.sourceLayer.metadata.isSourceLayer = true;
    }
    this.searchButton.enable();
    this.cancelButton.disable();
    this.drawFieldSet.show();
    this.activateDrawControl();
    this.selectionStatusField.show();
    this.updateStatusPanel();
    this.updateSelectionStatusField(__('Select a draw tool and draw to select objects from') + (this.sourceLayer ? '<br/>"' + this.sourceLayer.name + '"' : ''));
}, onSelectionLayerUpdate: function () {
}, onTargetLayerSelected: function () {
    this.searchTypeCombo.show();
    this.actionButtons.show();
    this.searchButton.enable();
    this.cancelButton.disable();
    this.doLayout();
    this.updateStatusPanel(__('Select the spatial operator (optional) and use the Search button to start your search.'));
}, onPanelRendered: function () {
    if (this.fromLastResult && this.filterFeatures) {
        this.selectionLayer.addFeatures(this.filterFeatures);
    }
    this.activateSearchByFeature();
    this.resetForm();
}, onParentShow: function () {
    this.activateSearchByFeature();
}, onParentHide: function () {
    this.deactivateSearchByFeature();
    this.resetForm();
}, onBeforeDestroy: function () {
    this.deactivateSearchByFeature();
    if (this.selectionLayer) {
        this.selectionLayer.removeAllFeatures();
        this.map.removeLayer(this.selectionLayer);
    }
}, onSearchCanceled: function (searchPanel) {
    Heron.widgets.search.SearchByFeaturePanel.superclass.onSearchCanceled.call(this);
    this.resetForm();
}, onSearchSuccess: function (searchPanel, result) {
    var features = this.features = this.filterFeatures = result.olResponse.features;
    this.searchButton.enable();
    this.cancelButton.disable();
    if (this.searchSelect) {
        this.selectionLayer.removeAllFeatures();
        this.selectionLayer.addFeatures(features);
        this.targetLayerCombo.hide();
        this.updateStatusPanel();
        if (this.selectionLayer.features.length == 0) {
            this.updateSelectionStatusField(__('No objects selected.'));
            return;
        }
        if (this.selectionLayer.features.length > this.maxFilterGeometries) {
            this.updateSelectionStatusField(__('Too many geometries for spatial filter: ') + this.selectionLayer.features.length + ' ' + 'max: ' + this.maxFilterGeometries);
            return;
        }
        this.searchSelect = false;
        this.targetLayerCombo.setLayers(this.targetLayerFilter(this.map));
        this.targetLayerCombo.show();
        var text = this.selectionLayer.features.length + ' ' + __('objects selected from "') + (this.sourceLayer ? this.sourceLayer.name : '') + '"';
        this.updateSelectionStatusField(text);
        this.updateStatusPanel(__('Select a target layer to search using the geometries of the selected objects'));
    } else {
        Heron.widgets.search.SearchByFeaturePanel.superclass.onSearchSuccess.call(this, searchPanel, result);
    }
}, searchFromFeatures: function () {
    var geometries = [];
    var features = this.selectionLayer.features;
    for (var i = 0; i < features.length; i++) {
        geometries.push(features[i].geometry);
    }
    this.searchButton.disable();
    this.cancelButton.enable();
    if (!this.search(geometries, {spatialFilterType: this.spatialFilterType, targetLayer: this.targetLayer, projection: this.selectionLayer.projection, units: this.selectionLayer.units})) {
        this.selectionLayer.removeAllFeatures();
    }
}});
Ext.reg('hr_searchbyfeaturepanel', Heron.widgets.search.SearchByFeaturePanel);
Ext.namespace("Heron.widgets.search");
Heron.widgets.GXP_QueryPanel_Empty = Ext.extend(Ext.Panel, {});
Heron.widgets.search.GXP_QueryPanel = Ext.extend(gxp.QueryPanel, {statusReady: __('Ready'), statusNoQueryLayers: __('No query layers found'), wfsVersion: '1.0.0', title: __('Query Panel'), bodyStyle: 'padding: 12px', layerSortOrder: 'ASC', caseInsensitiveMatch: false, autoWildCardAttach: false, downloadFormats: null, wfsLayers: undefined, layerFilter: function (map) {
    return map.getLayersBy('metadata', {test: function (metadata) {
        return metadata && metadata.wfs && !metadata.wfs.noBBOX;
    }})
}, progressMessages: [__('Working on it...'), __('Still searching, please be patient...'), __('Still searching, have you selected an area with too many objects?')], initComponent: function () {
    var map = this.map = Heron.App.getMap();
    this.wfsLayers = this.getWFSLayers();
    var config = {map: map, layerStore: new Ext.data.JsonStore({data: {layers: this.wfsLayers}, sortInfo: this.layerSortOrder ? {field: 'title', direction: this.layerSortOrder} : null, root: "layers", fields: ["title", "name", "namespace", "url", "schema", "options"]}), listeners: {ready: function (panel, store) {
        store.addListener("exception", this.onQueryException, this);
    }, layerchange: function (panel, record) {
        this.layerRecord = record;
    }, beforequery: function (panel, store) {
        var area = Math.round(map.getExtent().toGeometry().getGeodesicArea(map.projection));
        var filter = this.getFilter();
        return true;
    }, query: function (panel, store) {
        this.fireEvent('searchissued', this);
    }, storeload: function (panel, store) {
        var features = [];
        store.each(function (record) {
            features.push(record.get("feature"));
        });
        var protocol = store.proxy.protocol;
        //var wfsOptions = this.layerRecord.get('options');
        var filterFormat = new OpenLayers.Format.Filter.v1_0_0({srsName: protocol.srsName});
        var filterStr = protocol.filter ? OpenLayers.Format.XML.prototype.write.apply(filterFormat, [filterFormat.write(protocol.filter)]) : null;
        //var downloadInfo = {type: 'wfs', url: protocol.options.url, downloadFormats: this.downloadFormats ? this.downloadFormats : wfsOptions.downloadFormats, params: {typename: protocol.featureType, maxFeatures: undefined, "Content-Disposition": "attachment", filename: protocol.featureType, srsName: protocol.srsName, service: "WFS", version: "1.0.0", request: "GetFeature", filter: filterStr}};
        var result = {olResponse: store.proxy.response, downloadInfo: null};
        this.fireEvent('searchcomplete', panel, result);
        store.removeListener("exception", this.onQueryException, this);
    }}};
    if (config.layerStore.data.items[0]) {
        Ext.apply(this, config);
        this.addEvents({"searchissued": true, "searchcomplete": true, "searchfailed": true, "searchsuccess": true, "searchaborted": true});
        this.likeSubstring = this.autoWildCardAttach;
        Heron.widgets.search.GXP_QueryPanel.superclass.initComponent.call(this);
        this.addButton(this.createActionButtons());
        this.addListener("searchissued", this.onSearchIssued, this);
        this.addListener("searchcomplete", this.onSearchComplete, this);
        this.addListener("beforedestroy", this.onBeforeDestroy, this);
        this.addListener("afterrender", this.onPanelRendered, this);
        if (this.ownerCt) {
            this.ownerCt.addListener("parenthide", this.onParentHide, this);
            this.ownerCt.addListener("parentshow", this.onParentShow, this);
        }
    } else {
        Ext.apply(this, {});
        Heron.widgets.GXP_QueryPanel_Empty.superclass.initComponent.apply(this, arguments);
    }
    this.statusPanel = this.add({xtype: "hr_htmlpanel", html: config.layerStore.data.items[0] ? this.statusReady : this.statusNoQueryLayers, height: 132, preventBodyReset: true, bodyCfg: {style: {padding: '6px', border: '0px'}}, style: {marginTop: '24px', paddingTop: '24px', fontFamily: 'Verdana, Arial, Helvetica, sans-serif', fontSize: '11px', color: '#0000C0'}});
}, createActionButtons: function () {
    this.searchButton = new Ext.Button({text: __('Search'), tooltip: __('Search in target layer using the selected filters'), disabled: false, handler: function () {
        this.search();
    }, scope: this});
    this.cancelButton = new Ext.Button({text: __('Cancel'), tooltip: __('Cancel current search'), disabled: true, listeners: {click: function () {
        this.searchAbort();
    }, scope: this}});
    return this.actionButtons = new Ext.ButtonGroup({fieldLabel: null, anchor: "100%", title: null, border: false, width: 160, padding: '2px', bodyBorder: false, style: {border: '0px'}, items: [this.cancelButton, this.searchButton]});
}, getWFSLayers: function () {
    var self = this;
    if (this.wfsLayers) {
        return this.wfsLayers;
    }
    var wmsLayers = this.layerFilter(this.map);
    var wfsLayers = [];
    Ext.each(wmsLayers, function (wmsLayer) {
        var wfsOpts = wmsLayer.metadata.wfs;
        var protocol = wfsOpts.protocol;
        if (wfsOpts.protocol === 'fromWMSLayer') {
            protocol = OpenLayers.Protocol.WFS.fromWMSLayer(wmsLayer);
            if (protocol.url instanceof Array) {
                protocol.url = Heron.Utils.randArrayElm(protocol.url);
                protocol.options.url = protocol.url;
            }
        } else {
            protocol = wfsOpts.protocol;
        }
        var url = protocol.url.indexOf('?') == protocol.url.length - 1 ? protocol.url.slice(0, -1) : protocol.url;
        var featureType = protocol.featureType;
        var featurePrefix = wfsOpts.featurePrefix;
        var fullFeatureType = featurePrefix ? featurePrefix + ':' + featureType : featureType;
        var wfsVersion = protocol.version ? protocol.version : self.version;
        var outputFormat = protocol.outputFormat ? '&outputFormat=' + protocol.outputFormat : '';
        var wfsLayer = {title: wmsLayer.name, name: featureType, namespace: wfsOpts.featureNS, url: url, schema: url + '?service=WFS&version=' + wfsVersion + '&request=DescribeFeatureType&typeName=' + fullFeatureType + outputFormat, options: wfsOpts};
        wfsLayers.push(wfsLayer);
    });
    return wfsLayers;
}, getFeatureType: function () {
    return this.layerRecord ? this.layerRecord.get('name') : 'heron';
}, updateStatusPanel: function (text) {
    this.statusPanel.body.update(text);
}, onPanelRendered: function () {
}, onParentShow: function () {
}, onParentHide: function () {
}, onBeforeDestroy: function () {
}, onQueryException: function (type, action, obj, response_error, o_records) {
    this.searchButton.enable();
    if (this.timer) {
        clearInterval(this.timer);
        this.timer = null;
    }
    this.updateStatusPanel(__('Search Failed'));
}, onSearchIssued: function () {
    this.searchState = "searchissued";
    this.response = null;
    this.features = null;
    this.updateStatusPanel(__('Searching...'));
    if (this.timer) {
        clearInterval(this.timer);
        this.timer = null;
    }
    this.searchButton.disable();
    var self = this;
    var startTime = new Date().getTime() / 1000;
    this.timer = setInterval(function () {
        if (self.searchState != 'searchissued') {
            return;
        }
        self.updateStatusPanel(Math.floor(new Date().getTime() / 1000 - startTime) + ' ' + __('Seconds') + ' - ' +
            self.progressMessages[Math.floor(Math.random() * self.progressMessages.length)]);
    }, 4000);
}, onSearchComplete: function (searchPanel, result) {
    this.searchButton.enable();
    this.cancelButton.disable();
    if (this.timer) {
        clearInterval(this.timer);
        this.timer = null;
    }
    if (this.searchState == 'searchaborted') {
        return;
    }
    this.searchState = "searchcomplete";
    var features = result.olResponse.features;
    var featureCount = features ? features.length : 0;
    this.updateStatusPanel(__('Search Completed: ') + featureCount + ' ' + (featureCount != 1 ? __('Results') : __('Result')));
    this.fireEvent('searchsuccess', searchPanel, result);
}, searchAbort: function () {
    if (this.timer) {
        clearInterval(this.timer);
        this.timer = null;
    }
    if (this.featureStore && this.featureStore.proxy && this.featureStore.proxy.protocol) {
        this.featureStore.proxy.protocol.abort(this.featureStore.proxy.response);
    }
    this.fireEvent('searchaborted', this);
    this.searchState = 'searchaborted';
    this.searchButton.enable();
    this.cancelButton.disable();
    this.updateStatusPanel(__('Search aborted'));
}, search: function () {
    this.query();
    this.cancelButton.enable();
}});
Ext.reg('hr_gxpquerypanel', Heron.widgets.search.GXP_QueryPanel);
Ext.namespace("Heron.widgets.ToolbarBuilder");
Heron.widgets.ToolbarBuilder.defs = {baselayer: {options: {id: "baselayercombo"}, create: function (mapPanel, options) {
    if (!options.initialConfig) {
        options.initialConfig = {};
    }
    Ext.apply(options.initialConfig, options);
    options.initialConfig.map = mapPanel.getMap();
    return new Heron.widgets.BaseLayerCombo(options);
}}, geocoder: {options: {id: "geocodercombo"}, create: function (mapPanel, options) {
    return new Heron.widgets.search.GeocoderCombo(options);
}}, scale: {options: {id: "scalecombo"}, create: function (mapPanel, options) {
    if (!options.initialConfig) {
        options.initialConfig = {};
    }
    Ext.apply(options.initialConfig, options);
    return new Heron.widgets.ScaleSelectorCombo(options);
}}, featureinfo: {options: {tooltip: __('Feature information'), iconCls: "icon-getfeatureinfo", enableToggle: true, pressed: false, id: "featureinfo", toggleGroup: "toolGroup", popupWindowDefaults: {title: __('Feature Info'), width: 360, height: 200, anchored: false, hideonmove: false}, controlDefaults: {maxFeatures: 8, hover: false, drillDown: true, infoFormat: "application/vnd.ogc.gml", queryVisible: true}}, create: function (mapPanel, options) {
    if (options.getfeatureControl) {
        options.controlDefaults = Ext.apply(options.controlDefaults, options.getfeatureControl);
    }
    options.control = new OpenLayers.Control.WMSGetFeatureInfo(options.controlDefaults);
    if (options.popupWindow) {
        var self = this;
        var popupWindowProps = Ext.apply(options.popupWindowDefaults, options.popupWindow);
        popupWindowProps.olControl = options.control;
        popupWindowProps.featureInfoPanel = options.popupWindow ? options.popupWindow.featureInfoPanel : null;
        var createPopupWindow = function () {
            if (!self.featurePopupWindow) {
                self.featurePopupWindow = new Heron.widgets.search.FeatureInfoPopup(popupWindowProps);
            }
        };
        if (options.pressed) {
            createPopupWindow();
        }
        options.handler = function () {
            createPopupWindow();
            self.featurePopupWindow.hide();
        };
    }
    return new GeoExt.Action(options);
}}, help: {options: {tooltip: __('Help'), iconCls: "icon-help", enableToggle: false, pressed: false, id: "helpinfo", content: '<b>Default help info</b>', contentUrl: null, popupWindowDefaults: {title: __('Help'), border: true, layoutConfig: {padding: '0'}, width: 460, height: 540, autoScroll: true, items: [
    {xtype: 'hr_htmlpanel', height: '100%', width: '95%', preventBodyReset: true, bodyBorder: false, border: false, style: {paddingTop: '12px', paddingBottom: '12px', paddingLeft: '10px', paddingRight: '10px', fontFamily: 'Verdana, Arial, Helvetica, sans-serif', fontSize: '10px', background: '#FFFFFF', color: '#111111'}}
]}}, create: function (mapPanel, options) {
    options.handler = function () {
        var popupOptions = Ext.apply(options.popupWindowDefaults, options.popupWindow);
        if (options.contentUrl) {
            popupOptions.items[0].autoLoad = {url: options.contentUrl};
        } else {
            popupOptions.items[0].html = options.content
        }
        new Ext.Window(popupOptions).show();
    };
    return new Ext.Action(options);
}}, tooltips: {options: {tooltip: __('Feature tooltips'), iconCls: "icon-featuretooltip", enableToggle: true, pressed: false, id: "tooltips", toggleGroup: "tooltipsGrp", popupWindowDefaults: {title: __('FeatureTooltip'), anchored: true, hideonmove: true, height: 150}, controlDefaults: {maxFeatures: 1, hover: true, drillDown: false, infoFormat: "application/vnd.ogc.gml", queryVisible: true}}, create: function (mapPanel, options) {
    return Heron.widgets.ToolbarBuilder.defs.featureinfo.create(mapPanel, options);
}}, pan: {options: {tooltip: __('Pan'), iconCls: "icon-hand", enableToggle: true, pressed: true, control: new OpenLayers.Control.Navigation(), id: "pan", toggleGroup: "toolGroup"}, create: function (mapPanel, options) {
    return new GeoExt.Action(options);
}}, upload: {options: {tooltip: __('Upload features from local file'), iconCls: "icon-upload", enableToggle: false, pressed: false, id: "hr-upload-button", toggleGroup: "toolGroup", upload: {layerName: __('My Upload'), visibleOnUpload: true, url: Heron.globals.serviceUrl, params: {action: 'upload', mime: 'text/html', encoding: 'escape'}, formats: [
    {name: 'Well-Known-Text (WKT)', fileExt: '.wkt', mimeType: 'text/plain', formatter: 'OpenLayers.Format.WKT'},
    {name: 'Geographic Markup Language - v2 (GML2)', fileExt: '.gml', mimeType: 'text/xml', formatter: 'OpenLayers.Format.GML'},
    {name: 'Geographic Markup Language - v3 (GML3)', fileExt: '.gml', mimeType: 'text/xml', formatter: 'OpenLayers.Format.GML.v3'},
    {name: 'GeoJSON', fileExt: '.json', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON'},
    {name: 'GPS Exchange Format (GPX)', fileExt: '.gpx', mimeType: 'text/xml', formatter: 'OpenLayers.Format.GPX'},
    {name: 'Keyhole Markup Language (KML)', fileExt: '.kml', mimeType: 'text/xml', formatter: 'OpenLayers.Format.KML'},
    {name: 'CSV (with X,Y)', fileExt: '.csv', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON'},
    {name: 'ESRI Shapefile (zipped)', fileExt: '.zip', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON'}
], fileProjection: new OpenLayers.Projection('EPSG:4326')}}, create: function (mapPanel, options) {
    var map = mapPanel.getMap();
    options.upload.map = map;
    var layers = map.getLayersByName(options.upload.layerName);
    var layer;
    if (layers.length == 0) {
        layer = new OpenLayers.Layer.Vector(options.upload.layerName);
        map.addLayers([layer]);
    } else {
        layer = layers[0];
    }
    options.control = new OpenLayers.Editor.Control.UploadFeature(layer, options.upload);
    return new GeoExt.Action(options);
}}, zoomin: {options: {tooltip: __('Zoom in'), iconCls: "icon-zoom-in", enableToggle: true, pressed: false, control: new OpenLayers.Control.ZoomBox({title: __('Zoom in'), out: false}), id: "zoomin", toggleGroup: "toolGroup"}, create: function (mapPanel, options) {
    return new GeoExt.Action(options);
}}, zoomout: {options: {tooltip: __('Zoom out'), iconCls: "icon-zoom-out", enableToggle: true, pressed: false, control: new OpenLayers.Control.ZoomBox({title: __('Zoom out'), out: true}), id: "zoomout", toggleGroup: "toolGroup"}, create: function (mapPanel, options) {
    return new GeoExt.Action(options);
}}, zoomvisible: {options: {tooltip: __('Zoom to full extent'), iconCls: "icon-zoom-visible", enableToggle: false, pressed: false, control: new OpenLayers.Control.ZoomToMaxExtent(), id: "zoomvisible"}, create: function (mapPanel, options) {
    return new GeoExt.Action(options);
}}, zoomprevious: {options: {tooltip: __('Zoom previous'), iconCls: "icon-zoom-previous", enableToggle: false, disabled: true, pressed: false, id: "zoomprevious"}, create: function (mapPanel, options) {
    if (!mapPanel.historyControl) {
        mapPanel.historyControl = new OpenLayers.Control.NavigationHistory();
        mapPanel.getMap().addControl(mapPanel.historyControl);
    }
    options.control = mapPanel.historyControl.previous;
    return new GeoExt.Action(options);
}}, zoomnext: {options: {tooltip: __('Zoom next'), iconCls: "icon-zoom-next", enableToggle: false, disabled: true, pressed: false, id: "zoomnext"}, create: function (mapPanel, options) {
    if (!mapPanel.historyControl) {
        mapPanel.historyControl = new OpenLayers.Control.NavigationHistory();
        mapPanel.getMap().addControl(mapPanel.historyControl);
    }
    options.control = mapPanel.historyControl.next;
    return new GeoExt.Action(options);
}}, measurelength: {options: {tooltip: __('Measure length'), iconCls: "icon-measure-length", enableToggle: true, pressed: false, measureLastLength: 0.0, control: new OpenLayers.Control.Measure(OpenLayers.Handler.Path, {persist: true, immediate: true, displayClass: "olControlMeasureDistance", handlerOptions: {layerOptions: {styleMap: new OpenLayers.StyleMap({"default": new OpenLayers.Style(null, {rules: [new OpenLayers.Rule({symbolizer: {"Point": {pointRadius: 10, graphicName: "square", fillColor: "white", fillOpacity: 0.25, strokeWidth: 1, strokeOpacity: 1, strokeColor: "#333333"}, "Line": {strokeWidth: 1, strokeOpacity: 1, strokeColor: "#FF0000", strokeDashstyle: "solid"}}})]})})}}}), id: "measurelength", toggleGroup: "toolGroup"}, create: function (mapPanel, options) {
    var action = new GeoExt.Action(options);
    var map = mapPanel.getMap();
    var controls = map.getControlsByClass("OpenLayers.Control.Measure");
    Heron.widgets.ToolbarBuilder.onMeasurementsActivate = function (event) {
        Ext.getCmp("measurelength").measureLastLength = 0.0;
    };
    Heron.widgets.ToolbarBuilder.onMeasurements = function (event) {
        var units = event.units;
        var measure = event.measure;
        var out = "";
        if (event.order == 1) {
            out += __('Length') + ": " + measure.toFixed(2) + " " + units;
            var item = Ext.getCmp("measurelength");
            item.measureLastLength = 0.0;
        } else {
            out += __('Area') + ": " + measure.toFixed(2) + " " + units + "&#178;";
        }
        Ext.getCmp("bbar_measure").setText(out);
    };
    Heron.widgets.ToolbarBuilder.onMeasurementsPartial = function (event) {
        var units = event.units;
        var measure = event.measure;
        var out = "";
        if (event.order == 1) {
            out += __('Length') + ": " + measure.toFixed(2) + " " + units;
            var item = Ext.getCmp("measurelength");
            item.measureLastLength = measure;
        } else {
            out += __('Area') + ": " + measure.toFixed(2) + " " + units + "&#178;";
        }
        Ext.getCmp("bbar_measure").setText(out);
    };
    Heron.widgets.ToolbarBuilder.onMeasurementsDeactivate = function (event) {
        Ext.getCmp("bbar_measure").setText("");
    };
    for (var i = 0; i < controls.length; i++) {
        if (controls[i].displayClass == 'olControlMeasureDistance') {
            controls[i].geodesic = options.geodesic;
            controls[i].events.register("activate", map, Heron.widgets.ToolbarBuilder.onMeasurementsActivate);
            controls[i].events.register("measure", map, Heron.widgets.ToolbarBuilder.onMeasurements);
            controls[i].events.register("measurepartial", map, Heron.widgets.ToolbarBuilder.onMeasurementsPartial);
            controls[i].events.register("deactivate", map, Heron.widgets.ToolbarBuilder.onMeasurementsDeactivate);
            break;
        }
    }
    return action;
}}, measurearea: {options: {tooltip: __('Measure area'), iconCls: "icon-measure-area", enableToggle: true, pressed: false, control: new OpenLayers.Control.Measure(OpenLayers.Handler.Polygon, {persist: true, immediate: true, displayClass: "olControlMeasureArea", handlerOptions: {layerOptions: {styleMap: new OpenLayers.StyleMap({"default": new OpenLayers.Style(null, {rules: [new OpenLayers.Rule({symbolizer: {"Point": {pointRadius: 10, graphicName: "square", fillColor: "white", fillOpacity: 0.25, strokeWidth: 1, strokeOpacity: 1, strokeColor: "#333333"}, "Polygon": {strokeWidth: 1, strokeOpacity: 1, strokeColor: "#FF0000", strokeDashstyle: "solid", fillColor: "#FFFFFF", fillOpacity: 0.5}}})]})})}}}), id: "measurearea", toggleGroup: "toolGroup"}, create: function (mapPanel, options) {
    var action = new GeoExt.Action(options);
    var map = mapPanel.getMap();
    var controls = map.getControlsByClass("OpenLayers.Control.Measure");
    for (var i = 0; i < controls.length; i++) {
        if (controls[i].displayClass == 'olControlMeasureArea') {
            controls[i].geodesic = options.geodesic;
            controls[i].events.register("activate", map, Heron.widgets.ToolbarBuilder.onMeasurementsActivate);
            controls[i].events.register("measure", map, Heron.widgets.ToolbarBuilder.onMeasurements);
            controls[i].events.register("measurepartial", map, Heron.widgets.ToolbarBuilder.onMeasurementsPartial);
            controls[i].events.register("deactivate", map, Heron.widgets.ToolbarBuilder.onMeasurementsDeactivate);
            break;
        }
    }
    return action;
}}, oleditor: {options: {tooltip: __('Draw Features'), iconCls: "icon-mapedit", enableToggle: true, pressed: false, id: "mapeditor", toggleGroup: "toolGroup", olEditorOptions: {activeControls: ['UploadFeature', 'DownloadFeature', 'Separator', 'Navigation', 'SnappingSettings', 'CADTools', 'Separator', 'DeleteAllFeatures', 'DeleteFeature', 'DragFeature', 'SelectFeature', 'Separator', 'DrawHole', 'ModifyFeature', 'Separator'], featureTypes: ['text', 'polygon', 'path', 'point'], language: 'en', DownloadFeature: {url: Heron.globals.serviceUrl, params: {action: 'download', mime: 'text/plain', filename: 'editor', encoding: 'none'}, formats: [
    {name: 'Well-Known-Text (WKT)', fileExt: '.wkt', mimeType: 'text/plain', formatter: 'OpenLayers.Format.WKT'},
    {name: 'Geographic Markup Language - v2 (GML2)', fileExt: '.gml', mimeType: 'text/xml', formatter: new OpenLayers.Format.GML.v2({featureType: 'oledit', featureNS: 'http://geops.de'})},
    {name: 'Geographic Markup Language - v3 (GML3)', fileExt: '.gml', mimeType: 'text/xml', formatter: new OpenLayers.Format.GML.v3({featureType: 'oledit', featureNS: 'http://geops.de'})},
    {name: 'GeoJSON', fileExt: '.json', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON'},
    {name: 'GPS Exchange Format (GPX)', fileExt: '.gpx', mimeType: 'text/xml', formatter: 'OpenLayers.Format.GPX'},
    {name: 'Keyhole Markup Language (KML)', fileExt: '.kml', mimeType: 'text/xml', formatter: 'OpenLayers.Format.KML'}
], fileProjection: new OpenLayers.Projection('EPSG:4326')}, UploadFeature: {url: Heron.globals.serviceUrl, params: {action: 'upload', mime: 'text/html', encoding: 'escape'}, formats: [
    {name: 'Well-Known-Text (WKT)', fileExt: '.wkt', mimeType: 'text/plain', formatter: 'OpenLayers.Format.WKT'},
    {name: 'Geographic Markup Language - v2 (GML2)', fileExt: '.gml', mimeType: 'text/xml', formatter: 'OpenLayers.Format.GML'},
    {name: 'Geographic Markup Language - v3 (GML3)', fileExt: '.gml', mimeType: 'text/xml', formatter: 'OpenLayers.Format.GML.v3'},
    {name: 'GeoJSON', fileExt: '.json', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON'},
    {name: 'GPS Exchange Format (GPX)', fileExt: '.gpx', mimeType: 'text/xml', formatter: 'OpenLayers.Format.GPX'},
    {name: 'Keyhole Markup Language (KML)', fileExt: '.kml', mimeType: 'text/xml', formatter: 'OpenLayers.Format.KML'},
    {name: 'CSV (with X,Y)', fileExt: '.csv', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON'},
    {name: 'ESRI Shapefile (zipped)', fileExt: '.zip', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON'}
], fileProjection: new OpenLayers.Projection('EPSG:4326')}}}, create: function (mapPanel, options) {
    OpenLayers.Lang.setCode(options.olEditorOptions.language);
    var map = mapPanel.getMap();
    this.editor = new OpenLayers.Editor(map, options.olEditorOptions);
    this.startEditor = function (self) {
        var editor = self.editor;
        if (!editor) {
            return;
        }
        if (editor.editLayer) {
            editor.editLayer.setVisibility(true);
        }
        self.editor.startEditMode();
    };
    this.stopEditor = function (self) {
        var editor = self.editor;
        if (!editor) {
            return;
        }
        if (editor.editLayer) {
        }
        editor.stopEditMode();
    };
    var self = this;
    options.handler = function (cmp, event) {
        if (cmp.pressed === true) {
            self.startEditor(self);
        } else {
            self.stopEditor(self);
        }
    };
    if (options.pressed) {
        this.startEditor(self);
    }
    options.control = this.editor.editorPanel;
    return new GeoExt.Action(options);
}}, any: {options: {tooltip: __('Anything is allowed here'), text: __('Any valid Toolbar.add() config goes here')}, create: function (mapPanel, options) {
    return options;
}}, search_nominatim: {options: {tooltip: __('Search Nominatim'), id: "search_nominatim"}, create: function (mapPanel, options) {
    return new Heron.widgets.search.NominatimSearchCombo(options);
}}, namesearch: {options: {id: "namesearch"}, create: function (mapPanel, options) {
    return Ext.create(options);
}}, searchcenter: {options: {id: "searchcenter", tooltip: __('Search'), iconCls: "icon-find", pressed: false, enableToggle: false, searchWindowDefault: {title: __('Search'), layout: "fit", closeAction: "hide", x: 100, width: 400, height: 400}}, create: function (mapPanel, options) {
    var searchWindow, searchWindowId = options.id;
    var pressButton = function () {
        var sc = Ext.getCmp(searchWindowId);
        if (sc && !sc.pressed) {
            sc.toggle();
        }
    };
    var depressButton = function () {
        var sc = Ext.getCmp(searchWindowId);
        if (sc && sc.pressed) {
            sc.toggle();
        }
    };
    var showSearchWindow = function () {
        if (!searchWindow) {
            var windowOptions = options.searchWindowDefault;
            Ext.apply(windowOptions, options.searchWindow);
            searchWindow = new Ext.Window(windowOptions);
            searchWindow.on('hide', depressButton);
            searchWindow.on('show', pressButton);
        }
        searchWindow.show();
    };
    var toggleSearchWindow = function () {
        if (searchWindow && searchWindow.isVisible()) {
            searchWindow.hide();
        } else {
            showSearchWindow();
        }
    };
    if (options.show) {
        options.pressed = true;
    }
    if (options.pressed || options.show) {
        showSearchWindow();
    }
    options.handler = function () {
        toggleSearchWindow();
    };
    if (options.enableToggle) {
        return new GeoExt.Action(options)
    } else {
        return new Ext.Action(options);
    }
}}, printdialog: {options: {id: "hr_printdialog", title: __('Print Dialog'), tooltip: __('Print Dialog Popup with Preview Map'), iconCls: "icon-printer", enableToggle: false, pressed: false, windowTitle: __('Print Preview'), windowWidth: 400, method: 'POST', url: null, legendDefaults: {useScaleParameter: false, baseParams: {FORMAT: "image/png"}}, showTitle: true, mapTitle: null, mapTitleYAML: "mapTitle", showComment: true, mapComment: null, mapCommentYAML: "mapComment", showFooter: false, mapFooter: null, mapFooterYAML: "mapFooter", showRotation: true, showLegend: true, showLegendChecked: false, mapLimitScales: true, showOutputFormats: false}, create: function (mapPanel, options) {
    options.handler = function () {
        if (!Heron.widgets.ToolbarBuilder.checkCanWePrint(mapPanel.getMap().layers)) {
            return;
        }
        var printWindow = new Heron.widgets.PrintPreviewWindow({title: options.windowTitle, modal: true, border: false, resizable: false, width: options.windowWidth, autoHeight: true, hropts: {mapPanel: mapPanel, method: options.method, url: options.url, legendDefaults: options.legendDefaults, showTitle: options.showTitle, mapTitle: options.mapTitle, mapTitleYAML: options.mapTitleYAML, showComment: options.showComment, mapComment: options.mapComment, mapCommentYAML: options.mapCommentYAML, showFooter: options.showFooter, mapFooter: options.mapFooter, mapFooterYAML: options.mapFooterYAML, showRotation: options.showRotation, showLegend: options.showLegend, showLegendChecked: options.showLegendChecked, mapLimitScales: options.mapLimitScales, showOutputFormats: options.showOutputFormats}});
    };
    return new Ext.Action(options);
}}, printdirect: {options: {id: "printdirect", tooltip: __('Print Visible Map Area Directly'), iconCls: "icon-print-direct", enableToggle: false, pressed: false, method: 'POST', url: null, mapTitle: null, mapTitleYAML: "mapTitle", mapComment: __('This is a simple map directly printed.'), mapCommentYAML: "mapComment", mapFooter: null, mapFooterYAML: "mapFooter", mapPrintLayout: "A4", mapPrintDPI: "75", mapPrintLegend: false, excludeLayers: ['OpenLayers.Handler.Polygon', 'OpenLayers.Handler.RegularPolygon', 'OpenLayers.Handler.Path', 'OpenLayers.Handler.Point'], legendDefaults: {useScaleParameter: true, baseParams: {FORMAT: "image/png"}}}, create: function (mapPanel, options) {
    options.handler = function () {
        if (!Heron.widgets.ToolbarBuilder.checkCanWePrint(mapPanel.getMap().layers)) {
            return;
        }
        var busyMask = new Ext.LoadMask(Ext.getBody(), {msg: __('Create PDF...')});
        busyMask.show();
        Ext.Ajax.request({url: options.url + '/info.json', method: 'GET', params: null, success: function (result, request) {
            var printCapabilities = Ext.decode(result.responseText);
            var printProvider = new GeoExt.data.PrintProvider({method: options.method, capabilities: printCapabilities, customParams: {}, listeners: {"printexception": function (printProvider, result) {
                alert(__('Error from Print server: ') + result.statusText);
            }, "beforeencodelayer": function (printProvider, layer) {
                for (var i = 0; i < options.excludeLayers.length; i++) {
                    if (layer.name == options.excludeLayers[i]) {
                        return false;
                    }
                }
                return true;
            }}});
            printProvider.customParams[options.mapTitleYAML] = (options.mapTitle) ? options.mapTitle : '';
            printProvider.customParams[options.mapCommentYAML] = (options.mapComment) ? options.mapComment : '';
            printProvider.customParams[options.mapFooterYAML] = (options.mapFooter) ? options.mapFooter : '';
            if ((printProvider.layouts.getCount() > 1) && (options.mapPrintLayout)) {
                var index = printProvider.layouts.find('name', options.mapPrintLayout);
                if (index != -1) {
                    printProvider.setLayout(printProvider.layouts.getAt(index));
                }
            }
            if ((printProvider.dpis.getCount() > 1) && (options.mapPrintDPI)) {
                var index = printProvider.dpis.find('value', options.mapPrintDPI);
                if (index != -1) {
                    printProvider.setDpi(printProvider.dpis.getAt(index));
                }
            }
            if (options.mapPrintLegend) {
                var legendPanel = new Heron.widgets.LayerLegendPanel({renderTo: document.body, hidden: true, defaults: options.legendDefaults});
            }
            var printPage = new GeoExt.data.PrintPage({printProvider: printProvider});
            printPage.fit(mapPanel, true);
            printProvider.print(mapPanel, printPage, options.mapPrintLegend && {legend: legendPanel});
            busyMask.hide();
        }, failure: function (result, request) {
            busyMask.hide();
            alert(__('Error getting Print options from server: ') + options.url);
        }});
    };
    return new Ext.Action(options);
}}, coordinatesearch: {options: {id: "coordinatesearch", tooltip: __('Enter coordinates to go to location on map'), iconCls: "icon-map-pin", enableToggle: false, pressed: false, formWidth: 340, formPageX: 200, formPageY: 75, buttonAlign: 'center'}, create: function (mapPanel, options) {
    options.handler = function () {
        if (!this.coordPopup) {
            var sp = new Heron.widgets.search.CoordSearchPanel({});
            this.coordPopup = new Ext.Window({layout: 'auto', resizable: false, autoHeight: true, pageX: options.formPageX, pageY: options.formPageY, width: options.formWidth, closeAction: 'hide', title: __('Go to coordinates'), items: [new Heron.widgets.search.CoordSearchPanel({deferredRender: false, border: false, title: options.title ? options.title : null, titleDescription: options.titleDescription ? options.titleDescription : sp.titleDescription, titleDescriptionStyle: options.titleDescriptionStyle ? options.titleDescriptionStyle : sp.titleDescriptionStyle, bodyBaseCls: options.bodyBaseCls ? options.bodyBaseCls : sp.bodyBaseCls, bodyItemCls: options.bodyItemCls ? options.bodyItemCls : null, bodyCls: options.bodyCls ? options.bodyCls : null, fieldMaxWidth: options.fieldMaxWidth ? options.fieldMaxWidth : sp.fieldMaxWidth, fieldLabelWidth: options.fieldLabelWidth ? options.fieldLabelWidth : sp.fieldLabelWidth, fieldStyle: options.fieldStyle ? options.fieldStyle : sp.fieldStyle, fieldLabelStyle: options.fieldLabelStyle ? options.fieldLabelStyle : sp.fieldLabelStyle, layerName: options.layerName ? options.layerName : sp.layerName, onProjectionIndex: options.onProjectionIndex ? options.onProjectionIndex : sp.onProjectionIndex, onZoomLevel: options.onZoomLevel ? options.onZoomLevel : sp.onZoomLevel, showProjection: options.showProjection ? options.showProjection : sp.showProjection, showZoom: options.showZoom ? options.showZoom : sp.showZoom, showAddMarkers: options.showAddMarkers ? options.showAddMarkers : sp.showAddMarkers, checkAddMarkers: options.checkAddMarkers ? options.checkAddMarkers : sp.checkAddMarkers, showHideMarkers: options.showHideMarkers ? options.showHideMarkers : sp.showHideMarkers, checkHideMarkers: options.checkHideMarkers ? options.checkHideMarkers : sp.checkHideMarkers, showResultMarker: options.showResultMarker ? options.showResultMarker : sp.showResultMarker, fieldResultMarkerStyle: options.fieldResultMarkerStyle ? options.fieldResultMarkerStyle : sp.fieldResultMarkerStyle, fieldResultMarkerText: options.fieldResultMarkerText ? options.fieldResultMarkerText : sp.fieldResultMarkerText, fieldResultMarkerSeparator: options.fieldResultMarkerSeparator ? options.fieldResultMarkerSeparator : sp.fieldResultMarkerSeparator, fieldResultMarkerPrecision: options.fieldResultMarkerPrecision ? options.fieldResultMarkerPrecision : sp.fieldResultMarkerPrecision, removeMarkersOnClose: options.removeMarkersOnClose ? options.removeMarkersOnClose : sp.removeMarkersOnClose, showRemoveMarkersBtn: options.showRemoveMarkersBtn ? options.showRemoveMarkersBtn : sp.showRemoveMarkersBtn, buttonAlign: options.buttonAlign ? options.buttonAlign : sp.buttonAlign, hropts: options.hropts ? options.hropts : null})]});
        }
        if (this.coordPopup.isVisible()) {
            this.coordPopup.hide();
        } else {
            this.coordPopup.show(this);
        }
    };
    return new Ext.Action(options);
}}, vectorstyler: {options: {id: "styler", tooltip: __('Edit vector Layer styles'), iconCls: "icon-palette", enableToggle: false, pressed: false, formWidth: 340, formPageX: 200, formPageY: 75, buttonAlign: 'center'}, create: function (mapPanel, options) {
    options.handler = function () {
        if (!this.stylerPopup) {
            var layer = mapPanel.map.getLayersByName('RD Info - Punten')[0];
            var layerRecord = mapPanel.layers.getByLayer(layer);
            var url = 'http://kademo.nl/gs2';
            this.stylerPopup = new Ext.Window({layout: 'auto', resizable: false, autoHeight: true, pageX: options.formPageX, pageY: options.formPageY, width: options.formWidth, closeAction: 'hide', title: __('Style Editor'), items: [
                {xtype: "gxp_wmsstylesdialog", layerRecord: layerRecord, plugins: [
                    {ptype: "gxp_memorystylewriter", baseUrl: url}
                ], listeners: {"styleselected": function (cmp, style) {
                    layer.mergeNewParams({styles: style});
                }, "modified": function (cmp, style) {
                    cmp.saveStyles();
                }, "saved": function (cmp, style) {
                    layer.mergeNewParams({_olSalt: Math.random(), styles: style});
                }, scope: this}}
            ]});
        }
        if (this.stylerPopup.isVisible()) {
            this.stylerPopup.hide();
        } else {
            this.stylerPopup.show(this);
        }
    };
    return new Ext.Action(options);
}}, addbookmark: {options: {id: "addbookmark", tooltip: __('Bookmark current map context (layers, zoom, extent)'), iconCls: "icon-bookmark", enableToggle: false, disabled: false, pressed: false}, create: function (mapPanel, options) {
    options.handler = function () {
        var bookmarksPanel = Heron.widgets.Bookmarks.getBookmarksPanel(this);
        if (!bookmarksPanel) {
            alert(__('Error: No \'BookmarksPanel\' found.'));
            return null;
        }
        bookmarksPanel.onAddBookmark();
    };
    return new GeoExt.Action(options);
}}, mapopen: {options: {id: "mapopen", tooltip: __('Open a map context (layers, styling, extent) from file'), iconCls: "icon-map-open", enableToggle: false, disabled: false, pressed: false}, create: function (mapPanel, options) {
    options.handler = function () {
        Heron.data.MapContext.openContext(mapPanel, options);
    }
    return new GeoExt.Action(options);
}}, mapsave: {options: {id: "mapsave", tooltip: __('Save current map context (layers, styling, extent) to file'), iconCls: "icon-map-save", enableToggle: false, disabled: false, pressed: false, mime: 'text/xml', fileName: 'heron_map', fileExt: '.cml'}, create: function (mapPanel, options) {
    options.handler = function () {
        Heron.data.MapContext.saveContext(mapPanel, options);
    }
    return new GeoExt.Action(options);
}}, epsgpanel: {options: {id: "map-panel-epsg", text: "", width: 80, xtype: "tbtext"}, create: function (mapPanel, options) {
    return Ext.create(options);
}}, xcoord: {options: {id: "x-coord", text: "X:", width: 80, xtype: "tbtext"}, create: function (mapPanel, options) {
    return Ext.create(options);
}}, ycoord: {options: {id: "y-coord", text: "Y:", width: 80, xtype: "tbtext"}, create: function (mapPanel, options) {
    return Ext.create(options);
}}, measurepanel: {options: {id: "bbar_measure", text: "", xtype: "tbtext"}, create: function (mapPanel, options) {
    return Ext.create(options);
}}};
Heron.widgets.ToolbarBuilder.checkCanWePrint = function (layers) {
    var failingLayers = '';
    for (var l = 0; l < layers.length; l++) {
        var nextLayer = layers[l];
        if (!nextLayer.visibility || !nextLayer.disallowPrinting) {
            continue;
        }
        failingLayers += "\n - " + nextLayer.name;
        if (nextLayer.isBaseLayer) {
            failingLayers += __(' [Baselayer]');
        }
    }
    if (failingLayers != '') {
        alert(__('!!Cannot Print!!\nThis service disallows printing of the following layer(s).\nPlease disable these layers and print again.\n') + failingLayers);
        return false;
    }
    return true;
};
Heron.widgets.ToolbarBuilder.setItemDef = function (type, createFun, defaultOptions) {
    Heron.widgets.ToolbarBuilder.defs[type].create = createFun;
    Heron.widgets.ToolbarBuilder.defs[type].options = defaultOptions ? defaultOptions : {};
};
Heron.widgets.ToolbarBuilder.build = function (mapPanel, config, toolbar) {
    var toolbarItems = [];
    if (typeof(config) !== "undefined") {
        for (var i = 0; i < config.length; i++) {
            var itemDef = config[i];
            if (itemDef.type == "-") {
                toolbarItems.push("-");
                continue;
            }
            if (itemDef.type == "->") {
                toolbarItems.push("->");
                continue;
            }
            var createFun;
            var defaultItemDef = Heron.widgets.ToolbarBuilder.defs[itemDef.type];
            if (itemDef.create) {
                createFun = itemDef.create;
            } else if (defaultItemDef && defaultItemDef.create) {
                createFun = defaultItemDef.create;
            }
            if (!createFun) {
                continue;
            }
            var coreOptions = {map: mapPanel.getMap(), scope: mapPanel};
            var defaultItemOptions = {};
            if (defaultItemDef && defaultItemDef.options) {
                defaultItemOptions = defaultItemDef.options;
            }
            var extraOptions = itemDef.options ? itemDef.options : {};
            var options = Ext.apply(coreOptions, extraOptions, defaultItemOptions);
            var item = createFun(mapPanel, options);
            if (item) {
                toolbarItems.push(item);
            }
        }
    }
    if (toolbarItems.length > 0) {
        toolbar.add(toolbarItems);
    } else {
        toolbar.setVisible(false);
    }
};
Ext.namespace("Heron.widgets");
Heron.widgets.XMLTreePanel = Ext.extend(Ext.tree.TreePanel, {initComponent: function () {
    Ext.apply(this, {autoScroll: true, rootVisible: false, root: this.root ? this.root : {nodeType: 'async', text: 'Ext JS', draggable: false, id: 'source'}});
    Heron.widgets.XMLTreePanel.superclass.initComponent.apply(this, arguments);
}, xmlTreeFromUrl: function (url) {
    var self = this;
    Ext.Ajax.request({url: url, method: 'GET', params: null, success: function (result, request) {
        self.xmlTreeFromDoc(self, result.responseXML);
    }, failure: function (result, request) {
        alert('error in ajax request');
    }});
}, xmlTreeFromText: function (self, text) {
    var doc = new OpenLayers.Format.XML().read(text);
    self.xmlTreeFromDoc(self, doc);
    return doc;
}, xmlTreeFromDoc: function (self, doc) {
    self.setRootNode(self.treeNodeFromXml(self, doc.documentElement || doc));
}, treeNodeFromXml: function (self, XmlEl) {
    var t = ((XmlEl.nodeType == 3) ? XmlEl.nodeValue : XmlEl.tagName);
    if (t.replace(/\s/g, '').length == 0) {
        return null;
    }
    var result = new Ext.tree.TreeNode({text: t});
    var xmlns = 'xmlns', xsi = 'xsi';
    if (XmlEl.nodeType == 1) {
        Ext.each(XmlEl.attributes, function (a) {
            var nodeName = a.nodeName;
            if (!(XmlEl.parentNode.nodeType == 9 && (nodeName.substring(0, xmlns.length) === xmlns || nodeName.substring(0, xsi.length) === xsi))) {
                var c = new Ext.tree.TreeNode({text: a.nodeName});
                c.appendChild(new Ext.tree.TreeNode({text: a.nodeValue}));
                result.appendChild(c);
            }
        });
        Ext.each(XmlEl.childNodes, function (el) {
            if ((el.nodeType == 1) || (el.nodeType == 3)) {
                var c = self.treeNodeFromXml(self, el);
                if (c) {
                    result.appendChild(c);
                }
            }
        });
    }
    return result;
}});
Ext.reg('hr_xmltreepanel', Heron.widgets.XMLTreePanel);
Ext.namespace("Heron.widgets");
Heron.widgets.IFramePanel = Ext.extend(Ext.Panel, {name: 'iframe', iframe: null, src: Ext.isIE && Ext.isSecure ? Ext.SSL_SECURE_URL : 'about:blank', maskMessage: __('Loading...'), doMask: true, initComponent: function () {
    this.bodyCfg = {tag: 'iframe', frameborder: '0', src: this.src, name: this.name};
    Ext.apply(this, {});
    Heron.widgets.IFramePanel.superclass.initComponent.apply(this, arguments);
    this.addListener = this.on;
}, onRender: function () {
    Heron.widgets.IFramePanel.superclass.onRender.apply(this, arguments);
    this.iframe = Ext.isIE ? this.body.dom.contentWindow : window.frames[this.name];
    this.body.dom[Ext.isIE ? 'onreadystatechange' : 'onload'] = this.loadHandler.createDelegate(this);
}, loadHandler: function () {
    this.src = this.body.dom.src;
    this.removeMask();
}, getIframe: function () {
    return this.iframe;
}, getIframeBody: function () {
    var b = this.iframe.document.getElementsByTagName('body');
    if (!Ext.isEmpty(b)) {
        return b[0];
    } else {
        return'';
    }
}, getUrl: function () {
    return this.body.dom.src;
}, setUrl: function (source) {
    this.setMask();
    this.body.dom.src = source;
}, resetUrl: function () {
    this.setMask();
    this.body.dom.src = this.src;
}, refresh: function () {
    if (!this.isVisible()) {
        return;
    }
    this.setMask();
    this.body.dom.src = this.body.dom.src;
}, setMask: function () {
    if (this.doMask) {
        this.el.mask(this.maskMessage);
    }
}, removeMask: function () {
    if (this.doMask) {
        this.el.unmask();
    }
}});
Ext.reg('hr_iframePpanel', Heron.widgets.IFramePanel);
Ext.namespace("Heron.widgets");
Heron.widgets.ScaleSelectorCombo = Ext.extend(Ext.form.ComboBox, {map: null, tpl: '<tpl for="."><div class="x-combo-list-item">1 : {[parseInt(values.scale + 0.5)]}</div></tpl>', editable: false, width: 130, listWidth: 120, emptyText: __('Scale'), tooltip: __('Scale'), triggerAction: 'all', mode: 'local', initComponent: function () {
    Heron.widgets.ScaleSelectorCombo.superclass.initComponent.apply(this, arguments);
    this.store = new GeoExt.data.ScaleStore({map: this.map});
    for (var i = 0; i < this.store.getCount(); i++) {
        this.store.getAt(i).data.formattedScale = parseInt(this.store.getAt(i).data.scale + 0.5);
    }
    this.on('select', function (combo, record, index) {
        this.map.zoomTo(record.data.level);
    }, this);
    this.map.events.register('zoomend', this, this.zoomendUpdate);
    this.map.events.triggerEvent("zoomend");
}, listeners: {render: function (c) {
    c.el.set({qtip: this.tooltip});
    c.trigger.set({qtip: this.tooltip});
}}, zoomendUpdate: function (record) {
    var scale = this.store.queryBy(function (record) {
        return this.map.getZoom() == record.data.level;
    });
    if (scale.length > 0) {
        scale = scale.items[0];
        this.setValue("1 : " + parseInt(scale.data.scale + 0.5));
    } else {
        if (!this.rendered) {
            return;
        }
        this.clearValue();
    }
}, beforeDestroy: function () {
    this.map.events.unregister('zoomend', this, this.zoomendUpdate);
    Heron.widgets.ScaleSelectorCombo.superclass.beforeDestroy.apply(this, arguments);
}});
Ext.reg('hr_scaleselectorcombo', Heron.widgets.ScaleSelectorCombo);
Ext.namespace("Heron.widgets.search");
Heron.widgets.search.GeocoderCombo = Ext.extend(Ext.form.ComboBox, {map: null, emptyText: __('Search'), loadingText: __('Loading...'), srs: "EPSG:4326", zoom: 10, layerOpts: undefined, queryDelay: 200, valueField: "bounds", displayField: "name", locationField: "lonlat", url: "http://nominatim.openstreetmap.org/search?format=json", queryParam: "q", minChars: 3, hideTrigger: true, tooltip: __('Search'), initComponent: function () {
    if (this.map) {
        this.setMap(this.map);
    }
    if (Ext.isString(this.srs)) {
        this.srs = new OpenLayers.Projection(this.srs);
    }
    if (!this.store) {
        this.store = new Ext.data.JsonStore({root: null, fields: [
            {name: "name", mapping: "display_name"},
            {name: "bounds", convert: function (v, rec) {
                var bbox = rec.boundingbox;
                return[bbox[2], bbox[0], bbox[3], bbox[1]];
            }},
            {name: "lonlat", convert: function (v, rec) {
                return[rec.lon, rec.lat];
            }}
        ], proxy: new Ext.data.ScriptTagProxy({url: this.url, callbackParam: "json_callback"})});
    }
    this.on({added: this.handleAdded, select: this.handleSelect, focus: function () {
        this.clearValue();
        this.removeLocationFeature();
    }, scope: this});
    return Heron.widgets.search.GeocoderCombo.superclass.initComponent.apply(this, arguments);
}, handleAdded: function () {
    if (!this.map) {
        this.setMap(Heron.App.getMap());
    }
}, handleSelect: function (combo, rec) {
    var value = this.getValue();
    if (Ext.isArray(value)) {
        var mapProj = this.map.getProjectionObject();
        delete this.center;
        delete this.locationFeature;
        if (this.zoom < 0) {
            this.map.zoomToExtent(OpenLayers.Bounds.fromArray(value).transform(this.srs, mapProj));
        } else {
            this.map.setCenter(OpenLayers.LonLat.fromArray(value).transform(this.srs, mapProj), Math.max(this.map.getZoom(), this.zoom));
        }
        this.center = this.map.getCenter();
        var lonlat = rec.get(this.locationField);
        if (this.layer && lonlat) {
            var geom = new OpenLayers.Geometry.Point(this.center.lon, this.center.lat).transform(this.srs, mapProj);
            this.locationFeature = new OpenLayers.Feature.Vector(geom, rec.data);
            this.layer.addFeatures([this.locationFeature]);
            var vm = this.map.getLayersByName(this.layer);
            if (vm.length === 0) {
                this.layer.setVisibility(true);
            }
        }
        var lropts = this.layerOpts;
        if (lropts) {
            var map = Heron.App.getMap();
            for (var l = 0; l < lropts.length; l++) {
                if (lropts[l]['layerOn']) {
                    var mapLayers = map.getLayersByName(lropts[l]['layerOn']);
                    for (var n = 0; n < mapLayers.length; n++) {
                        if (mapLayers[n].isBaseLayer) {
                            map.setBaseLayer(mapLayers[n]);
                        } else {
                            mapLayers[n].setVisibility(true);
                        }
                        if (lropts[l]['layerOpacity']) {
                            mapLayers[n].setOpacity(lropts[l]['layerOpacity']);
                        }
                    }
                }
            }
        }
    }
    (function () {
        this.triggerBlur();
        this.el.blur();
    }).defer(100, this);
}, removeLocationFeature: function () {
    if (this.locationFeature) {
        this.layer.destroyFeatures([this.locationFeature]);
    }
}, clearResult: function () {
    if (this.center && !this.map.getCenter().equals(this.center)) {
        this.clearValue();
    }
}, setMap: function (map) {
    if (map instanceof GeoExt.MapPanel) {
        map = map.map;
    }
    this.map = map;
    map.events.on({"moveend": this.clearResult, scope: this});
}, addToMapPanel: Ext.emptyFn, beforeDestroy: function () {
    this.map.events.un({"moveend": this.clearResult, scope: this});
    this.removeLocationFeature();
    delete this.map;
    delete this.layer;
    delete this.center;
    Heron.widgets.search.GeocoderCombo.superclass.beforeDestroy.apply(this, arguments);
}, listeners: {render: function (c) {
    c.el.set({qtip: this.tooltip});
    c.trigger.set({qtip: this.tooltip});
}}});
Ext.reg("hr_geocodercombo", Heron.widgets.search.GeocoderCombo);
Heron.version = '1.0.1';
