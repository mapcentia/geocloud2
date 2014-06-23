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
        var host, js;
        host = window.geocloud_host;
        if (typeof $ === "undefined") {
            js = document.createElement("script");
            js.type = "text/javascript";
            js.src = "http://ajax.googleapis.com/ajax/libs/jquery/1.10.0/jquery.min.js";
            document.getElementsByTagName("head")[0].appendChild(js);
        }
        (function pollForjQuery() {
            if (typeof $ !== "undefined") {
                // Load loadDependencies
                if (window.geocloud_maplib === "ol2") {
                    $.getScript("http://cdn.eu1.mapcentia.com/js/openlayers/OpenLayers.js");
                }
                else if (window.geocloud_maplib === "leaflet") {
                    $.getScript("http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js");
                }
                $.getScript("http://cdn.eu1.mapcentia.com/js/openlayers/proj4js-combined.js");
                $.getScript(host + "/api/v1/baselayerjs");
                (function pollForDependencies() {
                    if ((typeof L !== "undefined" || typeof OpenLayers !== "undefined") &&
                        typeof Proj4js !== "undefined" &&
                        typeof window.setBaseLayers !== "undefined"
                        ) {
                        // Load Dependants
                        $.getScript(host + "/api/v3/js/geocloud.js");
                        (function pollForDependants() {
                            if (typeof geocloud !== "undefined") {
                                $.getScript(host + "/js/i18n/" + window.gc2Al + ".js");
                                (function pollForDict() {
                                    if (typeof gc2i18n !== "undefined") {
                                        window[window.geocloud_callback]();
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
            } else {
                setTimeout(pollForjQuery, 10);
            }
        }());
    }());
}




