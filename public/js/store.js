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

Ext.Ajax.disableCaching = false;
Ext.QuickTips.init();
var form, store, writeFiles, clearTileCache, updateLegend, activeLayer, onEditWMSClasses, onAdd, onMove, onSchemaRename, onSchemaDelete, resetButtons, initExtent = null, App = new Ext.App({}), updatePrivileges, updateWorkflow, settings, extentRestricted = false, spinner, styleWizardWin, workflowStore, workflowStoreLoaded = false, subUserGroups;
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
            fieldsForStore.push({name: "indexed_in_es", type: "bool"});
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
                subUserGroups = settings.userGroups;
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
    //workflowStore.load();

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
            getRowClass: function (record) {
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
                    header: (window.gc2Options.extraLayerPropertyName !== null && window.gc2Options.extraLayerPropertyName[screenName]) ? window.gc2Options.extraLayerPropertyName[screenName] : "Extra",
                    dataIndex: "extra",
                    sortable: true,
                    width: 100,
                    hidden: (window.gc2Options.showExtraLayerProperty !== null && window.gc2Options.showExtraLayerProperty[screenName] === true) ? false : true

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
                    width: 50
                },
                {
                    xtype: 'checkcolumn',
                    header: __("Skip conflict"),
                    dataIndex: 'skipconflict',
                    hidden: (window.gc2Options.showConflictOptions !== null && window.gc2Options.showConflictOptions[screenName] === true) ? false : true,
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
                    renderer: function () {
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
                text: '<i class="icon-user btn-gc"></i> ' + __('Workflow'),
                id: 'workflow-btn',
                handler: onWorkflow,
                disabled: true,
                hidden: (window.gc2Options.enableWorkflow !== null && window.gc2Options.enableWorkflow[screenName] === true) ? false : true
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
                text: __('Copy properties'),
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
                        title: __("Copy all properties from another layer"),
                        modal: true,
                        layout: 'fit',
                        width: 350,
                        height: 120,
                        closeAction: 'close',
                        plain: true,
                        items: [
                            {
                                defaults: {
                                    border: false
                                },
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
                                                width: 150,
                                                allowBlank: false,
                                                emptyText: __('Schema'),
                                                listeners: {
                                                    'select': function (combo, value, index) {
                                                        Ext.getCmp('copyMetaFormKeys').clearValue();
                                                        (function () {
                                                            Ext.Ajax.request({
                                                                url: '/api/v1/meta/' + screenName + '/' + combo.getValue(),
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
                                                width: 150,
                                                allowBlank: false,
                                                emptyText: __('Layer')
                                            }

                                        ]
                                    },
                                    {
                                        layout: 'form',
                                        bodyStyle: 'padding: 10px',
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
                                                                document.getElementById("wfseditor").contentWindow.window.reLoadTree();
                                                                store.reload();
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
                    }).show(this);
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
            'mouseover': {
                fn: function () {
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
            width: 700,
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
        Ext.getCmp("layerStyleTabs").activate(2);

        Ext.getCmp("layerStyleTabs").activate(1);
        var template = new Ext.Template(markup);
        template.overwrite(Ext.getCmp('a5').body, record);
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
            width: 450,
            height: 420,
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
    }

    function onGlobalSettings(btn, ev) {
        new Ext.Window({
            title: "Services",
            modal: true,
            width: 700,
            height: 430,
            initCenter: true,
            closeAction: 'hide',
            border: false,
            layout: 'border',
            items: [
                new Ext.Panel({
                    region: "center",
                    layout: 'border',
                    items: [
                        new Ext.Panel({
                            border: false,
                            region: "center",
                            items: [
                                httpAuth.form,
                                apiKey.form
                            ]}),

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
                    width: 445,
                    id: "service-dialog",
                    items: [
                        new Ext.Panel({
                            border: false,
                            region: "center",
                            defaults: {
                                bodyStyle: {
                                    background: '#ffffff',
                                    padding: '7px'
                                },
                                border: false,
                                layout: 'fit'


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
            width: 600,
            height: 330,
            initCenter: true,
            closeAction: 'hide',
            border: false,
            layout: 'border',
            items: [
                new Ext.Panel({
                    height: 500,
                    border: true,
                    region: "center",
                    layout: 'border',

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
                                            if (typeof subUserGroups[record.data.subuser] === "undefined" || subUserGroups[record.data.subuser] === "") {
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
                                            return subUserGroups[record.data.subuser];
                                        }
                                    }
                                ]
                            })
                        }),
                        new Ext.Panel({
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

    // Workflow
    function onWorkflow(btn, ev) {
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
                        msg: __("The table must be versioned"),
                        buttons: Ext.MessageBox.OK,
                        width: 400,
                        height: 300,
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
                                    title: __("Apply role to sub-users on") + " '" + records[0].get("f_table_name") + "'",
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


    }

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
            title: __("Class wizard"),
            layout: 'fit',
            width: 700,
            height: 540,
            plain: true,
            modal: true,
            resizable: false,
            draggable: true,
            border: false,
            closeAction: 'hide',
            x: 250,
            y: 50,
            items: [
                {
                    xtype: "panel",
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
                Ext.getCmp('workflow-btn').setDisabled(false);
                Ext.getCmp('renamelayer-btn').setDisabled(false);
                Ext.getCmp('copy-properties-btn').setDisabled(false);
            }
        }
        else {
            Ext.getCmp('cartomobile-btn').setDisabled(true);
            Ext.getCmp('advanced-btn').setDisabled(true);
            Ext.getCmp('privileges-btn').setDisabled(true);
            Ext.getCmp('workflow-btn').setDisabled(true);
            Ext.getCmp('renamelayer-btn').setDisabled(true);
            Ext.getCmp('copy-properties-btn').setDisabled(true);
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
        Ext.getCmp('workflow-btn').setDisabled(true);
        Ext.getCmp('renamelayer-btn').setDisabled(true);
        Ext.getCmp('copy-properties-btn').setDisabled(true);
        Ext.getCmp('deletelayer-btn').setDisabled(true);
        Ext.getCmp('movelayer-btn').setDisabled(true);
    };

    var tabs = new Ext.TabPanel({
        id: "mainTabs",
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
                        width: 340,
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
                                                height: 470,
                                                defaults: {
                                                    layout: "fit",
                                                    border: false
                                                },
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
                                                            //param = encodeURIComponent(param);

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
                title: __('Workflow'),
                layout: 'border',
                id: "workflowPanel",
                listeners: {
                    activate: function () {
                        if (!workflowStoreLoaded) {
                            workflowStore.load();
                            workflowStoreLoaded = true;
                        }
                    }
                },
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
                            // encode and local configuration options defined previously for easier reuse
                            //encode: encode, // json encode the filter query
                            local: true,   // defaults to false (remote filtering)
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
                                }, {
                                    header: __("Schema"),
                                    dataIndex: "f_schema_name",
                                    sortable: true,
                                    width: 35,
                                    flex: 0.5
                                },
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
                                text: '<i class="icon-refresh btn-gc"></i> ' + __('Reload'),
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
                                text: '<i class="icon-tasks btn-gc"></i> ' + __('Show all'),
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
                                text: '<i class="icon-pencil btn-gc"></i> ' + __('See/edit feature'),
                                tooltip: __("Switch to Map view with the feature loaded."),
                                handler: function () {
                                    var records = Ext.getCmp("workflowGrid").getSelectionModel().getSelections();
                                    if (records.length === 0) {
                                        App.setAlert(App.STATUS_NOTICE, __("You've to select a layer"));
                                    }
                                    Ext.Ajax.request({
                                        url: '/api/v1/meta/' + screenName + '/' + records[0].get("f_schema_name") + "." + records[0].get("f_table_name"),
                                        method: 'GET',
                                        headers: {
                                            'Content-Type': 'application/json; charset=utf-8'
                                        },
                                        success: function (response) {
                                            var r = Ext.decode(response.responseText),
                                                mapFrame = document.getElementById("wfseditor").contentWindow.window,
                                                filter = new mapFrame.OpenLayers.Filter.Comparison({
                                                    type: mapFrame.OpenLayers.Filter.Comparison.EQUAL_TO,
                                                    property: "\"" + r.data[0].pkey + "\"",
                                                    value: records[0].get("gid")
                                                });
                                            Ext.getCmp("mainTabs").activate(0);
                                            setTimeout(function () {
                                                mapFrame.attributeForm.init(records[0].get("f_table_name"), r.data[0].pkey);
                                                mapFrame.startWfsEdition(records[0].get("f_table_name"), r.data[0].f_geometry_column, filter, true);
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
                                text: '<i class="icon-ok btn-gc"></i> ' + __('Check feafure'),
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
                            background: '#777',
                            color: '#fff',
                            padding: '7px'
                        }
                    }
                ]
            },
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

    // Hide tab if workflow is not available for the db
    if (window.gc2Options.enableWorkflow !== null) {
        if (window.gc2Options.enableWorkflow.hasOwnProperty(screenName) === false || window.gc2Options.enableWorkflow[screenName] === false) {
            tabs.hideTabStripItem(Ext.getCmp('workflowPanel'));
        }
    } else {
        tabs.hideTabStripItem(Ext.getCmp('workflowPanel'));
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
        var a = Ext.getCmp("a6");
        var b = Ext.getCmp("wizardLegend");
        if (activeLayer !== undefined) {
            $.ajax({
                url: '/api/v1/legend/html/' + screenName + '/' + activeLayer.split(".")[0] + '?l=' + activeLayer,
                dataType: 'jsonp',
                jsonp: 'jsonp_callback',
                success: function (response) {
                    a.update(response.html);
                    a.doLayout();
                    try {
                        b.update(response.html);
                        b.doLayout();
                    }
                    catch (e) {
                    }
                }
            });
        }
    };
    spinner = function (show, text) {
        if (show) {
            $("#spinner").show();
            $("#spinner span").html(text);
        } else {
            $("#spinner").hide();
            $("#spinner span").empty();
        }
    };
});



