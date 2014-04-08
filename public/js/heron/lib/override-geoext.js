/**
 * Copyright (c) 2008-2012 The Open Source Geospatial Foundation
 *
 * Published under the BSD license.
 * See http://svn.geoext.org/core/trunk/geoext/license.txt for the full text
 * of the license.
 */

/** Using the ExtJS-way to override single methods of classes. */
Ext.override(GeoExt.WMSLegend, {

    /*  NOTE (JvdB): override the WMSLegend.getLegendUrl() method: to allow baseParams, in particular FORMAT=
     *  to be merged in via Heron config
     *  the version below taken from GeoExt GitHub on 27.sept.2012
     */

    /** api: (define)
     *  module = GeoExt
     *  class = WMSLegend
     */
    /**
     * @param layerName
     * @param layerNames
     */
    /** private: method[getLegendUrl]
     *  :param layerName: ``String`` A sublayer.
     *  :param layerNames: ``Array(String)`` The array of sublayers,
     *      read from this.layerRecord if not provided.
     *  :return: ``String`` The legend URL.
     *
     *  Get the legend URL of a sublayer.
     */
    getLegendUrl: function (layerName, layerNames) {
        var rec = this.layerRecord;
        var url;
        var styles = rec && rec.get("styles");
        var layer = rec.getLayer();
        layerNames = layerNames || [layer.params.LAYERS].join(",").split(",");

        var styleNames = layer.params.STYLES &&
            [layer.params.STYLES].join(",").split(",");
        var idx = layerNames.indexOf(layerName);
        var styleName = styleNames && styleNames[idx];
        // check if we have a legend URL in the record's
        // "styles" data field
        if (styles && styles.length > 0) {
            if (styleName) {
                Ext.each(styles, function (s) {
                    url = (s.name == styleName && s.legend) && s.legend.href;
                    return !url;
                });
            } else if (this.defaultStyleIsFirst === true && !styleNames && !layer.params.SLD && !layer.params.SLD_BODY) {
                url = styles[0].legend && styles[0].legend.href;
            }
        }
        if (!url) {
            url = layer.getFullRequestString({
                REQUEST: "GetLegendGraphic",
                WIDTH: null,
                HEIGHT: null,
                EXCEPTIONS: "application/vnd.ogc.se_xml",
                LAYER: layerName,
                LAYERS: null,
                STYLE: (styleName !== '') ? styleName : null,
                STYLES: null,
                SRS: null,
                FORMAT: null,
                TIME: null
            });
        }
        var params = Ext.apply({}, this.baseParams);
        if (layer.params._OLSALT) {
            // update legend after a forced layer redraw
            params._OLSALT = layer.params._OLSALT;
        }
        url = Ext.urlAppend(url, Ext.urlEncode(params));
        if (url.toLowerCase().indexOf("request=getlegendgraphic") != -1) {
            if (url.toLowerCase().indexOf("format=") == -1) {
                url = Ext.urlAppend(url, "FORMAT=image%2Fgif");
            }
            // add scale parameter - also if we have the url from the record's
            // styles data field and it is actually a GetLegendGraphic request.
            if (this.useScaleParameter === true) {
                var scale = layer.map.getScale();
                url = Ext.urlAppend(url, "SCALE=" + scale);
            }
        }

        return url;
    }
});

/*  NOTE (WW): override the GeoExt.tree.LayerNodeUI.enforceOneVisible() method:
 *  to prevent null pointer assignment in 'this.node.getOwnerTree().getChecked()' when
 *  using the 'hr_layertreepanel' panel without the 'hropts' option
 *
 *  Version below taken from GeoExt GitHub on 01.nov.2012
 */
Ext.override(GeoExt.tree.LayerNodeUI, {

    /** private: method[enforceOneVisible]
     *
     *  Makes sure that only one layer is visible if checkedGroup is set.
     */
    enforceOneVisible: function () {
        var attributes = this.node.attributes;
        var group = attributes.checkedGroup;
        // If we are in the baselayer group, the map will take care of
        // enforcing visibility.
        if (group && group !== "gx_baselayer") {
            var layer = this.node.layer;
// --- WW ---
            if (attributes.checked) {
// ----------
                var checkedNodes = this.node.getOwnerTree().getChecked();
                var checkedCount = 0;
                // enforce "not more than one visible"
                Ext.each(checkedNodes, function (n) {
                    var l = n.layer;
                    if (!n.hidden && n.attributes.checkedGroup === group) {
                        checkedCount++;
                        if (l != layer && attributes.checked) {
                            l.setVisibility(false);
                        }
                    }
                });
                // enforce "at least one visible"
                if (checkedCount === 0 && attributes.checked == false) {
                    layer.setVisibility(true);
                }
// ----------
            }
// ----------
        }
    }

});

/*  NOTE (WW): append the GeoExt.tree.LayerNode.renderX() method:
 *  to prevent problems with the 'checkedGroup' flag for creating radiobuttons when
 *  using the 'hr_activelayerspanel' or 'hr_activethemespanel' panel - instead of a
 *  'baselayer radiobutton' a 'disabled baselayer checkbox' is shown
 *
 *  Version below taken from GeoExt GitHub on 01.nov.2012
 */
