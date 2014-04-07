/*global Ext:false */
/*global $:false */
/*global jQuery:false */
/*global OpenLayers:false */
/*global GeoExt:false */
/*global mygeocloud_ol:false */
/*global schema:false */
/*global window:false */
/*global document:false */

Ext.Ajax.disableCaching = false;
Ext.QuickTips.init();
var form, store, writeFiles, clearTileCache, updateLegend, activeLayer, onEditWMSClasses, onAdd, initExtent = null, App = new Ext.App({});
$(window).ready(function () {
    "use strict";
    Ext.Container.prototype.bufferResize = false;
    var winAdd, winAddSchema, winCartomobile, winMoreSettings, winGlobalSettings, fieldsForStore = {}, settings, groups, groupsStore;

    $.ajax({
        url: '/controllers/layer/columnswithkey',
        async: false,
        dataType: 'json',
        type: 'GET',
        success: function (data, textStatus, http) {
            if (http.readyState === 4) {
                if (http.status === 200) {
                    fieldsForStore = data.forStore;
                }
            }
        }
    });
    $.ajax({
        url: '/controllers/setting',
        async: false,
        dataType: 'json',
        type: 'GET',
        success: function (data, textStatus, http) {
            if (http.readyState === 4) {
                if (http.status === 200) {
                    settings = data.data;
                    $("#apikeyholder").html(settings.api_key);
                    if (typeof settings.extents !== "undefined") {
                        if (settings.extents[schema] !== undefined) {
                            initExtent = settings.extents[schema];
                        }
                    }
                }
            }
        }
    });
    // Write out mapfile and cfgfile
    $.ajax({
        dataType: 'json',
        async: false,
        url: '/controllers/mapfile',
        success: function (response) {
        }
    });
    $.ajax({
        dataType: 'json',
        async: false,
        url: '/controllers/cfgfile',
        success: function (response) {
        }
    });
    var writer = new Ext.data.JsonWriter({
        writeAllFields: false,
        encode: false
    });
    var reader = new Ext.data.JsonReader({
        successProperty: 'success',
        idProperty: '_key_',
        root: 'data',
        messageProperty: 'message'
    }, fieldsForStore);
    var onWrite = function (store, action, result, transaction, rs) {
        if (transaction.success) {
            groupsStore.load();
            writeFiles();
        }
    };
    var proxy = new Ext.data.HttpProxy({
        restful: true,
        api: {
            read: '/controllers/layer/records',
            update: '/controllers/layer/records',
            destroy: '/controllers/table/records'
        },
        listeners: {
            write: onWrite,
            exception: function (proxy, type, action, options, response, arg) {
                if (type === 'remote') {
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
        url: '/controllers/database/schemas'
    });
    schemasStore.load();

    var editor = new Ext.ux.grid.RowEditor();
    var grid = new Ext.grid.EditorGridPanel({
        //plugins: [editor],
        store: store,
        viewConfig: {
            forceFit: true
        },
        height: 300,
        split: true,
        region: 'north',
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
                {
                    header: "Name",
                    dataIndex: "f_table_name",
                    sortable: true,
                    editable: false,
                    tooltip: "This can't be changed",
                    width: 150,
                    flex: 1,
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
                    tooltip: "",
                    width: 250
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
                        decimalSeparator: '?'// Some strange char nobody is using
                    })
                },
                {
                    header: 'Authentication',
                    dataIndex: 'authentication',
                    width: 80,
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
                    xtype: 'checkcolumn',
                    header: 'Editable',
                    dataIndex: 'editable',
                    width: 50
                },
                {
                    header: 'Tile cache',
                    editable: false,
                    listeners: {
                        click: function () {
                            var r = grid.getSelectionModel().getSelected();
                            var layer = r.data.f_table_schema + "." + r.data.f_table_name;
                            //alert(layer);
                            Ext.MessageBox.confirm('Confirm', 'You are about to delete the tile cache for layer \'' + r.data.f_table_name + '\'. Are you sure?', function (btn) {
                                if (btn === "yes") {
                                    $.ajax({
                                        url: '/controllers/tilecache/index/' + layer,
                                        async: true,
                                        dataType: 'json',
                                        type: 'delete',
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
                text: '<i class="icon-camera btn-gc"></i> CartoMobile',
                handler: onEditCartomobile
            },
            {
                text: '<i class="icon-lock btn-gc"></i> Services',
                handler: onGlobalSettings
            },
            {
                text: '<i class="icon-cog btn-gc"></i> Advanced',
                handler: onEditMoreSettings
            },
            '->',
            {
                text: '<i class="icon-remove btn-g"></i> Clear all tile cache',
                handler: function () {
                    Ext.MessageBox.confirm('Confirm', 'You are about to delete the tile cache for the whole schema. Are you sure?', function (btn) {
                        if (btn === "yes") {
                            $.ajax({
                                url: '/controllers/tilecache/index/schema/' + schema,
                                dataType: 'json',
                                type: 'delete',
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
                handler: function () {
                    onAdd();
                }
            },
            '-',
            {
                text: '<i class="icon-trash btn-gc"></i> Delete layer',
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
                xtype: 'form',
                layout: 'hbox',
                width: 150,
                id: 'schemaform',
                items: [
                    {
                        xtype: 'textfield',
                        flex: 1,
                        name: 'schema',
                        emptyText: 'New schema',
                        allowBlank: false
                    }
                ]
            },
            {
                text: '<i class="icon-plus btn-gc"></i>',
                tooltip: 'Add new schema',
                handler: function () {
                    var f = Ext.getCmp('schemaform');
                    if (f.form.isValid()) {
                        f.getForm().submit({
                            url: '/controllers/database/schemas',
                            submitEmptyText: false,
                            success: function () {
                                schemasStore.reload();
                                App.setAlert(App.STATUS_OK, "New schema created");
                            },
                            failure: function (form, action) {
                                var result = action.result;
                                App.setAlert(App.STATUS_ERROR, result.message);
                            }
                        });
                    }
                }
            }
        ],
        listeners: {
            // Listensers here
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
            if (btn === "yes") {
                proxy.api.destroy.url = "/controllers/table/records/" + record.data.f_table_schema + "." + record.data.f_table_name;
                grid.store.remove(record);
                var s = Ext.getCmp("structurePanel");
                s.removeAll();
            } else {
                return false;
            }
        });
    }

    onAdd = function (btn, ev) {
        addShape.init();
        var p = new Ext.Panel({
                id: "uploadpanel",
                frame: false,
                layout: 'border',
                items: [new Ext.Panel({
                    region: "center"
                })]
            }),
            add = function () {
                addShape.init();
                var c = p.getComponent(0);
                c.remove(0);
                c.add(addShape.form);
                try {
                    c.doLayout();
                } catch (e) {
                }

            };
        winAdd = new Ext.Window({
            title: 'Add new layer',
            layout: 'fit',
            modal: true,
            width: 500,
            height: 385,
            closeAction: 'close',
            plain: true,
            items: [p],
            tbar: [
                {
                    text: 'Upload files',
                    id: 'addBtn',
                    handler: add
                },
                '-',
                {
                    text: 'Blank layer',
                    handler: function () {
                        addScratch.init();
                        var c = p.getComponent(0);
                        c.remove(0);
                        c.add(addScratch.form);
                        c.doLayout();
                    }
                }
            ]
        });
        winAdd.show(this);
        add();
    };

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
                    method: 'post',
                    labelWidth: 1,
                    frame: false,
                    border: false,
                    autoHeight: false,
                    region: 'center',
                    id: "schemaform",

                    defaults: {
                        allowBlank: false
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
                            text: '<i class="icon-plus btn-gc"></i> Add new schema',
                            handler: function () {
                                var f = Ext.getCmp('schemaform');
                                if (f.form.isValid()) {
                                    f.getForm().submit({
                                        url: '/controllers/database/schemas',
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

    function onEdit() {
        var record = grid.getSelectionModel().getSelected();
        if (!record) {
            App.setAlert(App.STATUS_NOTICE, "You\'ve to select a layer");
            return false;
        }
        tableStructure.grid = null;
        tableStructure.init(record, screenName);
        var s = Ext.getCmp("structurePanel");
        s.removeAll();
        s.add(tableStructure.grid);
        s.doLayout();
    }

    function onEditCartomobile(btn, ev) {
        var record = grid.getSelectionModel().getSelected();
        if (!record) {
            App.setAlert(App.STATUS_NOTICE, "You\'ve to select a layer");
            return false;
        }
        cartomobile.grid = null;
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
    }

    function onSave() {
        store.save();
    }

    onEditWMSClasses = function (e) {
        var record, markup;
        grid.getStore().each(function (rec) {  // for each row
            var row = rec.data; // get record
            if (row._key_ === e) {
                record = row;
            }
        });
        if (!record) {
            App.setAlert(App.STATUS_NOTICE, "You\'ve to select a layer");
            return false;
        }
        activeLayer = record.f_table_schema + "." + record.f_table_name;
        markup = [
            '<table style="margin-bottom: 7px"><tr class="x-grid3-row"><td>A SQL must return a primary key and a geometry. Naming and srid must match this: </td></tr></table>' +
                '<table>' +
                '<tr class="x-grid3-row"><td width="80"><b>Name</b></td><td  width="150">{f_table_schema}.{f_table_name}</td></tr>' +
                '<tr class="x-grid3-row"><td><b>Primary key</b></td><td  width="150">{pkey}</td></tr>' +
                '<tr class="x-grid3-row"><td><b>Srid</b></td><td>{srid}</td></tr>' +
                '<tr class="x-grid3-row"><td><b>Geom field</b></td><td>{f_geometry_column}</td></tr>' +
                '<tr class="x-grid3-row"><td><b>Geom type</b></td><td>{type}</td></tr>' +
                '</table>'
        ];
        var activeTab = Ext.getCmp("layerStyleTabs").getActiveTab();
        Ext.getCmp("layerStyleTabs").activate(1);
        updateLegend();

        Ext.getCmp("layerStyleTabs").activate(3);
        var template = new Ext.Template(markup);
        template.overwrite(Ext.getCmp('a5').body, record);
        Ext.getCmp("layerStylePanel").expand(true);
        var a1 = Ext.getCmp("a1");
        var a4 = Ext.getCmp("a4");
        a1.remove(wmsLayer.grid);
        a4.remove(wmsLayer.sqlForm);
        wmsLayer.grid = null;
        wmsLayer.sqlForm = null;
        wmsLayer.init(record);
        a1.add(wmsLayer.grid);
        a4.add(wmsLayer.sqlForm);
        a1.doLayout();
        a4.doLayout();

        Ext.getCmp("layerStyleTabs").activate(2);
        var a2 = Ext.getCmp("a2");
        a2.remove(wmsClasses.grid);
        wmsClasses.grid = null;
        wmsClasses.init(record);
        a2.add(wmsClasses.grid);
        a2.doLayout();
        var a3 = Ext.getCmp("a3");
        a3.remove(wmsClass.grid);
        a3.doLayout();

        Ext.getCmp("layerStyleTabs").activate(0);
        var a7 = Ext.getCmp("a7");
        a7.remove(classWizards.quantile);
        classWizards.init(record);
        a7.add(classWizards.quantile);
        a7.doLayout();

        Ext.getCmp("layerStyleTabs").activate(activeTab);
    };

    function onEditMoreSettings(btn, ev) {
        var record = grid.getSelectionModel().getSelected();
        if (!record) {
            App.setAlert(App.STATUS_NOTICE, "You\'ve to select a layer");
            return false;
        }
        var r = record;
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
                            name: 'not_querable',
                            fieldLabel: 'Not querable',
                            value: r.data.not_querable
                        }
                    ],
                    buttons: [
                        {
                            text: '<i class="icon-ok btn-gc"></i> Update',
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
    }

    function onGlobalSettings(btn, ev) {
        winGlobalSettings = new Ext.Window({
            title: "Services",
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
                        region: "center",
                        items: [httpAuth.form, apiKey.form]
                    }), new Ext.Panel({
                        layout: "border",
                        region: "east",
                        width: 400,
                        items: [
                            new Ext.Panel({
                                //title: 'WFS',
                                region: "north",
                                border: false,
                                height: 50,
                                bodyStyle: {
                                    background: '#ffffff',
                                    padding: '7px'
                                },
                                contentEl: "wfs-dialog"
                            }), new Ext.Panel({
                                //title: 'WMS',
                                border: false,
                                height: 50,
                                region: "center",
                                bodyStyle: {
                                    background: '#ffffff',
                                    padding: '7px'
                                },
                                contentEl: "wms-dialog"

                            }), new Ext.Panel({
                                //title: 'SQL',
                                height: 215,
                                border: false,
                                region: "south",
                                items: [
                                    new Ext.Panel({
                                        //title: 'SQL',
                                        height: 50,
                                        border: false,
                                        region: "north",
                                        bodyStyle: {
                                            background: '#ffffff',
                                            padding: '7px'
                                        },
                                        contentEl: "sql-dialog"

                                    }), new Ext.Panel({
                                        //title: 'elasticsearch',
                                        height: 60,
                                        border: false,
                                        region: "center",
                                        bodyStyle: {
                                            background: '#ffffff',
                                            padding: '7px'
                                        },
                                        contentEl: "elasticsearch-dialog"
                                    })]})]
                    })]
                })]});
        winGlobalSettings.show(this);
    }

    // define a template to use for the detail view
    var bookTplMarkup = ['<table>' +
        '<tr class="x-grid3-row"><td width="70"><b>Srid</b></td><td width="130">{srid}</td><td width="90"><b>Created</b></td><td>{created}</td></tr>' +
        '<tr class="x-grid3-row"><td><b>Geom field</b></td><td>{f_geometry_column}</td><td><b>Last modified</b></td><td>{lastmodified}</td>' +
        '</tr>' +
        '</table>'];
    var bookTpl = new Ext.Template(bookTplMarkup);
    var ct = new Ext.Panel({
        title: 'Database',
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
                background: '#777',
                color: '#fff',
                padding: '7px'
            }
        }, {
            id: 'structurePanel',
            region: 'center',
            collapsed: false,
            collapsible: false,
            border: false,
            layout: 'fit'
        }]
    });
    grid.getSelectionModel().on('rowselect', function (sm, rowIdx, r) {
        var detailPanel = Ext.getCmp('detailPanel');
        bookTpl.overwrite(detailPanel.body, r.data);
        onEdit();
    });


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
        } else {
            Ext.MessageBox.alert('Failure', result.message);
        }
    };
    var tabs = new Ext.TabPanel({
        activeTab: 0,
        region: 'center',
        plain: true,
        items: [
            {
                xtype: "panel",
                title: 'Map',
                layout: 'border',
                items: [
                    {
                        frame: false,
                        border: false,
                        id: "mapPane",
                        region: "center",
                        html: '<iframe frameborder="0" id="wfseditor" style="width:100%;height:100%" src="/editor/' + screenName + '/' + schema + '"></iframe>'
                    },
                    {
                        xtype: "panel",
                        autoScroll: true,
                        region: 'east',
                        collapsible: true,
                        collapsed: true,
                        id: "layerStylePanel",
                        width: 300,
                        frame: false,
                        plain: true,
                        border: true,
                        layoutConfig: {
                            animate: true
                        },
                        items: [
                            {
                                xtype: "tabpanel",
                                border: false,
                                id: "layerStyleTabs",
                                activeTab: 0,
                                plain: true,
                                items: [
                                    {
                                        xtype: "panel",
                                        title: 'Theme wizard',
                                        defaults: {
                                            border: false
                                        },
                                        height: 610,
                                        items: [
                                            {
                                                xtype: "panel",
                                                id: "a7",
                                                layout: "fit"
                                            }
                                        ]
                                    },
                                    {
                                        xtype: "panel",
                                        title: 'Legend',
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
                                            }
                                        ]
                                    },
                                    {
                                        xtype: "panel",
                                        title: 'Classes',
                                        defaults: {
                                            height: 150,
                                            border: false
                                        },
                                        items: [
                                            {
                                                xtype: "panel",
                                                id: "a2",
                                                layout: "fit"
                                            },
                                            {
                                                xtype: "panel",
                                                height: 360,
                                                id: "a3"

                                            }
                                        ]
                                    },
                                    {
                                        xtype: "panel",
                                        title: 'Settings',
                                        height: 600,
                                        defaults: {
                                            border: false
                                        },
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
                                                    background: '#ffffff',
                                                    padding: '10px'
                                                }
                                            }
                                        ]
                                    }
                                ]
                            }
                        ]
                    }
                ]},
            ct,
            {
                xtype: "panel",
                title: 'Log',
                layout: 'border',
                listeners: {
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
                    },
                    single: true
                },
                items: [
                    {
                        xtype: "panel",
                        autoScroll: true,
                        region: 'center',
                        frame: true,
                        plain: true,
                        border: true,
                        html: "<div id='gc-log'></div>"
                    }
                ]
            }
        ]
    });
    new Ext.Viewport({
        layout: 'border',
        items: [tabs]
    });

    writeFiles = function () {
        $.ajax({
            url: '/controllers/mapfile',
            success: function (response) {
                updateLegend();
            }
        });
        $.ajax({
            url: '/controllers/cfgfile',
            success: function (response) {
            }
        });
    };
    clearTileCache = function (layer) {
        $.ajax({
            url: '/controllers/tilecache/index/' + layer,
            async: true,
            dataType: 'json',
            type: 'delete',
            success: function (response) {
                if (response.success === true) {
                    App.setAlert(App.STATUS_NOTICE, response.message);
                    var l = document.getElementById("wfseditor").contentWindow.window.map.getLayersByName(layer)[0];
                    l.clearGrid();
                    var n = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                        var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
                        return v.toString(16);
                    });
                    l.url = l.url.replace(l.url.split("?")[1], "");
                    l.url = l.url + "token=" + n;
                    l.redraw();
                }
                else {
                    App.setAlert(App.STATUS_NOTICE, response.message);
                }
            }
        });
    };
    updateLegend = function () {
        var a6 = Ext.getCmp("a6");
        if (activeLayer !== undefined) {
            $.ajax({
                url: '/api/v1/legend/html/' + screenName + '/' + activeLayer.split(".")[0] + '?l=' + activeLayer,
                dataType: 'jsonp',
                jsonp: 'jsonp_callback',
                success: function (response) {
                    a6.update(response.html);
                    a6.doLayout();
                }
            });
        }
    };
});
