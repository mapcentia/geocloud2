/*global Ext:false */
/*global elasticsearch:false */

Ext.namespace('elasticsearch');
Ext.namespace('Ext.ux.grid');
Ext.ux.grid.CheckColumn = Ext.extend(
    Ext.grid.Column, {
        processEvent: function (name, e, grid, rowIndex, colIndex) {
            'use strict';
            if (name === 'click') {
                var record = grid.store.getAt(rowIndex);
                record.set(this.dataIndex,
                    !record.data[this.dataIndex]);
                return false;
            } else {
                return Ext.ux.grid.CheckColumn.superclass.processEvent
                    .apply(this, arguments);
            }
        },
        renderer: function (v, p, record) {
            'use strict';
            p.css += ' x-grid3-check-col-td';
            return String.format('<div class="x-grid3-check-col{0}"> </div>', v ? '-on' : '');
        },
        init: Ext.emptyFn
    }
);
elasticsearch.init = function (record, screenName) {
    'use strict';
    var analyzers = [];
    if (typeof window.gc2Options.es_settings === "object") {
        analyzers.push([]);
        $.each(window.gc2Options.es_settings.settings.analysis.analyzer, function(i, v){
            analyzers.push([i,i]);
        });
    } else {
        alert("No Elasticsearch settings");
    }
    elasticsearch.reader = new Ext.data.JsonReader({
        totalProperty: 'total',
        successProperty: 'success',
        idProperty: 'id',
        root: 'data',
        messageProperty: 'message'
    }, [
        {
            name: 'column',
            allowBlank: false
        },
        {
            name: 'type',
            allowBlank: false
        },
        {
            name: 'elasticsearchtype',
            allowBlank: true
        },
        {
            name: 'format',
            allowBlank: true
        },
        {
            name: 'searchanalyzer',
            allowBlank: true
        },
        {
            name: 'indexanalyzer',
            allowBlank: true
        }
    ]);

    elasticsearch.writer = new Ext.data.JsonWriter({
        writeAllFields: true,
        encode: false
    });
    elasticsearch.proxy = new Ext.data.HttpProxy(
        {
            restful: true,
            api: {
                read: '/controllers/layer/elasticsearch/' + record.get("_key_"),
                update: '/controllers/layer/elasticsearch/' + record.get("f_table_schema") + '.' + record.get("f_table_name") + '/' + record.get("_key_")
            },
            listeners: {
                write: function (store, action, result, transaction, rs) {
                    if (transaction.success) {
                        //elasticsearch.store.load();
                    }
                },
                exception: function (proxy, type, action, options, response, arg) {
                    if (type === 'remote') { // success is false
                        var message = "<p>" + __("Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong") + "</p><br/><textarea rows=5' cols='31'>" + __(response.message) + "</textarea>";
                        Ext.MessageBox.show({
                            title: __('Failure'),
                            msg: message,
                            buttons: Ext.MessageBox.OK,
                            width: 400,
                            height: 300,
                            icon: Ext.MessageBox.ERROR
                        });
                    } else {
                        elasticsearch.winCartomobile.close();
                        Ext.MessageBox.show({
                            title: __('Failure'),
                            msg: __(Ext.decode(response.responseText).message),
                            buttons: Ext.MessageBox.OK,
                            width: 400,
                            height: 300,
                            icon: Ext.MessageBox.ERROR
                        });
                    }

                }
            }
        });

    elasticsearch.store = new Ext.data.Store({
        writer: elasticsearch.writer,
        reader: elasticsearch.reader,
        proxy: elasticsearch.proxy,
        autoSave: true
    });

    elasticsearch.store.load();

    elasticsearch.grid = new Ext.grid.EditorGridPanel({
        iconCls: 'silk-grid',
        store: elasticsearch.store,
        // autoExpandColumn: "desc",
        height: 345,
        // width: 750,
        ddGroup: 'mygridDD',
        enableDragDrop: false,
        viewConfig: {
            forceFit: true
        },
        region: 'center',
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
                    id: "column",
                    header: __("Column"),
                    dataIndex: "column",
                    sortable: true,
                    editable: false,
                    width: 55
                },
                {
                    id: "type",
                    header: __("Native PG type"),
                    dataIndex: "type",
                    sortable: true,
                    width: 35,
                    editable: false
                },
                {
                    id: "elasticsearchtype",
                    header: __("Elasticsearch type"),
                    dataIndex: 'elasticsearchtype',
                    width: 45,
                    editor: {
                        xtype: 'combo',
                        store: new Ext.data.ArrayStore({
                            fields: ['abbr', 'action'],
                            data: [
                                ['string', 'string'],
                                ['string', 'string'],
                                ['integer', 'integer'],
                                ['boolean', 'boolean'],
                                ['float', 'float'],
                                ['date', 'date'],
                                ['geo_point', 'geo_point'],
                                ['geo_shape', 'geo_shape']
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
                    id: "format",
                    header: __("Format"),
                    dataIndex: "format",
                    sortable: true,
                    width: 45,
                    editor: new Ext.form.TextField({
                        allowBlank: true
                    })
                },
                {
                    id: "searchanalyzer",
                    header: __("Search analyzer"),
                    dataIndex: 'searchanalyzer',
                    width: 45,
                    editor: {
                        xtype: 'combo',
                        store: new Ext.data.ArrayStore({
                            fields: ['abbr', 'action'],
                            data: analyzers
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
                    id: "indexanalyzer",
                    header: __("Index analyzer"),
                    dataIndex: 'indexanalyzer',
                    width: 45,
                    editor: {
                        xtype: 'combo',
                        store: new Ext.data.ArrayStore({
                            fields: ['abbr', 'action'],
                            data: analyzers
                        }),
                        displayField: 'action',
                        valueField: 'abbr',
                        mode: 'local',
                        typeAhead: false,
                        editable: false,
                        triggerAction: 'all'
                    }
                }
            ]
        })
    });

};