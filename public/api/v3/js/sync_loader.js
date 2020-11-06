/*
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

var host = window.geocloud_host || "";
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
    document.write("<script src='https://swarm.gc2.io/js/openlayers/OpenLayers.js' type='text/javascript'><\/script>");
} else if (window.geocloud_maplib === "leaflet") {
    document.write("<script src='" + host + "/js/leaflet/leaflet-all.js' type='text/javascript'><\/script>");
}
document.write("<script src='https://swarm.gc2.io/js/openlayers/proj4js-combined.js' type='text/javascript'><\/script>");
document.write("<script src='" + host + "/api/v1/baselayerjs' type='text/javascript'><\/script>");
document.write("<script src='" + host + "/api/v3/js/geocloud.js'><\/script>");
document.write("<script src='" + host + "/js/i18n/" + "en_US" + ".js'><\/script>");

// Load some css
if (window.geocloud_maplib === "leaflet") {
    document.write("<link rel='stylesheet' type='text/css' href='" + host + "/js/leaflet/plugins/awesome-markers/leaflet.awesome-markers.css'>");
    document.write("<link rel='stylesheet' type='text/css' href='" + host + "/js/leaflet/plugins/Leaflet.draw/leaflet.draw.css'>");
    document.write("<link rel='stylesheet' type='text/css' href='" + host + "/js/leaflet/plugins/Leaflet.label/leaflet.label.css'>");
    document.write("<link rel='stylesheet' type='text/css' href='" + host + "/js/leaflet/plugins/markercluster/MarkerCluster.Default.css'>");
    document.write("<link rel='stylesheet' type='text/css' href='https://netdna.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css'>");
}
