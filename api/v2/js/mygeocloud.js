var mygeocloud_host;
var mygeocloud_ol = (function() {"use strict";
    var scriptSource = ( function(scripts) {"use strict";
            scripts = document.getElementsByTagName('script');
            var script = scripts[scripts.length - 1];
            if (script.getAttribute.length !== undefined) {
                return script.src;
            }
            return script.getAttribute('src', -1);
        }());
    // In IE7 host name is missing if script url is relative
    if (scriptSource.charAt(0) === "/") {
        mygeocloud_host = "";
    } else {
        mygeocloud_host = scriptSource.split("/")[0] + "//" + scriptSource.split("/")[2];
    }
    document.write("<script src='https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js'><\/script>");
    document.write("<script src='" + mygeocloud_host + "/js/openlayers/AnimatedCluster.js'><\/script>");
    document.write("<script src='" + mygeocloud_host + "/js/ext/adapter/ext/ext-base.js'><\/script>");
    document.write("<script src='" + mygeocloud_host + "/js/ext/ext-all.js'><\/script>");
    document.write("<script src='" + mygeocloud_host + "/js/GeoExt/script/GeoExt.js'><\/script>");

    var map;
    var host = mygeocloud_host;
    var parentThis = this;
    var mapLib;
    if ( typeof (OpenLayers) === "object" && typeof (L) === "object") {
        alert("You can\'t use both OpenLayer and Leaflet on the same page. You have to decide?");
    }
    if ( typeof (OpenLayers) !== "object" && typeof (L) !== "object") {
        alert("You need to load neither OpenLayer.js or Leaflet.js");
    }
    if ( typeof (OpenLayers) === "object") {
        mapLib = "ol";
    }
    if ( typeof (L) === "object") {
        mapLib = "l";
    }
    var geoJsonStore = function(config) {
        var prop, parentThis = this;
        var defaults = {
            db : null,
            sql : null,
            onLoad : function() {
            },
            styleMap : null,
            projection : "900913",
            strategies : null,
            visibility : true,
            rendererOptions : {
                zIndexing : true
            },
            lifetime : 0,
            movedEnd : function() {
            },
            selectControl : {}
        };
        if (config) {
            for (prop in config) {
                defaults[prop] = config[prop];
            }
        };
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
                    styleMap : defaults.styleMap,
                    visibility : defaults.visibility,
                    renderers : ['Canvas', 'SVG', 'VML'],
                    rendererOptions : defaults.rendererOptions,
                    strategies : [new OpenLayers.Strategy.AnimatedCluster({
                        //strategies : [new OpenLayers.Strategy.Cluster({
                        distance : 45,
                        animationMethod : OpenLayers.Easing.Expo.easeOut,
                        animationDuration : 10,
                        autoActivate : false
                    })]
                });
                break;
        }
        this.hide = function() {
            this.layer.setVisibility(false);
        };
        this.show = function() {
            this.layer.setVisibility(true);
        };

        /*
         this.layer.strategies[0].activate = function() {
         var activated = OpenLayers.Strategy.prototype.activate.call(this);
         if (activated) {
         var features = [];
         var clusters = this.layer.features;
         for (var i = 0; i < clusters.length; i++) {
         var cluster = clusters[i];
         if (cluster.cluster) {
         for (var j = 0; j < cluster.cluster.length; j++) {
         features.push(cluster.cluster[j]);
         }
         } else {
         features.push(cluster);
         }
         }
         this.layer.removeAllFeatures();
         this.layer.events.on({
         "beforefeaturesadded" : this.cacheFeatures,
         "moveend" : this.cluster,
         scope : this
         });
         this.layer.addFeatures(features);
         this.clearCache();
         }
         return activated;
         }

         this.layer.strategies[0].deactivate = function() {
         var deactivated = OpenLayers.Strategy.prototype.deactivate.call(this);
         if (deactivated) {
         var features = [];
         var clusters = this.layer.features;
         for (var i = 0; i < clusters.length; i++) {
         var cluster = clusters[i];
         if (cluster.cluster) {
         for (var j = 0; j < cluster.cluster.length; j++) {
         features.push(cluster.cluster[j]);
         }
         } else {
         features.push(cluster);
         }
         }
         this.layer.removeAllFeatures();
         this.layer.events.un({
         "beforefeaturesadded" : this.cacheFeatures,
         "moveend" : this.cluster,
         scope : this
         });
         this.layer.addFeatures(features);
         this.clearCache();
         }
         return deactivated;
         };
         */
        this.clusterDeactivate = function() {
            parentThis.layer.strategies[0].deactivate();
            parentThis.layer.refresh({
                forces : true
            });
        };
        this.clusterActivate = function() {
            parentThis.layer.strategies[0].activate();
            parentThis.layer.refresh({
                forces : true
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
        this.load = function(doNotShowAlertOnError) {
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
            } catch(e) {
                //console.log(e.message);
            }
            //console.log(sql);
            $.ajax({
                dataType : 'jsonp',
                data : 'q=' + encodeURIComponent(sql) + '&srs=' + defaults.projection + '&lifetime=' + defaults.lifetime,
                jsonp : 'jsonp_callback',
                url : host + '/api/v1/sql/' + this.db,
                success : function(response) {
                    if (response.success === false && doNotShowAlertOnError === undefined) {
                        alert(response.message);
                    }
                    if (response.success === true) {
                        if (response.features !== null) {
                            parentThis.geoJSON = response;
                            parentThis.layer.addFeatures(new OpenLayers.Format.GeoJSON().read(response));
                            parentThis.featureStore = new GeoExt.data.FeatureStore({
                                fields : response.forStore,
                                layer : parentThis.layer
                            });
                        }
                    }
                },
                complete : function() {
                    parentThis.onLoad();
                }
            });
        };
        this.reset = function() {
            this.layer.destroyFeatures();
        };
        this.getWKT = function() {
            return new OpenLayers.Format.WKT().write(this.layer.features);
        };
    };
    map = function(config) {
        var prop, baseLayer, popup, // baseLayer wrapper
        parentMap, defaults = {
            numZoomLevels : 20,
            projection : "EPSG:900913",
            //maxExtent : new OpenLayers.Bounds(-20037508.34, -20037508.34, 20037508.34, 20037508.34)
        };
        if (config) {
            for (prop in config) {
                defaults[prop] = config[prop];
            }
        }
        parentMap = this;
        this.layerStr = "";
        this.geoLocation = {
            x : null,
            y : null,
            obj : {}
        };
        this.zoomToExtent = function(extent, closest) {
            if (!extent) {
                this.map.zoomToExtent(this.map.maxExtent);
            } else {
                this.map.zoomToExtent(new OpenLayers.Bounds(extent), closest);
            }
        };
        this.zoomToExtentOfgeoJsonStore = function(store) {
            this.map.zoomToExtent(store.layer.getDataExtent());
        };
        this.getVisibleLayers = function() {
            var layerArr = [];
            //console.log(this.map.layers);
            for (var i = 0; i < this.map.layers.length; i++) {
                if (this.map.layers[i].isBaseLayer === false && this.map.layers[i].visibility === true && this.map.layers[i].CLASS_NAME === "OpenLayers.Layer.WMS") {
                    layerArr.push(this.map.layers[i].params.LAYERS);
                    //console.log(this.map.layers[i]);

                }
            }
            //console.log(layerArr);
            return layerArr.join(";");
        }
        this.getZoom = function() {
            return this.getZoom();
        }
        this.getPixelCoord = function(x, y) {
            var p = {};
            p.x = this.map.getPixelFromLonLat(new OpenLayers.LonLat(x, y)).x;
            p.y = this.map.getPixelFromLonLat(new OpenLayers.LonLat(x, y)).y;
            return p;
        }
        this.zoomToPoint = function(x, y, z) {
            this.map.setCenter(new OpenLayers.LonLat(x, y), z);
        }
        switch (mapLib) {
            case "ol":
                this.clickController = OpenLayers.Class(OpenLayers.Control, {
                    defaultHandlerOptions : {
                        'single' : true,
                        'double' : false,
                        'pixelTolerance' : 0,
                        'stopSingle' : false,
                        'stopDouble' : false
                    },
                    initialize : function(options) {
                        this.handlerOptions = OpenLayers.Util.extend({}, this.defaultHandlerOptions);
                        OpenLayers.Control.prototype.initialize.apply(this, arguments);
                        this.handler = new OpenLayers.Handler.Click(this, {
                            'click' : this.trigger
                        }, this.handlerOptions);
                    },
                    trigger : function(e) {
                        var mapBounds = this.map.getExtent();
                        var boundsArr = mapBounds.toArray();
                        var boundsStr = boundsArr.join(",");
                        var coords = this.map.getLonLatFromViewPortPx(e.xy);
                        //console.log(this.map.layers);
                        try {
                            popup.destroy();
                        } catch (e) {
                        }
                        ;
                        popup = new OpenLayers.Popup.FramedCloud("result", coords, null, "<div id='queryResult' style='z-index:1000;width:300px;height:100px;overflow:auto'>Wait..</div>", null, true);
                        this.map.addPopup(popup);
                        var mapSize = this.map.getSize();
                        $.ajax({
                            dataType : 'jsonp',
                            data : 'proj=900913&lon=' + coords.lon + '&lat=' + coords.lat + '&layers=' + parentMap.getVisibleLayers() + '&extent=' + boundsStr + '&width=' + mapSize.w + '&height=' + mapSize.h,
                            jsonp : 'jsonp_callback',
                            url : host + '/apps/viewer/servers/query/' + this.db,
                            success : function(response) {
                                if (response.html != false) {
                                    document.getElementById("queryResult").innerHTML = response.html;
                                    var resultHtml = response.html;
                                } else {
                                    document.getElementById("queryResult").innerHTML = "Found nothing";
                                }
                                vectors.removeAllFeatures();
                                _map.raiseLayer(vectors, 10);
                                for (var i = 0; i < response.renderGeometryArray.length; ++i) {
                                    vectors.addFeatures(deserialize(response.renderGeometryArray[i][0]));

                                }
                            }
                        });
                    }
                });
                break;
        }
        switch (mapLib) {
            case "ol":
                this.map = new OpenLayers.Map(defaults.el, {
                    //theme: null,
                    controls : [//new OpenLayers.Control.Navigation(),
                    //new OpenLayers.Control.PanZoomBar(),
                    //new OpenLayers.Control.LayerSwitcher(),
                    new OpenLayers.Control.Zoom(),
                    //new OpenLayers.Control.PanZoom(),
                    new OpenLayers.Control.TouchNavigation({
                        dragPanOptions : {
                            enableKinetic : true
                        }
                    })],
                    numZoomLevels : defaults.numZoomLevels,
                    projection : defaults.projection,
                    maxResolution : defaults.maxResolution,
                    minResolution : defaults.minResolution,
                    maxExtent : defaults.maxExtent,
                    eventListeners : defaults.eventListeners
                    //units : "m"
                });
                this.click = new this.clickController();
                this.map.addControl(this.click);
                var vectors = new OpenLayers.Layer.Vector("Mark", {
                    displayInLayerSwitcher : false
                });
                this.map.addLayers([vectors]);
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
        this.addMapQuestOSM = function() {
            switch (mapLib) {
                case "ol":
                    this.mapQuestOSM = new OpenLayers.Layer.OSM("MapQuest-OSM", ["http://otile1.mqcdn.com/tiles/1.0.0/osm/${z}/${x}/${y}.jpg", "http://otile2.mqcdn.com/tiles/1.0.0/osm/${z}/${x}/${y}.jpg", "http://otile3.mqcdn.com/tiles/1.0.0/osm/${z}/${x}/${y}.jpg", "http://otile4.mqcdn.com/tiles/1.0.0/osm/${z}/${x}/${y}.jpg"]);
                    //this.mapQuestOSM.wrapDateLine = false;
                    this.map.addLayer(this.mapQuestOSM);
                    break;
                case "l":
                    this.mapQuestOSM = new L.tileLayer('http://otile1.mqcdn.com/tiles/1.0.0/osm/{z}/{x}/{y}.jpg');
                    this.map.addLayer(this.mapQuestOSM);
                    break;
            }
            return (this.mapQuestOSM);
        }
        this.addMapQuestAerial = function() {
            switch (mapLib) {
                case "ol":
                    this.mapQuestAerial = new OpenLayers.Layer.OSM("MapQuest Open Aerial Tiles", ["http://oatile1.mqcdn.com/tiles/1.0.0/sat/${z}/${x}/${y}.jpg", "http://oatile2.mqcdn.com/tiles/1.0.0/sat/${z}/${x}/${y}.jpg", "http://oatile3.mqcdn.com/tiles/1.0.0/sat/${z}/${x}/${y}.jpg", "http://oatile4.mqcdn.com/tiles/1.0.0/sat/${z}/${x}/${y}.jpg"]);
                    this.mapQuestAerial.wrapDateLine = false;
                    this.map.addLayer(this.mapQuestAerial);
                    break;
                case "l":
                    this.mapQuestOSM = new L.tileLayer("http://oatile1.mqcdn.com/tiles/1.0.0/sat/${z}/${x}/${y}.jpg");
                    this.map.addLayer(this.mapQuestOSM);
                    break;
                case "l":

            }
            return (this.mapQuestAerial);
        }
        this.addOSM = function() {
            switch (mapLib) {
                case "ol":
                    this.baseOSM = new OpenLayers.Layer.OSM("OSM");
                    this.baseOSM.wrapDateLine = false;
                    this.map.addLayer(this.baseOSM);
                    break;
                case "l":
                    this.mapQuestOSM = new L.tileLayer("http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png");
                    this.map.addLayer(this.mapQuestOSM);
                    break;
            }
            return (this.baseOSM);
        }
        this.addGoogleStreets = function() {
            // v2
            try {
                this.baseGNORMAL = new OpenLayers.Layer.Google("Google Streets", {
                    type : G_NORMAL_MAP,
                    sphericalMercator : false,
                    wrapDateLine : true,
                    numZoomLevels : 20
                });

            } catch(e) {
            };
            // v3
            try {
                this.baseGNORMAL = new OpenLayers.Layer.Google("Google Streets", {// the default
                    wrapDateLine : false,
                    numZoomLevels : 20
                });
            } catch(e) {
            }
            this.map.addLayer(this.baseGNORMAL);
            return (this.baseGNORMAL);
        }
        this.addGoogleHybrid = function() {
            // v2
            try {
                this.baseGHYBRID = new OpenLayers.Layer.Google("Google Hybrid", {
                    type : G_HYBRID_MAP,
                    sphericalMercator : true,
                    wrapDateLine : true,
                    numZoomLevels : 20
                });
            } catch(e) {
            };
            // v3
            try {
                this.baseGHYBRID = new OpenLayers.Layer.Google("Google Hybrid", {
                    type : google.maps.MapTypeId.HYBRID,
                    wrapDateLine : true,
                    numZoomLevels : 20
                });
            } catch(e) {
                alert(e.message)
            }
            this.map.addLayer(this.baseGHYBRID);
            return (this.baseGHYBRID);
        }
        this.addWmtsLayer = function(layerConfig) {
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
        //this.addMapQuestOSM();
        this.setBaseLayer = function(baseLayer) {
            switch (mapLib) {
                case "ol":
                    this.map.setBaseLayer(baseLayer);
                    break
                case "l":
                    break;
            }
        }
        this.addTileLayers = function(config) {
            var defaults = {
                layers : [],
                db : null,
                singleTile : false,
                opacity : 1,
                isBaseLayer : false,
                visibility : true,
                wrapDateLine : true,
                tileCached : true,
                displayInLayerSwitcher : true,
                name : null
            };
            if (config) {
                for (prop in config) {
                    defaults[prop] = config[prop];
                }
            };
            var layers = defaults.layers;
            var layersArr = [];
            for (var i = 0; i < layers.length; i++) {
                var l = this.createTileLayer(layers[i], defaults)
                this.map.addLayer(l);
                layersArr.push(l);
            }
            return layersArr;
        };
        this.createTileLayer = function(layer, defaults) {
            var parts = [];
            parts = layer.split(".");
            if (!defaults.tileCached) {
                var url = host + "/wms/" + defaults.db + "/" + parts[0] + "/?";
            } else {
                var url = host + "/wms/" + defaults.db + "/" + parts[0] + "/tilecache/?";
            }

            switch (mapLib) {
                case "ol":
                    var l = new OpenLayers.Layer.WMS(defaults.name, url, {
                        layers : layer,
                        transparent : true
                    }, defaults);
                    l.id = layer;
                    break;
                case "l":
                    var l = new L.TileLayer.WMS(url, {
                        layers : layer,
                        format : 'image/png',
                        transparent : true
                    });
                    break;
            }
            return l;
        };
        this.addTileLayerGroup = function(layers, config) {
            var defaults = {
                singleTile : false,
                opacity : 1,
                isBaseLayer : false,
                visibility : true,
                //wrapDateLine : false,
                name : null,
                schema : null
            };
            if (config) {
                for (prop in config) {
                    defaults[prop] = config[prop];
                }
            };
            this.map.addLayer(this.createTileLayerGroup(layers, defaults));
        };
        this.createTileLayerGroup = function(layers, defaults) {
            var l = new OpenLayers.Layer.WMS(defaults.name, host + "/wms/" + defaults.db + "/" + defaults.schema + "/?", {
                layers : layers,
                transparent : true
            }, defaults);
            return l;
        };
        this.removeTileLayerByName = function(name) {
            var arr = this.map.getLayersByName(name);
            this.map.removeLayer(arr[0]);
        };
        this.addGeoJsonStore = function(store) {
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
        this.addControl = function(control) {
            this.map.addControl(control);
            try {
                control.handlers.feature.stopDown = false;
            } catch(e) {
            };
            control.activate();
            return control;
        };
        this.removeGeoJsonStore = function(store) {
            this.map.removeLayer(store.layer);
            //??????????????
        };
        this.hideLayer = function(name) {
            this.map.getLayersByName(name)[0].setVisibility(false);

        }
        this.showLayer = function(name) {
            this.map.getLayersByName(name)[0].setVisibility(true);
        }
        this.hideAllTileLayers = function(arr) {
            for (var i = 0; i < this.map.layers.length; i++) {
                if ($.inArray(this.map.layers[i].name,arr)===false && this.map.layers[i].isBaseLayer === false && this.map.layers[i].CLASS_NAME === "OpenLayers.Layer.WMS") {
                    this.map.layers[i].setVisibility(false);
                }
            }
        }
        this.getCenter = function() {
            var point = this.map.center;
            return {
                x : point.lon,
                y : point.lat
            }
        }
        this.getExtent = function() {
            var mapBounds = this.map.getExtent();
            return mapBounds.toArray();
        }
        this.getBbox = function() {
            return this.map.getExtent().toString();
        }
        // Geolocation stuff starts here
        switch (mapLib) {
            case "ol":
                var geolocation_layer = new OpenLayers.Layer.Vector('geolocation_layer', {
                    displayInLayerSwitcher : false
                });

                var firstGeolocation = true;
                var style = {
                    fillColor : '#000',
                    fillOpacity : 0.1,
                    strokeWidth : 0
                };
                this.map.addLayers([geolocation_layer]);

                var firstCallBack;
                var trackCallBack;
                this.locate = function(config) {
                    var defaults = {
                        firstCallBack : function() {
                        },
                        trackCallBack : function() {
                        }
                    };
                    if (config) {
                        for (prop in config) {
                            defaults[prop] = config[prop];
                        }
                    };
                    firstCallBack = defaults.firstCallBack;
                    trackCallBack = defaults.trackCallBack;
                    geolocation_layer.removeAllFeatures();
                    geolocate.deactivate();
                    //$('track').checked = false;
                    geolocate.watch = true;
                    firstGeolocation = true;
                    geolocate.activate();
                };
                this.stopLocate = function() {
                    geolocate.deactivate();
                }
                var geolocate = new OpenLayers.Control.Geolocate({
                    bind : false,
                    geolocationOptions : {
                        enableHighAccuracy : false,
                        maximumAge : 0,
                        timeout : 7000
                    }
                });
                this.map.addControl(geolocate);
                geolocate.events.register("locationupdated", geolocate, function(e) {
                    geolocation_layer.removeAllFeatures();
                    var circle = new OpenLayers.Feature.Vector(OpenLayers.Geometry.Polygon.createRegularPolygon(new OpenLayers.Geometry.Point(e.point.x, e.point.y), e.position.coords.accuracy / 2, 40, 0), {}, style);
                    geolocation_layer.addFeatures([new OpenLayers.Feature.Vector(e.point, {}, {
                        graphicName : 'cross',
                        strokeColor : '#f00',
                        strokeWidth : 1,
                        fillOpacity : 0,
                        pointRadius : 10
                    }), circle]);
                    parentMap.geoLocation = {
                        x : e.point.x,
                        y : e.point.y,
                        obj : e
                    };
                    if (firstGeolocation) {
                        this.map.zoomToExtent(geolocation_layer.getDataExtent());
                        pulsate(circle);
                        firstGeolocation = false;
                        this.bind = true;
                        firstCallBack();
                    } else {
                        trackCallBack();
                    }

                });
                geolocate.events.register("locationfailed", this, function() {
                    alert("No location");
                });
                var pulsate = function(feature) {
                    var point = feature.geometry.getCentroid(), bounds = feature.geometry.getBounds(), radius = Math.abs((bounds.right - bounds.left) / 2), count = 0, grow = 'up';

                    var resize = function() {
                        if (count > 16) {
                            clearInterval(window.resizeInterval);
                        }
                        var interval = radius * 0.03;
                        var ratio = interval / radius;
                        switch(count) {
                            case 4:
                            case 12:
                                grow = 'down';
                                break;
                            case 8:
                                grow = 'up';
                                break;
                        }
                        if (grow !== 'up') {
                            ratio = -Math.abs(ratio);
                        }
                        feature.geometry.resize(1 + ratio, point);
                        geolocation_layer.drawFeature(feature);
                        count++;
                    };
                    window.resizeInterval = window.setInterval(resize, 50, point, radius);

                }
                break;
        };
    };

    var deserialize = function(element) {
        // console.log(element);
        var type = "wkt";
        var format = new OpenLayers.Format.WKT;
        var features = format.read(element);
        return features;
    };

    var grid = function(el, store, config) {
        var prop;
        var defaults = {
            height : 300,
            selectControl : {
                onSelect : function(feature) {
                },
                onUnselect : function() {
                }
            },
            columns : store.geoJSON.forGrid
        };
        if (config) {
            for (prop in config) {
                defaults[prop] = config[prop];
            }
        }
        this.grid = new Ext.grid.GridPanel({
            id : "gridpanel",
            viewConfig : {
                forceFit : true
            },
            store : store.featureStore, // layer
            sm : new GeoExt.grid.FeatureSelectionModel({// Only when there is a map
                singleSelect : false,
                selectControl : defaults.selectControl
            }),
            cm : new Ext.grid.ColumnModel({
                defaults : {
                    sortable : true,
                    editor : {
                        xtype : "textfield"
                    }
                },
                columns : defaults.columns
            }),
            listeners : defaults.listeners
        });
        this.panel = new Ext.Panel({
            renderTo : el,
            split : true,
            frame : false,
            border : false,
            layout : 'fit',
            collapsible : false,
            collapsed : false,
            height : defaults.height,
            items : [this.grid]
        });
        this.grid.getSelectionModel().bind().handlers.feature.stopDown = false;
        this.selectionModel = this.grid.getSelectionModel().bind();
    };
    return {
        geoJsonStore : geoJsonStore,
        map : map,
        grid : grid,
        urlVars : (function getUrlVars() {
            var mapvars = {};
            var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m, key, value) {
                mapvars[key] = value;
            });
            return mapvars;
        })(),
        pathName : window.location.pathname.split("/")
    };
})();