Ext.override(GeoExt.tree.LayerNode, {

    /** private: method[renderX]
     *  :param bulkRender: ``Boolean``
     */
    renderX: function (bulkRender) {
        var layer = this.layer instanceof OpenLayers.Layer && this.layer;
        if (!layer) {
            // guess the store if not provided
            if (!this.layerStore || this.layerStore == "auto") {
                this.layerStore = GeoExt.MapPanel.guess().layers;
            }
            // now we try to find the layer by its name in the layer store
            var i = this.layerStore.findBy(function (o) {
                return o.get("title") == this.layer;
            }, this);
            if (i != -1) {
                // if we found the layer, we can assign it and everything
                // will be fine
                layer = this.layerStore.getAt(i).getLayer();
            }
        }
        if (!this.rendered || !layer) {
            var ui = this.getUI();

            if (layer) {
                this.layer = layer;
                // no DD and radio buttons for base layers
                if (layer.isBaseLayer) {
                    this.draggable = false;
// --- WW ---
                    // Don't use 'checkedGroup' argument

                    // Ext.applyIf(this.attributes, {
                    // checkedGroup: "gx_baselayer"
                    // });

                    // Disabled baselayer checkbox
                    this.disabled = true;
// ----------
                }

                //base layers & alwaysInRange layers should never be auto-disabled
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
    }

});

// Allow for case insensitive LIKE and EQUALS in OpenLayers Filters for WFS search
// v0.73 18.4.2013 JvdB
Ext.override(GeoExt.form.SearchAction, {
    /** private: method[run]
     }
     *  Run the action.
     */
    run: function () {
        var o = this.options;
        var f = GeoExt.form.toFilter(this.form, o);
        if (o.clientValidation === false || this.form.isValid()) {

            if (o.abortPrevious && this.form.prevResponse) {
                o.protocol.abort(this.form.prevResponse);
            }

            this.form.prevResponse = o.protocol.read(
                Ext.applyIf({
                    filter: f,
                    callback: this.handleResponse,
                    scope: this
                }, o)
            );

        } else if (o.clientValidation !== false) {
            // client validation failed
            this.failureType = Ext.form.Action.CLIENT_INVALID;
            this.form.afterAction(this, false);
        }
    }
});

GeoExt.form.toFilter = function (form, options) {
    // JvdB: use options to pass extra filter options
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

        if (s.length > 1 &&
            (type = GeoExt.form.toFilter.FILTER_MAP[s[1]]) !== undefined) {
            prop = s[0];
        } else {
            type = OpenLayers.Filter.Comparison.EQUAL_TO;
        }

        if (type === OpenLayers.Filter.Comparison.LIKE) {
            // JvdB fix issue https://code.google.com/p/geoext-viewer/issues/detail?id=235
            // Do not send wildcards for empty or null values.
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
                    // do nothing, just take the value
                    break;
            }
        }

        filters.push(
            new OpenLayers.Filter.Comparison({
                type: type,
                value: value,
                property: prop,
                matchCase: matchCase
            })
        );
    }

    return filters.length == 1 && logicalOp != OpenLayers.Filter.Logical.NOT ?
        filters[0] :
        new OpenLayers.Filter.Logical({
            type: logicalOp || OpenLayers.Filter.Logical.AND,
            filters: filters
        });
};

/** private: constant[FILTER_MAP]
 *  An object mapping operator strings as found in field names to
 *      ``OpenLayers.Filter.Comparison`` types.
 */
GeoExt.form.toFilter.FILTER_MAP = {
    "eq": OpenLayers.Filter.Comparison.EQUAL_TO,
    "ne": OpenLayers.Filter.Comparison.NOT_EQUAL_TO,
    "lt": OpenLayers.Filter.Comparison.LESS_THAN,
    "le": OpenLayers.Filter.Comparison.LESS_THAN_OR_EQUAL_TO,
    "gt": OpenLayers.Filter.Comparison.GREATER_THAN,
    "ge": OpenLayers.Filter.Comparison.GREATER_THAN_OR_EQUAL_TO,
    "like": OpenLayers.Filter.Comparison.LIKE
};

GeoExt.form.ENDS_WITH = 1;
GeoExt.form.STARTS_WITH = 2;
GeoExt.form.CONTAINS = 3;


// v0.74 11.06.2013 JvdB
// Copy resolutions for PrintPreview in PrintMapPanel.
// https://code.google.com/p/geoext-viewer/issues/detail?id=191
// GeoExt issue: http://trac.geoext.org/ticket/306
// Somehow not solved in geoExt 1.1, by copying resolutions from
// main Map this works.
// v1.0.1 17.2.2014 JvdB
// Some fixes for proper Vector Layer cloning: protocol and StyleMap properties
Ext.override(GeoExt.PrintMapPanel, {
    /**
     * private: method[initComponent]
     * private override
     */
    initComponent: function () {
        if (this.sourceMap instanceof GeoExt.MapPanel) {
            this.sourceMap = this.sourceMap.map;
        }

        if (!this.map) {
            this.map = {};
        }
        Ext.applyIf(this.map, {
            projection: this.sourceMap.getProjection(),
            maxExtent: this.sourceMap.getMaxExtent(),
            maxResolution: this.sourceMap.getMaxResolution(),
            // ADDED by JvdB: copy resolutions if any from source map, otherwiswe keep original
            resolutions: this.sourceMap.resolutions ? this.sourceMap.resolutions.slice(0) : this.map.resolutions,
            units: this.sourceMap.getUnits()
        });

        if (!(this.printProvider instanceof GeoExt.data.PrintProvider)) {
            this.printProvider = new GeoExt.data.PrintProvider(
                this.printProvider);
        }
        this.printPage = new GeoExt.data.PrintPage({
            printProvider: this.printProvider
        });

        this.previewScales = new Ext.data.Store();
        this.previewScales.add(this.printProvider.scales.getRange());

        this.layers = [];
        var layer, clonedLayer;
        Ext.each(this.sourceMap.layers, function (layer) {

            if (layer.getVisibility() === true) {
                // JvdB: for Vector Layers the original Layer's protocol property otherwise gets destroyed..
                if (layer.protocol) {
                    layer.protocol.autoDestroy = false;
                }
                clonedLayer = layer.clone();
                // JvdB: If a Layer has a StyleMap it is not always cloned properly
                if (layer.styleMap && layer.styleMap.styles) {
                    clonedLayer.styleMap = new OpenLayers.StyleMap(layer.styleMap.styles);
                }
                this.layers.push(clonedLayer);
            }
        }, this);

        this.extent = this.sourceMap.getExtent();

        GeoExt.PrintMapPanel.superclass.initComponent.call(this);
    }
});


// GeoExt.data.PrintProvider: taken from
// https://raw.github.com/geoext/geoext/master/lib/GeoExt/data/PrintProvider.js
// on oct 6, 2013, updated to GeoExt GitHub version on 16.dec.2013.
// Includes Heron-fix (see "Heron") fix for Fixes tileOrigin setting for TileCache/TMS
// Heron fix JvdB 6 oct 2013
// Add tileOrigin otherwise MapFish Print will be confused.
// https://github.com/mapfish/mapfish-print/issues/68

/**
 * Plus (16.dec.2013) changes for selecting/printing other Output Formats except PDF.
 * See https://code.google.com/p/geoext-viewer/issues/detail?id=189
 * and https://github.com/geoext/geoext/issues/91, solved with Pull Req:
 * https://github.com/geoext/geoext/pull/95
 */

/* Complete version of PrintProvider.js as we also need to override constructor. */

