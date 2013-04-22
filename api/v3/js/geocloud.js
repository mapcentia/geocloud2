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
    var map, lControl;
    var host = mygeocloud_host;
    //var parentThis = this;
    var mapLib;
    mapLib = "l";
    var geoJsonStore = function (config) {
        var prop, parentThis = this;
        var defaults = {
            db: null,
            sql: null,
            styleMap: null,
            projection: "900913",
            strategies: null,
            visibility: true,
            rendererOptions: {
                zIndexing: true
            },
            lifetime: 0,
            selectControl: {},
            movedEnd: function () {
            },
            onLoad: function () {
            }
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
            }
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
                numZoomLevels: 20,
                projection: "EPSG:3857"
            };
        if (config) {
            for (prop in config) {
                defaults[prop] = config[prop];
            }
        }
        parentMap = this;
        //ol3
        this.zoomToExtent = function (extent, closest) {
            switch (mapLib) {
                case "ol":
                    if (!extent) {
                        this.map.getView().setCenter([0, 0]);
                        this.map.getView().setResolution(39136);
                    } else {
                        this.map.zoomToExtent(new OpenLayers.Bounds(extent), closest);
                    }
                    break;
                case "l":
                    if (!extent) {
                        this.map.fitWorld();
                    }
                    else {
                        //Check it!
                        this.map.fitBounds(extent)
                    }
                    break;
            }

        };
        this.zoomToExtentOfgeoJsonStore = function (store) {
            this.map.zoomToExtent(store.layer.getDataExtent());
        };
        //ol3 and leaflet
        this.getVisibleLayers = function () {
            var layerArr = [];
            switch (mapLib) {
                case "ol":
                    for (var i = 0; i < this.map.getLayers().getLength(); i++) {
                        if (this.map.getLayers().a[i].t.visible === true && this.map.getLayers().a[i].baseLayer !== true) {
                            layerArr.push(this.map.getLayers().a[i].id);
                        }
                    }
                    break;
                case "l":
                    var layers = this.map._layers;
                    for (var key in layers) {
                        if (layers.hasOwnProperty(key)) {
                            if (layers[key].baseLayer !== true) {
                                layerArr.push(layers[key].id);
                            }
                        }
                    }
                    break;
            }
            return layerArr.join(";");
        };
        //ol3 and leaflet
        this.getNamesOfVisibleLayers = function () {
            var layerArr = [];
            switch (mapLib) {
                case "ol":
                    for (var i = 0; i < this.map.getLayers().getLength(); i++) {
                        if (this.map.getLayers().a[i].t.visible === true && this.map.getLayers().a[i].baseLayer !== true) {
                            layerArr.push(this.map.getLayers().a[i].id);
                        }
                    }
                    break;
                case "l":
                    var layers = this.map._layers;
                    for (var key in layers) {
                        if (layers.hasOwnProperty(key)) {
                            if (layers[key].baseLayer !== true) {
                                layerArr.push(layers[key].id);
                            }
                        }
                    }
                    break;
            }

            return layerArr.join(",");
        };
        this.getBaseLayer = function () {
            return this.map.baseLayer;
        };
        //ol3 and leaflet
        this.getBaseLayerName = function () {
            var name, layers;
            switch (mapLib) {
                case "ol":
                    layers = this.map.getLayers();
                    for (var i = 0; i < layers.getLength(); i++) {
                        if (layers.a[i].t.visible === true && layers.a[i].baseLayer === true) {
                            name = this.map.getLayers().a[i].id;
                        }
                    }
                    break;
                case "l":
                    layers = this.map._layers;
                    for (var key in layers) {
                        if (layers.hasOwnProperty(key)) {
                            if (layers[key].baseLayer === true) {
                                name = layers[key].id;
                            }
                        }
                    }
                    break;
            }
            return name;
        };
        //leaflet
        this.getZoom = function () {
            var zoom;
            switch (mapLib) {
                case "ol":
                    //zoom=this.map.getZoom();
                    break;
                case "l":
                    zoom = this.map.getZoom();
                    break;
            }
            return zoom;
        };
        //ol3
        this.getResolution = function () {
            // return this.map.getView().getResolution();
        };
        this.getPixelCoord = function (x, y) {
            var p = {};
            p.x = this.map.getPixelFromLonLat(new OpenLayers.LonLat(x, y)).x;
            p.y = this.map.getPixelFromLonLat(new OpenLayers.LonLat(x, y)).y;
            return p;
        };
        //ol3 and leaflet
        this.zoomToPoint = function (x, y, r) {
            switch (mapLib) {
                case "ol":
                    this.map.getView().setCenter([x, y]);
                    if (r) this.map.getView().setResolution(r);
                    break;
                case "l":
                    var p = transformPoint(x, y, "EPSG:900913", "EPSG:4326");
                    this.map.setView([p.y, p.x], r);
                    break;
            }
        };
        //click
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
        // map init
        switch (mapLib) {
            case "ol":
                this.map = new ol.Map({
                    target: defaults.el,
                    view: new ol.View2D({
                    }),
                    renderers: ol.RendererHints.createFromQueryData()
                });
                //var vectors = new ol.layer.Vector();
                //this.map.addLayer(vectors);
                break;
            case "l":
                this.map = new L.map(defaults.el);
                lControl = L.control.layers([], [])
                this.map.addControl(lControl);
                break;
        }
        var _map = this.map;
        //ol3 and leaflet
        this.addMapQuestOSM = function () {
            switch (mapLib) {
                case "ol":

                    this.mapQuestOSM = new ol.layer.TileLayer({
                        source: new ol.source.MapQuestOSM(),
                        visible: false
                    });
                    this.map.addLayer(this.mapQuestOSM);
                    break;
                case "l":
                    this.mapQuestOSM = new L.tileLayer('http://otile1.mqcdn.com/tiles/1.0.0/osm/{z}/{x}/{y}.jpg');
                    lControl.addBaseLayer(this.mapQuestOSM);
                    break;
            }
            this.mapQuestOSM.baseLayer = true;
            this.mapQuestOSM.id = "mapQuestOSM";

            return (this.mapQuestOSM);
        };
        //ol3 and leaflet
        this.addMapQuestAerial = function () {
            switch (mapLib) {
                case "ol":
                    this.mapQuestAerial = new ol.layer.TileLayer({
                        source: new ol.source.MapQuestOpenAerial(),
                        visible: false
                    });
                    break
                case "l":
                    this.mapQuestAerial = new L.tileLayer("http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png");
                    lControl.addBaseLayer(this.mapQuestAerial);
                    break;

            }
            this.mapQuestAerial.baseLayer = true;
            this.mapQuestAerial.id = "mapQuestAerial";
            return (this.mapQuestAerial);
        };
        //ol3 and leaflet
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
                    lControl.addBaseLayer(this.osm);
                    break;
            }
            this.osm.baseLayer = true;
            this.osm.id = "osm";
            return (this.osm);
        };
        //ol3 and leaflet
        this.addStamenToner = function () {
            switch (mapLib) {
                case "ol":
                    this.stamenToner = new ol.layer.TileLayer({
                        source: new ol.source.Stamen({
                            layer: 'toner'
                        }),
                        visible: false
                    });
                    break;
                case "l":
                    this.stamenToner = new L.tileLayer("http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png");
                    lControl.addBaseLayer(this.stamenToner);
                    break;
            }
            this.stamenToner.baseLayer = true;
            this.stamenToner.id = "stamenToner";
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
        //ol3 and leaflet
        this.setBaseLayer = function (baseLayerName) {
            switch (mapLib) {
                case "ol":
                    var layers = this.map.getLayers();
                    for (var i = 0; i < layers.getLength(); i++) {
                        if (layers.a[i].baseLayer === true) {
                            layers.a[i].set("visible", false);
                        }
                    }
                    this.getLayersByName(baseLayerName).set("visible", true);
                    break;
                case "l":
                    var layers = lControl._layers;
                    for (var key in layers) {
                        if (layers.hasOwnProperty(key)) {
                            if (layers[key].layer.baseLayer === true && this.map.hasLayer(layers[key].layer)) this.map.removeLayer(layers[key].layer);
                            if (layers[key].layer.baseLayer === true && layers[key].layer.id === baseLayerName) {
                                this.map.addLayer(layers[key].layer,false);
                            }
                        }
                    }
                    break;
            }
        }
        //ol3 and leaflet
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
                l.baseLayer = defaults.isBaseLayer;
                switch (mapLib) {
                    case "ol":
                        this.map.addLayer(l);
                        break;
                    case "l":
                        if (defaults.isBaseLayer === true) {
                            lControl.addBaseLayer(l);
                        }
                        else {
                            lControl.addOverlay(l);
                        }
                        break;
                }
                layersArr.push(l);
            }
            return layersArr;
        };
        //ol3 and leaflet
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
                        transparent: true,
                    });
                    l.id = layer;
                    break;
            }
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
        };
        this.removeGeoJsonStore = function (store) {
            this.map.removeLayer(store.layer);
            //??????????????
        };
        //ol3 and leaflet
        this.hideLayer = function (name) {
            switch (mapLib) {
                case "ol":
                    this.getLayersByName(name).set("visible", false);
                    break;
                case "l":
                    this.map.removeLayer(this.getLayersByName(name));
                    break;
            }
        };
        //ol3 and leaflet
        this.showLayer = function (name) {
            switch (mapLib) {
                case "ol":
                    this.getLayersByName(name).set("visible", true);
                    break;
                case "l":
                    this.getLayersByName(name).addTo(this.map);
                    break;
            }
        };
        this.getLayerById = function (id) {
            return this.map.getLayer(id);
        }
        //ol3 and leaflet (rename to getLayerByName)
        this.getLayersByName = function (name) {
            var l;
            switch (mapLib) {
                case "ol":
                    for (var i = 0; i < this.map.getLayers().getLength(); i++) {
                        if (this.map.getLayers().a[i].id === name) {
                            l = this.map.getLayers().a[i];
                        }
                    }
                    break;
                case "l":
                    var layers = lControl._layers;
                    for (var key in layers) {
                        if (layers.hasOwnProperty(key)) {
                            if (layers[key].layer.id === name) {
                                l = layers[key].layer;
                            }
                        }
                    }
                    break;
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
        // ol3 and leaflet
        this.getCenter = function () {
            switch (mapLib) {
                case "ol":
                    var point = this.map.getView().getCenter();
                    return {
                        x: point[0],
                        y: point[1]
                    }
                    break;
                case "l":
                    var point = this.map.getCenter();
                    var p = transformPoint(point.lng, point.lat, "EPSG:4326", "EPSG:900913");
                    return {
                        x: p.x,
                        y: p.y
                    }
                    break;
            }

        };
        this.getExtent = function () {
            var mapBounds = this.map.getExtent();
            return mapBounds.toArray();
        };
        this.getBbox = function () {
            return this.map.getExtent().toString();
        };
        // ol3
        this.locate = function () {
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
            geolocation.addEventListener('accuracy_changed', function () {
                center = ol.projection.transform([geolocation.a[0], geolocation.a[1]], 'EPSG:4326', 'EPSG:3857');
                this.zoomToPoint(center[0], center[1], 1000);
                $(marker.getElement()).tooltip({
                    title: this.getAccuracy() + 'm from this point'
                });
            });
        }
    }
    var deserialize = function (element) {
        var type = "wkt";
        var format = new OpenLayers.Format.WKT;
        return format.read(element);
    };
    var transformPoint = function (lat, lon, s, d) {
        var source = new Proj4js.Proj(s);    //source coordinates will be in Longitude/Latitude
        var dest = new Proj4js.Proj(d);
        var p = new Proj4js.Point(lat, lon);
        Proj4js.transform(source, dest, p);
        return p;
    };
    return {
        geoJsonStore: geoJsonStore,
        map: map,
        transformPoint: transformPoint,
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
})();
