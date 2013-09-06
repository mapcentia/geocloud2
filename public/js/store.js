var form;
var store;
Ext.Ajax.disableCaching = false;
Ext.QuickTips.init();
var App = new Ext.App({});

// We need to use jQuery load function to make sure that document.namespaces are ready. Only IE
$(window).load(function () {
    "use strict";
    Ext.Container.prototype.bufferResize = false;
    var winAdd;
    var winAddSchema;
    var winEdit;
    var winCartomobile;
    var winClasses;
    var winWmsLayer;
    var winMoreSettings;
    var winGlobalSettings;
    var fieldsForStore;
    var settings;
    var groups;

    $.ajax({
        url: '/controller/tables/' + screenName + '/getcolumnswithkey/settings.geometry_columns_view',
        async: false,
        dataType: 'json',
        type: 'GET',
        success: function (data, textStatus, http) {
            if (http.readyState == 4) {
                if (http.status == 200) {
                    var response = eval('(' + http.responseText + ')');
                    // JSON
                    fieldsForStore = response.forStore;
                }
            }
        }
    });

    $.ajax({
        url: '/controller/settings_viewer/' + screenName + '/get',
        async: false,
        dataType: 'json',
        type: 'GET',
        success: function (data, textStatus, http) {
            if (http.readyState == 4) {
                if (http.status == 200) {
                    var response = eval('(' + http.responseText + ')');
                    // JSON
                    settings = response.data;
                    $("#apikeyholder").html(settings.api_key)
                }
            }
        }
    });
    var writer = new Ext.data.JsonWriter({
        writeAllFields: false,
        encode: false
    });
    var reader = new Ext.data.JsonReader({
        //totalProperty: 'total',
        successProperty: 'success',
        idProperty: '_key_',
        root: 'data',
        messageProperty: 'message'
        // <-- New "messageProperty" meta-data
    }, fieldsForStore);
    var onWrite = function (store, action, result, transaction, rs) {
        // console.log('onwrite', store, action, result, transaction, rs);
        if (transaction.success) {
            groupsStore.load();
        }
    };
    var proxy = new Ext.data.HttpProxy({
        api: {
            read: '/controller/tables/' + screenName + '/getrecords/settings.geometry_columns_view',
            update: '/controller/tables/' + screenName + '/updaterecord/settings.geometry_columns_join/_key_',
            destroy: '/controller/tables/' + screenName + '/destroy'
        },
        listeners: {
            write: onWrite,
            exception: function (proxy, type, action, options, response, arg) {
                if (type === 'remote') {
                    // success is false
                    //alert(response.message);
                    var message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows=5' cols='31'>" + response.message + "</textarea>";
                    Ext.MessageBox.show({
                        title: 'Failure',
                        msg: message,
                        buttons: Ext.MessageBox.OK,
                        width: 300,
                        height: 300
                    });
                }
            }
        }
    });

    store = new Ext.data.Store({
        writer: writer,
        reader: reader,
        proxy: proxy,
        autoSave: true
    });
    store.load();

    var groupsStore = new Ext.data.Store({
        reader: new Ext.data.JsonReader({
            successProperty: 'success',
            root: 'data'
        }, [
            {
                "name": "group"
            }
        ]),
        url: '/controller/tables/' + screenName + '/getgroupby/settings.geometry_columns_view/layergroup'
    });
    groupsStore.load();
    var schemasStore = new Ext.data.Store({
        reader: new Ext.data.JsonReader({
            successProperty: 'success',
            root: 'data'
        }, [
            {
                "name": "schema"
            }
        ]),
        url: '/controller/databases/' + screenName + '/getschemas'
    });
    schemasStore.load();

    // create a grid to display records from the store
    var grid = new Ext.grid.EditorGridPanel({
        //title: "Layers in your geocloud",
        store: store,
        autoExpandColumn: "desc",
        height: 400,
        split: true,
        region: 'center',
        frame: false,
        border: false,
        sm: new Ext.grid.RowSelectionModel({
            singleSelect: true
        }),
        cm: new Ext.grid.ColumnModel({
            defaults: {
                sortable: true,
                editor: {
                    xtype: "textfield"

                }
            },
            columns: [
                /*{
                 header: "_Key_",
                 dataIndex: "_key_",
                 sortable: true,
                 editable: false,
                 width: 150
                 },*/
                {
                    header: "Name",
                    dataIndex: "f_table_name",
                    sortable: true,
                    editable: false,
                    tooltip: "This can't be changed",
                    width: 150,
                    renderer: function (v, p) {
                        return v;
                    }
                },
                {
                    header: "Type",
                    dataIndex: "type",
                    sortable: true,
                    editable: false,
                    tooltip: "This can't be changed",
                    width: 150
                },
                {
                    header: "Title",
                    dataIndex: "f_table_title",
                    sortable: true,
                    width: 150
                },
                {
                    id: "desc",
                    header: "Description",
                    dataIndex: "f_table_abstract",
                    sortable: true,
                    editable: true,
                    tooltip: ""
                },
                {
                    header: 'Group',
                    dataIndex: 'layergroup',
                    sortable: true,
                    editable: true,
                    width: 150,
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
                    header: 'Sort id',
                    dataIndex: 'sort_id',
                    sortable: true,
                    editable: true,
                    width: 55,
                    editor: new Ext.form.NumberField({
                        decimalPrecision: 0,
                        decimalSeparator: '?'// Some strange char nobody is
                        // using
                    })
                },
                {
                    xtype: 'checkcolumn',
                    header: 'Editable',
                    dataIndex: 'editable',
                    width: 55
                },
                {
                    header: 'Authentication',
                    dataIndex: 'authentication',
                    width: 120,
                    tooltip: 'When accessing your layer from external clients, which level of authentication do you want?',
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
                    header: 'Tile cache',
                    dataIndex: 'tilecache',
                    editable: false,
                    listeners: {
                        click: function () {
                            var r = grid.getSelectionModel().getSelected();
                            var layer = r.data.f_table_schema + "." + r.data.f_table_name;
                            //alert(layer);
                            Ext.MessageBox.confirm('Confirm', 'You are about to invalidate the tile cache for layer \'' + r.data.f_table_name + '\'. Are you sure?', function (btn) {
                                if (btn === "yes") {
                                    $.ajax({
                                        url: '/controller/clear_tile_cache/' + screenName + '/' + layer,
                                        async: true,
                                        dataType: 'json',
                                        type: 'GET',
                                        success: function (data, textStatus, http) {
                                            if (http.readyState == 4) {
                                                if (http.status == 200) {
                                                    var response = eval('(' + http.responseText + ')');
                                                    if (response.success === true) {
                                                        App.setAlert(App.STATUS_NOTICE, response.message);
                                                    } else {
                                                        App.setAlert(App.STATUS_NOTICE, response.message);
                                                    }
                                                }
                                            }
                                        }
                                    });
                                } else {
                                    return false;
                                }
                            });

                        }
                    },
                    renderer: function (value, id, r) {
                        return ('<a href="#">Clear</a>');
                    },
                    width: 70
                }
            ]
        }),
        tbar: [
            {
                text: '<i class="icon-wrench btn-gc"></i> Layer settings',
                //iconCls : 'silk-layers', // <-- icon
                menu: new Ext.menu.Menu({
                    id: 'mainMenu',
                    style: {
                        overflow: 'visible' // For the Combo popup
                    },
                    items: [
                        {
                            text: 'Settings',
                            iconCls: 'icon-edit',
                            handler: onEditWMSLayer
                        },
                        {
                            text: 'Styles',
                            iconCls: 'icon-map-marker',
                            handler: onEditWMSClasses
                        },
                        {
                            text: 'Table structure',
                            iconCls: 'icon-th',
                            handler: onEdit
                        },
                        {
                            text: 'CartoMobile',
                            iconCls: 'icon-camera',
                            handler: onEditCartomobile
                        },
                        {
                            text: 'Advanced',
                            iconCls: 'icon-cog',
                            handler: onEditMoreSettings
                        }
                    ]
                })
            },
            '-',
            {
                text: '<i class="icon-lock btn-gc""></i> Authentication',
                //iconCls : 'icon-search',
                handler: onGlobalSettings
            },
            '->',
            {
                text: '<i class="icon-remove btn-gc""></i> Clear all tile cache',
                //iconCls : 'icon-search',
                handler: function () {
                    Ext.MessageBox.confirm('Confirm', 'You are about to invalidate the tile cache for the whole schema. Are you sure?', function (btn) {
                        if (btn === "yes") {
                            $.ajax({
                                url: '/controller/clear_tile_cache/' + screenName + '/schema/' + schema,
                                async: true,
                                dataType: 'json',
                                type: 'GET',
                                success: function (data, textStatus, http) {
                                    if (http.readyState == 4) {
                                        if (http.status == 200) {
                                            var response = eval('(' + http.responseText + ')');
                                            if (response.success === true) {
                                                App.setAlert(App.STATUS_OK, response.message);
                                            } else {
                                                App.setAlert(App.STATUS_NOTICE, response.message);
                                            }
                                        }
                                    }
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
                text: '<i class="icon-plus btn-gc"></i> New layer',
                //iconCls : 'icon-plus',
                handler: onAdd
            },
            '-',
            {
                text: '<i class="icon-trash btn-gc"></i> Delete layer',
                //iconCls : 'silk-delete',
                handler: onDelete
            },
            '-',
            new Ext.form.ComboBox({
                id: "schemabox",
                store: schemasStore,
                displayField: 'schema',
                editable: false,
                mode: 'local',
                triggerAction: 'all',
                emptyText: 'Select a cloud...',
                value: schema,
                width: 135
            }),
            {
                text: '<i class="icon-plus btn-gc"></i>',
                tooltip: 'Add new schema',
                //iconCls : 'silk-add',
                handler: onAddSchema
            }
        ],
        listeners: {
            // rowdblclick: mapPreview
        }
    });
    Ext.getCmp("schemabox").on('select', function (e) {
        window.location = "/store/" + screenName + "/" + e.value;
    });
    function onDelete() {
        var record = grid.getSelectionModel().getSelected();
        if (!record) {
            App.setAlert(App.STATUS_NOTICE, "You\'ve to select a layer");
            return false;
        }
        Ext.MessageBox.confirm('Confirm', 'Are you sure you want to do that?', function (btn) {
            if (btn == "yes") {
                proxy.api.destroy.url = "/controller/tables/" + screenName + "/destroy/" + schema + "." + record.data.f_table_name;
                grid.store.remove(record);
            } else {
                return false;
            }
        });
    }

    function onAdd(btn, ev) {
        winAdd = null;
        var p = new Ext.Panel({
            id: "uploadpanel",
            frame: false,
            //width: 500,
            //height: 400,
            layout: 'border',
            items: [new Ext.Panel({
                region: "center"
            })]
        });
        winAdd = new Ext.Window({
            title: 'Add new layer',
            layout: 'fit',
            modal: true,
            width: 500,
            height: 350,
            closeAction: 'close',
            plain: true,
            items: [p],
            tbar: [
                {
                    text: 'Blank layer',
                    //iconCls: 'silk-add',
                    handler: function () {
                        addScratch.init();
                        var c = p.getComponent(0);
                        c.remove(0);
                        c.add(addScratch.form);
                        c.doLayout();
                    }
                },
                '-',
                {
                    text: 'Esri Shape',
                    //iconCls: 'silk-add',
                    handler: function () {
                        addShape.init();
                        var c = p.getComponent(0);
                        c.remove(0);
                        c.add(addShape.form);
                        c.doLayout();
                    }
                },
                '-',
                {
                    text: 'GML',
                    disabled: true,
                    //iconCls: 'silk-add',
                    tooltip: "Coming in beta",
                    handler: function () {
                        addGml.init();
                        var c = p.getComponent(0);
                        c.remove(0);
                        c.add(addGml.form);
                        c.doLayout();
                    }
                },
                '-',
                {
                    text: 'MapInfo TAB',
                    disabled: false,
                    tooltip: "Coming in beta",
                    handler: function () {
                        addMapInfo.init();
                        var c = p.getComponent(0);
                        c.remove(0);
                        c.add(addMapInfo.form);
                        c.doLayout();
                    }
                }
            ]
        });

        winAdd.show(this);
    }

    function onAddSchema(btn, ev) {
        winAddSchema = new Ext.Window({
            title: 'Add new schema',
            height: 170,
            modal: true,
            items: [new Ext.Panel({
                layout: 'border',
                width: 320,
                height: 150,
                closeAction: 'close',
                border: false,
                frame: true,
                items: [new Ext.FormPanel({
                    labelWidth: 1,
                    frame: false,
                    border: false,
                    autoHeight: false,
                    region: 'center',
                    id: "schemaform",

                    defaults: {
                        allowBlank: false
                        //msgTarget: 'side'
                    },
                    items: [
                        {
                            width: 293,
                            xtype: 'textfield',
                            name: 'schema',
                            emptyText: 'Name of new schema'
                        }
                    ],
                    buttons: [
                        {
                            //iconCls : 'silk-add',
                            text: '<i class="icon-plus btn-gc"></i> Add new schema',
                            handler: function () {
                                var f = Ext.getCmp('schemaform');
                                if (f.form.isValid()) {
                                    f.getForm().submit({
                                        url: '/controller/databases/' + screenName + '/addschema',
                                        submitEmptyText: false,
                                        waitMsg: 'Creating schema',
                                        success: function () {
                                            schemasStore.reload();
                                            App.setAlert(App.STATUS_OK, "New schema created");
                                        },
                                        failure: function (form, action) {
                                            var result = action.result;
                                            App.setAlert(App.STATUS_ERROR, result.message);
                                        }
                                    });
                                } else {
                                    var s = '';
                                    Ext.iterate(schemaForm.form.getValues(), function (key, value) {
                                        s += String.format("{0} = {1}<br />", key, value);
                                    }, this);
                                }
                            }
                        }
                    ]
                }), new Ext.Panel({
                    region: "south",
                    border: false,
                    bodyStyle: "padding : 7px",

                    height: 50,
                    html: "Schemas are used to organize tables into logical groups to make them more manageable."
                })]

            })]
        });

        winAddSchema.show(this);
    }

    function onSpatialEdit(btn, ev) {
        var url = "/editor/" + screenName + "/" + schema;
        window.open(url, 'editor');

    }

    function onView(btn, ev) {
        var url = "/apps/viewer/map_list_frame/" + screenName + "/" + schema;
        window.open(url, 'viewer', 'width=1000,height=800');
    }

    function onEdit(btn, ev) {
        var record = grid.getSelectionModel().getSelected();
        if (!record) {
            App.setAlert(App.STATUS_NOTICE, "You\'ve to select a layer");
            return false;
        }

        tableStructure.grid = null;
        winEdit = null;
        tableStructure.init(record, screenName);
        form = new Ext.FormPanel({
            labelWidth: 100,
            // label settings here cascade unless overridden
            frame: true,
            region: 'center',
            title: 'Add new column',
            items: [
                {
                    xtype: 'textfield',
                    flex: 1,
                    name: 'column',
                    fieldLabel: 'Column',
                    allowBlank: false
                },
                {
                    width: 100,
                    xtype: 'combo',
                    mode: 'local',
                    triggerAction: 'all',
                    forceSelection: true,
                    editable: false,
                    fieldLabel: 'Type',
                    name: 'type',
                    displayField: 'name',
                    valueField: 'value',
                    allowBlank: false,
                    store: new Ext.data.JsonStore({
                        fields: ['name', 'value'],
                        data: [
                            {
                                name: 'String',
                                value: 'string'
                            },
                            {
                                name: 'Integer',
                                value: 'int'
                            },
                            {
                                name: 'Decimal',
                                value: 'float'
                            },
                            {
                                name: 'Text',
                                value: 'text'
                            },
                            {
                                name: 'Geometry',
                                value: 'geometry'
                            }
                        ]
                    })
                }
            ],
            buttons: [
                {
                    //iconCls : 'silk-add',
                    text: '<i class="icon-plus btn-gc"></i> Add new column',
                    handler: function () {
                        if (form.form.isValid()) {
                            form.getForm().submit({
                                url: '/controller/tables/' + screenName + '/createcolumn/' + schema + '.' + record.get("f_table_name"),
                                waitMsg: 'Saving Data...',
                                submitEmptyText: false,
                                success: onSubmit,
                                failure: onSubmit
                            });
                        } else {
                            var s = '';
                            Ext.iterate(form.form.getValues(), function (key, value) {
                                s += String.format("{0} = {1}<br />", key, value);
                            }, this);
                            //Ext.example.msg('Form Values', s);
                        }
                    }
                }
            ]
        });
        winEdit = new Ext.Window({
            title: "Structure for the layer '" + record.get("f_table_name") + "'",
            modal: true,
            layout: 'fit',
            width: 800,
            height: 500,
            initCenter: false,
            x: 100,
            y: 100,
            closeAction: 'close',
            plain: true,
            frame: true,
            border: false,
            items: [new Ext.Panel({
                frame: false,
                border: false,
                layout: 'border',
                items: [tableStructure.grid, form]
            })]
        });
        winEdit.show(this);
    }

    function onEditCartomobile(btn, ev) {
        var record = grid.getSelectionModel().getSelected();
        if (!record) {
            App.setAlert(App.STATUS_NOTICE, "You\'ve to select a layer");
            return false;
        }

        cartomobile.grid = null;
        winCartomobile = null;
        cartomobile.init(record, screenName);
        winCartomobile = new Ext.Window({
            title: "CartoMobile settings for the layer '" + record.get("f_table_name") + "'",
            modal: true,
            layout: 'fit',
            width: 750,
            height: 350,
            initCenter: false,
            x: 100,
            y: 100,
            closeAction: 'close',
            plain: true,
            frame: true,
            border: false,
            items: [new Ext.Panel({
                frame: false,
                border: false,
                layout: 'border',
                items: [cartomobile.grid]
            })]
        });
        winCartomobile.show(this);
    };
    function onSave() {
        store.save();
    }

    function onEditWMSClasses(btn, ev) {
        var record = grid.getSelectionModel().getSelected();
        if (!record) {
            App.setAlert(App.STATUS_NOTICE, "You\'ve to select a layer");
            return false;
        }
        wmsClasses.grid = null;
        wmsClasses.init(record);
        winClasses = null;
        winClasses = new Ext.Window({
            title: "Edit classes '" + record.get("f_table_name") + "'",
            modal: true,
            layout: 'fit',
            width: 500,
            height: 400,
            initCenter: false,
            x: 50,
            y: 100,
            closeAction: 'close',
            plain: true,
            border: false,
            items: [wmsClasses.grid]

        });
        winClasses.show(this);
    }

    function onEditWMSLayer(btn, ev) {
        var record = grid.getSelectionModel().getSelected();
        if (!record) {
            App.setAlert(App.STATUS_NOTICE, "You\'ve to select a layer");
            return false;
        }
        wmsLayer.grid = null;
        winWmsLayer = null;
        wmsLayer.init(record);
        winWmsLayer = new Ext.Window({
            title: "Settings on layer '" + record.get("f_table_name") + "'",
            modal: true,
            layout: 'fit',
            width: 500,
            height: 400,
            closeAction: 'close',
            plain: true,
            border: true,
            items: [wmsLayer.grid]
        });
        winWmsLayer.show(this);
    };
    function onEditMoreSettings(btn, ev) {
        var record = grid.getSelectionModel().getSelected();
        if (!record) {
            App.setAlert(App.STATUS_NOTICE, "You\'ve to select a layer");
            return false;
        }
        var r = record;
        winMoreSettings = null;
        //wmsLayer.init(record.get("f_table_name"));
        winMoreSettings = new Ext.Window({
            title: "More settings on '" + record.get("f_table_name") + "'",
            modal: true,
            layout: 'fit',
            width: 500,
            height: 400,
            closeAction: 'close',
            plain: true,
            items: [new Ext.Panel({
                frame: false,
                border: false,
                width: 500,
                height: 400,
                layout: 'border',
                items: [new Ext.FormPanel({
                    labelWidth: 100,
                    // label settings here cascade unless overridden
                    frame: false,
                    border: false,
                    region: 'center',
                    id: "detailform",
                    bodyStyle: 'padding: 10px 10px 0 10px;',
                    items: [
                        {
                            name: '_key_',
                            xtype: 'hidden',
                            value: r.data._key_

                        },
                        {
                            width: 300,
                            xtype: 'textfield',
                            fieldLabel: 'Meta data URL',
                            name: 'meta_url',
                            value: r.data.meta_url

                        },
                        {
                            width: 300,
                            xtype: 'textfield',
                            fieldLabel: 'WMS source',
                            name: 'wmssource',
                            value: r.data.wmssource
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
                            fieldLabel: 'Not querable',
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
                            name: 'single_tile',
                            fieldLabel: 'Single tile',
                            value: r.data.single_tile
                        },
                        {
                            width: 300,
                            xtype: 'textfield',
                            fieldLabel: 'Local data source',
                            name: 'data',
                            value: r.data.data

                        },
                        {
                            width: 300,
                            xtype: 'textfield',
                            fieldLabel: 'Filter',
                            name: 'filter',
                            value: r.data.filter

                        }
                    ],
                    buttons: [
                        {
                            //iconCls : 'silk-add',
                            text: '<i class="icon-ok btn-gc"></i> Update',
                            handler: function () {
                                var f = Ext.getCmp('detailform');
                                if (f.form.isValid()) {
                                    var values = f.form.getValues();
                                    var param = {
                                        data: values
                                    };
                                    param = Ext.util.JSON.encode(param);
                                    Ext.Ajax.request({
                                        url: '/controller/tables/' + screenName + '/updaterecord/settings.geometry_columns_join/_key_',
                                        headers: {
                                            'Content-Type': 'application/json; charset=utf-8'
                                        },
                                        params: param,
                                        success: function () {
                                            store.reload();
                                            groupsStore.load();
                                            App.setAlert(App.STATUS_NOTICE, "Settings updated");
                                        }
                                        //failure: test
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
    };
    function onGlobalSettings(btn, ev) {
        winGlobalSettings = null;
        winGlobalSettings = new Ext.Window({
            title: "Authentication",
            modal: true,
            width: 700,
            height: 350,
            initCenter: true,
            closeAction: 'hide',
            border: false,
            layout: 'border',
            items: [
                new Ext.Panel({
                    border: false,
                    layout: 'border',
                    region: "center",
                    items: [new Ext.Panel({
                        //title: 'Authentication',
                        region: "center",
                        items: [httpAuth.form, apiKey.form]
                    }), new Ext.Panel({
                        layout: "border",
                        region: "east",
                        width: 400,
                        items: [new Ext.Panel({
                            //title: 'WFS',
                            region: "north",
                            border: false,
                            height: 100,
                            bodyStyle: {
                                background: '#ffffff',
                                padding: '7px'
                            },
                            contentEl: "wfs-dialog"
                        }), new Ext.Panel({
                            //title: 'WMS',
                            border: false,
                            height: 100,
                            region: "center",
                            bodyStyle: {
                                background: '#ffffff',
                                padding: '7px'
                            },
                            contentEl: "wms-dialog"

                        }), new Ext.Panel({
                            //title: 'SQL',
                            height: 120,
                            border: false,
                            region: "south",
                            bodyStyle: {
                                background: '#ffffff',
                                padding: '7px'
                            },
                            contentEl: "sql-dialog"

                        })]})]
                })]
        });
        winGlobalSettings.show(this);
    };
    function onSave() {
        store.save();
    }

    // define a template to use for the detail view
    var bookTplMarkup = ['<table>' + '<tr class="x-grid3-row"><td width="80">Srid:</td><td  width="150">{srid}</td><td>Created:</td><td>{created}</td></tr>' + '<tr class="x-grid3-row"><td>Geom field</td><td>{f_geometry_column}</td><td>Last modified:</td><td>{lastmodified}</td>' + '</tr>' + '</table>'];
    var bookTpl = new Ext.Template(bookTplMarkup);
    var ct = new Ext.Panel({
        title: 'Layers test',
        frame: false,
        layout: 'border',
        region: 'center',
        border: true,
        split: true,
        items: [grid, {
            id: 'detailPanel',
            region: 'south',
            border: false,
            height: 70,
            bodyStyle: {
                background: '#ffffff',
                padding: '7px'
            }
        }]
    });
    grid.getSelectionModel().on('rowselect', function (sm, rowIdx, r) {
        var detailPanel = Ext.getCmp('detailPanel');
        bookTpl.overwrite(detailPanel.body, r.data);
    });

    var onSubmit = function (form, action) {
        var result = action.result;
        if (result.success) {
            tableStructure.store.load();
            form.reset();
        } else {
            var message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows=5' cols='31'>" + result.message + "</textarea>";
            Ext.MessageBox.show({
                title: 'Failure',
                msg: message,
                buttons: Ext.MessageBox.OK,
                width: 300,
                height: 300
            });
        }
    };
    Ext.namespace('viewerSettings');
    if (schema === "" || schema === "public") {
        viewerSettings.form = new Ext.FormPanel({
            title: "Global settings",
            frame: false,
            border: false,
            autoHeight: false,
            labelWidth: 100,
            bodyStyle: 'padding: 7px 7px 10px 7px;',
            defaults: {
                anchor: '95%',
                allowBlank: false,
                msgTarget: 'side'
            },
            items: [new Ext.Panel({
                frame: false,
                border: false,
                bodyStyle: 'padding: 7px 7px 10px 7px;',
                contentEl: "map-settings"
            }), {
                width: 10,
                xtype: 'combo',
                mode: 'local',
                triggerAction: 'all',
                forceSelection: true,
                editable: false,
                fieldLabel: 'Extent layer',
                name: 'default_extent',
                displayField: 'f_table_name',
                valueField: 'f_table_name',
                allowBlank: true,
                store: store,
                value: settings.default_extent

            }, {
                xtype: 'numberfield',
                name: 'minzoomlevel',
                fieldLabel: 'Min zoom level',
                allowBlank: true,
                minValue: 1,
                maxValue: 20,
                value: settings.minzoomlevel
            }, {
                xtype: 'numberfield',
                name: 'maxzoomlevel',
                fieldLabel: 'Max zoom level',
                allowBlank: true,
                minValue: 1,
                maxValue: 20,
                value: settings.maxzoomlevel
            }],
            buttons: [
                {
                    text: 'Update',
                    handler: function () {
                        if (viewerSettings.form.getForm().isValid()) {
                            viewerSettings.form.getForm().submit({
                                url: '/controller/settings_viewer/' + screenName + '/update',
                                waitMsg: 'Saving your settings',
                                success: viewerSettings.onSubmit,
                                failure: viewerSettings.onSubmit
                            });
                        }
                    }
                }
            ]
        });
    } else {
        viewerSettings.form = new Ext.Panel({
            title: "Global settings",
            frame: false,
            border: false,
            bodyStyle: 'padding: 7px 7px 10px 7px;',
            html: "<p>You can only set global settings from the 'public schema.'</p>"
        });
    }
    viewerSettings.onSubmit = function (form, action) {
        var result = action.result;
        if (result.success) {
            Ext.MessageBox.alert('Success', result.message);
            //viewerSettings.form.reset();
        } else {
            Ext.MessageBox.alert('Failure', result.message);
        }
    };
    var tabs = new Ext.TabPanel({
        activeTab: 0,
        region: 'center',
        items: [ct, {
            title: 'Map',
            html: '<iframe frameborder="0" style="width:100%;height:100%" src="/editor/' + screenName + '/' + schema + '"></iframe>'
        }]
    });
    var viewport = new Ext.Viewport({
        layout: 'border',
        items: [tabs]
    });
});
