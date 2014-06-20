// Only initiate once when the file is load twice or more
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
            js.src = "http://ajax.googleapis.com/ajax/libs/jquery/1.10.0/jquery.min.js";
            document.getElementsByTagName("head")[0].appendChild(js);
        }
        (function pollForjQuery() {
            if (typeof $ !== "undefined") {
                // Load loadDependencies
                //$.getScript("http://cdn.eu1.mapcentia.com/js/openlayers/OpenLayers.js");
                $.getScript("http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js");
                $.getScript("http://cdn.eu1.mapcentia.com/js/openlayers/proj4js-combined.js");
                $.getScript("http://cdn.eu1.mapcentia.com/js/bootstrap3/js/bootstrap.min.js");
                $.getScript("http://cdn.eu1.mapcentia.com/js/hogan/hogan-2.0.0.js");
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
                $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: 'http://netdna.bootstrapcdn.com/font-awesome/4.0.1/css/font-awesome.min.css' }).appendTo('head');
                $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: scriptHost + '/apps/widgets/gc2map/css/non-responsive.css' }).appendTo('head');
                $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: scriptHost + '/apps/widgets/gc2map/css/styles.css' }).appendTo('head');
                $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: 'http://fonts.googleapis.com/css?family=Open+Sans+Condensed:300' }).appendTo('head');
                $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: 'http://fonts.googleapis.com/css?family=Open+Sans+Condensed:300' }).appendTo('head');
            } else {
                setTimeout(pollForjQuery, 10);
            }
        }());

        init = function (conf) {
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
            (function pollForScripts() {
                if (scriptsLoaded) {
                    var context;
                    $.getScript(host + "/js/i18n/" + defaults.locale + ".js");
                    (function pollForDict() {
                        if (typeof gc2i18n !== "undefined") {
                            context = gc2i18n.dict;
                            context.id = gc2RandId;
                            if (defaults.staticmap) {
                                $("#" + gc2RandId).html("<img src='" + defaults.host + "/api/v1/staticmap/png/mydb?baselayer=" + defaults.setBaseLayer.toUpperCase() + "&layers=" + defaults.layers.join(",") + "&size=" + $("#" + gc2RandId).width() + "x" + $("#" + gc2RandId).height() + "&zoom=" + defaults.zoom[2] + "&center=" + defaults.zoom[1] + "," + defaults.zoom[0] + "&lifetime=10'>");
                            } else {
                                $("#" + gc2RandId).html(templates.body.render(context));
                                if (gc2i18n.dict["Info text"] !== "") {
                                    $(".alert").show();
                                }
                                maps[gc2RandId] = new MapCentia(gc2RandId);
                                // Init the map
                                maps[gc2RandId].init(defaults);
                            }
                        } else {
                            setTimeout(pollForDict, 10);
                        }
                    }());
                } else {
                    setTimeout(pollForScripts, 10);
                }
            }());
        };
        return {
            maps: maps,
            init: init
        };
    }());
}




