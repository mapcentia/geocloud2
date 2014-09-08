// Only initiate once when the file is load twice or more
/*if (typeof gc2map === "undefined") {
 var gc2map;
 gc2map = (function () {
 "use strict";
 window.__ = function (string) {
 if (typeof gc2i18n !== 'undefined') {
 if (gc2i18n.dict[string]) {
 return gc2i18n.dict[string];
 }
 }
 return string;
 };
 var init, js, maps = [],
 scriptSource = (function () {
 var scripts = document.getElementsByTagName('script'),
 script = scripts[scripts.length - 1];
 if (script.getAttribute.length !== undefined) {
 return script.src;
 }
 return script.getAttribute('src', -1);
 }()),
 scriptHost, host;
 if (typeof window.geocloud_host === "undefined") {
 window.geocloud_host = (scriptSource.charAt(0) === "/") ? "" : scriptSource.split("/")[0] + "//" + scriptSource.split("/")[2];
 }
 scriptHost = host = window.geocloud_host;
 if (typeof $ === "undefined") {
 document.write("<script src='http://ajax.googleapis.com/ajax/libs/jquery/1.10.0/jquery.min.js' type='text/javascript'><\/script>");
 }
 if (window.geocloud_maplib === "ol2") {
 document.write("<script src='http://cdn.eu1.mapcentia.com/js/openlayers/OpenLayers.js' type='text/javascript'><\/script>");
 } else {
 document.write("<script src='http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js' type='text/javascript'><\/script>");
 }
 document.write("<script src='http://cdn.eu1.mapcentia.com/js/openlayers/proj4js-combined.js' type='text/javascript'><\/script>");
 document.write("<script src='http://cdn.eu1.mapcentia.com/js/bootstrap3/js/bootstrap.min.js' type='text/javascript'><\/script>");
 document.write("<script src='http://cdn.eu1.mapcentia.com/js/hogan/hogan-2.0.0.js' type='text/javascript'><\/script>");
 document.write("<script src='" + host + "/apps/widgets/gc2map/js/bootstrap-alert.js' type='text/javascript'><\/script>");
 document.write("<script src='" + host + "/api/v1/baselayerjs' type='text/javascript'><\/script>");
 document.write("<script src='" + host + "/api/v3/js/geocloud.js'><\/script>");
 document.write("<script src='" + host + "/apps/widgets/gc2map/js/main.js'><\/script>");
 document.write("<script src='" + host + "/apps/widgets/gc2map/js/templates.js'><\/script>");
 document.write("<script src='" + host + "/js/i18n/da_DK.js'><\/script>");
 init = function (conf) {
 $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: scriptHost + '/apps/widgets/gc2map/css/bootstrap.css' }).appendTo('head');
 $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: scriptHost + '/apps/widgets/gc2map/css/bootstrap-alert.css' }).appendTo('head');
 $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: scriptHost + '/apps/widgets/gc2map/css/non-responsive.css' }).appendTo('head');
 $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: scriptHost + '/apps/widgets/gc2map/css/styles.css' }).appendTo('head');
 $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: 'http://netdna.bootstrapcdn.com/font-awesome/4.0.1/css/font-awesome.min.css' }).appendTo('head');
 $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: 'http://fonts.googleapis.com/css?family=Open+Sans+Condensed:300' }).appendTo('head');

 var defaults = {
 host: host,
 width: "100%",
 height: "100%",
 staticmap: false,
 locale: "en_US"
 }, prop, divs = document.getElementsByTagName('div'),
 div = divs[divs.length - 1],
 gc2RandId = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
 var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
 return v.toString(16);
 });
 if (conf) {
 for (prop in conf) {
 defaults[prop] = conf[prop];
 }
 }
 div.setAttribute("id", gc2RandId);
 div.setAttribute("class", "gc2map");
 div.style.width = defaults.width;
 div.style.height = defaults.height;
 if (div.style.position === "") {
 div.style.position = "relative";
 }
 var context;
 context = gc2i18n.dict;
 context.id = gc2RandId;
 if (defaults.staticmap) {
 $("#" + gc2RandId).html("<img src='" + defaults.host + "/api/v1/staticmap/png/mydb?baselayer=" + defaults.setBaseLayer.toUpperCase() + "&layers=" + defaults.layers.join(",") + "&size=" + $("#" + gc2RandId).width() + "x" + $("#" + gc2RandId).height() + "&zoom=" + defaults.zoom[2] + "&center=" + defaults.zoom[1] + "," + defaults.zoom[0] + "&lifetime=10'>");
 } else {
 $("#" + gc2RandId).html(templates.body.render(context));
 setTimeout(function () {
 if (gc2i18n.dict["Info text"] !== "") {
 $(".alert").show();
 }
 maps[gc2RandId] = new MapCentia(gc2RandId);
 maps[gc2RandId].init(defaults);
 }, 100);
 }
 };
 return {
 maps: maps,
 init: init
 };
 }());
 }*/
