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
Ext.namespace("Heron.widgets.ToolbarBuilder");

/** api: (define)
 *  module = Heron.widgets
 *  class = ToolbarBuilder
 */

/**
 * Definition of MapPanel toolbar widgets as object map.
 *
 * Each definition has
 * - a key as a named identifier e.g. "baselayer" or "geocoder"
 * - an object with an "options" object for default config options
 * - a create function that returns an GeoExt, Heron or Ext Component
 *
 * Within a Heron config the Toolbar can be defined by adding definitions
 * by name to the MapPanel ("toolbar" property) and optionally overriding the
 * their default options.
 *
 * The ToolbarBuilder.build function will be called by the MapPanel and will
 * call the create() function for each configured item, passing (possibly
 * overidden options) and the MapPanel object.
 *
 * See the Heron examples configs.
 * */
Heron.widgets.ToolbarBuilder.defs = {

    baselayer: {
        options: {
            id: "baselayercombo"
        },

        create: function (mapPanel, options) {
            if (!options.initialConfig) {
                options.initialConfig = {};
            }
            Ext.apply(options.initialConfig, options);
            options.initialConfig.map = mapPanel.getMap();
            return new Heron.widgets.BaseLayerCombo(options);
        }
    },

    geocoder: {
        options: {
            id: "geocodercombo"
        },

        create: function (mapPanel, options) {
            return new Heron.widgets.search.GeocoderCombo(options);
        }
    },

    scale: {
        options: {
            // width: 120,
            // emptyText: __('Scale'),
            // tooltip: __('Scale'),
            id: "scalecombo"
        },

        create: function (mapPanel, options) {
            if (!options.initialConfig) {
                options.initialConfig = {};
            }
            Ext.apply(options.initialConfig, options);
            return new Heron.widgets.ScaleSelectorCombo(options);
        }
    },

    featureinfo: {
        options: {
            tooltip: __('Feature information'),
            iconCls: "icon-getfeatureinfo",
            enableToggle: true,
            pressed: false,
            id: "featureinfo",
            toggleGroup: "toolGroup",
            popupWindowDefaults: {
                // Eddy
                title: __('Feature Info'),
                width: 360,
                height: 200,
                anchored: false,
                hideonmove: false
            },
            controlDefaults: {
                maxFeatures: 8,
                hover: false,
                drillDown: true,
                infoFormat: "application/vnd.ogc.gml",
                queryVisible: true
            }
        },

        create: function (mapPanel, options) {
            if (options.getfeatureControl) {
                options.controlDefaults = Ext.apply(options.controlDefaults, options.getfeatureControl);
            }

            options.control = new OpenLayers.Control.WMSGetFeatureInfo(options.controlDefaults);

            // FeatureInfoPanel via Popup
            if (options.popupWindow) {
                var self = this;

                //The control will be added to the map in constuctor of GeoExt.Action
                var popupWindowProps = Ext.apply(options.popupWindowDefaults, options.popupWindow);

                //Add the control to the popupWindow.
                popupWindowProps.olControl = options.control;

                //Apply the featureinfopanel-options to the popupWindow.
                popupWindowProps.featureInfoPanel = options.popupWindow ? options.popupWindow.featureInfoPanel : null;

                var createPopupWindow = function () {
                    // Create only once, show only when features found
                    if (!self.featurePopupWindow) {
                        self.featurePopupWindow = new Heron.widgets.search.FeatureInfoPopup(popupWindowProps);
                    }
                };

                // If enabled already create the window.
                if (options.pressed) {
                    createPopupWindow();
                }
                options.handler = function () {
                    createPopupWindow();
                    self.featurePopupWindow.hide();
                };
            }
            return new GeoExt.Action(options);
        }
    },

    help: {
        options: {
            tooltip: __('Help'),
            iconCls: "icon-help",
            enableToggle: false,
            pressed: false,
            id: "helpinfo",
            content: '<b>Default help info</b>',
            contentUrl: null,
            popupWindowDefaults: {
                title: __('Help'),
                border: true,
//                layout: 'fit',
                layoutConfig: {
                    padding: '0'
                },
                width: 460,
                height: 540,
                autoScroll: true,
                items: [
                    {
                        xtype: 'hr_htmlpanel',
                        height: '100%',
                        width: '95%',
                        preventBodyReset: true,
                        bodyBorder: false,
                        border: false,
                        style: {
                            paddingTop: '12px',
                            paddingBottom: '12px',
                            paddingLeft: '10px',
                            paddingRight: '10px',
                            fontFamily: 'Verdana, Arial, Helvetica, sans-serif',
                            fontSize: '10px',
                            background: '#FFFFFF',
                            color: '#111111'
                        }
                    }
                ]
            }
        },

        create: function (mapPanel, options) {
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
        }
    },

    tooltips: {
        options: {
            tooltip: __('Feature tooltips'),
            iconCls: "icon-featuretooltip",
            enableToggle: true,
            pressed: false,
            id: "tooltips",
            toggleGroup: "tooltipsGrp",
            popupWindowDefaults: {
                title: __('FeatureTooltip'),
                anchored: true,
                hideonmove: true,
                height: 150
            },
            controlDefaults: {
                maxFeatures: 1,
                hover: true,
                drillDown: false,
                infoFormat: "application/vnd.ogc.gml",
                queryVisible: true
            }
        },

        create: function (mapPanel, options) {
            // Tooltips and featureinfo with popup+hover are really the same thing except for the icon and other minor
            // settings, so reuse the implementation
            return Heron.widgets.ToolbarBuilder.defs.featureinfo.create(mapPanel, options);
        }
    },

    pan: {
        options: {
            tooltip: __('Pan'),
            /* Use iconCls: "icon-hand" for hand icon */
            iconCls: "icon-hand",
            enableToggle: true,
            pressed: true,
            control: new OpenLayers.Control.Navigation(),
            id: "pan",
            toggleGroup: "toolGroup"
        },

        create: function (mapPanel, options) {
            return new GeoExt.Action(options);
        }
    },

    upload: {
        options: {
            tooltip: __('Upload features from local file'),
            iconCls: "icon-upload",
            enableToggle: false,
            pressed: false,
            id: "hr-upload-button",
            toggleGroup: "toolGroup",
            upload: {
                layerName: __('My Upload'),
                visibleOnUpload: true,
                url: Heron.globals.serviceUrl,
                params: {
                    action: 'upload',
                    mime: 'text/html',
                    encoding: 'escape'
                },
                formats: [
                    {name: 'Well-Known-Text (WKT)', fileExt: '.wkt', mimeType: 'text/plain', formatter: 'OpenLayers.Format.WKT'},
                    {name: 'Geographic Markup Language - v2 (GML2)', fileExt: '.gml', mimeType: 'text/xml', formatter: 'OpenLayers.Format.GML'},
                    {name: 'Geographic Markup Language - v3 (GML3)', fileExt: '.gml', mimeType: 'text/xml', formatter: 'OpenLayers.Format.GML.v3'},
                    {name: 'GeoJSON', fileExt: '.json', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON'},
                    {name: 'GPS Exchange Format (GPX)', fileExt: '.gpx', mimeType: 'text/xml', formatter: 'OpenLayers.Format.GPX'},
                    {name: 'Keyhole Markup Language (KML)', fileExt: '.kml', mimeType: 'text/xml', formatter: 'OpenLayers.Format.KML'},
                    {name: 'CSV (with X,Y)', fileExt: '.csv', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON'},
                    {name: 'ESRI Shapefile (zipped)', fileExt: '.zip', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON'}
                ],
                // For custom projections use Proj4.js
                fileProjection: new OpenLayers.Projection('EPSG:4326')
            }
        },

        create: function (mapPanel, options) {
            var map = mapPanel.getMap();
            options.upload.map = map;
            // Define Vector Layer once (see Ext namespace def above)
            // Global var for Vector drawing features

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
        }
    },

    zoomin: {
        options: {
            tooltip: __('Zoom in'),
            iconCls: "icon-zoom-in",
            enableToggle: true,
            pressed: false,
            control: new OpenLayers.Control.ZoomBox({
                title: __('Zoom in'),
                out: false
            }),
            id: "zoomin",
            toggleGroup: "toolGroup"
        },

        create: function (mapPanel, options) {
            return new GeoExt.Action(options);
        }
    },

    zoomout: {
        options: {
            tooltip: __('Zoom out'),
            iconCls: "icon-zoom-out",
            enableToggle: true,
            pressed: false,
            control: new OpenLayers.Control.ZoomBox({
                title: __('Zoom out'),
                out: true
            }),
            id: "zoomout",
            toggleGroup: "toolGroup"
        },

        create: function (mapPanel, options) {
            return new GeoExt.Action(options);
        }
    },

    zoomvisible: {
        options: {
            tooltip: __('Zoom to full extent'),
            iconCls: "icon-zoom-visible",
            enableToggle: false,
            pressed: false,
            control: new OpenLayers.Control.ZoomToMaxExtent(),
            id: "zoomvisible"
        },
        create: function (mapPanel, options) {
            return new GeoExt.Action(options);
        }
    },

    zoomprevious: {
        options: {
            tooltip: __('Zoom previous'),
            iconCls: "icon-zoom-previous",
            enableToggle: false,
            disabled: true,
            pressed: false,
            id: "zoomprevious"
        },

        create: function (mapPanel, options) {
            // create a navigation history control
            if (!mapPanel.historyControl) {
                mapPanel.historyControl = new OpenLayers.Control.NavigationHistory();
                mapPanel.getMap().addControl(mapPanel.historyControl);
            }
            options.control = mapPanel.historyControl.previous;

            return new GeoExt.Action(options);
        }
    },

    zoomnext: {
        options: {
            tooltip: __('Zoom next'),
            iconCls: "icon-zoom-next",
            enableToggle: false,
            disabled: true,
            pressed: false,
            id: "zoomnext"
        },

        create: function (mapPanel, options) {
            if (!mapPanel.historyControl) {
                mapPanel.historyControl = new OpenLayers.Control.NavigationHistory();
                mapPanel.getMap().addControl(mapPanel.historyControl);
            }
            options.control = mapPanel.historyControl.next;
            return new GeoExt.Action(options);
        }
    },

    measurelength: {
        options: {
            tooltip: __('Measure length'),
            iconCls: "icon-measure-length",
            enableToggle: true,
            pressed: false,
            measureLastLength: 0.0,
            control: new OpenLayers.Control.Measure(OpenLayers.Handler.Path, {
                persist: true,
                immediate: true,
                displayClass: "olControlMeasureDistance", // css-Cursor
                handlerOptions: {
                    layerOptions: {styleMap: new OpenLayers.StyleMap({
                        "default": new OpenLayers.Style(null, {
                            rules: [new OpenLayers.Rule({
                                symbolizer: {
                                    /*
                                     "Point": {
                                     pointRadius: 4,
                                     graphicName: "square",
                                     fillColor: "white",
                                     fillOpacity: 1,
                                     strokeWidth: 1,
                                     strokeOpacity: 1,
                                     strokeColor: "#333333"
                                     },
                                     "Line": {
                                     strokeWidth: 2,
                                     strokeOpacity: 1,
                                     strokeColor: "#666666",
                                     strokeDashstyle: "dash"
                                     }
                                     */
                                    "Point": {
                                        pointRadius: 10,
                                        graphicName: "square",
                                        fillColor: "white",
                                        fillOpacity: 0.25,
                                        strokeWidth: 1,
                                        strokeOpacity: 1,
                                        strokeColor: "#333333"
                                    },
                                    "Line": {
                                        strokeWidth: 1,
                                        strokeOpacity: 1,
                                        strokeColor: "#FF0000",
                                        strokeDashstyle: "solid"
                                        /* strokeDashstyle: "dot dash dashdot longdash longdashdot solid" */
                                    }
                                }
                            })]
                        })
                    })
                    }
                }
            }),
            id: "measurelength",
            toggleGroup: "toolGroup"
        },

        create: function (mapPanel, options) {
            var action = new GeoExt.Action(options);
            var map = mapPanel.getMap();
            var controls = map.getControlsByClass("OpenLayers.Control.Measure");

            /** Measurements handling function for length/area. */

            Heron.widgets.ToolbarBuilder.onMeasurementsActivate = function (event) {
                Ext.getCmp("measurelength").measureLastLength = 0.0;
            };

            Heron.widgets.ToolbarBuilder.onMeasurements = function (event) {
                var units = event.units;
                var measure = event.measure;
                var out = "";
                if (event.order == 1) {
                    out += __('Length') + ": " + measure.toFixed(2) + " " + units;

                    /* Show diff */
                    var item = Ext.getCmp("measurelength");
                    // only works with option 'immediate: false' => static measurement
                    //		if (item.measureLastLength > 0) {
                    //			out += "  (" + __('Leg') + ": " + (measure - item.measureLastLength).toFixed(2) + "&nbsp;" + units + ")";
                    //		}
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

                    /* Show diff */
                    var item = Ext.getCmp("measurelength");
                    // only works with option 'immediate: false' => static measurement
                    //		if (item.measureLastLength > 0) {
                    //			out += "  (" + __('Leg') + ": " + (measure - item.measureLastLength).toFixed(2) + "&nbsp;" + units + ")";
                    //		}
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
                // Only register for Distance measurements (otherwise may get events twice)
                // See http://code.google.com/p/geoext-viewer/issues/detail?id=106
                if (controls[i].displayClass == 'olControlMeasureDistance') {
                    // Add optional property "geodesic" to cater for those projections
                    // See http://code.google.com/p/geoext-viewer/issues/detail?id=90
                    controls[i].geodesic = options.geodesic;
                    controls[i].events.register("activate", map, Heron.widgets.ToolbarBuilder.onMeasurementsActivate);
                    controls[i].events.register("measure", map, Heron.widgets.ToolbarBuilder.onMeasurements);
                    controls[i].events.register("measurepartial", map, Heron.widgets.ToolbarBuilder.onMeasurementsPartial);
                    controls[i].events.register("deactivate", map, Heron.widgets.ToolbarBuilder.onMeasurementsDeactivate);
                    break;
                }
            }

            return action;
        }

    },

    measurearea: {
        options: {
            tooltip: __('Measure area'),
            iconCls: "icon-measure-area",
            enableToggle: true,
            pressed: false,
            control: new OpenLayers.Control.Measure(OpenLayers.Handler.Polygon, {
                persist: true,
                immediate: true,
                displayClass: "olControlMeasureArea", // css-Cursor
                handlerOptions: {
                    layerOptions: {styleMap: new OpenLayers.StyleMap({
                        "default": new OpenLayers.Style(null, {
                            rules: [new OpenLayers.Rule({
                                symbolizer: {
                                    /*
                                     "Point": {
                                     pointRadius: 4,
                                     graphicName: "square",
                                     fillColor: "white",
                                     fillOpacity: 1,
                                     strokeWidth: 1,
                                     strokeOpacity: 1,
                                     strokeColor: "#333333"
                                     },
                                     "Polygon": {
                                     strokeWidth: 2,
                                     strokeOpacity: 1,
                                     strokeColor: "#666666",
                                     fillColor: "white",
                                     fillOpacity: 0.3
                                     }
                                     */
                                    "Point": {
                                        pointRadius: 10,
                                        graphicName: "square",
                                        fillColor: "white",
                                        fillOpacity: 0.25,
                                        strokeWidth: 1,
                                        strokeOpacity: 1,
                                        strokeColor: "#333333"
                                    },
                                    "Polygon": {
                                        strokeWidth: 1,
                                        strokeOpacity: 1,
                                        strokeColor: "#FF0000",
                                        strokeDashstyle: "solid",
                                        fillColor: "#FFFFFF",
                                        fillOpacity: 0.5
                                    }
                                }
                            })]
                        })
                    })
                    }
                }
            }),
            id: "measurearea",
            toggleGroup: "toolGroup"
        },

        create: function (mapPanel, options) {
            var action = new GeoExt.Action(options);
            var map = mapPanel.getMap();
            var controls = map.getControlsByClass("OpenLayers.Control.Measure");
            for (var i = 0; i < controls.length; i++) {
                // Only register for Area measurements (otherwise may get events twice)
                // See http://code.google.com/p/geoext-viewer/issues/detail?id=106
                if (controls[i].displayClass == 'olControlMeasureArea') {
                    // Add optional property "geodesic" to cater for those projections
                    // See http://code.google.com/p/geoext-viewer/issues/detail?id=90
                    controls[i].geodesic = options.geodesic;
                    controls[i].events.register("activate", map, Heron.widgets.ToolbarBuilder.onMeasurementsActivate);
                    controls[i].events.register("measure", map, Heron.widgets.ToolbarBuilder.onMeasurements);
                    controls[i].events.register("measurepartial", map, Heron.widgets.ToolbarBuilder.onMeasurementsPartial);
                    controls[i].events.register("deactivate", map, Heron.widgets.ToolbarBuilder.onMeasurementsDeactivate);
                    break;
                }
            }

            return action;
        }

    },
    /**
     * OpenLayers Editor Control Panel
     * The OpenLayers Editor (OLE) is a set of OpenLayers widgets developed by GeOps http://www.geops.de.
     * and enhanced for the Heron project by Just: https://github.com/justb4/ole.
     *
     * */
    oleditor: {
        /* Default options to be passed to create function below. */
        options: {
            tooltip: __('Draw Features'),
            iconCls: "icon-mapedit",
            enableToggle: true,
            pressed: false,
            id: "mapeditor",
            toggleGroup: "toolGroup",

            // Options for OLEditor
            olEditorOptions: {
                activeControls: ['UploadFeature', 'DownloadFeature', 'Separator', 'Navigation', 'SnappingSettings', 'CADTools', 'Separator', 'DeleteAllFeatures', 'DeleteFeature', 'DragFeature', 'SelectFeature', 'Separator', 'DrawHole', 'ModifyFeature', 'Separator'],
                featureTypes: ['text', 'polygon', 'path', 'point'],
                language: 'en',
                DownloadFeature: {
                    url: Heron.globals.serviceUrl,
                    params: {
                        action: 'download',
                        mime: 'text/plain',
                        filename: 'editor',
                        encoding: 'none'
                    },
                    formats: [
                        {name: 'Well-Known-Text (WKT)', fileExt: '.wkt', mimeType: 'text/plain', formatter: 'OpenLayers.Format.WKT'},
                        {name: 'Geographic Markup Language - v2 (GML2)', fileExt: '.gml', mimeType: 'text/xml', formatter: new OpenLayers.Format.GML.v2({featureType: 'oledit', featureNS: 'http://geops.de'})},
                        {name: 'Geographic Markup Language - v3 (GML3)', fileExt: '.gml', mimeType: 'text/xml', formatter: new OpenLayers.Format.GML.v3({featureType: 'oledit', featureNS: 'http://geops.de'})},
                        {name: 'GeoJSON', fileExt: '.json', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON'},
                        {name: 'GPS Exchange Format (GPX)', fileExt: '.gpx', mimeType: 'text/xml', formatter: 'OpenLayers.Format.GPX'},
                        {name: 'Keyhole Markup Language (KML)', fileExt: '.kml', mimeType: 'text/xml', formatter: 'OpenLayers.Format.KML'}
                    ],
                    // For custom projections use Proj4.js
                    fileProjection: new OpenLayers.Projection('EPSG:4326')
                },
                UploadFeature: {
                    url: Heron.globals.serviceUrl,
                    params: {
                        action: 'upload',
                        mime: 'text/html',
                        encoding: 'escape'
                    },
                    formats: [
                        {name: 'Well-Known-Text (WKT)', fileExt: '.wkt', mimeType: 'text/plain', formatter: 'OpenLayers.Format.WKT'},
                        {name: 'Geographic Markup Language - v2 (GML2)', fileExt: '.gml', mimeType: 'text/xml', formatter: 'OpenLayers.Format.GML'},
                    /** {name: 'Geographic Markup Language - v2 (GML2)', fileExt: '.gml', mimeType: 'text/xml', formatter: 'OpenLayers.Format.GML.v3'}, */
                        {name: 'Geographic Markup Language - v3 (GML3)', fileExt: '.gml', mimeType: 'text/xml', formatter: 'OpenLayers.Format.GML.v3'},
                        {name: 'GeoJSON', fileExt: '.json', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON'},
                        {name: 'GPS Exchange Format (GPX)', fileExt: '.gpx', mimeType: 'text/xml', formatter: 'OpenLayers.Format.GPX'},
                        {name: 'Keyhole Markup Language (KML)', fileExt: '.kml', mimeType: 'text/xml', formatter: 'OpenLayers.Format.KML'},
                        {name: 'CSV (with X,Y)', fileExt: '.csv', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON'},
                        {name: 'ESRI Shapefile (zipped)', fileExt: '.zip', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON'}
                    ],
                    // For custom projections use Proj4.js
                    fileProjection: new OpenLayers.Projection('EPSG:4326')
                }
            }
            // save: function() {alert('saved')}
        },

        create: function (mapPanel, options) {
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
                    // map.removeLayer(editor.editLayer);
                    // editor.editLayer.eraseFeatures();
                }
                editor.stopEditMode();
            };

            // A trivial handler
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

            // Provide an ExtJS Action object
            // If you use an OpenLayers control, you need to provide a GeoExt Action object.
            options.control = this.editor.editorPanel;
            return new GeoExt.Action(options);
        }
    },
    any: {
        /** Add your own stuff like a Menu config */
        options: {
            tooltip: __('Anything is allowed here'),
            text: __('Any valid Toolbar.add() config goes here')
        },

        create: function (mapPanel, options) {
            return options;
        }
    },
    search_nominatim: {
        /** DEPRECATED : PLEASE USE 'namesearch' with xtype: hr_nominatimsearchcombo' + options */
        options: {
            tooltip: __('Search Nominatim'),
            id: "search_nominatim"
        },

        create: function (mapPanel, options) {
            return new Heron.widgets.search.NominatimSearchCombo(options);
        }
    },

    namesearch: {
        options: {
            id: "namesearch"
        },

        create: function (mapPanel, options) {
            return Ext.create(options);
        }
    },

    searchcenter: {

        /* Options to be passed to your create function. */
        options: {
            id: "searchcenter",
            tooltip: __('Search'),
            iconCls: "icon-find",
            pressed: false,
            enableToggle: false,
//            toggleGroup: "toolGroup",

            searchWindowDefault: {
                title: __('Search'),
                layout: "fit",
                closeAction: "hide",
                x: 100,
                width: 400,
                height: 400
            }
        },

        // Instead of an internal "type".
        // provide a create factory function.
        // MapPanel and options (see below) are always passed
        create: function (mapPanel, options) {
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

            // A trivial handler
            // Handler to create Window with FeatSelSearchPanel
            options.handler = function () {
                toggleSearchWindow();
            };

            // Provide an ExtJS Action object (invokes handler on click)
            if (options.enableToggle) {
                return new GeoExt.Action(options)
            } else {
                return new Ext.Action(options);
            }
        }
    },

    printdialog: {

        // Options to be passed to your create function. //
        options: {
            id: "hr_printdialog",
            title: __('Print Dialog'),
            tooltip: __('Print Dialog Popup with Preview Map'),
            iconCls: "icon-printer",
            enableToggle: false,
            pressed: false,
            windowTitle: __('Print Preview'),
            windowWidth: 400,
            method: 'POST',
            url: null, // 'http://kademo.nl/print/pdf28992',
            legendDefaults: {
                useScaleParameter: false,
                baseParams: {FORMAT: "image/png"}
            },
            showTitle: true,
            mapTitle: null,
            mapTitleYAML: "mapTitle", // MapFish - field name in config.yaml - default is: 'mapTitle'
            showComment: true,
            mapComment: null,
            mapCommentYAML: "mapComment", // MapFish - field name in config.yaml - default is: 'mapComment'
            showFooter: false,
            mapFooter: null,
            mapFooterYAML: "mapFooter", // MapFish - field name in config.yaml - default is: 'mapFooter'
            showRotation: true,
            showLegend: true,
            showLegendChecked: false,
            mapLimitScales: true,
            showOutputFormats: false
        },

        // Instead of an internal "type" provide a create factory function.
        // MapPanel and options (see below) are always passed.
        create: function (mapPanel, options) {

            // Show a popup Print Preview
            options.handler = function () {

                //Check to see if we're allowed to print. Bug out if not.
                if (!Heron.widgets.ToolbarBuilder.checkCanWePrint(mapPanel.getMap().layers)) {
                    return;
                }

                var printWindow = new Heron.widgets.PrintPreviewWindow({
                    title: options.windowTitle,
                    modal: true,
                    border: false,
                    resizable: false,
                    width: options.windowWidth,
                    autoHeight: true,

                    hropts: {
                        mapPanel: mapPanel,
                        method: options.method,
                        url: options.url,
                        legendDefaults: options.legendDefaults,
                        showTitle: options.showTitle,
                        mapTitle: options.mapTitle,
                        mapTitleYAML: options.mapTitleYAML,
                        showComment: options.showComment,
                        mapComment: options.mapComment,
                        mapCommentYAML: options.mapCommentYAML,
                        showFooter: options.showFooter,
                        mapFooter: options.mapFooter,
                        mapFooterYAML: options.mapFooterYAML,
                        showRotation: options.showRotation,
                        showLegend: options.showLegend,
                        showLegendChecked: options.showLegendChecked,
                        mapLimitScales: options.mapLimitScales,
                        showOutputFormats: options.showOutputFormats
                    }

                });
            };

            // Provide an ExtJS Action object
            // If you use an OpenLayers control, you need to provide a GeoExt Action object.
            return new Ext.Action(options);
        }
    },

    printdirect: {

        // Options to be passed to your create function. //
        options: {
            id: "printdirect",
            tooltip: __('Print Visible Map Area Directly'),
            iconCls: "icon-print-direct",
            enableToggle: false,
            pressed: false,
            method: 'POST',
            url: null,
            mapTitle: null,
            mapTitleYAML: "mapTitle", // MapFish - field name in config.yaml - default is: 'mapTitle'
            mapComment: __('This is a simple map directly printed.'),
            mapCommentYAML: "mapComment", // MapFish - field name in config.yaml - default is: 'mapComment'
            mapFooter: null,
            mapFooterYAML: "mapFooter", // MapFish - field name in config.yaml - default is: 'mapFooter'
            mapPrintLayout: "A4", // MapFish - 'name' entry of the 'layouts' array or Null (=> MapFish default)
            mapPrintDPI: "75", // MapFish - 'value' entry of the 'dpis' array or Null (=> MapFish default)
            mapPrintLegend: false,
            excludeLayers: ['OpenLayers.Handler.Polygon', 'OpenLayers.Handler.RegularPolygon', 'OpenLayers.Handler.Path', 'OpenLayers.Handler.Point'], // Layer-names to be excluded from Printing, mostly edit-Layers
            legendDefaults: {
                useScaleParameter: true,
                baseParams: {FORMAT: "image/png"}
            }
        },

        // Instead of an internal "type".
        // provide a create factory function.
        // MapPanel and options (see below) are always passed
        create: function (mapPanel, options) {

            // Handler to create Print dialog popup Window
            options.handler = function () {

                //Check to see if we're allowed to print. Bug out if not.
                if (!Heron.widgets.ToolbarBuilder.checkCanWePrint(mapPanel.getMap().layers)) {
                    return;
                }

                // Display loading panel
                var busyMask = new Ext.LoadMask(Ext.getBody(), {msg: __('Create PDF...')});
                busyMask.show();

                Ext.Ajax.request({
                    url: options.url + '/info.json',
                    method: 'GET',
                    params: null,
                    success: function (result, request) {

                        var printCapabilities = Ext.decode(result.responseText);
                        var printProvider = new GeoExt.data.PrintProvider({
                            method: options.method, // "POST" recommended for production use
                            capabilities: printCapabilities, // from the info.json script in the html
                            customParams: { },
                            listeners: {
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
                                "printexception": function (printProvider, result) {
                                    alert(__('Error from Print server: ') + result.statusText);
                                },
                                "beforeencodelayer": function (printProvider, layer) {
                                    // Exclude Layer from Printing if name matches by returning False
                                    for (var i = 0; i < options.excludeLayers.length; i++) {
                                        if (layer.name == options.excludeLayers[i]) {
                                            return false;
                                        }
                                    }
                                    return true;
                                }

                            }
                        });

                        // Dynamicly declare the MapFish names of the config.yaml output fields
                        // for the 'customParams' of the 'printProvider'
                        printProvider.customParams[options.mapTitleYAML] = (options.mapTitle) ? options.mapTitle : '';
                        printProvider.customParams[options.mapCommentYAML] = (options.mapComment) ? options.mapComment : '';
                        printProvider.customParams[options.mapFooterYAML] = (options.mapFooter) ? options.mapFooter : '';

                        // Set print layout format
                        if ((printProvider.layouts.getCount() > 1) && (options.mapPrintLayout)) {
                            var index = printProvider.layouts.find('name', options.mapPrintLayout);
                            if (index != -1) {
                                printProvider.setLayout(printProvider.layouts.getAt(index));
                            }
                        }

                        // Set print DPI format
                        if ((printProvider.dpis.getCount() > 1) && (options.mapPrintDPI)) {
                            var index = printProvider.dpis.find('value', options.mapPrintDPI);
                            if (index != -1) {
                                printProvider.setDpi(printProvider.dpis.getAt(index));
                            }
                        }

                        // Only print the legend entries if:
                        // - print legend request is true
                        // - Layer is visible  AND
                        // - it should not be hidden (hideInLegend == true) AND
                        // - it has not been created
                        // See doc for 'Heron.widgets.LayerLegendPanel'
                        if (options.mapPrintLegend) {
                            // Hidden LegendPanel : needed to fetch active legends
                            var legendPanel = new Heron.widgets.LayerLegendPanel({
                                renderTo: document.body,
                                hidden: true,
                                defaults: options.legendDefaults
                            });
                        }

                        // The printProvider that connects us to the print service of our print page.
                        // Tells the PrintProvider about the scale and center of our page.
                        var printPage = new GeoExt.data.PrintPage({
                            printProvider: printProvider
                        });

                        // Convenient way to fit the print page to the visible map area
                        printPage.fit(mapPanel, true);

                        // Print the page, optionally including the legend
                        printProvider.print(mapPanel, printPage, options.mapPrintLegend && {legend: legendPanel});

                        // Hide loading panel
                        busyMask.hide();

                    },
                    failure: function (result, request) {
                        // Hide loading panel
                        busyMask.hide();
                        alert(__('Error getting Print options from server: ') + options.url);
                    }
                });

            };
            // Provide an ExtJS Action object (invokes handler on click)
            return new Ext.Action(options);
        }
    },
    coordinatesearch: {
        /* Options to be passed to your create function. */
        options: {
            id: "coordinatesearch",
            tooltip: __('Enter coordinates to go to location on map'),
            iconCls: "icon-map-pin",
            enableToggle: false,
            pressed: false,
            formWidth: 340,
            formPageX: 200,
            formPageY: 75,
            buttonAlign: 'center'
        },

        // Instead of an internal "type".
        // provide a create factory function.
        // MapPanel and options (see below) are always passed
        create: function (mapPanel, options) {
            // A trivial handler
            options.handler = function () {
                // Create only once
                if (!this.coordPopup) {
                    // default entries
                    var sp = new Heron.widgets.search.CoordSearchPanel({ });
                    this.coordPopup = new Ext.Window({
                        layout: 'auto',
                        resizable: false,
                        autoHeight: true,
                        pageX: options.formPageX,
                        pageY: options.formPageY,
                        width: options.formWidth,
                        // height: options.formHeight,
                        closeAction: 'hide',
                        title: __('Go to coordinates'),
                        items: [new Heron.widgets.search.CoordSearchPanel({
                            deferredRender: false,
                            border: false,
                            title: options.title ? options.title : null,
                            titleDescription: options.titleDescription ? options.titleDescription : sp.titleDescription,
                            titleDescriptionStyle: options.titleDescriptionStyle ? options.titleDescriptionStyle : sp.titleDescriptionStyle,
                            bodyBaseCls: options.bodyBaseCls ? options.bodyBaseCls : sp.bodyBaseCls,
                            bodyItemCls: options.bodyItemCls ? options.bodyItemCls : null,
                            bodyCls: options.bodyCls ? options.bodyCls : null,
                            fieldMaxWidth: options.fieldMaxWidth ? options.fieldMaxWidth : sp.fieldMaxWidth,
                            fieldLabelWidth: options.fieldLabelWidth ? options.fieldLabelWidth : sp.fieldLabelWidth,
                            fieldStyle: options.fieldStyle ? options.fieldStyle : sp.fieldStyle,
                            fieldLabelStyle: options.fieldLabelStyle ? options.fieldLabelStyle : sp.fieldLabelStyle,
                            layerName: options.layerName ? options.layerName : sp.layerName,
                            onProjectionIndex: options.onProjectionIndex ? options.onProjectionIndex : sp.onProjectionIndex,
                            onZoomLevel: options.onZoomLevel ? options.onZoomLevel : sp.onZoomLevel,
                            showProjection: options.showProjection ? options.showProjection : sp.showProjection,
                            showZoom: options.showZoom ? options.showZoom : sp.showZoom,
                            showAddMarkers: options.showAddMarkers ? options.showAddMarkers : sp.showAddMarkers,
                            checkAddMarkers: options.checkAddMarkers ? options.checkAddMarkers : sp.checkAddMarkers,
                            showHideMarkers: options.showHideMarkers ? options.showHideMarkers : sp.showHideMarkers,
                            checkHideMarkers: options.checkHideMarkers ? options.checkHideMarkers : sp.checkHideMarkers,
                            showResultMarker: options.showResultMarker ? options.showResultMarker : sp.showResultMarker,
                            fieldResultMarkerStyle: options.fieldResultMarkerStyle ? options.fieldResultMarkerStyle : sp.fieldResultMarkerStyle,
                            fieldResultMarkerText: options.fieldResultMarkerText ? options.fieldResultMarkerText : sp.fieldResultMarkerText,
                            fieldResultMarkerSeparator: options.fieldResultMarkerSeparator ? options.fieldResultMarkerSeparator : sp.fieldResultMarkerSeparator,
                            fieldResultMarkerPrecision: options.fieldResultMarkerPrecision ? options.fieldResultMarkerPrecision : sp.fieldResultMarkerPrecision,
                            removeMarkersOnClose: options.removeMarkersOnClose ? options.removeMarkersOnClose : sp.removeMarkersOnClose,
                            showRemoveMarkersBtn: options.showRemoveMarkersBtn ? options.showRemoveMarkersBtn : sp.showRemoveMarkersBtn,
                            buttonAlign: options.buttonAlign ? options.buttonAlign : sp.buttonAlign,
                            hropts: options.hropts ? options.hropts : null
                        })
                        ]
                    });

                }
                // Toggle visibility
                if (this.coordPopup.isVisible()) {
                    this.coordPopup.hide();
                } else {
                    this.coordPopup.show(this);
                }
            };

            // Provide an ExtJS Action object
            // If you use an OpenLayers control, you need to provide a GeoExt Action object.
            return new Ext.Action(options);
        }
    },
    vectorstyler: {
        /* Options to be passed to your create function. */
        options: {
            id: "styler",
            tooltip: __('Edit vector Layer styles'),
            iconCls: "icon-palette",
            enableToggle: false,
            pressed: false,
            formWidth: 340,
            formPageX: 200,
            formPageY: 75,
            buttonAlign: 'center'
        },

        // Instead of an internal "type".
        // provide a create factory function.
        // MapPanel and options (see below) are always passed
        create: function (mapPanel, options) {
            // A trivial handler
            options.handler = function () {
                // Create only once
                if (!this.stylerPopup) {
                    var layer = mapPanel.map.getLayersByName('RD Info - Punten')[0];
                    var layerRecord = mapPanel.layers.getByLayer(layer);
                    var url = 'http://kademo.nl/gs2';
                    // default entries
                    this.stylerPopup = new Ext.Window({
                        layout: 'auto',
                        resizable: false,
                        autoHeight: true,
                        pageX: options.formPageX,
                        pageY: options.formPageY,
                        width: options.formWidth,
                        // height: options.formHeight,
                        closeAction: 'hide',
                        title: __('Style Editor'),
                        items: [
                            {
                                xtype: "gxp_wmsstylesdialog",
                                layerRecord: layerRecord,
                                plugins: [
                                    {
                                        ptype: "gxp_memorystylewriter",
                                        baseUrl: url
                                    }
                                ],
                                listeners: {
                                    "styleselected": function (cmp, style) {
                                        layer.mergeNewParams({
                                            styles: style
                                        });
                                    },
                                    "modified": function (cmp, style) {
                                        cmp.saveStyles();
                                    },
                                    "saved": function (cmp, style) {
                                        layer.mergeNewParams({
                                            _olSalt: Math.random(),
                                            styles: style
                                        });
                                    },
                                    scope: this
                                }}
                        ]
                    });

                }
                // Toggle visibility
                if (this.stylerPopup.isVisible()) {
                    this.stylerPopup.hide();
                } else {
                    this.stylerPopup.show(this);
                }
            };

            // Provide an ExtJS Action object
            // If you use an OpenLayers control, you need to provide a GeoExt Action object.
            return new Ext.Action(options);
        }
    },
    addbookmark: {
        options: {
            id: "addbookmark",
            tooltip: __('Bookmark current map context (layers, zoom, extent)'),
            iconCls: "icon-bookmark",
            enableToggle: false,
            disabled: false,
            pressed: false
        },

        create: function (mapPanel, options) {

            options.handler = function () {
                var bookmarksPanel = Heron.widgets.Bookmarks.getBookmarksPanel(this);
                if (!bookmarksPanel) {
                    alert(__('Error: No \'BookmarksPanel\' found.'));
                    return null;
                }
                bookmarksPanel.onAddBookmark();
            };
            return new GeoExt.Action(options);
        }
    },
    mapopen: {
        options: {
            id: "mapopen",
            tooltip: __('Open a map context (layers, styling, extent) from file'),
            iconCls: "icon-map-open",
            enableToggle: false,
            disabled: false,
            pressed: false
        },

        create: function (mapPanel, options) {
            options.handler = function () {
                Heron.data.MapContext.openContext(mapPanel, options);
            }
            return new GeoExt.Action(options);
        }
    },
    mapsave: {
        options: {
            id: "mapsave",
            tooltip: __('Save current map context (layers, styling, extent) to file'),
            iconCls: "icon-map-save",
            enableToggle: false,
            disabled: false,
            pressed: false,
            mime : 'text/xml',
            fileName: 'heron_map',
            fileExt: '.cml'
        },

        create: function (mapPanel, options) {
            options.handler = function () {
                Heron.data.MapContext.saveContext(mapPanel, options);
            }
            return new GeoExt.Action(options);
        }
    },

    epsgpanel: {
        options: {
            id: "map-panel-epsg",
            text: "",
            width: 80,
            xtype: "tbtext"
        },
        create: function (mapPanel, options) {
            return Ext.create(options);
        }
    },
    xcoord: {
        options: {
            id: "x-coord",
            text: "X:",
            width: 80,
            xtype: "tbtext"
        },
        create: function (mapPanel, options) {
            return Ext.create(options);
        }
    },
    ycoord: {
        options: {
            id: "y-coord",
            text: "Y:",
            width: 80,
            xtype: "tbtext"
        },
        create: function (mapPanel, options) {
            return Ext.create(options);
        }
    },
    measurepanel: {
        options: {
            id: "bbar_measure",
            text: "",
            xtype: "tbtext"
        },
        create: function (mapPanel, options) {
            return Ext.create(options);
        }
    }
};

/**
 * Check to see if there are any disallowed layers for printing.
 * For specialized issue: https://code.google.com/p/geoext-viewer/issues/detail?id=266
 */
Heron.widgets.ToolbarBuilder.checkCanWePrint = function (layers) {

    var failingLayers = '';

    // Loop through Layer array and create a text list of any that don't allow printing.
    for (var l = 0; l < layers.length; l++) {
        var nextLayer = layers[l];

        // Skip Layers that are invisible or printing allowed
        if (!nextLayer.visibility || !nextLayer.disallowPrinting) {
            continue;
        }

        // Layer test has failed so can't be printed.
        failingLayers += "\n - " + nextLayer.name;
        if (nextLayer.isBaseLayer) {
            failingLayers += __(' [Baselayer]');
        }
    }

    // We found at least one layer we can't print.
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

/**
 * Builds the toolbar or statusbar.
 *
 * Supports also "-" and "->" special tokens.
 */
Heron.widgets.ToolbarBuilder.build = function (mapPanel, config, toolbar) {
    var toolbarItems = [];
    if (typeof(config) !== "undefined") {
        for (var i = 0; i < config.length; i++) {
            var itemDef = config[i];

            // Check for separators (ExtJS convention to use "-")
            if (itemDef.type == "-") {
                toolbarItems.push("-");
                continue;
            }

            // Check for alignment modifiers (ExtJS convention to use "->")
            if (itemDef.type == "->") {
                toolbarItems.push("->");
                continue;
            }

            // Determine toolbar (Action) item cretae function
            // Default is from the above config, but a user can supply a function
            var createFun;
            var defaultItemDef = Heron.widgets.ToolbarBuilder.defs[itemDef.type];
            if (itemDef.create) {
                createFun = itemDef.create;
            } else if (defaultItemDef && defaultItemDef.create) {
                createFun = defaultItemDef.create;
            }

            // No use to proceed if no function to create item
            if (!createFun) {
                continue;
            }

            // Determine options
            var coreOptions = {
                // 1. Always needed but available here
                map: mapPanel.getMap(),
                scope: mapPanel
            };

            // 2. Default configured options
            var defaultItemOptions = {};
            if (defaultItemDef && defaultItemDef.options) {
                defaultItemOptions = defaultItemDef.options;
            }

            // 3. User supplied options
            var extraOptions = itemDef.options ? itemDef.options : {};

            // Assemble all options into a single struct
            var options = Ext.apply(coreOptions, extraOptions, defaultItemOptions);

            // Finally try to create the item if ok
            var item = createFun(mapPanel, options);
            if (item) {
                toolbarItems.push(item);
            }
        }
    }

    // Are there any items?
    if (toolbarItems.length > 0) {
        // Add created items to the toolbar
        toolbar.add(toolbarItems);
    } else {
        // Make toolbar invisible
        toolbar.setVisible(false);
    }
};

