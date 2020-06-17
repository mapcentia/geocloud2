
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
/*global GeoExt:false */
/*global mygeocloud_ol:false */
/*global schema:false */
/*global document:false */
/*global mygeocloud_host:false */

var popup;
var scriptSource = (function (scripts) {
    'use strict';
    scripts = document.getElementsByTagName('script');
    var script = scripts[scripts.length - 1];
    if (script.getAttribute.length !== undefined) {
        return script.src;
    }
    return script.getAttribute('src', -1);
}());
// In IE7 host name is missing if script url is relative
if (typeof mygeocloud_host === "undefined") {
    if (scriptSource.charAt(0) === "/") {
        window.mygeocloud_host = "";
    } else {
        window.mygeocloud_host = scriptSource.split("/")[0] + "//" + scriptSource.split("/")[2];
    }
}
if (typeof jQuery === "undefined") {
    document.write("<script src='//ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js'><\/script>");
}
if (typeof OpenLayers === "undefined") {
    // This is a hacked version of OpenLayers 2.12. Do NOT use 2.13 in GC2 Admin
    document.write("<script src='" + mygeocloud_host + "/js/OpenLayers-2.12/OpenLayers.gc2.js'><\/script>");
}
if (typeof Ext === "undefined") {
    document.write("<script src='" + mygeocloud_host + "/js/ext/adapter/ext/ext-base.js'><\/script>");
    document.write("<script src='" + mygeocloud_host + "/js/ext/ext-all.js'><\/script>");
    document.write("<script src='" + mygeocloud_host + "/js/GeoExt/script/GeoExt.js'><\/script>");
}

