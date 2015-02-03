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

Ext.Ajax.disableCaching = false;
Ext.QuickTips.init();
var form, store, writeFiles, clearTileCache, updateLegend, activeLayer, onEditWMSClasses, onAdd, onMove, onSchemaRename, onSchemaDelete, resetButtons, initExtent = null, App = new Ext.App({}), updatePrivileges, settings;
$(window).ready(function () {
    "use strict";
    Ext.Container.prototype.bufferResize = false;
    var winAdd, winMoreSettings, fieldsForStore = {}, groups, groupsStore, subUsers;

    $.ajax({
        url: '/controllers/layer/columnswithkey',
        async: false,
        dataType: 'json',
        success: function (data) {
            fieldsForStore = data.forStore;
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
        type: 'json',
        api: {
            read: '/controllers/layer/records',
            update: '/controllers/layer/records',
            destroy: '/controllers/table/records'
        },
        listeners: {
            write: onWrite,
            exception: function (proxy, type, action, options, response, arg) {
                if (type === 'remote') {
                    var message = "<p>" + __("Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong") + "</p><br/><textarea rows=5' cols='31'>" + __(response.message) + "</textarea>";
                    Ext.MessageBox.show({
                        title: 'Failure',
                        msg: message,
                        buttons: Ext.MessageBox.OK,
                        width: 300,
                        height: 300
                    });
                } else {
                    store.reload();
                    Ext.MessageBox.show({
                        title: 'Not allowed',
                        msg: __(Ext.decode(response.responseText).message),
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
    //var editor = new Ext.ux.grid.RowEditor();
    var grid = new Ext.grid.EditorGridPanel({
        //plugins: [editor],
        store: store,
        viewConfig: {
            forceFit: true,
            stripeRows: true,
            getRowClass: function(record) {
                return record.json.isview ? 'isview' : null;
            }
        },
        height: 300,
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
                }
            },
            columns: [
                {
                    header: __("Name"),
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
                    header: __("Type"),
                    dataIndex: "type",
                    sortable: true,
                    editable: false,
                    tooltip: "This can't be changed",
                    width: 150
                },
                {
                    header: __("Title"),
                    dataIndex: "f_table_title",
                    sortable: true,
                    width: 150
                },
                {
                    id: "desc",
                    header: __("Description"),
                    dataIndex: "f_table_abstract",
                    sortable: true,
                    editable: true,
                    tooltip: "",
                    width: 250
                },
                {
                    header: __("Group"),
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
                    header: __("Sort id"),
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
                    header: __("Authentication"),
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
                    header: __("Editable"),
                    dataIndex: 'editable',
                    width: 50
                },
                {
                    header: __("Tile cache"),
                    editable: false,
                    listeners: {
                        click: function () {
                            var r = grid.getSelectionModel().getSelected();
                            var layer = r.data._key_;
                            Ext.MessageBox.confirm(__('Confirm'), __("You are about to delete the tile cache for layer") + " '" + r.data.f_table_name + "'. " + __("Are you sure?"), function (btn) {
                                if (btn === "yes") {
                                    Ext.Ajax.request({
                                        url: '/controllers/tilecache/index/' + layer,
                                        method: 'delete',
                                        headers: {
                                            'Content-Type': 'application/json; charset=utf-8'
                                        },
                                        success: function () {
                                            store.reload();
                                            App.setAlert(App.STATUS_OK, __("Tile cache deleted"));
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
                    renderer: function (value, id, r) {
                        return ('<a href="#">' + __("Clear") + '</a>');
                    },
                    width: 70
                }
            ]
        }),
        tbar: [
            {
                text: '<i class="icon-user btn-gc"></i> ' + __('Privileges'),
                id: 'privileges-btn',
                handler: onPrivileges,
                disabled: true

            },
            {
                text: '<i class="icon-camera btn-gc"></i> ' + __('CartoMobile'),
                handler: onEditCartomobile,
                id: 'cartomobile-btn',
                disabled: true
            },
            {
                text: '<i class="icon-cog btn-gc"></i> ' + __('Advanced'),
                handler: onEditMoreSettings,
                id: 'advanced-btn',
                disabled: true
            },
            {
                text: '<i class="icon-lock btn-gc"></i> ' + __('Services'),
                handler: onGlobalSettings

            },

            {
                text: '<i class="icon-remove btn-gc"></i> ' + __('Clear tile cache'),
                disabled: (subUser === schema || subUser === false) ? false : true,
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
                text: '<i class="icon-plus btn-gc"></i> ' + __('New layer'),
                disabled: (subUser === schema || subUser === false) ? false : true,
                handler: function () {
                    onAdd();
                }
            },
            '-',
            {
                text: '<i class="icon-arrow-right btn-gc"></i> ' + __('Move layers'),
                disabled: true,
                id: 'movelayer-btn',
                handler: function () {
                    onMove();
                }
            },
            '-',
            {
                text: '<i class="icon-retweet btn-gc"></i> ' + __('Rename layer'),
                disabled: true,
                id: 'renamelayer-btn',
                handler: function () {
                    onRename();
                }
            },
            '-',
            {
                text: '<i class="icon-trash btn-gc"></i> ' + __('Delete layers'),
                disabled: true,
                id: 'deletelayer-btn',
                handler: function () {
                    onDelete();
                }
            },
            '-',
            {
                text: '<i class="icon-th btn-gc"></i> ' + __('Schema'),
                disabled: subUser ? true : false,
                menu: new Ext.menu.Menu({
                    items: [
                        {
                            text: __('Rename schema'),
                            handler: function () {
                                onSchemaRename();
                            }
                        },
                        {
                            text: __('Delete schema'),
                            handler: function () {
                                onSchemaDelete();
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
                text: '<i class="icon-plus btn-gc"></i>',
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
            'mouseover' : {
                fn: function(){
                },
                scope: this
            }
        }
    });
    Ext.getCmp("schemabox").on('select', function (e) {
        window.location = "/store/" + screenName + "/" + e.value;
    });

    function onRename() {
        var record = grid.getSelectionModel().getSelected();
        if (!record) {
            App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
            return false;
        }
        var winTableRename = new Ext.Window({
            title: __("Rename table") + " '" + record.data.f_table_name + "'",
            modal: true,
            layout: 'fit',
            width: 270,
            height: 80,
            closeAction: 'close',
            plain: true,
            items: [
                {
                    defaults: {
                        border: false
                    },
                    layout: 'hbox',
                    items: [
                        {
                            xtype: "form",
                            id: "tableRenameForm",
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
                                                    document.getElementById("wfseditor").contentWindow.window.cloud.removeTileLayerByName([
                                                        [name]
                                                    ]);
                                                    document.getElementById("wfseditor").contentWindow.window.reLoadTree();
                                                    store.reload();
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

    function onDelete() {
        var records = grid.getSelectionModel().getSelections();
        if (records.length === 0) {
            App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
            return false;
        }
        Ext.MessageBox.confirm(__('Confirm'), __('Are you sure you want to delete') + ' ' + records.length + ' ' + __('table(s)') + '?', function (btn) {
            if (btn === "yes") {
                var tables = [];
                Ext.iterate(records, function (v) {
                    tables.push(v.data.f_table_schema + "." + v.get("f_table_name"));
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

    onAdd = function (btn, ev) {
        addShape.init();
        var p = new Ext.Panel({
                id: "uploadpanel",
                frame: false,
                border: false,
                layout: 'border',
                items: [new Ext.Panel({
                    region: "center"
                })]
            }),
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
            };
        winAdd = new Ext.Window({
            title: __('New layer'),
            layout: 'fit',
            modal: true,
            width: 550,
            height: 390,
            closeAction: 'close',
            plain: true,
            items: [p],
            tbar: [
                {
                    text: __('Add vector'),
                    handler: addVector
                },
                '-',
                {
                    text: __('Add raster'),
                    handler: addRaster
                },
                '-',
                {
                    text: __('Add imagery'),
                    handler: addImage
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
                    }
                },
                '-',
                {
                    text: __('OSM view'),
                    disabled: (window.gc2Options.osmConfig === null) ? true : false,
                    handler: function () {
                        addOsm.init();
                        var c = p.getComponent(0);
                        c.remove(0);
                        c.add(addOsm.form);
                        c.doLayout();
                    }
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
                    }
                }
            ]
        });

        winAdd.show(this);
        addVector();
    };

    onMove = function (btn, ev) {
        var records = grid.getSelectionModel().getSelections();
        if (records.length === 0) {
            App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
            return false;
        }
        var winMoveTable = new Ext.Window({
            title: __("Move") + " " + records.length + " " + __("selected to another schema"),
            modal: true,
            layout: 'fit',
            width: 270,
            height: 80,
            closeAction: 'close',
            plain: true,
            items: [
                {
                    defaults: {
                        border: false
                    },
                    layout: 'hbox',
                    items: [
                        {
                            xtype: "form",
                            id: "moveform",
                            layout: "form",
                            bodyStyle: 'padding: 10px',
                            items: [
                                {
                                    xtype: 'container',
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
                                                        document.getElementById("wfseditor").contentWindow.window.cloud.removeTileLayerByName([
                                                            [v.data.f_table_schema + "." + v.get("f_table_name")]
                                                        ]);
                                                    });
                                                    document.getElementById("wfseditor").contentWindow.window.reLoadTree();
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
    };

    onSchemaRename = function (btn, ev) {
        var winSchemaRename = new Ext.Window({
            title: __("Rename schema") + " '" + schema + "'",
            modal: true,
            layout: 'fit',
            width: 270,
            height: 80,
            closeAction: 'close',
            plain: true,
            items: [
                {
                    defaults: {
                        border: false
                    },
                    layout: 'hbox',
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
                                                    window.location = "/store/" + screenName + "/" + data.data.name;
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
    };

    onSchemaDelete = function (btn, ev) {
        Ext.MessageBox.confirm(__('Confirm'), __('Are you sure you want to do that? All layers in the schema will be deleted!'), function (btn) {
            if (btn === "yes") {
                Ext.Ajax.request({
                    url: '/controllers/database/schema',
                    method: 'delete',
                    headers: {
                        'Content-Type': 'application/json; charset=utf-8'
                    },
                    success: function (response) {
                        window.location = "/store/" + screenName + "/public";
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
    };

    function onEdit() {
        var records = grid.getSelectionModel().getSelections(),
            s = Ext.getCmp("structurePanel"), detailPanel = Ext.getCmp('detailPanel');
        if (records.length === 1) {
            bookTpl.overwrite(detailPanel.body, records[0].data);
            tableStructure.grid = null;
            tableStructure.init(records[0], screenName);
            s.removeAll();
            s.add(tableStructure.grid);
            s.doLayout();
        } else {
            s.removeAll();
            s.doLayout();
        }
    }

    function onEditCartomobile(btn, ev) {
        var record = grid.getSelectionModel().getSelected();
        if (!record) {
            App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
            return false;
        }
        cartomobile.grid = null;
        cartomobile.init(record, screenName);
        cartomobile.winCartomobile = new Ext.Window({
            title: __("CartoMobile settings for the layer") + " '" + record.get("f_table_name") + "'",
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
        }).show(this);
    }

    function onSave() {
        store.save();
    }

    onEditWMSClasses = function (e) {
        var record = null, markup;
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
        Ext.getCmp("layerStyleTabs").activate(1);

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

        Ext.getCmp("layerStyleTabs").activate(0);
        var a7 = Ext.getCmp("a7");
        a7.remove(classWizards.quantile);
        classWizards.init(record);
        a7.add(classWizards.quantile);
        a7.doLayout();

        Ext.getCmp("layerStyleTabs").activate(activeTab);
        updateLegend();
    };

    function onEditMoreSettings(btn, ev) {
        var record = grid.getSelectionModel().getSelected();
        if (!record) {
            App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
            return false;
        }
        var r = record;
        winMoreSettings = new Ext.Window({
            title: __("Advanced settings on") + " '" + record.get("f_table_name") + "'",
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
                            fieldLabel: __('Meta data URL'),
                            name: 'meta_url',
                            value: r.data.meta_url

                        },
                        {
                            width: 300,
                            xtype: 'textfield',
                            fieldLabel: __('WMS source'),
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
                            width: 300,
                            xtype: 'textfield',
                            fieldLabel: __('SQL where clause'),
                            name: 'filter',
                            value: r.data.filter
                        },
                        {
                            width: 300,
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
                            width: 300,
                            xtype: 'textfield',
                            fieldLabel: __('ES trigger table'),
                            name: 'triggertable',
                            value: r.data.triggertable
                        },
                        {
                            width: 300,
                            xtype: 'textarea',
                            height: 100,
                            fieldLabel: __('View definition'),
                            name: 'viewdefinition',
                            value: r.json.viewdefinition,
                            disabled: true
                        }
                    ],
                    buttons: [
                        {
                            text: '<i class="icon-ok btn-gc"></i> ' + __('Update'),
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
                                            //grid.getSelectionModel().clearSelections();
                                            store.reload();
                                            groupsStore.load();
                                            App.setAlert(App.STATUS_NOTICE, __("Settings updated"));
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
    }

    function onGlobalSettings(btn, ev) {
        new Ext.Window({
            title: "Services",
            modal: true,
            width: 700,
            height: 272,
            initCenter: true,
            closeAction: 'hide',
            border: false,
            layout: 'border',
            items: [
                new Ext.Panel({
                    border: false,
                    layout: 'border',
                    region: "center",
                    items: [
                        new Ext.Panel({
                            region: "center",
                            items: [httpAuth.form, apiKey.form,
                                new Ext.Panel({
                                    region: "south",
                                    border: false,
                                    bodyStyle: {
                                        background: '#777',
                                        color: '#fff',
                                        padding: '7px'
                                    },
                                    html: __("HTTP Basic auth password and API key are set for the specific (sub) user.")
                                })
                            ]
                        }), new Ext.Panel({
                            layout: "border",
                            region: "east",
                            width: 400,
                            height: 72,
                            items: [
                                new Ext.Panel({
                                    //title: 'WFS',
                                    region: "north",
                                    border: false,
                                    //height: 50,
                                    bodyStyle: {
                                        background: '#ffffff',
                                        padding: '7px'
                                    },
                                    contentEl: "wfs-dialog"
                                }), new Ext.Panel({
                                    //title: 'WMS',
                                    border: false,
                                    //height: 50,
                                    region: "center",
                                    bodyStyle: {
                                        background: '#ffffff',
                                        padding: '7px'
                                    },
                                    contentEl: "wms-dialog"

                                }), new Ext.Panel({
                                    //title: 'SQL',
                                    height: 135,
                                    border: false,
                                    region: "south",
                                    items: [
                                        new Ext.Panel({
                                            //title: 'SQL',
                                            // height: 50,
                                            border: false,
                                            region: "north",
                                            bodyStyle: {
                                                background: '#ffffff',
                                                padding: '7px'
                                            },
                                            contentEl: "sql-dialog"

                                        }), new Ext.Panel({
                                            //title: 'elasticsearch',
                                            //height: 60,
                                            border: false,
                                            region: "center",
                                            bodyStyle: {
                                                background: '#ffffff',
                                                padding: '7px'
                                            },
                                            contentEl: "elasticsearch-dialog"
                                        })
                                    ]
                                })
                            ]
                        })]
                })]
        }).show(this);
    }

    function onPrivileges(btn, ev) {
        var records = grid.getSelectionModel().getSelections();
        if (records.length === 0) {
            App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
            return false;
        }
        var privilegesStore = new Ext.data.Store({
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
                        if (type === 'remote') {
                            var message = "<p>" + __("Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong") + "</p><br/><textarea rows=5' cols='31'>" + __(response.message) + "</textarea>";
                            Ext.MessageBox.show({
                                title: __('Failure'),
                                msg: message,
                                buttons: Ext.MessageBox.OK,
                                width: 300,
                                height: 300
                            });
                        } else {
                            privilgesWin.close();
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
        privilegesStore.load();
        var privilgesWin = new Ext.Window({
            title: __("Grant privileges to sub-users on") + " '" + records[0].get("f_table_name") + "'",
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
                            store: privilegesStore,
                            viewConfig: {
                                forceFit: true
                            },
                            height: 200,
                            region: 'center',
                            frame: false,
                            border: true,
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
                                            var _key_ = records[0].get("_key_");
                                            var retval =
                                                    '<input data-key="' + _key_ + '" data-subuser="' + record.data.subuser + '" onclick="updatePrivileges(this.getAttribute(\'data-subuser\'),this.getAttribute(\'data-key\'),this.value)" type="radio" value="none" name="' + rowIndex + '"' + ((val === 'none') ? ' checked="checked"' : '') + '>&nbsp;' + __('None') + '&nbsp;&nbsp;&nbsp;' +
                                                    '<input data-key="' + _key_ + '" data-subuser="' + record.data.subuser + '" onclick="updatePrivileges(this.getAttribute(\'data-subuser\'),this.getAttribute(\'data-key\'),this.value)" type="radio" value="read" name="' + rowIndex + '"' + ((val === 'read') ? ' checked="checked"' : '') + '>&nbsp;' + __('Only read') + '&nbsp;&nbsp;&nbsp;' +
                                                    '<input data-key="' + _key_ + '" data-subuser="' + record.data.subuser + '" onclick="updatePrivileges(this.getAttribute(\'data-subuser\'),this.getAttribute(\'data-key\'),this.value)" type="radio" value="write" name="' + rowIndex + '"' + ((val === 'write') ? ' checked="checked"' : '') + '>&nbsp;' + __('Read and write') + '&nbsp;&nbsp;&nbsp;' +
                                                    '<input data-key="' + _key_ + '" data-subuser="' + record.data.subuser + '" onclick="updatePrivileges(this.getAttribute(\'data-subuser\'),this.getAttribute(\'data-key\'),this.value)" type="radio" value="all" name="' + rowIndex + '"' + ((val === 'all') ? ' checked="checked"' : '') + '>&nbsp;' + __('All')
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
                                "<li>" + "<b>" + __("All") + "</b>: " + __("The sub-user change properties like style and alter table structure.") + "</li>" +
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
    }

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

    // define a template to use for the detail view
    var bookTplMarkup = ['<table>' +
    '<tr class="x-grid3-row"><td width="70"><b>Srid</b></td><td width="130">{srid}</td><td width="90"><b>' + __('Created') + '</b></td><td>{created}</td></tr>' +
    '<tr class="x-grid3-row"><td><b>' + __('Geom field') + '</b></td><td>{f_geometry_column}</td><td><b>' + __('Last modified') + '</b></td><td>{lastmodified}</td>' +
    '</tr>' +
    '</table>'];
    var bookTpl = new Ext.Template(bookTplMarkup);
    var ct = new Ext.Panel({
        title: __('Database'),
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
        var records = sm.getSelections();
        if (records.length === 1) {
            Ext.getCmp('cartomobile-btn').setDisabled(false);
            Ext.getCmp('advanced-btn').setDisabled(false);
            if (subUser === false || subUser === schema) {
                Ext.getCmp('privileges-btn').setDisabled(false);
                Ext.getCmp('renamelayer-btn').setDisabled(false);
            }
        }
        else {
            Ext.getCmp('cartomobile-btn').setDisabled(true);
            Ext.getCmp('advanced-btn').setDisabled(true);
            Ext.getCmp('privileges-btn').setDisabled(true);
            Ext.getCmp('renamelayer-btn').setDisabled(true);
        }
        if (records.length > 0 && subUser === false) {
            Ext.getCmp('movelayer-btn').setDisabled(false);
        }
        if (records.length > 0 && (subUser === false || subUser === schema)) {
            Ext.getCmp('deletelayer-btn').setDisabled(false);
        }
        onEdit();
    });

    resetButtons = function () {
        Ext.getCmp('cartomobile-btn').setDisabled(true);
        Ext.getCmp('advanced-btn').setDisabled(true);
        Ext.getCmp('privileges-btn').setDisabled(true);
        Ext.getCmp('renamelayer-btn').setDisabled(true);
        Ext.getCmp('deletelayer-btn').setDisabled(true);
        Ext.getCmp('movelayer-btn').setDisabled(true);
    };

    var tabs = new Ext.TabPanel({
        activeTab: 0,
        region: 'center',
        plain: true,
        items: [
            {
                xtype: "panel",
                title: __('Map'),
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
                                        title: __('Class wizards'),
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
                                        title: __('Classes'),
                                        defaults: {
                                            border: false
                                        },
                                        items: [
                                            {
                                                xtype: "panel",
                                                id: "a2",
                                                layout: "fit",
                                                height: 150
                                            },

                                            new Ext.TabPanel({
                                                activeTab: 0,
                                                region: 'center',
                                                plain: true,
                                                id: "classTabs",
                                                border: false,
                                                height: 450,
                                                defaults: {
                                                    layout: "fit",
                                                    border: false
                                                },
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

                                                ],
                                                tbar: [
                                                    {
                                                        text: '<i class="icon-ok btn-gc"></i> ' + __('Update'),
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

                                                            // Encode the json because it can contain "="
                                                            param = encodeURIComponent(param);

                                                            Ext.Ajax.request({
                                                                url: '/controllers/classification/index/' + wmsClasses.table + '/' + wmsClass.classId,
                                                                method: 'put',
                                                                params: param,
                                                                headers: {
                                                                    'Content-Type': 'application/json; charset=utf-8'
                                                                },
                                                                success: function (response) {
                                                                    App.setAlert(App.STATUS_OK, __("Style is updated"));
                                                                    writeFiles(wmsClasses.table);
                                                                    wmsClasses.store.load();
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
                ]
            },
            ct,
            {
                xtype: "panel",
                title: __('Scheduler'),
                layout: 'border',
                id: "schedulerPanel",
                items: [
                    {
                        frame: false,
                        border: false,
                        region: "center",
                        html: '<iframe frameborder="0" id="scheduler" style="width:100%;height:100%" src="/scheduler/index2.html"></iframe>'
                    }
                ]
            },
            {
                xtype: "panel",
                title: __('Log'),
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
    var viewPort = new Ext.Viewport({
        layout: 'border',
        items: [tabs]
    });

    // Hide tab if scheduler is not available for the db
    if (window.gc2Options.gc2scheduler !== null) {
        if (window.gc2Options.gc2scheduler.hasOwnProperty(screenName) === false || window.gc2Options.gc2scheduler[screenName] === false) {
            tabs.hideTabStripItem(Ext.getCmp('schedulerPanel'));
        }
    } else {
        tabs.hideTabStripItem(Ext.getCmp('schedulerPanel'));
    }

    writeFiles = function (clearCachedLayer, map) {
        $.ajax({
            url: '/controllers/mapfile',
            success: function (response) {
                updateLegend();
                document.getElementById("wfseditor").contentWindow.window.getMetaData();
                if (clearCachedLayer) {
                    clearTileCache(clearCachedLayer, map);
                }
            }
        });
        $.ajax({
            url: '/controllers/cfgfile',
            success: function (response) {
            }
        });
        /*$.ajax({
         url: '/controllers/tinyowsfile',
         success: function (response) {
         }
         });*/
    };
    clearTileCache = function (layer, map) {
        var key = layer.split(".")[0] + "." + layer.split(".")[1];
        $.ajax({
            url: '/controllers/tilecache/index/' + layer,
            async: true,
            dataType: 'json',
            type: 'delete',
            success: function (response) {
                if (response.success === true) {
                    App.setAlert(App.STATUS_NOTICE, __(response.message));
                    var l;
                    l = document.getElementById("wfseditor").contentWindow.window.map.getLayersByName(key)[0];
                    if (l === undefined) { // If called from iframe
                        l = map.getLayersByName(key)[0];
                    }
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
                else {
                    App.setAlert(App.STATUS_NOTICE, __(response.message));
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



