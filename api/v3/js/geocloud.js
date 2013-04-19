var mygeocloud_host; // Global var
var mygeocloud_ol = (function () {
    "use strict";
    var scriptSource = (function (scripts) {
        "use strict";
        scripts = document.getElementsByTagName('script');
        var script = scripts[scripts.length - 1];
        if (script.getAttribute.length !== undefined) {
            return script.src;
        }
        return script.getAttribute('src', -1);
    })();
    // In IE7 host name is missing if script url is relative
    if (scriptSource.charAt(0) === "/") {
        mygeocloud_host = "";
    } else {
        mygeocloud_host = scriptSource.split("/")[0] + "//" + scriptSource.split("/")[2];
    }
    document.write("<script src='https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js'><\/script>");
    var map;
    var host = mygeocloud_host;
    var parentThis = this;
    var mapLib;
    mapLib = "ol";
    var geoJsonStore = function (config) {
        var prop, parentThis = this;
        var defaults = {
            db: null,
            sql: null,
            onLoad: function () {
            },
            styleMap: null,
            projection: "900913",
            strategies: null,
            visibility: true,
            rendererOptions: {
                zIndexing: true
            },
            lifetime: 0,
            movedEnd: function () {
            },
            selectControl: {}
        };
        if (config) {
            for (prop in config) {
                defaults[prop] = config[prop];
            }
        }
        this.db = defaults.db;
        this.sql = defaults.sql;
        this.onLoad = defaults.onLoad;
        this.movedEnd = defaults.movedEnd;
        // Map member for parent map obj. Set when store is added to a map
        this.map = null;
        // Layer Def
        switch (mapLib) {
            case "ol":
                this.layer = new OpenLayers.Layer.Vector("Vector", {
                    styleMap: defaults.styleMap,
                    visibility: defaults.visibility,
                    renderers: ['Canvas', 'SVG', 'VML'],
                    rendererOptions: defaults.rendererOptions,
                    strategies: [new OpenLayers.Strategy.AnimatedCluster({
                        //strategies : [new OpenLayers.Strategy.Cluster({
                        distance: 45,
                        animationMethod: OpenLayers.Easing.Expo.easeOut,
                        animationDuration: 10,
                        autoActivate: false
                    })]
                });
                break;
        }
        this.hide = function () {
            this.layer.setVisibility(false);
        };
        this.show = function () {
            this.layer.setVisibility(true);
        };

        this.clusterDeactivate = function () {
            parentThis.layer.strategies[0].deactivate();
            parentThis.layer.refresh({
                forces: true
            });
        };
        this.clusterActivate = function () {
            parentThis.layer.strategies[0].activate();
            parentThis.layer.refresh({
                forces: true
            });
        };

        //this.clusterDeactivate();
        this.pointControl = new OpenLayers.Control.DrawFeature(this.layer, OpenLayers.Handler.Point);
        this.lineControl = new OpenLayers.Control.DrawFeature(this.layer, OpenLayers.Handler.Path);
        this.polygonControl = new OpenLayers.Control.DrawFeature(this.layer, OpenLayers.Handler.Polygon);
        this.selectControl = new OpenLayers.Control.SelectFeature(this.layer, defaults.selectControl);
        this.selectControl.handlers.feature.stopDown = false;

        this.modifyControl = new OpenLayers.Control.ModifyFeature(this.layer, {});

        this.geoJSON = {};
        this.featureStore = null;
        this.load = function (doNotShowAlertOnError) {
            var sql = this.sql;
            try {
                var map = parentThis.map;
                sql = sql.replace("{centerX}", map.getCenter().lat.toString());
                sql = sql.replace("{centerY}", map.getCenter().lon.toString());
                sql = sql.replace("{minX}", map.getExtent().left);
                sql = sql.replace("{maxX}", map.getExtent().right);
                sql = sql.replace("{minY}", map.getExtent().bottom);
                sql = sql.replace("{maxY}", map.getExtent().top);
                sql = sql.replace("{bbox}", map.getExtent().toString());
            } catch (e) {
                //console.log(e.message);
            }
            //console.log(sql);
            $.ajax({
                dataType: 'jsonp',
                data: 'q=' + encodeURIComponent(sql) + '&srs=' + defaults.projection + '&lifetime=' + defaults.lifetime,
                jsonp: 'jsonp_callback',
                url: host + '/api/v1/sql/' + this.db,
                success: function (response) {
                    if (response.success === false && doNotShowAlertOnError === undefined) {
                        alert(response.message);
                    }
                    if (response.success === true) {
                        if (response.features !== null) {
                            parentThis.geoJSON = response;
                            parentThis.layer.addFeatures(new OpenLayers.Format.GeoJSON().read(response));
                            parentThis.featureStore = new GeoExt.data.FeatureStore({
                                fields: response.forStore,
                                layer: parentThis.layer
                            });
                        }
                    }
                },
                complete: function () {
                    parentThis.onLoad();
                }
            });
        };
        this.reset = function () {
            this.layer.destroyFeatures();
        };
        this.getWKT = function () {
            return new OpenLayers.Format.WKT().write(this.layer.features);
        };
    };
    map = function (config) {
        var prop, popup, // baseLayer wrapper
            parentMap, defaults = {
                db: null,
                numZoomLevels: 20,
                projection: "EPSG:900913"
            };
        if (config) {
            for (prop in config) {
                defaults[prop] = config[prop];
            }
        }
        parentMap = this;
        this.geoLocation = {
            x: null,
            y: null,
            obj: {}
        };
        this.zoomToExtent = function (extent, closest) {
            if (!extent) {
                this.map.getView().setCenter([0, 0]);
                this.map.getView().setResolution(39136);
            } else {
                this.map.zoomToExtent(new OpenLayers.Bounds(extent), closest);
            }
        };
        this.zoomToExtentOfgeoJsonStore = function (store) {
            this.map.zoomToExtent(store.layer.getDataExtent());
        };
        this.getVisibleLayers = function () {
            var layerArr = [];
            for (var i = 0; i < this.map.getLayers().getLength(); i++) {
                if (this.map.getLayers().a[i].t.visible === true && this.map.getLayers().a[i].id !== undefined && this.map.getLayers().a[i].baseLayer === undefined) {
                    layerArr.push(this.map.getLayers().a[i].id);
                }
            }
            return layerArr.join(";");
        };
        this.getNamesOfVisibleLayers = function () {
            var layerArr = [];
            for (var i = 0; i < this.map.getLayers().getLength(); i++) {
                if (this.map.getLayers().a[i].t.visible === true && this.map.getLayers().a[i].baseLayer !== true) {
                    layerArr.push(this.map.getLayers().a[i].id);
                }
            }
            return layerArr.join(",");
        };
        this.getBaseLayer = function () {
            return this.map.baseLayer;
        };
        this.getBaseLayerName = function () {
            var layers = this.map.getLayers();
            for (var i = 0; i < layers.getLength(); i++) {
                if (layers.a[i].t.visible === true && layers.a[i].baseLayer===true) {
                   return this.map.getLayers().a[i].id;
                }
            }
        };
        this.getZoom = function () {
            return this.map.getZoom();
        };
        //ol3
        this.getResolution = function () {
            return this.map.getView().getResolution();
        };
        this.getPixelCoord = function (x, y) {
            var p = {};
            p.x = this.map.getPixelFromLonLat(new OpenLayers.LonLat(x, y)).x;
            p.y = this.map.getPixelFromLonLat(new OpenLayers.LonLat(x, y)).y;
            return p;
        };
        this.zoomToPoint = function (x, y, r) {
            this.map.getView().setCenter([x, y]);
            if (r) this.map.getView().setResolution(r);
        };
        switch (mapLib) {
            case "ol3":
                this.popupTemplate = '<div style="position:relative"><div>tets</div><div id="queryResult"></div><button onclick="popup.destroy()" style="position:absolute; top:5px; right: 5px" type="button" class="close" aria-hidden="true">×</button></div>';
                this.clickController = OpenLayers.Class(OpenLayers.Control, {
                    defaultHandlerOptions: {
                        'single': true,
                        'double': false,
                        'pixelTolerance': 0,
                        'stopSingle': false,
                        'stopDouble': false
                    },
                    initialize: function (options) {
                        this.handlerOptions = OpenLayers.Util.extend({}, this.defaultHandlerOptions);
                        OpenLayers.Control.prototype.initialize.apply(this, arguments);
                        this.handler = new OpenLayers.Handler.Click(this, {
                            'click': this.trigger
                        }, this.handlerOptions);
                    },
                    trigger: function (e) {
                        var coords = this.map.getLonLatFromViewPortPx(e.xy);
                        var waitPopup = new OpenLayers.Popup("wait", coords, new OpenLayers.Size(36, 36), "<div style='z-index:1000;'><img src='assets/spinner/spinner.gif'></div>", null, true);
                        cloud.map.addPopup(waitPopup);
                        try {
                            popup.destroy();
                        } catch (e) {
                        }
                        var mapBounds = this.map.getExtent();
                        var boundsArr = mapBounds.toArray();
                        var boundsStr = boundsArr.join(",");
                        try {
                            popup.destroy();
                        } catch (e) {
                        }
                        var mapSize = this.map.getSize();
                        $.ajax({
                            dataType: 'jsonp',
                            data: 'proj=900913&lon=' + coords.lon + '&lat=' + coords.lat + '&layers=' + parentMap.getVisibleLayers() + '&extent=' + boundsStr + '&width=' + mapSize.w + '&height=' + mapSize.h,
                            jsonp: 'jsonp_callback',
                            url: host + '/apps/viewer/servers/query/' + defaults.db,
                            success: function (response) {
                                waitPopup.destroy();
                                var anchor = new OpenLayers.LonLat(coords.lon, coords.lat);
                                popup = new OpenLayers.Popup.Anchored("result", anchor, new OpenLayers.Size(200, 200), parentMap.popupTemplate, null, false, null);
                                popup.panMapIfOutOfView = true;
                                // Make popup global, so it can be accessed. Dirty hack!
                                window.popup = popup;
                                cloud.map.addPopup(popup);
                                if (response.html !== false) {
                                    document.getElementById("queryResult").innerHTML = response.html;
                                    //popup.relativePosition="tr";
                                    vectors.removeAllFeatures();
                                    _map.raiseLayer(vectors, 10);
                                    for (var i = 0; i < response.renderGeometryArray.length; ++i) {
                                        vectors.addFeatures(deserialize(response.renderGeometryArray[i][0]));
                                    }
                                } else {
                                    document.getElementById("queryResult").innerHTML = "Found nothing";
                                }
                            }
                        });
                    }
                });
                break;
        }
        switch (mapLib) {
            case "ol":
                this.map = new ol.Map({
                    target: defaults.el,
                    view: new ol.View2D({
                        zoom: 10

                    }),
                    renderers: ol.RendererHints.createFromQueryData()
                });

                //var vectors = new ol.layer.Vector();
                //this.map.addLayer(vectors);
                break;
            case "l":
                this.map = new L.map(defaults.el).setView([51.505, -0.09], 13);
                break;
        }
        /**
         *
         */
        var _map = this.map;
        /**
         * Creates a new Circle from a diameter.
         *
         * @return {Object} The new layer object.
         */
        this.addMapQuestOSM = function () {
            this.mapQuestOSM = new ol.layer.TileLayer({
                source: new ol.source.MapQuestOSM(),
                visible: false
            });
            this.mapQuestOSM.baseLayer = true;
            this.mapQuestOSM.id = "mapQuestOSM";
            this.map.addLayer(this.mapQuestOSM);
            return (this.mapQuestOSM);
        };
        this.addMapQuestAerial = function () {
            this.mapQuestAerial = new ol.layer.TileLayer({
                source: new ol.source.MapQuestOpenAerial(),
                visible: false
            });
            this.mapQuestAerial.baseLayer = true;
            this.mapQuestAerial.id = "mapQuestAerial";
            this.map.addLayer(this.mapQuestAerial);
            return (this.mapQuestAerial);
        };
        this.addOSM = function () {
            switch (mapLib) {
                case "ol":
                    this.osm = new ol.layer.TileLayer({
                        source: new ol.source.OSM(),
                        visible: false
                    });
                    this.map.addLayer(this.osm);
                    break;
                case "l":
                    this.osm = new L.tileLayer("http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png");
                    this.map.addLayer(this.osm);
                    break;
            }
            this.osm.baseLayer=true;
            this.osm.id="osm";
            return (this.osm);
        };
        this.addStamenToner = function () {
            this.stamenToner = new ol.layer.TileLayer({
                source: new ol.source.Stamen({
                    layer: 'toner'
                }),
                visible: false
            });
            this.stamenToner.baseLayer = true;
            this.stamenToner.id = "stamenToner";
            this.map.addLayer(this.stamenToner);
            return (this.stamenToner);
        };
        this.addGoogleStreets = function () {
            // v2
            try {
                this.baseGNORMAL = new OpenLayers.Layer.Google("googleStreets", {
                    type: G_NORMAL_MAP,
                    sphericalMercator: false,
                    wrapDateLine: true,
                    numZoomLevels: 20
                });

            } catch (e) {
            }
            ;
            // v3
            try {
                this.baseGNORMAL = new OpenLayers.Layer.Google("googleStreets", {// the default
                    wrapDateLine: false,
                    numZoomLevels: 20
                });
            } catch (e) {
            }
            //this.map.addLayer(this.baseGNORMAL);
            return (this.baseGNORMAL);
        };
        this.addGoogleHybrid = function () {
            // v2
            try {
                this.baseGHYBRID = new OpenLayers.Layer.Google("googleHybrid", {
                    type: G_HYBRID_MAP,
                    sphericalMercator: true,
                    wrapDateLine: true,
                    numZoomLevels: 20
                });
            } catch (e) {
            }

            // v3
            try {
                this.baseGHYBRID = new OpenLayers.Layer.Google("googleHybrid", {
                    type: google.maps.MapTypeId.HYBRID,
                    wrapDateLine: true,
                    numZoomLevels: 20
                });
            } catch (e) {
                //alert(e.message)
            }
            //this.map.addLayer(this.baseGHYBRID);
            return (this.baseGHYBRID);
        };
        this.addGoogleSatellite = function () {
            // v3
            try {
                this.baseGSATELLITE = new OpenLayers.Layer.Google("googleSatellite", {
                    type: google.maps.MapTypeId.SATELLITE,
                    wrapDateLine: true,
                    numZoomLevels: 20
                });
            } catch (e) {
                // alert(e.message)
            }
            //this.map.addLayer(this.baseGSATELLITE);
            return (this.baseGSATELLITE);
        };
        this.addGoogleTerrain = function () {
            // v3
            try {
                this.baseGTERRAIN = new OpenLayers.Layer.Google("GoogleTerrain", {
                    type: google.maps.MapTypeId.TERRAIN,
                    wrapDateLine: true,
                    numZoomLevels: 20
                });
            } catch (e) {
                // alert(e.message)
            }
            //this.map.addLayer(this.baseGTERRAIN);
            return (this.baseGTERRAIN);
        };
        this.addWmtsLayer = function (layerConfig) {
            var layer;
            layerConfig.options.tileSize = new OpenLayers.Size(256, 256);
            layerConfig.options.tileOrigin = new OpenLayers.LonLat(this.map.maxExtent.left, this.map.maxExtent.top);
            layerConfig.options.tileFullExtent = this.map.maxExtent.clone();
            for (var opts in layerConfig.options) {
                layerConfig[opts] = layerConfig.options[opts];
            }
            for (var par in layerConfig.params) {
                layerConfig[par] = layerConfig.params[par];
            }
            if (layerConfig["matrixIds"] != null) {
                if (layerConfig["matrixIds"].indexOf(',') > 0) {
                    layerConfig["matrixIds"] = layerConfig["matrixIds"].split(',');
                }
            }
            layer = new OpenLayers.Layer.WMTS(layerConfig);
            this.map.addLayer(layer);
            return layer;
        }
        this.setBaseLayer = function(baseLayerName) {
            var layers = this.map.getLayers();
            for (var i = 0; i < layers.getLength(); i++) {
                if (layers.a[i].baseLayer === true) {
                    layers.a[i].set("visible", false);
                }
            }
            this.getLayersByName(baseLayerName).set("visible", true);
        }
        this.addTileLayers = function (config) {
            var defaults = {
                layers: [],
                db: null,
                singleTile: false,
                opacity: 1,
                isBaseLayer: false,
                visibility: true,
                wrapDateLine: true,
                tileCached: true,
                displayInLayerSwitcher: true,
                name: null
            };
            if (config) {
                for (prop in config) {
                    defaults[prop] = config[prop];
                }
            }
            var layers = defaults.layers;
            var layersArr = [];
            for (var i = 0; i < layers.length; i++) {
                var l = this.createTileLayer(layers[i], defaults)
                this.map.addLayer(l);
                layersArr.push(l);
            }
            return layersArr;
        };
        this.createTileLayer = function (layer, defaults) {
            var parts;
            parts = layer.split(".");
            if (!defaults.tileCached) {
                var url = host + "/wms/" + defaults.db + "/" + parts[0] + "/?";
                var urlArray = [url];
            } else {
                var url = host + "/wms/" + defaults.db + "/" + parts[0] + "/tilecache/?";
                var url1 = url;
                var url2 = url;
                var url3 = url;
                var urlArray = [url1.replace("cdn", "cdn1"), url2.replace("cdn", "cdn2"), url3.replace("cdn", "cdn3")];
            }
            switch (mapLib) {
                case "ol":
                    var l = new ol.layer.TileLayer({
                        source: new ol.source.TiledWMS({
                            url: urlArray,
                            params: {LAYERS: layer}
                        }),
                        visible: defaults.visibility
                    });
                    l.id = layer;
                    break;
                case "l":
                    var l = new L.TileLayer.WMS(url, {
                        layers: layer,
                        format: 'image/png',
                        transparent: true
                    });
                    break;
            }
            return l;
        };
        this.addTileLayerGroup = function (layers, config) {
            var defaults = {
                singleTile: false,
                opacity: 1,
                isBaseLayer: false,
                visibility: true,
                //wrapDateLine : false,
                name: null,
                schema: null
            };
            if (config) {
                for (prop in config) {
                    defaults[prop] = config[prop];
                }
            }
            ;
            this.map.addLayer(this.createTileLayerGroup(layers, defaults));
        };
        this.createTileLayerGroup = function (layers, defaults) {
            var l = new OpenLayers.Layer.WMS(defaults.name, host + "/wms/" + defaults.db + "/" + defaults.schema + "/?", {
                layers: layers,
                transparent: true
            }, defaults);
            return l;
        };
        this.removeTileLayerByName = function (name) {
            var arr = this.map.getLayersByName(name);
            this.map.removeLayer(arr[0]);
        };
        this.addGeoJsonStore = function (store) {
            store.map = this.map;
            // set the parent map obj
            this.map.addLayers([store.layer]);
            this.map.addControl(store.pointControl);
            this.map.addControl(store.lineControl);
            this.map.addControl(store.polygonControl);
            this.map.addControl(store.selectControl);
            this.map.addControl(store.modifyControl);
            this.map.events.register("moveend", null, store.movedEnd);

        };
        this.addControl = function (control) {
            this.map.addControl(control);
            try {
                control.handlers.feature.stopDown = false;
            } catch (e) {
            }
            ;
            control.activate();
            return control;
        };
        this.removeGeoJsonStore = function (store) {
            this.map.removeLayer(store.layer);
            //??????????????
        };
        //ol3
        this.hideLayer = function (name) {
            this.getLayersByName(name).set("visible", false);

        };
        //ol3
        this.showLayer = function (name) {
            this.getLayersByName(name).set("visible", true);
        };
        this.getLayerById = function (id) {
            return this.map.getLayer(id);
        }
        //ol3
        this.getLayersByName = function (name) {
            for (var i = 0; i < this.map.getLayers().getLength(); i++) {
                if (this.map.getLayers().a[i].id === name) {
                    var l = this.map.getLayers().a[i];
                }
            }
            return l;
        }
        this.hideAllTileLayers = function () {
            for (var i = 0; i < this.map.layers.length; i++) {
                if (this.map.layers[i].isBaseLayer === false && this.map.layers[i].CLASS_NAME === "OpenLayers.Layer.WMS") {
                    this.map.layers[i].setVisibility(false);
                }
            }
        };
        // ol3
        this.getCenter = function () {
            var point = this.map.getView().getCenter();
            return {
                x: point[0],
                y: point[1]
            }
        };
        this.getExtent = function () {
            var mapBounds = this.map.getExtent();
            return mapBounds.toArray();
        };
        this.getBbox = function () {
            return this.map.getExtent().toString();
        };
        // Geolocation stuff starts here
        this.locate = function(){
            var center;
            var geolocation = new ol.Geolocation();
            geolocation.setTracking(true);
            geolocation.bindTo('projection', this.map.getView());
            var marker = new ol.Overlay({
                map: this.map,
                element: /** @type {Element} */ ($('<i/>').addClass('icon-flag').get(0))
            });
            // bind the marker position to the device location.
            marker.bindTo('position', geolocation);
            geolocation.addEventListener('accuracy_changed', function() {
                console.log(geolocation);
                center = ol.projection.transform([geolocation.a[0], geolocation.a[1]], 'EPSG:4326', 'EPSG:3857');
                this.zoomToPoint(center[0],center[1],1000);
                $(marker.getElement()).tooltip({
                    title: this.getAccuracy() + 'm from this point'
                });
            });
        }
    };

    var deserialize = function (element) {
        // console.log(element);
        var type = "wkt";
        var format = new OpenLayers.Format.WKT;
        return format.read(element);
    };
    var grid = function (el, store, config) {
        var prop;
        var defaults = {
            height: 300,
            selectControl: {
                onSelect: function (feature) {
                },
                onUnselect: function () {
                }
            },
            columns: store.geoJSON.forGrid
        };
        if (config) {
            for (prop in config) {
                defaults[prop] = config[prop];
            }
        }
        this.grid = new Ext.grid.GridPanel({
            id: "gridpanel",
            viewConfig: {
                forceFit: true
            },
            store: store.featureStore, // layer
            sm: new GeoExt.grid.FeatureSelectionModel({// Only when there is a map
                singleSelect: false,
                selectControl: defaults.selectControl
            }),
            cm: new Ext.grid.ColumnModel({
                defaults: {
                    sortable: true,
                    editor: {
                        xtype: "textfield"
                    }
                },
                columns: defaults.columns
            }),
            listeners: defaults.listeners
        });
        this.panel = new Ext.Panel({
            renderTo: el,
            split: true,
            frame: false,
            border: false,
            layout: 'fit',
            collapsible: false,
            collapsed: false,
            height: defaults.height,
            items: [this.grid]
        });
        this.grid.getSelectionModel().bind().handlers.feature.stopDown = false;
        this.selectionModel = this.grid.getSelectionModel().bind();
    };
    return {
        geoJsonStore: geoJsonStore,
        map: map,
        grid: grid,
        urlVars: (function getUrlVars() {
            var mapvars = {};
            var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function (m, key, value) {
                mapvars[key] = value;
            });
            return mapvars;
        })(),
        pathName: window.location.pathname.split("/"),
        urlHash: window.location.hash
    };
})
    ();
