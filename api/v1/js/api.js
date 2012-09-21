var mygeocloud_host = "http://beta.mygeocloud.cowi.webhouse.dk";
document.write("<script src='https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js'><\/script>");
//document.write("<script src='" + mygeocloud_host + "/js/openlayers/OpenLayers.js'><\/script>");
document.write("<script src='" + mygeocloud_host + "/js/openlayers/OpenLayers.js'><\/script>");
document.write("<script src='" + mygeocloud_host + "/js/ext/adapter/ext/ext-base.js'><\/script>");
document.write("<script src='" + mygeocloud_host + "/js/ext/ext-all.js'><\/script>");
document.write("<script src='" + mygeocloud_host + "/js/GeoExt/script/GeoExt.js'><\/script>");
//document.write("<link rel='stylesheet' type='text/css' href='" + mygeocloud_host + "/js/openlayers/theme/default/style.mobile.css'\/>");
//document.write("<link rel='stylesheet' type='text/css' href='" + mygeocloud_host + "/js/ext/resources/css/ext-all.css'\/>");

var mygeocloud_ol;
mygeocloud_ol = (function() {
	var host = mygeocloud_host;
	var parentThis = this;
	var geoJsonStore = function(db,config) {
			var parentThis = this;
			var defaults = {
				styleMap: new OpenLayers.StyleMap({}),
				projection: "900913"
			};
			if(config) {
				for(prop in config){
					defaults[prop] = config[prop];
				}
			}
			this.layer = new OpenLayers.Layer.Vector("Vector",{
					styleMap: defaults.styleMap
				}
			);
			this.pointControl = new OpenLayers.Control.DrawFeature(this.layer,OpenLayers.Handler.Point);
			this.lineControl = new OpenLayers.Control.DrawFeature(this.layer,OpenLayers.Handler.Path);
			this.polygonControl = new OpenLayers.Control.DrawFeature(this.layer,OpenLayers.Handler.Polygon);
			this.selectFeatureControl = new OpenLayers.Control.SelectFeature(this.layer,{
				//hover: true,
				multiple: false,
				highlightOnly: true,
				renderIntent: "temporary"
				//onSelect: function() {alert('')} 
			});
			this.modifyControl = new OpenLayers.Control.ModifyFeature(this.layer, {
			
			});
			this.onLoad = function(){};
			this.geoJSON = {};
			this.featureStore = null;
			this.sql;
			this.load = function(){
				$.ajax({
			        dataType: 'jsonp',
			        data: 'q=' + this.sql + '&srs=' + defaults.projection,
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
	var map = function(el,db,config) {
		var defaults = {
			numZoomLevels : 20,
			projection : "EPSG:900913"
		};
		if(config) {
			for(prop in config){
				defaults[prop] = config[prop];
			}
		}
		var parentMap = this;
		this.layerStr;
		this.db = db;
		this.geoLocation = {x:1,y:2};
		this.baseOSM;
	    this.baseAerial;
		this.zoomToExtent = function() {
			this.map.zoomToExtent(this.map.maxExtent);
		};
		this.zoomToExtentOfgeoJsonStore = function(store) {
			this.map.zoomToExtent(store.layer.getDataExtent());
		};
		this.getVisibleLayers = function() {
			var layerArr = [];
			//console.log(this.map.layers);
			for(var i=0; i < this.map.layers.length; i++) {
				if(this.map.layers[i].isBaseLayer===false && this.map.layers[i].visibility===true && this.map.layers[i].CLASS_NAME==="OpenLayers.Layer.WMS") {
					layerArr.push(this.map.layers[i].params.LAYERS);
					//console.log(this.map.layers[i]);
					
				}
			}
			//console.log(layerArr);
			return layerArr.join(";");
		}
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
						//console.log(this.map.layers);
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
								+ parentMap.getVisibleLayers() + '&extent='
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
			//theme: null,
			controls : [ //new OpenLayers.Control.Navigation(),
					//new OpenLayers.Control.PanZoomBar(),
					//new OpenLayers.Control.LayerSwitcher()
		            new OpenLayers.Control.Zoom(),
		            //new OpenLayers.Control.PanZoom(),
					new OpenLayers.Control.TouchNavigation({
		                dragPanOptions: {
		                    enableKinetic: true
		                }
		            }) 
					 ],
			numZoomLevels : defaults.numZoomLevels,
			projection : defaults.projection,
			maxResolution : defaults.maxResolution,
			minResolution : defaults.minResolution,
			maxExtent : defaults.maxExtent
			//units : "m"
			//maxExtent : new OpenLayers.Bounds(-20037508.34, -20037508.34, 20037508.34, 20037508.34)
		});
		
		var _map = this.map;
		this.click = new this.clickController();
		this.map.addControl(this.click);
		var vectors = new OpenLayers.Layer.Vector("Mark",{
			displayInLayerSwitcher: false
		});
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
	       
	        this.baseOSM = new OpenLayers.Layer.OSM("MapQuest-OSM Tiles", arrayOSM);
			this.baseOSM.wrapDateLine = false;
	        this.baseAerial = new OpenLayers.Layer.OSM("MapQuest Open Aerial Tiles", arrayAerial);
			this.baseAerial.wrapDateLine = false;
			
			this.map.addLayer(this.baseOSM); 
			//this.map.addLayer(this.baseAerial);
            var osm = new OpenLayers.Layer.OSM();
			//this.map.addLayer(osm);

			
			try {
				this.baseGNORMAL = new OpenLayers.Layer.Google("Google Streets", {
					type : G_NORMAL_MAP,
					sphericalMercator : true
				});
				this.baseGNORMAL.wrapDateLine = false;
				this.baseGHYBRID = new OpenLayers.Layer.Google("Google Hybrid", {
					type : G_HYBRID_MAP,
					sphericalMercator : true
				});
				this.baseGHYBRID.wrapDateLine = false;
				this.map.addLayer(this.baseGNORMAL);
				this.map.addLayer(this.baseGHYBRID);
			}
			catch(e) {};
		};
		this.addTileLayers = function(layers,config) {
			var defaults = {
				singleTile : false,
				opacity : 1,
				isBaseLayer : false,
				visibility : true,
				wrapDateLine : false,
				tileCached: false,
				displayInLayerSwitcher: true,
				name: null
			};
			if(config) {
				for(prop in config){
					defaults[prop] = config[prop];
				}
			};
			var layersArr=[];
			for(var i=0; i < layers.length; i++) {
				var l = this.createTileLayer(layers[i],defaults)
				this.map.addLayer(l);
				layersArr.push(l);
			}
			return layersArr;
		};
		this.createTileLayer = function(layer,defaults) {
			var parts = [];
			parts = layer.split(".");
			var l = new OpenLayers.Layer.WMS(defaults.name,
					host + "/wms/" + this.db + "/" + parts[0]
							+ "/?", {
						layers : layer,
						transparent : true
					}, defaults
				);
			l.id = layer;
			return l;
		};
		this.addTileLayerGroup = function(layers,config) {
			var defaults = {
				singleTile : false,
				opacity : 1,
				isBaseLayer : false,
				visibility : true,
				wrapDateLine : false,
				name: null,
				schema: null
			};
			if(config) {
				for(prop in config){
					defaults[prop] = config[prop];
				}
			};
			this.map.addLayer(this.createTileLayerGroup(layers,defaults)); 
		};
		this.createTileLayerGroup = function(layers,defaults) {
			var l = new OpenLayers.Layer.WMS(defaults.name,
					host + "/wms/" + this.db + "/" + defaults.schema
							+ "/?", {
						layers : layers,
						transparent : true
					}, defaults
				);
			return l;
		};
		this.removeTileLayerByName = function(name) {
			var arr = this.map.getLayersByName(name);
			this.map.removeLayer(arr[0]); 
		};
		this.addGeoJsonStore = function(store) {
			this.map.addLayers([store.layer]);
			this.map.addControl(store.pointControl);
			this.map.addControl(store.lineControl);
			this.map.addControl(store.polygonControl);
			this.map.addControl(store.selectFeatureControl); 
			this.map.addControl(store.modifyControl);
		};
		this.addControl = function(control) {
			this.map.addControl(control);
			control.activate();
		}; 
		this.removeGeoJsonStore = function(store) {
			this.map.removeLayer(store.layer);//??????????????
		};
		
		
		this.addBaseLayer();
		
		this.getCenter = function() {
			var point = this.map.center;
			return  {
				x : point.lon,
				y : point.lat
			}
		}
		
		this.getExtent = function() {
			var mapBounds = this.map.getExtent();
			return mapBounds.toArray();
		}
		
		
		// Geolocation stuff starts here
		var geolocation_layer = new OpenLayers.Layer.Vector('geolocation_layer',{
			displayInLayerSwitcher: false
		});
		var firstGeolocation = true;
		var style = {
			fillColor: '#000',
			fillOpacity: 0.1,
			strokeWidth: 0
		};
		this.map.addLayers([geolocation_layer]);
		var locateCallBack = function () {}; // A function that is fired when map is zoomed to geolocation
		this.locate = function(callback) {
		    if (callback==null) {
				callback = function () {};
			}
			locateCallBack = callback;
			geolocation_layer.removeAllFeatures();
			geolocate.deactivate();
			//$('track').checked = false;
			geolocate.watch = false;
			firstGeolocation = true;
			geolocate.activate();
		};
		var geolocate = new OpenLayers.Control.Geolocate({
			bind: false,
			geolocationOptions: {
				enableHighAccuracy: false,
				maximumAge: 0,
				timeout: 7000
			}
		});
		this.map.addControl(geolocate);
		geolocate.events.register("locationupdated",geolocate,function(e) {
			geolocation_layer.removeAllFeatures();
			var circle = new OpenLayers.Feature.Vector(
				OpenLayers.Geometry.Polygon.createRegularPolygon(
					new OpenLayers.Geometry.Point(e.point.x, e.point.y),
					e.position.coords.accuracy/2,
					40,
					0
				),
				{},
				style
			);
			geolocation_layer.addFeatures([
				new OpenLayers.Feature.Vector(
					e.point,
					{},
					{
						graphicName: 'cross',
						strokeColor: '#f00',
						strokeWidth: 1,
						fillOpacity: 0,
						pointRadius: 10
					}
				),
				circle
			]);
			if (firstGeolocation) {
				this.map.zoomToExtent(geolocation_layer.getDataExtent());
				pulsate(circle);
				firstGeolocation = false;
				this.bind = true;

				parentMap.geoLocation = {
					x : e.point.x,
					y : e.point.y
				};
				locateCallBack();
			}
		});
		geolocate.events.register("locationfailed",this,function() {
			alert("No location");
		});
		var pulsate = function(feature) {
			var point = feature.geometry.getCentroid(),
				bounds = feature.geometry.getBounds(),
				radius = Math.abs((bounds.right - bounds.left)/2),
				count = 0,
				grow = 'up';
				
			var resize = function(){
				if (count>16) {
					clearInterval(window.resizeInterval);
				}
				var interval = radius * 0.03;
				var ratio = interval/radius;
				switch(count) {
					case 4:
					case 12:
						grow = 'down'; break;
					case 8:
						grow = 'up'; break;
				}
				if (grow!=='up') {
					ratio = - Math.abs(ratio);
				}
				feature.geometry.resize(1+ratio, point);
				geolocation_layer.drawFeature(feature);
				count++;
			};
			window.resizeInterval = window.setInterval(resize, 50, point, radius);
		};		
	};
	var deserialize = function (element) {
		// console.log(element);
        var type = "wkt";
		var format = new OpenLayers.Format.WKT;
        var features = format.read(element);
        return features;
    };
	
	
    var grid = function(el,store,config){
		var defaults = {
			height: 300,
			selectControl: {
				onSelect: function (feature) {},
				onUnselect: function() {}
			},
			columns: store.geoJSON.forGrid
		};
		if(config) {
			for(prop in config){
				defaults[prop] = config[prop];
			}
		}
		this.grid = new Ext.grid.GridPanel( {
			id : "gridpanel",	
			viewConfig : {
				forceFit : true
			},
			store : store.featureStore, // layer
			sm : new GeoExt.grid.FeatureSelectionModel( { // Only when there is a map
				singleSelect : false,
				selectControl : defaults.selectControl
			}),
			cm : new Ext.grid.ColumnModel( {
				defaults : {
					sortable : true,
					editor : {
						xtype : "textfield"
					}
				},
				columns : defaults.columns
			}), 
			listeners: defaults.listeners
		});
		this.panel = new Ext.Panel( {
			renderTo : el,
			split : true,
			frame : false,
			border : false,
			layout : 'fit',
			collapsible: false,
			collapsed: false,
			height : defaults.height,
			items : [this.grid]
		});
    };
	return {
		geoJsonStore : geoJsonStore,
		map : map,
		grid : grid
	};
})();
