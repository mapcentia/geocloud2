var styleMap = new OpenLayers.StyleMap({
							//ses ikke fordi fillOpacity og strokeOpacity er 0.0
							"default": new OpenLayers.Style({
								fillColor: "#ffffff",
								fillOpacity: 0.0,
								strokeColor: "#00aa00",
								strokeWidth: 2,
								strokeOpacity: 0.0,
								graphicZIndex: 2
								}
							),
							"temporary": new OpenLayers.Style({
								fillColor: "#00ff00",
								fillOpacity: 0.0,
								strokeColor: "#969696",
								strokeWidth: 2,
								strokeOpacity: 1.0,
								graphicZIndex: 3
								}
							)
						});
$(window).load(function(){
	cloud = new mygeocloud_ol.map("map","gladsaxe");
		var store = new mygeocloud_ol.geoJsonStore("gladsaxe",{styleMap: styleMap});
		var b = cloud.addTileLayers(Tilelayers);
		//cloud.map.setBaseLayer(b[0]);
	cloud.addGeoJsonStore(store);
		//store.selectFeatureControl.activate();
		store.sql = sql;
		store.load();
		store.onLoad = function(){
			cloud.zoomToExtentOfgeoJsonStore(store); 
		};
	cloud.addControl(
		//definere at objektet kan vælges
		new OpenLayers.Control.SelectFeature(
			store.layer, {
				hover: true,
				highlightOnly: true,
				clickout: true,
				renderIntent: "temporary",									
					//definere info boks
			}
		)
	);
});

