/*global Ext:false */
/*global $:false */
/*global OpenLayers:false */
/*global GeoExt:false */
/*global mygeocloud_ol:false */
/*global schema:false */
/*global screenName:false */
/*global attributeForm:false */
/*global geocloud:false */
/*global gc2i18n:false */
OpenLayers.IMAGE_RELOAD_ATTEMPTS = 3;
Ext.BLANK_IMAGE_URL = "/js/ext/resources/images/default/s.gif";
Ext.QuickTips.init();
var App = new Ext.App({}), cloud, gc2, layer, grid, store, map, wfsTools, viewport, drawControl, gridPanel, modifyControl, tree, viewerSettings, loadTree, reLoadTree, layerBeingEditing, layerBeingEditingGeomField, saveStrategy, getMetaData, searchWin, measureWin, placeMarkers, placePopup, measureControls, extentRestrictLayer, addedBaseLayers = [], currentId, mapTools;
function startWfsEdition(layerName, geomField, wfsFilter, single, timeSlice) {
    'use strict';
    var fieldsForStore, columnsForGrid, type, multi, handlerType, editable = true, sm, south = Ext.getCmp("attrtable"), singleEditing = single;
    layerBeingEditing = layerName;
    layerBeingEditingGeomField = geomField;
    try {
        drawControl.deactivate();
        layer.removeAllFeatures();
        map.removeLayer(layer);
    } catch (e) {
    }
    try {
        south.remove(grid);
    } catch (e) {

    }
    $.ajax({
        url: '/controllers/table/columns/' + layerName,
        async: false,
        dataType: 'json',
        type: 'GET',
        success: function (data, textStatus, http) {
            var response = data, validProperties = true;
            // JSON
            fieldsForStore = response.forStore;
            columnsForGrid = response.forGrid;
            type = response.type;
            multi = response.multi;
            // We add an editor to the fields
            for (var i in columnsForGrid) {
                columnsForGrid[i].editable = editable;
                if (columnsForGrid[i].typeObj !== undefined) {
                    if (columnsForGrid[i].properties) {
                        try {
                            var json = Ext.decode(columnsForGrid[i].properties);
                            columnsForGrid[i].editor = new Ext.form.ComboBox({
                                store: Ext.decode(columnsForGrid[i].properties),
                                editable: true,
                                triggerAction: 'all'
                            });
                            validProperties = false;
                        }
                        catch (e) {
                            alert('There is invalid properties on field ' + columnsForGrid[i].dataIndex);
                        }
                    } else if (columnsForGrid[i].typeObj.type === "int") {
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
    var rules = {
        rules: [
            new OpenLayers.Rule({
                filter: new OpenLayers.Filter.Comparison({
                    type: OpenLayers.Filter.Comparison.NOT_EQUAL_TO,
                    property: "gc2_version_end_date",
                    value: 'null'
                }),
                symbolizer: {
                    fillColor: "#000000",
                    fillOpacity: 0.0,
                    strokeColor: "#FF0000",
                    strokeWidth: 2,
                    strokeDashstyle: "dash",
                    strokeOpacity: 0.7,
                    graphicZIndex: 1
                }
            }),
            new OpenLayers.Rule({
                filter: new OpenLayers.Filter.Comparison({
                    type: OpenLayers.Filter.Comparison.EQUAL_TO,
                    property: "gc2_version_end_date",
                    value: null
                }),
                symbolizer: {
                    fillColor: "#000000",
                    fillOpacity: 0.0,
                    strokeColor: "#0000FF",
                    strokeWidth: 3,
                    strokeOpacity: 0.7,
                    graphicZIndex: 3,
                    strokeDashstyle: "solid"
                }
            })
        ]
    };
    var styleMap = new OpenLayers.StyleMap({
        "default": new OpenLayers.Style({
                fillColor: "#000000",
                fillOpacity: 0.0,
                pointRadius: 5,
                strokeColor: "#0000FF",
                strokeWidth: 3,
                strokeOpacity: 0.7,
                graphicZIndex: 3

            },
            rules
        ),
        temporary: new OpenLayers.Style({
                fillColor: "#FFFFFF",
                fillOpacity: 0.7,
                pointRadius: 5,
                strokeColor: "#0000FF",
                strokeWidth: 1,
                strokeOpacity: 0.7,
                graphicZIndex: 1
            }
        ),
        select: new OpenLayers.Style({
                fillColor: "#000000",
                fillOpacity: 0.2,
                pointRadius: 8,
                strokeColor: "#0000FF",
                strokeWidth: 3,
                strokeOpacity: 1,
                graphicZIndex: 3
            }, rules
        )
    });

    layer = new OpenLayers.Layer.Vector("vector", {
        strategies: [new OpenLayers.Strategy.Fixed(), saveStrategy],
        protocol: new OpenLayers.Protocol.WFS.v1_0_0({
            url: "/wfs/" + (subUser ? subUser + "@" + screenName : screenName) + "/" + schema + "/900913" + (timeSlice ? "/" + timeSlice : "") + "?",
            version: "1.0.0",
            featureType: layerName,
            featureNS: "http://mapcentia.com/" + screenName,
            featurePrefix: screenName,
            srsName: "EPSG:900913",
            geometryName: geomField, // must be dynamamic
            defaultFilter: wfsFilter
        }),
        styleMap: styleMap
    });
    map.addLayers([layer]);
    layer.events.register("loadend", layer, function () {
        var count = layer.features.length;
        window.parent.App.setAlert(App.STATUS_NOTICE, count + " features loaded");
        if (layer.features.length > 0) {
            map.zoomToExtent(layer.getDataExtent());
        }
        if (singleEditing) {
            setTimeout(function () {
                map.controls[map.controls.length - 1].selectControl.select(layer.features[0]);
            }, 600);
            singleEditing = false;
        }
    });
    layer.events.register("loadstart", layer, function () {
        //App.setAlert(App.STATUS_OK, "Start loading...");
    });

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
        wfsTools[0].control = drawControl;
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
                    attributeForm.form.enable();
                    try {
                        attributeForm.form.getForm().loadRecord(rec);
                    } catch (e) {
                    }
                },
                rowdeselect: function () {
                    attributeForm.form.disable();
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
            //forceFit: true
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
        })/*,
         bbar: [new Ext.PagingToolbar({
         pageSize: 2,
         store: store,
         displayInfo: true,
         displayMsg: 'Features {0} - {1} of {2}',
         emptyMsg: "No features"
         })]*/
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
        width: 500,
        height: 350,
        closeAction: 'hide',
        plain: true,
        items: [new Ext.Panel({
            frame: false,
            layout: 'border',
            border: false,
            items: [attributeForm.form]
        })]
    });
    attributeForm.win.show();
    attributeForm.win.hide();
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
    cloud = new mygeocloud_ol.map(null, screenName, {
        controls: [
            new OpenLayers.Control.Navigation({
                //zoomBoxEnabled: true
            }),
            new OpenLayers.Control.Zoom(),
            new OpenLayers.Control.Attribution()
        ],
        restrictedExtent: subUser && window.parent.settings.extentrestricts ? window.parent.settings.extentrestricts[schema] : null
    });
    map = cloud.map;
    var metaData, metaDataKeys = [], metaDataKeysTitle = [], metaDataRealKeys = [], extent = null;
    var gc2 = new geocloud.map({});
    gc2.map = map;
    getMetaData = function () {
        $.ajax({
            url: '/api/v1/meta/' + screenName + '/' + schema,
            async: true,
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
    };
    var clicktimer;
    gc2.on("dblclick", function (e) {
        clicktimer = undefined;
    });
    var qstore = [];
    var queryWin = new Ext.Window({
        title: "Query result",
        modal: false,
        border: false,
        layout: 'fit',
        width: 400,
        height: 400,
        closeAction: 'hide',
        x: 100,
        y: 100,
        plain: true,
        listeners: {
            hide: {
                fn: function (el, e) {
                    Ext.iterate(qstore, function (v) {
                        v.reset();
                    });
                }
            }
        },
        items: [
            new Ext.TabPanel({
                activeTab: 0,
                frame: false,
                id: "queryTabs",
                resizeTabs: true,
                plain: true,
                border: false
            })
        ]
    });
    gc2.on("click", function (e) {
        var layers, count = 0, hit = false, event = new geocloud.clickEvent(e, cloud), distance, db = screenName;
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
                $.each(layers, function (index, value) {
                    var isEmpty = true;
                    var srid = metaDataKeys[value.split(".")[1]].srid;
                    var pkey = metaDataKeys[value.split(".")[1]].pkey;
                    var geoField = metaDataKeys[value.split(".")[1]].f_geometry_column;
                    var geoType = metaDataKeys[value.split(".")[1]].type;
                    var layerTitel = metaDataKeys[value.split(".")[1]].f_table_name;
                    var versioning = metaDataKeys[value.split(".")[1]].versioning;
                    if (geoType !== "POLYGON" && geoType !== "MULTIPOLYGON") {
                        var res = [156543.033928, 78271.516964, 39135.758482, 19567.879241, 9783.9396205,
                            4891.96981025, 2445.98490513, 1222.99245256, 611.496226281, 305.748113141, 152.87405657,
                            76.4370282852, 38.2185141426, 19.1092570713, 9.55462853565, 4.77731426782, 2.38865713391,
                            1.19432856696, 0.597164283478, 0.298582141739, 0.149291];
                        distance = 5 * res[cloud.getZoom()];
                    }
                    qstore[index] = new geocloud.sqlStore({
                        db: db,
                        id: index,
                        styleMap: new OpenLayers.StyleMap({
                            "default": new OpenLayers.Style({
                                    fillColor: "#000000",
                                    fillOpacity: 0.0,
                                    pointRadius: 8,
                                    strokeColor: "#FF0000",
                                    strokeWidth: 3,
                                    strokeOpacity: 0.7,
                                    graphicZIndex: 3
                                }
                            )
                        }),
                        onLoad: function () {
                            var layerObj = qstore[this.id], out = [], source = {}, pkeyValue;
                            isEmpty = layerObj.isEmpty();
                            if ((!isEmpty)) {
                                queryWin.show();
                                $.each(layerObj.geoJSON.features, function (i, feature) {
                                    $.each(feature.properties, function (name, property) {
                                        out.push([name, 0, name, property]);
                                    });
                                    out.sort(function (a, b) {
                                        return a[1] - b[1];
                                    });
                                    $.each(out, function (name, property) {
                                        if (property[2] === pkey) {
                                            pkeyValue = property[3];
                                        }
                                        source[property[2]] = property[3];
                                    });
                                    out = [];
                                });
                                Ext.getCmp("queryTabs").add(
                                    {
                                        title: layerTitel,
                                        layout: "fit",
                                        border: false,
                                        items: [
                                            {
                                                xtype: "panel",
                                                layout: "fit",
                                                id: layerTitel,
                                                border: false,
                                                tbar: [
                                                    {
                                                        text: "<i class='fa fa-edit'></i> Edit feature #" + pkeyValue,
                                                        handler: function () {
                                                            if (geoType === "GEOMETRY" || geoType === "RASTER") {
                                                                Ext.MessageBox.show({
                                                                    title: 'No geometry type on layer',
                                                                    msg: "The layer has no geometry type or type is GEOMETRY. You can set geom type for the layer in 'Settings' to the right.",
                                                                    buttons: Ext.MessageBox.OK,
                                                                    width: 400,
                                                                    height: 300,
                                                                    icon: Ext.MessageBox.ERROR
                                                                });
                                                                return false;

                                                            }
                                                            else {
                                                                var filter = new OpenLayers.Filter.Comparison({
                                                                    type: OpenLayers.Filter.Comparison.EQUAL_TO,
                                                                    property: "\"" + pkey + "\"",
                                                                    value: pkeyValue
                                                                });
                                                                attributeForm.init(layerTitel, geoField);
                                                                startWfsEdition(layerTitel, geoField, filter, true);
                                                                attributeForm.form.disable();
                                                                Ext.iterate(qstore, function (v) {
                                                                    v.reset();
                                                                });
                                                                queryWin.hide();
                                                            }
                                                        }
                                                    }
                                                ],
                                                items: [
                                                    new Ext.grid.PropertyGrid({
                                                        autoHeight: false,
                                                        border: false,
                                                        startEditing: Ext.emptyFn,
                                                        source: source
                                                    })
                                                ]
                                            }
                                        ]
                                    }
                                );
                                hit = true;
                            }
                            if (!hit) {
                                try {
                                    queryWin.hide();
                                }
                                catch (e) {
                                }
                            }
                            count++;
                            Ext.getCmp("queryTabs").activate(0);
                        }
                    });
                    gc2.addGeoJsonStore(qstore[index]);
                    var sql, f_geometry_column = metaDataKeys[value.split(".")[1]].f_geometry_column;
                    if (geoType !== "POLYGON" && geoType !== "MULTIPOLYGON") {
                        sql = "SELECT * FROM " + value + " WHERE round(ST_Distance(ST_Transform(\"" + f_geometry_column + "\",3857), ST_GeomFromText('POINT(" + coords.x + " " + coords.y + ")',3857))) < " + distance;
                        if (versioning) {
                            sql = sql + " AND gc2_version_end_date IS NULL";
                        }
                        sql = sql + " ORDER BY round(ST_Distance(ST_Transform(\"" + f_geometry_column + "\",3857), ST_GeomFromText('POINT(" + coords.x + " " + coords.y + ")',3857)))";
                    }
                    else {
                        sql = "SELECT * FROM " + value + " WHERE ST_Intersects(ST_Transform(ST_geomfromtext('POINT(" + coords.x + " " + coords.y + ")',900913)," + srid + "),\"" + f_geometry_column + "\")";
                        if (versioning) {
                            sql = sql + " AND gc2_version_end_date IS NULL";
                        }

                    }
                    sql = sql + " LIMIT 1";
                    qstore[index].sql = sql;
                    qstore[index].load();
                });
            }, 250);
        }
    });

    if (typeof window.setBaseLayers !== 'object') {
        window.setBaseLayers = [
            {"id": "mapQuestOSM", "name": "MapQuset OSM"},
            {"id": "osm", "name": "OSM"}
        ];
    }
    cloud.bingApiKey = window.bingApiKey;
    cloud.digitalGlobeKey = window.digitalGlobeKey;
    window.setBaseLayers = window.setBaseLayers.reverse();
    var altId, lName;
    for (var i = 0; i < window.setBaseLayers.length; i++) {
        if (typeof window.setBaseLayers[i].restrictTo === "undefined" || window.setBaseLayers[i].restrictTo.indexOf(schema) > -1) {
            // Local base layer
            if (typeof window.setBaseLayers[i].db !== "undefined") {
                altId = window.setBaseLayers[i].id + window.setBaseLayers[i].name;
                lName = window.setBaseLayers[i].name;
            }
            bl = cloud.addBaseLayer(window.setBaseLayers[i].id, window.setBaseLayers[i].db, altId, lName);
        }
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
                nodeType: "gx_baselayercontainer",
                singleClickExpand: true
            }
        ];
        $.ajax({
            url: '/api/v1/meta/' + screenName + '/' + schema,
            dataType: 'json',
            type: 'GET',
            success: function (response) {
                var groups = [], isBaseLayer;
                if (response.data !== undefined) {
                    for (var i = 0; i < response.data.length; ++i) {
                        groups[i] = response.data[i].layergroup;
                        metaDataRealKeys[response.data[i]._key_] = response.data[i];// Holds the layer extents
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
                        if (response.data[u].type) {
                            layers[[response.data[u].f_table_schema + "." + response.data[u].f_table_name]] = cloud.addTileLayers([response.data[u].f_table_schema + "." + response.data[u].f_table_name], {
                                singleTile: false,
                                //isBaseLayer: isBaseLayer,
                                visibility: false,
                                wrapDateLine: false,
                                tileCached: true,
                                displayInLayerSwitcher: true,
                                name: response.data[u].f_table_schema + "." + response.data[u].f_table_name
                            });
                        }
                    }
                    for (i = 0; i < arr.length; ++i) {
                        var l = [], id;
                        for (u = 0; u < response.data.length; ++u) {
                            if (response.data[u].layergroup === arr[i]) {
                                id = response.data[u].f_table_schema + "." + response.data[u].f_table_name + "." + response.data[u].f_geometry_column;
                                if (response.data[u].type) {
                                    l.push({
                                        text: ((response.data[u].f_table_title === null || response.data[u].f_table_title === "") ? response.data[u].f_table_name : response.data[u].f_table_title) + " <span style='float:right' class='leaf-tools' id='" + id.split('.').join('-') + "'></span>",
                                        id: id,
                                        leaf: true,
                                        checked: false,
                                        geomField: response.data[u].f_geometry_column,
                                        geomType: response.data[u].type
                                    });
                                }
                            }
                        }
                        treeConfig.push({
                            text: arr[i],
                            isLeaf: false,
                            singleClickExpand: true,
                            expanded: false,
                            children: l.reverse()
                        });
                    }
                }
                treeConfig.push(treeConfig.shift());
                // create the tree with the configuration from above
                tree = new Ext.tree.TreePanel({
                    id: "tree",
                    border: false,
                    region: "center",
                    split: true,
                    autoScroll: true,
                    root: {
                        text: 'Ext JS',
                        children: Ext.decode(new OpenLayers.Format.JSON().write(treeConfig.reverse(), true)),
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
                                var id = e.id.split('.').join('-'), load = function () {
                                    if (e.leaf === true && e.parentNode.id !== "baselayers") {
                                        window.parent.onEditWMSClasses(e.id);
                                    }
                                };
                                try {
                                    stopEdit();
                                }
                                catch (error) {
                                }
                                if (currentId !== e.id) {
                                    if (window.parent.Ext.getCmp("layerStylePanel").collapsed) {
                                        window.parent.Ext.getCmp("layerStylePanel").expand(false);
                                        load();
                                        window.parent.Ext.getCmp("layerStylePanel").collapse(false);
                                    } else {
                                        load();
                                    }
                                }
                                if (e.leaf === true && e.parentNode.id !== "baselayers") {
                                    Ext.getCmp('editlayerbutton').setDisabled(false);
                                    Ext.getCmp('quickdrawbutton').setDisabled(false);
                                    Ext.getCmp('stylebutton').setDisabled(false);
                                } else {
                                    Ext.getCmp('editlayerbutton').setDisabled(true);
                                    Ext.getCmp('quickdrawbutton').setDisabled(true);
                                    Ext.getCmp('stylebutton').setDisabled(true);
                                }
                                if (typeof filter.win !== "undefined") {
                                    if (typeof  filter.win.hide !== "undefined") {
                                        filter.win.hide();
                                    }
                                    filter.win = false;
                                }
                                $(".leaf-tools").empty();
                                $("#" + id).html("<i class='fa fa-cog' style='cursor:pointer' id='style-" + id + "'></i> <i class='fa fa-arrows-alt' style='cursor:pointer' id='ext-" + id + "'></i>");
                                currentId = e.id;
                                $("#style-" + id).on("click", function () {
                                    window.parent.Ext.getCmp("layerStylePanel").expand(true);
                                });
                                $("#ext-" + id).on("click", function () {
                                    if (metaDataRealKeys[e.id].type === "RASTER") {
                                        window.parent.App.setAlert(App.STATUS_NOTICE, __('You can only zoom to vector layers.'));
                                        return false;
                                    }
                                    Ext.Ajax.request({
                                        url: '/api/v1/extent/' + screenName + '/' + e.id + '/900913',
                                        method: 'get',
                                        headers: {
                                            'Content-Type': 'application/json; charset=utf-8'
                                        },
                                        success: function (response) {
                                            var ext = Ext.decode(response.responseText).extent;
                                            cloud.map.zoomToExtent([ext.xmin, ext.ymin, ext.xmax, ext.ymax]);
                                        },
                                        failure: function (response) {
                                            Ext.MessageBox.show({
                                                title: 'Failure',
                                                msg: __(Ext.decode(response.responseText).message),
                                                buttons: Ext.MessageBox.OK,
                                                width: 400,
                                                height: 300,
                                                icon: Ext.MessageBox.ERROR
                                            });
                                        }
                                    });
                                });
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
                                                border: true,
                                                map: map,
                                                zoom: 5,
                                                split: true,
                                                tbar: mapTools
                                            },
                                            {
                                                region: "south",
                                                id: "attrtable",
                                                title: "Attribute table",
                                                split: true,
                                                frame: false,
                                                border: false,
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
                                collapsible: true,
                                split: true,
                                width: 350,
                                tbar: [{
                                    text: '<i class="fa fa-eye"></i> ' + __('Class wizard'),
                                    id: 'stylebutton',
                                    disabled: true,
                                    handler: function () {
                                        var node = tree.getSelectionModel().getSelectedNode();
                                        window.parent.styleWizardWin(node.id);
                                    }
                                }, '-', {
                                    text: '<i class="fa fa-cloud-upload"></i> ' + __('New layer'),
                                    disabled: (subUser === schema || subUser === false) ? false : true,
                                    handler: function () {
                                        window.parent.onAdd();
                                    }
                                }, '-',
                                    {
                                        text: "<i class='fa fa-edit'></i> " + __("Start edit"),
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
                                    }, '-', {
                                        text: "<i class='fa fa-refresh'></i> " + __("Reload"),
                                        handler: function () {
                                            stopEdit();
                                            reLoadTree();
                                        }
                                    }],
                                items: [
                                    {
                                        xtype: "panel",
                                        border: false,
                                        tbar: wfsTools,
                                        html: "<div class=\"layer-desc\">Click on a layer title to access settings and to edit data. Check the box to see the layer in the map.</div>"
                                    },
                                    new Ext.Panel({
                                        border: false,
                                        id: "treepanel",
                                        style: {
                                            height: (Ext.getBody().getViewSize().height - 120) + "px",
                                            overflow: "auto"
                                        },
                                        collapsible: false

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
                    window.parent.App.setAlert(App.STATUS_NOTICE, __("Layer tree reloaded"));
                }
                var west = Ext.getCmp("treepanel");
                west.remove(tree);
                west.add(tree);
                west.doLayout();
                window.parent.writeFiles();
                // Last we add the restricted area layer.
                extentRestrictLayer = new OpenLayers.Layer.Vector("extentRestrictLayer", {
                    styleMap: new OpenLayers.StyleMap({
                        "default": new OpenLayers.Style({
                            fillColor: "#000000",
                            fillOpacity: 0.0,
                            pointRadius: 5,
                            strokeColor: "#ff0000",
                            strokeWidth: 2,
                            strokeOpacity: 0.7,
                            graphicZIndex: 1
                        })
                    })
                });
                if (window.parent.extentRestricted) {
                    extentRestrictLayer.addFeatures(new OpenLayers.Feature.Vector(OpenLayers.Bounds.fromArray(window.parent.settings.extentrestricts[schema]).toGeometry()));
                }
                map.addLayers([extentRestrictLayer]);
            }
        })
        ;
    };
    wfsTools = [
        new GeoExt.Action({
            control: drawControl,
            text: "<i class='fa fa-pencil'></i> " + __("Draw"),
            id: "editcreatebutton",
            disabled: true,
            enableToggle: true
        }),
        '-',
        {
            text: "<i class='fa fa-cut'></i> " + __("Delete"),
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
        '-',
        {
            text: "<i class='fa fa-save'></i> " + __("Save"),
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
            text: "<i class='fa fa-stop-circle'></i> " + __("Stop editing"),
            disabled: true,
            id: "editstopbutton",
            handler: stopEdit
        },
        '-',
        {
            text: "<i class='fa fa-pencil-square-o'></i> " + __("Quick draw"),
            id: "quickdrawbutton",
            disabled: true,
            handler: function () {
                var node = tree.getSelectionModel().getSelectedNode();
                var id = node.id.split(".");
                var geomField = node.attributes.geomField;
                var type = node.attributes.geomType;
                if (type === "GEOMETRY" || type === "RASTER") {
                    Ext.MessageBox.show({
                        title: 'No geometry type on layer',
                        msg: "The layer has no geometry type or type is GEOMETRY. You can set geom type for the layer in 'Settings' to the right.",
                        buttons: Ext.MessageBox.OK,
                        width: 400,
                        height: 300,
                        icon: Ext.MessageBox.ERROR
                    });
                    return false;
                }
                else {
                    var filter = new OpenLayers.Filter.Comparison({
                        type: OpenLayers.Filter.Comparison.EQUAL_TO,
                        property: "\"dummy\"",
                        value: "-1"
                    });

                    attributeForm.init(id[1], geomField);
                    startWfsEdition(id[1], geomField, filter);
                    attributeForm.form.disable();
                    wfsTools[0].control.activate();
                    Ext.getCmp('editcreatebutton').toggle(true);
                    Ext.iterate(qstore, function (v) {
                        v.reset();
                    });
                    queryWin.hide();
                }
            }
        }
    ];
    mapTools = [{
        text: "<i class='fa fa-arrows-v'></i> " + __("Measure"),
        menu: new Ext.menu.Menu({
            items: [
                {
                    text: __('Distance'),
                    handler: function () {
                        openMeasureWin();
                        measureControls.polygon.deactivate();
                        measureControls.line.activate();
                    }
                },
                {
                    text: __('Area'),
                    handler: function () {
                        openMeasureWin();
                        measureControls.line.deactivate();
                        measureControls.polygon.activate();
                    }
                }

            ]
        })
    },
        {
            text: "<i class='fa fa-search'></i> " + __("Search"),
            handler: function (objRef) {
                if (!searchWin) {
                    searchWin = new Ext.Window({
                        title: __("Find"),
                        layout: 'fit',
                        width: 300,
                        height: 70,
                        plain: true,
                        border: false,
                        closeAction: 'hide',
                        html: '<div style="padding: 5px" id="searchContent"><input style="width: 270px" type="text" id="gAddress" name="gAddress" value="" /></div>',
                        x: 250,
                        y: 35
                    });
                }
                if (typeof(objRef) === "object") {
                    searchWin.show(objRef);
                } else {
                    searchWin.show();
                }//end if object reference was passed
                var input = document.getElementById('gAddress');
                var options = {
                    //bounds: defaultBounds
                    //types: ['establishment']
                };
                var autocomplete = new google.maps.places.Autocomplete(input, options);
                //console.log(autocomplete.getBounds());
                google.maps.event.addListener(autocomplete, 'place_changed', function () {
                    var place = autocomplete.getPlace();
                    var transformPoint = function (lat, lon, s, d) {
                        var p = [];
                        if (typeof Proj4js === "object") {
                            var source = new Proj4js.Proj(s);    //source coordinates will be in Longitude/Latitude
                            var dest = new Proj4js.Proj(d);
                            p = new Proj4js.Point(lat, lon);
                            Proj4js.transform(source, dest, p);
                        }
                        else {
                            p.x = null;
                            p.y = null;
                        }
                        return p;
                    };
                    var p = transformPoint(place.geometry.location.lng(), place.geometry.location.lat(), "EPSG:4326", "EPSG:900913");
                    var point = new OpenLayers.LonLat(p.x, p.y);
                    map.setCenter(point, 17);
                    try {
                        placeMarkers.destroy();
                    } catch (e) {
                    }

                    try {
                        placePopup.destroy();
                    } catch (e) {
                    }

                    placeMarkers = new OpenLayers.Layer.Markers("Markers");
                    map.addLayer(placeMarkers);
                    var size = new OpenLayers.Size(21, 25);
                    var offset = new OpenLayers.Pixel(-(size.w / 2), -size.h);
                    var icon = new OpenLayers.Icon('http://www.openlayers.org/dev/img/marker.png', size, offset);
                    placeMarkers.addMarker(new OpenLayers.Marker(point, icon));
                    placePopup = new OpenLayers.Popup.FramedCloud("place", point, null, "<div id='placeResult' style='z-index:1000;width:200px;height:50px;overflow:auto'>" + place.formatted_address + "</div>", null, true);
                    map.addPopup(placePopup);
                });

            },
            tooltip: "Search with Google Places"
        },
        '-',
        {
            text: "<i class='fa fa-globe'></i> " + __("Save extent"),
            id: "extentbutton",
            disabled: false,
            handler: function () {
                Ext.Ajax.request({
                    url: '/controllers/setting/extent/',
                    method: 'put',
                    params: Ext.util.JSON.encode({
                        data: {
                            schema: schema,
                            extent: cloud.getExtent(),
                            zoom: cloud.getZoom(),
                            center: [cloud.getCenter().x, cloud.getCenter().y]
                        }
                    }),
                    headers: {
                        'Content-Type': 'application/json; charset=utf-8'
                    },
                    success: function (response) {
                        window.parent.App.setAlert(App.STATUS_NOTICE, __(Ext.decode(response.responseText).message));
                    },
                    failure: function (response) {
                        Ext.MessageBox.show({
                            title: 'Failure',
                            msg: __(Ext.decode(response.responseText).message),
                            buttons: Ext.MessageBox.OK,
                            width: 400,
                            height: 300,
                            icon: Ext.MessageBox.ERROR
                        });
                    }
                });
            }
        }, '-',
        {
            text: "<i class='fa fa-unlock-alt'></i> " + __("Lock extent"),
            id: "extentlockbutton",
            enableToggle: true,
            tooltip: __('Lock the map extent for sub-users in Admin and for all users in the public Viewer.'),
            disabled: subUser ? true : false,
            pressed: (window.parent.extentRestricted ? true : false),
            handler: function () {
                window.parent.extentRestricted = this.pressed;
                if (window.parent.extentRestricted) {
                    extentRestrictLayer.addFeatures(new OpenLayers.Feature.Vector(cloud.map.getExtent().toGeometry()));
                }
                else {
                    extentRestrictLayer.destroyFeatures();
                }
                Ext.Ajax.request({
                    url: '/controllers/setting/extentrestrict/',
                    method: 'put',
                    params: Ext.util.JSON.encode({
                        data: {
                            schema: schema,
                            extent: window.parent.extentRestricted ? cloud.getExtent() : null,
                            zoom: window.parent.extentRestricted ? cloud.getZoom() : null
                        }
                    }),
                    headers: {
                        'Content-Type': 'application/json; charset=utf-8'
                    },
                    success: function (response) {
                        window.parent.App.setAlert(App.STATUS_NOTICE, __(Ext.decode(response.responseText).message));
                    },
                    failure: function (response) {
                        Ext.MessageBox.show({
                            title: 'Failure',
                            msg: __(Ext.decode(response.responseText).message),
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
            text: "<i class='fa fa-list'></i> " + __("Attributes"),
            id: "infobutton",
            disabled: true,
            handler: function () {
                attributeForm.win.show();
            }
        },
        '-',
        {
            text: "<i class='fa fa-location-arrow'></i> " + __("Locate me"),
            handler: function () {
                cloud.locate();
            }
        }];
    reLoadTree = function () {
        loadTree();
    };
    var sketchSymbolizers = {
        "Point": {
            pointRadius: 4,
            graphicName: "square",
            fillColor: "white",
            fillOpacity: 1,
            strokeWidth: 1,
            strokeOpacity: 1,
            strokeColor: "#333333"
        },
        "Line": {
            strokeWidth: 3,
            strokeOpacity: 1,
            strokeColor: "#666666",
            strokeDashstyle: "dash"
        },
        "Polygon": {
            strokeWidth: 2,
            strokeOpacity: 1,
            strokeColor: "#666666",
            fillColor: "white",
            fillOpacity: 0.3
        }
    };
    var measureStyle = new OpenLayers.Style();
    measureStyle.addRules([
        new OpenLayers.Rule({symbolizer: sketchSymbolizers})
    ]);
    var measureStyleMap = new OpenLayers.StyleMap({"default": measureStyle});
    measureControls = {
        line: new OpenLayers.Control.Measure(
            OpenLayers.Handler.Path, {
                persist: true,
                geodesic: true,
                immediate: true,
                handlerOptions: {
                    layerOptions: {
                        styleMap: measureStyleMap
                    }
                }
            }
        ),
        polygon: new OpenLayers.Control.Measure(
            OpenLayers.Handler.Polygon, {
                persist: true,
                geodesic: true,
                immediate: true,
                handlerOptions: {
                    layerOptions: {
                        styleMap: measureStyleMap
                    }
                }
            }
        )
    };
    function handleMeasurements(event) {
        var geometry = event.geometry;
        var units = event.units;
        var order = event.order;
        var measure = event.measure;
        var element = document.getElementById('output');
        var out = "";
        if (order === 1) {
            out += __("Measure") + ": " + measure.toFixed(3) + " " + units;
        } else {
            out += __("Measure") + ": " + measure.toFixed(3) + " " + units + "<sup>2</" + "sup>";
        }
        element.innerHTML = out;
    }

    function openMeasureWin(objRef) {
        if (!measureWin) {
            measureWin = new Ext.Window({
                title: __("Measure"),
                layout: 'fit',
                width: 300,
                height: 90,
                plain: true,
                border: false,
                closeAction: 'hide',
                html: '<div style="padding: 5px"><div id="output" style="height: 20px; margin-bottom: 10px"></div><div>' + __("Close this window to disable measure tool") + '</div></div>',
                x: 250,
                y: 35,
                listeners: {
                    hide: {
                        fn: function (el, e) {
                            measureControls.polygon.deactivate();
                            measureControls.line.deactivate();
                        }
                    }
                }
            });
        }
        if (typeof(objRef) === "object") {
            measureWin.show(objRef);
        } else {
            measureWin.show();
        }//end if object reference was passed
    }

    measureControls.line.events.on({
        "measure": handleMeasurements,
        "measurepartial": handleMeasurements
    });
    map.addControl(measureControls.line);

    measureControls.polygon.events.on({
        "measure": handleMeasurements,
        "measurepartial": handleMeasurements
    });
    map.addControl(measureControls.polygon);
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
            window.parent.writeFiles(false, map);
            var l;
            l = window.map.getLayersByName(schema + "." + layerBeingEditing)[0];
            l.clearGrid();
            var n = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
            l.url = l.url.replace(l.url.split("?")[1], "");
            l.url = l.url + "token=" + n;
            setTimeout(function () {
                l.redraw();
            }, 500);
        }
    }
});


