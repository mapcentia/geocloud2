 
var tree, mapPanel, map;

Ext.onReady(function() {
    var root = new Ext.tree.AsyncTreeNode({
        text: 'Your geo clouds',
        loader: new GeoExt.tree.WMSCapabilitiesLoader({
            url: '/wms/' + screenName + '/',
            layerOptions: {buffer: 0, singleTile: true, ratio: 1},
            layerParams: {'TRANSPARENT': 'TRUE'},
            // customize the createNode method to add a checkbox to nodes
            createNode: function(attr) {
                attr.checked = attr.leaf ? false : undefined;
                return GeoExt.tree.WMSCapabilitiesLoader.prototype.createNode.apply(this, [attr]);
            }
        })
    });

    tree = new Ext.tree.TreePanel({
        root: root,
   		title: 'Layers',
        width: 250,
        listeners: {
            // Add layers to the map when ckecked, remove when unchecked.
            // Note that this does not take care of maintaining the layer
            // order on the map.
            'checkchange': function(node, checked) { 
                if (checked === true) {
                    mapPanel.map.addLayer(node.attributes.layer); 
                } else {
                    mapPanel.map.removeLayer(node.attributes.layer);
                }
            }
        }
    });

	map = new OpenLayers.Map(null, {
		controls: [new OpenLayers.Control.Navigation(),new OpenLayers.Control.PanZoomBar(), new OpenLayers.Control.LayerSwitcher(),
		new OpenLayers.Control.TouchNavigation({
                dragPanOptions: {
                    interval: 100
                }})],
		'numZoomLevels':20,
		'projection': new OpenLayers.Projection("EPSG:900913"),
		'maxResolution': 156543.0339,
		'units': "m"
	});
	var extent = new OpenLayers.Bounds(-20037508, -20037508, 20037508, 20037508);
	map.maxExtent = extent;
	
	var base = [];
	base.push(new OpenLayers.Layer.Google("Google Hybrid", {
		type: G_HYBRID_MAP,
		sphericalMercator: true
	}));
	base.push(new OpenLayers.Layer.Google("Google Satellite", {
		type: G_SATELLITE_MAP,
		sphericalMercator: true
	}));
	base.push(new OpenLayers.Layer.Google("Google Terrain", {
		type: G_PHYSICAL_MAP,
		sphericalMercator: true
	}));
	base.push(new OpenLayers.Layer.Google("Google Normal", {
		type: G_NORMAL_MAP,
		sphericalMercator: true
	}));
	

	map.addLayers(base);
	var accordion = new Ext.Panel({
				title: 'Other stuff',
				layout: 'accordion',
				region: 'center',
				region: 'west',
				width: 300,
				frame: false,
				plain:true,
				closable:false,
				border:true,
				layoutConfig:{animate:true},
				items: [
					tree,{
                contentEl: "desc",
                region: "east",
                bodyStyle: {"padding": "5px"},
                collapsible: true,
                collapseMode: "mini",
                split: true,
                width: 200,
                title: "Description"
            }
				]
			})
    new Ext.Viewport({
        layout: "fit",
        hideBorders: true,
        items: {
            layout: "border",
            deferredRender: false,
            items: [
				{
				region: "center",
				id: "mappanel",
				title: "Map",
				xtype: "gx_mappanel",
				map: map,
				//zoom: 5
				},accordion]
        }
    });
	mapPanel = Ext.getCmp("mappanel");
});
