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

Ext.namespace("Heron.widgets");

/** api: (define)
 *  module = Heron.widgets
 *  class = MapPanel
 *  base_link = `GeoExt.MapPanel <http://geoext.org/lib/GeoExt/widgets/MapPanel.html>`_
 */

Heron.widgets.MapPanelOptsDefaults = {
    center: '0,0',

    map: {
        units: 'degrees',
        maxExtent: '-180,-90,180,90',
        extent: '-180,-90,180,90',
        maxResolution: 0.703125,
        numZoomLevels: 20,
        zoom: 1,
        allOverlays: false,
        fractionalZoom: false,
        /**
         * Useful to always have permalinks enabled. default is enabled with these settings.
         * MapPanel.getPermalink() returns current permalink
         *
         **/
        permalinks: {
            /** The prefix to be used for parameters, e.g. map_x, default is 'map' */
            paramPrefix: 'map',
            /** Encodes values of permalink parameters ? default false*/
            encodeType: false,
            /** Use Layer names i.s.o. OpenLayers-generated Layer Id's in Permalinks */
            prettyLayerNames: true
        },

//		resolutions: [1.40625,0.703125,0.3515625, 0.17578125, 0.087890625, 0.0439453125, 0.02197265625, 0.010986328125, 0.0054931640625, 0.00274658203125, 0.001373291015625, 0.0006866455078125, 0.00034332275390625, 0.000171661376953125, 8.58306884765625e-05, 4.291534423828125e-05, 2.1457672119140625e-05, 1.0728836059570312e-05, 5.3644180297851562e-06, 2.6822090148925781e-06, 1.3411045074462891e-06],

        controls: [
            new OpenLayers.Control.Attribution(),
            new OpenLayers.Control.ZoomBox(),
            new OpenLayers.Control.Navigation({dragPanOptions: {enableKinetic: true}}),
            new OpenLayers.Control.LoadingPanel(),
            new OpenLayers.Control.PanPanel(),
            new OpenLayers.Control.ZoomPanel()
            /*,				new OpenLayers.Control.OverviewMap()

             new OpenLayers.Control.ScaleLine({geodesic: true, maxWidth: 200}) */
        ]

    }
};

/** api: constructor
 *  .. class:: MapPanel(config)
 *
 *  A wrapper Panel for a GeoExt MapPanel.
 */