if (typeof gc2map === "undefined") {
    var gc2map;
    gc2map = (function () {
        "use strict";
        window.__ = function (string) {
            if (typeof gc2i18n !== 'undefined') {
                if (gc2i18n.dict[string]) {
                    return gc2i18n.dict[string];
                }
            }
            return string;
        };
        var init, js, maps = [],
            scriptsLoaded = false,
            scriptSource = (function () {
                var scripts = document.getElementsByTagName('script'),
                    script = scripts[scripts.length - 1];
                if (script.getAttribute.length !== undefined) {
                    return script.src;
                }
                return script.getAttribute('src', -1);
            }()),
            scriptHost, host;
        if (typeof window.geocloud_host === "undefined") {
            window.geocloud_host = (scriptSource.charAt(0) === "/") ? "" : scriptSource.split("/")[0] + "//" + scriptSource.split("/")[2];
        }
        //host = "http://local2.mapcentia.com";
        scriptHost = host = window.geocloud_host;
        if (typeof $ === "undefined") {
            js = document.createElement("script");
            js.type = "text/javascript";
            js.src = "//ajax.googleapis.com/ajax/libs/jquery/1.10.0/jquery.min.js";
            document.getElementsByTagName("head")[0].appendChild(js);
        }
        (function pollForjQuery() {
            if (typeof $ !== "undefined") {
                // Load loadDependencies
                //$.getScript("http://cdn.eu1.mapcentia.com/js/openlayers/OpenLayers.js");
                $.getScript("/js/leaflet/leaflet.js");
                $.getScript("/js/openlayers/proj4js-combined.js");
                $.getScript("/js/bootstrap3/js/bootstrap.min.js");
                $.getScript("/js/hogan/hogan-2.0.0.js");
                $.getScript(host + "/apps/widgets/gc2map/js/bootstrap-alert.js");
                $.getScript(host + "/api/v1/baselayerjs");
                (function pollForDependencies() {
                    if (typeof L !== "undefined" &&
                        typeof Proj4js !== "undefined" &&
                        typeof $().emulateTransitionEnd !== 'undefined' &&
                        typeof Hogan !== "undefined" &&
                        typeof window.setBaseLayers !== "undefined"
                        ) {
                        // Load Dependants
                        $.getScript(host + "/api/v3/js/geocloud.js");
                        $.getScript(scriptHost + "/apps/widgets/gc2map/js/main.js");
                        $.getScript(host + "/apps/widgets/gc2map/js/templates.js");
                        (function pollForDependants() {
                            if (typeof geocloud !== "undefined" && typeof MapCentia !== "undefined" && typeof templates !== "undefined") {
                                scriptsLoaded = true;
                            } else {
                                setTimeout(pollForDependants, 10);
                            }
                        }());
                    } else {
                        setTimeout(pollForDependencies, 10);
                    }
                }());
                $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: scriptHost + '/apps/widgets/gc2map/css/bootstrap.css' }).appendTo('head');
                $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: scriptHost + '/apps/widgets/gc2map/css/bootstrap-alert.css' }).appendTo('head');
                $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: scriptHost + '/apps/widgets/gc2map/css/non-responsive.css' }).appendTo('head');
                $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: scriptHost + '/apps/widgets/gc2map/css/styles.css' }).appendTo('head');
                $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: '//netdna.bootstrapcdn.com/font-awesome/4.0.1/css/font-awesome.min.css' }).appendTo('head');
                $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: '//fonts.googleapis.com/css?family=Open+Sans+Condensed:300' }).appendTo('head');
            } else {
                setTimeout(pollForjQuery, 10);
            }
        }());

        init = function (conf) {
            var defaults = {
                    host: host,
                    width: "100%",
                    height: "100%",
                    staticMap: false,
                    infoText: null,
                    infoTextWidth: "200px",
                    locale: null,
                    key: null,
                    clickDistance: 5,
                    baseLayers: null,
                    callBack: function () {
                    }
                },
                prop,
                divs = document.getElementsByTagName('div'),
                div = divs[divs.length - 1],
                gc2RandId;
            if (conf) {
                for (prop in conf) {
                    defaults[prop] = conf[prop];
                }
            }
            gc2RandId = (defaults.key !== null) ? defaults.key : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
            div.setAttribute("id", gc2RandId);
            div.setAttribute("class", "gc2map");
            div.style.width = defaults.width;
            div.style.height = defaults.height;
            if (div.style.position === "") {
                div.style.position = "relative";
            }
            (function pollForScripts() {
                var i = 0;
                if (scriptsLoaded) {
                    var context;
                    $.getScript(host + "/js/i18n/" + (defaults.locale || window.gc2Al) + ".js");
                    (function pollForDict() {
                        if (typeof gc2i18n !== "undefined") {
                            context = gc2i18n.dict;
                            context.id = gc2RandId;
                            context.infoText = defaults.infoText;
                            context.infoTextWidth = defaults.infoTextWidth;
                            if (defaults.staticMap) {
                                if (typeof defaults.extent === "object") {
                                    var p1 = geocloud.transformPoint(defaults.extent[0], defaults.extent[1], "EPSG:4326", "EPSG:900913");
                                    var p2 = geocloud.transformPoint(defaults.extent[2], defaults.extent[3], "EPSG:4326", "EPSG:900913");
                                    defaults.extent = [p1.x, p1.y, p2.x, p2.y];
                                    $("#" + gc2RandId).html("<img src='" + defaults.host + "/api/v1/staticmap/png/" + defaults.db + "?baselayer=" + defaults.setBaseLayer.toUpperCase() + "&layers=" + defaults.layers.join(",") + "&size=" + $("#" + gc2RandId).width() + "x" + $("#" + gc2RandId).height() + "&bbox=" + defaults.extent.join(",") + "&lifetime=10'>");
                                } else if (typeof defaults.zoom === "object") {
                                    $("#" + gc2RandId).html("<img src='" + defaults.host + "/api/v1/staticmap/png/" + defaults.db + "?baselayer=" + defaults.setBaseLayer.toUpperCase() + "&layers=" + defaults.layers.join(",") + "&size=" + $("#" + gc2RandId).width() + "x" + $("#" + gc2RandId).height() + "&zoom=" + defaults.zoom[2] + "&center=" + defaults.zoom[1] + "," + defaults.zoom[0] + "&lifetime=10'>");
                                }
                            } else {
                                $("#" + gc2RandId).html(templates.body.render(context));
                                if (defaults.infoText) {
                                    $("#info-text-" + gc2RandId).show();
                                }
                                maps[gc2RandId] = new MapCentia(gc2RandId);
                                maps[gc2RandId].init(defaults);
                            }
                        } else {
                            i = i + 1;
                            if (i > 100) {
                                alert("Seems that i18n file " + (defaults.locale || window.gc2Al) + ".js is not loading. Please check your locale setting.");
                            } else {
                                setTimeout(pollForDict, 10);
                            }
                        }
                    }());
                } else {
                    setTimeout(pollForScripts, 10);
                }
            }());
            return {
                maps: maps
            };
        };
        return {
            maps: maps,
            init: init
        };
    }());
}






