/*
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

/*global Ext:false */
/*global $:false */
/*global jQuery:false */
/*global OpenLayers:false */
/*global ol:false */
/*global L:false */
/*global google:false */
/*global GeoExt:false */
/*global mygeocloud_ol:false */
/*global schema:false */
/*global document:false */
/*global window:false */

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
        cartoDbStore,
        sqlStore,
        tweetStore,
        elasticStore,
        tileLayer,
        createTileLayer,
        createTMSLayer,
        clickEvent,
        transformPoint,
        base64,
        MAPLIB,
        host,
        OSM = "osm",
        MAPQUESTOSM = "mapQuestOSM",
        MAPBOXNATURALEARTH = "mapBoxNaturalEarth",
        STAMENTONER = "stamenToner",
        STAMENTONERLITE = "stamenTonerLite",
        GOOGLESTREETS = "googleStreets",
        GOOGLEHYBRID = "googleHybrid",
        GOOGLESATELLITE = "googleSatellite",
        GOOGLETERRAIN = "googleTerrain",
        BINGROAD = "bingRoad",
        BINGAERIAL = "bingAerial",
        BINGAERIALWITHLABELS = "bingAerialWithLabels",
        DTKSKAERMKORT_25832 = "dtkSkaermkort@25832",
        DTKSKAERMKORT = "dtkSkaermkort",
        DTKSKAERMKORTDAEMPET = "dtkSkaermkortDaempet",
        GEODKBRIGHT = "geodkBright",
        DIGITALGLOBE = "DigitalGlobe:Imagery",
        HERENORMALDAYGREY = "hereNormalDayGrey",
        HERENORMALNIGHTGREY = "hereNormalNightGrey",
        attribution = (window.mapAttribution === undefined) ? "Powered by <a target='_blank' href='//www.mapcentia.com/en/geocloud/geocloud.htm'>MapCentia GC2</a> " : window.mapAttribution,
        resolutions = [156543.033928, 78271.516964, 39135.758482, 19567.879241, 9783.9396205,
            4891.96981025, 2445.98490513, 1222.99245256, 611.496226281, 305.748113141, 152.87405657,
            76.4370282852, 38.2185141426, 19.1092570713, 9.55462853565, 4.77731426782, 2.38865713391,
            1.19432856696, 0.597164283478, 0.298582141739, 0.149291070869, 0.074645535],
        googleMapAdded = {}, yandexMapAdded = {};
    // Try to set host from script if not set already
    if (typeof window.geocloud_host === "undefined") {
        window.geocloud_host = host = (scriptSource.charAt(0) === "/") ? "" : scriptSource.split("/")[0] + "//" + scriptSource.split("/")[2];
    }
    host = window.geocloud_host;
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
    }
    //Only if loaded in script tag
    if (document.readyState === "loading") {
        if (typeof jQuery === "undefined") {
            document.write("<script src='//ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js'><\/script>");
        }
    }
    var setHost = function (str) {
        host = str;
    }
    // Helper for extending classes
    extend = function (ChildClass, ParentClass) {
        ChildClass.prototype = new ParentClass();
    };
    var STOREDEFAULTS = {
        styleMap: null,
        visibility: true,
        lifetime: 0,
        host: host,
        uri: "/api/v2/sql",
        db: null,
        sql: null,
        q: null,
        name: "Vector",
        id: null,
        rendererOptions: {zIndexing: true},
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
        loading: function () {
        },
        index: "",
        type: "",
        size: 100,
        clientEncoding: "UTF8",
        async: true,
        jsonp: false,
        method: "GET",
        clickable: true,
        error: function () {
        },
        key: null,
        base64: true
    };
    // Base class for stores
    storeClass = function () {
        //this.defaults = STOREDEFAULTS;
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
            this.loading = this.defaults.loading;
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
                        onEachFeature: this.defaults.onEachFeature,
                        clickable: this.defaults.clickable
                    });
                    this.layer.id = this.defaults.name;
                    break;
            }
        };
        this.geoJSON = null;
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
        var prop, me = this, map, sql, xhr = {
            abort: function () {/* stub */
            }
        };
        this.defaults = $.extend({}, STOREDEFAULTS);
        if (config) {
            for (prop in config) {
                this.defaults[prop] = config[prop];
            }
        }
        this.init();
        this.name = this.defaults.name;
        this.id = this.defaults.id;
        this.sql = this.defaults.sql;
        this.db = this.defaults.db;
        this.host = this.defaults.host.replace("cdn.", "");
        this.onLoad = this.defaults.onLoad;
        this.loading = this.defaults.loading;
        this.dataType = this.defaults.dataType;
        this.async = this.defaults.async;
        this.jsonp = this.defaults.jsonp;
        this.method = this.defaults.method;
        this.uri = this.defaults.uri;
        this.base64 = this.defaults.base64;
        this.load = function (doNotShowAlertOnError) {

            try {
                me.abort();
            } catch (e) {
                console.error(e.message);
            }

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
            me.loading();
            xhr = $.ajax({
                dataType: (this.defaults.jsonp) ? 'jsonp' : 'json',
                async: this.defaults.async,
                data: 'q=' + (this.base64 ? base64.encode(sql) + "&base64=true" : encodeURIComponent(sql)) + '&srs=' + this.defaults.projection + '&lifetime=' + this.defaults.lifetime + '&client_encoding=' + this.defaults.clientEncoding + '&key=' + this.defaults.key,
                jsonp: (this.defaults.jsonp) ? 'jsonp_callback' : false,
                url: this.host + this.uri + '/' + this.db,
                type: this.defaults.method,
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
                        } else {
                            me.geoJSON = null;
                        }
                    }
                },
                error: this.defaults.error,
                complete: function () {
                    me.onLoad(me);
                }

            });
            return this.layer;
        };
        this.abort = function () {
            xhr.abort();
        }
    };
    cartoDbStore = function (config) {
        var prop, me = this, map, sql, xhr;
        this.defaults = $.extend({}, STOREDEFAULTS);
        if (config) {
            for (prop in config) {
                this.defaults[prop] = config[prop];
            }
        }
        this.init();
        this.name = this.defaults.name;
        this.id = this.defaults.id;
        this.sql = this.defaults.sql;
        this.db = this.defaults.db;
        this.onLoad = this.defaults.onLoad;
        this.dataType = this.defaults.dataType;
        this.async = this.defaults.async;
        this.jsonp = this.defaults.jsonp;
        this.method = this.defaults.method;
        this.load = function (doNotShowAlertOnError) {
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
            xhr = $.ajax({
                dataType: (this.defaults.jsonp) ? 'jsonp' : 'json',
                async: this.defaults.async,
                data: 'format=geojson&q=' + encodeURIComponent(sql) + '&srs=' + this.defaults.projection + '&lifetime=' + this.defaults.lifetime + "&srs=" + this.defaults.projection + '&client_encoding=' + this.defaults.clientEncoding,
                jsonp: (this.defaults.jsonp) ? 'callback' : false,
                url: '//' + this.db + '.cartodb.com' + '/api/v2/sql',
                type: this.defaults.method,
                success: function (response) {
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
                    } else {
                        me.geoJSON = null;
                    }
                },
                error: this.defaults.error,
                complete: function () {
                    me.onLoad();
                }

            });
            return this.layer;
        };
        this.abort = function () {
            xhr.abort();
        }
    };

    tweetStore = function (config) {
        var prop, me = this;
        this.defaults = $.extend({}, STOREDEFAULTS);
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
                q = q.replace(/\{bbox\}/g, map.getExtent().toString());
            } catch (e) {
            }
            $.ajax({
                dataType: 'jsonp',
                data: 'search=' + encodeURIComponent(q),
                jsonp: 'jsonp_callback',
                url: this.defaults.host + '/api/v1/twitter/' + this.db,
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
        this.defaults = $.extend({}, STOREDEFAULTS);
        if (config) {
            for (prop in config) {
                this.defaults[prop] = config[prop];
            }
        }
        this.init();
        this.q = this.defaults.q;
        this.db = this.defaults.db;
        this.host = this.defaults.host.replace("cdn.", "");
        this.onLoad = this.defaults.onLoad;
        this.total = 0;
        this.size = this.defaults.size;
        this.dataType = this.defaults.dataType;
        this.async = this.defaults.async;
        this.jsonp = this.defaults.jsonp;
        this.method = this.defaults.method;
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
                method: this.method,
                dataType: (this.jsonp) ? 'jsonp' : 'json',
                async: this.async,
                jsonp: (this.jsonp) ? 'jsonp_callback' : false,
                data: 'q=' + encodeURIComponent(q) + "&size=" + this.size,
                url: this.defaults.host + '/api/v1/elasticsearch/search/' + this.defaults.db + "/" + this.defaults.index + "/" + this.defaults.type,
                success: function (response) {
                    if (typeof response.error !== "undefined") {
                        return false;
                    }
                    var features = [], geoJson = {type: "FeatureCollection"};
                    me.total = response.hits.total;
                    $.each(response.hits.hits, function (i, v) {
                        features.push(v._source);
                    });
                    geoJson.features = features;
                    if (response.features !== null) {
                        me.geoJSON = geoJson;
                        switch (MAPLIB) {
                            case "ol2":
                                me.layer.addFeatures(new OpenLayers.Format.GeoJSON({
                                        internalProjection: new OpenLayers.Projection("EPSG:3857"),
                                        externalProjection: new OpenLayers.Projection("EPSG:4326")
                                    }
                                ).read(geoJson));
                                break;
                            case "leaflet":
                                me.layer.addData(geoJson);
                                break;
                        }
                    }

                },
                complete: function (response) {
                    me.onLoad(response);
                }
            });
            return this.layer;
        };
    };
    // Extend store classes
    extend(sqlStore, storeClass);
    extend(tweetStore, storeClass);
    extend(elasticStore, storeClass);
    extend(cartoDbStore, storeClass);

    //ol2, ol3 and leaflet
    tileLayer = function (config) {
        var prop;
        var defaults = {
            host: host,
            layer: null,
            db: null,
            singleTile: false,
            opacity: 1,
            wrapDateLine: true,
            tileCached: true,
            name: null,
            isBaseLayer: false,
            resolutions: resolutions

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
            url = defaults.host + "/wms/" + defaults.db + "/" + parts[0] + "?";
            urlArray = [url];
        } else {
            url = defaults.host + "/mapcache/" + defaults.db + "/wms";
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
                    subdomains: ["cdn1", "cdn2", "cdn3"],
                    maxZoom: defaults.maxZoom,
                    tileSize: defaults.tileSize
                });
                l.id = layer;
                if (defaults.loadEvent) {
                    l.on("load", defaults.loadEvent);
                }
                if (defaults.loadingEvent) {
                    l.on("loading", defaults.loadingEvent);
                }
                break;
        }
        return l;
    };

    /**
     * ol2 and leaflet
     * @param layer
     * @param defaults
     * @param subDomains
     * @returns {*}
     */
    createTMSLayer = function (layer, defaults) {
        var l, url, urlArray, url1, url2, url3, config, usingSubDomains = false;
        url = defaults.host + "/mapcache/" + defaults.db + "/tms/";
        url1 = url;
        url2 = url;
        url3 = url;
        //If host has cdn. as subdomain, when create use cdn1, cdn2, cdn3
        if (typeof defaults.subdomains === "undefined" || defaults.subdomains === null) {
            // For ol2
            urlArray = [url1.replace("cdn.", "cdn1."), url2.replace("cdn.", "cdn2."), url3.replace("cdn.", "cdn3.")];
            // For leaflet
            url = url.replace("cdn.", "{s}.");
        } else {
            usingSubDomains = true;
            // Only Leaflet
            url = url.replace("//", "//{s}.");
        }
        switch (MAPLIB) {
            case "ol2":
                l = new OpenLayers.Layer.TMS(defaults.name, urlArray, {
                    layername: layer,
                    type: 'png',
                    resolutions: defaults.resolutions,
                    isBaseLayer: defaults.isBaseLayer
                });
                l.id = layer;
                break;
            case "ol3":

                break;
            case "leaflet":
                config = {
                    tms: true,
                    attribution: defaults.attribution,
                    maxZoom: defaults.maxZoom,
                    maxNativeZoom: defaults.maxNativeZoom,
                    tileSize: 256,
                    ran: function () {
                        return Math.random();
                    }
                };
                if (usingSubDomains) {
                    config.subdomains = defaults.subdomains;
                }
                l = new L.TileLayer(url + "1.0.0/" + layer + "" + "/{z}/{x}/{y}.png?{ran}", config);
                l.id = layer;
                if (defaults.loadEvent) {
                    l.on("load", defaults.loadEvent);
                }
                if (defaults.loadingEvent) {
                    l.on("loading", defaults.loadingEvent);
                }
                break;
        }
        return l;
    };

    // Set map constructor
    map = function (config) {
        var prop, lControl, queryLayers = [],
            defaults = {
                numZoomLevels: 20,
                projection: "EPSG:900913",
                fadeAnimation: true,
                zoomAnimation: true,
                showLayerSwitcher: false,
                // maxExtent: '-20037508.34, -20037508.34, 20037508.34, 20037508.34',
                resolutions: resolutions,
                editable: true
            };
        if (config) {
            for (prop in config) {
                defaults[prop] = config[prop];
            }
        }
        //Load css
        if (MAPLIB === "leaflet") {
            // The css
            $('<link/>').attr({
                rel: 'stylesheet',
                type: 'text/css',
                href: window.geocloud_host + '/js/leaflet/leaflet-0.7.7.css'
            }).appendTo('head');
        }
        this.bingApiKey = null;
        this.digitalGlobeKey = null;
        //ol2, ol3
        // extent array
        this.zoomToExtent = function (extent, closest) {
            var p1, p2;
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
                        p1 = transformPoint(extent[0], extent[1], "EPSG:900913", "EPSG:4326");
                        p2 = transformPoint(extent[2], extent[3], "EPSG:900913", "EPSG:4326");
                        this.map.fitBounds([
                            [p1.y, p1.x],
                            [p2.y, p2.x]
                        ]);
                    }
                    break;
            }
        };
        this.zoomToExtentOfgeoJsonStore = function (store, maxZoom) {
            switch (MAPLIB) {
                case "ol2":
                    this.map.zoomToExtent(store.layer.getDataExtent());
                    break;
                case "leaflet":
                    this.map.fitBounds(store.layer.getBounds(), {maxZoom: maxZoom});
                    break;
            }
        };
        this.getBaseLayers = function (removeMapReference) {
            var layerArr = [];
            switch (MAPLIB) {
                case "ol2":
                    for (var i = 0; i < this.map.layers.length; i++) {
                        if (this.map.layers[i].isBaseLayer === true) {
                            //console.log(this.map.layers[i]);
                            if (removeMapReference) {
                                this.map.layers[i].map = null;
                            }
                            layerArr.push(this.map.layers[i]);
                        }
                    }
                    break;
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
        this.getActiveBaseLayer = function () {
            var layers = lControl._layers
            for (var layerId in layers) {
                if (layers.hasOwnProperty(layerId)) {
                    var layer = layers[layerId]
                    if (!layer.overlay && lControl._map.hasLayer(layer.layer)) {
                        return layer
                    }
                }
            }
            throw new Error('Control doesn\'t have any active base layer!')
        }
        //ol2, ol3 and leaflet
        this.getVisibleLayers = function (getBaseLayers) {
            getBaseLayers = (getBaseLayers === true) ? true : false;
            var layerArr = [], i;
            switch (MAPLIB) {
                case "ol2":
                    for (i = 0; i < this.map.layers.length; i++) {
                        if ((this.map.layers[i].isBaseLayer === getBaseLayers || this.map.layers[i].isBaseLayer === false || this.map.layers[i].isBaseLayer === null) && this.map.layers[i].visibility === true && this.map.layers[i].CLASS_NAME === "OpenLayers.Layer.WMS") {
                            layerArr.push(this.map.layers[i].params.LAYERS);
                        }
                    }
                    break;
                case "ol3":
                    for (i = 0; i < this.map.getLayers().getLength(); i++) {

                        if (this.map.getLayers().a[i].e.visible === true && this.map.getLayers().a[i].baseLayer !== true) {
                            layerArr.push(this.map.getLayers().a[i].id);
                        }
                    }
                    break;
                case "leaflet":
                    var layers = this.map._layers;
                    for (var key in layers) {
                        if (layers.hasOwnProperty(key)) {
                            if ((layers[key].baseLayer === getBaseLayers || layers[key].baseLayer === false || layers[key].baseLayer === null) && typeof layers[key]._tiles === "object") {
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
            var layerArr = [], i;
            switch (MAPLIB) {
                case "ol2":
                    for (i = 0; i < this.map.layers.length; i++) {
                        if (this.map.layers[i].isBaseLayer === false && this.map.layers[i].visibility === true && this.map.layers[i].CLASS_NAME === "OpenLayers.Layer.WMS") {
                            layerArr.push(this.map.layers[i].name);
                        }
                    }
                    break;
                case "ol3":
                    for (i = 0; i < this.map.getLayers().getLength(); i++) {
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
            if (layerArr.length > 0) {
                return layerArr.join(",");
            }
            else {
                return false;
            }
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
                var olControls = [
                    new OpenLayers.Control.Zoom(),
                    new OpenLayers.Control.Attribution(),
                    new OpenLayers.Control.TouchNavigation({
                        dragPanOptions: {
                            enableKinetic: true
                        }
                    })
                ];
                if (defaults.showLayerSwitcher) {
                    olControls.push(new OpenLayers.Control.LayerSwitcher());
                }
                this.map = new OpenLayers.Map(defaults.el, {
                    //theme: null,
                    controls: olControls,
                    numZoomLevels: defaults.numZoomLevels,
                    resolutions: defaults.resolutions,
                    projection: defaults.projection,
                    maxExtent: defaults.maxExtent,
                    eventListeners: defaults.eventListeners
                });
                break;
            case "ol3":
                this.map = new ol.Map({
                    target: defaults.el,
                    view: new ol.View2D({})
                    //renderers: ol.RendererHints.createFromQueryData()
                });
                break;
            case "leaflet":
                this.map = new L.map(defaults.el, defaults);
                lControl = L.control.layers([], []);
                this.layerControl = lControl;
                this.map.addControl(lControl);
                this.map.attributionControl.setPrefix(attribution);
                break;
        }
        var _map = this.map;
        this.addLayer = function (layer, name, baseLayer) {
            if (baseLayer) {
                lControl.addBaseLayer(layer, name);
            } else {
                lControl.addOverlay(layer, name);
            }
        }
        //ol2, ol3 and leaflet
        // MapQuest OSM doesn't work anymore. Switching to OSM.
        this.addMapQuestOSM = function () {
            switch (MAPLIB) {
                case "ol2":
                    this.mapQuestOSM = new OpenLayers.Layer.OSM("mapQuestOSM");
                    this.mapQuestOSM.wrapDateLine = false;
                    this.map.addLayer(this.mapQuestOSM);
                    this.mapQuestOSM.setVisibility(false);
                    break;
                case "ol3":
                    this.mapQuestOSM = new ol.layer.TileLayer({
                        source: new ol.source.OSM(),
                        visible: false
                    });
                    this.map.addLayer(this.mapQuestOSM);
                    break;
                case "leaflet":
                    this.mapQuestOSM = new L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                        attribution: "&copy; <a target='_blank' href='https://www.openstreetmap.org/copyright'>OpenStreetMap</a> contributors",
                        maxZoom: 20,
                        maxNativeZoom: 18
                    });
                    lControl.addBaseLayer(this.mapQuestOSM, "MapQuest OSM");
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
                    this.mapQuestAerial = new OpenLayers.Layer.OSM("mapQuestAerial", ["https://oatile1.mqcdn.com/tiles/1.0.0/sat/${z}/${x}/${y}.jpg", "https://oatile2.mqcdn.com/tiles/1.0.0/sat/${z}/${x}/${y}.jpg", "https://oatile3.mqcdn.com/tiles/1.0.0/sat/${z}/${x}/${y}.jpg", "https://oatile4.mqcdn.com/tiles/1.0.0/sat/${z}/${x}/${y}.jpg"]);
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
                    break;
                case "leaflet":
                    this.mapQuestAerial = new L.tileLayer("https://oatile1.mqcdn.com/tiles/1.0.0/sat/{z}/{x}/{y}.jpg", {
                        maxZoom: 20,
                        maxNativeZoom: 18
                    });
                    lControl.addBaseLayer(this.mapQuestAerial, "Map Quest Aerial");
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
                    this.osm = new L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                        attribution: "&copy; <a target='_blank' href='https://www.openstreetmap.org/copyright'>OpenStreetMap</a> contributors",
                        maxZoom: 21,
                        maxNativeZoom: 18
                    });
                    lControl.addBaseLayer(this.osm, "OSM");
                    break;
            }
            this.osm.baseLayer = true;
            this.osm.id = "osm";
            return (this.osm);
        };
        //ol2, ol3 and leaflet
        this.addStamen = function (type) {
            var name, prettyName;
            switch (type) {
                case "toner":
                    name = "stamenToner";
                    prettyName = "Stamen Toner";
                    break;
                case "toner-lite":
                    name = "stamenTonerLite";
                    prettyName = "Stamen Toner Lite";
                    break;
            }
            switch (MAPLIB) {
                case "ol2":
                    this.stamenToner = new OpenLayers.Layer.Stamen(type);
                    this.stamenToner.name = name;
                    this.map.addLayer(this.stamenToner);
                    this.stamenToner.setVisibility(false);
                    break;
                case "ol3":
                    this.stamenToner = new ol.layer.TileLayer({
                        source: new ol.source.Stamen({
                            layer: type
                        }),
                        visible: false
                    });
                    this.map.addLayer(this.stamenToner);
                    break;
                case "leaflet":
                    try {
                        this.stamenToner = new L.StamenTileLayer(type);
                        lControl.addBaseLayer(this.stamenToner, prettyName);
                    }
                    catch (e) {
                    }
                    break;
            }
            this.stamenToner.baseLayer = true;
            this.stamenToner.id = name;
            return (this.stamenToner);
        };
        //ol2 and leaflet
        this.addMapBoxNaturalEarth = function () {
            switch (MAPLIB) {
                case "ol2":
                    this.mapBoxNaturalEarth = new OpenLayers.Layer.XYZ("mapBoxNaturalEarth", [
                        "//a.tiles.mapbox.com/v3/mapbox.natural-earth-hypso-bathy/${z}/${x}/${y}.png",
                        "//b.tiles.mapbox.com/v3/mapbox.natural-earth-hypso-bathy/${z}/${x}/${y}.png",
                        "//c.tiles.mapbox.com/v3/mapbox.natural-earth-hypso-bathy/${z}/${x}/${y}.png",
                        "//d.tiles.mapbox.com/v3/mapbox.natural-earth-hypso-bathy/${z}/${x}/${y}.png"
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
                    this.mapBoxNaturalEarth = new L.tileLayer("https://a.tiles.mapbox.com/v3/mapbox.natural-earth-hypso-bathy/{z}/{x}/{y}.png");
                    lControl.addBaseLayer(this.mapBoxNaturalEarth, "Mapbox Natural Earth");
                    break;
            }
            this.mapBoxNaturalEarth.baseLayer = true;
            this.mapBoxNaturalEarth.id = "mapBoxNaturalEarth";
            return (this.mapBoxNaturalEarth);
        };
        //ol2 and leaflet
        this.addGoogle = function (type) {
            var l, name, prettyName, me = this;
            switch (type) {
                case "ROADMAP":
                    name = "googleStreets";
                    prettyName = "Google Streets";
                    break;
                case "HYBRID":
                    name = "googleHybrid";
                    prettyName = "Google Hybrid";
                    break;
                case "SATELLITE":
                    name = "googleSatellite";
                    prettyName = "Google Satellite";
                    break;
                case "TERRAIN":
                    name = "googleTerrain";
                    prettyName = "Google Terrain";
                    break;
            }
            // Load Google Maps API and make sure its not loaded more than once
            if (typeof window.GoogleMapsDirty === "undefined" && !(typeof google !== "undefined" && typeof google.maps !== "undefined")) {
                window.GoogleMapsDirty = true;
                jQuery.getScript("https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false&libraries=places&callback=gc2SetLGoogle");
                // Google Maps API is loaded
            } else if (typeof window.GoogleMapsDirty === "undefined") {
                window.gc2SetLGoogle();
            }

            (function poll() {
                if (typeof google !== "undefined" && typeof google.maps !== "undefined" && typeof google.maps.Map !== "undefined") {
                    switch (MAPLIB) {
                        case "ol2":
                            l = new OpenLayers.Layer.Google(name, {
                                type: google.maps.MapTypeId[type],
                                wrapDateLine: true,
                                numZoomLevels: 20,
                                title: prettyName
                            });
                            me.map.addLayer(l);
                            l.setVisibility(false);
                            l.baseLayer = true;
                            l.id = name;
                            break;
                        case "leaflet":
                            l = new L.Google(type);
                            l.baseLayer = true;
                            lControl.addBaseLayer(l, prettyName);
                            l.id = name;
                            break;
                    }
                    googleMapAdded[name] = true;
                    return l;
                } else {
                    setTimeout(poll, 50);
                }
            }());
        };
        //ol2 and leaflet
        this.addBing = function (type) {
            var l, name, prettyName;
            switch (type) {
                case "Road":
                    name = "bingRoad";
                    prettyName = "Bing Road";
                    break;
                case "Aerial":
                    name = "bingAerial";
                    prettyName = "Bing Aerial";
                    break;
                case "AerialWithLabels":
                    name = "bingAerialWithLabels";
                    prettyName = "Bing Aerial w Labels";
                    break;
            }
            switch (MAPLIB) {
                case "ol2":
                    l = new OpenLayers.Layer.Bing({
                        name: name,
                        wrapDateLine: true,
                        key: window.bingApiKey || this.bingApiKey,
                        type: type
                    });
                    this.map.addLayer(l);
                    l.setVisibility(false);
                    l.baseLayer = true;
                    l.id = name;
                    return (l);
                case "leaflet":
                    l = new L.BingLayer(this.bingApiKey || window.bingApiKey, {
                        type: type,
                        maxZoom: 21,
                        maxNativeZoom: 18
                    });
                    l.baseLayer = true;
                    lControl.addBaseLayer(l, prettyName);
                    l.id = name;
                    return (l);
            }
        };
        //ol2 and leaflet
        this.addDigitalGlobe = function (type) {
            var l, name, prettyName, key = this.digitalGlobeKey;
            switch (type) {
                case "DigitalGlobe:Imagery":
                    name = "DigitalGlobe:ImageryTileService";
                    prettyName = "Digital Globe";
                    break;
            }
            switch (MAPLIB) {
                case "ol2":
                    l = new OpenLayers.Layer.XYZ(
                        type,
                        "https://services.digitalglobe.com/earthservice/wmtsaccess?CONNECTID=" + key + "&Service=WMTS&REQUEST=GetTile&Version=1.0.0&Format=image/png&Layer=" + name + "&TileMatrixSet=EPSG:3857&TileMatrix=EPSG:3857:${z}&TileRow=${y}&TileCol=${x}",
                        {
                            resolutions: resolutions
                        }
                    );
                    this.map.addLayer(l);
                    l.setVisibility(false);
                    break;
                case "leaflet":
                    l = new L.TileLayer("https://services.digitalglobe.com/earthservice/wmtsaccess?CONNECTID=" + key + "&Service=WMTS&REQUEST=GetTile&Version=1.0.0&Format=image/png&Layer=" + name + "&TileMatrixSet=EPSG:3857&TileMatrix=EPSG:3857:{z}&TileRow={y}&TileCol={x}", {
                        maxZoom: 21
                    });
                    lControl.addBaseLayer(l, prettyName);
                    break;
            }
            l.baseLayer = true;
            l.id = type;
            return (l);

        };
        //ol2 and leaflet
        this.addHere = function (type) {
            var l, name, prettyName;
            switch (type) {
                case "hereNormalNightGrey":
                    name = "normal.night.grey";
                    prettyName = "HERE Normal Night Grey"
                    break;
                case "hereNormalDayGrey":
                    name = "normal.day.grey";
                    prettyName = "HERE Normal day Grey"
                    break;
            }
            switch (MAPLIB) {
                case "ol2":
                    l = new OpenLayers.Layer.XYZ(
                        type,
                        "https://1.base.maps.cit.api.here.com/maptile/2.1/maptile/newest/" + name + "/${z}/${x}/${y}/256/png8?app_id=" + window.gc2Options.hereApp.App_Id + "&app_code=" + window.gc2Options.hereApp.App_Code,
                        {
                            attribution: "&copy; Nokia</span>&nbsp;<a href='https://maps.nokia.com/services/terms' target='_blank' title='Terms of Use' style='color:#333;text-decoration: underline;'>Terms of Use</a></div> <img src='//api.maps.nokia.com/2.2.4/assets/ovi/mapsapi/by_here.png' border='0'>",
                            resolutions: resolutions
                        }
                    );
                    this.map.addLayer(l);
                    l.setVisibility(false);
                    break;
                case "leaflet":
                    l = new L.TileLayer("https://{s}.base.maps.cit.api.here.com/maptile/2.1/maptile/newest/" + name + "/{z}/{x}/{y}/256/png8?app_id=" + window.gc2Options.hereApp.App_Id + "&app_code=" + window.gc2Options.hereApp.App_Code, {
                        maxZoom: 21,
                        subdomains: ["1", "2", "3", "4"],
                        attribution: "&copy; Nokia</span>&nbsp;<a href='https://maps.nokia.com/services/terms' target='_blank' title='Terms of Use' style='color:#333;text-decoration: underline;'>Terms of Use</a></div> <img src='https://api.maps.nokia.com/2.2.4/assets/ovi/mapsapi/by_here.png' border='0'>"
                    });
                    lControl.addBaseLayer(l, prettyName);
                    break;
            }
            l.baseLayer = true;
            l.id = type;
            return (l);
        };
        //ol2 and leaflet
        this.addDtkSkaermkort = function (name, layer) {
            var l,
                url = "http://cdn.eu1.mapcentia.com/wms/dk/tilecache/";

            switch (MAPLIB) {
                case "ol2":
                    l = new OpenLayers.Layer.TMS(name, url, {
                        layername: layer,
                        type: 'png',
                        attribution: "&copy; Geodatastyrelsen",
                        resolutions: resolutions,
                        wrapDateLine: true
                    });
                    this.map.addLayer(l);
                    l.setVisibility(false);
                    break;
                case "leaflet":
                    url = url.replace("cdn.", "{s}.");
                    l = new L.TileLayer(url + "1.0.0/" + layer + "/{z}/{x}/{y}.png", {
                        tms: true,
                        subdomains: ["cdn1", "cdn2", "cdn3"],
                        attribution: "&copy; Geodatastyrelsen",
                        maxZoom: 21,
                        maxNativeZoom: 19

                    });
                    lControl.addBaseLayer(l);
                    break;
            }
            l.baseLayer = true;
            l.id = name;
            return (l);
        };
        //ol2 and leaflet
        this.addGeoDk = function (name, layer) {
            var l,
                url = "https://gc2.io/mapcache/baselayers/tms/";

            switch (MAPLIB) {
                case "ol2":
                    l = new OpenLayers.Layer.TMS(name, url, {
                        layername: layer,
                        type: 'png',
                        attribution: "&copy; Geodatastyrelsen",
                        resolutions: resolutions,
                        wrapDateLine: true
                    });
                    this.map.addLayer(l);
                    l.setVisibility(false);
                    break;
                case "leaflet":
                    l = new L.TileLayer(url + "1.0.0/" + layer + "/{z}/{x}/{y}.png", {
                        tms: true,
                        attribution: "",
                        maxZoom: 21,
                        maxNativeZoom: 19

                    });
                    lControl.addBaseLayer(l);
                    console.log(l)
                    break;
            }
            l.baseLayer = true;
            l.id = name;
            return (l);
        };
        //ol2 and leaflet
        this.addDtkSkaermkortUtm = function (name, layer) {
            var l,
                uriLayerName = (layer === "dtk_skaermkort") ? "topo_skaermkort" : "topo_skaermkort_daempet",
                topLeftCorner = new OpenLayers.LonLat(120000, 6500000),
                tileWidth = 256,
                tileHeight = 256;
            switch (MAPLIB) {
                case "ol2":
                    l = new OpenLayers.Layer.WMTS({
                        name: name,
                        url: ["https://a.services.kortforsyningen.dk/" + uriLayerName + "?login=mh1&password=sajas&", "https://b.services.kortforsyningen.dk/" + uriLayerName, "https://c.services.kortforsyningen.dk/" + uriLayerName],
                        style: "default",
                        layer: layer,
                        matrixSet: "View1",
                        format: "image/jpeg",
                        attribution: "&copy; Geodatastyrelsen",
                        params: {
                            login: "mh1",
                            password: "sajas"
                        },
                        matrixIds: [
                            {
                                identifier: "L00",
                                scaleDenominator: 1638.4 / 0.00028,
                                topLeftCorner: topLeftCorner,
                                tileWidth: tileWidth,
                                tileHeight: tileHeight,
                                matrixWidth: 3,
                                matrixHeight: 2
                            },
                            {
                                identifier: "L01",
                                scaleDenominator: 819.2 / 0.00028,
                                topLeftCorner: topLeftCorner,
                                tileWidth: tileWidth, tileHeight: tileHeight,
                                matrixWidth: 5,
                                matrixHeight: 3
                            },
                            {
                                identifier: "L02",
                                scaleDenominator: 409.6 / 0.00028,
                                topLeftCorner: topLeftCorner,
                                tileWidth: tileWidth, tileHeight: tileHeight,
                                matrixWidth: 9,
                                matrixHeight: 3
                            },
                            {
                                identifier: "L03",
                                scaleDenominator: 204.8 / 0.00028,
                                topLeftCorner: topLeftCorner,
                                tileWidth: tileWidth, tileHeight: tileHeight,
                                matrixWidth: 17,
                                matrixHeight: 12
                            },
                            {
                                identifier: "L04",
                                scaleDenominator: 102.4 / 0.00028,
                                topLeftCorner: topLeftCorner,
                                tileWidth: tileWidth, tileHeight: tileHeight,
                                matrixWidth: 34,
                                matrixHeight: 23
                            },
                            {
                                identifier: "L05",
                                scaleDenominator: 51.2 / 0.00028,
                                topLeftCorner: topLeftCorner,
                                tileWidth: tileWidth, tileHeight: tileHeight,
                                matrixWidth: 68,
                                matrixHeight: 46
                            },
                            {
                                identifier: "L06",
                                scaleDenominator: 25.6 / 0.00028,
                                topLeftCorner: topLeftCorner,
                                tileWidth: tileWidth, tileHeight: tileHeight,
                                matrixWidth: 135,
                                matrixHeight: 92
                            },
                            {
                                identifier: "L07",
                                scaleDenominator: 12.8 / 0.00028,
                                topLeftCorner: topLeftCorner,
                                tileWidth: tileWidth, tileHeight: tileHeight,
                                matrixWidth: 269,
                                matrixHeight: 184
                            },
                            {
                                identifier: "L08",
                                scaleDenominator: 6.4 / 0.00028,
                                topLeftCorner: topLeftCorner,
                                tileWidth: tileWidth, tileHeight: tileHeight,
                                matrixWidth: 538,
                                matrixHeight: 397
                            },
                            {
                                identifier: "L09",
                                scaleDenominator: 3.2 / 0.00028,
                                topLeftCorner: topLeftCorner,
                                tileWidth: tileWidth, tileHeight: tileHeight,
                                matrixWidth: 2149,
                                matrixHeight: 1465
                            },
                            {
                                identifier: "L10",
                                scaleDenominator: 1.6 / 0.00028,
                                topLeftCorner: topLeftCorner,
                                tileWidth: tileWidth, tileHeight: tileHeight,
                                matrixWidth: 4297,
                                matrixHeight: 2930
                            },
                            {
                                identifier: "L11",
                                scaleDenominator: 0.8 / 0.00028,
                                topLeftCorner: topLeftCorner,
                                tileWidth: tileWidth, tileHeight: tileHeight,
                                matrixWidth: 4297,
                                matrixHeight: 2930
                            },
                            {
                                identifier: "L12",
                                scaleDenominator: 0.4 / 0.00028,
                                topLeftCorner: topLeftCorner,
                                tileWidth: tileWidth, tileHeight: tileHeight,
                                matrixWidth: 8594,
                                matrixHeight: 5860
                            },
                            {
                                identifier: "L13",
                                scaleDenominator: 0.2 / 0.00028,
                                topLeftCorner: topLeftCorner,
                                tileWidth: tileWidth, tileHeight: tileHeight,
                                matrixWidth: 17188,
                                matrixHeight: 11719
                            }
                        ],
                        isBaseLayer: true,
                        displayInLayerSwitcher: true,
                        transitionEffect: 'resize'
                    });
                    this.map.addLayer(l);
                    l.setVisibility(false);
                    break;
                case "leaflet":
                    break;
            }
            l.baseLayer = true;
            l.id = name;
            return (l);
        };
        this.addYandex = function (type) {
            var name, prettyName;
            switch (type) {
                //map, satellite, hybrid, publicMap, publicMapHybrid
                case "map":
                    name = "yandexMap"
                    prettyName = "Yandex Map";
                    break;
                case "satellite":
                    name = "yandexSatellite"
                    prettyName = "Yandex Satellite";
                    break;
                case "hybrid":
                    name = "yandexHybrid"
                    prettyName = "Yandex Hybrid";
                    break;
                case "publicMap":
                    name = "yandexPublicMap"
                    prettyName = "Yandex Public Map";
                    break;
                case "publicMapHybrid":
                    name = "yandexPublicMapHybrid"
                    prettyName = "Yandex Public Map Hybrid";
                    break;
            }

            // Load Yandex Maps API and make sure its not loaded more than once
            if (typeof window.YandexMapsDirty === "undefined" && !(typeof ymaps !== "undefined" && typeof ymaps.Map !== "undefined")) {
                window.YandexMapsDirty = true;
                jQuery.getScript("https://api-maps.yandex.ru/2.0-stable/?load=package.standard&lang=ru-RU");
            }

            (function poll() {
                if (typeof ymaps !== "undefined" && typeof ymaps.Map !== "undefined") {
                    switch (MAPLIB) {
                        case "leaflet":
                            this.yandex = new L.Yandex(type);
                            yandexMapAdded[name] = true;
                            lControl.addBaseLayer(this.yandex, prettyName);
                            this.yandex.baseLayer = true;
                            this.yandex.id = name;
                            return (this.yandex);
                            break;
                    }
                } else {
                    setTimeout(poll, 100);
                }
            }());

        };
        //ol2, ol3 and leaflet
        this.setBaseLayer = function (baseLayerName, loadEvent, loadingEvent) {
            var me = this;
            var layers;
            (function poll() {
                if (((baseLayerName.search("google") > -1 && googleMapAdded[baseLayerName] !== undefined)) ||
                    ((baseLayerName.search("yandex") > -1 && yandexMapAdded[baseLayerName] !== undefined)) ||
                    (baseLayerName.search("google") === -1 && baseLayerName.search("yandex") === -1)) {
                    switch (MAPLIB) {
                        case "ol2":
                            me.showLayer(baseLayerName);
                            me.map.setBaseLayer(me.getLayersByName(baseLayerName));
                            break;
                        case "ol3":
                            layers = me.map.getLayers();
                            for (var i = 0; i < layers.getLength(); i++) {
                                if (layers.a[i].baseLayer === true) {
                                    layers.a[i].set("visible", false);
                                }
                            }
                            me.getLayersByName(baseLayerName).set("visible", true);
                            break;
                        case "leaflet":
                            layers = lControl._layers;
                            for (var key in layers) {
                                if (layers.hasOwnProperty(key)) {
                                    if (layers[key].layer.baseLayer === true && me.map.hasLayer(layers[key].layer)) {
                                        me.map.removeLayer(layers[key].layer);
                                    }
                                    if (layers[key].layer.baseLayer === true && layers[key].layer.id === baseLayerName) {
                                        // Move all others than Google maps back
                                        if (baseLayerName.search("google") === -1 && baseLayerName.search("yandex") === -1) {
                                            layers[key].layer.setZIndex(1);
                                        }
                                        if (!loadEvent) {
                                            loadEvent = function () {
                                            }
                                        }
                                        if (!loadingEvent) {
                                            loadingEvent = function () {
                                            }
                                        }

                                        layers[key].layer.off("load", loadEvent);
                                        layers[key].layer.on("load", loadEvent);

                                        layers[key].layer.off("loading", loadingEvent);
                                        layers[key].layer.on("loading", loadingEvent);

                                        me.map.addLayer(layers[key].layer, false);
                                    }
                                }
                            }
                            break;
                    }
                } else {
                    setTimeout(poll, 200);
                }
            }());
        };

        this.addBaseLayer = function (l, db, config, h) {
            var o;
            switch (l) {
                case "osm":
                    o = this.addOSM();
                    break;
                case "mapQuestOSM":
                    o = this.addMapQuestOSM();
                    break;
                case "addMapQuestAerial":
                    o = this.addMapQuestAerial();
                    break;
                case "mapBoxNaturalEarth":
                    o = this.addMapBoxNaturalEarth();
                    break;
                case "stamenToner":
                    o = this.addStamen("toner");
                    break;
                case "stamenTonerLite":
                    o = this.addStamen("toner-lite");
                    break;
                case "googleStreets":
                    o = this.addGoogle("ROADMAP");
                    break;
                case "googleHybrid":
                    o = this.addGoogle("HYBRID");
                    break;
                case "googleSatellite":
                    o = this.addGoogle("SATELLITE");
                    break;
                case "googleTerrain":
                    o = this.addGoogle("TERRAIN");
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
                case "yandexMap":
                    o = this.addYandex("map");
                    break;
                case "yandexSatellite":
                    o = this.addYandex("satellite");
                    break;
                case "yandexHybrid":
                    o = this.addYandex("hybrid");
                    break;
                case "yandexPublicMap":
                    o = this.addYandex("publicMap");
                    break;
                case "yandexPublicMapHybrid":
                    o = this.addYandex("publicMapHybrid");
                    break;
                case "dtkSkaermkort":
                    o = this.addDtkSkaermkort("dtkSkaermkort", "dtk_skaermkort");
                    break;
                case "dtkSkaermkortDaempet":
                    o = this.addDtkSkaermkort("dtkSkaermkortDaempet", "dtk_skaermkort_daempet");
                    break;
                case "dtkSkaermkort@25832":
                    o = this.addDtkSkaermkortUtm("dtkSkaermkort@25832", "dtk_skaermkort");
                    break;
                case "dtkSkaermkortDaempet@25832":
                    o = this.addDtkSkaermkortUtm("dtkSkaermkortDaempet@25832", "dtk_skaermkort_daempet");
                    break;
                case "geodkBright":
                    o = this.addGeoDk("geodkBright", "geodk.bright");
                    break;
                case "DigitalGlobe:Imagery":
                    o = this.addDigitalGlobe("DigitalGlobe:Imagery");
                    break;
                case "hereNormalDayGrey":
                    o = this.addHere("hereNormalDayGrey");
                    break;
                case "hereNormalNightGrey":
                    o = this.addHere("hereNormalNightGrey");
                    break;
                default : // Try to add as tile layer
                    o = this.addTileLayers($.extend({
                        layers: [l],
                        db: db,
                        host: h || host,
                        isBaseLayer: true,
                        visibility: false,
                        wrapDateLine: false,
                        displayInLayerSwitcher: true,
                        name: l,
                        type: "tms"
                    }, config));
                    break;
            }
            return o;
        };
        //ol2, ol3 and leaflet
        this.addTileLayers = function (config) {
            var defaults = {
                host: host,
                layers: [],
                db: null,
                singleTile: false,
                opacity: 1,
                isBaseLayer: false,
                visibility: true,
                wrapDateLine: true,
                tileCached: true,
                displayInLayerSwitcher: true,
                name: null,
                names: [],
                resolutions: this.map.resolutions,
                type: "wms",
                maxZoom: 21,
                maxNativeZoom: 21,
                tileSize: MAPLIB === "ol2" ? OpenLayers.Size(256, 256) : 256
            };
            if (config) {
                for (prop in config) {
                    defaults[prop] = config[prop];
                }
            }
            var layers = defaults.layers;
            var layersArr = [];
            for (var i = 0; i < layers.length; i++) {
                var l;
                switch (defaults.type) {
                    case  "wms":
                        l = createTileLayer(layers[i], defaults);
                        break;
                    case "tms":
                        l = createTMSLayer(layers[i], defaults);
                        break;
                    default:
                        l = createTileLayer(layers[i], defaults);
                        break;
                }
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
                            lControl.addBaseLayer(l, defaults.name || defaults.names[i]);
                        }
                        else {
                            lControl.addOverlay(l, defaults.name || defaults.names[i] || layers[i]);
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

        //ol2 and leaflet
        this.removeTileLayerByName = function (name) {
            switch (MAPLIB) {
                case "ol2":
                    var arr = this.map.getLayersByName(name);
                    this.map.removeLayer(arr[0]);
                    break;
                case "leaflet":
                    this.map.removeLayer(this.getLayersByName(name));
                    lControl.removeLayer(this.getLayersByName(name));
                    break;
            }
        };

        // Leaflet
        this.setZIndexOfLayer = function (layer, z) {
            switch (MAPLIB) {
                case "ol2":
                    break;
                case "leaflet":
                    layer.setZIndex(z);
                    break;
            }
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
                    lControl.addOverlay(store.layer, store.name);
                    this.showLayer(store.layer.id);
                    break;
            }
        };
        this.addHeatMap = function (store, weight, factor, config) {
            var points = [], features = store.geoJSON.features;
            weight = weight || 1;
            factor = factor || 1;
            config = config || {};
            for (var key in features) {
                if (features.hasOwnProperty(key)) {
                    features[key].geometry.coordinates.reverse();
                    features[key].geometry.coordinates.push((features[key].properties[weight] * factor) + "")
                    points.push(features[key].geometry.coordinates)
                }
            }
            store.layer = L.heatLayer(points, config);
            this.addGeoJsonStore(store);
        }

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
        };
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
        };
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
        };
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
                            styleMap: new OpenLayers.StyleMap({
                                'default': new OpenLayers.Style({
                                    strokeColor: '#000000',
                                    strokeWidth: 3,
                                    fillOpacity: 0,
                                    strokeOpacity: 0.8
                                })
                            })
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
                                callBack(e);
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
            p = new Proj4js.Point(lat, lon);
            Proj4js.transform(source, dest, p);
        }
        else {
            p.x = null;
            p.y = null;
        }
        return p;
    };

    base64 = {

        // private property
        _keyStr: "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",

        // public method for encoding
        encode: function (input) {
            var output = "";
            var chr1, chr2, chr3, enc1, enc2, enc3, enc4;
            var i = 0;

            input = base64._utf8_encode(input);

            while (i < input.length) {

                chr1 = input.charCodeAt(i++);
                chr2 = input.charCodeAt(i++);
                chr3 = input.charCodeAt(i++);

                enc1 = chr1 >> 2;
                enc2 = ((chr1 & 3) << 4) | (chr2 >> 4);
                enc3 = ((chr2 & 15) << 2) | (chr3 >> 6);
                enc4 = chr3 & 63;

                if (isNaN(chr2)) {
                    enc3 = enc4 = 64;
                } else if (isNaN(chr3)) {
                    enc4 = 64;
                }

                output = output + this._keyStr.charAt(enc1) + this._keyStr.charAt(enc2) + this._keyStr.charAt(enc3) + this._keyStr.charAt(enc4);

            }

            return output;
        },

        // public method for decoding
        decode: function (input) {
            var output = "";
            var chr1, chr2, chr3;
            var enc1, enc2, enc3, enc4;
            var i = 0;

            input = input.replace(/[^A-Za-z0-9\+\/\=]/g, "");

            while (i < input.length) {

                enc1 = this._keyStr.indexOf(input.charAt(i++));
                enc2 = this._keyStr.indexOf(input.charAt(i++));
                enc3 = this._keyStr.indexOf(input.charAt(i++));
                enc4 = this._keyStr.indexOf(input.charAt(i++));

                chr1 = (enc1 << 2) | (enc2 >> 4);
                chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
                chr3 = ((enc3 & 3) << 6) | enc4;

                output = output + String.fromCharCode(chr1);

                if (enc3 != 64) {
                    output = output + String.fromCharCode(chr2);
                }
                if (enc4 != 64) {
                    output = output + String.fromCharCode(chr3);
                }

            }

            output = base64._utf8_decode(output);

            return output;

        },

        // private method for UTF-8 encoding
        _utf8_encode: function (string) {
            string = string.replace(/\r\n/g, "\n");
            var utftext = "";

            for (var n = 0; n < string.length; n++) {

                var c = string.charCodeAt(n);

                if (c < 128) {
                    utftext += String.fromCharCode(c);
                } else if ((c > 127) && (c < 2048)) {
                    utftext += String.fromCharCode((c >> 6) | 192);
                    utftext += String.fromCharCode((c & 63) | 128);
                } else {
                    utftext += String.fromCharCode((c >> 12) | 224);
                    utftext += String.fromCharCode(((c >> 6) & 63) | 128);
                    utftext += String.fromCharCode((c & 63) | 128);
                }

            }

            return utftext;
        },

        // private method for UTF-8 decoding
        _utf8_decode: function (utftext) {
            var string = "", i, c2, c3, c;
            c = 0;
            c2 = 0;
            c3 = 0;

            while (i < utftext.length) {
                c = utftext.charCodeAt(i);
                if (c < 128) {
                    string += String.fromCharCode(c);
                    i++;
                } else if ((c > 191) && (c < 224)) {
                    c2 = utftext.charCodeAt(i + 1);
                    string += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
                    i += 2;
                } else {
                    c2 = utftext.charCodeAt(i + 1);
                    c3 = utftext.charCodeAt(i + 2);
                    string += String.fromCharCode(((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
                    i += 3;
                }

            }
            return string;
        }
    }

    return {
        geoJsonStore: geoJsonStore,
        sqlStore: sqlStore,
        tileLayer: tileLayer,
        elasticStore: elasticStore,
        tweetStore: tweetStore,
        cartoDbStore: cartoDbStore,
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
        STAMENTONERLITE: STAMENTONERLITE,
        GOOGLESTREETS: GOOGLESTREETS,
        GOOGLEHYBRID: GOOGLEHYBRID,
        GOOGLESATELLITE: GOOGLESATELLITE,
        GOOGLETERRAIN: GOOGLETERRAIN,
        BINGROAD: BINGROAD,
        BINGAERIAL: BINGAERIAL,
        BINGAERIALWITHLABELS: BINGAERIALWITHLABELS,
        DTKSKAERMKORT: DTKSKAERMKORT,
        DTKSKAERMKORT_25832: DTKSKAERMKORT_25832,
        DTKSKAERMKORTDAEMPET: DTKSKAERMKORTDAEMPET,
        GEODKBRIGHT: GEODKBRIGHT,
        DIGITALGLOBE: DIGITALGLOBE,
        HERENORMALDAYGREY: HERENORMALDAYGREY,
        HERENORMALNIGHTGREY: HERENORMALNIGHTGREY,
        setHost: setHost
    };
}());

// Adding extensions for several map providers

// Stamen (Leaflet and OpenLayers)
(function (exports) {
    /*
     * tile.stamen.js v1.2.4
     */
    "use strict";
    var SUBDOMAINS = " a. b. c. d.".split(" "),
        MAKE_PROVIDER = function (layer, type, minZoom, maxZoom) {
            return {
                "url": ["https://stamen-tiles.a.ssl.fastly.net/", layer, "/{Z}/{X}/{Y}.", type].join(""),
                "type": type,
                "subdomains": SUBDOMAINS.slice(),
                "minZoom": minZoom,
                "maxZoom": maxZoom,
                "attribution": [
                    'Map tiles by <a href="https://stamen.com">Stamen Design</a>, ',
                    'under <a href="https://creativecommons.org/licenses/by/3.0">CC BY 3.0</a>. ',
                    'Data by <a href="https://openstreetmap.org">OpenStreetMap</a>, ',
                    'under <a href="https://creativecommons.org/licenses/by-sa/3.0">CC BY SA</a>.'
                ].join("")
            };
        },
        PROVIDERS = {
            "toner": MAKE_PROVIDER("toner", "png", 0, 21),
            "terrain": MAKE_PROVIDER("terrain", "jpg", 4, 18),
            "watercolor": MAKE_PROVIDER("watercolor", "jpg", 1, 18)
        };
    /*
     * Get the named provider, or throw an exception if it doesn't exist.
     */
    var getProvider = function (name) {
        if (name in PROVIDERS) {
            return PROVIDERS[name];
        } else {
            throw 'No such provider (' + name + ')';
        }
    };

    /*
     * A shortcut for specifying "flavors" of a style, which are assumed to have the
     * same type and zoom range.
     */
    var setupFlavors = function (base, flavors, type) {
        var provider = getProvider(base);
        for (var i = 0; i < flavors.length; i++) {
            var flavor = [base, flavors[i]].join("-");
            PROVIDERS[flavor] = MAKE_PROVIDER(flavor, type || provider.type, provider.minZoom, provider.maxZoom);
        }
    };
// set up toner and terrain flavors
    setupFlavors("toner", ["hybrid", "labels", "lines", "background", "lite"]);
// toner 2010
    setupFlavors("toner", ["2010"]);
// toner 2011 flavors
    setupFlavors("toner", ["2011", "2011-lines", "2011-labels", "2011-lite"]);
    setupFlavors("terrain", ["background"]);
    setupFlavors("terrain", ["labels", "lines"], "png");

    /*
     * Export stamen.tile to the provided namespace.
     */
    exports.stamen = exports.stamen || {};
    exports.stamen.tile = exports.stamen.tile || {};
    exports.stamen.tile.providers = PROVIDERS;
    exports.stamen.tile.getProvider = getProvider;


    /*
     * StamenTileLayer for Leaflet
     * <http://leaflet.cloudmade.com/>
     *
     * Tested with version 0.3 and 0.4, but should work on all 0.x releases.
     */
    if (typeof L === "object") {
        L.StamenTileLayer = L.TileLayer.extend({
            initialize: function (name) {
                var provider = getProvider(name),
                    url = provider.url.replace(/({[A-Z]})/g, function (s) {
                        return s.toLowerCase();
                    });
                L.TileLayer.prototype.initialize.call(this, url, {
                    "minZoom": provider.minZoom,
                    "maxZoom": provider.maxZoom,
                    "maxNativeZoom": 18,
                    "subdomains": provider.subdomains,
                    "scheme": "xyz",
                    "attribution": provider.attribution
                });
            }
        });
    }

    /*
     * StamenTileLayer for OpenLayers
     * <http://openlayers.org/>
     *
     * Tested with v2.1x.
     */
    if (typeof OpenLayers === "object") {
        // make a tile URL template OpenLayers-compatible
        var openlayerize = function (url) {
            return url.replace(/({.})/g, function (v) {
                return "$" + v.toLowerCase();
            });
        };

        // based on http://www.bostongis.com/PrinterFriendly.aspx?content_name=using_custom_osm_tiles
        OpenLayers.Layer.Stamen = OpenLayers.Class(OpenLayers.Layer.OSM, {
            initialize: function (name, options) {
                var provider = getProvider(name),
                    url = provider.url,
                    subdomains = provider.subdomains,
                    hosts = [];
                if (url.indexOf("{S}") > -1) {
                    for (var i = 0; i < subdomains.length; i++) {
                        hosts.push(openlayerize(url.replace("{S}", subdomains[i])));
                    }
                } else {
                    hosts.push(openlayerize(url));
                }
                options = OpenLayers.Util.extend({
                    "numZoomLevels": provider.maxZoom,
                    "buffer": 0,
                    "transitionEffect": "resize",
                    // see: <http://dev.openlayers.org/apidocs/files/OpenLayers/Layer/OSM-js.html#OpenLayers.Layer.OSM.tileOptions>
                    // and: <http://dev.openlayers.org/apidocs/files/OpenLayers/Tile/Image-js.html#OpenLayers.Tile.Image.crossOriginKeyword>
                    "tileOptions": {
                        "crossOriginKeyword": null
                    }
                }, options);
                return OpenLayers.Layer.OSM.prototype.initialize.call(this, name, hosts, options);
            }
        });
    }

})(typeof exports === "undefined" ? this : exports);

if (geocloud.MAPLIB === "leaflet") {
// Bing Maps (Leaflet)
    L.BingLayer = L.TileLayer.extend({
        options: {
            subdomains: [0, 1, 2, 3],
            type: 'Aerial',
            attribution: 'Bing',
            culture: ''
        },

        initialize: function (key, options) {
            L.Util.setOptions(this, options);

            this._key = key;
            this._url = null;
            this.meta = {};
            this.loadMetadata();
        },

        tile2quad: function (x, y, z) {
            var quad = '';
            for (var i = z; i > 0; i--) {
                var digit = 0;
                var mask = 1 << (i - 1);
                if ((x & mask) != 0) digit += 1;
                if ((y & mask) != 0) digit += 2;
                quad = quad + digit;
            }
            return quad;
        },

        getTileUrl: function (p, z) {
            var z = this._getZoomForUrl();
            var subdomains = this.options.subdomains,
                s = this.options.subdomains[Math.abs((p.x + p.y) % subdomains.length)];
            return this._url.replace('{subdomain}', s)
                .replace('http:', 'https:')
                .replace('{quadkey}', this.tile2quad(p.x, p.y, z))
                .replace('{culture}', this.options.culture);
        },

        loadMetadata: function () {
            var _this = this;
            var cbid = '_bing_metadata_' + L.Util.stamp(this);
            window[cbid] = function (meta) {
                _this.meta = meta;
                window[cbid] = undefined;
                var e = document.getElementById(cbid);
                e.parentNode.removeChild(e);
                if (meta.errorDetails) {
                    alert("Got metadata" + meta.errorDetails);
                    return;
                }
                _this.initMetadata();
            };
            var url = "https://dev.virtualearth.net/REST/v1/Imagery/Metadata/" + this.options.type + "?include=ImageryProviders&jsonp=" + cbid + "&key=" + this._key;
            var script = document.createElement("script");
            script.type = "text/javascript";
            script.src = url;
            script.id = cbid;
            document.getElementsByTagName("head")[0].appendChild(script);
        },

        initMetadata: function () {
            var r = this.meta.resourceSets[0].resources[0];
            this.options.subdomains = r.imageUrlSubdomains;
            this._url = r.imageUrl;
            this._providers = [];
            for (var i = 0; i < r.imageryProviders.length; i++) {
                var p = r.imageryProviders[i];
                for (var j = 0; j < p.coverageAreas.length; j++) {
                    var c = p.coverageAreas[j];
                    var coverage = {zoomMin: c.zoomMin, zoomMax: c.zoomMax, active: false};
                    var bounds = new L.LatLngBounds(
                        new L.LatLng(c.bbox[0] + 0.01, c.bbox[1] + 0.01),
                        new L.LatLng(c.bbox[2] - 0.01, c.bbox[3] - 0.01)
                    );
                    coverage.bounds = bounds;
                    coverage.attrib = p.attribution;
                    this._providers.push(coverage);
                }
            }
            this._update();
        },

        _update: function () {
            if (this._url == null || !this._map) return;
            this._update_attribution();
            L.TileLayer.prototype._update.apply(this, []);
        },

        _update_attribution: function () {
            var bounds = this._map.getBounds();
            var zoom = this._map.getZoom();
            for (var i = 0; i < this._providers.length; i++) {
                var p = this._providers[i];
                if ((zoom <= p.zoomMax && zoom >= p.zoomMin) &&
                    bounds.intersects(p.bounds)) {
                    if (!p.active)
                        this._map.attributionControl.addAttribution(p.attrib);
                    p.active = true;
                } else {
                    if (p.active)
                        this._map.attributionControl.removeAttribution(p.attrib);
                    p.active = false;
                }
            }
        },

        onRemove: function (map) {
            for (var i = 0; i < this._providers.length; i++) {
                var p = this._providers[i];
                if (p.active) {
                    this._map.attributionControl.removeAttribution(p.attrib);
                    p.active = false;
                }
            }
            L.TileLayer.prototype.onRemove.apply(this, [map]);
        }
    });
// Leaflet.AwesomeMarkers (Leaflet)
    /*
     Leaflet.AwesomeMarkers, a plugin that adds colorful iconic markers for Leaflet, based on the Font Awesome icons
     (c) 2012-2013, Lennard Voogdt

     http://leafletjs.com
     https://github.com/lvoogdt
     */
    /*global L*/
    (function (e, t, n) {
        "use strict";
        L.AwesomeMarkers = {};
        L.AwesomeMarkers.version = "2.0.1";
        L.AwesomeMarkers.Icon = L.Icon.extend({
            options: {
                iconSize: [35, 45],
                iconAnchor: [17, 42],
                popupAnchor: [1, -32],
                shadowAnchor: [10, 12],
                shadowSize: [36, 16],
                className: "awesome-marker",
                prefix: "glyphicon",
                spinClass: "fa-spin",
                icon: "home",
                markerColor: "blue",
                iconColor: "white"
            }, initialize: function (e) {
                e = L.Util.setOptions(this, e)
            }, createIcon: function () {
                var e = t.createElement("div"), n = this.options;
                n.icon && (e.innerHTML = this._createInner());
                n.bgPos && (e.style.backgroundPosition = -n.bgPos.x + "px " + -n.bgPos.y + "px");
                this._setIconStyles(e, "icon-" + n.markerColor);
                return e
            }, _createInner: function () {
                var e, t = "", n = "", r = "", i = this.options;
                i.icon.slice(0, i.prefix.length + 1) === i.prefix + "-" ? e = i.icon : e = i.prefix + "-" + i.icon;
                i.spin && typeof i.spinClass == "string" && (t = i.spinClass);
                i.iconColor && (i.iconColor === "white" || i.iconColor === "black" ? n = "icon-" + i.iconColor : r = "style='color: " + i.iconColor + "' ");
                return "<i " + r + "class='" + i.prefix + " " + e + " " + t + " " + n + "'></i>"
            }, _setIconStyles: function (e, t) {
                var n = this.options, r = L.point(n[t === "shadow" ? "shadowSize" : "iconSize"]), i;
                t === "shadow" ? i = L.point(n.shadowAnchor || n.iconAnchor) : i = L.point(n.iconAnchor);
                !i && r && (i = r.divideBy(2, !0));
                e.className = "awesome-marker-" + t + " " + n.className;
                if (i) {
                    e.style.marginLeft = -i.x + "px";
                    e.style.marginTop = -i.y + "px"
                }
                if (r) {
                    e.style.width = r.x + "px";
                    e.style.height = r.y + "px"
                }
            }, createShadow: function () {
                var e = t.createElement("div");
                this._setIconStyles(e, "shadow");
                return e
            }
        });
        L.AwesomeMarkers.icon = function (e) {
            return new L.AwesomeMarkers.Icon(e)
        }
    })(this, document);
}
// Google Maps (Leaflet)
var gc2SetLGoogle = function () {
    if (geocloud.MAPLIB === "leaflet") {
        L.Google = L.Class.extend({
            includes: L.Mixin.Events,
            options: {
                minZoom: 0,
                maxZoom: 20,
                tileSize: 256,
                subdomains: 'abc',
                errorTileUrl: '',
                attribution: '',
                opacity: 1,
                continuousWorld: false,
                noWrap: false,
                mapOptions: {
                    backgroundColor: '#dddddd'
                }
            },

            // Possible types: SATELLITE, ROADMAP, HYBRID, TERRAIN
            initialize: function (type, options) {
                L.Util.setOptions(this, options);

                this._ready = google.maps.Map != undefined;
                if (!this._ready) L.Google.asyncWait.push(this);

                this._type = type || 'SATELLITE';
            },

            onAdd: function (map, insertAtTheBottom) {
                this._map = map;
                this._insertAtTheBottom = insertAtTheBottom;

                // create a container div for tiles
                this._initContainer();
                this._initMapObject();

                // set up events
                map.on('viewreset', this._resetCallback, this);

                this._limitedUpdate = L.Util.limitExecByInterval(this._update, 150, this);
                map.on('move', this._update, this);

                map.on('zoomanim', this._handleZoomAnim, this);

                //20px instead of 1em to avoid a slight overlap with google's attribution
                map._controlCorners['bottomright'].style.marginBottom = "20px";

                this._reset();
                this._update();
            },

            onRemove: function (map) {
                this._map._container.removeChild(this._container);
                //this._container = null;

                this._map.off('viewreset', this._resetCallback, this);

                this._map.off('move', this._update, this);

                this._map.off('zoomanim', this._handleZoomAnim, this);

                map._controlCorners['bottomright'].style.marginBottom = "0em";
                //this._map.off('moveend', this._update, this);
            },

            getAttribution: function () {
                return this.options.attribution;
            },

            setOpacity: function (opacity) {
                this.options.opacity = opacity;
                if (opacity < 1) {
                    L.DomUtil.setOpacity(this._container, opacity);
                }
            },

            setElementSize: function (e, size) {
                e.style.width = size.x + "px";
                e.style.height = size.y + "px";
            },

            _initContainer: function () {
                var tilePane = this._map._container,
                    first = tilePane.firstChild;

                if (!this._container) {
                    this._container = L.DomUtil.create('div', 'leaflet-google-layer leaflet-top leaflet-left');
                    this._container.id = "_GMapContainer_" + L.Util.stamp(this);
                    this._container.style.zIndex = "auto";
                }

                tilePane.insertBefore(this._container, first);

                this.setOpacity(this.options.opacity);
                this.setElementSize(this._container, this._map.getSize());
            },

            _initMapObject: function () {
                if (!this._ready) return;
                this._google_center = new google.maps.LatLng(0, 0);
                var map = new google.maps.Map(this._container, {
                    center: this._google_center,
                    zoom: 0,
                    tilt: 0,
                    mapTypeId: google.maps.MapTypeId[this._type],
                    disableDefaultUI: true,
                    keyboardShortcuts: false,
                    draggable: false,
                    disableDoubleClickZoom: true,
                    scrollwheel: false,
                    streetViewControl: false,
                    styles: this.options.mapOptions.styles,
                    backgroundColor: this.options.mapOptions.backgroundColor
                });

                var _this = this;
                this._reposition = google.maps.event.addListenerOnce(map, "center_changed",
                    function () {
                        _this.onReposition();
                    });
                this._google = map;

                google.maps.event.addListenerOnce(map, "idle",
                    function () {
                        _this._checkZoomLevels();
                    });
            },

            _checkZoomLevels: function () {
                //setting the zoom level on the Google map may result in a different zoom level than the one requested
                //(it won't go beyond the level for which they have data).
                // verify and make sure the zoom levels on both Leaflet and Google maps are consistent
                if (this._google.getZoom() !== this._map.getZoom()) {
                    //zoom levels are out of sync. Set the leaflet zoom level to match the google one
                    this._map.setZoom(this._google.getZoom());
                }
            },

            _resetCallback: function (e) {
                this._reset(e.hard);
            },

            _reset: function (clearOldContainer) {
                this._initContainer();
            },

            _update: function (e) {
                if (!this._google) return;
                this._resize();

                var center = e && e.latlng ? e.latlng : this._map.getCenter();
                var _center = new google.maps.LatLng(center.lat, center.lng);

                this._google.setCenter(_center);
                this._google.setZoom(this._map.getZoom());

                this._checkZoomLevels();
                //this._google.fitBounds(google_bounds);
            },

            _resize: function () {
                var size = this._map.getSize();
                if (this._container.style.width == size.x &&
                    this._container.style.height == size.y)
                    return;
                this.setElementSize(this._container, size);
                this.onReposition();
            },


            _handleZoomAnim: function (e) {
                var center = e.center;
                var _center = new google.maps.LatLng(center.lat, center.lng);

                this._google.setCenter(_center);
                this._google.setZoom(e.zoom);
            },


            onReposition: function () {
                if (!this._google) return;
                google.maps.event.trigger(this._google, "resize");
            }
        });
        L.Google.asyncWait = [];
        L.Google.asyncInitialize = function () {
            var i;
            for (i = 0; i < L.Google.asyncWait.length; i++) {
                var o = L.Google.asyncWait[i];
                o._ready = true;
                if (o._container) {
                    o._initMapObject();
                    o._update();
                }
            }
            L.Google.asyncWait = [];
        }
    }
};
/*
 * L.TileLayer is used for standard xyz-numbered tile layers.
 */
if (geocloud.MAPLIB === "leaflet") {
    L.Yandex = L.Class.extend({
        includes: L.Mixin.Events,

        options: {
            minZoom: 0,
            maxZoom: 18,
            attribution: '',
            opacity: 1,
            traffic: false
        },

        // Possible types: map, satellite, hybrid, publicMap, publicMapHybrid
        initialize: function (type, options) {
            L.Util.setOptions(this, options);

            this._type = "yandex#" + (type || 'map');
        },

        onAdd: function (map, insertAtTheBottom) {
            this._map = map;
            this._insertAtTheBottom = insertAtTheBottom;

            // create a container div for tiles
            this._initContainer();
            this._initMapObject();

            // set up events
            map.on('viewreset', this._resetCallback, this);

            this._limitedUpdate = L.Util.limitExecByInterval(this._update, 150, this);
            map.on('move', this._update, this);

            map._controlCorners['bottomright'].style.marginBottom = "3em";

            this._reset();
            this._update(true);
        },

        onRemove: function (map) {
            this._map._container.removeChild(this._container);

            this._map.off('viewreset', this._resetCallback, this);

            this._map.off('move', this._update, this);

            map._controlCorners['bottomright'].style.marginBottom = "0em";
        },

        getAttribution: function () {
            return this.options.attribution;
        },

        setOpacity: function (opacity) {
            this.options.opacity = opacity;
            if (opacity < 1) {
                L.DomUtil.setOpacity(this._container, opacity);
            }
        },

        setElementSize: function (e, size) {
            e.style.width = size.x + "px";
            e.style.height = size.y + "px";
        },

        _initContainer: function () {
            var tilePane = this._map._container,
                first = tilePane.firstChild;

            if (!this._container) {
                this._container = L.DomUtil.create('div', 'leaflet-yandex-layer leaflet-top leaflet-left');
                this._container.id = "_YMapContainer_" + L.Util.stamp(this);
                this._container.style.zIndex = "auto";
            }

            if (this.options.overlay) {
                first = this._map._container.getElementsByClassName('leaflet-map-pane')[0];
                first = first.nextSibling;
                // XXX: Bug with layer order
                if (L.Browser.opera)
                    this._container.className += " leaflet-objects-pane";
            }
            tilePane.insertBefore(this._container, first);

            this.setOpacity(this.options.opacity);
            this.setElementSize(this._container, this._map.getSize());
        },

        _initMapObject: function () {
            if (this._yandex) return;

            // Check that ymaps.Map is ready
            if (ymaps.Map === undefined) {
                if (console) {
                    console.debug("L.Yandex: Waiting on ymaps.load('package.map')");
                }
                return ymaps.load(["package.map"], this._initMapObject, this);
            }

            // If traffic layer is requested check if control.TrafficControl is ready
            if (this.options.traffic)
                if (ymaps.control === undefined ||
                    ymaps.control.TrafficControl === undefined) {
                    if (console) {
                        console.debug("L.Yandex: loading traffic and controls");
                    }
                    return ymaps.load(["package.traffic", "package.controls"],
                        this._initMapObject, this);
                }

            var map = new ymaps.Map(this._container, {center: [0, 0], zoom: 0, behaviors: []});

            if (this.options.traffic)
                map.controls.add(new ymaps.control.TrafficControl({shown: true}));

            if (this._type == "yandex#null") {
                this._type = new ymaps.MapType("null", []);
                map.container.getElement().style.background = "transparent";
            }
            map.setType(this._type);

            this._yandex = map;
            this._update(true);
        },

        _resetCallback: function (e) {
            this._reset(e.hard);
        },

        _reset: function (clearOldContainer) {
            this._initContainer();
        },

        _update: function (force) {
            if (!this._yandex) return;
            this._resize(force);

            var center = this._map.getCenter();
            var _center = [center.lat, center.lng];
            var zoom = this._map.getZoom();

            if (force || this._yandex.getZoom() != zoom)
                this._yandex.setZoom(zoom);
            this._yandex.panTo(_center, {duration: 0, delay: 0});
        },

        _resize: function (force) {
            var size = this._map.getSize(), style = this._container.style;
            if (style.width == size.x + "px" &&
                style.height == size.y + "px")
                if (force != true) return;
            this.setElementSize(this._container, size);
            var b = this._map.getBounds(), sw = b.getSouthWest(), ne = b.getNorthEast();
            this._yandex.container.fitToViewport();
        }
    });
}

if (geocloud.MAPLIB === "leaflet") {
    (function () {
        var bounce = false, isSet = true, first = true;
        /* Patch for Leaflet 0.7.3  */
        if (L.TileLayer && L.TileLayer.WMS) {
            /* Set tileSize option to 9999 (or greater) to switch it to single tile mode */
            L.TileLayer.WMS.prototype.getTileUrl2 = function (tilePoint) {
                var me = this;

                if (!this._singleTile) {
                    return this.getTileUrl(tilePoint);
                }
                var tmp = this._map.getSize();
                this.wmsParams.width = tmp.x;
                this.wmsParams.height = tmp.y;
                tmp = this._map.getBounds();

                var nw = this._crs.project(tmp.getNorthWest());
                var se = this._crs.project(tmp.getSouthEast());
                var bbox = this._wmsVersion >= 1.3 && this._crs === L.CRS.EPSG4326 ?
                    [se.y, nw.x, nw.y, se.x].join(',') :
                    [nw.x, se.y, se.x, nw.y].join(',');
                url = L.Util.template(this._url, {s: this._getSubdomain(tilePoint)});

                if (!bounce && !first) {
                    bounce = true;
                    setTimeout(function () {
                        first = true;

                        bounce = false
                    }, 1000)
                    isSet = true;
                    return url + L.Util.getParamString(this.wmsParams, url, true) + '&BBOX=' + bbox;
                } else {
                    isSet = false;
                    setTimeout(function () {
                        first = false;

                        if (!isSet) {
                            me.redraw();
                        }
                    }, 1000)
                    return false
                }
            }
            L.TileLayer.WMS.prototype._loadTile = function (tile, tilePoint) {
                var me = this;
                if (this._singleTile) {
                    var siz = this._map.getSize();
                    tile.style.width = siz.x + 'px';
                    tile.style.height = siz.y + 'px';
                }
                tile._layer = this;


                tile.onload = function () {
                    if (this._layer._singleTile) {
                        this._layer._reset({hard: true});
                        this._layer._tileContainer.appendChild(this);
                        this._layer.fire("load");
                    }
                    L.TileLayer.WMS.prototype._tileOnLoad.call(this);
                };
                tile.onerror = this._tileOnError;
                this._adjustTilePoint(tilePoint);
                var url = this.getTileUrl2(tilePoint);
                if (url) {
                    //console.info(url);
                    tile.src = url
                } else {
                    console.info("Skipping single tile");
                }
                this.fire('tileloadstart', {tile: tile, url: tile.src});
            }
            L.TileLayer.WMS.prototype._addTilesFromCenterOut = function (bounds) {
                this._singleTile = this.options.tileSize >= 9999;
                if (!this._singleTile) {
                    return L.TileLayer.prototype._addTilesFromCenterOut.call(this, bounds);
                }

                var fragment = document.createDocumentFragment();
                this.fire('loading');
                this._addTile(new L.Point(bounds.min.x, bounds.min.y), fragment);
                this._tileContainer.appendChild(fragment);
            }
            L.TileLayer.WMS.prototype._getTilePos = function (tilePoint) {
                if (!this._singleTile) {
                    return L.TileLayer.prototype._getTilePos.call(this, tilePoint);
                }
                return this._map.getPixelBounds().min.subtract(this._map.getPixelOrigin())
            }
        }
    }());
}