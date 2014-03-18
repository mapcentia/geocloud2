/*global Ext:false */
/*global $:false */
/*global OpenLayers:false */
/*global GeoExt:false */
/*global mygeocloud_ol:false */
/*global schema:false */
/*global screenName:false */
Ext.BLANK_IMAGE_URL = "/js/ext/resources/images/default/s.gif";
var App = new Ext.App({}), cloud, layer, grid, store, map, wfsTools, viewport, drawControl, gridPanel, modifyControl, tree, viewerSettings, loadTree, reLoadTree, layerBeingEditing, saveStrategy;
function startWfsEdition(layerName, geomField) {
    'use strict';
    var fieldsForStore, columnsForGrid, type, multi, handlerType, editable = true, sm, south = Ext.getCmp("attrtable");
    layerBeingEditing = layerName;
    try {
        drawControl.deactivate();
        layer.removeAllFeatures();
        map.removeLayer(layer);
    } catch (e) {
        //alert(e.message);
    }
    south.expand(true);
    south.remove(grid);
    $.ajax({
        url: '/controllers/table/columns/' + layerName,
        async: false,
        dataType: 'json',
        type: 'GET',
        success: function (data, textStatus, http) {
            if (http.readyState === 4) {
                if (http.status === 200) {
                    var response = data;
                    // JSON
                    fieldsForStore = response.forStore;
                    columnsForGrid = response.forGrid;
                    type = response.type;
                    multi = response.multi;
                    // We add an editor to the fields
                    for (var i in columnsForGrid) {
                        columnsForGrid[i].editable = editable;
                        if (columnsForGrid[i].typeObj !== undefined) {
                            if (columnsForGrid[i].typeObj.type === "int") {
                                columnsForGrid[i].editor = new Ext.form.NumberField({
                                    decimalPrecision: 0,
                                    decimalSeparator: '¤'// Some strange char nobody is using
                                });
                            } else if (columnsForGrid[i].typeObj.type === "decimal") {
                                columnsForGrid[i].editor = new Ext.form.NumberField({
                                    decimalPrecision: columnsForGrid[i].typeObj.scale,
                                    decimalSeparator: '.'
                                });
                            } else if (columnsForGrid[i].typeObj.type === "string") {
                                columnsForGrid[i].editor = new Ext.form.TextField();
                            } else if (columnsForGrid[i].typeObj.type === "text") {
                                columnsForGrid[i].editor = new Ext.form.TextArea();
                            }
                        }
                    }
                }
            }
        }
    });
    var styleMap = new OpenLayers.StyleMap({
        temporary: OpenLayers.Util.applyDefaults({
            pointRadius: 5
        }, OpenLayers.Feature.Vector.style.temporary)
    });
    layer = new OpenLayers.Layer.Vector("vector", {
        //renderers : renderer,
        strategies: [new OpenLayers.Strategy.Fixed(), saveStrategy],
        protocol: new OpenLayers.Protocol.WFS.v1_0_0({
            url: "/wfs/" + screenName + "/" + schema + "/900913?",
            version: "1.0.0",
            featureType: layerName,
            featureNS: "http://twitter/" + screenName,
            srsName: "EPSG:900913",
            geometryName: geomField, // must be dynamamic
            // Only load features in map extent
            defaultFilter: new OpenLayers.Filter.Spatial({
                type: OpenLayers.Filter.Spatial.BBOX,
                value: map.getExtent()
            })
        }),
        styleMap: styleMap
    });

    layer.events.register("loadend", layer, function () {
        var count = layer.features.length;
        window.parent.App.setAlert(App.STATUS_NOTICE, count + " features loaded");
    });
    layer.events.register("loadstart", layer, function () {
        //App.setAlert(App.STATUS_OK, "Start loading...");
    });
    map.addLayers([layer]);
    if (type === "Point") {
        handlerType = OpenLayers.Handler.Point;
    }
    if (type === "Polygon") {
        handlerType = OpenLayers.Handler.Polygon;
    }
    if (type === "Path") {
        handlerType = OpenLayers.Handler.Path;
    }
    drawControl = new OpenLayers.Control.DrawFeature(layer, handlerType, {
        featureAdded: onInsert,
        handlerOptions: {
            multi: multi,
            handlerOptions: {
                holeModifier: "altKey"
            }
        }
    });
    if (editable) {
        // We set the control to the second button in wfsTools
        wfsTools[2].control = drawControl;
        map.addControl(drawControl);
        modifyControl = new OpenLayers.Control.ModifyFeature(layer, {
            vertexRenderIntent: 'temporary',
            displayClass: 'olControlModifyFeature'
        });
        map.addControl(modifyControl);
        modifyControl.activate();
        sm = new GeoExt.grid.FeatureSelectionModel({
            selectControl: modifyControl.selectControl,
            singleSelect: true,
            listeners: {
                rowselect: function (sm, row, rec) {
                    try {
                        attributeForm.form.getForm().loadRecord(rec);
                    } catch (e) {
                    }
                }
            }
        });
    } else {
        sm = new GeoExt.grid.FeatureSelectionModel({
            singleSelect: false
        });
    }

    store = new GeoExt.data.FeatureStore({
        proxy: new GeoExt.data.ProtocolProxy({
            protocol: layer.protocol
        }),
        fields: fieldsForStore,
        layer: layer,
        featureFilter: new OpenLayers.Filter({
            evaluate: function (feature) {
                return feature.state !== OpenLayers.State.DELETE;
            }
        })
    });
    grid = new Ext.grid.EditorGridPanel({
        id: "gridpanel",
        region: "center",
        disabled: false,
        viewConfig: {
            forceFit: true
        },
        store: store,
        listeners: {
            afteredit: function (e) {
                var feature = e.record.get("feature");
                if (feature.state !== OpenLayers.State.INSERT) {
                    feature.state = OpenLayers.State.UPDATE;
                }
            }
        },

        sm: sm,
        cm: new Ext.grid.ColumnModel({
            defaults: {
                sortable: true,
                editor: {
                    xtype: "textfield"
                }
            },
            columns: columnsForGrid
        })
    });

    attributeForm.init(layerName);

    south.add(grid);
    gridPanel = Ext.getCmp("gridpanel");
    south.doLayout();

    attributeForm.win = new Ext.Window({
        title: "Attributes",
        modal: false,
        layout: 'fit',
        initCenter: true,
        border: false,
        width: 270,
        height: 300,
        closeAction: 'hide',
        plain: true,

        items: [new Ext.Panel({
            frame: false,
            layout: 'border',
            items: [attributeForm.form]
        })]
    });
    Ext.getCmp('editcreatebutton').toggle(false);
    Ext.getCmp('editcreatebutton').setDisabled(false);
    Ext.getCmp('editdeletebutton').setDisabled(false);
    Ext.getCmp('editsavebutton').setDisabled(false);
    Ext.getCmp('editstopbutton').setDisabled(false);
    Ext.getCmp('filterbutton').setDisabled(false);
    Ext.getCmp('infobutton').setDisabled(false);
}
$(window).ready(function () {
    'use strict';
    var bl = null;
    $("#upload").click(function () {
        window.parent.onAdd();
    });
    cloud = new mygeocloud_ol.map(null, screenName);
    map = cloud.map;
    cloud.click.activate();
    if (typeof window.setBaseLayers !== 'object') {
        window.setBaseLayers = [
            {"id": "mapQuestOSM", "name": "MapQuset OSM"},
            {"id": "osm", "name": "OSM"}
        ];
    }
    cloud.bingApiKey = window.bingApiKey;
    window.setBaseLayers = window.setBaseLayers.reverse();
    for (var i = 0; i < window.setBaseLayers.length; i++) {
        bl = cloud.addBaseLayer(window.setBaseLayers[i].id);
    }
    if (bl !== null) {
        cloud.setBaseLayer(bl);
    }

    var LayerNodeUI = Ext.extend(GeoExt.tree.LayerNodeUI, new GeoExt.tree.TreeNodeUIEventMixin());

    var layers = {};
    loadTree = function () {
        var treeConfig = [
            {
                id: "baselayers",
                nodeType: "gx_baselayercontainer"
            }
        ];
        $.ajax({
            url: '/controllers/layer/records',
            async: false,
            dataType: 'json',
            type: 'GET',
            success: function (response, textStatus, http) {
                var groups = [], isBaseLayer;
                if (http.readyState === 4) {
                    if (http.status === 200) {
                        if (response.data !== undefined) {
                            for (var i = 0; i < response.data.length; ++i) {
                                groups[i] = response.data[i].layergroup;
                            }
                            var arr = array_unique(groups);
                            for (var u = 0; u < response.data.length; ++u) {
                                if (response.data[u].baselayer) {
                                    isBaseLayer = true;
                                } else {
                                    isBaseLayer = false;
                                }
                                // Try to remove layer before adding it
                                try {
                                    cloud.removeTileLayerByName([
                                        [response.data[u].f_table_schema + "." + response.data[u].f_table_name]
                                    ]);
                                }
                                catch (e) {
                                }
                                layers[[response.data[u].f_table_schema + "." + response.data[u].f_table_name]] = cloud.addTileLayers([response.data[u].f_table_schema + "." + response.data[u].f_table_name], {
                                    singleTile: false,
                                    isBaseLayer: isBaseLayer,
                                    visibility: false,
                                    wrapDateLine: false,
                                    tileCached: true,
                                    displayInLayerSwitcher: true,
                                    name: response.data[u].f_table_schema + "." + response.data[u].f_table_name
                                });
                            }
                            for (var i = 0; i < arr.length; ++i) {
                                var l = [];
                                for (var u = 0; u < response.data.length; ++u) {
                                    if (response.data[u].layergroup === arr[i]) {
                                        l.push({
                                            text: (response.data[u].f_table_title === null || response.data[u].f_table_title === "") ? response.data[u].f_table_name : response.data[u].f_table_title,
                                            id: response.data[u].f_table_schema + "." + response.data[u].f_table_name + "." + response.data[u].f_geometry_column,
                                            leaf: true,
                                            checked: false,
                                            geomField: response.data[u].f_geometry_column
                                        });
                                    }
                                }
                                treeConfig.push({
                                    text: arr[i],
                                    isLeaf: false,
                                    expanded: false,
                                    children: l
                                });
                            }
                        }
                    }
                }
            }
        });

        // create the tree with the configuration from above
        tree = new Ext.tree.TreePanel({
            id: "tree",
            border: false,
            region: "center",
            width: 200,
            split: true,
            autoScroll: true,
            root: {
                text: 'Ext JS',
                children: Ext.decode(new OpenLayers.Format.JSON().write(treeConfig, true)),
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
                    fn: function (e) {
                        if (e.leaf === true && e.parentNode.id !== "baselayers") {
                            window.parent.onEditWMSClasses(e.id);
                            Ext.getCmp('editlayerbutton').setDisabled(false);
                        } else {
                            Ext.getCmp('editlayerbutton').setDisabled(true);
                        }
                    }
                }
            },
            rootVisible: false,
            lines: false
        });
        tree.on("checkchange", function (node, checked) {
            if (node.lastChild === null && node.parentNode.id !== "baselayers") {
                // layer id are still only schema.table in map file
                var layerId = node.id.split(".")[0] + "." + node.id.split(".")[1];
                if (checked) {
                    layers[layerId][0].setVisibility(true);
                } else {
                    layers[layerId][0].setVisibility(false);
                }
            }
        });
    };
    loadTree();
    reLoadTree = function () {
        var west = Ext.getCmp("treepanel");
        west.remove(tree);
        tree = null;
        loadTree();
        window.parent.App.setAlert(App.STATUS_NOTICE, "Layer tree (re)loaded");
        west.add(tree);
        west.doLayout();
    };
    wfsTools = [
        {
            text: "<i class='icon-edit btn-gc'></i> Start edit",
            id: "editlayerbutton",
            disabled: true,
            handler: function (thisBtn, event) {
                var node = tree.getSelectionModel().getSelectedNode();
                var id = node.id.split(".");
                var geomField = node.attributes.geomField;
                startWfsEdition(id[1], geomField);
            }
        },
        '-',
        new GeoExt.Action({
            control: drawControl,
            text: "<i class='icon-pencil btn-gc'></i> Draw",
            id: "editcreatebutton",
            disabled: true,
            enableToggle: true
        }),
        {
            text: "<i class='icon-trash btn-gc'></i> Delete",
            id: "editdeletebutton",
            disabled: true,
            handler: function () {
                gridPanel.getSelectionModel().each(function (rec) {
                    var feature = rec.get("feature");
                    modifyControl.unselectFeature(feature);
                    gridPanel.store.remove(rec);
                    if (feature.state !== OpenLayers.State.INSERT) {
                        feature.state = OpenLayers.State.DELETE;
                        layer.addFeatures([feature]);
                    }

                });
            }
        },
        {
            text: "<i class='icon-ok btn-gc'></i> Save",
            disabled: true,
            id: "editsavebutton",
            handler: function () {
                // alert(layer.features.length);
                if (modifyControl.feature) {
                    modifyControl.selectControl.unselectAll();
                }
                store.commitChanges();
                saveStrategy.save();
            }
        },
        '-',
        {
            text: "<i class='icon-stop btn-gc'></i> Stop editing",
            disabled: true,
            id: "editstopbutton",
            handler: stopEdit
        },
        '->',
        {
            text: "<i class='icon-refresh btn-gc'></i> Reload tree",
            handler: function () {
                stopEdit();
                reLoadTree();
            }
        },
        '-',
        {
            text: "<i class='icon-globe btn-gc'></i> Save extent",
            id: "extentbutton",
            disabled: false,
            handler: function () {
                Ext.Ajax.request({
                    url: '/controllers/setting/extent/',
                    method: 'put',
                    params: Ext.util.JSON.encode({data: {
                        schema: schema,
                        extent: cloud.getExtent()
                    }}),
                    headers: {
                        'Content-Type': 'application/json; charset=utf-8'
                    },
                    success: function (response) {
                        window.parent.App.setAlert(App.STATUS_NOTICE, eval('(' + response.responseText + ')').message);
                    },
                    failure: function (response) {
                        Ext.MessageBox.show({
                            title: 'Failure',
                            msg: eval('(' + response.responseText + ')').message,
                            buttons: Ext.MessageBox.OK,
                            width: 400,
                            height: 300,
                            icon: Ext.MessageBox.ERROR
                        });
                    }
                });
            }
        },
        '-',
        {
            text: "<i class='icon-th-list btn-gc'></i> Attributes",
            id: "infobutton",
            disabled: true,
            handler: function () {
                attributeForm.win.show();
            }
        },
        '-',
        {
            text: "<i class='icon-filter btn-gc'></i> Filter",
            id: "filterbutton",
            disabled: true,
            handler: function () {
                filter.win.show();
            }
        }
    ];
    viewport = new Ext.Viewport({
        layout: 'border',
        items: [
            {
                region: "center",
                layout: "fit",
                border: false,
                items: [
                    new Ext.Panel({
                        layout: "border",
                        border: false,
                        items: [
                            {
                                region: "center",
                                id: "mappanel",
                                xtype: "gx_mappanel",
                                map: map,
                                zoom: 5,
                                split: true,
                                tbar: wfsTools
                            },
                            {
                                region: "south",
                                id: "attrtable",
                                title: "Attribute table",
                                split: true,
                                frame: false,
                                layout: 'fit',
                                height: 200,
                                collapsible: true,
                                collapsed: true,
                                contentEl: "instructions"
                            }
                        ]
                    })
                ]
            },
            new Ext.Panel({
                border: false,
                region: "west",
                collapsible: false,
                width: 200,
                items: [
                    {
                        xtype: "panel",
                        border: false,
                        html: "<div class=\"layer-desc\">Click on a layer title to access settings and to edit data. Check the box to see the layer in the map.</div>",
                        height: 70
                    },
                    new Ext.Panel({
                        border: false,
                        id: "treepanel",
                        collapsible: false,
                        width: 200,
                        items: [tree]
                    })
                ]
            })
        ]
    });
    // HACK. reload tree after the view has rendered
    setTimeout(function () {
        reLoadTree();

    }, 700);
    if (window.parent.initExtent !== null) {
        cloud.map.zoomToExtent(window.parent.initExtent, false);
    } else {
        cloud.map.zoomToMaxExtent();
    }

});
function stopEdit() {
    Ext.getCmp('editcreatebutton').toggle(false);
    Ext.getCmp('editcreatebutton').setDisabled(true);
    Ext.getCmp('editdeletebutton').setDisabled(true);
    Ext.getCmp('editsavebutton').setDisabled(true);
    Ext.getCmp('editstopbutton').setDisabled(true);
    Ext.getCmp('filterbutton').setDisabled(true);
    Ext.getCmp('infobutton').setDisabled(true);
    try {
        drawControl.deactivate();
        layer.removeAllFeatures();
        map.removeLayer(layer);
    } catch (e) {
        //console.log(e.message);
    }
    Ext.getCmp("attrtable").collapse(true);
}
function onInsert() {
    var pos = grid.getStore().getCount() - 1;
    grid.selModel.selectRow(pos);
}
function array_unique(ar) {
    var sorter = {}, out = [];
    if (ar.length && typeof ar !== 'string') {
        for (var i = 0, j = ar.length; i < j; i++) {
            if (!sorter[ar[i] + typeof ar[i]]) {
                out.push(ar[i]);
                sorter[ar[i] + typeof ar[i]] = true;
            }
        }
    }
    return out || ar;
}
saveStrategy = new OpenLayers.Strategy.Save({
    onCommit: function (response) {
        if (!response.success()) {
            var format = new OpenLayers.Format.XML();
            var doc = format.read(response.priv.responseText);
            try {
                var error = doc
                    .getElementsByTagName('ServiceException')[0].firstChild.data;
            } catch (e) {
            }
            try {
                var error = doc
                    .getElementsByTagName('wfs:ServiceException')[0].firstChild.data;
            } catch (e) {
            }
            message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows='5' cols='31'>" + error + "</textarea>";
            Ext.MessageBox.show({
                title: 'Failure',
                msg: message,
                buttons: Ext.MessageBox.OK,
                width: 400,
                height: 300,
                icon: Ext.MessageBox.ERROR
            });
        } else {
            saveStrategy.layer.refresh();
            format = new OpenLayers.Format.XML();
            var doc = format.read(response.priv.responseText);
            try {
                var inserted = doc
                    .getElementsByTagName('wfs:totalInserted')[0].firstChild.data;
            } catch (e) {
            }

            try {
                var deleted = doc
                    .getElementsByTagName('wfs:totalDeleted')[0].firstChild.data;
            } catch (e) {
            }

            try {
                var updated = doc
                    .getElementsByTagName('wfs:totalUpdated')[0].firstChild.data;
            } catch (e) {
            }

            try {
                var updated = doc
                    .getElementsByTagName('wfs:Message')[0].firstChild.data;
            } catch (e) {
            }


            // For webkit
            try {
                var inserted = doc
                    .getElementsByTagName('totalInserted')[0].firstChild.data;
            } catch (e) {
            }

            try {
                var deleted = doc
                    .getElementsByTagName('totalDeleted')[0].firstChild.data;
            } catch (e) {
            }

            try {
                var updated = doc
                    .getElementsByTagName('totalUpdated')[0].firstChild.data;
            } catch (e) {
            }

            try {
                var updated = doc
                    .getElementsByTagName('Message')[0].firstChild.data;
            } catch (e) {
            }


            var message = "";
            if (inserted) {
                message = "<p>Inserted: " + inserted + "</p>";
                window.parent.App.setAlert(App.STATUS_OK, message);
            }
            if (updated) {
                message = "<p>Updated: " + updated + "</p>";
                window.parent.App.setAlert(App.STATUS_OK, message);
            }
            if (deleted) {
                message = "<p>Deleted: " + deleted + "</p>";
                window.parent.App.setAlert(App.STATUS_OK, message);
            }
        }
    }
});

