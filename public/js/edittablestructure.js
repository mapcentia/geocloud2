Ext.namespace('tableStructure');
Ext.namespace('Ext.ux.grid');
Ext.ux.grid.CheckColumn = Ext.extend(
    Ext.grid.Column,
    {
        processEvent: function (name, e, grid, rowIndex, colIndex) {
            "use strict";
            if (name === 'click'/* 'mousedown' */) {
                var record = grid.store.getAt(rowIndex);
                record.set(this.dataIndex,
                    !record.data[this.dataIndex]);
                return false;
            } else {
                return Ext.ux.grid.CheckColumn/* Ext.grid.ActionColumn */.superclass.processEvent
                    .apply(this, arguments);
            }
        },
        renderer: function (v, p, record) {
            p.css += ' x-grid3-check-col-td';
            return String.format(
                '<div class="x-grid3-check-col{0}"> </div>',
                v ? '-on' : ''
            );
        },
        init: Ext.emptyFn
    }
);
tableStructure.init = function (record, screenName) {
    "use strict";
    tableStructure.reader = new Ext.data.JsonReader({
        totalProperty: 'total',
        successProperty: 'success',
        idProperty: 'id',
        root: 'data',
        messageProperty: 'message'
    }, [
        {
            name: 'sort_id',
            allowBlank: true
        },
        {
            name: 'column',
            allowBlank: false
        },
        {
            name: 'type',
            allowBlank: false
        },
        {
            name: 'querable',
            allowBlank: true
        },
        {
            name: 'alias',
            allowBlank: true
        },
        {
            name: 'link',
            allowBlank: true
        },
        {
            name: 'linkprefix',
            allowBlank: true
        },
        {
            name: 'properties',
            allowBlank: true
        }
    ]);

    tableStructure.writer = new Ext.data.JsonWriter({
        writeAllFields: true,
        encode: false
    });
    tableStructure.proxy = new Ext.data.HttpProxy(
        {
            restful: true,
            api: {
                read: '/controllers/table/structure/' + record.get("f_table_schema") + '.' + record.get("f_table_name") + '/' + record.get("_key_"),
                create: '/controllers/table/columns/' + record.get("f_table_schema") + '.' + record.get("f_table_name") + '/' + record.get("_key_"),
                update: '/controllers/table/columns/' + record.get("f_table_schema") + '.' + record.get("f_table_name") + '/' + record.get("_key_"),
                destroy: '/controllers/table/columns/' + record.get("f_table_schema") + '.' + record.get("f_table_name") + '/' + record.get("_key_")
            },
            listeners: {
                write: tableStructure.onWrite,
                exception: function (proxy, type, action, options, response, arg) {
                    if (type === 'remote') { // success is false
                        var message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows=5' cols='31'>" + response.message + "</textarea>";
                        Ext.MessageBox.show({
                            title: __("Failure"),
                            msg: message,
                            buttons: Ext.MessageBox.OK,
                            width: 400,
                            height: 300,
                            icon: Ext.MessageBox.ERROR
                        });
                    } else {
                        //tableStructure.store.load();
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
        }
    );

    tableStructure.store = new Ext.data.Store({
        writer: tableStructure.writer,
        reader: tableStructure.reader,
        proxy: tableStructure.proxy,
        autoSave: true,
        listeners: {
            load: function (store, records, options) {
                if (($.inArray('gc2_version_gid', store.data.keys)) !== -1) {
                    Ext.getCmp('add-versioning-btn').setDisabled(true);
                    Ext.getCmp('remove-versioning-btn').setDisabled(false);
                } else {
                    Ext.getCmp('add-versioning-btn').setDisabled(false);
                    Ext.getCmp('remove-versioning-btn').setDisabled(true);
                }
            },
            beforewrite: function (store, d) {
                for (var key in store.data.keys) {
                    if (store.data.keys.hasOwnProperty(key)) {
                        store.data.keys[key] = encodeURIComponent(store.data.keys[key]);
                    }
                }
            }
        }
    });

    tableStructure.store.setDefaultSort('sort_id', 'asc');
    tableStructure.store.load();

    tableStructure.grid = new Ext.grid.EditorGridPanel({
        iconCls: 'silk-grid',
        store: tableStructure.store,
        ddGroup: 'mygridDD',
        enableDragDrop: false,
        viewConfig: {
            forceFit: true
        },
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
                    id: "sort_id",
                    header: __("Sort id"),
                    dataIndex: "sort_id",
                    sortable: true,
                    width: 30,
                    editor: new Ext.grid.GridEditor(new Ext.form.NumberField({
                        decimalPrecision: 0,
                        decimalSeparator: '¤'// Some strange char nobody is using
                    }), {})
                },
                {
                    id: "column",
                    header: __("Column"),
                    dataIndex: "column",
                    sortable: true,
                    editor: new Ext.form.TextField({
                        allowBlank: false
                    }),
                    width: 60
                },
                {
                    id: "type",
                    header: __("Type"),
                    dataIndex: "type",
                    sortable: true,
                    width: 30,
                    editor: new Ext.form.ComboBox({
                        typeAhead: false,
                        triggerAction: 'all',
                        mode: 'local',
                        editable: false,
                        allowBlank: false,
                        readOnly: true,
                        valueField: 'type',
                        displayField: 'type'
                    })
                },
                {
                    id: "alias",
                    header: __("Alias"),
                    dataIndex: "alias",
                    sortable: true,
                    editor: new Ext.form.TextField({
                        allowBlank: true
                    })
                },
                {
                    id: "querable",
                    xtype: 'checkcolumn',
                    header: __("Queryable"),
                    dataIndex: 'querable',
                    width: 35
                },
                {
                    id: "link",
                    xtype: 'checkcolumn',
                    header: __("Link"),
                    dataIndex: 'link',
                    width: 20
                },
                {
                    id: "linkprefix",
                    header: __("Link prefix"),
                    dataIndex: "linkprefix",
                    sortable: true,
                    width: 60,
                    editor: new Ext.form.TextField({
                        allowBlank: true
                    })
                },
                {
                    id: "properties",
                    header: __("Properties"),
                    dataIndex: "properties",
                    sortable: true,
                    width: 80,
                    editor: new Ext.form.TextField({
                        allowBlank: true
                    })
                }
            ]
        }),
        listeners: {
            "render": {
                scope: this,
                fn: function (grid) {

                    // Enable sorting Rows via Drag & Drop
                    // this drop target listens for a row drop
                    // and handles rearranging the rows

                    var ddrow = new Ext.dd.DropTarget(grid.container, {
                        ddGroup: 'mygridDD',
                        copy: false,
                        notifyDrop: function (dd, e, data) {

                            var ds = grid.store;

                            // NOTE:
                            // you may need to make an ajax call here
                            // to send the new order
                            // and then reload the store

                            // alternatively, you can handle the changes
                            // in the order of the row as demonstrated below

                            // ***************************************

                            var sm = grid.getSelectionModel();
                            var rows = sm.getSelections();
                            if (dd.getDragData(e)) {
                                var cindex = dd.getDragData(e).rowIndex;
                                if (typeof (cindex) != "undefined") {
                                    for (i = 0; i < rows.length; i++) {
                                        ds.remove(ds.getById(rows[i].id));
                                    }
                                    ds.insert(cindex, data.selections);
                                    sm.clearSelections();
                                }
                            }

                            // ************************************
                        }
                    });

                    // load the grid store
                    // after the grid has been rendered
                    // store.load();
                }
            }
        },
        tbar: [
            {
                xtype: 'form',
                layout: 'hbox',
                width: 300,
                id: 'addColumnForm',
                items: [
                    {
                        xtype: 'textfield',
                        flex: 1,
                        name: 'column',
                        emptyText: __("New column name"),
                        allowBlank: false
                    },
                    {
                        width: 150,
                        xtype: 'combo',
                        mode: 'local',
                        triggerAction: 'all',
                        forceSelection: true,
                        editable: false,
                        emptyText: __("New column type"),
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
                ]
            },
            {
                text: '<i class="icon-plus btn-gc"></i> ' + __("Add new column"),
                handler: function () {
                    var form = Ext.getCmp("addColumnForm");
                    if (form.form.isValid()) {
                        form.form.submit({
                            url: '/controllers/table/columns/' + schema + '.' + record.get("f_table_name") + '/' + record.get("_key_"),
                            submitEmptyText: false,
                            success: function (response) {
                                tableStructure.store.load();
                                form.reset();
                            },
                            failure: function (form, action) {
                                Ext.MessageBox.show({
                                    title: __("Failure"),
                                    msg: __(Ext.decode(action.response.responseText).message),
                                    buttons: Ext.MessageBox.OK,
                                    width: 400,
                                    height: 300,
                                    icon: Ext.MessageBox.ERROR
                                });
                            }
                        });
                    } else {
                        var s = '';
                        Ext.iterate(form.form.getValues(), function (key, value) {
                            s += String.format("{0} = {1}<br />", key, value);
                        }, this);
                    }
                }
            },
            {
                text: '<i class="icon-trash btn-gc"></i> ' + __("Delete column"),
                handler: tableStructure.onDelete
            },
            {
                text: '<i class="icon-plus-sign btn-gc"></i> ' + __("Start track changes"),
                id: "add-versioning-btn",
                disabled: true,
                handler: function () {
                    tableStructure.onVersion(record);
                }
            },
            {
                text: '<i class="icon-minus-sign btn-gc"></i> ' + __("Stop track changes"),
                id: "remove-versioning-btn",
                disabled: true,
                handler: function () {
                    tableStructure.onRemoveVersion(record);
                }
            },
            {
                text: '<i class="icon-search btn-gc"></i> ' + __("Index in Elasticsearch"),
                id: "index-in-elasticsearch-btn",
                disabled: (window.gc2Options.esIndexingInGui) ? false : true,
                handler: function () {
                    tableStructure.onIndexInElasticsearch(record);
                }
            }
        ]
    });

};
tableStructure.onDelete = function () {
    "use strict";
    var record = tableStructure.grid.getSelectionModel().getSelected();
    if (!record) {
        return false;
    }
    Ext.MessageBox.confirm(__('Confirm'), __('Are you sure you want to do that?'),
        function (btn) {
            if (btn === "yes") {
                tableStructure.grid.store.remove(record);
            } else {
                return false;
            }
        });
};
tableStructure.onAdd = function (btn, ev) {
    "use strict";
    var field = tableStructure.grid.getStore().recordType,
        u = new field(
            {
                column: "New_field",
                type: "string"
            }
        );
    tableStructure.grid.store.insert(0, u);
};
tableStructure.onVersion = function (record) {
    "use strict";
    Ext.MessageBox.confirm(__('Confirm'), __('This will track changes on the table. For each edit a new version of the feature is made. Four new system columns will be added to the table. Do you want to proceed?'),
        function (btn) {
            if (btn === "yes") {
                Ext.Ajax.request(
                    {
                        url: '/controllers/table/versions/' + record.data.f_table_schema + "." + record.data.f_table_name + '/' + record.data._key_,
                        method: 'put',
                        headers: {
                            'Content-Type': 'application/json; charset=utf-8'
                        },
                        success: function () {
                            tableStructure.grid.getStore().reload();
                        },
                        failure: function (response) {
                            Ext.MessageBox.show({
                                title: __("Failure"),
                                msg: __(Ext.decode(response.responseText).message),
                                buttons: Ext.MessageBox.OK,
                                width: 400,
                                height: 300,
                                icon: Ext.MessageBox.ERROR
                            });
                        }
                    }
                );
            } else {
                return false;
            }
        });
};
tableStructure.onRemoveVersion = function (record) {
    "use strict";
    Ext.MessageBox.confirm(__('Confirm'), __("This will remove 'track changes' from the table. The versions will not be deleted, but all tracking information will be deleted. Do you want to proceed?"),
        function (btn) {
            if (btn === "yes") {
                Ext.Ajax.request(
                    {
                        url: '/controllers/table/versions/' + record.data.f_table_schema + "." + record.data.f_table_name + '/' + record.data._key_,
                        method: 'delete',
                        headers: {
                            'Content-Type': 'application/json; charset=utf-8'
                        },
                        success: function () {
                            tableStructure.grid.getStore().reload();
                        },
                        failure: function (response) {
                            Ext.MessageBox.show({
                                title: __("Failure"),
                                msg: __(Ext.decode(response.responseText).message),
                                buttons: Ext.MessageBox.OK,
                                width: 400,
                                height: 300,
                                icon: Ext.MessageBox.ERROR
                            });
                        }
                    }
                );
            } else {
                return false;
            }
        });
};
tableStructure.onIndexInElasticsearch = function (record) {
    "use strict";
    Ext.MessageBox.confirm(__('Confirm'), __("This will pipe the data from the table/view to an index in Elasticsearch. Do you want to proceed?"),
        function (btn) {
            if (btn === "yes") {
                spinner(true, __("Piping data to Elasticsearch"));
                var param = "&key=" + settings.api_key + (record.data.triggertable ? "&tt=" + record.data.triggertable : "");
                Ext.Ajax.request(
                    {
                        url: '/api/v1/elasticsearch/river/' + screenName + '/' + record.data.f_table_schema + '/' + record.data.f_table_name + '/' + record.data.f_table_name,
                        method: 'post',
                        params: param,
                        headers: {
                            'Content-Type': 'application/json; charset=utf-8'
                        },
                        timeout: 300000,
                        success: function (response) {
                            spinner(false);
                            Ext.MessageBox.show({
                                title: __("Info"),
                                msg: "<b>" + __(Ext.decode(response.responseText).message + "</b>, with errors:<b> " + Ext.decode(response.responseText).errors + "</b><br>Index:<b> " + Ext.decode(response.responseText)._index + "</b><br>Type:<b> " + Ext.decode(response.responseText)._type + "</b><br>Relation type:<b> " + Ext.decode(response.responseText).relation + "</b><br>Trigger installed in:<b> " + Ext.decode(response.responseText).trigger_installed_in) + "</b>",
                                buttons: Ext.MessageBox.OK,
                                width: 400,
                                height: 300,
                                icon: Ext.MessageBox.OK
                            });
                        },
                        failure: function (response) {
                            spinner(false);
                            Ext.MessageBox.show({
                                title: __("Failure"),
                                msg: __(Ext.decode(response.responseText).message),
                                buttons: Ext.MessageBox.OK,
                                width: 400,
                                height: 300,
                                icon: Ext.MessageBox.INFO
                            });
                        }
                    }
                );
            } else {
                return false;
            }
        });
};
tableStructure.onSave = function () {
    "use strict";
    tableStructure.store.save();
};
tableStructure.onWrite = function (store, action, result, transaction, rs) {
    "use strict";
    if (transaction.success) {
        tableStructure.store.load();
    }
};

