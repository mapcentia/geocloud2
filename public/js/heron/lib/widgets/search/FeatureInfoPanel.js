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
Ext.namespace("Heron.widgets.search");
Ext.namespace("Heron.utils");

/** api: (define)
 *  module = Heron.widgets.search
 *  class = FeatureInfoPanel
 *  base_link = `Ext.Panel <http://dev.sencha.com/deploy/ext-3.3.1/docs/?class=Ext.Panel>`_
 */

/** api: example
 *  Sample code showing how to configure a Heron FeatureInfoPanel.
 *  All regular ExtJS `Ext.Panel <http://dev.sencha.com/deploy/ext-3.3.1/docs/?class=Ext.Panel>`_
 *  config params also apply.
 *  The ``infoFormat`` config parameter is the default ``INFO_FORMAT`` to be used for WMS GetFeatureInfo (GFI).
 *  This value can be overruled by an optional per-Layer ``infoFormat`` WMS Layer config parameter.
 *  GetFeatureInfo-response data may be displayed as a Grid, a Tree or formatted XML. The ``displayPanels``
 *  config option can be used to trigger a menu with display options. Note also the use of "GridCellRenderers".
 *  These allow you to render specific formatting of cell content within the feature grid. For example
 *  URL substitution to render external links in a new tab or browser window. You can even supply your own formatting
 *  function. This function is according to the ExtJS ColumnModel renderers (see e.g. http://snipplr.com/view/40942).
 *  More finegrained grid-control can be obtained with ``gridColumns``. Here the standard ExtJS ``columns``
 *  layout can be specified per feature type. Via ``gridColumns`` should become the preferred method.
 *
 *  .. code-block:: javascript
 *
 *			 xtype: 'hr_featureinfopanel',
 *			 id: 'hr-feature-info',
 *			 region: "south",
 *			 border: true,
 *			 collapsible: true,
 *			 collapsed: true,
 *			 height: 205,
 *			 split: true,
 *			 infoFormat: 'application/vnd.ogc.gml',
 *			 displayPanels: ['Grid', 'XML'],
 *			 exportFormats: ['CSV', 'XLS'],
 *			 maxFeatures: 10,
 *			 columnCapitalize: false,
 *			 discardStylesForDups: true,
 *			 gridCellRenderers: [
 *						{
 *							featureType: 'cities',
 *							attrName: 'City',
 *							renderer: {
 *								fn : Heron.widgets.GridCellRenderer.directLink,
 *								options : {
 *									url: 'http://en.wikipedia.org/wiki/{City}',
 *									target: '_new'
 *								}
 *							}
 *						},
 *						{
 *							featureType: 'cities',
 *							attrName : 'Country',
 *							renderer :  {
 *								fn : Heron.widgets.GridCellRenderer.browserPopupLink,
 *								options : {
 *									url: 'http://en.wikipedia.org/wiki/{Country}',
 *									winName: 'demoWin',
 *									width: 400,
 *									height: 800,
 *									scrollbars: 'yes'
 *								}
 *							}
 *						},
 *						{   // Example for custom HTML, could use also with e.g. links
 *							featureType: 'cities',
 *							attrName : 'longitude',
 *							renderer :  {
 *								fn : Heron.widgets.GridCellRenderer.valueSubstitutor,
 *								options : {
 *									template: '<i>ll={latitude},{longitude}{empty}</i>'
 *								}
 *							}
 *						},
 *						{
 *							// Example: supply your own function, parms as in ExtJS ColumnModel
 *							featureType: 'cities',
 *							attrName : 'population',
 *							renderer :  {
 *								fn : function(value, metaData, record, rowIndex, colIndex, store) {
 *									// Custom formatting, may also use this.options if needed
 *									return '<b>' + value + ' inh.</b>';
 *								},
 *								options : {
 *
 *								}
 *							}
 *						}
 *					],
 *
 * 	      gridColumns: [
 * 	      {
 * 	      featureType: 'states',
 * 	        columns: [
 * 	         {
 *                  header: "State Name",
 *                  width: 120,
 *                  dataIndex: "STATE_NAME"
 *           },
 *           {
 *                  header: "Inhabitants",
 *                  width: 120,
 *                  dataIndex: "PERSONS"
 *           },
 *           {
 *                  header: "More Info",
 *                  width: 120,
 *                  renderer: function (value, metaData, record, rowIndex, colIndex, store) {
 *                      var template = '<a target="_new" href="http://en.wikipedia.org/wiki/{STATE_NAME}">Wikipedia Info</a>';
 *                      var options = {attrNames: ['STATE_NAME']};
 *                      return Heron.widgets.GridCellRenderer.substituteAttrValues(template, options, record);
 *                  }
 *            }
 *
 *           ]
 *         }
 *      ]
 *
 *
 *
 *    }
 *
 */