/**
 * Copyright (c) 2008-2012 The Open Source Geospatial Foundation
 *
 * Published under the BSD license.
 * See http://svn.geoext.org/core/trunk/geoext/license.txt for the full text
 * of the license.
 */

/**
 * @require OpenLayers/Layer.js
 * @require OpenLayers/Format/JSON.js
 * @require OpenLayers/Format/GeoJSON.js
 * @require OpenLayers/BaseTypes/Class.js
 */

/** api: (define)
 *  module = GeoExt.data
 *  class = PrintProvider
 *  base_link = `Ext.util.Observable <http://dev.sencha.com/deploy/dev/docs/?class=Ext.util.Observable>`_
 */
Ext.namespace("GeoExt.data");

/** api: example
 *  Minimal code to print as much of the current map extent as possible as
 *  soon as the print service capabilities are loaded, using the first layout
 *  reported by the print service:
 *
 *  .. code-block:: javascript
 *
 *      var mapPanel = new GeoExt.MapPanel({
 *          renderTo: "mappanel",
 *          layers: [new OpenLayers.Layer.WMS("wms", "/geoserver/wms",
 *              {layers: "topp:tasmania_state_boundaries"})],
 *          center: [146.56, -41.56],
 *          zoom: 7
 *      });
 *      var printProvider = new GeoExt.data.PrintProvider({
 *          url: "/geoserver/pdf",
 *          listeners: {
 *              "loadcapabilities": function() {
 *                  var printPage = new GeoExt.data.PrintPage({
 *                      printProvider: printProvider
 *                  });
 *                  printPage.fit(mapPanel, true);
 *                  printProvider.print(mapPanel, printPage);
 *              }
 *          }
 *      });
 */

/** api: constructor
 *  .. class:: PrintProvider
 *
 *  Provides an interface to a Mapfish or GeoServer print module. For printing,
 *  one or more instances of :class:`GeoExt.data.PrintPage` are also required
 *  to tell the PrintProvider about the scale and extent (and optionally
 *  rotation) of the page(s) we want to print.
 */
