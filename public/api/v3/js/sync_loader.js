var host = window.geocloud_host;
window.__ = function (string) {
    if (typeof gc2i18n !== 'undefined') {
        if (gc2i18n.dict[string]) {
            return gc2i18n.dict[string];
        }
    }
    return string;
};
if (typeof $ === "undefined") {
    document.write("<script src='http://ajax.googleapis.com/ajax/libs/jquery/1.10.0/jquery.min.js' type='text/javascript'><\/script>");
}
if (window.geocloud_maplib === "ol2") {
    document.write("<script src='http://cdn.eu1.mapcentia.com/js/openlayers/OpenLayers.js' type='text/javascript'><\/script>");
} else if (window.geocloud_maplib === "leaflet") {
    document.write("<script src='http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js' type='text/javascript'><\/script>");
}
document.write("<script src='http://cdn.eu1.mapcentia.com/js/openlayers/proj4js-combined.js' type='text/javascript'><\/script>");
document.write("<script src='" + host + "/api/v1/baselayerjs' type='text/javascript'><\/script>");
document.write("<script src='" + host + "/api/v3/js/geocloud.js'><\/script>");
document.write("<script src='" + host + "/js/i18n/" + "en_US" + ".js'><\/script>");

// Load some css
if (window.geocloud_maplib === "leaflet") {
    document.write("<link rel='stylesheet' type='text/css' href='" + host + "/js/leaflet/plugins/awesome-markers/leaflet.awesome-markers.css'>");
    document.write("<link rel='stylesheet' type='text/css' href='" + host + "/js/leaflet/plugins/Leaflet.draw/leaflet.draw.css'>");
    document.write("<link rel='stylesheet' type='text/css' href='" + host + "/js/leaflet/plugins/Leaflet.label/leaflet.label.css'>");
    document.write("<link rel='stylesheet' type='text/css' href='//netdna.bootstrapcdn.com/font-awesome/4.0.1/css/font-awesome.min.css'>");
}