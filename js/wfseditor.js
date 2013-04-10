Ext.BLANK_IMAGE_URL = "/js/ext/resources/images/default/s.gif";
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
var loadTree;
var reLoadTree;
var layerBeingEditing;

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
    var layerBeingEditing=layerName;
    // allow testing of specific renderers via "?renderer=Canvas", etc
    /*
     var renderer = OpenLayers.Util.getParameters(window.location.href).renderer;

     renderer = (renderer) ? [ renderer ]
     : OpenLayers.Layer.Vector.prototype.renderers;
     */

    try {
        drawControl.deactivate();
        layer.removeAllFeatures();
        map.removeLayer(layer);
    } catch (e) {
        //alert(e.message);
    }
    var south = viewport.getComponent(1);
    south.expand(true);
    south.remove(grid);

    $.ajax({
        url: '/controller/tables/' + screenName + '/getcolumns/' + layerName,
        async: false,
        dataType: 'json',
        type: 'GET',
        success: function (data, textStatus, http) {
            if (http.readyState == 4) {
                if (http.status == 200) {
                    var response = eval('(' + http.responseText + ')');
                    // JSON
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
                                columnsForGrid[i].editor = new Ext.form.NumberField({
                                    decimalPrecision: 0,
                                    decimalSeparator: '¤'// Some strange char nobody is
                                    // using
                                });
                            } else if (columnsForGrid[i].typeObj.type == "decimal") {
                                columnsForGrid[i].editor = new Ext.form.NumberField({
                                    decimalPrecision: columnsForGrid[i].typeObj.scale,
                                    decimalSeparator: '.'
                                    // maxLength: columnsForGrid[i].type.precision
                                });
                            } else if (columnsForGrid[i].typeObj.type == "string") {
                                columnsForGrid[i].editor = new Ext.form.TextField();
                            } else if (columnsForGrid[i].typeObj.type == "text") {
                                columnsForGrid[i].editor = new Ext.form.TextArea();
                            }
                        }
                    }
                    ;
                }
            }

        }
    });
    var styleMap = new OpenLayers.StyleMap({
        temporary: OpenLayers.Util.applyDefaults({
            pointRadius: 15
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
            geometryName: "the_geom", // must be dynamamic
            // Only load features in map extent
            defaultFilter: new OpenLayers.Filter.Spatial({
                type: OpenLayers.Filter.Spatial.BBOX,
                value: map.getExtent()
            })
        }),
        styleMap: styleMap
    });

    map.addLayers([layer]);
    layer.events.register("loadend", layer, function () {
        //map.zoomToExtent(layer.getDataExtent());

        // loadMessage.hide();
        // alert("");
    });
    layer.events.register("loadstart", layer, function () {
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
        //drawControl.deactivate();
        //drawControl.activate();
        wfsTools[2].control = drawControl;
        // We set the control to the first button in wfsTools
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
        // height:100,
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


};
$(window).load(function () {
    var cloud = new mygeocloud_ol.map(null, screenName);
    map = cloud.map;

    cloud.click.activate();
    cloud.addGoogleTerrain();
    cloud.addGoogleSatellite();
    cloud.addGoogleHybrid();
    cloud.addGoogleStreets();
    cloud.addMapQuestAerial();
    cloud.addMapQuestOSM();
    cloud.setBaseLayer(cloud.addOSM())
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
            url: '/controller/tables/' + screenName + '/getrecords/settings.geometry_columns_view',
            async: false,
            dataType: 'json',
            type: 'GET',
            success: function (data, textStatus, http) {
                var groups = [];
                if (http.readyState == 4) {
                    if (http.status == 200) {
                        var response = eval('(' + http.responseText + ')');
                        //console.log(response);
                        for (var i = 0; i < response.data.length; ++i) {
                            groups[i] = response.data[i].layergroup;
                        }
                        var arr = array_unique(groups);
                        for (var u = 0; u < response.data.length; ++u) {
                            //console.log(response.data[u].baselayer);
                            if (response.data[u].baselayer) {
                                var isBaseLayer = true;
                            } else {
                                var isBaseLayer = false;
                            }
                            layers[[response.data[u].f_table_schema + "." + response.data[u].f_table_name]] = cloud.addTileLayers([response.data[u].f_table_schema + "." + response.data[u].f_table_name], {
                                singleTile: false,
                                isBaseLayer: isBaseLayer,
                                visibility: false,
                                wrapDateLine: false,
                                tileCached: true,
                                displayInLayerSwitcher: true,
                                name: response.data[u].f_table_name
                            });
                        }
                        for (var i = 0; i < arr.length; ++i) {
                            var l = [];
                            for (var u = 0; u < response.data.length; ++u) {
                                //console.log(response.data[u].baselayer);
                                //console.log(response.data[u].f_table_title);
                                if (response.data[u].layergroup == arr[i]) {
                                    l.push({
                                        text: (response.data[u].f_table_title === null || response.data[u].f_table_title === "") ? response.data[u].f_table_name : response.data[u].f_table_title,
                                        id: response.data[u].f_table_schema + "." + response.data[u].f_table_name,
                                        leaf: true,
                                        checked: false
                                    });
                                }
                            }
                            treeConfig.push({
                                //nodeType: "gx_layer",
                                text: arr[i],
                                isLeaf: false,
                                //id: arr[i],
                                expanded: true,
                                children: l
                            });
                        }
                    }
                }
            }
        });
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
            frame: false,
            border: false,
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
                    fn: function (e) {
                        if (e.lastChild === null && e.parentNode.id !== "baselayers") {
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
                if (checked) {
                    layers[node.id][0].setVisibility(true);
                } else {
                    layers[node.id][0].setVisibility(false);
                }
            }
        });
    };
    reLoadTree = function () {
        var num = cloud.map.getNumLayers();
        for (var j = 1; j < num; j++) {
            if (cloud.map.layers[j].isBaseLayer === false) {
                cloud.map.layers[j].setVisibility(false);
            };
        }
        var west = viewport.getComponent(2);
        west.remove(tree);
        tree = null;
        loadTree();
        west.add(tree);
        west.doLayout();
    }
    loadTree();
    wfsTools = [
        {
            text: "<i class='icon-edit btn-gc'></i> Start edit",
            id: "editlayerbutton",
            disabled: true,
            handler: function (thisBtn, event) {
                var node = tree.getSelectionModel().getSelectedNode();
                //console.log(node.id);
                var id = node.id.split(".");
                startWfsEdition(id[1]);
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
        },'-',
        {
            text: "<i class='icon-stop btn-gc'></i> Stop editing",
            disabled: true,
            id: "editstopbutton",
            handler: stopEdit
        },
        '->',

        {
            text: "<i class='icon-th-list btn-gc'></i> Attributes",
            id : "infobutton",
            disabled: true,
            handler: function () {
                attributeForm.win.show();

            }
        },
        '-',
        {
            text: "<i class='icon-filter btn-gc'></i> Filter",
            id : "filterbutton",
            disabled: true,
            handler: function () {
                filter.win.show();

            }
        },'-', {
            text: "<i class='icon-refresh btn-gc'></i> Reload",
            handler: function () {
                stopEdit();
                reLoadTree();
            }
        }
    ];

    viewport = new Ext.Viewport({
        layout: 'border',
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
                title: "Attribute table",
                split: true,
                frame: false,
                layout: 'fit',
                height: 200,
                collapsible: true,
                collapsed: true,
                contentEl: "instructions"
            },
            new Ext.Panel({
                frame: false,
                region: "west",
                collapsible: true,
                layout: 'fit',
                width: 200,
                items: [tree]
            })
        ]
    });
    cloud.map.zoomToMaxExtent();

    // After the view is initiated we zoom to max extent.
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
        //alert(e.message);
    }
    var south = viewport.getComponent(1);
    //south.remove(grid);
    south.collapse(true);
}
function onInsert() {
    var pos = grid.getStore().getCount() - 1;
    grid.selModel.selectRow(pos);
    //attributeForm.win.show();
}

function test() {
    alert('test');
}

function array_unique(ar) {
    if (ar.length && typeof ar !== 'string') {
        var sorter = {};
        var out = [];
        for (var i = 0, j = ar.length; i < j; i++) {
            if (!sorter[ar[i] + typeof ar[i]]) {
                out.push(ar[i]);
                sorter[ar[i] + typeof ar[i]] = true;
            }
        }
    }
    return out || ar;
}

var saveStrategy = new OpenLayers.Strategy.Save({
    onCommit: function (response) {
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
            Ext.MessageBox.show({
                title: 'Success!',
                msg: message,
                buttons: Ext.MessageBox.OK,
                width: 200,
                icon: Ext.MessageBox.INFO
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
            message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows='5' cols='31'>" + error + "</textarea>";
            Ext.MessageBox.show({
                title: 'Failure',
                msg: message,
                buttons: Ext.MessageBox.OK,
                width: 400,
                height: 300,
                icon: Ext.MessageBox.ERROR
            });
        }

    }
});