GeoExt.data.PrintProvider = Ext.extend(Ext.util.Observable, {

    /** api: config[url]
     *  ``String`` Base url of the print service. Only required if
     *  ``capabilities`` is not provided. This
     *  is usually something like http://path/to/mapfish/print for Mapfish,
     *  and http://path/to/geoserver/pdf for GeoServer with the printing
     *  extension installed. This property requires that the print service is
     *  at the same origin as the application (or accessible via proxy).
     */

    /** private:  property[url]
     *  ``String`` Base url of the print service. Will always have a trailing
     *  "/".
     */
    url: null,

    /** api: config[autoLoad]
     *  ``Boolean`` If set to true, the capabilities will be loaded upon
     *  instance creation, and ``loadCapabilities`` does not need to be called
     *  manually. Setting this when ``capabilities`` and no ``url`` is provided
     *  has no effect. Default is false.
     */

    /** api: config[capabilities]
     *  ``Object`` Capabilities of the print service. Only required if ``url``
     *  is not provided. This is the object returned by the ``info.json``
     *  endpoint of the print service, and is usually obtained by including a
     *  script tag pointing to
     *  http://path/to/printservice/info.json?var=myvar in the head of the
     *  html document, making the capabilities accessible as ``window.myvar``.
     *  This property should be used when no local print service or proxy is
     *  available, or when you do not listen for the ``loadcapabilities``
     *  events before creating components that require the PrintProvider's
     *  capabilities to be available.
     */

    /** private: property[capabilities]
     *  ``Object`` Capabilities as returned from the print service.
     */
    capabilities: null,

    /** api: config[method]
     *  ``String`` Either ``POST`` or ``GET`` (case-sensitive). Method to use
     *  when sending print requests to the servlet. If the print service is at
     *  the same origin as the application (or accessible via proxy), then
     *  ``POST`` is recommended. Use ``GET`` when accessing a remote print
     *  service with no proxy available, but expect issues with character
     *  encoding and URLs exceeding the maximum length. Default is ``POST``.
     */

    /** private: property[method]
     *  ``String`` Either ``POST`` or ``GET`` (case-sensitive). Method to use
     *  when sending print requests to the servlet.
     */
    method: "POST",

    /** api: config[encoding]
     * ``String`` The encoding to set in the headers when requesting the print
     * service. Prevent character encoding issues, especially when using IE.
     * Default is retrieved from document charset or characterSet if existing
     * or ``UTF-8`` if not.
     */
    encoding: document.charset || document.characterSet || "UTF-8",

    /** api: config[timeout]
     *  ``Number`` Timeout of the POST Ajax request used for the print request
     *  (in milliseconds). Default of 30 seconds. Has no effect if ``method``
     *  is set to ``GET``.
     */
    timeout: 30000,

    /** api: property[customParams]
     *  ``Object`` Key-value pairs of custom data to be sent to the print
     *  service. Optional. This is e.g. useful for complex layout definitions
     *  on the server side that require additional parameters.
     */
    customParams: null,

    /** api: config[baseParams]
     *  ``Object`` Key-value pairs of base params to be add to every
     *  request to the service. Optional.
     */

    /** api: property[scales]
     *  ``Ext.data.JsonStore`` read-only. A store representing the scales
     *  available.
     *
     *  Fields of records in this store:
     *
     *  * name - ``String`` the name of the scale
     *  * value - ``Float`` the scale denominator
     */
    scales: null,

    /** api: property[dpis]
     *  ``Ext.data.JsonStore`` read-only. A store representing the dpis
     *  available.
     *
     *  Fields of records in this store:
     *
     *  * name - ``String`` the name of the dpi
     *  * value - ``Float`` the dots per inch
     */
    dpis: null,

    /** api: property[outputFormats]
     *  ``Ext.data.JsonStore`` read-only. A store representing the output formats
     *  available.
     *
     *  Fields of the output formats in this store:
     *
     *  * name - ``String`` the name of the output format
     */
    outputFormats: null,

    /** api: property[outputFormatsEnabled]
     *  ``Boolean`` read-only. Should outputFormats be populated and used?
     *  Default value is 'False'
     */
    outputFormatsEnabled: false,

    /** api: property[layouts]
     *  ``Ext.data.JsonStore`` read-only. A store representing the layouts
     *  available.
     *
     *  Fields of records in this store:
     *
     *  * name - ``String`` the name of the layout
     *  * size - ``Object`` width and height of the map in points
     *  * rotation - ``Boolean`` indicates if rotation is supported
     */
    layouts: null,

    /** api: property[dpi]
     *  ``Ext.data.Record`` the record for the currently used resolution.
     *  Read-only, use ``setDpi`` to set the value.
     */
    dpi: null,

    /** api: property[layout]
     *  ``Ext.data.Record`` the record of the currently used layout. Read-only,
     *  use ``setLayout`` to set the value.
     */
    layout: null,

    /** api: property[outputFormat]
     *  ``Ext.data.Record`` the record of the currently used output format. Read-only,
     *  use ``setOutputFormat`` to set the value.
     */
    outputFormat: null,

    /** api: property[defaultOutputFormatName]
     *  ``String`` the name of the default output format.
     */
    defaultOutputFormatName: 'pdf',

    /** private:  method[constructor]
     *  Private constructor override.
     */
    constructor: function (config) {
        this.initialConfig = config;
        Ext.apply(this, config);

        if (!this.customParams) {
            this.customParams = {};
        }

        this.addEvents(
            /** api: event[loadcapabilities]
             *  Triggered when the capabilities have finished loading. This
             *  event will only fire when ``capabilities`` is not  configured.
             *
             *  Listener arguments:
             *
             *  * printProvider - :class:`GeoExt.data.PrintProvider` this
             *    PrintProvider
             *  * capabilities - ``Object`` the capabilities
             */
            "loadcapabilities",

            /** api: event[layoutchange]
             *  Triggered when the layout is changed.
             *
             *  Listener arguments:
             *
             *  * printProvider - :class:`GeoExt.data.PrintProvider` this
             *    PrintProvider
             *  * layout - ``Ext.data.Record`` the new layout
             */
            "layoutchange",

            /** api: event[dpichange]
             *  Triggered when the dpi value is changed.
             *
             *  Listener arguments:
             *
             *  * printProvider - :class:`GeoExt.data.PrintProvider` this
             *    PrintProvider
             *  * dpi - ``Ext.data.Record`` the new dpi record
             */
            "dpichange",

            /** api: event[outputformatchange]
             *  Triggered when the outputFormat  value is changed.
             *
             *  Listener arguments:
             *
             *  * printProvider - :class:`GeoExt.data.PrintProvider` this
             *    PrintProvider
             *  * outputFormat - ``Ext.data.Record`` the new output format record
             */
            "outputformatchange",

            /** api: event[beforeprint]
             *  Triggered when the print method is called.
             *  TODO: rename this event to beforeencode
             *
             *  Listener arguments:
             *
             *  * printProvider - :class:`GeoExt.data.PrintProvider` this
             *    PrintProvider
             *  * map - ``OpenLayers.Map`` the map being printed
             *  * pages - Array of :class:`GeoExt.data.PrintPage` the print
             *    pages being printed
             *  * options - ``Object`` the options to the print command
             */
            "beforeprint",

            /** api: event[print]
             *  Triggered when the print document is opened.
             *
             *  Listener arguments:
             *
             *  * printProvider - :class:`GeoExt.data.PrintProvider` this
             *    PrintProvider
             *  * url - ``String`` the url of the print document
             */
            "print",

            /** api: event[printexception]
             *  Triggered when using the ``POST`` method, when the print
             *  backend returns an exception.
             *
             *  Listener arguments:
             *
             *  * printProvider - :class:`GeoExt.data.PrintProvider` this
             *    PrintProvider
             *  * response - ``Object`` the response object of the XHR
             */
            "printexception",

            /** api: event[beforeencodelayer]
             *  Triggered before a layer is encoded. This can be used to
             *  exclude layers from the printing, by having the listener
             *  return false.
             *
             *  Listener arguments:
             *
             *  * printProvider - :class:`GeoExt.data.PrintProvider` this
             *    PrintProvider
             *  * layer - ``OpenLayers.Layer`` the layer which is about to be
             *    encoded.
             */
            "beforeencodelayer",

            /** api: event[encodelayer]
             *  Triggered when a layer is encoded. This can be used to modify
             *  the encoded low-level layer object that will be sent to the
             *  print service.
             *
             *  Listener arguments:
             *
             *  * printProvider - :class:`GeoExt.data.PrintProvider` this
             *    PrintProvider
             *  * layer - ``OpenLayers.Layer`` the layer which is about to be
             *    encoded.
             *  * encodedLayer - ``Object`` the encoded layer that will be
             *    sent to the print service.
             */
            "encodelayer",

            /** api: events[beforedownload]
             *  Triggered before the PDF is downloaded. By returning false from
             *  a listener, the default handling of the PDF can be cancelled
             *  and applications can take control over downloading the PDF.
             *  TODO: rename to beforeprint after the current beforeprint event
             *  has been renamed to beforeencode.
             *
             *  Listener arguments:
             *  * printProvider - :class:`GeoExt.data.PrintProvider` this
             *    PrintProvider
             *  * url - ``String`` the url of the print document
             */
            "beforedownload",

            /** api: event[beforeencodelegend]
             *  Triggered before the legend is encoded. If the listener
             *  returns false, the default encoding based on GeoExt.LegendPanel
             *  will not be executed. This provides an option for application
             *  to get legend info from a custom component other than
             *  GeoExt.LegendPanel.
             *
             *  Listener arguments:
             *
             *  * printProvider - :class:`GeoExt.data.PrintProvider` this
             *    PrintProvider
             *  * jsonData - ``Object`` The data that will be sent to the print
             *    server. Can be used to populate jsonData.legends.
             *  * legend - ``Object`` The legend supplied in the options which were
             *    sent to the print function.
             */
            "beforeencodelegend"

        );

        GeoExt.data.PrintProvider.superclass.constructor.apply(this, arguments);

        this.scales = new Ext.data.JsonStore({
            root: "scales",
            sortInfo: {field: "value", direction: "DESC"},
            fields: ["name", {name: "value", type: "float"}]
        });

        this.dpis = new Ext.data.JsonStore({
            root: "dpis",
            fields: ["name", {name: "value", type: "float"}]
        });

        // Optional outputformats
        if (this.outputFormatsEnabled === true) {
            this.outputFormats = new Ext.data.JsonStore({
                root: "outputFormats",
                sortInfo: {field: "name", direction: "ASC"},
                fields: ["name"]
            });
        }

        this.layouts = new Ext.data.JsonStore({
            root: "layouts",
            fields: [
                "name",
                {name: "size", mapping: "map"},
                {name: "rotation", type: "boolean"}
            ]
        });

        if (config.capabilities) {
            this.loadStores();
        } else {
            if (this.url.split("/").pop()) {
                this.url += "/";
            }
            this.initialConfig.autoLoad && this.loadCapabilities();
        }
    },

    /** api: method[setLayout]
     *  :param layout: ``Ext.data.Record`` the record of the layout.
     *
     *  Sets the layout for this printProvider.
     */
    setLayout: function (layout) {
        this.layout = layout;
        this.fireEvent("layoutchange", this, layout);
    },

    /** api: method[setDpi]
     *  :param dpi: ``Ext.data.Record`` the dpi record.
     *
     *  Sets the dpi for this printProvider.
     */
    setDpi: function (dpi) {
        this.dpi = dpi;
        this.fireEvent("dpichange", this, dpi);
    },

    /** api: method[setOutputFormat]
     *  :param outputFormat: ``Ext.data.Record`` the format record.
     *
     *  Sets the output print format for this printProvider.
     */
    setOutputFormat: function (outputFormat) {
        this.outputFormat = outputFormat;
        this.fireEvent("outputformatchange", this, outputFormat);
    },

    /** api: method[print]
     *  :param map: ``GeoExt.MapPanel`` or ``OpenLayers.Map`` The map to print.
     *  :param pages: ``Array`` of :class:`GeoExt.data.PrintPage` or
     *      :class:`GeoExt.data.PrintPage` page(s) to print.
     *  :param options: ``Object`` of additional options, see below.
     *
     *  Sends the print command to the print service and opens a new window
     *  with the resulting PDF.
     *
     *  Valid properties for the ``options`` argument:
     *
     *      * ``legend`` - :class:`GeoExt.LegendPanel` If provided, the legend
     *        will be added to the print document. For the printed result to
     *        look like the LegendPanel, the following ``!legends`` block
     *        should be included in the ``items`` of your page layout in the
     *        print module's configuration file:
     *
     *        .. code-block:: none
     *
     *          - !legends
     *              maxIconWidth: 0
     *              maxIconHeight: 0
     *              classIndentation: 0
     *              layerSpace: 5
     *              layerFontSize: 10
     *
     *      * ``overview`` - :class:`OpenLayers.Control.OverviewMap` If provided,
     *        the layers for the overview map in the printout will be taken from
     *        the OverviewMap control. If not provided, the print service will
     *        use the main map's layers for the overview map. Applies only for
     *        layouts configured to print an overview map.
     */
    print: function (map, pages, options) {
        if (map instanceof GeoExt.MapPanel) {
            map = map.map;
        }
        pages = pages instanceof Array ? pages : [pages];
        options = options || {};
        if (this.fireEvent("beforeprint", this, map, pages, options) === false) {
            return;
        }

        var jsonData = Ext.apply({
            units: map.getUnits(),
            srs: map.baseLayer.projection.getCode(),
            layout: this.layout.get("name"),
            dpi: this.dpi.get("value"),
            outputFormat: this.outputFormat ? this.outputFormat.get("name") : this.defaultOutputFormatName
        }, this.customParams);

        var pagesLayer = pages[0].feature.layer;
        var encodedLayers = [];

        // ensure that the baseLayer is the first one in the encoded list
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
            encodedPages.push(Ext.apply({
                center: [page.center.lon, page.center.lat],
                scale: page.scale.get("value"),
                rotation: page.rotation
            }, page.customParams));
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
                legend = legend.cloneConfig({
                    renderTo: document.body,
                    hidden: true
                });
            }
            var encodedLegends = [];
            legend.items && legend.items.each(function (cmp) {
                if (!cmp.hidden) {
                    var encFn = this.encoders.legends[cmp.getXType()];
                    // MapFish Print doesn't currently support per-page
                    // legends, so we use the scale of the first page.
                    encodedLegends = encodedLegends.concat(
                        encFn.call(this, cmp, jsonData.pages[0].scale));
                }
            }, this);
            if (!rendered) {
                legend.destroy();
            }
            jsonData.legends = encodedLegends;
        }

        if (this.method === "GET") {
            var url = Ext.urlAppend(this.capabilities.printURL,
                "spec=" + encodeURIComponent(Ext.encode(jsonData)));
            this.download(url);
        } else {
            Ext.Ajax.request({
                url: this.capabilities.createURL,
                timeout: this.timeout,
                jsonData: jsonData,
                headers: {"Content-Type": "application/json; charset=" + this.encoding},
                success: function (response) {
                    var url = Ext.decode(response.responseText).getURL;
                    this.download(url);
                },
                failure: function (response) {
                    this.fireEvent("printexception", this, response);
                },
                params: this.initialConfig.baseParams,
                scope: this
            });
        }
    },

    /** private: method[download]
     *  :param url: ``String``
     */
    download: function (url) {
        if (this.fireEvent("beforedownload", this, url) !== false) {
            if (Ext.isOpera) {
                // Make sure that Opera don't replace the content tab with
                // the pdf
                window.open(url);
            } else {
                // This avoids popup blockers for all other browsers
                window.location.href = url;
            }
        }
        this.fireEvent("print", this, url);
    },

    /** api: method[loadCapabilities]
     *
     *  Loads the capabilities from the print service. If this instance is
     *  configured with either ``capabilities`` or a ``url`` and ``autoLoad``
     *  set to true, then this method does not need to be called from the
     *  application.
     */
    loadCapabilities: function () {
        if (!this.url) {
            return;
        }
        var url = this.url + "info.json";
        Ext.Ajax.request({
            url: url,
            method: "GET",
            disableCaching: false,
            success: function (response) {
                this.capabilities = Ext.decode(response.responseText);
                this.loadStores();
            },
            params: this.initialConfig.baseParams,
            scope: this
        });
    },

    /** private: method[loadStores]
     */
    loadStores: function () {
        this.scales.loadData(this.capabilities);
        this.dpis.loadData(this.capabilities);
        this.layouts.loadData(this.capabilities);

        this.setLayout(this.layouts.getAt(0));
        this.setDpi(this.dpis.getAt(0));

        // In rare cases (YAML+MFP-dependent) no Output Formats are returned
        if (this.outputFormatsEnabled && this.capabilities.outputFormats) {
            this.outputFormats.loadData(this.capabilities);
            var defaultOutputIndex = this.outputFormats.find('name', this.defaultOutputFormatName);
            this.setOutputFormat(defaultOutputIndex > -1 ? this.outputFormats.getAt(defaultOutputIndex) : this.outputFormats.getAt(0));
        }
        this.fireEvent("loadcapabilities", this, this.capabilities);
    },

    /** private: method[encodeLayer]
     *  :param layer: ``OpenLayers.Layer``
     *  :return: ``Object``
     *
     *  Encodes a layer for the print service.
     */
    encodeLayer: function (layer) {
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
        // only return the encLayer object when we have a type. Prevents a
        // fallback on base encoders like HTTPRequest.
        return (encLayer && encLayer.type) ? encLayer : null;
    },

    /** private: method[getAbsoluteUrl]
     *  :param url: ``String``
     *  :return: ``String``
     *
     *  Converts the provided url to an absolute url.
     */
    getAbsoluteUrl: function (url) {
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
    },

    /** private: property[encoders]
     *  ``Object`` Encoders for all print content
     */
    encoders: {
        "layers": {
            "Layer": function (layer) {
                var enc = {};
                if (layer.options && layer.options.maxScale) {
                    enc.minScaleDenominator = layer.options.maxScale;
                }
                if (layer.options && layer.options.minScale) {
                    enc.maxScaleDenominator = layer.options.minScale;
                }
                return enc;
            },
            "WMS": function (layer) {
                var enc = this.encoders.layers.HTTPRequest.call(this, layer);
                enc.singleTile = layer.singleTile;
                Ext.apply(enc, {
                    type: 'WMS',
                    layers: [layer.params.LAYERS].join(",").split(","),
                    format: layer.params.FORMAT,
                    styles: [layer.params.STYLES].join(",").split(","),
                    singleTile: layer.singleTile
                });
                var param;
                for (var p in layer.params) {
                    param = p.toLowerCase();
                    if (layer.params[p] != null && !layer.DEFAULT_PARAMS[param] &&
                        "layers,styles,width,height,srs".indexOf(param) == -1) {
                        if (!enc.customParams) {
                            enc.customParams = {};
                        }
                        enc.customParams[p] = layer.params[p];
                    }
                }
                return enc;
            },
            "OSM": function (layer) {
                var enc = this.encoders.layers.TileCache.call(this, layer);
                return Ext.apply(enc, {
                    type: 'OSM',
                    baseURL: enc.baseURL.substr(0, enc.baseURL.indexOf("$")),
                    extension: "png"
                });
            },
            "XYZ": function (layer) {
                var enc = this.encoders.layers.TileCache.call(this, layer);
                return Ext.apply(enc, {
                    type: 'XYZ',
                    baseURL: enc.baseURL.substr(0, enc.baseURL.indexOf("$")),
                    extension: enc.baseURL.substr(enc.baseURL.lastIndexOf("$")).split(".").pop(),
                    tileOriginCorner: layer.tileOriginCorner
                });
            },
            "TMS": function (layer) {
                var enc = this.encoders.layers.TileCache.call(this, layer);
                return Ext.apply(enc, {
                    type: 'TMS',
                    format: layer.type
                });
            },
            "TileCache": function (layer) {
                var enc = this.encoders.layers.HTTPRequest.call(this, layer);
                // Heron fix JvdB 6 oct 2013
                // Add tileOrigin otherwise MapFish Print will be confused.
                // https://github.com/mapfish/mapfish-print/issues/68
                var maxExtent = layer.maxExtent.toArray();
                var tileOriginX = layer.tileOrigin ? layer.tileOrigin.lon : maxExtent[0];
                var tileOriginY = layer.tileOrigin ? layer.tileOrigin.lat : maxExtent[1];
                return Ext.apply(enc, {
                    type: 'TileCache',
                    layer: layer.layername,
                    maxExtent: maxExtent,
                    tileOrigin: {x: tileOriginX, y: tileOriginY},
                    tileSize: [layer.tileSize.w, layer.tileSize.h],
                    extension: layer.extension,
                    resolutions: layer.serverResolutions || layer.resolutions
                });
            },
            "WMTS": function (layer) {
                var enc = this.encoders.layers.HTTPRequest.call(this, layer);
                enc = Ext.apply(enc, {
                    type: 'WMTS',
                    layer: layer.layer,
                    version: layer.version,
                    requestEncoding: layer.requestEncoding,
                    style: layer.style,
                    dimensions: layer.dimensions,
                    params: layer.params,
                    matrixSet: layer.matrixSet
                });
                if (layer.matrixIds) {
                    if (layer.requestEncoding == "KVP") {
                        enc.format = layer.format;
                    }
                    enc.matrixIds = []
                    Ext.each(layer.matrixIds, function (matrixId) {
                        enc.matrixIds.push({
                            identifier: matrixId.identifier,
                            matrixSize: [matrixId.matrixWidth,
                                matrixId.matrixHeight],
                            resolution: matrixId.scaleDenominator * 0.28E-3
                                / OpenLayers.METERS_PER_INCH
                                / OpenLayers.INCHES_PER_UNIT[layer.units],
                            tileSize: [matrixId.tileWidth, matrixId.tileHeight],
                            topLeftCorner: [matrixId.topLeftCorner.lon,
                                matrixId.topLeftCorner.lat]
                        });
                    })
                    return enc;
                }
                else {
                    return Ext.apply(enc, {
                        formatSuffix: layer.formatSuffix,
                        tileOrigin: [layer.tileOrigin.lon, layer.tileOrigin.lat],
                        tileSize: [layer.tileSize.w, layer.tileSize.h],
                        maxExtent: (layer.tileFullExtent != null) ? layer.tileFullExtent.toArray() : layer.maxExtent.toArray(),
                        zoomOffset: layer.zoomOffset,
                        resolutions: layer.serverResolutions || layer.resolutions
                    });
                }
            },
            "KaMapCache": function (layer) {
                var enc = this.encoders.layers.KaMap.call(this, layer);
                return Ext.apply(enc, {
                    type: 'KaMapCache',
                    // group param is mandatory when using KaMapCache
                    group: layer.params['g'],
                    metaTileWidth: layer.params['metaTileSize']['w'],
                    metaTileHeight: layer.params['metaTileSize']['h']
                });
            },
            "KaMap": function (layer) {
                var enc = this.encoders.layers.HTTPRequest.call(this, layer);
                return Ext.apply(enc, {
                    type: 'KaMap',
                    map: layer.params['map'],
                    extension: layer.params['i'],
                    // group param is optional when using KaMap
                    group: layer.params['g'] || "",
                    maxExtent: layer.maxExtent.toArray(),
                    tileSize: [layer.tileSize.w, layer.tileSize.h],
                    resolutions: layer.serverResolutions || layer.resolutions
                });
            },
            "HTTPRequest": function (layer) {
                var enc = this.encoders.layers.Layer.call(this, layer);
                return Ext.apply(enc, {
                    baseURL: this.getAbsoluteUrl(layer.url instanceof Array ?
                        layer.url[0] : layer.url),
                    opacity: (layer.opacity != null) ? layer.opacity : 1.0
                });
            },
            "Image": function (layer) {
                var enc = this.encoders.layers.Layer.call(this, layer);
                return Ext.apply(enc, {
                    type: 'Image',
                    baseURL: this.getAbsoluteUrl(layer.getURL(layer.extent)),
                    opacity: (layer.opacity != null) ? layer.opacity : 1.0,
                    extent: layer.extent.toArray(),
                    pixelSize: [layer.size.w, layer.size.h],
                    name: layer.name
                });
            },
            "Vector": function (layer) {
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
                    style = feature.style || layer.style ||
                        layer.styleMap.createSymbolizer(feature,
                            feature.renderIntent);

                    // don't send unvisible features
                    if (style.display == 'none') {
                        continue;
                    }

                    // MFP does not accept SLD form like '4 4' for dash stroke
                    if (style.strokeDashstyle) {
                        if (style.strokeDashstyle == '4 4') {
                            style.strokeDashstyle = 'dash';
                        } else if (style.strokeDashstyle == '2 4') {
                            // Somehow 'dot' is not understood by MFP...
                            style.strokeDashstyle = 'dot';
                        }
                    }

                    dictKey = styleFormat.write(style);
                    dictItem = styleDict[dictKey];
                    if (dictItem) {
                        //this style is already known
                        styleName = dictItem;
                    } else {
                        //new style
                        styleDict[dictKey] = styleName = nextId++;
                        if (style.externalGraphic) {
                            encStyles[styleName] = Ext.applyIf({
                                externalGraphic: this.getAbsoluteUrl(
                                    style.externalGraphic)}, style);
                        } else {
                            encStyles[styleName] = style;
                        }
                    }
                    var featureGeoJson = featureFormat.extract.feature.call(
                        featureFormat, feature);

                    featureGeoJson.properties = OpenLayers.Util.extend({
                        _gx_style: styleName
                    }, featureGeoJson.properties);

                    encFeatures.push(featureGeoJson);
                }
                var enc = this.encoders.layers.Layer.call(this, layer);
                return Ext.apply(enc, {
                    type: 'Vector',
                    styles: encStyles,
                    styleProperty: '_gx_style',
                    geoJson: {
                        type: "FeatureCollection",
                        features: encFeatures
                    },
                    name: layer.name,
                    opacity: (layer.opacity != null) ? layer.opacity : 1.0
                });
            },
            "Markers": function (layer) {
                var features = [];
                for (var i = 0, len = layer.markers.length; i < len; i++) {
                    var marker = layer.markers[i];
                    var geometry = new OpenLayers.Geometry.Point(marker.lonlat.lon, marker.lonlat.lat);
                    var style = {externalGraphic: marker.icon.url,
                        graphicWidth: marker.icon.size.w, graphicHeight: marker.icon.size.h,
                        graphicXOffset: marker.icon.offset.x, graphicYOffset: marker.icon.offset.y};
                    var feature = new OpenLayers.Feature.Vector(geometry, {}, style);
                    features.push(feature);
                }
                var vector = new OpenLayers.Layer.Vector(layer.name);
                vector.addFeatures(features);
                var output = this.encoders.layers.Vector.call(this, vector);
                vector.destroy();
                return output;
            }
        },
        "legends": {
            "gx_wmslegend": function (legend, scale) {
                var enc = this.encoders.legends.base.call(this, legend);
                var icons = [];
                for (var i = 1, len = legend.items.getCount(); i < len; ++i) {
                    var url = legend.items.get(i).url;
                    if (legend.useScaleParameter === true &&
                        url.toLowerCase().indexOf(
                            'request=getlegendgraphic') != -1) {
                        var split = url.split("?");
                        var params = Ext.urlDecode(split[1]);
                        params['SCALE'] = scale;
                        url = split[0] + "?" + Ext.urlEncode(params);
                    }
                    icons.push(this.getAbsoluteUrl(url));
                }
                enc[0].classes[0] = {
                    name: "",
                    icons: icons
                };
                return enc;
            },
            "gx_wmtslegend": function (legend, scale) {
                return this.encoders.legends.gx_urllegend.call(this, legend);
            },
            "gx_urllegend": function (legend) {
                var enc = this.encoders.legends.base.call(this, legend);
                enc[0].classes.push({
                    name: "",
                    icon: this.getAbsoluteUrl(legend.items.get(1).url)
                });
                return enc;
            },
            "base": function (legend) {
                return [
                    {
                        name: legend.getLabel(),
                        classes: []
                    }
                ];
            }
        }
    }


});

