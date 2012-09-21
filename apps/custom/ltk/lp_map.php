<?php
	preg_match('/[0-9]+/',$_GET['plan'],$matches);
	$plannr = $matches[0];
?>
<link rel="stylesheet" type="text/css" href="http://beta.mygeocloud.cowi.webhouse.dk/js/ext/resources/css/ext-all.css"/>
<script type="text/javascript" src="http://beta.mygeocloud.cowi.webhouse.dk/api/v1/js/api.js"></script>
<script type="text/javascript" src="http://maps.google.com/maps?file=api&amp;v=2&amp;sensor=false&amp;key=ABQIAAAAixUaqWcOE1cqF2LJyDYCdhTww2B3bmOd5Of57BUV-HZKowzURRTDiOeJ4A8o-OZoiMfdrJzdG3POiw"></script>
<script>
var styleMap = new OpenLayers.StyleMap({
	"default": new OpenLayers.Style({
		pointRadius: 10,
		fillColor: "#ffffff",
		fillOpacity: 0.0,
		strokeColor: "#ff0000",
		strokeWidth: 2,
		graphicZIndex: 1
		}
	)
});
$(window).load(function() {
	// Create a new map object
	var cloud_example2 = new mygeocloud_ol.map("map_example2","ltk");
	// Create a new GeoJSON store
	var store_lp = new mygeocloud_ol.geoJsonStore("ltk",{
		styleMap: styleMap
	});
	var store_lpdel = new mygeocloud_ol.geoJsonStore("ltk",{
		styleMap: styleMap
	});
	cloud_example2.addGeoJsonStore(store_lp);
	cloud_example2.addGeoJsonStore(store_lpdel);
	store_lp.sql = "SELECT * FROM lokalplaner.lpplandk2_view WHERE plannr='<?php echo $plannr 	?>'";
	store_lpdel.sql = "SELECT * FROM lokalplaner.lpdelplandk2_view";
	store_lp.load();
	//store_lpdel.load();
	store_lp.onLoad = function(){
		cloud_example2.zoomToExtentOfgeoJsonStore(store_lp);
	};
});
</script>
<div id="map_example2" style="width: 100%;height: 350px"></div>