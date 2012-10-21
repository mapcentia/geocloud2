Ext.BLANK_IMAGE_URL = "/js/ext/resources/images/default/s.gif";
var getvars = getUrlVars();
var layer;
var modifyControl;
var grid;
var store;
var map;
var wfsTools;
var viewport;
var drawControl;
var gridPanel;
var modifyControl;
var tree;
var viewerSettings;
// We need to use jQuery load function to make sure that document.namespaces are ready. Only IE
function startWfsEdition(layerName) {
	
	var fieldsForStore;
	var columnsForGrid;
	var type;
	var multi;
	var handlerType;
	var loadMessage = Ext.MessageBox;
	var editable = true;
	var sm;
	// allow testing of specific renderers via "?renderer=Canvas", etc
	/*
		var renderer = OpenLayers.Util.getParameters(window.location.href).renderer;

		renderer = (renderer) ? [ renderer ]
				: OpenLayers.Layer.Vector.prototype.renderers;
	*/
	
	try {
		layer.removeAllFeatures();
		map.removeLayer(layer);
	} catch (e) {
		// TODO: handle exception
	}
	var south = viewport.getComponent(1);
	south.remove(grid);
		
		$.ajax( {
			url : '/controller/tables/' + screenName + '/getcolumns/'
					+ layerName,
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
			for (var i in columnsForGrid) {
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
				featureType : layerName,
				featureNS : "http://twitter/" + screenName,
				srsName : "EPSG:900913",
				geometryName : getvars['gf'],// must be dynamamic
				// Only load features in map extent
				defaultFilter : new OpenLayers.Filter.Spatial({type: OpenLayers.Filter.Spatial.BBOX, value: map.getExtent() })
			}),
			styleMap : styleMap
		});
		
		map.addLayers([layer]);
		layer.events.register("loadend", layer, function() {
			//map.zoomToExtent(layer.getDataExtent());

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
		if (type == "Point") {
			handlerType = OpenLayers.Handler.Point;
		}
		if (type == "Polygon") {
			handlerType = OpenLayers.Handler.Polygon;
		}
		if (type == "Path") {
			handlerType = OpenLayers.Handler.Path;
		}
		drawControl = new OpenLayers.Control.DrawFeature(layer,
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
			wfsTools[0].control=drawControl; // We set the control to the first button in wfsTools
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
		
		attributeForm.init(layerName);
		
		
		south.add(grid);
		gridPanel = Ext.getCmp("gridpanel");
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
		
		

		
	};
$(window).load(function() {
	var cloud = new mygeocloud_ol.map(null,screenName);
	map = cloud.map;
	cloud.click.activate();
	var LayerNodeUI = Ext.extend(GeoExt.tree.LayerNodeUI, new GeoExt.tree.TreeNodeUIEventMixin());
	var treeConfig = [{
		id: "baselayers",
        nodeType: "gx_baselayercontainer"
    }];
	var layers={};
	$.ajax({
        url: '/controller/tables/' + screenName + '/getrecords/settings.geometry_columns_view',
        async: false,
        dataType: 'json',
        type: 'GET',
        success: function (data, textStatus, http) {
			var groups=[];
            if (http.readyState == 4) {
                if (http.status == 200) {	
                    var response = eval('(' + http.responseText + ')');
					//console.log(response);
					for ( var i = 0; i < response.data.length; ++i) {
						if(response.data[i].layergroup){
							groups[i] = response.data[i].layergroup;
						}
						else{
							//groups[i] = "Default group";
						}
					}
					
					var arr = array_unique(groups);

				
							for ( var u = 0; u < response.data.length; ++u) {
								//console.log(response.data[u].baselayer);
					
								if (response.data[u].baselayer) {
									var isBaseLayer = true;
									}
								else {
									var isBaseLayer = false;
								}
								layers[[response.data[u].f_table_schema + "." + response.data[u].f_table_name]]=cloud.addTileLayers([response.data[u].f_table_schema + "." + response.data[u].f_table_name],
										{
											singleTile : false,
											isBaseLayer : isBaseLayer,
											visibility : false,
											wrapDateLine : false,
											tileCached: false,
											displayInLayerSwitcher: true,
											name: response.data[u].f_table_name
										}
									);
								
							}
				
						
					
					
					

                    for (var i = 0; i < arr.length; ++i) {	
						var l = [];
							for ( var u = 0; u < response.data.length; ++u) {
								//console.log(response.data[u].baselayer);
								if (response.data[u].layergroup==arr[i]) {
									l.push(
										{
	                                     text:  response.data[u].f_table_name,
	                                     id:  response.data[u].f_table_schema + "." + response.data[u].f_table_name,
	                                     leaf: true,
	                                     checked: false
	                                 }
									);
								}
						
							}
						treeConfig.push(
							{
							   //nodeType: "gx_layer",
								text: arr[i],
								isLeaf: false,
								//id: arr[i],
								expanded: true,
								children: l
							}
						);
						
					}
				}
			}
    }	});
	//console.log(layers);
	$.ajax({
        url: '/controller/settings_viewer/' + screenName + '/get',
        async: false,
        dataType: 'json',
        type: 'GET',
        success: function (data, textStatus, http) {
			var groups=[];
            if (http.readyState == 4) {
                if (http.status == 200) {	
                    var response = eval('(' + http.responseText + ')');
					viewerSettings = response;
				}
			}
    }	});
    var extentStore = new mygeocloud_ol.geoJsonStore(screenName);
    extentStore.sql = "SELECT ST_Envelope(ST_SetSRID(ST_Extent(transform(the_geom,900913)),900913)) as the_geom FROM " + viewerSettings.data.default_extent;
    extentStore.load();
    extentStore.onLoad = function(){
		// When the GeoJSON is loaded, zoom to its extent
		cloud.zoomToExtentOfgeoJsonStore(extentStore);
		//cloud.map.getLayersByName("test")[0].setVisibility(true);
		//console.log(cloud.map.getLayersByName("test")[0]);
		};
    treeConfig = new OpenLayers.Format.JSON().write(treeConfig, true);
    // create the tree with the configuration from above
   
    tree = new Ext.tree.TreePanel({
		id: "martin",
        border: true,
        region: "center",
        width: 200,
        split: true,
        //collapsible: true,
        //collapseMode: "mini",
        autoScroll: true,
        root: {
	        text: 'Ext JS',
	        children: Ext.decode(treeConfig),
	        id: 'source'

        },
        loader: new Ext.tree.TreeLoader({
            applyLoader: false,
            uiProviders: {
                "layernodeui": LayerNodeUI
            }
        }),
        listeners: {
			click: {
            fn:function(e) {
				if (e.lastChild===null && e.parentNode.id!=="baselayers") {
					Ext.getCmp('editlayerbutton').setDisabled(false);
				}
				else {
					Ext.getCmp('editlayerbutton').setDisabled(true);
				}
			}
        }
        },
        rootVisible: false,
        lines: false
    });
    tree.on("checkchange", function(node, checked){
    	if (node.lastChild===null && node.parentNode.id!=="baselayers") {
    	 if(checked){
             layers[node.id][0].setVisibility(true);
         }else{
        	 layers[node.id][0].setVisibility(false);
         }
    	}
     });
	wfsTools = [ new GeoExt.Action( {
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
	},{
		text : "Edit layer",
		id : "editlayerbutton",
		disabled: true,
		handler : function(thisBtn,event) {
			var node = tree.getSelectionModel().getSelectedNode();
			//console.log(node.id);
			var id = node.id.split(".");			
			startWfsEdition(id[1]);
		}
	},{
		text : "Embed",
		id : "Embed",
		disabled: false,
		handler : function(thisBtn,event) {
			
			//console.log(tree.getNodeById("TEST"));
			//console.log(tree); 
			tree.getNodeById("aad.test").getUI().toggleCheck(true);
			
			
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
	}];
	
	viewport = new Ext.Viewport( {
		layout : 'border',
		items : [
		{
			region : "center",
			id : "mappanel",
			title : "Map",
			xtype : "gx_mappanel",
			map : map,
			zoom : 5,
			split : true,
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
		},
		new Ext.Panel( {
			frame : false,
			region : "west",
			layout : 'border',
			width:300,
			items : [ tree ]
		})]
	});
	$('#martin').textWalk(function() {
		this.data = this.data.replace('vandforsyning.vandvarksgranse_polygon','Peter');
	});
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
function array_unique(ar){
if(ar.length && typeof ar!=='string'){
var sorter = {};
var out = [];
for(var i=0,j=ar.length;i<j;i++){
if(!sorter[ar[i]+typeof ar[i]]){
out.push(ar[i]);
sorter[ar[i]+typeof ar[i]]=true;
}
}
}
return out || ar;
}
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
jQuery.fn.textWalk = function( fn ) {
    this.contents().each( jwalk );
    
    function jwalk() {
        var nn = this.nodeName.toLowerCase();
        if( nn === '#text' ) {
            fn.call( this );
        } else if( this.nodeType === 1 && this.childNodes && this.childNodes[0] && nn !== 'script' && nn !== 'textarea' ) {
            $(this).contents().each( jwalk );
        }
    }
    return this;
};