/**
 * Complete version of PrintProviderField.js
 * For selecting/printing other Output Formats except PDF.
 * See https://code.google.com/p/geoext-viewer/issues/detail?id=189
 * and https://github.com/geoext/geoext/issues/91
 */

/**
 * Copyright (c) 2008-2012 The Open Source Geospatial Foundation
 *
 * Published under the BSD license.
 * See http://svn.geoext.org/core/trunk/geoext/license.txt for the full text
 * of the license.
 */
Ext.namespace("GeoExt.plugins");

/** api: (define)
 *  module = GeoExt.plugins
 *  class = PrintProviderField
 *  base_link = `Ext.util.Observable <http://dev.sencha.com/deploy/dev/docs/?class=Ext.util.Observable>`_
 */

/** api: example
 *  A form with combo boxes for layout and resolution, and a text field for a
 *  map title. The latter is a custom parameter to the print module, which is
 *  a default for all print pages. For setting custom parameters on the page
 *  level, use :class:`GeoExt.plugins.PrintPageField`):
 *
 *  .. code-block:: javascript
 *
 *      var printProvider = new GeoExt.data.PrintProvider({
 *          capabilities: printCapabilities
 *      });
 *      new Ext.form.FormPanel({
 *          renderTo: "form",
 *          width: 200,
 *          height: 300,
 *          items: [{
 *              xtype: "combo",
 *              displayField: "name",
 *              store: printProvider.layouts, // printProvider.layout
 *              fieldLabel: "Layout",
 *              typeAhead: true,
 *              mode: "local",
 *              forceSelection: true,
 *              triggerAction: "all",
 *              selectOnFocus: true,
 *              plugins: new GeoExt.plugins.PrintProviderField({
 *                  printProvider: printProvider
 *              })
 *          }, {
 *              xtype: "combo",
 *              displayField: "name",
 *              store: printProvider.dpis, // printProvider.dpi
 *              fieldLabel: "Resolution",
 *              typeAhead: true,
 *              mode: "local",
 *              forceSelection: true,
 *              triggerAction: "all",
 *              selectOnFocus: true,
 *              plugins: new GeoExt.plugins.PrintProviderField({
 *                  printProvider: printProvider
 *              })
*          }, {
 *              xtype: "combo",
 *              displayField: "name",
 *              store: printProvider.outputFormats,
 *              fieldLabel: "Output",
 *              typeAhead: true,
 *              mode: "local",
 *              forceSelection: true,
 *              triggerAction: "all",
 *              selectOnFocus: true,
 *              plugins: new GeoExt.plugins.PrintProviderField({
 *                  printProvider: printProvider
 *              })
 *          }, {
 *              xtype: "textfield",
 *              name: "mapTitle", // printProvider.customParams.mapTitle
 *              fieldLabel: "Map Title",
 *              plugins: new GeoExt.plugins.PrintProviderField({
 *                  printProvider: printProvider
 *              })
 *          }]
 *      }):
 */

