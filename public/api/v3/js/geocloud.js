var geocloud_host; // Global var
var geocloud;
geocloud = (function () {
    "use strict";
    var scriptSource = (function (scripts) {
            scripts = document.getElementsByTagName('script');
            var script = scripts[scripts.length - 1];
            if (script.getAttribute.length !== undefined) {
                return script.src;
            }
            return script.getAttribute('src', -1);
        }()),
        map,
        storeClass,
        extend,
        geoJsonStore,
        sqlStore,
        tweetStore,
        elasticStore,
        tileLayer,
        createTileLayer,
        clickEvent,
        transformPoint,
        lControl,
        MAPLIB,
        host,
        OSM = "osm",
        MAPQUESTOSM = "mapQuestOSM",
        MAPBOXNATURALEARTH = "mapBoxNaturalEarth",
        STAMENTONER = "stamenToner",
        GOOGLESTREETS = "googleStreets",
        GOOGLEHYBRID = "googleHybrid",
        GOOGLESATELLITE = "googleSatellite",
        GOOGLETERRAIN = "googleTerrain",
        BINGROAD = "bingRoad",
        BINGAERIAL = "bingAerial",
        BINGAERIALWITHLABELS = "bingAerialWithLabels",
        attribution = (window.mapAttribution === undefined) ? "Powered by <a href='http://geocloud.mapcentia.com'>MapCentia</a> " : window.mapAttribution;

    // In IE7 host name is missing if script url is relative
    geocloud_host = host = (scriptSource.charAt(0) === "/") ? "" : scriptSource.split("/")[0] + "//" + scriptSource.split("/")[2];

    // Check if jQuery is loaded and load if not
    if (typeof jQuery === "undefined") {
        document.write("<script src='https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js'><\/script>");
    }
    if (typeof ol !== "object" && typeof L !== "object" && typeof OpenLayers !== "object") {
        alert("You need to load neither OpenLayers.js, ol3,js or Leaflet.js");
    }
    if (typeof OpenLayers === "object") {
        MAPLIB = "ol2";
    }
    if (typeof ol === "object") {
        MAPLIB = "ol3";
    }
    if (typeof L === "object") {
        MAPLIB = "leaflet";
        document.write("<script src='http://cdn.eu1.mapcentia.com/js/leaflet/plugins/leaflet-google.js'><\/script>");
        document.write("<script src='http://cdn.eu1.mapcentia.com/js/leaflet/plugins/leaflet-bing.js'><\/script>");
        document.write("<script src='http://cdn.eu1.mapcentia.com/js/leaflet/plugins/leaflet-yandex.js'><\/script>");
        document.write("<script src='http://cdn.eu1.mapcentia.com/js/leaflet/plugins/awesome-markers/leaflet.awesome-markers.min.js'><\/script>");
    }

    // Helper for extending classes
    extend = function (ChildClass, ParentClass) {
        ChildClass.prototype = new ParentClass();
    };
    // Base class for stores
    storeClass = function () {
        this.defaults = {
            styleMap: null,
            visibility: true,
            lifetime: 0,
            db: null,
            sql: null,
            q: null,
            name: "Vector",
            id: null,
            rendererOptions: { zIndexing: true },
            projection: (MAPLIB === "leaflet") ? "4326" : "900913",
            //Only leaflet
            pointToLayer: function (feature, latlng) {
                return L.circleMarker(latlng);
            },
            //Only leaflet
            onEachFeature: function () {
            },
            onLoad: function () {
            },
            index: "",
            type: "",
            size: 3,
            clientEncoding: "UTF8"
        };
        this.hide = function () {
            this.layer.setVisibility(false);
        };
        this.show = function () {
            this.layer.setVisibility(true);
        };
        this.map = null;

        // Initiate base class settings
        this.init = function () {
            this.onLoad = this.defaults.onLoad;
            switch (MAPLIB) {
                case 'ol2':
                    this.layer = new OpenLayers.Layer.Vector(this.defaults.name, {
                        styleMap: this.defaults.styleMap,
                        visibility: this.defaults.visibility,
                        renderers: ['Canvas', 'SVG', 'VML'],
                        rendererOptions: this.defaults.rendererOptions
                    });
                    break;
                case 'ol3':
                    this.layer = new ol.layer.Vector({
                        source: new ol.source.GeoJSON(),
                        style: this.defaults.styleMap
                    });
                    this.layer.id = this.defaults.name;
                    break

                case 'leaflet':
                    this.layer = L.geoJson(null, {
                        style: this.defaults.styleMap,
                        pointToLayer: this.defaults.pointToLayer,
                        onEachFeature: this.defaults.onEachFeature
                    });
                    this.layer.id = this.defaults.name;
                    break;
            }
        };
        this.geoJSON = {};
        this.featureStore = null;
        this.reset = function () {
            switch (MAPLIB) {
                case "ol2":
                    this.layer.destroyFeatures();
                    break;
                case "leaflet":
                    this.layer.clearLayers();
                    break;
            }
        };
        this.isEmpty = function () {
            switch (MAPLIB) {
                case "ol2":
                    return (this.layer.features.length === 0) ? true : false;
                    break;
                case "leaflet":
                    return (Object.keys(this.layer._layers).length === 0) ? true : false;
                    break;
            }
        };
        this.getWKT = function () {
            return new OpenLayers.Format.WKT().write(this.layer.features);
        };
    };
    geoJsonStore = sqlStore = function (config) {
        var prop, me = this, map, sql;
        if (config) {
            for (prop in config) {
                this.defaults[prop] = config[prop];
            }
        }
        this.init();
        this.id = this.defaults.id;
        this.sql = this.defaults.sql;
        this.db = this.defaults.db;
        this.load = function (doNotShowAlertOnError) {
            var url = host.replace("cdn.", "");
            try {
                map = me.map;
                sql = this.sql;
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
                data: 'q=' + encodeURIComponent(sql) + '&srs=' + this.defaults.projection + '&lifetime=' + this.defaults.lifetime + "&srs=" + this.defaults.projection + '&client_encoding=' + this.defaults.clientEncoding,
                jsonp: 'jsonp_callback',
                url: url + '/api/v1/sql/' + this.db,
                success: function (response) {
                    if (response.success === false && doNotShowAlertOnError === undefined) {
                        alert(response.message);
                    }
                    if (response.success === true) {
                        if (response.features !== null) {
                            me.geoJSON = response;
                            switch (MAPLIB) {
                                case "ol2":
                                    me.layer.addFeatures(new OpenLayers.Format.GeoJSON().read(response));
                                    break;
                                case "ol3":
                                    me.layer.getSource().addFeatures(new ol.source.GeoJSON(
                                        {
                                            object: response.features[0]
                                        }
                                    ));

                                    break;
                                case "leaflet":
                                    me.layer.addData(response);
                                    break;
                            }
                        }
                    }
                },
                complete: function () {
                    me.onLoad();
                }

            });
            return this.layer;
        };
    };
    tweetStore = function (config) {
        var prop, me = this;
        if (config) {
            for (prop in config) {
                this.defaults[prop] = config[prop];
            }
        }
        this.init();
        this.load = function (doNotShowAlertOnError) {
            var q = this.defaults.q;
            try {
                var map = me.map;
                //q = q.replace("{centerX}", map.getCenter().lat.toString());
                // = q.replace("{centerY}", map.getCenter().lon.toString());
                q = q.replace(/\{minX\}/g, map.getExtent().left);
                q = q.replace(/\{maxX\}/g, map.getExtent().right);
                q = q.replace(/\{minY\}/g, map.getExtent().bottom);
                q = q.replace(/\{maxY\}/g, map.getExtent().top);
                q = q.replace(/{bbox}/g, map.getExtent().toString());
            } catch (e) {
            }
            $.ajax({
                dataType: 'jsonp',
                data: 'search=' + encodeURIComponent(q),
                jsonp: 'jsonp_callback',
                url: host + '/api/v1/twitter/' + this.db,
                success: function (response) {
                    if (response.success === false && doNotShowAlertOnError === undefined) {
                        alert(response.message);
                    }
                    if (response.success === true) {
                        if (response.features !== null) {
                            me.geoJSON = response;
                            switch (MAPLIB) {
                                case "ol2":
                                    me.layer.addFeatures(new OpenLayers.Format.GeoJSON().read(response));
                                    break;
                                case "leaflet":
                                    me.layer.addData(response);
                                    break;
                            }
                        }
                    }
                },
                complete: function () {
                    me.onLoad();
                }
            });
            return this.layer;
        };
    };
    elasticStore = function (config) {
        var prop, me = this;
        if (config) {
            for (prop in config) {
                this.defaults[prop] = config[prop];
            }
        }
        this.init();
        this.q = this.defaults.q;
        this.load = function (doNotShowAlertOnError) {
            var map = me.map, q = this.q;
            try {
                //q = q.replace("{centerX}", map.getCenter().lat.toString());
                //q = q.replace("{centerY}", map.getCenter().lon.toString());
                q = q.replace("{minX}", map.getExtent().left);
                q = q.replace("{maxX}", map.getExtent().right);
                q = q.replace("{minY}", map.getExtent().bottom);
                q = q.replace("{maxY}", map.getExtent().top);
                q = q.replace("{bbox}", map.getExtent().toString());
            } catch (e) {
            }

            $.ajax({
                dataType: 'jsonp',
                data: 'q=' + encodeURIComponent(q) + "&size=" + this.defaults.size,
                jsonp: 'jsonp_callback',
                url: host + '/api/v1/elasticsearch/search/' + this.defaults.db + "/" + this.defaults.index + "/" + this.defaults.type,
                success: function (response) {
                    var features = [];
                    $.each(response.hits.hits, function (i, v) {
                        features.push(v._source);
                    })
                    response.features = features;
                    if (response.features !== null) {
                        me.geoJSON = response;
                        switch (MAPLIB) {
                            case "ol2":
                                me.layer.addFeatures(new OpenLayers.Format.GeoJSON().read(response));
                                break;
                            case "leaflet":
                                me.layer.addData(response);
                                break;
                        }
                    }

                },
                complete: function () {
                    me.onLoad();
                }
            });
            return this.layer;
        };
    };
    // Extend store classes
    extend(sqlStore, storeClass);
    extend(tweetStore, storeClass);
    extend(elasticStore, storeClass);

    //ol2, ol3 and leaflet
    tileLayer = function (config) {
        var prop;
        var defaults = {
            layer: null,
            db: null,
            singleTile: false,
            opacity: 1,
            wrapDateLine: true,
            tileCached: true,
            name: null,
            isBaseLayer: false
        };
        if (config) {
            for (prop in config) {
                defaults[prop] = config[prop];
            }
        }
        return createTileLayer(defaults.layer, defaults);
    };

    //ol2, ol3 and leaflet
    createTileLayer = function (layer, defaults) {
        var parts, l, url, urlArray;
        parts = layer.split(".");
        if (!defaults.tileCached) {
            url = host + "/wms/" + defaults.db + "/" + parts[0] + "?";
            urlArray = [url];
        } else {
            var url = host + "/wms/" + defaults.db + "/tilecache";
            var url1 = url;
            var url2 = url;
            var url3 = url;
            // For ol2
            urlArray = [url1.replace("cdn.", "cdn1."), url2.replace("cdn.", "cdn2."), url3.replace("cdn.", "cdn3.")];

            // For leaflet
            url = url.replace("cdn.", "{s}.");
        }
        switch (MAPLIB) {
            case "ol2":
                l = new OpenLayers.Layer.WMS(defaults.name, urlArray, {
                    layers: layer,
                    transparent: true
                }, defaults);
                l.id = layer;
                break;
            case "ol3":
                l = new ol.layer.Tile({
                    source: new ol.source.TileWMS({
                        url: url,
                        params: {LAYERS: layer}
                    }),
                    visible: defaults.visibility
                });
                l.id = layer;
                break;
            case "leaflet":
                l = new L.TileLayer.WMS(url, {
                    layers: layer,
                    format: 'image/png',
                    transparent: true,
                    subdomains: ["cdn1", "cdn2", "cdn3"]

                });
                l.id = layer;
                break;
        }
        return l;
    };

    // Set map constructor
    map = function (config) {
        var prop, queryLayers = [],
            defaults = {
                numZoomLevels: 20,
                projection: "EPSG:900913"
            };
        if (config) {
            for (prop in config) {
                defaults[prop] = config[prop];
            }
        }
        // Load js and css
        if (MAPLIB === "leaflet") {
            // The css
            $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: 'http://cdn.eu1.mapcentia.com/js/leaflet/leaflet.css' }).appendTo('head');
            $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: 'http://cdn.eu1.mapcentia.com/js/leaflet/plugins/awesome-markers/leaflet.awesome-markers.css' }).appendTo('head');
        }
        $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: 'http://eu1.mapcentia.com/api/v3/css/styles.css' }).appendTo('head');

        this.bingApiKey = null;
        //ol2, ol3
        // extent array
        this.zoomToExtent = function (extent, closest) {
            switch (MAPLIB) {
                case "ol2":
                    if (!extent) {
                        this.map.zoomToExtent(this.map.maxExtent);
                    } else {
                        this.map.zoomToExtent(new OpenLayers.Bounds(extent), closest);
                    }
                    break;
                case "ol3":
                    if (!extent) {
                        this.map.getView().setCenter([0, 0]);
                        this.map.getView().setResolution(39136);
                    } else {
                        this.map.zoomToExtent(new OpenLayers.Bounds(extent), closest);
                    }
                    break;
                case "leaflet":
                    if (!extent) {
                        this.map.fitWorld();
                    }
                    else {
                        //Check it!
                        this.map.fitBounds(extent);
                    }
                    break;
            }

        };
        this.zoomToExtentOfgeoJsonStore = function (store) {
            switch (MAPLIB) {
                case "ol2":
                    this.map.zoomToExtent(store.layer.getDataExtent());
                    break;
                case "leaflet":
                    this.map.fitBounds(store.layer.getBounds());
                    break;
            }
        };
        this.getBaseLayers = function () {
            var layerArr = [];
            switch (MAPLIB) {
                case "leaflet":
                    var layers = this.map._layers;
                    for (var key in layers) {
                        if (layers.hasOwnProperty(key)) {
                            if (layers[key].baseLayer === true) {
                                layerArr.push(layers);
                            }
                        }
                    }
                    break;
            }
            return layerArr;
        };
        //ol2, ol3 and leaflet
        this.getVisibleLayers = function () {
            var layerArr = [];
            switch (MAPLIB) {
                case "ol2":
                    for (var i = 0; i < this.map.layers.length; i++) {
                        if (this.map.layers[i].isBaseLayer === false && this.map.layers[i].visibility === true && this.map.layers[i].CLASS_NAME === "OpenLayers.Layer.WMS") {
                            layerArr.push(this.map.layers[i].params.LAYERS);
                        }
                    }
                    break;
                case "ol3":
                    for (var i = 0; i < this.map.getLayers().getLength(); i++) {

                        if (this.map.getLayers().a[i].e.visible === true && this.map.getLayers().a[i].baseLayer !== true) {
                            layerArr.push(this.map.getLayers().a[i].id);
                        }
                    }
                    break;
                case "leaflet":
                    var layers = this.map._layers;
                    for (var key in layers) {
                        if (layers.hasOwnProperty(key)) {
                            if (layers[key].baseLayer !== true && typeof layers[key]._tiles === "object") {
                                layerArr.push(layers[key].id);
                            }
                        }
                    }
                    break;
            }
            return layerArr.join(";");
        };
        //ol2, ol3 and leaflet
        this.getNamesOfVisibleLayers = function () {
            var layerArr = [];
            switch (MAPLIB) {
                case "ol2":
                    for (var i = 0; i < this.map.layers.length; i++) {
                        if (this.map.layers[i].isBaseLayer === false && this.map.layers[i].visibility === true && this.map.layers[i].CLASS_NAME === "OpenLayers.Layer.WMS") {
                            layerArr.push(this.map.layers[i].name);
                        }
                    }
                    break;
                case "ol3":
                    for (var i = 0; i < this.map.getLayers().getLength(); i++) {
                        if (this.map.getLayers().a[i].t.visible === true && this.map.getLayers().a[i].baseLayer !== true) {
                            layerArr.push(this.map.getLayers().a[i].id);
                        }
                    }
                    break;
                case "leaflet":
                    var layers = this.map._layers;
                    for (var key in layers) {
                        if (layers.hasOwnProperty(key)) {
                            if (layers[key].baseLayer !== true && typeof layers[key]._tiles === "object") {
                                layerArr.push(layers[key].id);
                            }
                        }
                    }
                    break;
            }
            if (layerArr.length > 0) return layerArr.join(",");
            else return layerArr;
        };
        //ol2
        this.getBaseLayer = function () {
            return this.map.baseLayer;
        };
        //ol2, ol3 and leaflet
        this.getBaseLayerName = function () {
            var name, layers;
            switch (MAPLIB) {
                case "ol2":
                    name = this.map.baseLayer.name;
                    break;
                case "ol3":
                    layers = this.map.getLayers();
                    for (var i = 0; i < layers.getLength(); i++) {
                        if (layers.a[i].t.visible === true && layers.a[i].baseLayer === true) {
                            name = this.map.getLayers().a[i].id;
                        }
                    }
                    break;
                case "leaflet":
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
        //ol2, ol3 leaflet
        this.getZoom = function () {
            var zoom;
            switch (MAPLIB) {
                case "ol2":
                    zoom = this.map.getZoom();
                    break;
                case "ol3":
                    var resolution = this.getResolution();
                    zoom = Math.round(Math.log(2 * Math.PI * 6378137 / (256 * resolution)) / Math.LN2);
                    break;
                case "leaflet":
                    zoom = this.map.getZoom();
                    break;
            }
            return zoom;
        };
        //ol3
        this.getResolution = function () {
            return this.map.getView().getResolution();
        };
        //ol2
        this.getPixelCoord = function (x, y) {
            var p = {};
            p.x = this.map.getPixelFromLonLat(new OpenLayers.LonLat(x, y)).x;
            p.y = this.map.getPixelFromLonLat(new OpenLayers.LonLat(x, y)).y;
            return p;
        };
        //ol2, ol3 and leaflet
        // Input map coordinates (900913)
        this.zoomToPoint = function (x, y, r) {
            switch (MAPLIB) {
                case "ol2":
                    this.map.setCenter(new OpenLayers.LonLat(x, y), r);
                    break;
                case "ol3":
                    this.map.getView().setCenter([x, y]);
                    var resolution;
                    resolution = 2 * Math.PI * 6378137 / (256 * Math.pow(2, r));
                    this.map.getView().setResolution(resolution);
                    break;
                case "leaflet":
                    var p = transformPoint(x, y, "EPSG:900913", "EPSG:4326");
                    this.map.setView([p.y, p.x], r);
                    break;
            }
        };
        // Leaflet only
        this.setView = function (xy, r) {
            this.map.setView(xy, r);
        };
        // map init
        switch (MAPLIB) {
            case "ol2":
                this.map = new OpenLayers.Map(defaults.el, {
                    //theme: null,
                    controls: [
                        new OpenLayers.Control.Zoom(),
                        new OpenLayers.Control.TouchNavigation({
                            dragPanOptions: {
                                enableKinetic: true
                            }
                        })],
                    numZoomLevels: defaults.numZoomLevels,
                    projection: defaults.projection,
                    maxResolution: defaults.maxResolution,
                    minResolution: defaults.minResolution,
                    maxExtent: defaults.maxExtent,
                    eventListeners: defaults.eventListeners
                });
                break;
            case "ol3":
                this.map = new ol.Map({
                    target: defaults.el,
                    view: new ol.View2D({
                    })
                    //renderers: ol.RendererHints.createFromQueryData()
                });
                break;
            case "leaflet":
                this.map = new L.map(defaults.el, {});
                lControl = L.control.layers([], []);
                this.map.addControl(lControl);
                this.map.attributionControl.setPrefix(attribution);
                break;
        }
        var _map = this.map;
        //ol2, ol3 and leaflet
        this.addMapQuestOSM = function () {
            switch (MAPLIB) {
                case "ol2":
                    this.mapQuestOSM = new OpenLayers.Layer.OSM("mapQuestOSM", [
                        "http://otile1.mqcdn.com/tiles/1.0.0/osm/${z}/${x}/${y}.jpg",
                        "http://otile2.mqcdn.com/tiles/1.0.0/osm/${z}/${x}/${y}.jpg",
                        "http://otile3.mqcdn.com/tiles/1.0.0/osm/${z}/${x}/${y}.jpg",
                        "http://otile4.mqcdn.com/tiles/1.0.0/osm/${z}/${x}/${y}.jpg"
                    ]);
                    this.mapQuestOSM.wrapDateLine = false;
                    this.map.addLayer(this.mapQuestOSM);
                    this.mapQuestOSM.setVisibility(false);
                    break;
                case "ol3":
                    this.mapQuestOSM = new ol.layer.TileLayer({
                        source: new ol.source.MapQuestOSM(),
                        visible: false
                    });
                    this.map.addLayer(this.mapQuestOSM);
                    break;
                case "leaflet":
                    this.mapQuestOSM = new L.tileLayer('http://otile1.mqcdn.com/tiles/1.0.0/osm/{z}/{x}/{y}.jpg');
                    lControl.addBaseLayer(this.mapQuestOSM);
                    break;
            }
            this.mapQuestOSM.baseLayer = true;
            this.mapQuestOSM.id = "mapQuestOSM";
            return (this.mapQuestOSM);
        };
        //ol2, ol3 and leaflet
        this.addMapQuestAerial = function () {
            switch (MAPLIB) {
                case "ol2":
                    this.mapQuestAerial = new OpenLayers.Layer.OSM("mapQuestAerial", ["http://oatile1.mqcdn.com/tiles/1.0.0/sat/${z}/${x}/${y}.jpg", "http://oatile2.mqcdn.com/tiles/1.0.0/sat/${z}/${x}/${y}.jpg", "http://oatile3.mqcdn.com/tiles/1.0.0/sat/${z}/${x}/${y}.jpg", "http://oatile4.mqcdn.com/tiles/1.0.0/sat/${z}/${x}/${y}.jpg"]);
                    this.mapQuestAerial.wrapDateLine = false;
                    this.map.addLayer(this.mapQuestAerial);
                    this.mapQuestAerial.setVisibility(false);
                    break;
                case "ol3":
                    this.mapQuestAerial = new ol.layer.TileLayer({
                        source: new ol.source.MapQuestOpenAerial(),
                        visible: false
                    });
                    this.map.addLayer(this.mapQuestAerial);
                    break
                case "leaflet":
                    this.mapQuestAerial = new L.tileLayer("http://oatile1.mqcdn.com/tiles/1.0.0/sat/{z}/{x}/{y}.jpg");
                    lControl.addBaseLayer(this.mapQuestAerial);
                    break;

            }
            this.mapQuestAerial.baseLayer = true;
            this.mapQuestAerial.id = "mapQuestAerial";
            return (this.mapQuestAerial);
        };
        //ol2, ol3 and leaflet
        this.addOSM = function () {
            switch (MAPLIB) {
                case "ol2":
                    this.osm = new OpenLayers.Layer.OSM("osm");
                    this.osm.wrapDateLine = false;
                    this.map.addLayer(this.osm);
                    this.osm.setVisibility(false);
                    break;
                case "ol3":
                    this.osm = new ol.layer.Tile({
                        source: new ol.source.OSM(),
                        visible: false
                    });
                    this.map.addLayer(this.osm);
                    break;
                case "leaflet":
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
            switch (MAPLIB) {
                case "ol2":
                    this.stamenToner = new OpenLayers.Layer.Stamen("toner");
                    this.stamenToner.name = "stamenToner";
                    this.map.addLayer(this.stamenToner);
                    this.stamenToner.setVisibility(false);
                    break;
                case "ol3":
                    this.stamenToner = new ol.layer.TileLayer({
                        source: new ol.source.Stamen({
                            layer: 'toner'
                        }),
                        visible: false
                    });
                    this.map.addLayer(this.stamenToner);
                    break;
                case "leaflet":
                    this.stamenToner = new L.StamenTileLayer("toner");
                    lControl.addBaseLayer(this.stamenToner);
                    break;
            }
            this.stamenToner.baseLayer = true;
            this.stamenToner.id = "stamenToner";
            return (this.stamenToner);
        };
        //ol3 and leaflet
        this.addMapBoxNaturalEarth = function () {
            switch (MAPLIB) {
                case "ol2":
                    this.mapBoxNaturalEarth = new OpenLayers.Layer.XYZ("mapBoxNaturalEarth", [
                        "http://a.tiles.mapbox.com/v3/mapbox.natural-earth-hypso-bathy/${z}/${x}/${y}.png",
                        "http://b.tiles.mapbox.com/v3/mapbox.natural-earth-hypso-bathy/${z}/${x}/${y}.png",
                        "http://c.tiles.mapbox.com/v3/mapbox.natural-earth-hypso-bathy/${z}/${x}/${y}.png",
                        "http://d.tiles.mapbox.com/v3/mapbox.natural-earth-hypso-bathy/${z}/${x}/${y}.png"
                    ]);
                    this.mapBoxNaturalEarth.wrapDateLine = false;
                    this.map.addLayer(this.mapBoxNaturalEarth);
                    this.mapBoxNaturalEarth.setVisibility(false);
                    break;
                case "ol3":
                    this.mapBoxNaturalEarth = new ol.layer.TileLayer({
                        source: new ol.source.OSM(),
                        visible: false
                    });
                    this.map.addLayer(this.mapBoxNaturalEarth);
                    break;
                case "leaflet":
                    this.mapBoxNaturalEarth = new L.tileLayer("http://a.tiles.mapbox.com/v3/mapbox.natural-earth-hypso-bathy/{z}/{x}/{y}.png");
                    lControl.addBaseLayer(this.mapBoxNaturalEarth);
                    break;
            }
            this.mapBoxNaturalEarth.baseLayer = true;
            this.mapBoxNaturalEarth.id = "mapBoxNaturalEarth";
            return (this.mapBoxNaturalEarth);
        };
        this.addGoogleStreets = function () {
            switch (MAPLIB) {
                case "ol2":
                    try {
                        this.baseGNORMAL = new OpenLayers.Layer.Google("googleStreets", {// the default
                            wrapDateLine: false,
                            numZoomLevels: 20
                        });
                    } catch (e) {
                    }
                    this.map.addLayer(this.baseGNORMAL);
                    this.baseGNORMAL.setVisibility(false);
                    break;
                case "leaflet":
                    this.baseGNORMAL = new L.Google('ROADMAP');
                    lControl.addBaseLayer(this.baseGNORMAL);
                    break;

            }
            this.baseGNORMAL.baseLayer = true;
            this.baseGNORMAL.id = "googleStreets";
            return (this.baseGNORMAL);
        }
        this.addGoogleHybrid = function () {
            switch (MAPLIB) {
                case "ol2":
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
                    this.map.addLayer(this.baseGHYBRID);
                    this.baseGHYBRID.setVisibility(false);
                    break;
                case "leaflet":
                    this.baseGHYBRID = new L.Google('HYBRID');
                    lControl.addBaseLayer(this.baseGHYBRID);
                    break;
            }
            this.baseGHYBRID.baseLayer = true;
            this.baseGHYBRID.id = "googleHybrid";
            return (this.baseGHYBRID);
        };
        this.addGoogleSatellite = function () {
            switch (MAPLIB) {
                case "ol2":
                    // v3
                    try {
                        this.baseGSATELLITE = new OpenLayers.Layer.Google("googleSatellite", {
                            type: google.maps.MapTypeId.SATELLITE,
                            wrapDateLine: true,
                            numZoomLevels: 20
                        });
                    }
                    catch
                        (e) {
                        // alert(e.message)
                    }
                    this.map.addLayer(this.baseGSATELLITE);
                    this.baseGSATELLITE.setVisibility(false);
                    break;
                case "leaflet":
                    this.baseGSATELLITE = new L.Google('SATELLITE');
                    lControl.addBaseLayer(this.baseGSATELLITE);
                    break;
            }
            this.baseGSATELLITE.baseLayer = true;
            this.baseGSATELLITE.id = "googleSatellite";
            return (this.baseGSATELLITE);
        };
        this.addGoogleTerrain = function () {
            switch (MAPLIB) {
                case "ol2":
                    this.baseGTERRAIN = new OpenLayers.Layer.Google("googleTerrain", {
                        type: google.maps.MapTypeId.TERRAIN,
                        wrapDateLine: true,
                        numZoomLevels: 20
                    });
                    this.map.addLayer(this.baseGTERRAIN);
                    this.baseGTERRAIN.setVisibility(false);
                    break;
                case "leaflet":
                    this.baseGTERRAIN = new L.Google('TERRAIN');
                    lControl.addBaseLayer(this.baseGTERRAIN);
                    break;
            }
            this.baseGTERRAIN.baseLayer = true;
            this.baseGTERRAIN.id = "googleTerrain";
            return (this.baseGTERRAIN);
        };
        this.addBing = function (type) {
            var l, name;
            switch (type) {
                case "Road":
                    name = "bingRoad";
                    break;
                case "Aerial":
                    name = "bingAerial";
                    break;
                case "AerialWithLabels":
                    name = "bingAerialWithLabels";
                    break;
            }
            switch (MAPLIB) {
                case "ol2":
                    l = new OpenLayers.Layer.Bing({
                        name: name,
                        wrapDateLine: true,
                        key: this.bingApiKey,
                        type: type
                    });
                    this.map.addLayer(l);
                    l.setVisibility(false);
                    break;
                case "leaflet":
                    l = new L.BingLayer(this.bingApiKey, {"type": type});
                    lControl.addBaseLayer(l);
                    break;
            }
            l.baseLayer = true;
            l.id = name;
            return (l);
        };
        this.addYandex = function () {
            switch (MAPLIB) {
                case "ol2":
                    this.osm = new OpenLayers.Layer.OSM("osm");
                    this.osm.wrapDateLine = false;
                    this.map.addLayer(this.osm);
                    this.osm.setVisibility(false);
                    break;
                case "ol3":
                    this.osm = new ol.layer.TileLayer({
                        source: new ol.source.OSM(),
                        visible: false
                    });
                    this.map.addLayer(this.osm);
                    break;
                case "leaflet":
                    this.yandex = new L.Yandex();
                    lControl.addBaseLayer(this.yandex);
                    break;
            }
            this.yandex.baseLayer = true;
            this.yandex.id = "yandex";
            return (this.yandex);
        };
        //ol2, ol3 and leaflet
        this.setBaseLayer = function (baseLayerName) {
            switch (MAPLIB) {
                case "ol2":
                    this.showLayer(baseLayerName);
                    this.map.setBaseLayer(this.getLayersByName(baseLayerName));
                    break
                case "ol3":
                    var layers = this.map.getLayers();
                    for (var i = 0; i < layers.getLength(); i++) {
                        if (layers.a[i].baseLayer === true) {
                            layers.a[i].set("visible", false);
                        }
                    }
                    this.getLayersByName(baseLayerName).set("visible", true);
                    break;
                case "leaflet":
                    var layers = lControl._layers;

                    for (var key in layers) {

                        if (layers.hasOwnProperty(key)) {
                            if (layers[key].layer.baseLayer === true && this.map.hasLayer(layers[key].layer)) {
                                this.map.removeLayer(layers[key].layer);

                            }
                            if (layers[key].layer.baseLayer === true && layers[key].layer.id === baseLayerName) {
                                this.map.addLayer(layers[key].layer, false);

                            }
                        }
                    }
                    break;
            }
        };
        this.addBaseLayer = function (l) {
            var o;
            switch (l) {
                case "osm":
                    o = this.addOSM();
                    break;
                case "mapQuestOSM":
                    o = this.addMapQuestOSM();
                    break;
                case "mapBoxNaturalEarth":
                    o = this.addMapBoxNaturalEarth();
                    break;
                case "stamenToner":
                    o = this.addStamenToner();
                    break;
                case "googleStreets":
                    o = this.addGoogleStreets();
                    break;
                case "googleHybrid":
                    o = this.addGoogleHybrid();
                    break;
                case "googleSatellite":
                    o = this.addGoogleSatellite();
                    break;
                case "googleTerrain":
                    o = this.addGoogleTerrain();
                    break;
                case "bingRoad":
                    o = this.addBing("Road");
                    break;
                case "bingAerial":
                    o = this.addBing("Aerial");
                    break;
                case "bingAerialWithLabels":
                    o = this.addBing("AerialWithLabels");
                    break;
                case "yandex":
                    o = this.addYandex();
                    break;
            }
            return o;
        };
        //ol2, ol3 and leaflet
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
                var l = createTileLayer(layers[i], defaults);
                l.baseLayer = defaults.isBaseLayer;
                switch (MAPLIB) {
                    case "ol2":
                        this.map.addLayer(l);
                        break;
                    case "ol3":
                        this.map.addLayer(l);
                        break;
                    case "leaflet":
                        if (defaults.isBaseLayer === true) {
                            lControl.addBaseLayer(l);
                        }
                        else {
                            lControl.addOverlay(l);
                        }
                        if (defaults.visibility === true) {
                            this.showLayer(layers[i]);
                        }
                        break;
                }
                layersArr.push(l);
            }
            return layersArr;
        };


        this.removeTileLayerByName = function (name) {
            var arr = this.map.getLayersByName(name);
            this.map.removeLayer(arr[0]);
        };
        //ol2 and leaflet
        this.addGeoJsonStore = function (store) {
            // set the parent map obj
            store.map = this;
            switch (MAPLIB) {
                case "ol2":
                    this.map.addLayers([store.layer]);
                    break;
                case "ol3":
                    this.map.addLayer(store.layer);
                    break;
                case "leaflet":
                    lControl.addOverlay(store.layer);
                    this.showLayer(store.layer.id);
                    break;
            }
        };
        //ol2 and leaflet
        this.removeGeoJsonStore = function (store) {
            switch (MAPLIB) {
                case "ol2":
                    this.map.removeLayer(store.layer);
                    break;
                case "leaflet":
                    this.map.removeLayer(store.layer);
                    break;
            }

        };
        //ol2, ol3 and leaflet
        this.hideLayer = function (name) {
            switch (MAPLIB) {
                case "ol2":
                    this.getLayersByName(name).setVisibility(false);
                    break;
                case "ol3":
                    this.getLayersByName(name).set("visible", false);
                    break;
                case "leaflet":
                    this.map.removeLayer(this.getLayersByName(name));
                    break;
            }
        };
        //ol2, ol3 and leaflet
        this.showLayer = function (name) {
            switch (MAPLIB) {
                case "ol2":
                    this.getLayersByName(name).setVisibility(true);
                    break;
                case "ol3":
                    this.getLayersByName(name).set("visible", true);
                    break;
                case "leaflet":
                    this.getLayersByName(name).addTo(this.map);
                    break;
            }
        };
        //ol2
        this.getLayerById = function (id) {
            return this.map.getLayer(id);
        }
        //ol2, ol3 and leaflet (rename to getLayerByName)
        this.getLayersByName = function (name) {
            var l;
            switch (MAPLIB) {
                case "ol2":
                    l = this.map.getLayersByName(name)[0];
                    break;
                case "ol3":
                    for (var i = 0; i < this.map.getLayers().getLength(); i++) {
                        if (this.map.getLayers().a[i].id === name) {
                            l = this.map.getLayers().a[i];
                        }
                    }
                    break;
                case "leaflet":
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
        //ol2
        this.hideAllTileLayers = function () {
            for (var i = 0; i < this.map.layers.length; i++) {
                if (this.map.layers[i].isBaseLayer === false && this.map.layers[i].CLASS_NAME === "OpenLayers.Layer.WMS") {
                    this.map.layers[i].setVisibility(false);
                }
            }
        };
        //ol2, ol3 and leaflet
        // Output map coordinates (900913)
        this.getCenter = function () {
            var point;
            switch (MAPLIB) {
                case "ol2":
                    point = this.map.center;
                    return {
                        x: point.lon,
                        y: point.lat
                    };
                    break;
                case "ol3":
                    point = this.map.getView().getCenter();
                    return {
                        x: point[0],
                        y: point[1]
                    };
                    break;
                case "leaflet":
                    point = this.map.getCenter();
                    var p = transformPoint(point.lng, point.lat, "EPSG:4326", "EPSG:900913");
                    return {
                        x: p.x,
                        y: p.y,
                        lon: point.lng,
                        lat: point.lat
                    };
                    break;
            }
        };
        //ol2
        this.getExtent = function () {
            var mapBounds, bounds;
            switch (MAPLIB) {
                case "ol2":
                    mapBounds = this.map.getExtent();
                    bounds = mapBounds.toArray();
                    break;
                case "ol3":

                    break;
                case "leaflet":
                    mapBounds = this.map.getBounds().toBBoxString().split(",");
                    bounds = {left: mapBounds[0], right: mapBounds[2], top: mapBounds[3], bottom: mapBounds[1]};
                    break;
            }
            return (bounds);
        };

        //ol2
        this.getBbox = function () {
            return this.map.getExtent().toString();
        };
        // Leaflet
        this.locate = function () {
            this.map.locate({
                setView: true
            });
        }
        //ol2 and leaflet
        this.addLayerFromWkt = function (elements) { // Take 4326
            switch (MAPLIB) {
                case "ol2":
                    this.removeQueryLayers();
                    var features, geometry, transformedFeature, wkt = new OpenLayers.Format.WKT;
                    for (var i = 0; i < elements.length; i++) {
                        features = wkt.read(elements[i]);
                        queryLayers[i] = new OpenLayers.Layer.Vector(null, {
                            displayInLayerSwitcher: false,
                            styleMap: new OpenLayers.StyleMap({'default': new OpenLayers.Style({
                                strokeColor: '#000000',
                                strokeWidth: 3,
                                fillOpacity: 0,
                                strokeOpacity: 0.8
                            })})
                        });
                        geometry = features.geometry.transform(
                            new OpenLayers.Projection('EPSG:4326'),
                            new OpenLayers.Projection('EPSG:900913')
                        );
                        transformedFeature = new OpenLayers.Feature.Vector(geometry, {});
                        queryLayers[i].addFeatures([transformedFeature]);
                        this.map.addLayers([queryLayers[i]]);
                    }
                    break;
                case "ol3":

                    break;
                case "leaflet":
                    this.removeQueryLayers();
                    var wkt = new Wkt.Wkt();
                    for (var i = 0; i < elements.length; i++) {
                        wkt.read(elements[i]);
                        queryLayers[i] = wkt.toObject({
                            color: '#000000',
                            weight: 3,
                            opacity: 0.8,
                            fillOpacity: 0
                        }).addTo(this.map);
                    }
                    break;
            }
        };
        // ol2 and leaflet
        this.removeQueryLayers = function () {
            switch (MAPLIB) {
                case "ol2":
                    try {
                        for (var i = 0; i < queryLayers.length; i++) {
                            //queryLayers[i].destroy();
                            this.map.removeLayer(queryLayers[i])
                        }
                    }
                    catch (e) {
                    }
                    break;
                case "ol3":
                    break;
                case "leaflet":
                    try {
                        for (var i = 0; i < queryLayers.length; i++) {
                            this.map.removeLayer(queryLayers[i]);
                        }
                    }
                    catch (e) {
                    }
                    break;
            }

        };
        // ol2, ol3 and leaflet
        this.on = function (event, callBack) {
            switch (MAPLIB) {
                case "ol2":
                    if (event === "click") {
                        OpenLayers.Control.Click = OpenLayers.Class(OpenLayers.Control, {
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
                                callBack(e)
                            }
                        });
                        var click = new OpenLayers.Control.Click()
                        this.map.addControl(click);
                        click.activate();
                    }
                    if (event === "moveend") {
                        this.map.events.register("moveend", null, callBack);
                    }
                    break;
                case "ol3":
                    this.map.on(event, callBack);
                    break;
                case "leaflet":
                    this.map.on(event, callBack);
                    break;
            }
        };
    };
    // ol2, ol3 and leaflet
    // Input map coordinates (900913)
    clickEvent = function (e, map) {
        this.getCoordinate = function () {
            var point;
            switch (MAPLIB) {
                case "ol2":
                    point = map.map.getLonLatFromPixel(e.xy);
                    return {
                        x: point.lon,
                        y: point.lat
                    };
                    break;
                case "ol3":
                    point = e.coordinate;
                    return {
                        x: point[0],
                        y: point[1]
                    };
                    break;
                case "leaflet":
                    point = e.latlng;
                    var p = transformPoint(point.lng, point.lat, "EPSG:4326", "EPSG:900913");
                    return {
                        x: p.x,
                        y: p.y
                    };
                    break;
            }
        };
    };
    transformPoint = function (lat, lon, s, d) {
        var p = [];
        if (typeof Proj4js === "object") {
            var source = new Proj4js.Proj(s);    //source coordinates will be in Longitude/Latitude
            var dest = new Proj4js.Proj(d);
            var p = new Proj4js.Point(lat, lon);
            Proj4js.transform(source, dest, p);
        }
        else {
            p.x = null;
            p.y = null;
        }
        return p;
    };

    return {
        geoJsonStore: geoJsonStore,
        sqlStore: sqlStore,
        tileLayer: tileLayer,
        elasticStore: elasticStore,
        tweetStore: tweetStore,
        map: map,
        MAPLIB: MAPLIB,
        clickEvent: clickEvent,
        transformPoint: transformPoint,
        urlVars: (function getUrlVars() {
            var mapvars = {};
            var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function (m, key, value) {
                mapvars[key] = value;
            });
            return mapvars;
        })(),
        pathName: window.location.pathname.split("/"),
        urlHash: window.location.hash,
        OSM: OSM,
        MAPQUESTOSM: MAPQUESTOSM,
        MAPBOXNATURALEARTH: MAPBOXNATURALEARTH,
        STAMENTONER: STAMENTONER,
        GOOGLESTREETS: GOOGLESTREETS,
        GOOGLEHYBRID: GOOGLEHYBRID,
        GOOGLESATELLITE: GOOGLESATELLITE,
        GOOGLETERRAIN: GOOGLETERRAIN,
        BINGROAD: BINGROAD,
        BINGAERIAL: BINGAERIAL,
        BINGAERIALWITHLABELS: BINGAERIALWITHLABELS
    };
}());
