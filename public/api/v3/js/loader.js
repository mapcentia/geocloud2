// Only initiate once when the file is load twice or more
if (typeof gc2apiLoader === "undefined") {
    var gc2apiLoader;
    gc2apiLoader = (function () {
        "use strict";
        window.__ = function (string) {
            if (typeof gc2i18n !== 'undefined') {
                if (gc2i18n.dict[string]) {
                    return gc2i18n.dict[string];
                }
            }
            return string;
        };
        var scriptSource = (function () {
                var scripts = document.getElementsByTagName('script'),
                    script = scripts[scripts.length - 1];
                if (script.getAttribute.length !== undefined) {
                    return script.src;
                }
                return script.getAttribute('src', -1);
            }()),
            host;
        if (typeof window.geocloud_host === "undefined") {
            // In IE7 host name is missing if script url is relative
            window.geocloud_host = (scriptSource.charAt(0) === "/") ? "" : scriptSource.split("/")[0] + "//" + scriptSource.split("/")[2];
        }
        host = window.geocloud_host;
        if (typeof $ === "undefined") {
            var head = document.getElementsByTagName("head")[0],
                js = document.createElement("script");
            js.type = "text/javascript";
            js.src = "http://ajax.googleapis.com/ajax/libs/jquery/1.10.0/jquery.min.js";
            head.appendChild(js);
        }
        (function pollForjQuery() {
            if (typeof $ !== "undefined") {
                // Load loadDependencies
                //$.getScript("http://cdn.eu1.mapcentia.com/js/openlayers/OpenLayers.js");
                $.getScript("http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js");
                $.getScript("http://cdn.eu1.mapcentia.com/js/openlayers/proj4js-combined.js");
                $.getScript(host + "/api/v1/baselayerjs");

                (function pollForDependencies() {
                    if (typeof L !== "undefined" &&
                        typeof Proj4js !== "undefined" &&
                        typeof window.setBaseLayers !== "undefined"
                        ) {
                        // Load Dependants
                        $.getScript(host + "/api/v3/js/geocloud.js");
                        (function pollForDependants() {
                            if (typeof geocloud !== "undefined") {
                                $.getScript(host + "/js/i18n/da_DK.js");
                                (function pollForDict() {
                                    if (typeof gc2i18n !== "undefined") {
                                        var context = gc2i18n.dict;
                                        window.go();
                                    } else {
                                        setTimeout(pollForDict, 10);
                                    }
                                }());
                            } else {
                                setTimeout(pollForDependants, 10);
                            }
                        }());
                    } else {
                        setTimeout(pollForDependencies, 10);
                    }
                }());
                $('<link/>').attr({ rel: 'stylesheet', type: 'text/css', href: 'http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.css' }).appendTo('head');
            } else {
                setTimeout(pollForjQuery, 10);
            }
        }());
    }());
}




