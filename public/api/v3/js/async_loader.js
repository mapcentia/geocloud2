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
            js.src = "//ajax.googleapis.com/ajax/libs/jquery/1.10.0/jquery.min.js";
            document.getElementsByTagName("head")[0].appendChild(js);
        }
        (function pollForjQuery() {
            if (typeof $ !== "undefined") {
                // Load loadDependencies
                if (window.geocloud_maplib === "ol2" && typeof OpenLayers === "undefined") {
                    $.getScript(host + "/js/openlayers/OpenLayers.js");
                }
                else if (window.geocloud_maplib === "leaflet" && typeof L === "undefined") {
                    $.getScript(host + "/js/leaflet/leaflet.js");
                }
                $.getScript(host + "/js/openlayers/proj4js-combined.js");
                $.getScript(host + "/api/v1/baselayerjs");
                (function pollForDependencies() {
                    if ((typeof L !== "undefined" || typeof OpenLayers !== "undefined") &&
                        typeof Proj4js !== "undefined" &&
                        typeof window.setBaseLayers !== "undefined"
                    ) {
                        // Load Dependants
                        $.getScript(host + "/api/v3/js/geocloud.min.js?adcf76f3df0740c9#grunt-cache-bust");
                        if (window.geocloud_maplib === "leaflet" && typeof L.drawVersion === "undefined" && typeof L.labelVersion === "undefined") {
                            $.getScript(host + "/js/leaflet/plugins/Leaflet.draw/leaflet.draw.js");
                            $.getScript(host + "/js/leaflet/plugins/Leaflet.label/leaflet.label.js");
                        }
                        (function pollForDependants() {
                            if (typeof geocloud !== "undefined" && (window.geocloud_maplib === "ol2" || typeof L.drawVersion !== "undefined") && (window.geocloud_maplib === "ol2" || typeof L.labelVersion !== "undefined")) {
                                if (window.geocloud_maplib === "leaflet") {
                                    L.Icon.Default.imagePath = "/js/leaflet/images";
                                }
                                $.getScript(host + "/js/i18n/" + window.gc2Al + ".js");
                                (function pollForDict() {
                                    if (typeof gc2i18n !== "undefined") {
                                        window[window.geocloud_callback]();
                                    } else {
                                        setTimeout(pollForDict, 3);
                                    }
                                }());
                            } else {
                                setTimeout(pollForDependants, 3);
                            }
                        }());
                    } else {
                        setTimeout(pollForDependencies, 3);
                    }
                }());
            } else {
                setTimeout(pollForjQuery, 3);
            }
        }());
        // Load some css
        if (window.geocloud_maplib === "leaflet") {
            $('<link/>').attr({
                rel: 'stylesheet',
                type: 'text/css',
                href: host + '/js/leaflet/plugins/awesome-markers/leaflet.awesome-markers.css'
            }).appendTo('head');
            $('<link/>').attr({
                rel: 'stylesheet',
                type: 'text/css',
                href: host + '/js/leaflet/plugins/Leaflet.draw/leaflet.draw.css'
            }).appendTo('head');
            $('<link/>').attr({
                rel: 'stylesheet',
                type: 'text/css',
                href: host + '/js/leaflet/plugins/Leaflet.label/leaflet.label.css'
            }).appendTo('head');
        }
        $('<link/>').attr({
            rel: 'stylesheet',
            type: 'text/css',
            href: '//netdna.bootstrapcdn.com/font-awesome/4.0.1/css/font-awesome.min.css'
        }).appendTo('head');
    }());
}






