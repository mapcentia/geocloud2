$(window).load(function(){
	cloud = new mygeocloud_ol.map("map","gladsaxe");
		var store = new mygeocloud_ol.geoJsonStore("gladsaxe",{/*styleMap: styleMap*/});
		//var b = cloud.addTileLayers(["public.kms"],{isBaseLayer:true,name:"KMS"});
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
					eventListeners: {
							beforefeaturehighlighted: function(e) {},
							featurehighlighted: function(e) {
								feature = e.feature;
								// Start med at fjerne alle popups
								while(cloud.map.popups.length ) {
									cloud.map.removePopup(cloud.map.popups[0]);
								}
								popup = new OpenLayers.Popup.FramedCloud("chicken", 
									feature.geometry.getBounds().getCenterLonLat(),
									//coord_position,
									null,
									"<div style='font-size:.8em'>" + feature.attributes[infoboks] +"<br>Klik for mere info</div>",
									null, false);
								feature.popup = popup;
								cloud.map.addPopup(popup);
								//$("#label").show();
								//$("#label").html("<div style='font-size:.8em'>" + feature.attributes.plannr +"<br>Klik for at se ramme</div>");
							},
							featureunhighlighted:  function (e) {
								feature = e.feature;
								cloud.map.removePopup(feature.popup);
								feature.popup.destroy();
								feature.popup = null;
								//$("#label").hide();
							}										
						}
			}
		)
	);
	cloud.addControl(
		//definere at der linkes videre
		new OpenLayers.Control.SelectFeature(
			store.layer, {
				hover: false,
				renderIntent: "temporary",
				onSelect: function(feature) {
					//cloud.map.removePopup(feature.popup);
					//feature.popup.destroy();
					//feature.popup = null;
					//window.open(feature.attributes.se_hjemmes);
					window.location = feature.attributes[linkurl];
					//Deselect feature med det samme
					this.unselect(feature);
									
				},
			onUnselect : function (feature) {}  
			}
		)
	);
});

