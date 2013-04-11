Ext.namespace('tableStructure');
Ext.namespace('Ext.ux.grid');
Ext.ux.grid.CheckColumn = Ext.extend(
    Ext.grid.Column,
    {
        processEvent: function (name, e, grid, rowIndex, colIndex) {
            'use strict'
            if (name == 'click'/* 'mousedown' */) {
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
                v ? '-on' : '');
        },
        init: Ext.emptyFn
    });
tableStructure.init = function (record, screenName) {
    'use strict'

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
        } ,
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
            api: {
                read: '/controller/tables/' + screenName
                    + '/getstructure/' + record.get("f_table_schema")
                    + '.' + record.get("f_table_name"),
                create: '/controller/tables/' + screenName
                    + '/createcolumn/' + record.get("f_table_schema")
                    + '.' + record.get("f_table_name"),
                update: '/controller/tables/' + screenName
                    + '/updatecolumn/' + record.get("f_table_schema")
                    + '.' + record.get("f_table_name") + '/'
                    + record.get("_key_"),
                destroy: '/controller/tables/' + screenName
                    + '/destroycolumn/' + record.get("f_table_schema")
                    + '.' + record.get("f_table_name")
            },
            listeners: {
                write: tableStructure.onWrite,
                exception: function (proxy, type, action, options, response, arg) {
                    if (type === 'remote') { // success is false
                        var message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows=5' cols='31'>"
                            + response.message + "</textarea>";
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
            }
        });

    tableStructure.store = new Ext.data.Store({
        writer: tableStructure.writer,
        reader: tableStructure.reader,
        proxy: tableStructure.proxy,
        autoSave: true
    });

    tableStructure.store.setDefaultSort('sort_id', 'asc');
    tableStructure.store.load();

    tableStructure.grid = new Ext.grid.EditorGridPanel({
        iconCls: 'silk-grid',
        store: tableStructure.store,
        // autoExpandColumn: "desc",
        height: 345,
        // width: 750,
        ddGroup: 'mygridDD',
        enableDragDrop: false,
        viewConfig: {
            forceFit: true
        },
        region: 'north',
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
                    header: "Sort id",
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
                    header: "Column",
                    dataIndex: "column",
                    sortable: true,
                    editor: new Ext.form.TextField({
                        allowBlank: false
                    }),
                    width: 60
                },
                {
                    id: "type",
                    header: "Type",
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
                    header: "Alias",
                    dataIndex: "alias",
                    sortable: true,
                    editor: new Ext.form.TextField({
                        allowBlank: true
                    })
                },
                {
                    id: "querable",
                    xtype: 'checkcolumn',
                    // editor: new Ext.ux.grid.CheckColumn({}),
                    header: 'Querable',
                    dataIndex: 'querable',
                    width: 35
                },
                {
                    id: "link",
                    xtype: 'checkcolumn',
                    // editor: new Ext.ux.grid.CheckColumn({}),
                    header: 'Link',
                    dataIndex: 'link',
                    width: 20
                },
                {
                    id: "linkprefix",
                    header: "Link prefix",
                    dataIndex: "linkprefix",
                    sortable: true,
                    width: 60,
                    editor: new Ext.form.TextField({
                        allowBlank: true
                    })
                },
                {
                    id: "properties",
                    header: "Properties",
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
                text: '<i class="icon-trash btn-gc"></i> Delete column',
                //iconCls : 'silk-delete',
                handler: tableStructure.onDelete
            }
        ]
    });

};
tableStructure.onDelete = function () {
    var record = tableStructure.grid.getSelectionModel().getSelected();
    if (!record) {
        return false;
    }
    Ext.MessageBox.confirm('Confirm', 'Are you sure you want to do that?',
        function (btn) {
            if (btn === "yes") {
                tableStructure.grid.store.remove(record);
            } else {
                return false;
            }
        });
};
tableStructure.onAdd = function (btn, ev) {
    var field = tableStructure.grid.getStore().recordType;
    var u = new field({
        column: "New_field",
        type: "string"
    });
    tableStructure.grid.store.insert(0, u);
};
tableStructure.onSave = function () {
    tableStructure.store.save();
};
tableStructure.onWrite = function (store, action, result, transaction, rs) {
    if (transaction.success) {
        tableStructure.store.load();
    }
};