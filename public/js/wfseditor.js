/*global Ext:false */
/*global $:false */
/*global OpenLayers:false */
/*global GeoExt:false */
/*global mygeocloud_ol:false */
/*global schema:false */
/*global screenName:false */
/*global attributeForm:false */
Ext.BLANK_IMAGE_URL = "/js/ext/resources/images/default/s.gif";
var App = new Ext.App({}), cloud, layer, grid, store, map, wfsTools, viewport, drawControl, gridPanel, modifyControl, tree, viewerSettings, loadTree, reLoadTree, layerBeingEditing, saveStrategy;
function startWfsEdition(layerName, geomField, wfsFilter) {
    'use strict';
    var fieldsForStore, columnsForGrid, type, multi, handlerType, editable = true, sm, south = Ext.getCmp("attrtable");
    layerBeingEditing = layerName;
    try {
        drawControl.deactivate();
        layer.removeAllFeatures();
        map.removeLayer(layer);
    } catch (e) {
    }
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
    if (type === "Point") {
        handlerType = OpenLayers.Handler.Point;
    }
    else if (type === "Polygon") {
        handlerType = OpenLayers.Handler.Polygon;
    }
    else if (type === "Path") {
        handlerType = OpenLayers.Handler.Path;
    }
    south.expand(true);
    var styleMap = new OpenLayers.StyleMap({
        temporary: OpenLayers.Util.applyDefaults({
            pointRadius: 5
        }, OpenLayers.Feature.Vector.style.temporary)
    });
    layer = new OpenLayers.Layer.Vector("vector", {
        strategies: [new OpenLayers.Strategy.Fixed(), saveStrategy],
        protocol: new OpenLayers.Protocol.WFS.v1_0_0({
            url: "/wfs/" + screenName + "/" + schema + "/900913?",
            version: "1.0.0",
            featureType: layerName,
            featureNS: "http://twitter/" + screenName,
            srsName: "EPSG:900913",
            geometryName: geomField, // must be dynamamic
            // Only load features in map extent
            defaultFilter: wfsFilter
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
    Ext.getCmp('infobutton').setDisabled(false);
}
$(document).ready(function () {
    'use strict';
    var bl = null;
    $("#upload").click(function () {
        window.parent.onAdd();
    });
    cloud = new mygeocloud_ol.map(null, screenName);
    map = cloud.map;
    var metaData, metaDataKeys = [], metaDataKeysTitle = [], extent = null;
    var gc2 = new geocloud.map({});
    gc2.map = map;
    $.ajax({
        url: '/api/v1/meta/' + screenName + '/' + schema,
        async: false,
        dataType: 'json',
        type: 'GET',
        success: function (response) {
            metaData = response;
            for (var i = 0; i < metaData.data.length; i++) {
                metaDataKeys[metaData.data[i].f_table_name] = metaData.data[i];
                if (!metaData.data[i].f_table_title) {
                    metaData.data[i].f_table_title = metaData.data[i].f_table_name;
                }
                metaDataKeysTitle[metaData.data[i].f_table_title] = metaData.data[i];
            }
        }
    }); // Ajax call end
    var clicktimer;
    gc2.on("dblclick", function (e) {
        clicktimer = undefined;
    });
    var qstore = [];
    var queryWin = new Ext.Window({
        title: "Query result",
        modal: false,
        layout: 'fit',
        width: 500,
        height: 400,
        closeAction: 'hide',
        plain: true,
        items: [
            new Ext.TabPanel({
                activeTab: 0,
                frame: true,
                id: "queryTabs"
            })
        ]
    });
    gc2.on("click", function (e) {
        /*var layers, count = 0, hit = false, event = new geocloud.clickEvent(e, cloud), distance, db = screenName;
         if (clicktimer) {
         clearTimeout(clicktimer);
         }
         else {
         clicktimer = setTimeout(function (e) {
         clicktimer = undefined;
         var coords = event.getCoordinate();
         $.each(qstore, function (index, st) {
         try {
         st.reset();
         gc2.removeGeoJsonStore(st);
         }
         catch (e) {

         }
         });
         layers = gc2.getVisibleLayers().split(";");
         Ext.getCmp("queryTabs").removeAll();
         queryWin.show();
         $.each(layers, function (index, value) {
         var isEmpty = true;
         var srid = metaDataKeys[value.split(".")[1]].srid;
         var geoType = metaDataKeys[value.split(".")[1]].type;
         var layerTitel = metaDataKeys[value.split(".")[1]].f_table_name;
         if (geoType !== "POLYGON" && geoType !== "MULTIPOLYGON") {
         var res = [156543.033928, 78271.516964, 39135.758482, 19567.879241, 9783.9396205,
         4891.96981025, 2445.98490513, 1222.99245256, 611.496226281, 305.748113141, 152.87405657,
         76.4370282852, 38.2185141426, 19.1092570713, 9.55462853565, 4.77731426782, 2.38865713391,
         1.19432856696, 0.597164283478, 0.298582141739];
         distance = 5 * res[cloud.getZoom()];
         }
         qstore[index] = new geocloud.sqlStore({
         db: db,
         id: index,
         onLoad: function () {
         var layerObj = qstore[this.id], out = [], fieldLabel;
         isEmpty = layerObj.isEmpty();
         if ((!isEmpty)) {
         Ext.getCmp("queryTabs").add(
         {
         title: layerTitel,
         layout: "fit",
         items: [
         {
         xtype: "panel",
         layout: "fit",
         html: "sdd",
         tbar: [
         {text: "Edit"}
         ]
         }
         ]
         }
         );
         $.each(layerObj.geoJSON.features, function (i, feature) {
         $.each(feature.properties, function (name, property) {
         out.push([name, 0, name, property]);
         });
         out.sort(function (a, b) {
         return a[1] - b[1];
         });
         //console.log(out);
         $.each(out, function (name, property) {
         console.log(property[2] + ":" + property[3]);
         $("#_" + index + " table").append('<tr><td>' + property[2] + '</td><td>' + property[3] + '</td></tr>');
         });
         out = [];
         //$('#info-tab a:first').tab('show');
         });
         hit = true;
         }
         count++;
         Ext.getCmp("queryTabs").activate(0);
         }
         });
         gc2.addGeoJsonStore(qstore[index]);
         var sql, f_geometry_column = metaDataKeys[value.split(".")[1]].f_geometry_column;
         if (geoType !== "POLYGON" && geoType !== "MULTIPOLYGON") {
         sql = "SELECT * FROM " + value + " WHERE ST_Intersects(ST_Transform(ST_buffer(ST_geomfromtext('POINT(" + coords.x + " " + coords.y + ")',900913), " + distance + " )," + srid + "),\"" + f_geometry_column + "\")";
         }
         else {
         sql = "SELECT * FROM " + value + " WHERE ST_Intersects(ST_Transform(ST_geomfromtext('POINT(" + coords.x + " " + coords.y + ")',900913)," + srid + "),\"" + f_geometry_column + "\")";
         }
         qstore[index].sql = sql;
         qstore[index].load();
         });
         }, 250);
         }*/
    });

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
            dataType: 'json',
            type: 'GET',
            success: function (response) {
                var groups = [], isBaseLayer;
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
                                    geomField: response.data[u].f_geometry_column,
                                    geomType: response.data[u].type
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
                                try {
                                    stopEdit();
                                    filter.win.hide();
                                    filter.win = false;

                                }
                                catch (e) {
                                }
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
                if (typeof viewport === "undefined") {
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
                                        width: 200
                                    })
                                ]
                            })
                        ]
                    });
                    if (window.parent.initExtent !== null) {
                        cloud.map.zoomToExtent(window.parent.initExtent, false);
                    } else {
                        cloud.map.zoomToMaxExtent();
                    }
                }
                else {
                    window.parent.App.setAlert(App.STATUS_NOTICE, "Layer tree (re)loaded");
                }
                var west = Ext.getCmp("treepanel");
                west.remove(tree);
                west.add(tree);
                west.doLayout();
                window.parent.writeFiles();
            }
        });
    };
    wfsTools = [
        {
            text: "<i class='icon-edit btn-gc'></i> Start edit",
            id: "editlayerbutton",
            disabled: true,
            handler: function (thisBtn, event) {
                try {
                    stopEdit();
                }
                catch (e) {
                }
                var node = tree.getSelectionModel().getSelectedNode();
                var id = node.id.split(".");
                var geomField = node.attributes.geomField;
                var type = node.attributes.geomType;
                attributeForm.init(id[1], geomField);
                if (type === "GEOMETRY" || type === "RASTER") {
                    Ext.MessageBox.show({
                        title: 'No geometry type on layer',
                        msg: "The layer has no geometry type or type is GEOMETRY. You can set geom type for the layer in 'Settings' to the right.",
                        buttons: Ext.MessageBox.OK,
                        width: 400,
                        height: 300,
                        icon: Ext.MessageBox.ERROR
                    });

                }
                else {
                    var poll = function () {
                        if (typeof filter.win === "object") {
                            filter.win.show();
                        }
                        else {
                            setTimeout(poll, 10);
                        }
                    };
                    poll();
                }
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
                        extent: cloud.getExtent(),
                        zoom: cloud.getZoom(),
                        center: [cloud.getCenter().x, cloud.getCenter().y]
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
        }
    ];
    reLoadTree = function () {
        loadTree();
    };
    loadTree();
});
function stopEdit() {
    "use strict";
    layerBeingEditing = null;
    try {
        filter.win.hide();
        filter.win = false;
    }
    catch (e) {
    }
    Ext.getCmp('editcreatebutton').toggle(false);
    Ext.getCmp('editcreatebutton').setDisabled(true);
    Ext.getCmp('editdeletebutton').setDisabled(true);
    Ext.getCmp('editsavebutton').setDisabled(true);
    Ext.getCmp('editstopbutton').setDisabled(true);
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
        var format, doc, error;
        if (!response.success()) {
            format = new OpenLayers.Format.XML();
            doc = format.read(response.priv.responseText);
            try {
                error = doc
                    .getElementsByTagName('ServiceException')[0].firstChild.data;
            } catch (e) {
            }
            try {
                error = doc
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
            doc = format.read(response.priv.responseText);
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

