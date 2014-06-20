if (typeof $ === "undefined") {
    document.write("<script src='http://ajax.googleapis.com/ajax/libs/jquery/1.10.0/jquery.min.js' type='text/javascript'><\/script>");
}if (window.geocloud_maplib === "ol2") {
    document.write("<script src='http://cdn.eu1.mapcentia.com/js/openlayers/OpenLayers.js' type='text/javascript'><\/script>");
}
else if (window.geocloud_maplib === "leaflet") {
    document.write("<script src='http://cdn.leafletjs.com/leaflet-0.7.3/leaflet.js' type='text/javascript'><\/script>");
}
document.write("<script src='http://cdn.eu1.mapcentia.com/js/openlayers/proj4js-combined.js' type='text/javascript'><\/script>");
document.write("<script src='" + window.geocloud_host + "/api/v1/baselayerjs' type='text/javascript'><\/script>");
document.write("<script src='" + window.geocloud_host + "/api/v3/js/geocloud.js'><\/script>");
document.write("<script src='" + window.geocloud_host + "/js/i18n/da_DK.js'><\/script>");
