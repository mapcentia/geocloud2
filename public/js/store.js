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
Ext.MessageBox.buttonText = {
    ok: "<i class='fa fa-check'></i> " + __("Ok"),
    cancel: "<i class='fa fa-remove'></i> " + __("Cancel"),
    yes: "<i class='fa fa-check'></i> " + __("Yes"),
    no: "<i class='fa fa-remove'></i> " + __("No")
}
var form, store, writeFiles, clearTileCache, updateLegend, activeLayer, onEditWMSClasses, onAdd, resetButtons,
    initExtent = null, App = new Ext.App({}), updatePrivileges, updateWorkflow, settings,
    extentRestricted = false, spinner, styleWizardWin, workflowStore, workflowStoreLoaded = false, subUserGroups = {},
    dataStore, dataGrid, tableDataLoaded = false, dataPanel, esPanel, esGrid,
    enableWorkflow = (window.gc2Options.enableWorkflow !== null && typeof window.gc2Options.enableWorkflow[screenName] !== "undefined" && window.gc2Options.enableWorkflow[screenName] === true) || (window.gc2Options.enableWorkflow !== null && typeof window.gc2Options.enableWorkflow["*"] !== "undefined" && window.gc2Options.enableWorkflow["*"] === true);

$(window).ready(function () {
    "use strict";
    Ext.Container.prototype.bufferResize = false;
    var winAdd, winMoreSettings, fieldsForStore = {}, groups, groupsStore, tagStore, subUsers;
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
                subUserGroups = settings.userGroups || {};
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
    tagStore.load();

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
                    width: 150,
                    renderer: function (v, p) {
                        return v || __('No geometry');
                    }
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
                text: '<i class="fa fa-user"></i> ' + __('Privileges'),
                id: 'privileges-btn',
                handler: function (btn, ev) {
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
                                                        if (typeof subUserGroups[record.data.subuser] !== "undefined" && subUserGroups[record.data.subuser] !== "") {
                                                            return subUserGroups[record.data.subuser];
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
                        height: 460,
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
                                                xtype: 'textarea',
                                                height: 100,
                                                fieldLabel: __('View definition'),
                                                name: 'viewdefinition',
                                                value: r.json.viewdefinition,
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
                        title: "Services",
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
                                width: 600,
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
                                    labelWidth: 1,
                                    items: [
                                        new Ext.ux.form.SuperBoxSelect({
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
                                            value: (records.length === 1 ) ? ((records[0].data.tags !== null) ? Ext.decode(records[0].data.tags) : []) : [],
                                            listeners: {
                                                newitem: function (bs, v, f) {
                                                    bs.addNewItem({
                                                        tag: v
                                                    });
                                                }
                                            }
                                        })
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
                                                                tags: values.tags
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
                                    id: "metaform",
                                    layout: "form",
                                    bodyStyle: 'padding: 10px',
                                    labelWidth: 80,
                                    items: [
                                        {
                                            xtype: 'fieldset',
                                            title: __('Custom settings'),
                                            defaults: {
                                                anchor: '100%'
                                            },
                                            items: (function () {
                                                var fields = [];
                                                Ext.each(window.gc2Options.metaConfig, function (v) {
                                                    switch (v.type) {
                                                        case "text":
                                                            fields.push(
                                                                {
                                                                    xtype: 'textfield',
                                                                    fieldLabel: v.title,
                                                                    name: v.name,
                                                                    value: (records.length === 1 ) ? (Ext.decode(records[0].data.meta)[v.name] || v.default) : null
                                                                }
                                                            )
                                                            break;
                                                        case "checkbox":
                                                            fields.push(
                                                                {
                                                                    xtype: 'checkbox',
                                                                    fieldLabel: v.title,
                                                                    name: v.name,
                                                                    checked: (records.length === 1 ) ? ((Ext.decode(records[0].data.meta)[v.name] !== undefined) ? Ext.decode(records[0].data.meta)[v.name] : v.default) : false
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
                                                                    value: (records.length === 1 ) ? ((Ext.decode(records[0].data.meta)[v.name]) || v.default) : null
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
                                                                    value: (records.length === 1 ) ? ((Ext.decode(records[0].data.meta)[v.name] !== null) ? Ext.decode(records[0].data.meta)[v.name] : []) : [],
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
                                        }
                                    ],
                                    buttons: [
                                        {
                                            text: '<i class="fa fa-check"></i> ' + __('Update'),
                                            handler: function () {

                                                var f = Ext.getCmp('metaform');
                                                if (f.form.isValid()) {
                                                    var values = f.form.getFieldValues();
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
                text: '<i class="fa fa-remove"></i> ' + __('Clear tile cache'),
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
                text: '<i class="fa fa-cloud-upload"></i> ' + __('New layer'),
                disabled: (subUser === schema || subUser === false) ? false : true,
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
                        title: __("Move") + " " + records.length + " " + __("selected to another schema"),
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
                                                        width: 150
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
                        title: __("Rename table") + " '" + record.data.f_table_name + "'",
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
                                                                    document.getElementById("wfseditor").contentWindow.window.cloud.removeTileLayerByName([
                                                                        [name]
                                                                    ]);
                                                                } catch (e) {
                                                                }
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
                    Ext.MessageBox.confirm(__('Confirm'), __('Are you sure you want to delete') + ' ' + records.length + ' ' + __('table(s)') + '?', function (btn) {
                        if (btn === "yes") {
                            var tables = [];
                            Ext.iterate(records, function (v) {
                                tables.push(v.get("_key_"));
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
                        title: __("Copy all properties from another layer"),
                        modal: true,
                        layout: 'fit',
                        width: 350,
                        height: 120,
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
    Ext.getCmp("schemabox").on('select', function (e) {
        window.location = "/store/" + screenName + "/" + e.value;
    });

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
                    text: __('OSM view'),
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
                }
            ]
        });

        winAdd.show(this);
        addVector();
    };

    function onEdit() {
        var records = grid.getSelectionModel().getSelections(),
            s = Ext.getCmp("structurepanel"),
            detailPanel = Ext.getCmp('detailPanel');
        if (records.length === 1) {
            bookTpl.overwrite(detailPanel.body, records[0].data);
            tableStructure.grid = null;
            Ext.getCmp("tablepanel").activate(0);
            tableStructure.init(records[0], screenName);
            s.removeAll();
            s.add(tableStructure.grid);
            s.doLayout();
        } else {
            s.removeAll();
            s.doLayout();
        }
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
        wmsLayer.init(record);
        a1.add(wmsLayer.grid);
        a4.add(wmsLayer.sqlForm);
        a1.doLayout();
        a4.doLayout();

        Ext.getCmp("layerStyleTabs").activate(2);
        var a12 = Ext.getCmp("a12");
        a12.remove(tileLayer.grid);
        tileLayer.grid = null;
        tileLayer.init(record);
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
        updateLegend();
    };

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

    // define a template to use for the detail view
    var bookTplMarkup = ['<table border="0">' +
    '<tr class="x-grid3-row"><td class="bottom-info-bar-param"><b>' + __('Srid') + '</b></td><td >{srid}</td><td class="bottom-info-bar-pipe">|</td><td class="bottom-info-bar-param"><b>' + __('Key') + '</b></td><td >{_key_}</td><td class="bottom-info-bar-pipe">|</td><td class="bottom-info-bar-param"><b>' + __('Tags') + '</b></td><td>{tags}</td></tr>' +
    '<tr class="x-grid3-row"><td class="bottom-info-bar-param"><b>' + __('Geom field') + '</b></td><td>{f_geometry_column}</td><td class="bottom-info-bar-pipe">|</td><td class="bottom-info-bar-param"><b>' + __('Created') + '</b></td><td>{created}</td><td class="bottom-info-bar-pipe">|</td></td><td class="bottom-info-bar-param"><b>' + __('Guid') + '</b></td><td>{uuid}</td></tr>' +
    '</table>'];
    var bookTpl = new Ext.Template(bookTplMarkup);
    var ct = new Ext.Panel({
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
                background: '#777',
                color: '#fff',
                padding: '7px'
            }
        }, {
            xtype: "tabpanel",
            activeTab: 0,
            plain: true,
            border: false,
            resizeTabs: true,
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
                                                if (action === "create") { // HACK exception is thrown with successful create
                                                    dataStore.reload();
                                                } else {
                                                    Ext.MessageBox.show({
                                                        title: __('Failure'),
                                                        msg: __(Ext.decode(response.responseText).message),
                                                        buttons: Ext.MessageBox.OK,
                                                        width: 300,
                                                        height: 300
                                                    });
                                                }

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
                    layout: 'fit',
                    xtype: "panel",
                    title: __("Elasticsearch"),
                    id: 'espanel',
                    listeners: {
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
                            elasticsearch.init(grid.getSelectionModel().getSelected(), screenName);
                            esPanel.add(elasticsearch.grid);
                            esPanel.doLayout();
                        }
                    }

                }
            ]
        }
        ]
    });
    grid.getSelectionModel().on('rowselect', function (sm, rowIdx, r) {
        var records = sm.getSelections();
        if (records.length === 1) {
            Ext.getCmp('advanced-btn').setDisabled(false);
            if (subUser === false || subUser === schema) {
                Ext.getCmp('privileges-btn').setDisabled(false);
                Ext.getCmp('workflow-btn').setDisabled(false);
                Ext.getCmp('renamelayer-btn').setDisabled(false);
                Ext.getCmp('copy-properties-btn').setDisabled(false);
            }
        }
        else {
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
        resizeTabs: true,
        items: [
            {
                xtype: "panel",
                title: '<i class="fa fa-map"></i> ' + __('Map'),
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
                        layoutConfig: {
                            animate: true
                        },
                        border: false,
                        items: [
                            {
                                xtype: "tabpanel",
                                id: "layerStyleTabs",
                                activeTab: 0,
                                plain: true,
                                border: false,
                                resizeTabs: true,
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
                                                resizeTabs: true,
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
                                                    background: '#ffffff',
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
                title: '<i class="fa fa-users"></i> ' + __('Workflow'),
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
                                                mapFrame.attributeForm.form.disable();
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
                            background: '#777',
                            color: '#fff',
                            padding: '7px'
                        }
                    }
                ]
            },
            {
                xtype: "panel",
                title: '<i class="fa fa-clock-o"></i> ' + __('Scheduler'),
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
                title: '<i class="fa fa-list"></i> ' + __('Log'),
                layout: 'border',
                border: false,
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
                        border: false,
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
    if (!enableWorkflow) {
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
        $.ajax({
            url: '/controllers/mapcachefile',
            success: function (response) {
            }
        });
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