/** api: constructor
 *  .. class:: PrintProviderField
 *
 *  A plugin for ``Ext.form.Field`` components which provides synchronization
 *  with a :class:`GeoExt.data.PrintProvider`.
 */
GeoExt.plugins.PrintProviderField = Ext.extend(Ext.util.Observable, {

    /** api: config[printProvider]
     *  ``GeoExt.data.PrintProvider`` The print provider to use with this
     *  plugin's field. Not required if set on the owner container of the
     *  field.
     */

    /** private: property[target]
     *  ``Ext.form.Field`` This plugin's target field.
     */
    target: null,

    /** private: method[constructor]
     */
    constructor: function (config) {
        this.initialConfig = config;
        Ext.apply(this, config);

        GeoExt.plugins.PrintProviderField.superclass.constructor.apply(this, arguments);
    },

    /** private: method[init]
     *  :param target: ``Ext.form.Field`` The component that this plugin
     *      extends.
     */
    init: function (target) {
        this.target = target;
        var onCfg = {
            scope: this,
            "render": this.onRender,
            "beforedestroy": this.onBeforeDestroy
        };
        onCfg[target instanceof Ext.form.ComboBox ? "select" : "valid"] =
            this.onFieldChange;
        target.on(onCfg);
    },

    /** private: method[onRender]
     *  :param field: ``Ext.Form.Field``
     *
     *  Handler for the target field's "render" event.
     */
    onRender: function (field) {
        var printProvider = this.printProvider || field.ownerCt.printProvider;
        if (field.store === printProvider.layouts) {
            field.setValue(printProvider.layout.get(field.displayField));
            printProvider.on({
                "layoutchange": this.onProviderChange,
                scope: this
            });
        } else if (field.store === printProvider.dpis) {
            field.setValue(printProvider.dpi.get(field.displayField));
            printProvider.on({
                "dpichange": this.onProviderChange,
                scope: this
            });
        } else if (field.store === printProvider.outputFormats) {
            if (printProvider.outputFormat) {
                field.setValue(printProvider.outputFormat.get(field.displayField));
                printProvider.on({
                    "outputformatchange": this.onProviderChange,
                    scope: this
                });
            } else {
                // In rare cases no Output Formats are available: use default
                field.setValue(printProvider.defaultOutputFormatName);
                field.disable();
            }
        } else if (field.initialConfig.value === undefined) {
            field.setValue(printProvider.customParams[field.name]);
        }
    },

    /** private: method[onFieldChange]
     *  :param field: ``Ext.form.Field``
     *  :param record: ``Ext.data.Record`` Optional.
     *
     *  Handler for the target field's "valid" or "select" event.
     */
    onFieldChange: function (field, record) {
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
    },

    /** private: method[onProviderChange]
     *  :param printProvider: :class:`GeoExt.data.PrintProvider`
     *  :param rec: ``Ext.data.Record``
     *
     *  Handler for the printProvider's dpichange and layoutchange event
     */
    onProviderChange: function (printProvider, rec) {
        if (!this._updating) {
            this.target.setValue(rec.get(this.target.displayField));
        }
    },

    /** private: method[onBeforeDestroy]
     */
    onBeforeDestroy: function () {
        var target = this.target;
        target.un("beforedestroy", this.onBeforeDestroy, this);
        target.un("render", this.onRender, this);
        target.un("select", this.onFieldChange, this);
        target.un("valid", this.onFieldChange, this);
        var printProvider = this.printProvider || target.ownerCt.printProvider;
        printProvider.un("layoutchange", this.onProviderChange, this);
        printProvider.un("dpichange", this.onProviderChange, this);
        printProvider.un("outputformatchange", this.onProviderChange, this);
    }

});

Ext.override(GeoExt.VectorLegend, {

    /** private: method[styleChanged]
     *  Listener for map stylechanged event: update the legend.
     */
    styleChanged: function () {
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
    },

    /** api: method[update]
     *  Update rule titles and symbolizers.
     */
    update: function () {
        // Add a listener for the 'stylechanged' event. We need to do this here
        // as we cannot override initComponent() where this really should happen.
        if (this.layer && !this.layer.events.listeners['stylechanged']) {
            this.layer.events.on({
                stylechanged: this.styleChanged,
                scope: this
            });
        }

        // The remainder is as the original update() function
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
            // make sure that the selected rule is still selected after update
            if (this.selectedRule) {
                this.getRuleEntry(this.selectedRule).body.addClass("x-grid3-row-selected");
            }
        }
    }
});