Ext.BLANK_IMAGE_URL = "/js/ext/resources/images/default/s.gif";
var getvars = getUrlVars();
var layer;
var modifyControl;
var grid;
var store;
var map;
// We need to use jQuery load function to make sure that document.namespaces are ready. Only IE
$(window).load(function() {
	
	var fieldsForStore;
	var columnsForGrid;
	var type;
	var multi;
	var handlerType;
	var base = [];
	var loadMessage = Ext.MessageBox;
	var editable = true;
	var sm;
	// allow testing of specific renderers via "?renderer=Canvas", etc
	/*
		var renderer = OpenLayers.Util.getParameters(window.location.href).renderer;

		renderer = (renderer) ? [ renderer ]
				: OpenLayers.Layer.Vector.prototype.renderers;
	*/
		OpenLayers.Layer.Vector.prototype.renderers = ["SVG2","Canvas","VML"];
		$.ajax( {
			url : '/controller/tables/' + screenName + '/getcolumns/'
					+ getvars['layer'],
			async : false,
			dataType : 'json',
			type : 'GET',
			success : function(data, textStatus, http) {
				if (http.readyState == 4) {
					if (http.status == 200) {
						var response = eval('(' + http.responseText + ')'); // JSON
			fieldsForStore = response.forStore;
			columnsForGrid = response.forGrid;
			type = response.type;
			multi = response.multi;
			// We add an editor to the fields
			for ( var i in columnsForGrid) {
				columnsForGrid[i].editable = editable;
				// alert(columnsForGrid[i].header+"
			// "+columnsForGrid[i].typeObj.type);
			if (columnsForGrid[i].typeObj !== undefined) {
				if (columnsForGrid[i].typeObj.type == "int") {
					columnsForGrid[i].editor = new Ext.form.NumberField( {
						decimalPrecision : 0,
						decimalSeparator : '¤'// Some strange char nobody is
												// using
					});
				} else if (columnsForGrid[i].typeObj.type == "decimal") {
					columnsForGrid[i].editor = new Ext.form.NumberField( {
						decimalPrecision : columnsForGrid[i].typeObj.scale,
						decimalSeparator : '.'
					// maxLength: columnsForGrid[i].type.precision
							});
				} else if (columnsForGrid[i].typeObj.type == "string") {
					columnsForGrid[i].editor = new Ext.form.TextField();
				}
			}
		}
		;
	}
}

}
		});
		map = new OpenLayers.Map("mapel", {
			controls : [ new OpenLayers.Control.Navigation(),
					new OpenLayers.Control.PanZoomBar(),
					new OpenLayers.Control.LayerSwitcher() /*,
					new OpenLayers.Control.TouchNavigation( {
						dragPanOptions : {
							interval : 100
						}
					})*/ ],
			'numZoomLevels' : 20,
			'projection' : new OpenLayers.Projection("EPSG:900913"),
			'maxResolution' : 156543.0339,
			'units' : "m"
		});

		var saveStrategy = new OpenLayers.Strategy.Save(
				{
					onCommit : function(response) {
						if (response.success()) {
							saveStrategy.layer.refresh();
							format = new OpenLayers.Format.XML();
							var doc = format.read(response.priv.responseText);
							try {
								var inserted = doc
										.getElementsByTagName('wfs:totalInserted')[0].firstChild.data;
							} catch (e) {
							}
							;
							try {
								var deleted = doc
										.getElementsByTagName('wfs:totalDeleted')[0].firstChild.data;
							} catch (e) {
							}
							;
							try {
								var updated = doc
										.getElementsByTagName('wfs:totalUpdated')[0].firstChild.data;
							} catch (e) {
							}
							;
							try {
								var updated = doc
										.getElementsByTagName('wfs:Message')[0].firstChild.data;
							} catch (e) {
							}
							;

							// For webkit
							try {
								var inserted = doc
										.getElementsByTagName('totalInserted')[0].firstChild.data;
							} catch (e) {
							}
							;
							try {
								var deleted = doc
										.getElementsByTagName('totalDeleted')[0].firstChild.data;
							} catch (e) {
							}
							;
							try {
								var updated = doc
										.getElementsByTagName('totalUpdated')[0].firstChild.data;
							} catch (e) {
							}
							;
							try {
								var updated = doc
										.getElementsByTagName('Message')[0].firstChild.data;
							} catch (e) {
							}
							;

							var message = "";
							if (inserted) {
								message += "<p>Inserted: " + inserted + "</p>";
							}
							if (updated) {
								message += "<p>Updated: " + updated + "</p>";
							}
							if (deleted) {
								message += "<p>Deleted: " + deleted + "</p>";
							}
							// message+="<textarea rows='5'
							// cols='31'>"+error+"</textarea>"

							// Ext.fly('info').dom.value = Ext.MessageBox.INFO;
							Ext.MessageBox.show( {
								title : 'Success!',
								msg : message,
								buttons : Ext.MessageBox.OK,
								width : 200,
								icon : Ext.MessageBox.INFO
							});
						} else {
							format = new OpenLayers.Format.XML();
							var doc = format.read(response.priv.responseText);
							try {
								var error = doc
										.getElementsByTagName('ServiceException')[0].firstChild.data;
							} catch (e) {
							}
							;
							try {
								var error = doc
										.getElementsByTagName('wfs:ServiceException')[0].firstChild.data;
							} catch (e) {
							}
							;
							message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows='5' cols='31'>"
									+ error + "</textarea>";
							Ext.MessageBox.show( {
								title : 'Failure',
								msg : message,
								buttons : Ext.MessageBox.OK,
								width : 400,
								height : 300,
								icon : Ext.MessageBox.ERROR
							});
						}

					}
				});
		var styleMap = new OpenLayers.StyleMap( {
			temporary : OpenLayers.Util.applyDefaults( {
				pointRadius : 15
			}, OpenLayers.Feature.Vector.style.temporary)
		});
		layer = new OpenLayers.Layer.Vector("vector", {
			//renderers : renderer,
			strategies : [ new OpenLayers.Strategy.Fixed(), saveStrategy ],
			protocol : new OpenLayers.Protocol.WFS.v1_0_0( {
				url : "/wfs/" + screenName + "/" + schema + "/900913?",
				version : "1.0.0",
				featureType : getvars['layer'],
				featureNS : "http://twitter/" + screenName,
				srsName : "EPSG:900913",
				geometryName : getvars['gf']
			// must be dynamamic
					}),
			styleMap : styleMap
		});
		layer.events.register("loadend", layer, function() {
			map.zoomToExtent(layer.getDataExtent());

			// loadMessage.hide();
				// alert("");
			});
		layer.events.register("loadstart", layer, function() {

			/*
			 * loadMessage.show({ msg: 'Getting your data, please wait...',
			 * progressText: 'Loading...', width:300, wait:true, waitConfig:
			 * {interval:200} });
			 */

		});

		var extent = new OpenLayers.Bounds(-20037508, -20037508, 20037508,
				20037508);
		map.maxExtent = extent;

		base.push(new OpenLayers.Layer.Google("Google Hybrid", {
			type : G_HYBRID_MAP,
			sphericalMercator : true
		}));
		base.push(new OpenLayers.Layer.Google("Google Satellite", {
			type : G_SATELLITE_MAP,
			sphericalMercator : true
		}));
		base.push(new OpenLayers.Layer.Google("Google Terrain", {
			type : G_PHYSICAL_MAP,
			sphericalMercator : true
		}));
		base.push(new OpenLayers.Layer.Google("Google Normal", {
			type : G_NORMAL_MAP,
			sphericalMercator : true
		}));
		base.push(new OpenLayers.Layer.TMS(
						"OpenStreetMap (Mapnik)",
						"http://tile.openstreetmap.org/",
						{
							type : 'png',
							getURL : osm_getTileURL,
							displayOutsideMaxExtent : true,
							attribution : '<a href="http://www.openstreetmap.org/">OpenStreetMap</a>'
						})

				);

		map.addLayers(base);

		if (type == "Point") {
			handlerType = OpenLayers.Handler.Point;
		}
		if (type == "Polygon") {
			handlerType = OpenLayers.Handler.Polygon;
		}
		if (type == "Path") {
			handlerType = OpenLayers.Handler.Path;
		}
		var drawControl = new OpenLayers.Control.DrawFeature(layer,
				handlerType, {
					featureAdded : onInsert,
					handlerOptions : {
						multi : multi,
						handlerOptions : {
							holeModifier : "altKey"
						}
					}
				});

		if (editable) {
			map.addControl(drawControl);
			modifyControl = new OpenLayers.Control.ModifyFeature(layer, {
				vertexRenderIntent : 'temporary',
				displayClass : 'olControlModifyFeature'
			});
			map.addControl(modifyControl);
			modifyControl.activate();
			sm = new GeoExt.grid.FeatureSelectionModel( {
				selectControl : modifyControl.selectControl,
				singleSelect : true,
				listeners : {
					rowselect : function(sm, row, rec) {
						try {
							attributeForm.form.getForm().loadRecord(rec);
						} catch (e) {
						}
					}
				}
			});
		} else {
			sm = new GeoExt.grid.FeatureSelectionModel( {
				singleSelect : false
			});
		}

		var wfsTools = [ new GeoExt.Action( {
			control : drawControl,
			text : "Create",
			enableToggle : true
		}), {
			text : "Delete",
			handler : function() {
				gridPanel.getSelectionModel().each(function(rec) {
					var feature = rec.get("feature");
					modifyControl.unselectFeature(feature);
					gridPanel.store.remove(rec);
					if (feature.state !== OpenLayers.State.INSERT) {
						feature.state = OpenLayers.State.DELETE;
						layer.addFeatures( [ feature ]);
					}

				});
			}
		}, {
			text : "Save",
			handler : function() {
				// alert(layer.features.length);
			if (modifyControl.feature) {
				modifyControl.selectControl.unselectAll();
			}
			store.commitChanges();
			saveStrategy.save();
		}
		}, '->', {
			text : "Feature info",
			handler : function() {
				attributeForm.win.show();

			}
		}, '-', {
			text : "Feature filter",
			handler : function() {
				filter.win.show();

			}
		}  ];
		store = new GeoExt.data.FeatureStore( {
			proxy: new GeoExt.data.ProtocolProxy({
                protocol: layer.protocol
            }),
			fields : fieldsForStore,
			layer : layer,

			featureFilter : new OpenLayers.Filter( {
				evaluate : function(feature) {
					return feature.state !== OpenLayers.State.DELETE;
				}
			})
		});
		grid = new Ext.grid.EditorGridPanel( {
			id : "gridpanel",
			// height:100,
			region : "center",
			disabled : false,
			viewConfig : {
				forceFit : true
			},
			store : store,
			listeners : {
				afteredit : function(e) {
					var feature = e.record.get("feature");
					if (feature.state !== OpenLayers.State.INSERT) {
						feature.state = OpenLayers.State.UPDATE;
					}
				}
			},

			sm : sm,
			cm : new Ext.grid.ColumnModel( {
				defaults : {
					sortable : true,
					editor : {
						xtype : "textfield"
					}
				},
				columns : columnsForGrid
			})
		});
		if (!editable) {
			wfsTools = [];
		};
		attributeForm.init(getvars['layer']);
		var viewport = new Ext.Viewport( {
			layout : 'border',
			items : [ 
			{
				region : "center",
				id : "mappanel",
				title : "Map",
				xtype : "gx_mappanel",
				map : map,
				// extent:extent,
				zoom : 5,
				split : true,
				layers : [ layer ],
				tbar : wfsTools
			}, {
				region : "south",
				title: "Attribut table",
				split : true,
				frame : false,
				layout : 'fit',
				height : 300,
				collapsible: true,
				collapsed: false
			} ]
		});

		var mapPanel = Ext.getCmp("mappanel");
		// var north = viewport.getCompo
		var south = viewport.getComponent(1);

		south.add(grid);
		var gridPanel = Ext.getCmp("gridpanel");
		south.doLayout();

		
		attributeForm.win = new Ext.Window( {
			title : "Update feature information",
			modal : false,
			layout : 'fit',
			initCenter : false,
			border: false,
			x : 25,
			y : 100,
			width : 270,
			height : 300,
			closeAction : 'hide',
			plain : true,

			items : [ new Ext.Panel( {
				frame : false,
				layout : 'border',
				items : [ attributeForm.form ]
			}) ]
		});
		
		//filter.win.show();
		function osm_getTileURL(bounds) {
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
		}
	});
function getUrlVars() {
	var mapvars = {};
	var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi,
			function(m, key, value) {
				mapvars[key] = value;
			});
	return mapvars;
}
function onInsert() {

	var pos = grid.getStore().getCount() - 1;
	grid.selModel.selectRow(pos);

	attributeForm.win.show();
}
function test() {
	alert('test');
}