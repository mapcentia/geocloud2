var mygeocloud_host = "http://beta.mygeocloud.cowi.webhouse.dk";
document.write("<script src='https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js'><\/script>");
//document.write("<script src='" + mygeocloud_host + "/js/openlayers/OpenLayers.js'><\/script>");
document.write("<script src='http://openlayers.org/api/2.12-rc1/OpenLayers.js'><\/script>");
document.write("<script src='" + mygeocloud_host + "/js/ext/adapter/ext/ext-base.js'><\/script>");
document.write("<script src='" + mygeocloud_host + "/js/ext/ext-all.js'><\/script>");
document.write("<script src='" + mygeocloud_host + "/js/GeoExt/script/GeoExt.js'><\/script>");

var mygeocloud_ol;
mygeocloud_ol = (function() {
	var host = mygeocloud_host;
	this.layerStr = "";
	var parentThis = this;
	var geoJsonStore = function(db) {
			var parentThis = this;
			this.layer = new OpenLayers.Layer.Vector("Hi");
			this.onLoad = function(){};
			this.geoJSON = {};
			this.featureStore = null;
			this.sql;
			this.load = function(){
				$.ajax({
			        dataType: 'jsonp',
			        data: 'q=' + this.sql,
			        jsonp: 'jsonp_callback',
			        url: host + '/api/v1/sql/' + db,
			        success: function (response) {
						if (response.success==false) {
							alert(response.message); 
						}
						parentThis.geoJSON = response;
						var geojson_format = new OpenLayers.Format.GeoJSON();
						parentThis.layer.addFeatures(geojson_format.read(response));
						parentThis.featureStore = new GeoExt.data.FeatureStore( {
							fields : response.forStore,
							layer : parentThis.layer
						});
					},
				complete: function() {
						parentThis.onLoad();
		        } 
				});	
		};
		this.reset = function() {
			this.layer.destroyFeatures();
		};
	};
	var map = function(el,db) {
		this.db = db;
		this.zoomToExtent = function() {
			this.map.zoomToExtent(this.map.maxExtent);
		};
		this.zoomToExtentOfgeoJsonStore = function(store) {
			this.map.zoomToExtent(store.layer.getDataExtent());
		};
		this.clickController = OpenLayers.Class(
				OpenLayers.Control,
				{
					defaultHandlerOptions : {
						'single' : true,
						'double' : false,
						'pixelTolerance' : 0,
						'stopSingle' : false,
						'stopDouble' : false
					},
					initialize : function(options) {
						this.handlerOptions = OpenLayers.Util.extend(
								{}, this.defaultHandlerOptions);
						OpenLayers.Control.prototype.initialize.apply(
								this, arguments);
						this.handler = new OpenLayers.Handler.Click(
								this, {
									'click' : this.trigger
								}, this.handlerOptions);
					},
					trigger : function(e) {
						var mapBounds = this.map.getExtent();
						var boundsArr = mapBounds.toArray();
						var boundsStr = boundsArr.join(",");
						var coords = this.map.getLonLatFromViewPortPx(e.xy);
						try {
							popup.destroy();
						} catch (e) {
						}
						;
						popup = new OpenLayers.Popup.FramedCloud(
								"result",
								coords,
								null,
								"<div id='queryResult' style='z-index:1000;width:300px;height:100px;overflow:auto'>Wait..</div>",
								null, true);
						this.map.addPopup(popup);
						mapSize = this.map.getSize();
						$.ajax({
					        dataType: 'jsonp',
					        data: 'proj=900913&lon='
								+ coords.lon + '&lat='
								+ coords.lat + '&layers='
								+ parentThis.layerStr + '&extent='
								+ boundsStr + '&width='
								+ mapSize.w + '&height='
								+ mapSize.h
								,
					        jsonp: 'jsonp_callback',
					        url: host + '/apps/viewer/servers/query/' + db,
					        success: function(response) {
				    		if (response.html != false) {
				    			document
				    					.getElementById("queryResult").innerHTML = response.html;
				    			resultHtml = response.html; // Global
				    			// var
				    		} else {
				    			document
				    					.getElementById("queryResult").innerHTML = "Found nothing";
				    		}
				    		vectors.removeAllFeatures();
				    		_map.raiseLayer(vectors, 10);
				    		for ( var i = 0; i < response.renderGeometryArray.length; ++i) {
				    			vectors.addFeatures(deserialize(response.renderGeometryArray[i][0]));
				  
				    		}
				    	}
					        });
					}
				});
		this.map = new OpenLayers.Map(el, {
			controls : [ new OpenLayers.Control.Navigation(),
					//new OpenLayers.Control.PanZoomBar(),
					new OpenLayers.Control.TouchNavigation({
		                dragPanOptions: {
		                    enableKinetic: true
		                }
		            }),
		            new OpenLayers.Control.Zoom()/*
					new OpenLayers.Control.LayerSwitcher()*/ ],
			'numZoomLevels' : 20,
			'projection' : new OpenLayers.Projection("EPSG:900913"),
			'maxResolution' : 156543.0339,
			'units' : "m"
		});
		var _map = this.map;
		var click = new this.clickController();
		this.map.addControl(click);
		//click.activate();
		var vectors = new OpenLayers.Layer.Vector("Markering");
        this.map.addLayers([vectors]);
		this.addBaseLayer = function() {
			var arrayOSM = ["http://otile1.mqcdn.com/tiles/1.0.0/osm/${z}/${x}/${y}.jpg",
	                    "http://otile2.mqcdn.com/tiles/1.0.0/osm/${z}/${x}/${y}.jpg",
	                    "http://otile3.mqcdn.com/tiles/1.0.0/osm/${z}/${x}/${y}.jpg",
	                    "http://otile4.mqcdn.com/tiles/1.0.0/osm/${z}/${x}/${y}.jpg"];
	        var arrayAerial = ["http://oatile1.mqcdn.com/tiles/1.0.0/sat/${z}/${x}/${y}.jpg",
	                        "http://oatile2.mqcdn.com/tiles/1.0.0/sat/${z}/${x}/${y}.jpg",
	                        "http://oatile3.mqcdn.com/tiles/1.0.0/sat/${z}/${x}/${y}.jpg",
	                        "http://oatile4.mqcdn.com/tiles/1.0.0/sat/${z}/${x}/${y}.jpg"];
	       
	        var baseOSM = new OpenLayers.Layer.OSM("MapQuest-OSM Tiles", arrayOSM);
	        var baseAerial = new OpenLayers.Layer.OSM("MapQuest Open Aerial Tiles", arrayAerial);
	      
	        this.map.addLayer(baseOSM);
	        this.map.addLayer(baseAerial);
			/*
			this.map.addLayer(new OpenLayers.Layer.TMS(
					"OpenStreetMap (Mapnik)",
					"http://tile.openstreetmap.org/",
					{
						type : 'png',
						getURL : function osm_getTileURL(bounds) {
							var res = this.map.getResolution();
							var x = Math.round((bounds.left - this.maxExtent.left)
									/ (res * this.tileSize.w));
							var y = Math.round((this.maxExtent.top - bounds.top)
									/ (res * this.tileSize.h));
							var z = this.map.getZoom();
							var limit = Math.pow(2, z);

							if (y < 0 || y >= limit) {
								return OpenLayers.Util.getImagesLocation() + "404.png";
							} else {
								x = ((x % limit) + limit) % limit;
								return this.url + z + "/" + x + "/" + y + "." + this.type;
							}
						},
						displayOutsideMaxExtent : true,
						attribution : '<a href="http://www.openstreetmap.org/">OpenStreetMap</a>'
					})
			);
			*/
			/*
			this.map.addLayer(new OpenLayers.Layer.Google("Google Hybrid", {
				type : G_HYBRID_MAP,
				sphericalMercator : true
			}));
			*/
		};
		this.addTileLayers = function(layers) {
			var newLayerStr = layers.join(";");
			if (parentThis.layerStr.length>0) {
				layerStr = layerStr + ";" + newLayerStr;
			}
			else {
				parentThis.layerStr = newLayerStr;
			}
			for(var i=0; i < layers.length; i++) {
				this.map.addLayer(this.createTileLayer(layers[i]));
			}
		};
		this.removeTileLayerByName = function(name) {
			var arr = this.map.getLayersByName(name);
			this.map.removeLayer(arr[0]);
		};
		this.addGeoJsonStore = function(store) {
			this.map.addLayers([store.layer]);
			var control = new OpenLayers.Control.SelectFeature(
					store.layer,
					null
				);
			this.map.addControl(control);
			control.activate();
		};
		this.removeGeoJsonStore = function(store) {
			this.map.removeLayer(store.layer);//??????????????
		};
		this.createTileLayer = function(layer) {
			var parts = [];
			parts = layer.split(".");
			var l = new OpenLayers.Layer.WMS(layer,
					host + "/wms/" + this.db + "/" + parts[0]
							+ "//?", {
						layers : layer,
						transparent : true
					}, {
						singleTile : false,
						opacity : 1,
						isBaseLayer : false,
						visibility : true,
						wrapDateLine : false
					});
			return l;
		};
		this.map.maxExtent = new OpenLayers.Bounds(-20037508, -20037508, 20037508, 20037508);
		this.addBaseLayer();

		
				
	};
	var deserialize = function (element) {
		// console.log(element);
        var type = "wkt";
		var format = new OpenLayers.Format.WKT;
        var features = format.read(element);
        return features;
    };
    var grid = function(el,store,selectControl){
		this.grid = new Ext.grid.GridPanel( {
			id : "gridpanel",	
			viewConfig : {
				forceFit : true
			},
			store : store.featureStore, // layer
			sm : new GeoExt.grid.FeatureSelectionModel( { // Only when there is a map
				singleSelect : false,
				selectControl : selectControl
			}),
			cm : new Ext.grid.ColumnModel( {
				defaults : {
					sortable : true,
					editor : {
						xtype : "textfield"
					}
				},
				columns :store.geoJSON.forGrid
			})
		});
		this.panel = new Ext.Panel( {
			renderTo : el,
			split : true,
			frame : false,
			border : false,
			layout : 'fit',
			collapsible: false,
			collapsed: false,
			//height : 300,
			items : [this.grid]
		});
    };
	return {
		geoJsonStore : geoJsonStore,
		map : map,
		grid : grid
	};
})();