Heron.widgets.MapPanel = Ext.extend(
        GeoExt.MapPanel,
        {
            initComponent: function () {

                var gxMapPanelOptions = {
                    id: "gx-map-panel",
                    split: false,
                    layers: this.hropts.layers,
                    items: this.items ? this.items : [
                        {
                            xtype: "gx_zoomslider",
                            vertical: true,
                            height: 150,    // css => .olControlZoomPanel .olControlZoomOutItemInactive
                            x: 18,
                            y: 85,
                            aggressive: false,
                            plugins: new GeoExt.ZoomSliderTip(
                                    { template: __("Scale") + ": 1 : {scale}<br>" +
                                            __("Resolution") + ": {resolution}<br>" +
                                            __("Zoom") + ": {zoom}" }
                            )
                        }
                    ],
                    // Set default statusbar items.
                    statusbar: [
                        {type: "epsgpanel"} ,
                        {type: "-"} ,
                        {type: "xcoord"},
                        {type: "ycoord"},
                        {type: "-"},
                        {type: "measurepanel"}
                    ],

                    // Start with empty toolbar and fill through config.
                    tbar: new Ext.Toolbar({enableOverflow: true, items: []}),

                    // Start with empty statusbar and fill through config.
                    bbar: new Ext.Toolbar({enableOverflow: true, items: []})
                };

                // Custom statusbar?
                if (this.hropts.hasOwnProperty("statusbar")) {
                    if (this.hropts.statusbar) {
                        // Override default statusbar items.
                        Ext.apply(gxMapPanelOptions.statusbar, this.hropts.statusbar);
                    } else {
                        // No status bar.
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

                // Center may be: unset, string coordinates or OpenLayers (LonLat) object
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
                // Somehow needed, otherwise OL exception with get projectionObject()
                gxMapPanelOptions.map.layers = this.hropts.layers;

                Ext.apply(this, gxMapPanelOptions);

                if (this.layers) {
                    // Check if Layer objects are specified using the factory method (arguments)
                    // Create "real" Layer objects if required.
                    for (var i = 0; i < this.layers.length; i++) {
                        if (this.layers[i] instanceof Array) {
                            // Call factory method to create Layer instance from array with type and args
                            try {
                                this.layers[i] = Heron.Utils.createOLObject(this.layers[i]);
                            } catch(err) {
                                alert("Error creating Layer num=" + i + " msg=" + err.message + " args=" + this.layers[i]);
                            }
                        }
                    }
                }

                // Enable permalinks if set, default is enabled
                if (this.map.permalinks) {
                    // So layer names can be used
                    this.prettyStateKeys = this.map.permalinks.prettyLayerNames;

                    // The prefix in parameter names e.g. map_ like in map_x and map_y
                    this.stateId = this.map.permalinks.paramPrefix;

                    this.permalinkProvider = new GeoExt.state.PermalinkProvider({encodeType: this.map.permalinks.encodeType});
                    Ext.state.Manager.setProvider(this.permalinkProvider);
                }

                Heron.widgets.MapPanel.superclass.initComponent.call(this);

                // Check for custom format functions for xy coordinate text.
                if (this.hropts.settings && this.hropts.settings.formatX) {
                    // Override format function for x coordinate.
                    this.formatX = this.hropts.settings.formatX;
                }
                if (this.hropts.settings && this.hropts.settings.formatY) {
                    // Override format function for y coordinate.
                    this.formatY = this.hropts.settings.formatY;
                }

                // Set the global OpenLayers map variable, everyone needs it
                Heron.App.setMap(this.getMap());

                // Set the global GeoExt MapPanel variable, some need it
                Heron.App.setMapPanel(this);

                // Build top toolbar (if specified)
                Heron.widgets.ToolbarBuilder.build(this,
                        this.hropts.toolbar,
                        this.getTopToolbar());

                // Build statusbar (i.e. bottom toolbar)
                Heron.widgets.ToolbarBuilder.build(this,
                        gxMapPanelOptions.statusbar,
                        this.getBottomToolbar());
            },

            /** api: config[formatX]
             * ``Function`` A custom format function for the x coordinate text.
             * When set this function overrides the default format function.
             * The signature of this function should be: ``function(lon,precision)``.
             * The result should be a ``String`` with the formatted text.
             *
             * Example:
             *  .. code-block:: javascript
             *
             Heron.options.map.settings = {

              formatX: function(lon,precision) {
                  return 'x: ' + lon.toFixed(precision) + ' m.';
              },


						*/
            formatX: function (lon, precision) {
                return "X: " + lon.toFixed(precision);
            },

            /** api: config[formatY]
             * ``Function`` A custom format function for the y coordinate text.
             * When set this function overrides the default format function.
             * The signature of this function should be: ``function(lat,precision)``.
             * The result should be a ``String`` with the formatted text.
             *
             * Example:
             *  .. code-block:: javascript
             *
             Heron.options.map.settings = {

                  formatY: function(lat,precision) {
                      return 'y: ' + lat.toFixed(precision) + ' m.';
                  },

						*/
            formatY: function (lat, precision) {
                return "Y: " + lat.toFixed(precision);
            },

            getPermalink: function () {
                return this.permalinkProvider.getLink();
            },

            getMap: function () {
                return this.map;
            },

            afterRender: function () {
                Heron.widgets.MapPanel.superclass.afterRender.apply(this, arguments);

                var xy_precision = 3;
                if (this.hropts && this.hropts.settings && this.hropts.settings.hasOwnProperty('xy_precision')) {
                    xy_precision = this.hropts.settings.xy_precision;
                }

                // Get local vars for format functions.
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

                    // Get x coordinate text element.
                    var xcoord = Ext.getCmp("x-coord");
                    if (xcoord) {
                        // Found, show x coordinate text.
                        xcoord.setText(formatX(lonLat.lon, xy_precision));
                    }
                    // Get y coordinate text element.
                    var ycoord = Ext.getCmp("y-coord");
                    if (ycoord) {
                        // Found, show y coordinate text.
                        ycoord.setText(formatY(lonLat.lat, xy_precision));
                    }

                };

                var map = this.getMap();

                map.events.register("mousemove", map, onMouseMove);

                // EPSG box
                var epsgTxt = map.getProjection();
                if (epsgTxt) {
                    // Get EPSG text element.
                    var epsg = Ext.getCmp("map-panel-epsg");
                    if (epsg) {
                        // Found, show EPSG text.
                        epsg.setText(epsgTxt);
                    }
                }
            }
        });

/** api: xtype = hr_mappanel */
Ext.reg('hr_mappanel', Heron.widgets.MapPanel);
