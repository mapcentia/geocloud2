/*
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

/*global Ext:false */
/*global $:false */
/*global jQuery:false */
/*global OpenLayers:false */
/*global GeoExt:false */
/*global mygeocloud_ol:false */
/*global schema:false */
/*global window:false */
/*global document:false */
/*global gc2i18n:false */
/*global subUser:false */
/*global __:false */


"use strict";

/**
 * Configure Ext
 */
Ext.Ajax.disableCaching = false;
Ext.QuickTips.init();
Ext.Container.prototype.bufferResize = false;
Ext.MessageBox.buttonText = {
    ok: "<i class='fa fa-check'></i> " + __("Ok"),
    cancel: "<i class='fa fa-remove'></i> " + __("Cancel"),
    yes: "<i class='fa fa-check'></i> " + __("Yes"),
    no: "<i class='fa fa-remove'></i> " + __("No")
};
Ext.util.Format.comboRenderer = function (combo) {
    return function (value) {
        var record = combo.findRecord(combo.valueField, value);
        return record ? record.get(combo.displayField) : combo.valueNotFoundText;
    }
};

/**
 * Set vars in function scope
 */
var form, store, writeFiles, writeMapCacheFile, clearTileCache, updateLegend, activeLayer, onEditWMSClasses, onAdd,
    resetButtons, changeLayerType, isLoaded = false,
    initExtent = null, App = new Ext.App({}), updatePrivileges, updateWorkflow, settings,
    extentRestricted = false, spinner, styleWizardWin, workflowStore, workflowStoreLoaded = false,
    subUserGroups = {},
    dataStore, dataGrid, tableDataLoaded = false, dataPanel, esPanel, esGrid,
    enableWorkflow = (window.gc2Options.enableWorkflow !== null && typeof window.gc2Options.enableWorkflow[parentdb] !== "undefined" && window.gc2Options.enableWorkflow[parentdb] === true) || (window.gc2Options.enableWorkflow !== null && typeof window.gc2Options.enableWorkflow["*"] !== "undefined" && window.gc2Options.enableWorkflow["*"] === true);

var cloud, gc2, layer, grid, featureStore, map, viewport, drawControl, gridPanel, modifyControl, tree,
    viewerSettings,
    loadTree, reLoadTree, layerBeingEditing, layerBeingEditingGeomField, saveStrategy, searchWin,
    measureWin, privilegesStore,
    placeMarkers, placePopup, measureControls, extentRestrictLayer, addedBaseLayers = [], currentId, mapTools,
    firstLoad = true;

/**
 * Init the app on ready state
 */
