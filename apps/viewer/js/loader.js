var mapvars = {};
var parts = window.location.search.replace(/[?&]+([^=&]+)=([^&]*)/gi, function (m, key, value) {
    mapvars[key] = value;
});

if (mapvars['fw'] === "ol3")
    document.write("<script src='/js/ol3/ol.js'><\/script>");
else if (mapvars['fw'] === "leaflet")
    document.write("<script src='/js/leaflet/leaflet.js'><\/script>");
else
    document.write("<script src='/js/leaflet/leaflet.js'><\/script>");