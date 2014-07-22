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
        url = '/wms/' + db + '/' + schema,
        wfsUrl = '/wfs/' + db + '/' + schema;
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
        ],
        [
            "OpenLayers.Layer.Bing",
            {
                key: "Ar00ZDTFpjaza5W0AvQrJq8lEuSgevERqr6MjpIXJHoV2vKnusZh1ExhLX6DTKLK",
                type: "Aerial",
                name: "Bing Aerial",
                transitionEffect: 'resize'
            },
            {
                isBaseLayer: true
            }
        ],
        [
            "OpenLayers.Layer.Bing",
            {
                key: "Ar00ZDTFpjaza5W0AvQrJq8lEuSgevERqr6MjpIXJHoV2vKnusZh1ExhLX6DTKLK",
                type: "AerialWithLabels",
                name: "Bing Aerial With Labels",
                transitionEffect: 'resize'
            },
            {
                isBaseLayer: true
            }
        ],
        new OpenLayers.Layer.Image(
            "None",
            Ext.BLANK_IMAGE_URL,
            OpenLayers.Bounds.fromString(Heron.options.map.settings.maxExtent),
            new OpenLayers.Size(10, 10),
            {resolutions: [156543.033928, 78271.516964, 39135.758482, 19567.879241, 9783.9396205,
                4891.96981025, 2445.98490513, 1222.99245256, 611.496226281, 305.748113141, 152.87405657,
                76.4370282852, 38.2185141426, 19.1092570713, 9.55462853565, 4.77731426782, 2.38865713391,
                1.19432856696, 0.597164283478, 0.298582141739], isBaseLayer: true, visibility: false, displayInLayerSwitcher: true, transitionEffect: 'resize'}
        ),
        new OpenLayers.Layer.OSM("osm"),
        new OpenLayers.Layer.XYZ(
            "DigitalGlobe:Imagery",
            "https://services.digitalglobe.com/earthservice/wmtsaccess?CONNECTID=" + "cc512ac4-bbf1-4279-972d-b698b47a0e22" + "&Service=WMTS&REQUEST=GetTile&Version=1.0.0&Format=image/png&Layer=" + "DigitalGlobe:ImageryTileService" + "&TileMatrixSet=EPSG:3857&TileMatrix=EPSG:3857:${z}&TileRow=${y}&TileCol=${x}",
            {numZoomLevels: 20}
        )
    ];
    $.ajax({
        url: "/api/v1/meta/" + db + "/" + schema,
        contentType: "application/json; charset=utf-8",
        scriptCharset: "utf-8",
        async: false,
        dataType: 'json',
        success: function (response) {
            var groups = [], children = [], text, name, group, type, arr, lArr = [];
            $.each(response.data, function (i, v) {
                text = (v.f_table_title === null || v.f_table_title === "") ? v.f_table_name : v.f_table_title;
                name = v.f_table_schema + "." + v.f_table_name;
                group = v.layergroup;
                type = v.type;
                lArr.push({text: text, name: name, group: group, type: type});
                for (i = 0; i < response.data.length; i = i + 1) {
                    groups[i] = response.data[i].layergroup;

                }
                Heron.options.map.layers.push(
                    [
                        "OpenLayers.Layer.WMS",
                        name,
                        url,
                        {
                            layers: name,
                            format: 'image/png',
                            transparent: true
                        },
                        {
                            isBaseLayer: false,
                            title: (!v.bitmapsource) ? text : " ",
                            singleTile: true,
                            visibility: false,
                            transitionEffect: 'resize',
                            featureInfoFormat: 'application/vnd.ogc.gml',
                            metadata: {
                                wfs: {
                                    protocol: new OpenLayers.Protocol.WFS({
                                        version: "1.0.0",
                                        url: '/wfs/' + db + '/' + schema + '/3857?',
                                        srsName: "EPSG:3857",
                                        featureType: v.f_table_name,
                                        featureNS: "http://twitter/" + db
                                    })
                                }
                            }

                        }
                    ]
                );
                Heron.options.map.layers.push(
                    new OpenLayers.Layer.Vector(name + "_v", {
                        strategies: [new OpenLayers.Strategy.Fixed()],
                        visibility: false,
                        title: (!v.bitmapsource) ? text : " ",
                        protocol: new OpenLayers.Protocol.WFS({
                            version: "1.0.0",
                            url: '/wfs/' + db + '/' + schema + '/3857?',
                            srsName: "EPSG:3857",
                            featureType: v.f_table_name,
                            featureNS: "http://twitter/" + db
                        })
                    })

                );
            });
            arr = array_unique(groups);
            $.each(arr, function (u, m) {
                var g;
                g = {
                    text: m,
                    nodeType: 'hr_cascader',
                    children: []
                };
                $.each(lArr, function (i, v) {
                    if (m === v.group) {
                        if (v.type !== "RASTER") {
                            g.children.push(
                                {
                                    nodeType: "gx_layer",
                                    layer: v.name + "_v",
                                    text: v.text + " (WFS)",
                                    legend: false
                                }
                            );
                        }
                        g.children.push(
                            {
                                nodeType: "gx_layer",
                                layer: v.name,
                                text: v.text + "(WMS)",
                                legend: false
                            }
                        );

                    }
                });
                g.children.reverse();
                children.push(g);
            });
            children.reverse();
            Heron.options.layertree.tree = [
                {
                    text: 'BaseLayers',
                    expanded: true,
                    children: [
                        {
                            nodeType: "gx_layer",
                            layer: "Bing Road",
                            text: 'Bing Road'
                        },
                        {
                            nodeType: "gx_layer",
                            layer: "Bing Aerial",
                            text: 'Bing Aerial'
                        },
                        {
                            nodeType: "gx_layer",
                            layer: "Bing Aerial With Labels",
                            text: 'Bing Aerial With Labels'
                        },
                        {
                            nodeType: "gx_layer",
                            layer: "osm",
                            text: 'Osm'
                        },
                        {
                            nodeType: "gx_layer",
                            layer: "DigitalGlobe:Imagery",
                            text: 'DigitalGlobe:Imagery'
                        },
                        {
                            nodeType: "gx_layer",
                            layer: "None",
                            text: 'None'
                        }
                    ]
                }
            ].concat(children);
        }
    });
};
MapCentia.init = function () {
    "use strict";
    OpenLayers.Util.onImageLoadErrorColor = "transparent";
    OpenLayers.ProxyHost = "/cgi/proxy.cgi?url=";
    //OpenLayers.DOTS_PER_INCH = 25.4 / 0.28;

    Ext.BLANK_IMAGE_URL = 'http://cdnjs.cloudflare.com/ajax/libs/extjs/3.4.1-1/resources/images/default/s.gif';


    Heron.options.bookmarks = [];
    Heron.options.exportFormats = ['CSV', 'GMLv2', 'Shapefile',
        {
            name: 'Esri Shapefile (WGS84)',
            formatter: 'OpenLayersFormatter',
            format: 'OpenLayers.Format.GeoJSON',
            targetFormat: 'ESRI Shapefile',
            targetSrs: 'EPSG:4326',
            fileExt: '.zip',
            mimeType: 'application/zip'
        },
        'GeoJSON', 'WellKnownText'];

    Heron.options.wfs.downloadFormats = [
        {
            name: 'CSV',
            outputFormat: 'csv',
            fileExt: '.csv'
        },
        {
            name: 'GML (version 2.1.2)',
            outputFormat: 'text/xml; subtype=gml/2.1.2',
            fileExt: '.gml'
        },
        {
            name: 'ESRI Shapefile (zipped)',
            outputFormat: 'SHAPE-ZIP',
            fileExt: '.zip'
        },
        {
            name: 'GeoJSON',
            outputFormat: 'json',
            fileExt: '.json'
        }
    ];
    Heron.options.searchPanelConfig = {
        xtype: 'hr_multisearchcenterpanel',
        height: 600,
        hropts: [

            {
                searchPanel: {
                    xtype: 'hr_searchbydrawpanel',
                    name: __('Search by Drawing'),
                    header: false
                },
                resultPanel: {
                    xtype: 'hr_featuregridpanel',
                    id: 'hr-featuregridpanel',
                    header: false,
                    autoConfig: true,
                    autoConfigMaxSniff: 100,
                    exportFormats: Heron.options.exportFormats,
                    gridCellRenderers: Heron.options.gridCellRenderers,
                    hropts: {
                        zoomOnRowDoubleClick: true,
                        zoomOnFeatureSelect: false,
                        zoomLevelPointSelect: 8,
                        zoomToDataExtent: false
                    }
                }
            },
            {
                searchPanel: {
                    xtype: 'hr_searchbyfeaturepanel',
                    name: __('Search by Feature Selection'),
                    description: 'Select feature-geometries from one layer and use these to perform a spatial search in another layer.',
                    header: false,
                    border: false,
                    bodyStyle: 'padding: 6px',
                    style: {
                        fontFamily: 'Verdana, Arial, Helvetica, sans-serif',
                        fontSize: '12px'
                    }
                },
                resultPanel: {
                    xtype: 'hr_featuregridpanel',
                    id: 'hr-featuregridpanel',
                    header: false,
                    border: false,
                    autoConfig: true,
                    exportFormats: Heron.options.exportFormats,
                    gridCellRenderers: Heron.options.gridCellRenderers,
                    hropts: {
                        zoomOnRowDoubleClick: true,
                        zoomOnFeatureSelect: false,
                        zoomLevelPointSelect: 8,
                        zoomToDataExtent: false
                    }
                }
            },
            {
                searchPanel: {
                    xtype: 'hr_gxpquerypanel',
                    name: __('Build your own searches'),
                    description: 'This search uses both search within Map extent and/or your own attribute criteria',
                    header: false,
                    border: false,
                    caseInsensitiveMatch: true,
                    autoWildCardAttach: true
                },
                resultPanel: {
                    xtype: 'hr_featuregridpanel',
                    id: 'hr-featuregridpanel',
                    header: false,
                    border: false,
                    autoConfig: true,
                    exportFormats: ['XLS', 'GMLv2', 'GeoJSON', 'WellKnownText', 'Shapefile'],
                    gridCellRenderers: Heron.options.gridCellRenderers,
                    hropts: {
                        zoomOnRowDoubleClick: true,
                        zoomOnFeatureSelect: false,
                        zoomLevelPointSelect: 8,
                        zoomToDataExtent: true
                    }
                }
            }
        ]
    };
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
                        exportFormats: Heron.options.exportFormats,
                        maxFeatures: 10,
                        discardStylesForDups: true
                    }
                }
            }
        },
        {type: "-"},
        {type: "pan"},
        {type: "zoomin"},
        {type: "zoomout"},
        {type: "zoomvisible"},
        {type: "coordinatesearch", options: {onSearchCompleteZoom: 8}},
        {type: "-"},
        {type: "zoomprevious"},
        {type: "zoomnext"},
        {type: "-"},
    /** Use "geodesic: true" for non-linear/Mercator projections like Google, Bing etc */
        {type: "measurelength", options: {geodesic: true}},
        {type: "measurearea", options: {geodesic: true}},
        {type: "-"},
        {type: "addbookmark"},
        {type: "help", options: {tooltip: 'Help and info for this example', contentUrl: 'help.html'}},
        {type: "oleditor",
            options: {
                pressed: false,
                // Options for OLEditor
                olEditorOptions: {
                    activeControls: ['UploadFeature', 'DownloadFeature', 'Separator', 'Navigation', 'SnappingSettings', /*'CADTools',*/ 'Separator', 'DeleteAllFeatures', 'DeleteFeature', 'DragFeature', 'SelectFeature', 'Separator', 'DrawHole', 'ModifyFeature', 'Separator'],
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
                            {name: 'ESRI Shapefile (zipped, WGS84)', fileExt: '.zip', mimeType: 'application/zip', formatter: 'OpenLayers.Format.GeoJSON', targetFormat: 'ESRI Shapefile', fileProjection: new OpenLayers.Projection('EPSG:4326')}
                            //{name: 'OGC GeoPackage (Google projection)', fileExt: '.gpkg', mimeType: 'application/binary', formatter: 'OpenLayers.Format.GeoJSON', targetFormat: 'GPKG', fileProjection: new OpenLayers.Projection('EPSG:     ')},
                            //{name: 'OGC GeoPackage (WGS84)', fileExt: '.gpkg', mimeType: 'application/binary', formatter: 'OpenLayers.Format.GeoJSON', targetFormat: 'GPKG', fileProjection: new OpenLayers.Projection('EPSG:4326')}

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
                            {name: 'ESRI Shapefile (zipped, WGS84)', fileExt: '.zip', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON', fileProjection: new OpenLayers.Projection('EPSG:4326')}
                            //{name: 'OGC GeoPackage (Google projection)', fileExt: '.gpkg', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON', fileProjection: new OpenLayers.Projection('EPSG:900913')},
                            //{name: 'OGC GeoPackage (1 layer, WGS84)', fileExt: '.gpkg', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON', fileProjection: new OpenLayers.Projection('EPSG:4326')}

                        ],
                        fileProjection: new OpenLayers.Projection('EPSG:4326')
                    }
                }
            }},
        {type: "printdialog", options: {url: 'http://kademo.nl/print/pdf28992', windowWidth: 360
            // , showTitle: true
            // , mapTitle: 'My Header - Print Dialog'
            // , mapTitleYAML: "mapTitle"		// MapFish - field name in config.yaml - default is: 'mapTitle'
            // , showComment: true
            // , mapComment: 'My Comment - Print Dialog'
            // , mapCommentYAML: "mapComment"	// MapFish - field name in config.yaml - default is: 'mapComment'
            // , showFooter: true
            // , mapFooter: 'My Footer - Print Dialog'
            // , mapFooterYAML: "mapFooter"	    // MapFish - field name in config.yaml - default is: 'mapFooter'
            // , printAttribution: true         // Flag for printing the attribution
            // , mapAttribution: null           // Attribution text or null = visible layer attributions
            // , mapAttributionYAML: "mapAttribution" // MapFish - field name in config.yaml - default is: 'mapAttribution'
            , showOutputFormats: true
            // , showRotation: true
            // , showLegend: true
            // , showLegendChecked: true
            // , mapLimitScales: false
            , mapPreviewAutoHeight: true // Adapt height of preview map automatically, if false mapPreviewHeight is used.
            // , mapPreviewHeight: 400
        }},
        {
            type: "searchcenter",
            // Options for SearchPanel window
            options: {
                show: false,

                searchWindow: {
                    title: __('Multiple Searches'),
                    x: 100,
                    y: undefined,
                    width: 360,
                    height: 440,
                    items: [
                        Heron.options.searchPanelConfig
                    ]
                }
            }
        }
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
                    },
                    {
                        xtype: 'hr_bookmarkspanel',
                        id: 'hr-bookmarks',
                        border: true,
                        /** The map contexts to show links for in the BookmarksPanel. */
                        hropts: Heron.options.bookmarks
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
            },
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
            }
        ]
    };
};
MapCentia.setup();
MapCentia.init();


