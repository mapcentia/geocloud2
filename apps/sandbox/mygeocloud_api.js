var mygeocloud;

mygeocloud = (function() {
	var geoJsonStore = function(db,q) {
			var parentThis = this;
			this.layer = new OpenLayers.Layer.Vector("Hi");
			this.onLoad = function(){};
			this.geoJSON = {};
			this.featureStore = null;
			this.load = function(){
				$.ajax({
			        dataType: 'jsonp',
			        data: 'q='+q,
			        jsonp: 'jsonp_callback',
			        url: 'http://127.0.01/api/v1/sql/' + db,
			        success: function (response) {
						parentThis.geoJSON = response;
						//console.log(response);
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
	};
	var map = function(el,db) {
		this.db = db;
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
								+ 'public.ways&extent='
								+ boundsStr + '&width='
								+ mapSize.w + '&height='
								+ mapSize.h
								,
					        jsonp: 'jsonp_callback',
					        url: 'http://test.mygeocloud.com/apps/viewer/servers/query/' + db,
					        success: jsonp_callback
					        });
					}
				});
		this.map = new OpenLayers.Map(el, {
			controls : [ new OpenLayers.Control.Navigation(),
					new OpenLayers.Control.PanZoomBar(),
					new OpenLayers.Control.LayerSwitcher() ],
			'numZoomLevels' : 20,
			'projection' : new OpenLayers.Projection("EPSG:900913"),
			'maxResolution' : 156543.0339,
			'units' : "m"
		});
		var _map = this.map;
		var click = new this.clickController();
		this.map.addControl(click);
		click.activate();
		var vectors = new OpenLayers.Layer.Vector("Markering");
        this.map.addLayers([vectors]);
		this.addBaseLayer = function() {
			this.map.addLayer(new OpenLayers.Layer.Google("Google Hybrid", {
				type : G_HYBRID_MAP,
				sphericalMercator : true
			}));
		};
		this.addTileLayer = function(schema, layers) {
			for(var i=0; i < layers.length; i++) {
				this.map.addLayer(this.createTileLayer(schema, layers[i]));
			}
		};
		this.addGeoJsonStore = function(store) {
				this.map.addLayers([store.layer]);
		};
		this.createTileLayer = function(schema, layer) {
			var l = new OpenLayers.Layer.WMS("Test",
					"http://test.mygeocloud.com/wms/" + this.db + "/" + schema
							+ "/tilecache/?", {
						layers : schema + "." + layer,
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
		this.map.maxExtent = new OpenLayers.Bounds(-20037508, -20037508,
				20037508, 20037508);
		var jsonp_callback = function(response) {
			// alert('');
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
    	};		
	};
	var deserialize = function (element) {
		// console.log(element);
        var type = "wkt";
		var format = new OpenLayers.Format.WKT;
        var features = format.read(element);
        return features;
    };
    var grid = function(store){
		this.grid = new Ext.grid.GridPanel( {
			id : "gridpanel",
			viewConfig : {
				forceFit : true
			},
			store : store.featureStore, // layer
			sm : new GeoExt.grid.FeatureSelectionModel( {
				singleSelect : false
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
    };
	var gridPanel = function(el,grid) {
		var panel = new Ext.Panel( {
			renderTo : el,
			width : 600,
			height : 400,
			items : [ {
				region : "center",
				title: "Attribut table",
				split : true,
				frame : false,
				layout : 'fit',
				collapsible: false,
				collapsed: false,
				height : 300,
				items:[grid]
			} ]
		});

	};
	
	
	return {
		geoJsonStore : geoJsonStore,
		map : map,
		gridPanel : gridPanel,
		grid : grid
	};
})();