var mygeocloud_ol = (function () {
    "use strict";
    var map, host = mygeocloud_host, parentThis = this;
    var geoJsonStore = function (db, config) {
        var prop, parentThis = this;
        var defaults = {
            sql: null,
            onLoad: function () {
            },
            styleMap: null,
            projection: "3857",
            strategies: null,
            visibility: true,
            rendererOptions: {
                zIndexing: true
            },
            lifetime: 0,
            movedEnd: function () {
            },
            selectControl: {},
            clientEncoding: "UTF8",
            jsonp: true,
            method: "GET"
        };
        if (config) {
            for (prop in config) {
                defaults[prop] = config[prop];
            }
        }
        this.sql = defaults.sql;
        this.onLoad = defaults.onLoad;
        this.movedEnd = defaults.movedEnd;
        // Map member for parent map obj. Set when store is added to a map
        this.map = null;
        // Layer Def
        this.layer = new OpenLayers.Layer.Vector("Vector", {
            styleMap: defaults.styleMap,
            visibility: defaults.visibility,
            //renderers: ['Canvas', 'SVG', 'VML'],
            rendererOptions: defaults.rendererOptions
        });
        this.hide = function () {
            this.layer.setVisibility(false);
        };
        this.show = function () {
            this.layer.setVisibility(true);
        };
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
            }
            $.ajax({
                dataType: (defaults.jsonp) ? 'jsonp' : 'json',
                data: 'q=' + encodeURIComponent(sql) + '&srs=' + defaults.projection + '&lifetime=' + defaults.lifetime + '&client_encoding=' + defaults.clientEncoding,
                jsonp: (defaults.jsonp) ? 'jsonp_callback' : false,
                url: host + '/api/v1/sql/' + db,
                type: defaults.method,
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
    map = function (el, db, config) {
        var prop, parentMap, defaults = {
            numZoomLevels: 22,
            projection: "EPSG:3857",
            maxExtent: new OpenLayers.Bounds(-20037508.34, -20037508.34, 20037508.34, 20037508.34),
            restrictedExtent: null,
            controls: [
                //new OpenLayers.Control.Navigation(),
                //new OpenLayers.Control.PanZoomBar(),
                //new OpenLayers.Control.LayerSwitcher(),
                //new OpenLayers.Control.PanZoom(),
                new OpenLayers.Control.Attribution(),
                new OpenLayers.Control.Zoom(),
                new OpenLayers.Control.TouchNavigation({
                    dragPanOptions: {enableKinetic: true}
                })]
        };
        if (config) {
            for (prop in config) {
                defaults[prop] = config[prop];
            }
        }
        parentMap = this;
        this.bingApiKey = null;
        this.layerStr = "";
        this.db = db;
        this.geoLocation = {
            x: null,
            y: null,
            obj: {}
        };
        this.zoomToExtent = function (extent, closest) {
            if (!extent) {
                this.map.zoomToExtent(this.map.maxExtent);
            } else {
                this.map.zoomToExtent(new OpenLayers.Bounds(extent), closest);
            }
        };
        this.zoomToExtentOfgeoJsonStore = function (store) {
            this.map.zoomToExtent(store.layer.getDataExtent());
        };
        this.getVisibleLayers = function () {
            var layerArr = [];
            for (var i = 0; i < this.map.layers.length; i++) {
                if (this.map.layers[i].isBaseLayer === false && this.map.layers[i].visibility === true && this.map.layers[i].CLASS_NAME === "OpenLayers.Layer.WMS") {
                    layerArr.push(this.map.layers[i].params.LAYERS);
                }
            }
            return layerArr.join(";");
        };
        this.getZoom = function () {
            return this.map.getZoom();
        };
        this.getPixelCoord = function (x, y) {
            var p = {};
            p.x = this.map.getPixelFromLonLat(new OpenLayers.LonLat(x, y)).x;
            p.y = this.map.getPixelFromLonLat(new OpenLayers.LonLat(x, y)).y;
            return p;
        };
        this.zoomToPoint = function (x, y, z) {
            this.map.setCenter(new OpenLayers.LonLat(x, y), z);
        };
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
                try {
                    popup.destroy();
                } catch (e) {
                }
                var mapBounds = this.map.getExtent();
                var boundsArr = mapBounds.toArray();
                var boundsStr = boundsArr.join(",");
                var mapSize = this.map.getSize();
                var popupTemplate = '<div style="position:relative;"><div></div><div id="queryResult" style="display: table"></div><button onclick="popup.destroy()" style="position:absolute; top: -10px; right: 5px" type="button" class="close" aria-hidden="true">&times;</button></div>';
                var anchor = new OpenLayers.LonLat(coords.lon, coords.lat);
                popup = new OpenLayers.Popup.Anchored("result", anchor, new OpenLayers.Size(100, 150), popupTemplate, null, false, null);
                popup.panMapIfOutOfView = true;
                $.ajax({
                    dataType: 'jsonp',
                    data: 'proj=25832&lon=' + coords.lon + '&lat=' + coords.lat + '&layers=' + parentMap.getVisibleLayers() + '&extent=' + boundsStr + '&width=' + mapSize.w + '&height=' + mapSize.h,
                    jsonp: 'jsonp_callback',
                    url: "//plandk2.mapcentia.com" + '/apps/viewer/servers/query/' + db,
                    success: function (response) {
                        //waitPopup.destroy();

                        if (response.html !== false && response.html !== "") {
                            // Dirty hack! We have to add the popup measure the width, destroy it and add it again width the right width.
                            parentMap.map.addPopup(popup);
                            $("#queryResult").html(response.html);
                            var width = $("#queryResult").width() + 35;
                            popup.destroy();
                            popup = new OpenLayers.Popup.Anchored("result", anchor, new OpenLayers.Size(width, 150), popupTemplate, null, false, null);
                            parentMap.map.addPopup(popup);
                            $("#queryResult").html(response.html);
                            //popup.relativePosition="tr";
                            //vectors.removeAllFeatures();
                            //parentMap.map.raiseLayer(vectors, 10);
                            //for (var i = 0; i < response.renderGeometryArray.length; ++i) {
                            //    vectors.addFeatures(deserialize(response.renderGeometryArray[i][0]));
                            //}
                        } else {
                            $("#alert").fadeIn(400).delay(1000).fadeOut(400);
                            vectors.removeAllFeatures();
                        }
                    }
                });
            }
        });
        this.map = new OpenLayers.Map(el, {
            controls: defaults.controls,
            numZoomLevels: defaults.numZoomLevels,
            projection: defaults.projection,
            maxResolution: defaults.maxResolution,
            minResolution: defaults.minResolution,
            maxExtent: defaults.maxExtent,
            restrictedExtent: defaults.restrictedExtent
        });
        var _map = this.map;
        this.click = new this.clickController();
        this.map.addControl(this.click);
        var vectors = new OpenLayers.Layer.Vector("Mark", {
            displayInLayerSwitcher: false
        });
        this.map.addLayers([vectors]);

        // MapQuest OSM doesn't work anymore. Switching to OSM
        this.addMapQuestOSM = function () {
            this.mapQuestOSM = new OpenLayers.Layer.OSM("MapQuest-OSM");
            this.mapQuestOSM.wrapDateLine = true;
            this.map.addLayer(this.mapQuestOSM);
            return (this.mapQuestOSM);
        };
        this.addMapQuestAerial = function () {
            this.mapQuestAerial = new OpenLayers.Layer.OSM("MapQuest Open Aerial Tiles");
            this.mapQuestAerial.wrapDateLine = true;
            this.map.addLayer(this.mapQuestAerial);
            return (this.mapQuestAerial);
        };

        this.addOSM = function () {
            this.osm = new OpenLayers.Layer.OSM("OSM");
            this.osm.wrapDateLine = true;
            this.map.addLayer(this.osm);
            return (this.osm);
        };
        this.addGoogleStreets = function () {
            // v2
            try {
                this.baseGNORMAL = new OpenLayers.Layer.Google("Google Streets", {
                    type: G_NORMAL_MAP,
                    sphericalMercator: true,
                    wrapDateLine: true,
                    numZoomLevels: 20
                });

            } catch (e) {
            }
            // v3
            try {
                this.baseGNORMAL = new OpenLayers.Layer.Google("Google Streets", {// the default
                    wrapDateLine: true,
                    numZoomLevels: 20
                });
            } catch (e) {
            }
            this.map.addLayer(this.baseGNORMAL);
            return (this.baseGNORMAL);
        };
        this.addGoogleHybrid = function () {
            // v2
            try {
                this.baseGHYBRID = new OpenLayers.Layer.Google("Google Hybrid", {
                    type: G_HYBRID_MAP,
                    sphericalMercator: true,
                    wrapDateLine: true,
                    numZoomLevels: 20
                });
            } catch (e) {
            }
            // v3
            try {
                this.baseGHYBRID = new OpenLayers.Layer.Google("Google Hybrid", {
                    type: google.maps.MapTypeId.HYBRID,
                    wrapDateLine: true,
                    numZoomLevels: 20
                });
            } catch (e) {
                alert(e.message)
            }
            this.map.addLayer(this.baseGHYBRID);
            return (this.baseGHYBRID);
        };
        this.addGoogleSatellite = function () {
            // v3
            try {
                this.baseGSATELLITE = new OpenLayers.Layer.Google("Google Satellite", {
                    type: google.maps.MapTypeId.SATELLITE,
                    wrapDateLine: true,
                    numZoomLevels: 20
                });
            } catch (e) {
                alert(e.message)
            }
            this.map.addLayer(this.baseGSATELLITE);
            return (this.baseGSATELLITE);
        };
        this.addGoogleTerrain = function () {
            // v3
            try {
                this.baseGTERRAIN = new OpenLayers.Layer.Google("Google Terrain", {
                    type: google.maps.MapTypeId.TERRAIN,
                    wrapDateLine: true,
                    numZoomLevels: 20
                });
            } catch (e) {
                alert(e.message)
            }
            this.map.addLayer(this.baseGTERRAIN);
            return (this.baseGTERRAIN);
        };
        this.addBing = function (type) {
            var l, name;
            switch (type) {
                case "Road":
                    name = "Bing Road";
                    break;
                case "Aerial":
                    name = "Bing Aerial";
                    break;
                case "AerialWithLabels":
                    name = "Bing Aerial With Labels";
                    break;
            }
            l = new OpenLayers.Layer.Bing({
                name: name,
                wrapDateLine: true,
                key: this.bingApiKey,
                type: type
            });
            this.map.addLayer(l);
            l.baseLayer = true;
            l.id = name;
            return (l);
        };
        this.addStamenToner = function () {
            this.stamenToner = new OpenLayers.Layer.Stamen("toner");
            this.stamenToner.name = "Stamen Toner";
            this.stamenToner.wrapDateLine = true;
            this.map.addLayer(this.stamenToner);
            return (this.stamenToner);
        };
        this.addYandex = function () {
            //this.yandexMaps = new OpenLayers.Layer.Yandex("Яndex", {sphericalMercator: true});
            //this.map.addLayer(this.yandexMaps);
            //return (this.yandexMaps);
        };
        this.addDtkSkaermkort = function (name, layer) {
            var l,
                url = "https://dk.gc2.io/mapcache/baselayers/wms";
            l = new OpenLayers.Layer.WMS(name, url, {
                layers: layer
            }, {
                wrapDateLine: true,
                attribution: "&copy; Geodatastyrelsen"
            });
            this.map.addLayer(l);
            l.baseLayer = true;
            l.id = name;
            return (l);
        };
        this.addHere = function (type) {
            var l, name, schema;
            switch (type) {
                case "hereNormalNightGrey":
                    name = "Here Night";
                    schema = "normal.night.grey";
                    break;
                case "hereNormalDayGrey":
                    name = "Here Day";
                    schema = "normal.day.grey";
                    break;
            }
            l = new OpenLayers.Layer.XYZ(
                type,
                "//1.base.maps.cit.api.here.com/maptile/2.1/maptile/newest/" + schema + "/${z}/${x}/${y}/256/png8?app_id=" + window.gc2Options.hereApp.App_Id + "&app_code=" + window.gc2Options.hereApp.App_Code,
                {
                    attribution: "&copy; Nokia</span>&nbsp;<a href='http://maps.nokia.com/services/terms' target='_blank' title='Terms of Use' style='color:#333;text-decoration: underline;'>Terms of Use</a></div> <img src='//api.maps.nokia.com/2.2.4/assets/ovi/mapsapi/by_here.png' border='0'>"
                }
            );
            this.map.addLayer(l);
            l.setVisibility(false);
            l.baseLayer = true;
            l.name = name;
            l.id = type;
            return (l);
        };
        this.addDigitalGlobe = function (type) {
            var l, name, key = this.digitalGlobeKey;
            switch (type) {
                case "DigitalGlobe:Imagery":
                    name = "DigitalGlobe:ImageryTileService";
                    break;
            }
            l = new OpenLayers.Layer.XYZ(
                type,
                "https://services.digitalglobe.com/earthservice/wmtsaccess?CONNECTID=" + key + "&Service=WMTS&REQUEST=GetTile&Version=1.0.0&Format=image/png&Layer=" + name + "&TileMatrixSet=EPSG:3857&TileMatrix=EPSG:3857:${z}&TileRow=${y}&TileCol=${x}"
            );
            this.map.addLayer(l);
            l.baseLayer = true;
            l.id = type;
            return (l);
        };
        this.setBaseLayer = function (baseLayer) {
            this.map.setBaseLayer(baseLayer);
        };
        this.addBaseLayer = function (l, db, altId, lName, host) {
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
                case "dtkSkaermkort":
                    o = this.addDtkSkaermkort("dtkSkaermkort", "kortforsyningen.dtk_skaermkort");
                    break;
                case "dtkSkaermkortDaempet":
                    o = this.addDtkSkaermkort("dtkSkaermkortDaempet", "kortforsyningen.dtk_skaermkort_daempet");
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
                    o = this.addTileLayers([l], {
                        db: db,
                        host: host,
                        isBaseLayer: true,
                        visibility: false,
                        wrapDateLine: false,
                        displayInLayerSwitcher: true,
                        name: lName,
                        altId: altId
                    })[0];
                    break;
            }
            return o;
        };
        this.addTileLayers = function (layers, config) {
            var defaults = {
                db: this.db,
                host: host,
                singleTile: false,
                opacity: 1,
                isBaseLayer: false,
                visibility: true,
                wrapDateLine: true,
                tileCached: true,
                displayInLayerSwitcher: true,
                name: null,
                altId: null
            };
            if (config) {
                for (prop in config) {
                    defaults[prop] = config[prop];
                }
            }
            var layersArr = [];
            for (var i = 0; i < layers.length; i++) {
                var l = this.createTileLayer(layers[i], defaults);
                this.map.addLayer(l);
                layersArr.push(l);
            }
            return layersArr;
        };
        this.createTileLayer = function (layer, defaults) {
            var parts = layer.split("."), url;
            if (!defaults.tileCached) {
                url = (defaults.host || host) + "/wms/" + defaults.db + "/" + parts[0] + "?";
            } else {
                url = (defaults.host || host) + "/mapcache/" + defaults.db + "/wms?";
            }
            var l = new OpenLayers.Layer.WMS(defaults.name, url, {
                layers: layer,
                transparent: true
            }, defaults);
            l.id = defaults.altId || layer;
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
            this.map.addLayer(this.createTileLayerGroup(layers, defaults));
        };
        this.createTileLayerGroup = function (layers, defaults) {
            var l = new OpenLayers.Layer.WMS(defaults.name, host + "/wms/" + this.db + "/" + defaults.schema + "/?", {
                layers: layers,
                transparent: true
            }, defaults);
            return l;
        };
        this.addWmtsLayer = function (layerConfig) {
            var layer = null;

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
            control.activate();
            return control;
        };
        this.removeGeoJsonStore = function (store) {
            this.map.removeLayer(store.layer);
            //??????????????
        };
        this.hideLayer = function (name) {
            this.map.getLayersByName(name)[0].setVisibility(false);

        };
        this.showLayer = function (name) {
            this.map.getLayersByName(name)[0].setVisibility(true);
        };
        //this.addGoogleStreets();
        this.getCenter = function () {
            var point = this.map.center;
            return {
                x: point.lon,
                y: point.lat
            };
        };
        this.getExtent = function () {
            var mapBounds = this.map.getExtent();
            return mapBounds.toArray();
        };
        this.getBbox = function () {
            return this.map.getExtent().toString();
        };
        // Geolocation stuff starts here
        var geolocation_layer = new OpenLayers.Layer.Vector('geolocation_layer', {
            displayInLayerSwitcher: false
        });
        var firstGeolocation = true;
        var style = {
            fillColor: '#000',
            fillOpacity: 0.1,
            strokeWidth: 0
        };
        this.map.addLayers([geolocation_layer]);
        var firstCallBack;
        var trackCallBack;
        this.locate = function (config) {
            var defaults = {
                firstCallBack: function () {
                },
                trackCallBack: function () {
                }
            };
            if (config) {
                for (prop in config) {
                    defaults[prop] = config[prop];
                }
            }
            firstCallBack = defaults.firstCallBack;
            trackCallBack = defaults.trackCallBack;
            geolocation_layer.removeAllFeatures();
            geolocate.deactivate();
            //$('track').checked = false;
            geolocate.watch = false;
            firstGeolocation = true;
            geolocate.activate();
        };
        this.stopLocate = function () {
            geolocate.deactivate();
        };
        var geolocate = new OpenLayers.Control.Geolocate({
            bind: false,
            geolocationOptions: {
                enableHighAccuracy: false,
                maximumAge: 0,
                timeout: 7000
            }
        });
        this.map.addControl(geolocate);
        geolocate.events.register("locationupdated", geolocate, function (e) {
            geolocation_layer.removeAllFeatures();
            var circle = new OpenLayers.Feature.Vector(OpenLayers.Geometry.Polygon.createRegularPolygon(new OpenLayers.Geometry.Point(e.point.x, e.point.y), e.position.coords.accuracy / 2, 40, 0), {}, style);
            geolocation_layer.addFeatures([new OpenLayers.Feature.Vector(e.point, {}, {
                graphicName: 'cross',
                strokeColor: '#f00',
                strokeWidth: 1,
                fillOpacity: 0,
                pointRadius: 10
            }), circle]);
            parentMap.geoLocation = {
                x: e.point.x,
                y: e.point.y,
                obj: e
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
        geolocate.events.register("locationfailed", this, function () {
            alert("No location");
        });
        var pulsate = function (feature) {
            var point = feature.geometry.getCentroid(), bounds = feature.geometry.getBounds(), radius = Math.abs((bounds.right - bounds.left) / 2), count = 0, grow = 'up';

            var resize = function () {
                if (count > 16) {
                    clearInterval(window.resizeInterval);
                }
                var interval = radius * 0.03;
                var ratio = interval / radius;
                switch (count) {
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
        };
    };
    var deserialize = function (element) {
        // console.log(element);
        var type = "wkt";
        var format = new OpenLayers.Format.WKT;
        var features = format.read(element);
        return features;
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
        pathName: window.location.pathname.split("/")
    };

})();