$(document).ready(function () {
    var winAdd, winMoreSettings, fieldsForStore = {}, groups, groupsStore, tagStore, subUsers, bl = null, metaData,
        metaDataKeys = [], metaDataKeysTitle = [], metaDataRealKeys = [], extent = null, clicktimer, qstore = [],
        layers = {},
        LayerNodeUI = Ext.extend(GeoExt.tree.LayerNodeUI, new GeoExt.tree.TreeNodeUIEventMixin()),
        queryWin;

    /**
     * Make sync calls
     * TODO Make them async and poll
     */
    $.ajax({
        url: '/controllers/layer/columnswithkey',
        async: false,
        dataType: 'json',
        success: function (data) {
            fieldsForStore = data.forStore;
            fieldsForStore.push({name: "indexed_in_es", type: "bool"});
            fieldsForStore.push({name: "reltype", type: "text"});
        }
    });
    $.ajax({
        url: '/controllers/setting',
        async: false,
        dataType: 'json',
        success: function (data) {
            settings = data.data;
            $("#apikeyholder").html(settings.api_key);
            if (typeof settings.extents !== "undefined") {
                if (settings.extents[schema] !== undefined) {
                    initExtent = settings.extents[schema];
                }
            }
            if (typeof settings.extentrestricts !== "undefined") {
                if (settings.extentrestricts[schema] !== undefined && settings.extentrestricts[schema] !== null) {
                    extentRestricted = true;
                }
            }
            if (typeof settings.userGroups !== "undefined") {
                subUserGroups = settings.userGroups || {};
            }
        }
    });

    if (typeof window.setBaseLayers !== 'object') {
        window.setBaseLayers = [
            {"id": "mapQuestOSM", "name": "MapQuset OSM"},
            {"id": "osm", "name": "OSM"}
        ];
    }

    cloud = new mygeocloud_ol.map(null, parentdb, {
        controls: [
            new OpenLayers.Control.Navigation({}),
            new OpenLayers.Control.Zoom(),
            new OpenLayers.Control.Attribution()
        ],
        restrictedExtent: subUser && settings.extentrestricts ? settings.extentrestricts[schema] : null
    });
    gc2 = new geocloud.map({});

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
            bl = cloud.addBaseLayer(window.setBaseLayers[i].id, window.setBaseLayers[i].db, altId, lName, window.setBaseLayers[i].host);
        }
    }
    if (bl !== null) {
        cloud.setBaseLayer(bl);
    }
    map = gc2.map = cloud.map;

    gc2.on("dblclick", function (e) {
        clicktimer = undefined;
    });
    gc2.on("click", function (e) {
        var layers, count = 0, hit = false, event = new geocloud.clickEvent(e, cloud), distance, db = parentdb;
        if (clicktimer) {
            clearTimeout(clicktimer);
        } else {
            clicktimer = setTimeout(function (e) {
                clicktimer = undefined;
                var coords = event.getCoordinate();
                $.each(qstore, function (index, st) {
                    try {
                        st.reset();
                        gc2.removeGeoJsonStore(st);
                    } catch (e) {

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

                                                            } else {
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
                                } catch (e) {
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
                    } else {
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

    mapTools = [
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
                featureStore.commitChanges();
                saveStrategy.save();
            }
        },
        '-',
        {
            text: "<i class='fa fa-stop-circle'></i> " + __("Stop editing"),
            disabled: true,
            id: "editstopbutton",
            handler: stopEdit
        }, '-',
        {
            text: "<i class='fa fa-list'></i> " + __("Attributes"),
            id: "infobutton",
            disabled: true,
            handler: function () {
                attributeForm.win.show();
            }
        }, '->', {
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
        }, '-',
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
                        x: 660,
                        y: 175
                    });
                }
                if (typeof (objRef) === "object") {
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
                        } else {
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
                        App.setAlert(App.STATUS_NOTICE, __(Ext.decode(response.responseText).message));
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
            pressed: (extentRestricted ? true : false),
            handler: function () {
                extentRestricted = this.pressed;
                if (extentRestricted) {
                    extentRestrictLayer.addFeatures(new OpenLayers.Feature.Vector(cloud.map.getExtent().toGeometry()));
                } else {
                    extentRestrictLayer.destroyFeatures();
                }
                Ext.Ajax.request({
                    url: '/controllers/setting/extentrestrict/',
                    method: 'put',
                    params: Ext.util.JSON.encode({
                        data: {
                            schema: schema,
                            extent: extentRestricted ? cloud.getExtent() : null,
                            zoom: extentRestricted ? cloud.getZoom() : null
                        }
                    }),
                    headers: {
                        'Content-Type': 'application/json; charset=utf-8'
                    },
                    success: function (response) {
                        App.setAlert(App.STATUS_NOTICE, __(Ext.decode(response.responseText).message));
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
        '-',
        {
            text: "<i class='fa fa-location-arrow'></i> " + __("Locate me"),
            handler: function () {
                cloud.locate();
            }
        }];

    /**
     *
     * @type {Ext.data.JsonReader}
     */
    var writer = new Ext.data.JsonWriter({
        writeAllFields: false,
        encode: false
    });

    /**
     *
     * @type {Ext.data.JsonReader}
     */
    var reader = new Ext.data.JsonReader({
        successProperty: 'success',
        idProperty: '_key_',
        root: 'data',
        messageProperty: 'message'
    }, fieldsForStore);

    /**
     *
     * @param store
     * @param action
     * @param result
     * @param transaction
     * @param rs
     */
    var onWrite = function (store, action, result, transaction, rs) {
        if (transaction.success) {
            groupsStore.load();
            writeFiles();
        }
    };

    /**
     *
     * @type {Ext.data.HttpProxy}
     */
    var proxy = new Ext.data.HttpProxy({
        restful: true,
        type: 'json',
        api: {
            read: '/controllers/layer/records',
            update: '/controllers/layer/records',
            destroy: '/controllers/table/records'
        },
        listeners: {
            write: onWrite,
            load: function (store, records, data, the) {
                metaData = records.reader.jsonData;
                for (var i = 0; i < metaData.data.length; i++) {
                    metaDataKeys[metaData.data[i].f_table_name] = metaData.data[i];
                    if (!metaData.data[i].f_table_title) {
                        metaData.data[i].f_table_title = metaData.data[i].f_table_name;
                    }
                    metaDataKeysTitle[metaData.data[i].f_table_title] = metaData.data[i];
                }
                if (firstLoad) {
                    firstLoad = false;
                    loadTree(records.reader.jsonData);
                }
            },
            exception: function (proxy, type, action, options, response, arg) {
                if (response.status !== 200) {
                    Ext.MessageBox.show({
                        title: __('Failure'),
                        msg: __(Ext.decode(response.responseText).message),
                        buttons: Ext.MessageBox.OK,
                        width: 300,
                        height: 300
                    });
                    store.reload();
                }
            }
        }
    });

    /**
     *
     * @type {Ext.data.Store}
     */
    store = new Ext.data.Store({
        writer: writer,
        reader: reader,
        proxy: proxy,
        autoSave: true
    });

    /**
     *
     * @type {Ext.data.JsonStore}
     */
    workflowStore = new Ext.data.JsonStore({
        url: '/controllers/workflow',
        autoDestroy: true,
        root: 'data',
        idProperty: 'id',
        fields: [
            {"name": "f_table_name", "type": "string"},
            {"name": "f_schema_name", "type": "string"},
            {"name": "gid", "type": "number"},
            {"name": "status", "type": "integer"},
            {"name": "status_text", "type": "string"},
            {"name": "gc2_user", "type": "string"},
            {"name": "roles", "type": "string"},
            {"name": "workflow", "type": "string"},
            {"name": "author", "type": "string"},
            {"name": "reviewer", "type": "string"},
            {"name": "publisher", "type": "string"},
            {"name": "version_gid", "type": "number"},
            {"name": "operation", "type": "string"},
            {"name": "created", "type": "date"}
        ],
        listeners: {
            load: function (store, records) {
                var _1, _2, _3, markup = [
                    '<table>' +
                    '<tr class="x-grid3-row"><td><b>' + __('Drafted') + ':</b></td><td  width="50">{_1}</td></tr>' +
                    '<tr class="x-grid3-row"><td><b>' + __('Reviewed') + ':</b></td><td  width="50">{_2}</td></tr>' +
                    '<tr class="x-grid3-row"><td><b>' + __('Published') + ':</b></td><td  width="50">{_3}</td></tr>' +
                    '</table>'
                ], template;
                template = new Ext.Template(markup);
                _1 = _2 = _3 = 0;
                Ext.each(records, function (v) {

                        if (v.json.status === 1) {
                            _1 = _1 + 1;
                        }
                        if (v.json.status === 2) {
                            _2 = _2 + 1;
                        }
                        if (v.json.status === 3) {
                            _3 = _3 + 1;
                        }
                    }
                );
                template.overwrite(Ext.getCmp('workflow_footer').body, {_1: _1, _2: _2, _3: _3});
            }
        }
    });

    /**
     *
     * @type {Ext.data.Store}
     */
    groupsStore = new Ext.data.Store({
        reader: new Ext.data.JsonReader({
            successProperty: 'success',
            root: 'data'
        }, [
            {
                "name": "group"
            }
        ]),
        url: '/controllers/layer/groups'
    });

    /**
     *
     * @type {Ext.data.Store}
     */
    tagStore = new Ext.data.Store({
        reader: new Ext.data.JsonReader({
            successProperty: 'success',
            root: 'data'
        }, [
            {
                "name": "tag"
            }
        ]),
        url: '/controllers/layer/tags'
    });

    /**
     *
     * @type {Ext.data.Store}
     */
    var schemasStore = new Ext.data.Store({
        reader: new Ext.data.JsonReader({
            successProperty: 'success',
            root: 'data'
        }, [
            {
                "name": "schema"
            }
        ]),
        url: '/controllers/database/schemas'
    });

    /**
     * Main layer grid in Database tabe
     * @type {Ext.grid.EditorGridPanel}
     */
    var grid = new Ext.grid.EditorGridPanel({
        //plugins: [editor],
        store: store,
        viewConfig: {
            forceFit: true,
            stripeRows: true,
            getRowClass: function (record) {
                /*if (record.json.isview) {
                 return 'isview';
                 } else if (record.json.ismatview) {
                 return 'ismatview';
                 } else if (record.json.isforeign) {
                 return 'isforeign';

                 }*/
            }
        },
        height: (Ext.getBody().getViewSize().height / 2),
        split: true,
        region: 'north',
        frame: false,
        border: false,
        sm: new Ext.grid.RowSelectionModel({
            singleSelect: false
        }),
        cm: new Ext.grid.ColumnModel({
            defaults: {
                sortable: true,
                editor: {
                    xtype: "textfield"
                },
                menuDisabled: true
            },
            columns: [
                {
                    header: __("Relation"),
                    dataIndex: "reltype",
                    tooltip: "T) Table<br>V) View<br>MV) Materialized view <br>FT) Foreign table",
                    sortable: true,
                    editable: false,
                    width: 35,
                    renderer: function (v, p) {
                        var c;
                        c = v === "v" ? "#a6cee3" :
                            v === "mv" ? "#b2df8a" :
                                v === "ft" ? "#fb9a99" : "#fdbf6f";
                        return "<i style='color: " + c + "' class='fa fa-circle' aria-hidden='true'></i> " + v.toUpperCase() + "";
                    }
                },
                {
                    header: __("Type"),
                    dataIndex: "type",
                    sortable: true,
                    editable: false,
                    tooltip: "This can't be changed",
                    width: 70,
                    renderer: function (v, p) {
                        return v || __('No geometry');
                    }
                },
                {
                    header: __("Name"),
                    dataIndex: "f_table_name",
                    sortable: true,
                    editable: false,
                    tooltip: "This can't be changed",
                    width: 70,
                    renderer: function (v, p) {
                        return v;
                    }
                },

                {
                    header: __("Title"),
                    dataIndex: "f_table_title",
                    sortable: true,
                    //width: 150
                },
                {
                    id: "desc",
                    header: __("Description"),
                    dataIndex: "f_table_abstract",
                    sortable: true,
                    editable: true,
                    tooltip: ""

                },
                {
                    id: "note",
                    header: __("Note"),
                    dataIndex: "note",
                    sortable: true,
                    editable: true,
                    tooltip: ""

                },
                {
                    header: __("Group"),
                    dataIndex: 'layergroup',
                    sortable: true,
                    editable: true,
                    width: 60,
                    editor: {
                        xtype: 'combo',
                        mode: 'local',
                        triggerAction: 'all',
                        forceSelection: false,
                        displayField: 'group',
                        valueField: 'group',
                        allowBlank: true,
                        store: groupsStore
                    }
                },
                {
                    header: (window.gc2Options.extraLayerPropertyName !== null && window.gc2Options.extraLayerPropertyName[parentdb]) ? window.gc2Options.extraLayerPropertyName[parentdb] : "Extra",
                    dataIndex: "extra",
                    sortable: true,
                    width: 60,
                    hidden: (window.gc2Options.showExtraLayerProperty !== null && window.gc2Options.showExtraLayerProperty[parentdb] === true) ? false : true

                },
                {
                    xtype: 'checkcolumn',
                    header: __("OWS"),
                    dataIndex: 'enableows',
                    width: 30
                },
                {
                    header: __("Sort id"),
                    dataIndex: 'sort_id',
                    sortable: true,
                    editable: true,
                    width: 30,
                    editor: new Ext.form.NumberField({
                        decimalPrecision: 0,
                        decimalSeparator: '?'// Some strange char nobody is using
                    })
                },
                {
                    header: __("Authentication"),
                    dataIndex: 'authentication',
                    width: 40,
                    tooltip: __('When accessing your layer from external clients, which level of authentication do you want?'),
                    editor: {
                        xtype: 'combo',
                        store: new Ext.data.ArrayStore({
                            fields: ['abbr', 'action'],
                            data: [
                                ['Write', 'Write'],
                                ['Read/write', 'Read/write'],
                                ['None', 'None']
                            ]
                        }),
                        displayField: 'action',
                        valueField: 'abbr',
                        mode: 'local',
                        typeAhead: false,
                        editable: false,
                        triggerAction: 'all'
                    }
                },
                {
                    xtype: 'checkcolumn',
                    header: __("Editable"),
                    dataIndex: 'editable',
                    width: 30
                },
                {
                    xtype: 'checkcolumn',
                    header: __("Skip conflict"),
                    dataIndex: 'skipconflict',
                    hidden: (window.gc2Options.showConflictOptions !== null && window.gc2Options.showConflictOptions[parentdb] === true) ? false : true,
                    width: 40
                }
            ]
        }),
        tbar: [
            {
                text: '<i class="fa fa-user"></i> ' + __('Privileges'),
                id: 'privileges-btn',
                handler: function (btn, ev) {
                    var records = grid.getSelectionModel().getSelections();
                    if (records.length === 0) {
                        App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
                        return false;
                    }
                    privilegesStore = new Ext.data.Store({
                        writer: new Ext.data.JsonWriter({
                            writeAllFields: false,
                            encode: false
                        }),
                        reader: new Ext.data.JsonReader(
                            {
                                successProperty: 'success',
                                idProperty: 'subuser',
                                root: 'data',
                                messageProperty: 'message'
                            },
                            [
                                {
                                    name: "subuser"
                                },
                                {
                                    name: "privileges"
                                },
                                {
                                    name: "group"
                                }
                            ]
                        ),
                        proxy: new Ext.data.HttpProxy({
                            restful: true,
                            type: 'json',
                            api: {
                                read: '/controllers/layer/privileges/' + records[0].get("_key_")
                            },
                            listeners: {
                                exception: function (proxy, type, action, options, response, arg) {
                                    if (response.status !== 200) {
                                        Ext.MessageBox.show({
                                            title: __("Failure"),
                                            msg: __(Ext.decode(response.responseText).message),
                                            buttons: Ext.MessageBox.OK,
                                            width: 300,
                                            height: 300
                                        });
                                        privilgesWin.close();
                                    }
                                }
                            }
                        }),
                        autoSave: true
                    });
                    privilegesStore.load();
                    var privilgesWin = new Ext.Window({
                        title: '<i class="fa fa-user"></i> ' + __("Grant privileges to sub-users on") + " '" + records[0].get("f_table_name") + "'",
                        modal: true,
                        width: 600,
                        height: 330,
                        initCenter: true,
                        closeAction: 'hide',
                        border: false,
                        layout: 'border',
                        items: [
                            new Ext.Panel({
                                height: 500,
                                region: "center",
                                layout: 'border',
                                border: false,
                                items: [
                                    new Ext.grid.EditorGridPanel({
                                        store: privilegesStore,
                                        viewConfig: {
                                            forceFit: true
                                        },
                                        region: 'center',
                                        frame: false,
                                        border: false,
                                        sm: new Ext.grid.RowSelectionModel({
                                            singleSelect: true
                                        }),
                                        cm: new Ext.grid.ColumnModel({
                                            defaults: {
                                                sortable: true
                                            },
                                            columns: [
                                                {
                                                    header: __('Sub-user'),
                                                    dataIndex: 'subuser',
                                                    editable: false,
                                                    width: 50
                                                },
                                                {
                                                    header: __('Privileges'),
                                                    dataIndex: 'privileges',
                                                    sortable: false,
                                                    renderer: function (val, cell, record, rowIndex, colIndex, store) {
                                                        var _key_ = records[0].get("_key_"), disabled;
                                                        if (!record.data.group) {
                                                            disabled = "";
                                                        } else {
                                                            disabled = "disabled";
                                                        }
                                                        var retval =
                                                            '<input ' + disabled + ' data-key="' + _key_ + '" data-subuser="' + record.data.subuser + '" onclick="updatePrivileges(this.getAttribute(\'data-subuser\'),this.getAttribute(\'data-key\'),this.value)" type="radio" value="none" name="' + rowIndex + '"' + ((val === 'none') ? ' checked="checked"' : '') + '>&nbsp;' + __('None') + '&nbsp;&nbsp;&nbsp;' +
                                                            '<input ' + disabled + ' data-key="' + _key_ + '" data-subuser="' + record.data.subuser + '" onclick="updatePrivileges(this.getAttribute(\'data-subuser\'),this.getAttribute(\'data-key\'),this.value)" type="radio" value="read" name="' + rowIndex + '"' + ((val === 'read') ? ' checked="checked"' : '') + '>&nbsp;' + __('Only read') + '&nbsp;&nbsp;&nbsp;' +
                                                            '<input ' + disabled + ' data-key="' + _key_ + '" data-subuser="' + record.data.subuser + '" onclick="updatePrivileges(this.getAttribute(\'data-subuser\'),this.getAttribute(\'data-key\'),this.value)" type="radio" value="write" name="' + rowIndex + '"' + ((val === 'write') ? ' checked="checked"' : '') + '>&nbsp;' + __('Read and write') + '&nbsp;&nbsp;&nbsp;' +
                                                            '<input ' + disabled + ' data-key="' + _key_ + '" data-subuser="' + record.data.subuser + '" onclick="updatePrivileges(this.getAttribute(\'data-subuser\'),this.getAttribute(\'data-key\'),this.value)" type="radio" value="all" name="' + rowIndex + '"' + ((val === 'all') ? ' checked="checked"' : '') + '>&nbsp;' + __('All') + '&nbsp;&nbsp;&nbsp;'
                                                        ;
                                                        return retval;
                                                    }
                                                },
                                                {
                                                    header: __('Inherit privileges from'),
                                                    dataIndex: 'group',
                                                    editable: false,
                                                    width: 50,
                                                    renderer: function (val, cell, record, rowIndex, colIndex, store) {
                                                        if (record.data.group) {
                                                            return record.data.group;
                                                        }
                                                    }
                                                }
                                            ]
                                        })
                                    }),
                                    new Ext.Panel({
                                            border: false,
                                            region: "south",
                                            bodyStyle: {
                                                padding: '7px'
                                            },
                                            html: "<ul>" +
                                                "<li>" + "<b>" + __("None") + "</b>: " + __("The layer doesn't exist for the sub-user.") + "</li>" +
                                                "<li>" + "<b>" + __("Only read") + "</b>: " + __("The sub-user can see and query the layer.") + "</li>" +
                                                "<li>" + "<b>" + __("Read and write") + "</b>: " + __("The sub-user can edit the layer.") + "</li>" +
                                                "<li>" + "<b>" + __("All") + "</b>: " + __("The sub-user change properties like style and alter table structure.") + "</li>" +
                                                "<ul>" +
                                                "<br><p>" +
                                                __("If a sub-user is set to inherit the privileges of another sub-user, you can't change the privileges of the sub-user.") +
                                                "</p>" +
                                                "<br><p>" +
                                                __("The privileges are granted for both Admin and external services like WMS and WFS.") +
                                                "</p>"
                                        }
                                    )
                                ]

                            })
                        ]
                    }).show();
                },
                disabled: true
            },
            {
                text: '<i class="fa fa-users"></i> ' + __('Workflow'),
                id: 'workflow-btn',
                handler: function (btn, ev) {
                    var records = grid.getSelectionModel().getSelections(), workflowWin;
                    if (records.length === 0) {
                        App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
                        return false;
                    }
                    var workflowStore = new Ext.data.Store({
                        writer: new Ext.data.JsonWriter({
                            writeAllFields: false,
                            encode: false
                        }),
                        reader: new Ext.data.JsonReader(
                            {
                                successProperty: 'success',
                                idProperty: 'subuser',
                                root: 'data',
                                messageProperty: 'message'
                            },
                            [
                                {
                                    name: "subuser"
                                },
                                {
                                    name: "roles"
                                }
                            ]
                        ),
                        proxy: new Ext.data.HttpProxy({
                            restful: true,
                            type: 'json',
                            api: {
                                read: '/controllers/layer/roles/' + records[0].get("_key_")
                            },
                            listeners: {
                                exception: function (proxy, type, action, options, response, arg) {
                                    if (response.status !== 200) {
                                        workflowWin.close();
                                        Ext.MessageBox.show({
                                            title: __("Failure"),
                                            msg: __(Ext.decode(response.responseText).message),
                                            buttons: Ext.MessageBox.OK,
                                            width: 300,
                                            height: 300
                                        });
                                    }
                                }
                            }
                        }),
                        autoSave: true
                    });

                    Ext.Ajax.request({
                        url: '/controllers/table/checkcolumn/' + records[0].get("f_table_schema") + "." + records[0].get("f_table_name") + "/gc2_version_gid",
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json; charset=utf-8'
                        },
                        success: function (response) {
                            var r = Ext.decode(response.responseText);
                            if (!r.exists) {
                                Ext.MessageBox.show({
                                    title: 'Failure',
                                    msg: __("The table must be versioned."),
                                    buttons: Ext.MessageBox.OK,
                                    icon: Ext.MessageBox.ERROR
                                });
                                return false;
                            } else {
                                Ext.Ajax.request({
                                    url: '/controllers/table/checkcolumn/' + records[0].get("f_table_schema") + "." + records[0].get("f_table_name") + "/gc2_status",
                                    method: 'GET',
                                    headers: {
                                        'Content-Type': 'application/json; charset=utf-8'
                                    },
                                    success: function (response) {
                                        var r = Ext.decode(response.responseText), go;
                                        go = function () {
                                            workflowWin = new Ext.Window({
                                                title: '<i class="fa fa-users"></i> ' + __("Apply role to sub-users on") + " '" + records[0].get("f_table_name") + "'",
                                                modal: true,
                                                width: 500,
                                                height: 330,
                                                initCenter: true,
                                                closeAction: 'hide',
                                                border: false,
                                                layout: 'border',
                                                items: [
                                                    new Ext.Panel({
                                                        height: 200,
                                                        border: false,
                                                        region: "center",
                                                        items: [
                                                            new Ext.grid.EditorGridPanel({
                                                                store: workflowStore,
                                                                viewConfig: {
                                                                    forceFit: true
                                                                },
                                                                height: 200,
                                                                region: 'center',
                                                                frame: false,
                                                                border: false,
                                                                sm: new Ext.grid.RowSelectionModel({
                                                                    singleSelect: true
                                                                }),
                                                                cm: new Ext.grid.ColumnModel({
                                                                    defaults: {
                                                                        sortable: true
                                                                    },
                                                                    columns: [
                                                                        {
                                                                            header: __('Sub-user'),
                                                                            dataIndex: 'subuser',
                                                                            editable: false,
                                                                            width: 50
                                                                        },
                                                                        {
                                                                            header: __('Role'),
                                                                            dataIndex: 'roles',
                                                                            sortable: false,
                                                                            renderer: function (val, cell, record, rowIndex, colIndex, store) {
                                                                                var _key_ = records[0].get("_key_");
                                                                                var retval =
                                                                                    '<input data-key="' + _key_ + '" data-subuser="' + record.data.subuser + '" onclick="updateWorkflow(this.getAttribute(\'data-subuser\'),this.getAttribute(\'data-key\'),this.value)" type="radio" value="none" name="' + rowIndex + '"' + ((val === 'none') ? ' checked="checked"' : '') + '>&nbsp;' + __('None') + '&nbsp;&nbsp;&nbsp;' +
                                                                                    '<input data-key="' + _key_ + '" data-subuser="' + record.data.subuser + '" onclick="updateWorkflow(this.getAttribute(\'data-subuser\'),this.getAttribute(\'data-key\'),this.value)" type="radio" value="author" name="' + rowIndex + '"' + ((val === 'author') ? ' checked="checked"' : '') + '>&nbsp;' + __('Author') + '&nbsp;&nbsp;&nbsp;' +
                                                                                    '<input data-key="' + _key_ + '" data-subuser="' + record.data.subuser + '" onclick="updateWorkflow(this.getAttribute(\'data-subuser\'),this.getAttribute(\'data-key\'),this.value)" type="radio" value="reviewer" name="' + rowIndex + '"' + ((val === 'reviewer') ? ' checked="checked"' : '') + '>&nbsp;' + __('Reviewer') + '&nbsp;&nbsp;&nbsp;' +
                                                                                    '<input data-key="' + _key_ + '" data-subuser="' + record.data.subuser + '" onclick="updateWorkflow(this.getAttribute(\'data-subuser\'),this.getAttribute(\'data-key\'),this.value)" type="radio" value="publisher" name="' + rowIndex + '"' + ((val === 'publisher') ? ' checked="checked"' : '') + '>&nbsp;' + __('Publisher') + '&nbsp;&nbsp;&nbsp;'
                                                                                ;
                                                                                return retval;
                                                                            }
                                                                        }
                                                                    ]
                                                                })
                                                            }),
                                                            new Ext.Panel({
                                                                    height: 110,
                                                                    border: false,
                                                                    region: "south",
                                                                    bodyStyle: {
                                                                        background: '#777',
                                                                        color: '#fff',
                                                                        padding: '7px'
                                                                    },
                                                                    html: "<ul>" +
                                                                        "<li>" + "<b>" + __("None") + "</b>: " + __("The layer doesn't exist for the sub-user.") + "</li>" +
                                                                        "<li>" + "<b>" + __("Only read") + "</b>: " + __("The sub-user can see and query the layer.") + "</li>" +
                                                                        "<li>" + "<b>" + __("Read and write") + "</b>: " + __("The sub-user can edit the layer.") + "</li>" +
                                                                        "<ul>" +
                                                                        "<br><p>" +
                                                                        __("The privileges are granted for both Admin and external services like WMS and WFS.") +
                                                                        "</p>"
                                                                }
                                                            )
                                                        ]

                                                    })
                                                ]
                                            }).show();
                                            workflowStore.load();
                                        };
                                        if (!r.exists) {
                                            Ext.MessageBox.confirm(__('Confirm'), __("You are about to .....") + " '" + records[0].get("f_table_name") + "'. " + __("Are you sure?"), function (btn) {
                                                if (btn === "yes") {
                                                    Ext.Ajax.request({
                                                        url: '/controllers/table/workflow/' + records[0].get("f_table_schema") + "." + records[0].get("f_table_name"),
                                                        method: 'PUT',
                                                        headers: {
                                                            'Content-Type': 'application/json; charset=utf-8'
                                                        },
                                                        success: function () {
                                                            tableStructure.grid.getStore().reload();
                                                            go();
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
                                                } else {
                                                    return false;

                                                }
                                            });
                                        } else {
                                            go();
                                        }
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

                        }
                    });


                },
                disabled: true,
                hidden: !enableWorkflow
            },
            {
                text: '<i class="fa fa-cogs"></i> ' + __('Advanced'),
                handler: function (btn, ev) {
                    var record = grid.getSelectionModel().getSelected();
                    if (!record) {
                        App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
                        return false;
                    }
                    var r = record;
                    winMoreSettings = new Ext.Window({
                        title: '<i class="fa fa-cogs"></i> ' + __("Advanced settings on") + " '" + record.get("f_table_name") + "'",
                        modal: true,
                        layout: 'fit',
                        width: 450,
                        height: 520,
                        closeAction: 'close',
                        plain: true,
                        border: false,
                        items: [new Ext.Panel({
                            frame: false,
                            border: false,
                            layout: 'border',
                            items: [new Ext.FormPanel({
                                labelWidth: 100,
                                // label settings here cascade unless overridden
                                frame: false,
                                border: false,
                                region: 'center',
                                viewConfig: {
                                    forceFit: true
                                },
                                id: "detailform",
                                bodyStyle: 'padding: 10px 10px 0 10px;',

                                items: [
                                    {
                                        xtype: 'fieldset',
                                        title: __('Settings'),
                                        defaults: {
                                            anchor: '100%'
                                        },
                                        items: [
                                            {
                                                name: '_key_',
                                                xtype: 'hidden',
                                                value: r.data._key_
                                            },
                                            {
                                                xtype: 'textfield',
                                                fieldLabel: __('Meta data URL'),
                                                name: 'meta_url',
                                                value: r.data.meta_url
                                            },
                                            {
                                                xtype: 'textfield',
                                                fieldLabel: __('WMS source'),
                                                name: 'wmssource',
                                                value: r.data.wmssource
                                            },
                                            {
                                                xtype: 'textfield',
                                                fieldLabel: __('WMS EPSGs'),
                                                name: 'wmsclientepsgs',
                                                value: r.data.wmsclientepsgs
                                            },
                                            {
                                                xtype: 'combo',
                                                store: new Ext.data.ArrayStore({
                                                    fields: ['name', 'value'],
                                                    data: [
                                                        ['true', true],
                                                        ['false', false]
                                                    ]
                                                }),
                                                displayField: 'name',
                                                valueField: 'value',
                                                mode: 'local',
                                                typeAhead: false,
                                                editable: false,
                                                triggerAction: 'all',
                                                name: 'not_querable',
                                                fieldLabel: __('Not queryable'),
                                                value: r.data.not_querable
                                            },
                                            {
                                                xtype: 'combo',
                                                store: new Ext.data.ArrayStore({
                                                    fields: ['name', 'value'],
                                                    data: [
                                                        ['true', true],
                                                        ['false', false]
                                                    ]
                                                }),
                                                displayField: 'name',
                                                valueField: 'value',
                                                mode: 'local',
                                                typeAhead: false,
                                                editable: false,
                                                triggerAction: 'all',
                                                name: 'baselayer',
                                                fieldLabel: 'Is baselayer',
                                                value: r.data.baselayer
                                            },
                                            {
                                                xtype: 'textfield',
                                                fieldLabel: __('SQL where clause'),
                                                name: 'filter',
                                                value: r.data.filter
                                            },
                                            {
                                                xtype: 'textfield',
                                                fieldLabel: __('File source'),
                                                name: 'bitmapsource',
                                                value: r.data.bitmapsource
                                            },
                                            {
                                                xtype: 'combo',
                                                store: new Ext.data.ArrayStore({
                                                    fields: ['name', 'value'],
                                                    data: [
                                                        ['true', true],
                                                        ['false', false]
                                                    ]
                                                }),
                                                displayField: 'name',
                                                valueField: 'value',
                                                mode: 'local',
                                                typeAhead: false,
                                                editable: false,
                                                triggerAction: 'all',
                                                name: 'enablesqlfilter',
                                                fieldLabel: 'Enable sql filtering in Viewer',
                                                value: r.data.enablesqlfilter
                                            },
                                            {
                                                xtype: 'textfield',
                                                fieldLabel: __('ES trigger table'),
                                                name: 'triggertable',
                                                value: r.data.triggertable
                                            },
                                            {
                                                xtype: 'textfield',
                                                fieldLabel: __('Feature id'),
                                                name: 'featureid',
                                                value: r.data.featureid
                                            },
                                            {
                                                xtype: 'textarea',
                                                height: 100,
                                                fieldLabel: __('View definition'),
                                                name: 'viewdefinition',
                                                value: r.json.viewdefinition || r.json.matviewdefinition,
                                                disabled: true
                                            }]
                                    }
                                ],
                                buttons: [
                                    {
                                        text: '<i class="fa fa-check"></i> ' + __('Update'),
                                        handler: function () {
                                            var f = Ext.getCmp('detailform');
                                            if (f.form.isValid()) {
                                                var values = f.form.getValues();

                                                for (var key in values) {
                                                    if (values.hasOwnProperty(key)) {
                                                        values[key] = encodeURIComponent(values[key]);
                                                    }
                                                }

                                                var param = {
                                                    data: values
                                                };
                                                param = Ext.util.JSON.encode(param);
                                                Ext.Ajax.request({
                                                    url: '/controllers/layer/records/_key_',
                                                    method: 'put',
                                                    headers: {
                                                        'Content-Type': 'application/json; charset=utf-8'
                                                    },
                                                    params: param,
                                                    success: function () {
                                                        //TODO deselect/select
                                                        grid.getSelectionModel().clearSelections();
                                                        store.reload();
                                                        groupsStore.load();
                                                        App.setAlert(App.STATUS_NOTICE, __("Settings updated"));
                                                        winMoreSettings.close();
                                                    },
                                                    failure: function (response) {
                                                        winMoreSettings.close();
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
                                            } else {
                                                var s = '';
                                                Ext.iterate(f.form.getValues(), function (key, value) {
                                                    s += String.format("{0} = {1}<br />", key, value);
                                                }, this);
                                            }
                                        }
                                    }
                                ]
                            })]
                        })]
                    });
                    winMoreSettings.show(this);
                },
                id: 'advanced-btn',
                disabled: true
            },
            {
                text: '<i class="fa fa-lock"></i> ' + __('Services'),
                handler: function (btn, ev) {
                    new Ext.Window({
                        title: '<i class="fa fa-lock"></i> ' + __('Services'),
                        modal: true,
                        width: 850,
                        height: 430,
                        initCenter: true,
                        closeAction: 'hide',
                        border: false,
                        layout: 'border',
                        items: [
                            new Ext.Panel({
                                region: "center",
                                layout: 'border',
                                border: false,

                                items: [
                                    new Ext.Panel({
                                        border: false,
                                        region: "center",
                                        items: [
                                            httpAuth.form,
                                            apiKey.form
                                        ]
                                    }),

                                    new Ext.Panel({
                                        region: "south",
                                        border: false,
                                        bodyStyle: {
                                            padding: '7px'
                                        },
                                        html: __("HTTP Basic auth password and API key are set for the specific (sub) user.")
                                    })
                                ]

                            }), new Ext.Panel({
                                layout: "border",
                                region: "east",
                                width: 600,
                                id: "service-dialog",
                                items: [
                                    new Ext.Panel({
                                        border: false,
                                        region: "center",
                                        defaults: {
                                            bodyStyle: {
                                                padding: '7px'
                                            },
                                            border: false
                                        },
                                        items: [
                                            new Ext.Panel({
                                                contentEl: "wfs-dialog"
                                            }),
                                            new Ext.Panel({
                                                contentEl: "wms-dialog"
                                            }),
                                            new Ext.Panel({
                                                contentEl: "tms-dialog"
                                            }),
                                            new Ext.Panel({
                                                contentEl: "sql-dialog"
                                            }),
                                            new Ext.Panel({
                                                contentEl: "elasticsearch-dialog"
                                            })
                                        ]
                                    })
                                ]
                            })]
                    }).show(this);
                }
            },
            {
                text: '<i class="fa fa-tags"></i> ' + __('Tags'),
                handler: function (btn, ev) {
                    var records = grid.getSelectionModel().getSelections();
                    if (records.length === 0) {
                        App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
                        return false;
                    }
                    var win = new Ext.Window({
                        title: '<i class="fa fa-tags"></i> ' + __("Add tags on") + ' ' + records.length + ' ' + __('table(s)'),
                        modal: true,
                        layout: 'fit',
                        width: 450,
                        height: 220,
                        closeAction: 'close',
                        plain: true,
                        border: false,
                        resizable: false,
                        items: [new Ext.Panel({
                            frame: false,
                            border: false,
                            layout: 'border',
                            items: [
                                {
                                    xtype: "form",
                                    frame: false,
                                    border: false,
                                    region: 'center',
                                    viewConfig: {
                                        forceFit: true
                                    },
                                    id: "tagsform",
                                    layout: "form",
                                    bodyStyle: 'padding: 10px',
                                    defaults: {
                                        anchor: '100%'
                                    },
                                    labelWidth: 60,
                                    html: __("Write tag names into the field 'Tags'. Finish the tag name with an 'Enter' key stroke. If 'Append' is checked, new tags are appended to existing one. Append is only available when multiple layers are selected."),
                                    items: [
                                        new Ext.ux.form.SuperBoxSelect({
                                            fieldLabel: __("Tags"),
                                            allowBlank: true,
                                            msgTarget: 'under',
                                            allowAddNewData: true,
                                            assertValue: null,
                                            addNewDataOnBlur: true,
                                            name: 'tags',
                                            store: tagStore,
                                            displayField: 'tag',
                                            valueField: 'tag',
                                            mode: 'local',
                                            value: (records.length === 1) ? ((records[0].data.tags !== null) ? Ext.decode(records[0].data.tags) : []) : [],
                                            listeners: {
                                                newitem: function (bs, v, f) {
                                                    bs.addNewItem({
                                                        tag: v
                                                    });
                                                }
                                            }
                                        }),
                                        {
                                            xtype: "checkbox",
                                            fieldLabel: __("Append"),
                                            name: "append",
                                            checked: records.length > 1,
                                            disabled: records.length === 1
                                        }
                                    ],
                                    buttons: [
                                        {
                                            text: '<i class="fa fa-check"></i> ' + __('Update'),
                                            handler: function () {

                                                var f = Ext.getCmp('tagsform');
                                                if (f.form.isValid()) {
                                                    var values = f.form.getValues();
                                                    var data = [];
                                                    Ext.iterate(records, function (v) {
                                                        data.push(
                                                            {
                                                                _key_: v.get("_key_"),
                                                                tags: typeof values.tags === "string" ? values.tags === "" ? null : [values.tags] : values.tags
                                                            }
                                                        );
                                                    });
                                                    var param = {
                                                        data: data
                                                    };
                                                    param = Ext.util.JSON.encode(param);
                                                    Ext.Ajax.request({
                                                        url: '/controllers/layer/records/_key_/' + (values.append ? "1" : "0"),
                                                        method: 'put',
                                                        headers: {
                                                            'Content-Type': 'application/json; charset=utf-8'
                                                        },
                                                        params: param,
                                                        success: function () {
                                                            grid.getSelectionModel().clearSelections();
                                                            store.reload();
                                                            tagStore.load();
                                                            App.setAlert(App.STATUS_NOTICE, __("Tags updated"));
                                                            win.close();
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
                                                } else {
                                                    var s = '';
                                                    Ext.iterate(f.form.getValues(), function (key, value) {
                                                        s += String.format("{0} = {1}<br />", key, value);
                                                    }, this);
                                                }
                                            }
                                        }
                                    ]
                                }]
                        })]
                    }).show(this);
                }
            },
            {
                text: '<i class="fa fa-info"></i> ' + __('Meta'),
                handler: function (btn, ev) {
                    var records = grid.getSelectionModel().getSelections(), win;
                    if (records.length === 0) {
                        App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
                        return false;
                    }
                    win = new Ext.Window({
                        title: '<i class="fa fa-info"></i> ' + __("Add meta on") + ' ' + records.length + ' ' + __('table(s)'),
                        modal: true,
                        layout: 'fit',
                        width: 450,
                        height: 500,
                        closeAction: 'close',
                        plain: true,
                        border: false,
                        resizable: true,
                        items: [
                            new Ext.Panel({
                                    frame: false,
                                    border: false,
                                    layout: 'border',
                                    items: [
                                        {
                                            xtype: "form",
                                            frame: false,
                                            border: false,
                                            region: 'center',
                                            autoScroll: true,

                                            viewConfig: {
                                                forceFit: true
                                            },
                                            id: "metaform",
                                            layout: "form",
                                            bodyStyle: 'padding: 10px',
                                            labelWidth: 80,
                                            items: (function () {
                                                var fieldsets = [];
                                                Ext.each(window.gc2Options.metaConfig, function (w) {
                                                    var fields = [];
                                                    fieldsets.push({
                                                        xtype: 'fieldset',
                                                        title: w.fieldsetName,
                                                        checkboxToggle: records.length > 1,
                                                        collapsed: records.length > 1,
                                                        defaults: {
                                                            anchor: '100%'
                                                        },
                                                        listeners: {
                                                            collapse: function (p) {
                                                                p.items.each(function (i) {
                                                                        i.disable();
                                                                    },
                                                                    this);
                                                            },
                                                            expand: function (p) {
                                                                p.items.each(function (i) {
                                                                        i.enable();
                                                                    },
                                                                    this);
                                                            }
                                                        },
                                                        items: (function () {
                                                            Ext.each(w.fields, function (v) {
                                                                switch (v.type) {
                                                                    case "checkboxgroup":
                                                                        var values = ((records.length === 1 && Ext.decode(records[0].data.meta) !== null) ? (Ext.decode(records[0].data.meta)[v.name] || v.default) : '').split(',');
                                                                        var items = [];
                                                                        v.values.map(function (item) {
                                                                            items.push({
                                                                                boxLabel: item.name,
                                                                                name: v.name,
                                                                                inputValue: item.value,
                                                                                checked: (values.indexOf(item.value) > -1)
                                                                            });
                                                                        });

                                                                        fields.push({
                                                                            xtype: 'checkboxgroup',
                                                                            fieldLabel: v.title,
                                                                            name: v.name,
                                                                            vertical: true,
                                                                            columns: 2,
                                                                            allowBlank: false,
                                                                            items: items,
                                                                        });

                                                                        break;
                                                                    case "text":
                                                                        fields.push(
                                                                            {
                                                                                xtype: 'textfield',
                                                                                fieldLabel: v.title,
                                                                                name: v.name,
                                                                                value: (records.length === 1 && Ext.decode(records[0].data.meta) !== null) ? (Ext.decode(records[0].data.meta)[v.name] || v.default) : null
                                                                            }
                                                                        )
                                                                        break;
                                                                    case "textarea":
                                                                        fields.push(
                                                                            {
                                                                                xtype: 'textarea',
                                                                                fieldLabel: v.title,
                                                                                height: 100,
                                                                                name: v.name,
                                                                                value: (records.length === 1 && Ext.decode(records[0].data.meta) !== null) ? (Ext.decode(records[0].data.meta)[v.name] || v.default) : null
                                                                            }
                                                                        )
                                                                        break;
                                                                    case "checkbox":
                                                                        fields.push(
                                                                            {
                                                                                xtype: 'checkbox',
                                                                                fieldLabel: v.title,
                                                                                name: v.name,
                                                                                checked: (records.length === 1 && Ext.decode(records[0].data.meta) !== null) ? ((Ext.decode(records[0].data.meta)[v.name] !== undefined) ? Ext.decode(records[0].data.meta)[v.name] : v.default) : false
                                                                            }
                                                                        )
                                                                        break;
                                                                    case "combo":
                                                                        fields.push(
                                                                            {
                                                                                xtype: 'combo',
                                                                                displayField: 'name',
                                                                                valueField: 'value',
                                                                                mode: 'local',
                                                                                store: new Ext.data.JsonStore({
                                                                                    fields: ['name', 'value'],
                                                                                    data: v.values
                                                                                }),
                                                                                triggerAction: 'all',
                                                                                name: v.name,
                                                                                fieldLabel: v.title,
                                                                                value: (records.length === 1 && Ext.decode(records[0].data.meta) !== null) ? ((Ext.decode(records[0].data.meta)[v.name]) || v.default) : null
                                                                            }
                                                                        )
                                                                        break;
                                                                    case "superboxselect":
                                                                        fields.push(
                                                                            new Ext.ux.form.SuperBoxSelect({
                                                                                allowBlank: true,
                                                                                msgTarget: 'under',
                                                                                allowAddNewData: true,
                                                                                assertValue: null,
                                                                                addNewDataOnBlur: true,
                                                                                name: v.name,
                                                                                store: new Ext.data.ArrayStore({
                                                                                    fields: ['name', 'value'],
                                                                                    data: Ext.decode(records[0].data.meta)[v.name] || []
                                                                                }),
                                                                                displayField: 'tag',
                                                                                valueField: 'tag',
                                                                                mode: 'local',
                                                                                value: (records.length === 1 && Ext.decode(records[0].data.meta) !== null) ? ((Ext.decode(records[0].data.meta)[v.name] !== null) ? Ext.decode(records[0].data.meta)[v.name] : []) : [],
                                                                                listeners: {
                                                                                    newitem: function (bs, v, f) {
                                                                                        bs.addNewItem({
                                                                                            tag: v
                                                                                        });
                                                                                    }
                                                                                }
                                                                            })
                                                                        )
                                                                        break;
                                                                }
                                                            })

                                                            return fields;

                                                        }())

                                                    })


                                                })
                                                return fieldsets;
                                            }()),

                                            buttons: [
                                                {
                                                    text: '<i class="fa fa-check"></i> ' + __('Update'),
                                                    handler: function () {
                                                        var f = Ext.getCmp('metaform');
                                                        if (f.form.isValid()) {
                                                            var values = f.form.getFieldValues();
                                                            // Check for checkboxgroup type and join values to string
                                                            for (var key in values) {
                                                                var value = values[key];
                                                                if (values.hasOwnProperty(key)) {
                                                                    if (typeof value === "object") {
                                                                        console.log(value)
                                                                        var tmp = [];
                                                                        Ext.iterate(value, function (v) {
                                                                            tmp.push(v.inputValue)
                                                                        });
                                                                        values[key] = tmp.join(",");
                                                                    }
                                                                }
                                                            }
                                                            var data = [];
                                                            Ext.iterate(records, function (v) {
                                                                data.push(
                                                                    {
                                                                        _key_: v.get("_key_"),
                                                                        meta: values
                                                                    }
                                                                );
                                                            });
                                                            var param = {
                                                                data: data
                                                            };
                                                            param = Ext.util.JSON.encode(param);
                                                            Ext.Ajax.request({
                                                                url: '/controllers/layer/records/_key_',
                                                                method: 'put',
                                                                headers: {
                                                                    'Content-Type': 'application/json; charset=utf-8'
                                                                },
                                                                params: param,
                                                                success: function () {
                                                                    grid.getSelectionModel().clearSelections();
                                                                    store.reload();
                                                                    App.setAlert(App.STATUS_NOTICE, __("Meta data updated"));
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
                                                        } else {
                                                            var s = '';
                                                            Ext.iterate(f.form.getValues(), function (key, value) {
                                                                s += String.format("{0} = {1}<br />", key, value);
                                                            }, this);
                                                        }
                                                    }
                                                }
                                            ]

                                        }]
                                }
                            )
                        ]
                    }).show(this);
                }
            },
            {
                text: '<i class="fa fa-remove"></i> ' + __('Clear tile cache'),
                disabled: (screenName === schema || subUser === false) ? false : true,
                handler: function () {
                    Ext.MessageBox.confirm(__('Confirm'), __('You are about to delete the tile cache for the whole schema. Are you sure?'), function (btn) {
                        if (btn === "yes") {
                            Ext.Ajax.request({
                                url: '/controllers/tilecache/index/schema/' + schema,
                                method: 'delete',
                                headers: {
                                    'Content-Type': 'application/json; charset=utf-8'
                                },
                                success: function () {
                                    store.reload();
                                    App.setAlert(App.STATUS_OK, __("Tile cache deleted"));
                                    writeMapCacheFile();
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
                        } else {
                            return false;
                        }
                    });
                }
            },
            '->',
            {
                text: '<i class="fa fa-plus-circle"></i> ' + __('New layer'),
                disabled: (screenName === schema || subUser === false) ? false : true,
                handler: function () {
                    onAdd();
                }
            },
            '-',
            {
                text: '<i class="fa fa-arrow-right"></i> ' + __('Move layers'),
                disabled: true,
                id: 'movelayer-btn',
                handler: function (btn, ev) {
                    var records = grid.getSelectionModel().getSelections();
                    if (records.length === 0) {
                        App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
                        return false;
                    }
                    var winMoveTable = new Ext.Window({
                        title: '<i class="fa fa-arrow-right"></i> ' + __("Move") + " " + records.length + " " + __("selected to another schema"),
                        modal: true,
                        layout: 'fit',
                        width: 300,
                        height: 67,
                        closeAction: 'close',
                        plain: true,
                        border: false,
                        items: [
                            {
                                defaults: {
                                    border: false
                                },
                                layout: 'hbox',
                                border: false,
                                items: [
                                    {
                                        xtype: "form",
                                        id: "moveform",
                                        layout: "form",
                                        bodyStyle: 'padding: 10px',
                                        items: [
                                            {
                                                xtype: 'container',
                                                border: false,
                                                items: [
                                                    {
                                                        xtype: "combo",
                                                        store: schemasStore,
                                                        displayField: 'schema',
                                                        editable: false,
                                                        mode: 'local',
                                                        triggerAction: 'all',
                                                        value: schema,
                                                        name: 'schema',
                                                        width: 200
                                                    }
                                                ]
                                            }
                                        ]
                                    },
                                    {
                                        layout: 'form',
                                        bodyStyle: 'padding: 10px',
                                        border: false,
                                        items: [
                                            {
                                                xtype: 'button',
                                                text: 'Move',
                                                handler: function () {
                                                    var f = Ext.getCmp('moveform');
                                                    if (f.form.isValid()) {
                                                        var values = f.form.getValues();
                                                        values.tables = [];
                                                        Ext.iterate(records, function (v) {
                                                            values.tables.push(v.data.f_table_schema + "." + v.get("f_table_name"));
                                                        });

                                                        var param = {
                                                            data: values
                                                        };
                                                        param = Ext.util.JSON.encode(param);
                                                        Ext.Ajax.request({
                                                            url: '/controllers/layer/schema',
                                                            method: 'put',
                                                            headers: {
                                                                'Content-Type': 'application/json; charset=utf-8'
                                                            },
                                                            params: param,
                                                            success: function () {
                                                                store.reload();
                                                                Ext.iterate(records, function (v) {
                                                                    cloud.removeTileLayerByName([
                                                                        [v.data.f_table_schema + "." + v.get("f_table_name")]
                                                                    ]);
                                                                });
                                                                reLoadTree();
                                                                resetButtons();
                                                                winMoveTable.close(this);
                                                                App.setAlert(App.STATUS_OK, "Layers moved");
                                                            },
                                                            failure: function (response) {
                                                                winMoveTable.close(this);
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
                                                    } else {
                                                        var s = '';
                                                        Ext.iterate(f.form.getValues(), function (key, value) {
                                                            s += String.format("{0} = {1}<br />", key, value);
                                                        }, this);
                                                    }
                                                }
                                            }
                                        ]
                                    }
                                ]
                            }
                        ]
                    }).show(this);
                }
            },
            '-',
            {
                text: '<i class="fa fa-pencil"></i> ' + __('Rename layer'),
                disabled: true,
                id: 'renamelayer-btn',
                handler: function () {
                    var record = grid.getSelectionModel().getSelected();
                    if (!record) {
                        App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
                        return false;
                    }
                    var winTableRename = new Ext.Window({
                        title: '<i class="fa fa-pencil"></i> ' + __("Rename table") + " '" + record.data.f_table_name + "'",
                        modal: true,
                        layout: 'fit',
                        width: 270,
                        height: 67,
                        closeAction: 'close',
                        plain: true,
                        border: false,
                        items: [
                            {
                                defaults: {
                                    border: false
                                },
                                layout: 'hbox',
                                border: false,
                                items: [
                                    {
                                        xtype: "form",
                                        id: "tableRenameForm",
                                        layout: "form",
                                        bodyStyle: 'padding: 10px',
                                        items: [
                                            {
                                                xtype: 'container',
                                                border: false,
                                                items: [
                                                    {
                                                        xtype: "textfield",
                                                        name: 'name',
                                                        emptyText: __('New name'),
                                                        allowBlank: false,
                                                        width: 150
                                                    }
                                                ]
                                            }
                                        ]
                                    },
                                    {
                                        layout: 'form',
                                        bodyStyle: 'padding: 10px',
                                        items: [
                                            {
                                                xtype: 'button',
                                                text: __('Rename'),
                                                handler: function () {
                                                    var f = Ext.getCmp('tableRenameForm');
                                                    if (f.form.isValid()) {
                                                        var values = f.form.getValues();
                                                        var param = {
                                                            data: values
                                                        };
                                                        var name = record.data.f_table_schema + "." + record.data.f_table_name;
                                                        param.id = record.id;
                                                        param = Ext.util.JSON.encode(param);
                                                        Ext.Ajax.request({
                                                            url: '/controllers/layer/name/' + record.data.f_table_schema + "." + record.data.f_table_name,
                                                            method: 'put',
                                                            headers: {
                                                                'Content-Type': 'application/json; charset=utf-8'
                                                            },
                                                            params: param,
                                                            success: function () {
                                                                winTableRename.close();
                                                                resetButtons();
                                                                Ext.getCmp('renamelayer-btn').setDisabled(true);
                                                                try {
                                                                    cloud.removeTileLayerByName([
                                                                        [name]
                                                                    ]);
                                                                } catch (e) {
                                                                }
                                                                reLoadTree();
                                                                App.setAlert(App.STATUS_OK, __("layer rename"));
                                                            },
                                                            failure: function (response) {
                                                                winTableRename.close();
                                                                Ext.MessageBox.show({
                                                                    title: __('Failure'),
                                                                    msg: __(Ext.decode(response.responseText).message),
                                                                    buttons: Ext.MessageBox.OK,
                                                                    width: 400,
                                                                    height: 300,
                                                                    icon: Ext.MessageBox.ERROR
                                                                });
                                                            }
                                                        });
                                                    } else {
                                                        var s = '';
                                                        Ext.iterate(f.form.getValues(), function (key, value) {
                                                            s += String.format("{0} = {1}<br />", key, value);
                                                        }, this);
                                                    }
                                                }
                                            }
                                        ]
                                    }
                                ]
                            }
                        ]
                    }).show(this);
                }
            },
            '-',
            {
                text: '<i class="fa fa-cut"></i> ' + __('Delete layers'),
                disabled: true,
                id: 'deletelayer-btn',
                handler: function () {
                    var records = grid.getSelectionModel().getSelections();
                    if (records.length === 0) {
                        App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
                        return false;
                    }
                    Ext.MessageBox.confirm('<i class="fa fa-cut"></i> ' + __('Confirm'), __('Are you sure you want to delete') + ' ' + records.length + ' ' + __('table(s)') + '?', function (btn) {
                        if (btn === "yes") {
                            var tables = [];
                            Ext.iterate(records, function (v) {
                                tables.push(v.get("f_table_schema") + "." + v.get("f_table_name"));
                            });
                            var param = {
                                data: tables
                            };
                            param = Ext.util.JSON.encode(param);
                            Ext.Ajax.request({
                                url: '/controllers/layer/records',
                                method: 'delete',
                                headers: {
                                    'Content-Type': 'application/json; charset=utf-8'
                                },
                                params: param,
                                success: function () {
                                    store.reload();
                                    resetButtons();
                                    App.setAlert(App.STATUS_OK, records.length + " " + __("layers deleted"));
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
                        } else {
                            return false;
                        }
                    });
                }
            },
            '-',
            {
                text: '<i class="fa fa-copy"></i> ' + __('Copy properties'),
                id: 'copy-properties-btn',
                tooltip: __("Copy all properties from another layer"),
                disabled: true,
                handler: function () {
                    var record = grid.getSelectionModel().getSelected();
                    if (!record) {
                        App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
                        return false;
                    }
                    var winCopyMeta = new Ext.Window({
                        title: '<i class="fa fa-copy"></i> ' + __("Copy all properties from another layer"),
                        modal: true,
                        layout: 'fit',
                        width: 580,
                        height: 67,
                        closeAction: 'close',
                        plain: true,
                        border: false,
                        items: [
                            {
                                defaults: {
                                    border: false
                                },
                                border: false,
                                items: [
                                    {
                                        xtype: "form",
                                        id: "copyMetaForm",
                                        layout: 'hbox',
                                        bodyStyle: 'padding: 10px',
                                        items: [
                                            {
                                                xtype: "combo",
                                                store: schemasStore,
                                                displayField: 'schema',
                                                editable: false,
                                                mode: 'local',
                                                triggerAction: 'all',
                                                lazyRender: true,
                                                name: 'schema',
                                                width: 200,
                                                allowBlank: false,
                                                emptyText: __('Schema'),
                                                listeners: {
                                                    'select': function (combo, value, index) {
                                                        Ext.getCmp('copyMetaFormKeys').clearValue();
                                                        (function () {
                                                            Ext.Ajax.request({
                                                                url: '/controllers/layer/records/' + combo.getValue(),
                                                                method: 'GET',
                                                                headers: {
                                                                    'Content-Type': 'application/json; charset=utf-8'
                                                                },
                                                                success: function (response) {
                                                                    Ext.getCmp('copyMetaFormKeys').store.loadData(
                                                                        Ext.decode(response.responseText)
                                                                    );
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
                                                        }());
                                                    }
                                                }
                                            }, {
                                                xtype: "combo",
                                                id: "copyMetaFormKeys",
                                                store: new Ext.data.Store({
                                                    reader: new Ext.data.JsonReader({
                                                        successProperty: 'success',
                                                        root: 'data'
                                                    }, [
                                                        {
                                                            "name": "f_table_name"
                                                        },
                                                        {
                                                            "name": "_key_"
                                                        }
                                                    ]),
                                                    url: '/controllers/layer/groups'
                                                }),
                                                displayField: 'f_table_name',
                                                valueField: '_key_',
                                                editable: false,
                                                mode: 'local',
                                                triggerAction: 'all',
                                                name: 'key',
                                                width: 300,
                                                allowBlank: false,
                                                emptyText: __('Layer')
                                            },
                                            {
                                                layout: 'form',
                                                bodyStyle: 'padding-left: 10px',

                                                items: [
                                                    {
                                                        xtype: 'button',
                                                        text: __('Copy'),

                                                        handler: function () {
                                                            var f = Ext.getCmp('copyMetaForm');
                                                            if (f.form.isValid()) {
                                                                Ext.Ajax.request({
                                                                    url: '/controllers/layer/copymeta/' + record.data._key_ + "/" + Ext.getCmp('copyMetaFormKeys').value,
                                                                    method: 'put',
                                                                    headers: {
                                                                        'Content-Type': 'application/json; charset=utf-8'
                                                                    },
                                                                    success: function () {
                                                                        reLoadTree();
                                                                        App.setAlert(App.STATUS_OK, __("Layer properties copied"));
                                                                    },
                                                                    failure: function (response) {
                                                                        Ext.MessageBox.show({
                                                                            title: __('Failure'),
                                                                            msg: __(Ext.decode(response.responseText).message),
                                                                            buttons: Ext.MessageBox.OK,
                                                                            width: 400,
                                                                            height: 300,
                                                                            icon: Ext.MessageBox.ERROR
                                                                        });
                                                                    }
                                                                });
                                                            } else {
                                                                var s = '';
                                                                Ext.iterate(f.form.getValues(), function (key, value) {
                                                                    s += String.format("{0} = {1}<br />", key, value);
                                                                }, this);
                                                            }
                                                        }
                                                    }
                                                ]
                                            }
                                        ]
                                    }
                                ]
                            }
                        ]
                    }).show(this);
                }
            },

            '-',
            {
                text: '<i class="fa fa-th"></i> ' + __('Schema'),
                disabled: subUser ? true : false,
                menu: new Ext.menu.Menu({
                    items: [
                        {
                            text: __('Rename schema'),
                            handler: function (btn, ev) {
                                var winSchemaRename = new Ext.Window({
                                    title: __("Rename schema") + " '" + schema + "'",
                                    modal: true,
                                    layout: 'fit',
                                    width: 270,
                                    height: 80,
                                    closeAction: 'close',
                                    plain: true,
                                    border: false,
                                    items: [
                                        {
                                            defaults: {
                                                border: false
                                            },
                                            layout: 'hbox',
                                            border: false,
                                            items: [
                                                {
                                                    xtype: "form",
                                                    id: "schemaRenameForm",
                                                    layout: "form",
                                                    bodyStyle: 'padding: 10px',
                                                    items: [
                                                        {
                                                            xtype: 'container',
                                                            items: [
                                                                {
                                                                    xtype: "textfield",
                                                                    name: 'name',
                                                                    emptyText: __('New name'),
                                                                    allowBlank: false,
                                                                    width: 150
                                                                }
                                                            ]
                                                        }
                                                    ]
                                                },
                                                {
                                                    layout: 'form',
                                                    bodyStyle: 'padding: 10px',
                                                    items: [
                                                        {
                                                            xtype: 'button',
                                                            text: __('Rename'),
                                                            handler: function () {
                                                                var f = Ext.getCmp('schemaRenameForm');
                                                                if (f.form.isValid()) {
                                                                    var values = f.form.getValues();
                                                                    var param = {
                                                                        data: values
                                                                    };
                                                                    param = Ext.util.JSON.encode(param);
                                                                    Ext.Ajax.request({
                                                                        url: '/controllers/database/schema',
                                                                        method: 'put',
                                                                        headers: {
                                                                            'Content-Type': 'application/json; charset=utf-8'
                                                                        },
                                                                        params: param,
                                                                        success: function (response) {
                                                                            var data = eval('(' + response.responseText + ')');
                                                                            window.location = "/admin/" + parentdb + "/" + data.data.name;
                                                                        },
                                                                        failure: function (response) {
                                                                            winSchemaRename.close();
                                                                            Ext.MessageBox.show({
                                                                                title: __('Failure'),
                                                                                msg: __(Ext.decode(response.responseText).message),
                                                                                buttons: Ext.MessageBox.OK,
                                                                                width: 400,
                                                                                height: 300,
                                                                                icon: Ext.MessageBox.ERROR
                                                                            });
                                                                        }
                                                                    });
                                                                } else {
                                                                    var s = '';
                                                                    Ext.iterate(f.form.getValues(), function (key, value) {
                                                                        s += String.format("{0} = {1}<br />", key, value);
                                                                    }, this);
                                                                }
                                                            }
                                                        }
                                                    ]
                                                }
                                            ]
                                        }
                                    ]
                                }).show(this);
                            }
                        },
                        {
                            text: __('Delete schema'),
                            handler: function (btn, ev) {
                                Ext.MessageBox.confirm(__('Confirm'), __('Are you sure you want to do that? All layers in the schema will be deleted!'), function (btn) {
                                    if (btn === "yes") {
                                        Ext.Ajax.request({
                                            url: '/controllers/database/schema',
                                            method: 'delete',
                                            headers: {
                                                'Content-Type': 'application/json; charset=utf-8'
                                            },
                                            success: function (response) {
                                                window.location = "/admin/" + parentdb + "/public";
                                            },
                                            failure: function (response) {
                                                Ext.MessageBox.show({
                                                    title: __('Failure'),
                                                    msg: __(Ext.decode(response.responseText).message),
                                                    buttons: Ext.MessageBox.OK,
                                                    width: 400,
                                                    height: 300,
                                                    icon: Ext.MessageBox.ERROR
                                                });
                                            }
                                        });
                                    } else {
                                        return false;
                                    }
                                });
                            }
                        }

                    ]
                })
            },
            new Ext.form.ComboBox({
                id: "schemabox",
                store: schemasStore,
                displayField: 'schema',
                editable: false,
                mode: 'local',
                triggerAction: 'all',
                value: schema,
                width: 135
            }),
            {
                xtype: 'form',
                border: false,
                layout: 'hbox',
                width: 150,
                id: 'schemaform',
                disabled: subUser ? true : false,
                items: [
                    {
                        xtype: 'textfield',
                        flex: 1,
                        name: 'schema',
                        emptyText: __('New schema'),
                        allowBlank: false
                    }
                ]
            },
            {
                text: '<i class="fa fa-plus"></i>',
                tooltip: __('New schema'),
                disabled: subUser ? true : false,
                handler: function () {
                    var f = Ext.getCmp('schemaform');
                    if (f.form.isValid()) {
                        f.getForm().submit({
                            url: '/controllers/database/schemas',
                            submitEmptyText: false,
                            success: function () {
                                schemasStore.reload();
                                App.setAlert(App.STATUS_OK, __("New schema created"));
                            },
                            failure: function (form, action) {
                                Ext.MessageBox.show({
                                    title: 'Failure',
                                    msg: __(Ext.decode(action.response.responseText).message),
                                    buttons: Ext.MessageBox.OK,
                                    width: 400,
                                    height: 300,
                                    icon: Ext.MessageBox.ERROR
                                });
                            }
                        });
                    }
                }
            }
        ],
        listeners: {
            'mouseover': {
                fn: function () {
                },
                scope: this
            }
        }
    });

    /**
     * Creates the upload dialog
     * @param btn
     * @param ev
     */
    onAdd = function (btn, ev) {
        addShape.init();
        var p = new Ext.Panel({
                id: "uploadpanel",
                frame: false,
                border: false,
                layout: 'border',
                items: [new Ext.Panel({
                    border: false,
                    region: "center"
                })]
            }),
            /**
             * Add the vector upload
             */
            addVector = function () {
                addShape.init();
                var c = p.getComponent(0);
                c.remove(0);
                c.add(addShape.form);
                try {
                    c.doLayout();
                } catch (e) {
                }

            },
            addImage = function () {
                addBitmap.init();
                var c = p.getComponent(0);
                c.remove(0);
                c.add(addBitmap.form);
                try {
                    c.doLayout();
                } catch (e) {
                }

            },
            addRaster = function () {
                addRasterFile.init();
                var c = p.getComponent(0);
                c.remove(0);
                c.add(addRasterFile.form);
                try {
                    c.doLayout();
                } catch (e) {
                }
            },
            addQgs = function () {
                addQgis.init();
                var c = p.getComponent(0);
                c.remove(0);
                c.add(addQgis.form);
                try {
                    c.doLayout();
                } catch (e) {
                }
            };

        new Ext.Window({
            title: '<i class="fa fa-plus-circle"></i> ' + __('New layer'),
            layout: 'fit',
            modal: true,
            width: 800,
            height: 350,
            closeAction: 'close',
            resizable: false,
            border: false,
            plain: true,
            items: [p],
            tbar: [
                {
                    text: __('Add vector'),
                    handler: addVector,
                    pressed: true,
                    toggleGroup: "upload"
                },
                '-',
                {
                    text: __('Add raster'),
                    handler: addRaster,
                    toggleGroup: "upload"
                },
                '-',
                {
                    text: __('Add imagery'),
                    handler: addImage,
                    toggleGroup: "upload"
                },
                '-',
                {
                    text: __('Database view'),
                    handler: function () {
                        addView.init();
                        var c = p.getComponent(0);
                        c.remove(0);
                        c.add(addView.form);
                        c.doLayout();
                    },
                    toggleGroup: "upload"
                },
                '-',
                {
                    text: __('OSM'),
                    disabled: (window.gc2Options.osmConfig === null) ? true : false,
                    handler: function () {
                        addOsm.init();
                        var c = p.getComponent(0);
                        c.remove(0);
                        c.add(addOsm.form);
                        c.doLayout();
                    },
                    toggleGroup: "upload"
                },
                '-',
                {
                    text: __('Blank layer'),
                    handler: function () {
                        addScratch.init();
                        var c = p.getComponent(0);
                        c.remove(0);
                        c.add(addScratch.form);
                        c.doLayout();
                    },
                    toggleGroup: "upload"
                },
                '-',
                {
                    text: __('QGIS'),
                    handler: function () {
                        addQgis.init();
                        var c = p.getComponent(0);
                        c.remove(0);
                        c.add(addQgis.form);
                        c.doLayout();
                    },
                    toggleGroup: "upload"
                }
            ]
        }).show(this);
        addVector();
    };

    // TODO Set func as var?
    /**
     *
     */
    function onEdit() {
        var records = grid.getSelectionModel().getSelections(),
            s = Ext.getCmp("structurepanel"),
            detailPanel = Ext.getCmp('detailPanel'),
            detailPanelTemplate = new Ext.Template(['<table border="0">' +
            '<tr class="x-grid3-row"><td class="bottom-info-bar-param"><b>' + __('Srid') + '</b></td><td >{srid}</td><td class="bottom-info-bar-pipe">|</td><td class="bottom-info-bar-param"><b>' + __('Key') + '</b></td><td >{_key_}</td><td class="bottom-info-bar-pipe">|</td><td class="bottom-info-bar-param"><b>' + __('Tags') + '</b></td><td>{tags}</td></tr>' +
            '<tr class="x-grid3-row"><td class="bottom-info-bar-param"><b>' + __('Geom field') + '</b></td><td>{f_geometry_column}</td><td class="bottom-info-bar-pipe">|</td><td class="bottom-info-bar-param"><b>' + __('Dimensions') + '</b></td><td>{coord_dimension}</td><td class="bottom-info-bar-pipe">|</td></td><td class="bottom-info-bar-param"><b>' + __('Guid') + '</b></td><td>{uuid}</td></tr>' +
            '</table>']);
        if (records.length === 1) {
            var dataClone = JSON.parse(JSON.stringify(records[0].data));
            var tagsStr = dataClone.tags;
            var tagsArr = [];
            var tagsPresent
            if (typeof tagsStr === "string") {
                tagsArr = JSON.parse(tagsStr);
            }
            if (tagsArr.length === 0) {
                tagsPresent = __("No tags");
            } else {
                tagsPresent = tagsArr.join(", ");
            }
            dataClone.tags = tagsPresent;
            detailPanelTemplate.overwrite(detailPanel.body, dataClone);
            tableStructure.grid = null;
            Ext.getCmp("tablepanel").activate(0);
            tableStructure.init(records[0], parentdb);
            s.removeAll();
            s.add(tableStructure.grid);
            s.doLayout();
        } else {
            s.removeAll();
            s.doLayout();
        }
    }

    var clearLayerPanel = function () {
        Ext.getCmp("a1").removeAll();
        Ext.getCmp("a2").removeAll();
        Ext.getCmp("a3").removeAll();
        Ext.getCmp("a4").removeAll();
        Ext.getCmp("a5").hide();
        Ext.getCmp("a6").removeAll();
        Ext.getCmp("a8").removeAll();
        Ext.getCmp("a9").removeAll();
        Ext.getCmp("a10").removeAll();
        Ext.getCmp("a11").removeAll();
        Ext.getCmp("a12").removeAll();
        Ext.getCmp("a13").removeAll();
        Ext.getCmp("layerStylePanel").disable();
        Ext.getCmp("classTabs").disable();

    };

    /**
     *
     * @param e
     * @returns {boolean}
     */
    onEditWMSClasses = function (e) {
        var record = null, markup;


        Ext.getCmp("layerStylePanel").enable();
        Ext.getCmp("a5").show();
        Ext.getCmp("classTabs").disable();

        grid.getStore().each(function (rec) {  // for each row
            var row = rec.data; // get record
            if (row._key_ === e) {
                record = row;
            }
        });
        if (!record) {
            App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
            return false;
        }

        var _store = new Ext.data.JsonStore({
            // store config
            autoLoad: true,
            url: '/controllers/tile/index/' + record._key_,
            storeId: 'configStore',
            // reader config
            successProperty: 'success',
            idProperty: 'id',
            root: 'data',
            fields: [
                {
                    name: 'theme_column'
                },
                {
                    name: 'label_column'
                },
                {
                    name: 'opacity'
                },
                {
                    name: 'label_max_scale'
                },
                {
                    name: 'label_min_scale'
                },
                {
                    name: 'cluster'
                },
                {
                    name: 'maxscaledenom'
                },
                {
                    name: 'minscaledenom'
                },
                {
                    name: 'symbolscaledenom'
                },
                {
                    name: 'geotype'
                },
                {
                    name: 'offsite'
                },
                {
                    name: 'label_no_clip',
                    type: 'boolean'

                },
                {
                    name: 'polyline_no_clip',

                },
                {
                    name: 'bands'
                },
                {
                    name: 'meta_size'
                },
                {
                    name: 'meta_buffer'
                },
                {
                    name: 'ttl'
                },
                {
                    name: 'auto_expire'
                },
                {
                    name: 'format'
                },
                {
                    name: 'lock',
                    type: 'boolean'
                },
                {
                    name: 'layers'
                },
                {
                    name: 'cache'
                },
                {
                    name: 's3_tile_set'
                }

            ],
            listeners: {
                load: {
                    fn: function (store, records, options) {
                        var propGridLayer = Ext.getCmp('propGridLayer');
                        if (propGridLayer) {
                            // Remove default sorting
                            delete propGridLayer.getStore().sortInfo;
                            // set sorting of first column to false
                            propGridLayer.getColumnModel().getColumnById('name').sortable = false;
                            propGridLayer.setSource({
                                "theme_column": store.getAt(0).data.theme_column,
                                "label_column": store.getAt(0).data.label_column,
                                "opacity": store.getAt(0).data.opacity,
                                "label_max_scale": store.getAt(0).data.label_max_scale,
                                "label_min_scale": store.getAt(0).data.label_min_scale,
                                "cluster": store.getAt(0).data.cluster,
                                "maxscaledenom": store.getAt(0).data.maxscaledenom,
                                "minscaledenom": store.getAt(0).data.minscaledenom,
                                "symbolscaledenom": store.getAt(0).data.symbolscaledenom,
                                "geotype": store.getAt(0).data.geotype,
                                "offsite": store.getAt(0).data.offsite,
                                "label_no_clip": store.getAt(0).data.label_no_clip,
                                "polyline_no_clip": store.getAt(0).data.polyline_no_clip,
                                "bands": store.getAt(0).data.bands
                            });
                        }

                        var propGridTiles = Ext.getCmp('propGridTiles');
                        if (propGridTiles) {
                            // Remove default sorting
                            delete propGridTiles.getStore().sortInfo;
                            // set sorting of first column to false
                            propGridTiles.getColumnModel().getColumnById('name').sortable = false;
                            propGridTiles.setSource({
                                "meta_size": store.getAt(0).data.meta_size,
                                "meta_buffer": store.getAt(0).data.meta_buffer,
                                "ttl": store.getAt(0).data.ttl,
                                "auto_expire": store.getAt(0).data.auto_expire,
                                "format": store.getAt(0).data.format,
                                "lock": store.getAt(0).data.lock,
                                "layers": store.getAt(0).data.layers,
                                "cache": store.getAt(0).data.cache,
                                "s3_tile_set": store.getAt(0).data.s3_tile_set

                            });
                        }
                    }
                },
                exception: function (proxy, type, action, options, response, arg) {
                    if (response.status !== 200) {
                        Ext.MessageBox.show({
                            title: __('Failure'),
                            msg: __(Ext.decode(response.responseText).message),
                            buttons: Ext.MessageBox.OK,
                            width: 300,
                            height: 300
                        });
                        store.reload();
                    }
                }
            }
        });


        activeLayer = record.f_table_schema + "." + record.f_table_name;
        markup = [
            '<table style="margin-bottom: 7px"><tr class="x-grid3-row"><td>' + __('A SQL must return a primary key and a geometry. Naming and srid must match this') + '</td></tr></table>' +
            '<table>' +
            '<tr class="x-grid3-row"><td width="80"><b>Name</b></td><td  width="150">{f_table_schema}.{f_table_name}</td></tr>' +
            '<tr class="x-grid3-row"><td><b>Primary key</b></td><td  width="150">{pkey}</td></tr>' +
            '<tr class="x-grid3-row"><td><b>Srid</b></td><td>{srid}</td></tr>' +
            '<tr class="x-grid3-row"><td><b>Geom field</b></td><td>{f_geometry_column}</td></tr>' +
            '<tr class="x-grid3-row"><td><b>Geom type</b></td><td>{type}</td></tr>' +
            '</table>'
        ];
        var activeTab = Ext.getCmp("layerStyleTabs").getActiveTab();

        Ext.getCmp("layerStyleTabs").activate(3);

        Ext.getCmp("layerStyleTabs").activate(1);
        var template = new Ext.Template(markup);
        template.overwrite(Ext.getCmp('a5').body, record);
        var a1 = Ext.getCmp("a1");
        var a4 = Ext.getCmp("a4");
        a1.remove(wmsLayer.grid);
        a4.remove(wmsLayer.sqlForm);
        wmsLayer.grid = null;
        wmsLayer.sqlForm = null;
        wmsLayer.init(record); // TODO
        a1.add(wmsLayer.grid);
        a4.add(wmsLayer.sqlForm);
        a1.doLayout();
        a4.doLayout();

        Ext.getCmp("layerStyleTabs").activate(2);
        var a12 = Ext.getCmp("a12");
        a12.remove(tileLayer.grid);
        tileLayer.grid = null;
        tileLayer.init(record); // TODO
        a12.add(tileLayer.grid);
        a12.doLayout();

        Ext.getCmp("layerStyleTabs").activate(0);
        var a2 = Ext.getCmp("a2");
        a2.remove(wmsClasses.grid);
        wmsClasses.grid = null;
        wmsClasses.init(record);
        a2.add(wmsClasses.grid);
        a2.doLayout();
        var a3 = Ext.getCmp("a3");
        var a8 = Ext.getCmp("a8");
        var a9 = Ext.getCmp("a9");
        var a10 = Ext.getCmp("a10");
        var a11 = Ext.getCmp("a11");
        a3.remove(wmsClass.grid);
        a8.remove(wmsClass.grid2);
        a9.remove(wmsClass.grid3);
        a10.remove(wmsClass.grid4);
        a11.remove(wmsClass.grid5);
        a3.doLayout();
        a8.doLayout();
        a9.doLayout();
        a10.doLayout();
        a11.doLayout();

        Ext.getCmp("layerStyleTabs").activate(activeTab);
        var a13 = Ext.getCmp("a13");
        a13.remove(wmsLayer.legendForm);
        a13.add(wmsLayer.legendForm);
        a13.doLayout();
        updateLegend();
    };

    /**
     *
     * @param subuser
     * @param key
     * @param privileges
     */
    updatePrivileges = function (subuser, key, privileges) {
        var param = {
            data: {
                _key_: key,
                subuser: subuser,
                privileges: privileges
            }
        };
        param = Ext.util.JSON.encode(param);
        Ext.Ajax.request({
            url: '/controllers/layer/privileges',
            method: 'put',
            headers: {
                'Content-Type': 'application/json; charset=utf-8'
            },
            params: param,
            success: function () {
                App.setAlert(App.STATUS_NOTICE, __("Privileges updated"));
            },
            failure: function (response) {
                Ext.MessageBox.show({
                    title: __('Failure'),
                    msg: __(Ext.decode(response.responseText).message),
                    buttons: Ext.MessageBox.OK,
                    width: 400,
                    height: 300,
                    icon: Ext.MessageBox.ERROR
                });
                privilegesStore.load();

            }
        });
    };

    /**
     *
     * @param subuser
     * @param key
     * @param roles
     */
    updateWorkflow = function (subuser, key, roles) {
        var param = {
            data: {
                _key_: key,
                subuser: subuser,
                roles: roles
            }
        };
        param = Ext.util.JSON.encode(param);
        Ext.Ajax.request({
            url: '/controllers/layer/roles',
            method: 'put',
            headers: {
                'Content-Type': 'application/json; charset=utf-8'
            },
            params: param,
            failure: function (response) {
                Ext.MessageBox.show({
                    title: __('Failure'),
                    msg: __(Ext.decode(response.responseText).message),
                    buttons: Ext.MessageBox.OK,
                    width: 400,
                    height: 300,
                    icon: Ext.MessageBox.ERROR
                });
            }
        });
    };

    /**
     *
     * @param e
     * @returns {boolean}
     */
    styleWizardWin = function (e) {
        var record = null;
        grid.getStore().each(function (rec) {  // for each row
            var row = rec.data; // get record
            if (row._key_ === e) {
                record = row;
            }
        });
        if (!record) {
            App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
            return false;
        }
        new Ext.Window({
            title: '<i class="fa fa-eye"></i> ' + __("Class wizard"),
            layout: 'fit',
            width: 700,
            height: 540,
            plain: true,
            modal: true,
            resizable: false,
            draggable: true,
            border: false,
            closeAction: 'hide',
            x: 3,
            y: 32,
            items: [
                {
                    xtype: "panel",
                    border: false,
                    defaults: {
                        border: false
                    },
                    items: [
                        {
                            xtype: "panel",
                            id: "a7",
                            layout: "fit"
                        }
                    ]
                }
            ]
        }).show();
        var a7 = Ext.getCmp("a7");
        a7.remove(classWizards.quantile);
        classWizards.init(record);
        a7.add(classWizards.quantile);
        a7.doLayout();
    };

    /**
     *
     */
    resetButtons = function () {
        Ext.getCmp('advanced-btn').setDisabled(true);
        Ext.getCmp('privileges-btn').setDisabled(true);
        Ext.getCmp('workflow-btn').setDisabled(true);
        Ext.getCmp('renamelayer-btn').setDisabled(true);
        Ext.getCmp('copy-properties-btn').setDisabled(true);
        Ext.getCmp('deletelayer-btn').setDisabled(true);
        Ext.getCmp('movelayer-btn').setDisabled(true);
    };

    /**
     * Define the main tabs
     */
    var tabs = new Ext.TabPanel({
            id: "mainTabs",
            activeTab: 0,
            region: 'center',
            plain: true,
            resizeTabs: false,
            items: [
                {
                    xtype: "panel",
                    title: '<i class="fa fa-map"></i> ' + __('Map'),
                    layout: 'border',
                    items: [
                        {
                            frame: false,
                            layout: "fit",
                            border: false,
                            id: "mapPane",
                            region: "center",
                            items: [new Ext.Panel({
                                layout: 'border',
                                items: [
                                    new Ext.Panel({
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
                                                    }, {
                                                        region: "south",
                                                        id: "attrtable",
                                                        title: "Attribute table",
                                                        split: true,
                                                        frame: false,
                                                        border: false,
                                                        layout: 'fit',
                                                        height: 200,
                                                        collapsible: true,
                                                        collapsed: true
                                                    }
                                                ]
                                            })
                                        ]
                                    }),
                                    new Ext.Panel({
                                        border: false,
                                        region: "west",
                                        collapsible: true,
                                        split: true,
                                        width: 600,
                                        layout: "fit",

                                        items: new Ext.Panel({
                                            border: false,
                                            region: "center",
                                            collapsible: false,
                                            split: false,
                                            layout: "border",
                                            items: [
                                                new Ext.Panel({
                                                    border: false,
                                                    region: "center",
                                                    collapsible: false,
                                                    split: true,
                                                    width: 250,
                                                    tbar: [
                                                        {
                                                            text: '<i class="fa fa-plus-circle"></i> ' + __('New layer'),
                                                            disabled: (screenName === schema || subUser === false) ? false : true,
                                                            handler: function () {
                                                                onAdd();
                                                            }
                                                        }, '-',
                                                        {
                                                            text: "<i class='fa fa-refresh'></i> " + __("Reload"),
                                                            handler: function () {
                                                                stopEdit();
                                                                reLoadTree();
                                                            }
                                                        }],
                                                    items: [
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
                                                }),
                                                new Ext.Panel({
                                                    xtype: "panel",
                                                    autoScroll: true,
                                                    region: 'east',
                                                    collapsible: false,
                                                    id: "layerStylePanel",
                                                    disabled: true,
                                                    width: 340,
                                                    frame: false,
                                                    split: true,
                                                    plain: true,
                                                    layoutConfig: {
                                                        animate: true
                                                    },
                                                    border: false,
                                                    tbar: [{
                                                        text: '<i class="fa fa-eye"></i> ' + __('Class wizard'),
                                                        id: 'stylebutton',
                                                        disabled: true,
                                                        handler: function () {
                                                            var node = tree.getSelectionModel().getSelectedNode();
                                                            styleWizardWin(node.id);
                                                        }
                                                    }, '-',
                                                        {
                                                            text: "<i class='fa fa-pencil'></i> " + __("Start edit"),
                                                            id: "editlayerbutton",
                                                            disabled: true,
                                                            handler: function (thisBtn, event) {
                                                                try {
                                                                    stopEdit();
                                                                } catch (e) {
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
                                                                } else {
                                                                    var poll = function () {
                                                                        if (typeof filter.win === "object") {
                                                                            filter.win.show();
                                                                        } else {
                                                                            setTimeout(poll, 10);
                                                                        }
                                                                    };
                                                                    poll();
                                                                }
                                                            }
                                                        }, '-', {
                                                            text: "<i class='fa fa-bolt'></i> " + __("Quick draw"),
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
                                                                } else {
                                                                    var filter = new OpenLayers.Filter.Comparison({
                                                                        type: OpenLayers.Filter.Comparison.EQUAL_TO,
                                                                        property: "\"dummy\"",
                                                                        value: "-1"
                                                                    });

                                                                    attributeForm.init(id[1], geomField);
                                                                    startWfsEdition(id[1], geomField, filter);
                                                                    attributeForm.form.disable();
                                                                    mapTools[0].control.activate();
                                                                    Ext.getCmp('editcreatebutton').toggle(true);
                                                                    Ext.iterate(qstore, function (v) {
                                                                        v.reset();
                                                                    });
                                                                    queryWin.hide();
                                                                }
                                                            }
                                                        }],
                                                    items: [
                                                        {
                                                            xtype: "tabpanel",
                                                            id: "layerStyleTabs",
                                                            activeTab: 0,
                                                            plain: true,
                                                            border: false,
                                                            resizeTabs: false,
                                                            items: [
                                                                {
                                                                    xtype: "panel",
                                                                    title: __('Classes'),
                                                                    defaults: {
                                                                        border: false
                                                                    },
                                                                    items: [
                                                                        {
                                                                            xtype: "panel",
                                                                            id: "a2",
                                                                            layout: "fit",
                                                                            height: 200
                                                                        },
                                                                        new Ext.TabPanel({
                                                                            activeTab: 0,
                                                                            disabled: true,
                                                                            region: 'center',
                                                                            plain: true,
                                                                            id: "classTabs",
                                                                            border: false,
                                                                            height: 570,
                                                                            resizeTabs: false,
                                                                            defaults: {
                                                                                layout: "fit",
                                                                                border: false
                                                                            },
                                                                            tbar: [
                                                                                {
                                                                                    text: '<i class="fa fa-check"></i> ' + __('Update'),
                                                                                    handler: function () {
                                                                                        var grid = Ext.getCmp("propGrid");
                                                                                        var grid2 = Ext.getCmp("propGrid2");
                                                                                        var grid3 = Ext.getCmp("propGrid3");
                                                                                        var grid4 = Ext.getCmp("propGrid4");
                                                                                        var grid5 = Ext.getCmp("propGrid5");
                                                                                        var source = grid.getSource();
                                                                                        jQuery.extend(source, grid2.getSource());
                                                                                        jQuery.extend(source, grid3.getSource());
                                                                                        jQuery.extend(source, grid4.getSource());
                                                                                        jQuery.extend(source, grid5.getSource());
                                                                                        var param = {
                                                                                            data: source
                                                                                        };
                                                                                        param = Ext.util.JSON.encode(param);

                                                                                        Ext.Ajax.request({
                                                                                            url: '/controllers/classification/index/' + wmsClasses.table + '/' + wmsClass.classId,
                                                                                            method: 'put',
                                                                                            params: param,
                                                                                            headers: {
                                                                                                'Content-Type': 'application/json; charset=utf-8'
                                                                                            },
                                                                                            success: function (response) {
                                                                                                App.setAlert(App.STATUS_OK, __("Style is updated"));
                                                                                                writeFiles(wmsClasses.table, map);
                                                                                                wmsClasses.store.load();
                                                                                                store.load();
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
                                                                                }
                                                                            ],
                                                                            items: [
                                                                                {
                                                                                    xtype: "panel",
                                                                                    id: "a3",
                                                                                    title: "Base"
                                                                                },
                                                                                {
                                                                                    xtype: "panel",
                                                                                    id: "a8",
                                                                                    title: "Symbol1"
                                                                                },
                                                                                {
                                                                                    xtype: "panel",
                                                                                    id: "a9",
                                                                                    title: "Symbol2"
                                                                                },
                                                                                {
                                                                                    xtype: "panel",
                                                                                    id: "a10",
                                                                                    title: "Label1"
                                                                                },
                                                                                {
                                                                                    xtype: "panel",
                                                                                    id: "a11",
                                                                                    title: "Label2"
                                                                                }

                                                                            ]
                                                                        })


                                                                    ]
                                                                },
                                                                {
                                                                    xtype: "panel",
                                                                    title: __('Settings'),
                                                                    height: 700,
                                                                    defaults: {
                                                                        border: false
                                                                    },
                                                                    border: false,
                                                                    items: [
                                                                        {
                                                                            xtype: "panel",
                                                                            id: "a1",
                                                                            layout: "fit"
                                                                        },
                                                                        {
                                                                            xtype: "panel",
                                                                            id: "a4"

                                                                        },
                                                                        {
                                                                            id: 'a5',
                                                                            border: false,
                                                                            bodyStyle: {
                                                                                padding: '10px'
                                                                            }
                                                                        }
                                                                    ]
                                                                },
                                                                {
                                                                    xtype: "panel",
                                                                    title: __('Tile cache'),
                                                                    height: 700,
                                                                    defaults: {
                                                                        border: false
                                                                    },
                                                                    border: false,
                                                                    items: [
                                                                        {
                                                                            xtype: "panel",
                                                                            id: "a12",
                                                                            layout: "fit"
                                                                        }
                                                                    ]
                                                                },
                                                                {
                                                                    xtype: "panel",
                                                                    title: __('Legend'),
                                                                    autoHeight: true,
                                                                    defaults: {
                                                                        border: false,
                                                                        bodyStyle: "padding : 7px"
                                                                    },
                                                                    items: [
                                                                        {
                                                                            xtype: "panel",
                                                                            id: "a6",
                                                                            html: ""
                                                                        },
                                                                        {
                                                                            xtype: "panel",
                                                                            id: "a13"
                                                                        },
                                                                    ]
                                                                }
                                                            ]
                                                        }
                                                    ]
                                                })
                                            ]
                                        })


                                    })

                                ]
                            })]

                        }
                    ]
                },
                new Ext.Panel({
                    title: '<i class="fa fa-database"></i> ' + __('Database'),
                    frame: false,
                    layout: 'border',
                    region: 'center',
                    border: false,
                    split: true,
                    items: [grid, {
                        id: 'detailPanel',
                        region: 'south',
                        border: false,
                        height: 70,
                        bodyStyle: {
                            padding: '7px'
                        }
                    }, {
                        xtype: "tabpanel",
                        activeTab: 0,
                        plain: true,
                        border: false,
                        resizeTabs: false,
                        region: 'center',
                        collapsed: false,
                        collapsible: false,
                        id: "tablepanel",
                        items: [
                            {
                                border: false,
                                layout: 'fit',
                                xtype: "panel",
                                title: __("Structure"),
                                id: 'structurepanel'

                            },
                            {
                                border: false,
                                layout: 'fit',
                                xtype: "panel",
                                title: __("Data"),
                                id: 'datapanel',
                                listeners: {
                                    activate: function (e) {
                                        if (grid.getSelectionModel().getSelections().length > 1) {
                                            Ext.getCmp("datapanel").removeAll();
                                            return false;
                                        }
                                        var r = grid.getSelectionModel().getSelected(),
                                            tableName = r.data.f_table_schema + "." + r.data.f_table_name,
                                            dataPanel = Ext.getCmp("datapanel");
                                        try {
                                            dataPanel.remove(dataGrid);
                                        } catch (ex) {
                                        }
                                        $.ajax({
                                            url: '/controllers/table/columns/' + tableName + '?i=1',
                                            async: true,
                                            dataType: 'json',
                                            type: 'GET',
                                            success: function (response, textStatus, http) {
                                                var validProperties = true,
                                                    fieldsForStore = response.forStore,
                                                    columnsForGrid = response.forGrid;

                                                // We add an editor to the fields
                                                for (var i in columnsForGrid) {
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
                                                            } catch (e) {
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
                                                        } else if (columnsForGrid[i].typeObj.type === "date") {
                                                            columnsForGrid[i].editor = new Ext.form.TextField();
                                                        } else if (columnsForGrid[i].typeObj.type === "timestamp") {
                                                            columnsForGrid[i].editor = new Ext.form.TextField();
                                                        } else if (columnsForGrid[i].typeObj.type === "time") {
                                                            columnsForGrid[i].editor = new Ext.form.TextField();
                                                        }
                                                    }
                                                }
                                                var proxy = new Ext.data.HttpProxy({
                                                    restful: true,
                                                    type: 'json',
                                                    api: {
                                                        read: '/controllers/table/data/' + tableName + '/' + r.data._key_,
                                                        create: '/controllers/table/data/' + tableName + '/' + r.data._key_,
                                                        update: '/controllers/table/data/' + tableName + '/' + r.data.pkey + '/' + r.data._key_,
                                                        destroy: '/controllers/table/data/' + tableName + '/' + r.data.pkey + '/' + r.data._key_
                                                    },
                                                    listeners: {
                                                        write: function (store, action, result, transaction, rs) {
                                                            if (transaction.success) {
                                                                //
                                                            }
                                                        },
                                                        beforewrite: function () {
                                                            if (r.data.hasPkey === false) {
                                                                App.setAlert(App.STATUS_NOTICE, __("You can't edit a relation without a primary key"));
                                                                dataStore.reload();
                                                                return false;
                                                            }
                                                        },
                                                        exception: function (proxy, type, action, options, response, arg) {
                                                            if (response.status !== 200) {
                                                                Ext.MessageBox.show({
                                                                    title: __("Failure"),
                                                                    msg: __(Ext.decode(response.responseText).message),
                                                                    buttons: Ext.MessageBox.OK,
                                                                    width: 300,
                                                                    height: 300
                                                                });
                                                            }
                                                            //dataStore.reload();
                                                        }
                                                    }
                                                });
                                                dataStore = new Ext.data.Store({
                                                    writer: new Ext.data.JsonWriter({
                                                        writeAllFields: false,
                                                        encode: false
                                                    }),
                                                    reader: new Ext.data.JsonReader({
                                                        successProperty: 'success',
                                                        idProperty: r.data.pkey,
                                                        root: 'data',
                                                        messageProperty: 'message'
                                                    }, fieldsForStore),
                                                    proxy: proxy,
                                                    autoSave: true
                                                });
                                                dataGrid = new Ext.grid.EditorGridPanel({
                                                    id: "datagridpanel",
                                                    disabled: false,
                                                    stateful: false,
                                                    viewConfig: {
                                                        //forceFit: true
                                                    },
                                                    border: false,
                                                    store: dataStore,
                                                    listeners: {},
                                                    sm: new Ext.grid.RowSelectionModel({
                                                        singleSelect: false
                                                    }),
                                                    cm: new Ext.grid.ColumnModel({
                                                        defaults: {
                                                            sortable: true,
                                                            editor: {
                                                                xtype: "textfield"
                                                            }
                                                        },
                                                        columns: columnsForGrid
                                                    }),
                                                    tbar: [
                                                        {
                                                            text: '<i class="fa fa-plus"></i> ' + __('Add record'),
                                                            handler: function () {
                                                                // access the Record constructor through the grid's store
                                                                var rec = dataGrid.getStore().recordType;
                                                                var p = new rec({});
                                                                dataGrid.stopEditing();
                                                                dataStore.insert(0, p);
                                                                dataStore.load();
                                                            }
                                                        }, {
                                                            text: '<i class="fa fa-cut"></i> ' + __('Delete records'),
                                                            handler: function () {
                                                                var r = grid.getSelectionModel().getSelected();
                                                                if (r.data.hasPkey === false) {
                                                                    App.setAlert(App.STATUS_NOTICE, __("You can't edit a relation without a primary key"));
                                                                    return false;
                                                                }
                                                                var records = dataGrid.getSelectionModel().getSelections();
                                                                if (records.length === 0) {
                                                                    App.setAlert(App.STATUS_NOTICE, __("You've to select one or more records"));
                                                                    return false;
                                                                }
                                                                Ext.MessageBox.confirm(__('Confirm'), __('Are you sure you want to delete') + ' ' + records.length + ' ' + __('records(s)') + '?', function (btn) {
                                                                    if (btn === "yes") {
                                                                        Ext.each(dataGrid.getSelectionModel().getSelections(), function (i) {
                                                                            dataStore.remove(i);
                                                                        })
                                                                    } else {
                                                                        return false;
                                                                    }
                                                                });
                                                            }
                                                        }
                                                    ],
                                                    bbar: new Ext.PagingToolbar({
                                                        pageSize: 100,
                                                        store: dataStore,
                                                        displayInfo: true,
                                                        displayMsg: 'Features {0} - {1} of {2}',
                                                        emptyMsg: __("No features")
                                                    })
                                                });
                                                dataPanel.add(dataGrid);
                                                dataPanel.doLayout();
                                                dataStore.load();
                                            }
                                        });


                                    }
                                }
                            },
                            {
                                border: false,
                                layout:
                                    'fit',
                                xtype:
                                    "panel",
                                title:
                                    __("Elasticsearch"),
                                id:
                                    'espanel',
                                listeners:
                                    {
                                        activate: function (e) {
                                            if (grid.getSelectionModel().getSelections().length > 1) {
                                                Ext.getCmp("espanel").removeAll();
                                                return false;
                                            }
                                            esPanel = Ext.getCmp("espanel");

                                            try {
                                                esPanel.remove(elasticsearch.grid);
                                            } catch (ex) {
                                                console.log(ex.message)
                                            }
                                            elasticsearch.grid = null;
                                            elasticsearch.init(grid.getSelectionModel().getSelected(), parentdb);
                                            esPanel.add(elasticsearch.grid);
                                            esPanel.doLayout();
                                        }
                                    }

                            }
                        ]
                    }
                    ]
                }),
                {
                    xtype: "panel",
                    title:
                        '<i class="fa fa-users"></i> ' + __('Workflow'),
                    layout:
                        'border',
                    id:
                        "workflowPanel",
                    listeners:
                        {
                            activate: function () {
                                if (!workflowStoreLoaded) {
                                    workflowStore.load();
                                    workflowStoreLoaded = true;
                                }
                            }
                        }
                    ,
                    items: [
                        new Ext.grid.GridPanel({
                            id: "workflowGrid",
                            store: workflowStore,
                            viewConfig: {
                                forceFit: true,
                                stripeRows: true
                            },
                            height: 300,
                            split: true,
                            region: 'center',
                            frame: false,
                            border: false,
                            plugins: [new Ext.ux.grid.GridFilters({
                                local: true,
                                filters: [{
                                    type: 'string',
                                    dataIndex: 'f_table_name',
                                    disabled: false
                                }]
                            })],
                            sm: new Ext.grid.RowSelectionModel({
                                singleSelect: true
                            }),
                            cm: new Ext.grid.ColumnModel({
                                defaults: {
                                    sortable: true,
                                    menuDisabled: true
                                },
                                columns: [
                                    {
                                        header: __("Operation"),
                                        dataIndex: "operation",
                                        sortable: true,
                                        width: 35,
                                        flex: 1
                                    }, /*{
                                 header: __("Schema"),
                                 dataIndex: "f_schema_name",
                                 sortable: true,
                                 width: 35,
                                 flex: 0.5
                                 },*/
                                    {
                                        header: __("Table"),
                                        dataIndex: "f_table_name",
                                        sortable: true,
                                        width: 35,
                                        flex: 0.5,
                                        menuDisabled: false
                                    }, {
                                        header: __("Fid"),
                                        dataIndex: "gid",
                                        sortable: true,
                                        width: 25,
                                        flex: 1
                                    }, {
                                        header: __("Version id"),
                                        dataIndex: "version_gid",
                                        sortable: true,
                                        width: 40,
                                        flex: 1
                                    }, {
                                        header: __("Status"),
                                        dataIndex: "status_text",
                                        sortable: true,
                                        width: 35,
                                        flex: 1
                                    }, {
                                        header: __("Latest edit by"),
                                        dataIndex: "gc2_user",
                                        sortable: true,
                                        width: 50,
                                        flex: 1
                                    }, {
                                        header: __("Authored by"),
                                        dataIndex: "author",
                                        sortable: true,
                                        width: 50,
                                        flex: 2
                                    }, {
                                        header: __("Reviewed by"),
                                        dataIndex: "reviewer",
                                        sortable: true,
                                        width: 50,
                                        flex: 2
                                    }, {
                                        header: __("Published by"),
                                        dataIndex: "publisher",
                                        sortable: true,
                                        width: 50,
                                        flex: 2
                                    }, {
                                        header: __("Created"),
                                        dataIndex: "created",
                                        sortable: true,
                                        width: 120,
                                        flex: 1
                                    }
                                ]
                            }),
                            tbar: [
                                {
                                    text: '<i class="fa fa-refresh"></i> ' + __('Reload'),
                                    tooltip: __("Reload the list"),
                                    handler: function () {
                                        if (Ext.getCmp('workflowShowAllBtn').pressed) {
                                            workflowStore.load({params: "all=t"});
                                        } else {
                                            workflowStore.load();
                                        }
                                    }
                                },
                                {
                                    text: '<i class="fa fa-list"></i> ' + __('Show all'),
                                    enableToggle: true,
                                    id: "workflowShowAllBtn",
                                    disabled: (subUser === false) ? true : false,
                                    tooltip: __("Show all items, also those you've taken action on."),
                                    handler: function () {
                                        if (this.pressed) {
                                            workflowStore.load({params: "all=t"});
                                        } else {
                                            workflowStore.load();
                                        }
                                    }
                                },
                                {
                                    text: '<i class="fa fa-edit"></i> ' + __('See/edit feature'),
                                    tooltip: __("Switch to Map view with the feature loaded."),
                                    handler: function () {
                                        var records = Ext.getCmp("workflowGrid").getSelectionModel().getSelections();
                                        if (records.length === 0) {
                                            App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
                                        }
                                        Ext.Ajax.request({
                                            url: '/api/v1/meta/' + parentdb + '/' + records[0].get("f_schema_name") + "." + records[0].get("f_table_name"),
                                            method: 'GET',
                                            headers: {
                                                'Content-Type': 'application/json; charset=utf-8'
                                            },
                                            success: function (response) {
                                                var r = Ext.decode(response.responseText),
                                                    filter = new OpenLayers.Filter.Comparison({
                                                        type: OpenLayers.Filter.Comparison.EQUAL_TO,
                                                        property: "\"" + r.data[0].pkey + "\"",
                                                        value: records[0].get("gid")
                                                    });
                                                Ext.getCmp("mainTabs").activate(0);
                                                setTimeout(function () {
                                                    attributeForm.init(records[0].get("f_table_name"), r.data[0].pkey);
                                                    startWfsEdition(records[0].get("f_table_name"), r.data[0].f_geometry_column, filter, true);
                                                    attributeForm.form.disable();
                                                }, 100);
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
                                {
                                    text: '<i class="fa fa-check"></i> ' + __('Check feature'),
                                    tooltip: __("This will update the feature with your role in the workflow."),
                                    handler: function () {
                                        var records = Ext.getCmp("workflowGrid").getSelectionModel().getSelections();
                                        if (records.length === 0) {
                                            App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
                                        }
                                        Ext.Ajax.request({
                                            url: '/controllers/workflow/' + records[0].get("f_schema_name") + "/" + records[0].get("f_table_name") + "/" + records[0].get("gid"),
                                            method: 'PUT',
                                            headers: {
                                                'Content-Type': 'application/json; charset=utf-8'
                                            },
                                            success: function (response) {
                                                if (Ext.getCmp('workflowShowAllBtn').pressed) {
                                                    workflowStore.load({params: "all=t"});
                                                } else {
                                                    workflowStore.load();
                                                }
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
                                }
                            ]
                        }), {
                            region: 'south',
                            id: 'workflow_footer',
                            border: false,
                            height: 70,
                            bodyStyle: {
                                padding: '7px'
                            }
                        }
                    ]
                }
                ,
                {
                    xtype: "panel",
                    title:
                        '<i class="fa fa-clock-o"></i> ' + __('Scheduler'),
                    layout:
                        'border',
                    id:
                        "schedulerPanel",
                    items:
                        [
                            {
                                frame: false,
                                border: false,
                                region: "center",
                                html: '<iframe frameborder="0" id="scheduler" style="width:100%;height:100%" src="/scheduler/index2.html"></iframe>'
                            }
                        ]
                }
                ,
                {
                    xtype: "panel",
                    title:
                        '<i class="fa fa-list"></i> ' + __('Log'),
                    layout:
                        'border',
                    border:
                        false,
                    listeners:
                        {
                            activate: function () {
                                Ext.fly(this.ownerCt.getTabEl(this)).on({
                                    click: function () {
                                        Ext.Ajax.request({
                                            url: '/controllers/session/log',
                                            method: 'get',
                                            headers: {
                                                'Content-Type': 'application/json; charset=utf-8'
                                            },
                                            success: function (response) {
                                                $("#gc-log").html(Ext.decode(response.responseText).data);
                                            }
                                            //failure: test
                                        });
                                    }
                                });
                            }
                            ,
                            single: true
                        }
                    ,
                    items: [
                        {
                            xtype: "panel",
                            autoScroll: true,
                            region: 'center',
                            frame: true,
                            plain: true,
                            border: false,
                            html: "<div id='gc-log'></div>"
                        }
                    ]
                }
            ]
        })
    ;

    /**
     * Create the view port
     */
    var viewPort = new Ext.Viewport({
        layout: 'border',
        items: [tabs]
    });

    /**
     * TODO Rename to writeMapFile
     * Write out the MapFile
     * @param clearCachedLayer
     */
    writeFiles = function (clearCachedLayer) {
        $.ajax({
            url: '/controllers/mapfile',
            success: function (response) {
                updateLegend();

                if (clearCachedLayer) {
                    clearTileCache(clearCachedLayer);
                }
            }
        });
    };

    /**
     * Write out the MapCache file
     * @param clearCachedLayer
     */
    writeMapCacheFile = function (clearCachedLayer) {
        $.ajax({
            url: '/controllers/mapcachefile',
            success: function (response) {
                if (clearCachedLayer) {
                    clearTileCache(clearCachedLayer);
                }
            }
        });
    };

    /**
     *
     * @param layer
     * @param map
     */
    clearTileCache = function (layer) {
        var key = layer.split(".")[0] + "." + layer.split(".")[1];
        $.ajax({
            url: '/controllers/tilecache/index/' + layer,
            async: true,
            dataType: 'json',
            type: 'delete',
            success: function (response) {
                if (response.success === true) {
                    App.setAlert(App.STATUS_NOTICE, __(response.message));
                    var l = map.getLayersByName(key)[0];
                    l.clearGrid();
                    var n = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                        var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
                        return v.toString(16);
                    });
                    l.url = l.url.replace(l.url.split("?")[1], "");
                    l.url = l.url + "?token=" + n;
                    setTimeout(function () {
                        l.redraw();
                    }, 500);

                } else {
                    App.setAlert(App.STATUS_NOTICE, __(response.message));
                }
            }
        });
    };

    changeLayerType = function (layer) {
        var key = layer.split(".")[0] + "." + layer.split(".")[1];
        var elId = "#ext-change-type-" + layer.split(".").join("-");
        var l = map.getLayersByName(key)[0];
        var split = l.url.split("/");
        var newUri;

        if (split[3] === "mapcache") {
            newUri = split[0] + "//" + split[2] + "/ows/" + split[4] + "/" + layer.split(".")[0];
            l.setTileSize(new OpenLayers.Size(256, 256));
            l.url = newUri;
            $(elId).addClass("fa-square").removeClass("fa-delicious");

        } else {
            newUri = split[0] + "//" + split[2] + "/mapcache/" + split[4] + "/wms";
            l.url = newUri;
            $(elId).addClass("fa-delicious").removeClass("fa-square");

        }
        l.clearGrid();

        setTimeout(function () {
            l.redraw();
        }, 500);
    }

    /**
     *
     */
    updateLegend = function () {
        var a = Ext.getCmp("a6");
        var b = Ext.getCmp("wizardLegend");
        if (activeLayer !== undefined) {
            $.ajax({
                url: '/api/v1/legend/html/' + parentdb + '/' + activeLayer.split(".")[0] + '?l=' + activeLayer,
                dataType: 'jsonp',
                jsonp: 'jsonp_callback',
                success: function (response) {
                    a.update(response.html);
                    a.doLayout();
                    try {
                        b.update(response.html);
                        b.doLayout();
                    } catch (e) {
                    }
                }
            });
        }
    };

    /**
     *
     * @param show
     * @param text
     */
    spinner = function (show, text) {
        if (show) {
            $("#spinner").show();
            $("#spinner span").html(text);
        } else {
            $("#spinner").hide();
            $("#spinner span").empty();
        }
    };

    /**
     * Load stores
     */
    store.load(); // TODO chain?
    groupsStore.load(); // TODO chain?
    tagStore.load();// TODO chain?
    schemasStore.load(); // TODO chain?

    /**
     * Add listener on schema select box
     */
    Ext.getCmp("schemabox").on('select', function (e) {
        window.location = "/admin/" + parentdb + "/" + e.value;
    });

    /**
     * Add listener on layer grid
     */
    grid.getSelectionModel().on('rowselect', function (sm, rowIdx, r) {
        var records = sm.getSelections();
        if (records.length === 1) {
            Ext.getCmp('advanced-btn').setDisabled(false);
            if (subUser === false || screenName === schema) {
                Ext.getCmp('privileges-btn').setDisabled(false);
                Ext.getCmp('workflow-btn').setDisabled(false);
                Ext.getCmp('renamelayer-btn').setDisabled(false);
                Ext.getCmp('copy-properties-btn').setDisabled(false);
            }
        } else {
            Ext.getCmp('advanced-btn').setDisabled(true);
            Ext.getCmp('privileges-btn').setDisabled(true);
            Ext.getCmp('workflow-btn').setDisabled(true);
            Ext.getCmp('renamelayer-btn').setDisabled(true);
            Ext.getCmp('copy-properties-btn').setDisabled(true);
        }
        if (records.length > 0 && subUser === false) {
            Ext.getCmp('movelayer-btn').setDisabled(false);
        }
        if (records.length > 0 && (subUser === false || screenName === schema)) {
            Ext.getCmp('deletelayer-btn').setDisabled(false);
        }
        onEdit();
    });

    /**
     * Hide tab if scheduler is not available for the db
     */
    if (window.gc2Options.gc2scheduler !== null) {
        if ((window.gc2Options.gc2scheduler.hasOwnProperty(parentdb) === false || window.gc2Options.gc2scheduler[parentdb] === false) && window.gc2Options.gc2scheduler.hasOwnProperty("*") === false) {
            tabs.hideTabStripItem(Ext.getCmp('schedulerPanel'));
        }
    } else {
        tabs.hideTabStripItem(Ext.getCmp('schedulerPanel'));
    }

    /**
     * Hide tab if workflow is not available for the db
     */
    if (!enableWorkflow) {
        tabs.hideTabStripItem(Ext.getCmp('workflowPanel'));
    }


    loadTree = function (response) {
        var treeConfig = [
            {
                id: "baselayers",
                nodeType: "gx_baselayercontainer",
                singleClickExpand: true
            }
        ];

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
                } catch (e) {
                }
                if (response.data[u].type) {
                    layers[[response.data[u].f_table_schema + "." + response.data[u].f_table_name]] = cloud.addTileLayers([response.data[u].f_table_schema + "." + response.data[u].f_table_name], {
                        db: subUser ? screenName + "@" + parentdb : screenName,
                        singleTile: true,
                        //isBaseLayer: isBaseLayer,
                        visibility: false,
                        wrapDateLine: false,
                        tileCached: false,
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
                            var t, c, v = response.data[u].reltype;
                            c = v === "v" ? "#a6cee3" :
                                v === "mv" ? "#b2df8a" :
                                    v === "ft" ? "#fb9a99" : "#fdbf6f";
                            t = v === "v" ? "VIEW" :
                                v === "mv" ? "MATERIALIZED VIEW" :
                                    v === "ft" ? "FOREIGN TABLE" : "TABLE";
                            l.push({
                                text: "<i style='color: " + c + "' class='fa fa-circle' aria-hidden='true'></i> <span ext:qtip='" + t + "<br>" + response.data[u]._key_ + "'>" + ((response.data[u].f_table_title === null || response.data[u].f_table_title === "") ? response.data[u].f_table_name : response.data[u].f_table_title) + "</span><span style='float:right' class='leaf-tools' id='" + id.split('.').join('-') + "'></span>",
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
                    text: arr[i] || "<font color='red'>Ungrouped</font>",
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
                                onEditWMSClasses(e.id);
                            } else {
                                clearLayerPanel();
                            }
                        };

                        if (e.leaf === true && e.parentNode.id !== "baselayers") {
                            Ext.getCmp('editlayerbutton').setDisabled(false);
                            Ext.getCmp('quickdrawbutton').setDisabled(false);
                            Ext.getCmp('stylebutton').setDisabled(false);
                        } else {
                            Ext.getCmp('editlayerbutton').setDisabled(true);
                            Ext.getCmp('quickdrawbutton').setDisabled(true);
                            Ext.getCmp('stylebutton').setDisabled(true);
                            clearLayerPanel();
                        }

                        if (currentId !== e.id) {
                            if (e.leaf === true && e.parentNode.id !== "baselayers") {
                                Ext.getCmp("layerStylePanel").expand(true);
                                load();
                            }
                        } else {
                            return;
                        }

                        try {
                            stopEdit();
                        } catch (error) {
                        }

                        if (typeof filter.win !== "undefined") {
                            if (typeof filter.win.hide !== "undefined") {
                                filter.win.hide();
                            }
                            filter.win = false;
                        }
                        $(".leaf-tools").empty();

                        var split = [];
                        if (id.split("-").length === 3) {
                            var split = map.getLayersByName(id.split("-")[0] + "." + id.split("-")[1])[0].url.split("/");
                        }

                        $("#" + id).html(
                            "<i class='fa " + (split[3] === "mapcache" ? "fa-delicious" : "fa-square") + " layertree-btn' ext:qtip='" + __("Change between WMS and Tile Cache") + "' ext id='ext-change-type-" + id + "'></i>  " +
                            "<i class='fa fa-arrows-alt layertree-btn' ext:qtip='" + __("Zoom to layer extent") + "' ext id='ext-" + id + "'></i>"
                        );
                        currentId = e.id;
                        $("#edit-" + id).on("click", function () {
                            try {
                                stopEdit();
                            } catch (e) {
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
                            } else {
                                var poll = function () {
                                    if (typeof filter.win === "object") {
                                        filter.win.show();
                                    } else {
                                        setTimeout(poll, 10);
                                    }
                                };
                                poll();
                            }
                        });

                        $("#quick-draw-" + id).on("click", function (e) {
                            e.preventDefault();
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
                            } else {
                                var filter = new OpenLayers.Filter.Comparison({
                                    type: OpenLayers.Filter.Comparison.EQUAL_TO,
                                    property: "\"dummy\"",
                                    value: "-1"
                                });

                                attributeForm.init(id[1], geomField);
                                startWfsEdition(id[1], geomField, filter);
                                attributeForm.form.disable();
                                Ext.getCmp("edit-tbar");
                                mapTools[0].control.activate();
                                Ext.getCmp('editcreatebutton').toggle(true);
                                Ext.iterate(qstore, function (v) {
                                    v.reset();
                                });
                                queryWin.hide();
                            }
                        });

                        $("#ext-change-type-" + id).on("click", function () {
                            changeLayerType(e.id, true)
                        })

                        $("#ext-" + id).on("click", function () {
                            if (metaDataRealKeys[e.id].type === "RASTER") {
                                App.setAlert(App.STATUS_NOTICE, __('You can only zoom to vector layers.'));
                                return false;
                            }
                            Ext.Ajax.request({
                                url: '/api/v1/extent/' + parentdb + '/' + e.id + '/900913',
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
                                        title: __("Failure"),
                                        msg: __(Ext.decode(response.responseText).message),
                                        buttons: Ext.MessageBox.OK,
                                        width: 400,
                                        height: 300,
                                        icon: Ext.MessageBox.INFO
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

        // BM1

        var west = Ext.getCmp("treepanel");
        west.remove(tree);
        west.add(tree);
        west.doLayout();

        //writeFiles();
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
        if (extentRestricted) {
            extentRestrictLayer.addFeatures(new OpenLayers.Feature.Vector(OpenLayers.Bounds.fromArray(settings.extentrestricts[schema]).toGeometry()));
        }
        if (!isLoaded) {
            isLoaded = true;
            if (initExtent !== null) {
                cloud.map.zoomToExtent(initExtent, false);
            } else {
                cloud.map.zoomToMaxExtent();
            }
        }
        map.addLayers([extentRestrictLayer]);
        // Remove the loading screen
        $("#loadscreen").hide();


    };

    reLoadTree = function () {
        firstLoad = true; // Reset
        store.reload();
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
                title: '<i class="fa fa-arrows-v"></i> ' + __("Measure"),
                layout: 'fit',
                width: 300,
                height: 90,
                plain: true,
                border: false,
                closeAction: 'hide',
                renderTo: 'mappanel',
                html: '<div style="padding: 5px"><div id="output" style="height: 27px; margin-bottom: 10px; color: #fff"></div><div style="color: rgba(255, 255, 255, .5)">' + __("Close this window to disable measure tool") + '</div></div>',
                x: 50,
                y: 65,
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
        if (typeof (objRef) === "object") {
            measureWin.show(objRef);
        } else {
            measureWin.show();
        }//end if object reference was passed
    }

    queryWin = new Ext.Window({
        title: '<i class="fa fa-info"></i> ' + __("Query result"),
        modal: false,
        border: false,
        layout: 'fit',
        width: 400,
        height: 400,
        renderTo: 'mappanel',
        closeAction: 'hide',
        x: 50,
        y: 150,
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
                resizeTabs: false,
                plain: true,
                border: false
            })
        ]
    })


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

// Always write the MapFile on start up
    writeFiles();

});

/**
 * Setup checks for session and schema in session
 */
setInterval(function () {
    $.ajax({
        url: '/api/v2/session',
        dataType: 'json',
        success: function (data) {
            if (!data.data.session) {
                alert(__("You are no longer logged in to GC2. Close or refresh your browser"));
                return;
            }
            if (schema !== data.data.schema) {
                alert(__("You have started Admin for another schema. Either close this here or refresh your browser"));
            }
        },
        error: function () {
            alert("Noget gik galt. Prøv at refreshe din browser");
        }
    });
}, 2000);


function startWfsEdition(layerName, geomField, wfsFilter, single, timeSlice) {
    'use strict';
    var fieldsForStore, columnsForGrid, type, multi, handlerType, editable = true, sm,
        south = Ext.getCmp("attrtable"),
        singleEditing = single;
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
        url: '/controllers/table/columns/' + layerName + '?i=1',
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
                        } catch (e) {
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
                    } else if (columnsForGrid[i].typeObj.type === "date") {
                        columnsForGrid[i].editor = new Ext.form.TextField();
                    } else if (columnsForGrid[i].typeObj.type === "timestamp") {
                        columnsForGrid[i].editor = new Ext.form.TextField();
                    } else if (columnsForGrid[i].typeObj.type === "time") {
                        columnsForGrid[i].editor = new Ext.form.TextField();
                    } else if (columnsForGrid[i].typeObj.type === "timestamptz") {
                        columnsForGrid[i].editor = new Ext.form.TextField();
                    } else if (columnsForGrid[i].typeObj.type === "timetz") {
                        columnsForGrid[i].editor = new Ext.form.TextField();
                    }
                }
            }
        }
    });
    if (type === "Point") {
        handlerType = OpenLayers.Handler.Point;
    } else if (type === "Polygon") {
        handlerType = OpenLayers.Handler.Polygon;
    } else if (type === "Path") {
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
            url: "/wfs/" + (subUser ? screenName + "@" + parentdb : screenName) + "/" + schema + "/900913" + (timeSlice ? "/" + timeSlice : "") + "?",
            version: "1.0.0",
            featureType: layerName,
            featureNS: "http:" + "//" + location.hostname + (location.port ? ":" + location.port : "") + "/" + parentdb + "/" + schema,
            featurePrefix: schema,
            srsName: "EPSG:900913",
            geometryName: geomField, // must be dynamamic
            defaultFilter: wfsFilter
        }),
        styleMap: styleMap
    });
    map.addLayers([layer]);
    layer.events.register("loadend", layer, function () {
        var count = layer.features.length;
        App.setAlert(App.STATUS_NOTICE, count + " features loaded");
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
        // We set the control to the second button in mapTools
        mapTools[0].control = drawControl;
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

    featureStore = new GeoExt.data.FeatureStore({
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
        stateful: false,
        store: featureStore,
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
        title: '<i class="fa fa-list"></i> ' + __("Attributes"),
        modal: false,
        layout: 'fit',
        initCenter: true,
        border: false,
        width: 500,
        height: 350,
        closeAction: 'hide',
        renderTo: 'mappanel',
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

function stopEdit() {
    "use strict";
    layerBeingEditing = null;
    try {
        filter.win.hide();
        filter.win = false;
    } catch (e) {
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
    return ar.filter(function onlyUnique(value, index, self) {
        return self.lastIndexOf(value) === index;
    })
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
                App.setAlert(App.STATUS_OK, message);
            }
            if (updated) {
                message = "<p>Updated: " + updated + "</p>";
                App.setAlert(App.STATUS_OK, message);
            }
            if (deleted) {
                message = "<p>Deleted: " + deleted + "</p>";
                App.setAlert(App.STATUS_OK, message);
            }
            writeFiles(false, map);
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