/** api: constructor
 *  .. class:: FeatureInfoPanel(config)
 *
 *  Panel to hold Feature Information for one or more WMS (GetFeatureInfo) and/or Vector (e.g. WFS) layers.
 *
 */
Heron.widgets.search.FeatureInfoPanel = Ext.extend(Ext.Panel, {
    /** api: title
     *  ``String``
     *  Default title of the panel. If not set
     *  the value ``Feature Info`` will be used.
     */
    title: __('Feature Info'),

    /** api: config[maxFeatures]
     *  ``int``
     *  Default GFI MAX_FEATURES parameter. Will be ``5`` if not set.
     */
    maxFeatures: 5,

    /** api: config[displayPanels]
     *  ``String Array``
     *
     * String array  of types of Panels to display GFI info in, default value is ['Table'], a grid table.
     * Other value is 'Detail', a propertyPanel showing records one by one in a "Vertical" view.
     * If multiple display values are given buttons in the toolbar will be shown to switch display types.
     * First value is the panel to be opened at the first time info is requested
     * Note: The old implementation with 'Tree' and 'XML' was deprecated from v0.75
     */
    displayPanels: ['Table'],

    /** api: config[exportFormats]
     *  ``String Array``
     *
     * Array of document formats to be used when exporting the content of a GFI response. This requires the server-side CGI script
     * ``heron.cgi`` to be installed. Exporting results in a download of a document with the contents of the (Grid) Panel.
     * For example when 'XLS' is configured, exporting will result in the Excel (or compatible) program to be
     * started with the GFI data in an Excel worksheet.
     * Option values are 'CSV' and/or 'XLS', , 'GMLv2', 'GeoJSON', 'WellKnownText' default is, ``null``, meaning no export (results in no export menu).
     * The value ['CSV', 'XLS'] configures a menu to choose from a ``.csv`` or ``.xls`` export document format.
     */
    exportFormats: ['CSV', 'XLS', 'GMLv2', 'GeoJSON', 'WellKnownText'],

    /** api: config[infoFormat]
     *  ``String``
     *  Default GFI INFO_FORMAT parameter, may be overruled per Layer object infoFormat WMS param. If not set
     *  the value ``application/vnd.ogc.gml`` will be used.
     */
    infoFormat: 'application/vnd.ogc.gml',

    /** api: config[hover]
     *  ``Boolean``
     *  Show features on hovering.
     */
    hover: false,

    /** api: config[drillDown]
     *  ``Boolean``
     *  Show features from all visible layers that are queryable.
     */
    drillDown: true,

    /** api: config[layer]
     *  ``string``
     *  The layer to get feature information from. Parameter value will be ``""`` if not set.
     *  If not set, all visible layers of the map will be searched. In case the drillDown
     *  parameter is ``false``, the topmost visible layer will searched.
     */
    layer: "",

    /** api: config[discardStylesForDups]
     *  ``Boolean``
     *  In case the same Layer is present multiple times, request only once without any STYLES= parameter.
     *  Default is ``false``.
     */
    discardStylesForDups: false,

    /** api: config[showTopToolbar]
     *  ``Boolean``
     *  Show the toolbar with object count, clear and export buttons.
     *  Default is ``false``.
     */
    showTopToolbar: true,

    /** api: config[showGeometries]
     *  ``Boolean``
     *  Should the feature geometries be shown? Default ``true``.
     */
    showGeometries: true,

    /** api: config[featureSelection]
     *  ``Boolean``
     *  Should the feature geometries that are shown be selectable in grid and map? Default ``true``.
     */
    featureSelection: true,

    /** api: config[columnCapitalize]
     *  ``Boolean``
     *  Should the column names be capitalized when autoconfig is true?
     */
    columnCapitalize: true,

    /** api: config[gridCellRenderers]
     *  ``Array``
     *  Have all columns displayed but only these specifically formatted per feature type (see example).
     */
    gridCellRenderers: null,

    /** api: config[gridColumns]
     *  ``Array``
     *  Have only specific columns displayed and formatted per feature type (see example).
     */
    gridColumns: null,

    /** api: config[autoConfigMaxSniff]
     *  ``Integer``
     *  Maximum number of features to 'sniff' for autoconfigured grid columns (as null columns are often not sent by server).
     */
    autoConfigMaxSniff: 40,

    /** api: config[hideColumns]
     *  ``Array``
     *  An array of column names from WFS and WMS GetFeatureInfo results that should be removed and not shown to the user.
     */
    hideColumns: [],
	
    /** api: config[columnFixedWidth]
     *  ``Integer``
     *  The width of a column in a grid response
     */
    columnFixedWidth: 100,	
	
    /** api: config[autoMaxWidth]
     *  ``Integer``
     *  The maximum width of a auto adjusted column grid response. Setting to 0 will disable auto column width detection
     */
    autoMaxWidth: 300,

    /** api: config[autoMinWidth]
     *  ``Integer``
     *   The minimum width of a auto adjusted column. Requires autoMaxWidth to be > 1 to function.
     */
    autoMinWidth: 45,

    /** Internal vars */
    pop: null,
    map: null,
    displayPanel: null,
    lastEvt: null,
    olControl: null,
    tb: null,

    initComponent: function () {
        // For closures ("this" is not valid in callbacks)
        var self = this;

        Ext.apply(this, {
            layout: "fit"
        });

        this.display = this.displayFeatureInfo;

        Heron.widgets.search.FeatureInfoPanel.superclass.initComponent.call(this);
        this.map = Heron.App.getMap();

        /***
         * Add a WMSGetFeatureInfo control to the map if it is not yet present
         * The control could already have been set. If not try and find
         * the control in the map.
         */
        if (!this.olControl) {
            var controls = this.map.getControlsByClass("OpenLayers.Control.WMSGetFeatureInfo");
            if (controls && controls.length > 0) {
                for (var index = 0; index < controls.length; index++) {
                    //Control should not be the one used for tooltips.
                    if (controls[index].id !== "hr-feature-info-hover") {
                        this.olControl = controls[index];
                        // Overrule with our own info format and max features
                        this.olControl.infoFormat = this.infoFormat;
                        this.olControl.maxFeatures = this.maxFeatures;
                        this.olControl.hover = this.hover;
                        this.olControl.drillDown = this.drillDown;
                        break;
                    }
                }
            }

            // No GFI control present: create new and add to Map
            if (!this.olControl) {
                this.olControl = new OpenLayers.Control.WMSGetFeatureInfo({
                    maxFeatures: this.maxFeatures,
                    queryVisible: true,
                    infoFormat: this.infoFormat,
                    hover: this.hover,
                    drillDown: this.drillDown
                });

                this.map.addControl(this.olControl);
            }
        }
        // Register WMSGetFeatureInfo control event handlers
        this.olControl.events.register("getfeatureinfo", this, this.handleGetFeatureInfo);
        this.olControl.events.register("beforegetfeatureinfo", this, this.handleBeforeGetFeatureInfo);
        this.olControl.events.register("nogetfeatureinfo", this, this.handleNoGetFeatureInfo);

        this.addListener("afterrender", this.onPanelRendered, this);
        this.addListener("render", this.onPanelRender, this);
        this.addListener("show", this.onPanelShow, this);
        this.addListener("hide", this.onPanelHide, this);
    },

    /** api: method[onPanelRender]
     *  Called when Panel has been rendered.
     */
    onPanelRender: function () {
        this.mask = new Ext.LoadMask(this.body, {msg: __('Loading...')});
    },

    /** api: method[onPanelRendered]
     *  Called when Panel has been rendered.
     */
    onPanelRendered: function () {
        if (this.ownerCt) {
            this.ownerCt.addListener("hide", this.onPanelHide, this);
            this.ownerCt.addListener("show", this.onPanelShow, this);
        }
    },

    /** private: method[onPanelShow]
     * Called after our panel is shown.
     */
    onPanelShow: function () {
        if (this.tabPanel) {
            this.tabPanel.items.each(function (item) {
                return item.showLayer ? item.showLayer() : true;
            }, this);
        }
    },

    /** private: method[onPanelHide]
     * Called  before our panel is hidden.
     */
    onPanelHide: function () {
        if (this.tabPanel) {
            this.tabPanel.items.each(function (item) {
                return item.hideLayer ? item.hideLayer() : true;
            }, this);
        }
    },

    initPanel: function () {
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
    },

    handleBeforeGetFeatureInfo: function (evt) {
        //If the event was not triggered from this.olControl, do nothing
        if (evt.object !== this.olControl) {
            return;
        }

        this.olControl.layers = [];

        // Needed to force accessing multiple WMS-es when multiple layers are visible
        this.olControl.url = null;
        this.olControl.drillDown = this.drillDown;

        // Select WMS layers that are visible and enabled (via featureInfoFormat or Layer info_format (capitalized by OL) prop)
        var layer;
        //If a layer is specified, try and find the layer in the map.
        if (this.layer) {
            var layers = this.map.getLayersByName(this.layer);
            if (layers) {
                //Add the first layer found with name layer
                layer = layers[0];
                this.olControl.layers.push(layer);
            }
        }

        //If no layer was specified or the specified layer was not found,
        //assign the visible WMS-layers to the olControl.
        if (this.olControl.layers.length == 0) {
            this.layerDups = {};
            for (var index = 0; index < this.map.layers.length; index++) {
                layer = this.map.layers[index];

                // Skip non-WMS layers
                if (!layer instanceof OpenLayers.Layer.WMS || !layer.params) {
                    continue;
                }

                // Enable layers for GFI that have a GFI mime param specified
                if (layer.visibility && (layer.featureInfoFormat || layer.params.INFO_FORMAT)) {

                    // Backward compatible with old configs that have only featureInfoFormat
                    // set to a mime type like "text/xml". layer.params.INFO_FORMAT determines the mime
                    // requested from WMS server.
                    if (!layer.params.INFO_FORMAT && layer.featureInfoFormat) {
                        layer.params.INFO_FORMAT = layer.featureInfoFormat;
                    }

                    if (layer.params.CQL_FILTER) {
                        this.olControl.requestPerLayer = true;
                        layer.params.vendorParams = {CQL_FILTER: layer.params.CQL_FILTER};
                    }

                    if (this.layerDups[layer.params.LAYERS] && !this.olControl.requestPerLayer) {
                        // https://code.google.com/p/geoext-viewer/issues/detail?id=215
                        // what to do when we have duplicate layers, at least we may replace if
                        // one of them is without any STYLES or FILTER or CQL.
                        if (this.discardStylesForDups) {
                            // Make the STYLES empty for the request only (restore after the request)
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
            // Show loading mask
            this.mask.show();
        }

        this.fireEvent('beforefeatureinfo', evt);

        // Try to fetch features from WFS/Vector layers
        this.handleVectorFeatureInfo(evt.object.handler.evt);

        // No layers with GFI and no features from Vector layers available: display message
        if (this.olControl.layers.length == 0 && this.features == null) {
            this.handleNoGetFeatureInfo();
        }
    },

    handleGetFeatureInfo: function (evt) {
        // Always restore possible temporary vendorParams
        var layers = this.olControl.layers;
        if (layers) {
            for (var i = 0, len = layers.length; i < len; i++) {
                layers[i].params.vendorParams = null;
            }
        }

        // Always restore possible Layer duplicate STYLES
        if (this.discardStylesForDups) {
            // https://code.google.com/p/geoext-viewer/issues/detail?id=215
            for (var layerName in this.layerDups) {
                var layerDup = this.layerDups[layerName];
                if (layerDup.savedStyles) {
                    layerDup.params.STYLES = layerDup.savedStyles;
                    layerDup.savedStyles = null;
                }
            }
        }

        // If the event was not triggered from this.olControl, and not a Vector layer, do nothing
        if (evt && evt.object !== this.olControl) {
            return;
        }

        // Hide the loading mask
        if (this.mask) {
            this.mask.hide();
        }

        // Save result e.g. when changing views
        if (evt) {
            this.lastEvt = evt;
        }

        if (!this.lastEvt) {
            return;
        }

        this.displayFeatures(this.lastEvt);
    },

    /** Determine if Vector features are touched. */
    handleVectorFeatureInfo: function (evt) {
        this.vectorFeaturesFound = false;

        // Nasty hack but IE refuses to play nice and provide screen X,Y as all others!!

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
    },


    handleNoGetFeatureInfo: function () {
        // When also no visible Vector layers give warning
        if (!this.visibleVectorLayers) {
            Ext.Msg.alert(__('Warning'), __('Feature Info unavailable (you may need to make some layers visible)'));
        }
    },

    /**
     * Method: getFeaturesByXY
     * Get all features at the given screen location.
     *
     * Parameters:
     * x - {Number} Screen x coordinate.
     * y - {Number} Screen y coordinate.
     *
     * Returns:
     * {Array(<OpenLayers.Feature.Vector>)} List of features at the given point.
     *
     * From:
     * http://trac.osgeo.org/openlayers/browser/sandbox/tschaub/select/lib/OpenLayers/FeatureAgent.js
     */
    getFeaturesByXY: function(x, y) {
        this.visibleVectorLayers = false;
        var features = [], targets = [], layers = [];
        var layer, target, feature, i, len;
        // go through all layers looking for targets
        for (i=this.map.layers.length-1; i>=0; --i) {
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
                            // sketch, all bets off
                            target = false;
                        }
                    }
                }
                layers.push(layer);
                layer.div.style.display = "none";
            }
        }
        // restore feature visibility
        for (i=0, len=targets.length; i<len; ++i) {
            targets[i].style.display = "";
        }
        // restore layer visibility
        for (i=layers.length-1; i>=0; --i) {
            layers[i].div.style.display = "block";
        }
        return features;
    },

    /***
     * Try to get feature type name from a feature, this is often WMS-specific and a bit of black art.
     * TODO: determine for ESRI WMS.
     */
    getFeatureType: function (feature) {

        // If GFI returned GML, OL has may have parsed out the featureType
        // http://code.google.com/p/geoext-viewer/issues/detail?id=92
        if (feature.gml && feature.gml.featureType) {
            return feature.gml.featureType;
        }

        // GeoServer-specific
        if (feature.fid && feature.fid.indexOf('undefined') < 0) {
            // TODO: this is nasty and GeoServer specific ?
            // We may check the FT e.g. from the GML tag(s) available in the evt
            // More specific, we need to. Because now with multiple layers, all are assigned to
            // unknown and you get strange column results when the featuretypes are mixed...
            var featureType = /[^\.]*/.exec(feature.fid);

            return (featureType[0] != "null") ? featureType[0] : null;
        }

        // Mapserver-specific
        if (feature.type) {
            return feature.type;
        }

        // ESRI-specific
        if (feature.attributes['_LAYERID_']) {
            // Try ESRI WMS GFI returns layername/featureType as attribute '_LAYERID_'  !
            // See http://webhelp.esri.com/arcims/9.3/general/mergedprojects/wms_connect/wms_connector/get_featureinfo.htm
            // See e.g. http://svn.flamingo-mc.org/trac/changeset/648/flamingo/trunk/fmc/OGWMSConnector.as
            return feature.attributes['_LAYERID_'];
        }

        // TNO/DINO-specific
        if (feature.attributes['DINO_DBA.MAP_SDE_GWS_WELL_W_HEADS_VW.DINO_NR']) {
            // TODO find better way to determine and fix for DINO services
            //			var nodes = featureNode.childNodes;
            //			 var _featureType = "";
            //			 for (j = 0,jlen = nodes.length; j < jlen; ++j) {
            //				 var node = nodes[j];
            //				 if (node.nodeType !== 3) {
            //					 //Dirty fix for dino name needs to be stripped as it consists of 3 parts
            //					 var dino_name = node.getAttribute("name");
            //					 var _feat = dino_name.split(".");
            //					 if(_feat[0] === "DINO_DBA"){
            //						 attributes[_feat[2]] = node.getAttribute("value");
            //						 _featureType = _feat[1];
            //					 } else {
            //						 attributes[node.getAttribute("name")] = node.getAttribute("value");
            //					 }
            //				 }
            //			 }
            //		 }
            //		 _feature = new OpenLayers.Feature.Vector(geom, attributes, null);
            //
            //		 if(_featureType !== ""){
            //			 // Dirty fix for dino to maintain reference to layer
            //			 _feature.gml = {};
            //			 _feature.gml.featureType = _featureType;
            //			 _feature.fid = _featureType + "." + len;
            //			 _feature.layer = _featureType;
            //		 }
            //	var _feat = dino_name.split(".");
            //					 if(_feat[0] === "DINO_DBA"){
            //						 attributes[_feat[2]] = node.getAttribute("value");
            //						 _featureType = _feat[1];
            //					 } else {
            //						 attributes[node.getAttribute("name")] = node.getAttribute("value");
            //					 }
            // rec.attributes[0]
            return 'TNO_DINO_WELLS';
        }

        // TNO/DINO-specific  (see above)
        if (feature.attributes['DINO_DBA.MAP_SDE_BRH_BOREHOLE_RD_VW.DINO_NR']) {
            return 'TNO_DINO_BOREHOLES';
        }

        // Nothing found :-(
        return __('Unknown');
    },

    /***
     * Try to get feature title (user friendly name) for a feature type name.
     */
    getFeatureTitle: function (feature, featureType) {
        // In some cases when we are lucky we have the Layer Name.
        if (feature.layer) {
            return feature.layer.name;
        }

        // Fall back to featureType if we can't find the name
        // In some cases when we are lucky we have the Layer Name.
        var featureTitle = feature.layer? feature.layer.name : featureType;

        // WMS/WFS GetFeature results don't return the Human Friendly name.
        // So we get it from the layer declaration here and use this for the tab titles.
        // Resolves Enhancement #164 - JM, 2013.01.30
        var layers = this.map.layers;
        for (var l = 0; l < layers.length; l++) {
            var nextLayer = layers[l];

            // Skip non-WMS layers and non-visible layers
            if (!nextLayer.params || !nextLayer.visibility) {
                continue;
            }

            // Ensure cases match by making all lowerCase. May not otherwise.
            if (featureType.toLowerCase() == /([^:]*$)/.exec(nextLayer.params.LAYERS)[0].toLowerCase()) {
                featureTitle = nextLayer.name;
                break;
            }
        }

        return featureTitle;
    },

    displayFeatures: function (evt) {
        if (this.olControl.requestPerLayer) {
            // this.initPanel();
        }
        // Were any features returned ?
        if (evt.features && evt.features.length > 0) {
            if (!this.vectorFeaturesFound && this.displayPanel) {
                this.remove(this.displayPanel);
                this.displayPanel = null;
                this.displayOn = false;
            }
            // Delegate to current display panel (Grid, Tree, XML)
            this.displayPanel = this.display(evt);
        } else if (!this.vectorFeaturesFound) {
            // No features found in WMS and Vector Layers: show message
            this.displayPanel = this.displayInfo(__('No features found'));
        }

        if (this.displayPanel && !this.displayOn) {
            this.add(this.displayPanel);
            this.displayPanel.doLayout();
        }

        if (this.getLayout() instanceof Object && !this.displayOn) {
            this.getLayout().runLayout();
        }
        this.displayOn = true;
        this.fireEvent('featureinfo', evt);
    },

    /***
     * Callback function for handling the result of an OpenLayers GetFeatureInfo request
     */
    displayFeatureInfo: function (evt) {
        var featureSets = {}, featureSet, featureType, featureTitle, featureSetKey;

        // Extract feature set per feature type from total array of features
        for (var index = 0; index < evt.features.length; index++) {
            var feature = evt.features[index];

            // Get feature type name
            featureType = this.getFeatureType(feature);
            featureTitle = this.getFeatureTitle(feature, featureType);
            featureSetKey = featureType + featureTitle;

            if (!featureSets[featureSetKey]) {
                featureSet = {
                    featureType: featureType,
                    title: featureTitle,
                    features: []
                };

                featureSets[featureSetKey] = featureSet;
            }

            /***
             * Go through attributes and modify where needed:
             * - hyperlinks clickable
             * - illegal field names (with dots)
             * - custom hyperlinks
             */
            for (var attrName in feature.attributes) {

                // Check for hyperlinks
                // Simple fix for issue 23
                // http://code.google.com/p/geoext-viewer/issues/detail?id=23
                var attrValue = feature.attributes[attrName];
                if (attrValue && typeof attrValue == 'string' && attrValue.indexOf("http://") >= 0) {
                    // Display value as HTML hyperlink
                    feature.attributes[attrName] = '<a href="' + attrValue + '" target="_new">' + attrValue + '</a>';
                }

                // GetFeatureInfo response may contain dots in the fieldnames, these are not allowed in ExtJS store fieldnames.
                // Use a regex to replace the dots /w underscores.
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

        // Remove any existing panel
        if (this.tabPanel != null && !this.displayOn) {
            this.remove(this.tabPanel);
            this.tabPanel = null;
        }

        // Run through feature sets, creating a feature grid for each
        // and adding to the tab panel
        for (featureSetKey in featureSets) {
            featureSet = featureSets[featureSetKey];
            if (featureSet.features.length == 0) {
                // Do not display empty feature set
                continue;
            }

            var autoConfig = true;
            var columns = null;
            if (this.gridColumns)  {
                for (var c = 0; c < this.gridColumns.length; c++) {
                    if (this.gridColumns[c].featureType == featureSet.featureType) {
                        autoConfig = false;
                        columns = this.gridColumns[c].columns;
                        break;
                    }
                }
            }
            var panel = new Heron.widgets.search.FeaturePanel({
                title: featureSet.title,
                featureType: featureSet.featureType,
                featureSetKey: featureSetKey,
                header: false,
                features: featureSet.features,
                autoConfig: autoConfig,
                autoConfigMaxSniff: this.autoConfigMaxSniff,
				hideColumns: this.hideColumns,
				columnFixedWidth: this.columnFixedWidth,
				autoMaxWidth: this.autoMaxWidth,
				autoMinWidth: this.autoMinWidth,
                columnCapitalize: this.columnCapitalize,
                showGeometries: this.showGeometries,
                featureSelection: this.featureSelection,
                gridCellRenderers: this.gridCellRenderers,
                columns: columns,
                showTopToolbar: this.showTopToolbar,
                exportFormats: this.exportFormats,
                displayPanels: this.displayPanels,
                hropts: {
                    zoomOnRowDoubleClick: true,
                    zoomOnFeatureSelect: false,
                    zoomLevelPointSelect: 8
                }});


            // Create tab panel for the first FT and add additional tabs for each FT
            if (!this.tabPanel) {
                this.tabPanel = new Ext.TabPanel({
                    border: false,
                    autoDestroy: true,
                    enableTabScroll: true,
                    //height: this.getHeight(),
                    items: [panel],
                    activeTab: 0
                });
            } else {
                // Add to existing tab panel
                this.tabPanel.add(panel);
                this.tabPanel.setActiveTab(0);
            }

            panel.loadFeatures(featureSet.features, featureSet.featureType);
            //panel.setAutoScroll (true);
        }
        return this.tabPanel;
    },

    /***
     * Callback function for handling the result of an OpenLayers GetFeatureInfo request (display as Tree)
     */
    displayTree: function (evt) {
        var panel = new Heron.widgets.XMLTreePanel();

        panel.xmlTreeFromText(panel, evt.text);

        return panel;
    },

    /***
     * Callback function for handling the result of an OpenLayers GetFeatureInfo request (display as XML)
     */
    displayXML: function (evt) {
        var opts = {
            html: '<div class="hr-html-panel-body"><pre>' + Heron.Utils.formatXml(evt.text, true) + '</pre></div>',
            preventBodyReset: true,
            autoScroll: true
        };

        return new Ext.Panel(opts);
    },

    /***
     * Display info panel.
     */
    displayInfo: function (infoStr) {
        var opts = {
            html: '<div class="hr-html-panel-body"><pre>' + infoStr + '</pre></div>',
            preventBodyReset: true,
            autoScroll: true
        };

        return new Ext.Panel(opts);
    }
});

/** api: xtype = hr_featureinfopanel */
Ext.reg('hr_featureinfopanel', Heron.widgets.search.FeatureInfoPanel);
