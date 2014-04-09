/*global $:false */
/*global Ext:false */
/*global Heron:false */
/*global MapCentia:false */
/*global OpenLayers:false */
/*global ol:false */
/*global GeoExt:false */
/*global document:false */
/*global window:false */
Ext.namespace("MapCentia");
Ext.namespace("Heron.options");
Ext.namespace("Heron.options.map");
Ext.namespace("Heron.options.wfs");
Ext.namespace("Heron.options.center");
Ext.namespace("Heron.options.zoom");
Ext.namespace("Heron.options.layertree");
MapCentia.setup = function () {
    "use strict";
    Heron.globals.serviceUrl = '/cgi/heron.cgi';
    var uri = window.location.pathname.split("/"),
        db = uri[3],
        schema = uri[4],

        url = '/wms/' + db + '/tilecache/' + schema;
       // url = 'http://local2.mapcentia.com/wms/' + db + '/' + schema;
    Heron.options.map.layers = [
        [
            "OpenLayers.Layer.Bing",
            {
                key: "Ar00ZDTFpjaza5W0AvQrJq8lEuSgevERqr6MjpIXJHoV2vKnusZh1ExhLX6DTKLK",
                type: "Road",
                name: "Bing Road",
                transitionEffect: 'resize'
            },
            {
                isBaseLayer: true
            }
        ]
    ];
    $.ajax({
        url: "/api/v1/meta/" + db + "/" + schema,
        contentType: "application/json; charset=utf-8",
        scriptCharset: "utf-8",
        async: false,
        dataType: 'json',
        success: function (response) {
            var children = [], text, name;
            $.each(response.data, function (i, v) {
                text = (v.f_table_title === null || v.f_table_title === "") ? v.f_table_name : v.f_table_title;
                name = v.f_table_schema + "." + v.f_table_name;
                Heron.options.map.layers.push(
                    [
                        "OpenLayers.Layer.WMS", name, url,
                        {
                            layers: name,
                            format: 'image/png',
                            transparent: true
                        },
                        {
                            isBaseLayer: false,
                            singleTile: false,
                            visibility: false,
                            transitionEffect: 'resize',
                            featureInfoFormat: 'application/vnd.ogc.gml'
                        }
                    ]
                );
                children.push(
                    {
                        nodeType: "gx_layer",
                        layer: name,
                        text: text,
                        legend: true
                    }
                );
            });
            Heron.options.layertree.tree = [
                {
                    text: 'BaseLayers',
                    expanded: true,
                    children: [
                        {
                            nodeType: "gx_layer",
                            layer: "Bing Road",
                            text: 'Bing Road'
                        }
                    ]
                },
                {
                    text: 'Themes',
                    nodeType: 'hr_cascader',
                    children: children
                }
            ];
        }
    });
    $.ajax({
        url: '/api/v1/setting/' + db,
        async: false,
        dataType: 'json',
        success: function (response) {
            if (typeof response.data.extents === "object") {
                if (typeof response.data.center[schema] === "object") {
                    Heron.options.zoom = response.data.zoom[schema];
                    Heron.options.center = response.data.center[schema];
                }
            }
        }
    }); // Ajax call end
};
MapCentia.init = function () {
    "use strict";
    OpenLayers.Util.onImageLoadErrorColor = "transparent";
    //OpenLayers.DOTS_PER_INCH = 25.4 / 0.28;

    Ext.BLANK_IMAGE_URL = 'http://cdnjs.cloudflare.com/ajax/libs/extjs/3.4.1-1/resources/images/default/s.gif';

    Heron.options.map.settings = {
        projection: 'EPSG:900913',
        displayProjection: new OpenLayers.Projection("EPSG:4326"),
        units: 'm',
        maxExtent: '-20037508.34, -20037508.34, 20037508.34, 20037508.34',
        center: Heron.options.center,
        maxResolution: 'auto',
        //xy_precision: 5,
        zoom: Heron.options.zoom + 1, // Why?
        theme: null,
        permalinks: {
            /** The prefix to be used for parameters, e.g. map_x, default is 'map' */
            paramPrefix: 'map',
            /** Encodes values of permalink parameters ? default false*/
            encodeType: false,
            /** Use Layer names i.s.o. OpenLayers-generated Layer Id's in Permalinks */
            prettyLayerNames: true
        }
    };

    Heron.options.wfs.downloadFormats = [
        {
            name: 'CSV',
            outputFormat: 'csv',
            fileExt: '.csv'
        }
//    {
//        name: 'GML (version 2.1.2)',
//        outputFormat: 'text/xml; subtype=gml/2.1.2',
//        fileExt: '.gml'
//    },
//    {
//        name: 'ESRI Shapefile (zipped)',
//        outputFormat: 'SHAPE-ZIP',
//        fileExt: '.zip'
//    },
//    {
//        name: 'GeoJSON',
//        outputFormat: 'json',
//        fileExt: '.json'
//    }
    ];

    Heron.options.map.toolbar = [
        {
            type: "featureinfo",
            options: {
                popupWindow: {
                    width: 360,
                    height: 200,
                    featureInfoPanel: {
                        showTopToolbar: true,
                        displayPanels: ['Table'],
                        // Should column-names be capitalized? Default true.
                        columnCapitalize: true,
                        hideColumns: ['objectid', 'gid'],

                        // Export to download file. Option values are 'CSV', 'XLS', or a Formatter object (see FeaturePanel) , default is no export (results in no export menu).
                        exportFormats: ['CSV', 'XLS', 'GMLv2', 'Shapefile',
                            {
                                name: 'Esri Shapefile (WGS84)',
                                formatter: 'OpenLayersFormatter',
                                format: 'OpenLayers.Format.GeoJSON',
                                targetFormat: 'ESRI Shapefile',
                                targetSrs: 'EPSG:4326',
                                fileExt: '.zip',
                                mimeType: 'application/zip'
                            },
                            {
                                // Try this with PDOK Streekpaden and Fietsroutes :-)
                                name: 'GPS File (GPX)',
                                formatter: 'OpenLayersFormatter',
                                format: 'OpenLayers.Format.GeoJSON',
                                targetSrs: 'EPSG:4326',
                                targetFormat: 'GPX',
                                fileExt: '.gpx',
                                mimeType: 'text/plain'
                            },
                            {
                                name: 'OGC GeoPackage (EPSG:28992)',
                                formatter: 'OpenLayersFormatter',
                                format: 'OpenLayers.Format.GeoJSON',
                                targetFormat: 'GPKG',
                                fileExt: '.gpkg',
                                mimeType: 'application/binary'
                            },
                            {
                                name: 'OGC GeoPackage (WGS84)',
                                formatter: 'OpenLayersFormatter',
                                format: 'OpenLayers.Format.GeoJSON',
                                targetFormat: 'GPKG',
                                targetSrs: 'EPSG:4326',
                                fileExt: '.gpkg',
                                mimeType: 'application/binary'
                            },
                            'GeoJSON', 'WellKnownText'],
// Export to download file. Option values are 'CSV', 'XLS', default is no export (results in no export menu).
// exportFormats: ['CSV', 'XLS'],
                        maxFeatures: 10,

// In case that the same layer would be requested more than once: discard the styles
                        discardStylesForDups: true
                    }
                }
            }
        },
        {type: "-"},
        {type: "pan"},
//    {type: "pan", options: {iconCls: "icon-hand"}},
        {type: "zoomin"},
        {type: "zoomout"},
        {type: "zoomvisible"},
        {type: "coordinatesearch", options: {onSearchCompleteZoom: 8}},
        {type: "-"},
        {type: "zoomprevious"},
        {type: "zoomnext"},
        {type: "-"},
    /** Use "geodesic: true" for non-linear/Mercator projections like Google, Bing etc */
        {type: "measurelength", options: {geodesic: false}},
        {type: "measurearea", options: {geodesic: false}},
        {type: "-"},
        {type: "addbookmark"},
        {type: "help", options: {tooltip: 'Help and info for this example', contentUrl: 'help.html'}},
        {type: "oleditor",
            options: {
                pressed: false,
                // Options for OLEditor
                olEditorOptions: {
                    activeControls: ['UploadFeature', 'DownloadFeature', 'Separator', 'Navigation', 'SnappingSettings', 'CADTools', 'Separator', 'DeleteAllFeatures', 'DeleteFeature', 'DragFeature', 'SelectFeature', 'Separator', 'DrawHole', 'ModifyFeature', 'Separator'],
                    featureTypes: ['text', 'regular', 'polygon', 'path', 'point'],
                    language: 'en',
                    DownloadFeature: {
                        url: Heron.globals.serviceUrl,
                        formats: [
                            {name: 'Well-Known-Text (WKT)', fileExt: '.wkt', mimeType: 'text/plain', formatter: 'OpenLayers.Format.WKT'},
                            {name: 'Geographic Markup Language - v2 (GML2)', fileExt: '.gml', mimeType: 'text/xml', formatter: new OpenLayers.Format.GML.v2({featureType: 'oledit', featureNS: 'http://geops.de'})},
                            {name: 'GeoJSON', fileExt: '.json', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON'},
                            {name: 'GPS Exchange Format (GPX)', fileExt: '.gpx', mimeType: 'text/xml', formatter: 'OpenLayers.Format.GPX', fileProjection: new OpenLayers.Projection('EPSG:4326')},
                            {name: 'Keyhole Markup Language (KML)', fileExt: '.kml', mimeType: 'text/xml', formatter: 'OpenLayers.Format.KML', fileProjection: new OpenLayers.Projection('EPSG:4326')},
                            {name: 'ESRI Shapefile (zipped, Google projection)', fileExt: '.zip', mimeType: 'application/zip', formatter: 'OpenLayers.Format.GeoJSON', targetFormat: 'ESRI Shapefile', fileProjection: new OpenLayers.Projection('EPSG:900913')},
                            {name: 'ESRI Shapefile (zipped, WGS84)', fileExt: '.zip', mimeType: 'application/zip', formatter: 'OpenLayers.Format.GeoJSON', targetFormat: 'ESRI Shapefile', fileProjection: new OpenLayers.Projection('EPSG:4326')},
                            {name: 'OGC GeoPackage (Google projection)', fileExt: '.gpkg', mimeType: 'application/binary', formatter: 'OpenLayers.Format.GeoJSON', targetFormat: 'GPKG', fileProjection: new OpenLayers.Projection('EPSG:900913')},
                            {name: 'OGC GeoPackage (WGS84)', fileExt: '.gpkg', mimeType: 'application/binary', formatter: 'OpenLayers.Format.GeoJSON', targetFormat: 'GPKG', fileProjection: new OpenLayers.Projection('EPSG:4326')}

                        ],
                        fileProjection: new OpenLayers.Projection('EPSG:4326')
                    },
                    UploadFeature: {
                        url: Heron.globals.serviceUrl,
                        formats: [
                            {name: 'Well-Known-Text (WKT)', fileExt: '.wkt', mimeType: 'text/plain', formatter: 'OpenLayers.Format.WKT'},
                            {name: 'Geographic Markup Language - v2 (GML2)', fileExt: '.gml', mimeType: 'text/xml', formatter: 'OpenLayers.Format.GML'},
                            {name: 'GeoJSON', fileExt: '.json', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON'},
                            {name: 'GPS Exchange Format (GPX)', fileExt: '.gpx', mimeType: 'text/xml', formatter: 'OpenLayers.Format.GPX', fileProjection: new OpenLayers.Projection('EPSG:4326')},
                            {name: 'Keyhole Markup Language (KML)', fileExt: '.kml', mimeType: 'text/xml', formatter: 'OpenLayers.Format.KML', fileProjection: new OpenLayers.Projection('EPSG:4326')},
                            {name: 'CSV (with X,Y in WGS84)', fileExt: '.csv', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON', fileProjection: new OpenLayers.Projection('EPSG:4326')},
                            {name: 'ESRI Shapefile (zipped, Google projection)', fileExt: '.zip', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON', fileProjection: new OpenLayers.Projection('EPSG:900913')},
                            {name: 'ESRI Shapefile (zipped, WGS84)', fileExt: '.zip', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON', fileProjection: new OpenLayers.Projection('EPSG:4326')},
                            {name: 'OGC GeoPackage (Google projection)', fileExt: '.gpkg', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON', fileProjection: new OpenLayers.Projection('EPSG:900913')},
                            {name: 'OGC GeoPackage (1 layer, WGS84)', fileExt: '.gpkg', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON', fileProjection: new OpenLayers.Projection('EPSG:4326')}

                        ],
                        fileProjection: new OpenLayers.Projection('EPSG:4326')
                    }
                }
            }}
    ];

    Heron.layout = {
        xtype: 'panel',
        id: 'hr-container-main',
        layout: 'border',
        border: false,

        /** Any classes in "items" and nested items are automatically instantiated (via "xtype") and added by ExtJS. */
        items: [
            {
                xtype: 'panel',
                id: 'hr-menu-left-container',
                layout: 'accordion',
                region: "west",
                width: 240,
                collapsible: true,
                border: false,
                items: [
                    {
                        xtype: 'hr_layertreepanel',
                        border: true,
                        layerIcons: 'bylayertype',
                        contextMenu: [
                            {
                                xtype: 'hr_layernodemenulayerinfo'
                            },
                            {
                                xtype: 'hr_layernodemenuzoomextent'
                            },
                            {
                                xtype: 'hr_layernodemenustyle'
                            },
                            {
                                xtype: 'hr_layernodemenuopacityslider'
                            }
                        ],
                        hropts: Heron.options.layertree
                    }
                ]
            },
            {
                xtype: 'panel',
                id: 'hr-map-and-info-container',
                layout: 'border',
                region: 'center',
                width: '100%',
                collapsible: false,
                split: false,
                border: false,
                items: [
                    {
                        xtype: 'hr_mappanel',
                        id: 'hr-map',
                        title: '&nbsp;',
                        region: 'center',
                        collapsible: false,
                        border: false,
                        hropts: Heron.options.map
                    }
                ]
            }/*,
             {
             xtype: 'panel',
             id: 'hr-menu-right-container',
             layout: 'accordion',
             region: "east",
             width: 240,
             collapsible: true,
             split: false,
             border: false,
             items: [
             {
             xtype: 'hr_layerlegendpanel',
             id: 'hr-layerlegend-panel',
             border: true,
             defaults: {
             useScaleParameter: true,
             baseParams: {
             FORMAT: 'image/png'
             }
             },
             hropts: {
             // Preload Legends on initial startup
             // Will fire WMS GetLegendGraphic's for WMS Legends
             // Otherwise Legends will be loaded only when Layer
             // becomes visible. Default: false
             prefetchLegends: false
             }
             }
             ]
             }*/
        ]
    };
};
MapCentia.setup();
MapCentia.init();


