var mapvars = {};
var parts = window.location.search.replace(/[?&]+([^=&]+)=([^&]*)/gi, function (m, key, value) {
    mapvars[key] = value;
});
if (mapvars['fw'] === "ol2") {
    document.write("<script src='/js/openlayers/OpenLayers.js'><\/script>");
}
else if (mapvars['fw'] === "ol3")
    document.write("<script src='http://ol3js.org/en/master/build/ol.js'><\/script>");
else if (mapvars['fw'] === "leaflet") {
    document.write("<script src='/js/leaflet/leaflet.js'><\/script>");
    //document.write("<script src='/js/wicket/wicket.src.js'><\/script>");
    //document.write("<script src='/js/wicket/wicket-leaflet.src.js'><\/script>");
}
else {
    document.write("<script src='/js/leaflet/leaflet.js'><\/script>");
    //document.write("<script src='/js/wicket/wicket.src.js'><\/script>");
    //document.write("<script src='/js/wicket/wicket-leaflet.src.js'><\/script>");
}