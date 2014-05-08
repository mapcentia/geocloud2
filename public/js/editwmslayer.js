Ext.namespace('wmsLayer');
wmsLayer.init = function (record) {
    wmsLayer.fieldsForStore = [];
    wmsLayer.numFieldsForStore = [];
    wmsLayer.fieldsForStoreBrackets = [];
    wmsLayer.defaultSql = record.data || "SELECT * FROM " + record.f_table_schema + "." + record.f_table_name;
    $.ajax({
        url: '/controllers/table/columns/' + record.f_table_schema + '.' + record.f_table_name,
        async: false,
        dataType: 'json',
        type: 'GET',
        success: function (data, textStatus, http) {
            var response = data;
            // JSON
            var forStore = response.forStore;
            wmsLayer.fieldsForStore.push("");
            for (var i in forStore) {
                wmsLayer.fieldsForStore.push(forStore[i].name);
                wmsLayer.fieldsForStoreBrackets.push("[" + forStore[i].name + "]");
                if (forStore[i].type === "number" || forStore[i].type === "int") {
                    wmsLayer.numFieldsForStore.push(forStore[i].name);
                }
            }
        }
    });
    wmsLayer.classId = record._key_;
    wmsLayer.store = new Ext.data.JsonStore({
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
                name: 'meta_tiles',
                type: 'boolean'
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
                name: 'maxscaledenom'
            },
            {
                name: 'minscaledenom'
            },
            {
                name: 'geotype'
            }

        ],
        listeners: {
            load: {
                fn: function (store, records, options) {
                    // get the property grid component
                    var propGridLayer = Ext.getCmp('propGridLayer');
                    // make sure the property grid exists
                    if (propGridLayer) {
                        // Remove default sorting
                        delete propGridLayer.getStore().sortInfo;
                        // set sorting of first column to false
                        propGridLayer.getColumnModel().getColumnById('name').sortable = false;
                        // populate the property grid with store data
                        propGridLayer.setSource(store.getAt(0).data);
                    }
                }
            }
        }
    });
    wmsLayer.grid = new Ext.grid.PropertyGrid({
        id: 'propGridLayer',
        autoHeight: true,
        modal: false,
        region: 'west',
        frame: false,
        border: false,
        style: {
            borderBottom: '1px solid #d0d0d0'
        },
        propertyNames: {
            label_column: 'Label item',
            theme_column: 'Class item',
            opacity: 'Opacity',
            label_max_scale: 'Label max scale',
            label_min_scale: 'Label min scale',
            meta_tiles: 'Use meta tiles',
            meta_size: 'Meta tile size',
            meta_buffer: 'Meta buffer size (px)',
            ttl: 'Time to live (TTL)',
            maxscaledenom: 'Max scale',
            minscaledenom: 'Min scale',
            geotype: 'Geom type'
        },

        customRenderers: {
            theme_column: function (v, p) {
                p.attr = "ext:qtip='your tooltip here' ext";
                return v;
            },
            meta_tiles: function (v, p) {
                p.attr = "ext:qtip='Meta tiles fights cut of symboles and labels in tiles. Is slower on creation.' ext";
                return v;
            },
            ttl: function (v, p) {
                p.attr = "ext:qtip='Time to live in the CDN cache.' ext";
                return v;
            }
        },
        customEditors: {
            'label_column': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.fieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'theme_column': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.fieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'opacity': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {}),
            'label_max_scale': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {}),
            'label_min_scale': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {}),
            'ttl': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {}),
            'meta_size': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {}),
            'meta_buffer': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {}),
            'maxscaledenom': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {}),
            'minscaledenom': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {}),
            'geotype': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: ['Default', 'POINT', 'LINE', 'POLYGON'],
                editable: false,
                triggerAction: 'all'
            }), {})
        },
        viewConfig: {
            forceFit: true,
            scrollOffset: 2 // the grid will never have scrollbars
        },
        bbar: [
            {
                text: '<i class="icon-ok btn-gc"></i> Update',
                //iconCls : 'silk-accept',
                handler: function () {
                    var grid = Ext.getCmp("propGridLayer");
                    var id = Ext.getCmp("configStore");
                    var source = grid.getSource();
                    var jsonDataStr = null;
                    jsonDataStr = Ext.encode(source);
                    var requestCg = {
                        url: '/controllers/tile/index/' + wmsLayer.classId,
                        method: 'put',
                        params: {
                            data: jsonDataStr
                        },
                        timeout: 120000,
                        callback: function (options, success, http) {
                            var response = eval('(' + http.responseText + ')');
                            writeFiles(record.f_table_schema + "." + record.f_table_name);
                            wmsLayer.onSubmit(response);
                        }
                    };
                    Ext.Ajax.request(requestCg);
                }
            }
        ]
    });
    wmsLayer.sqlForm = new Ext.FormPanel({
        frame: false,
        border: false,
        id: "sqlForm",
        labelWidth: 1,
        bodyStyle: 'padding: 10px 5px 0px 5px;',
        items: [
            {
                html: '<table>' +
                    '<tr class="x-grid3-row"><td><b>SQL</b></td></tr>' +
                    '</table>',
                border: false,
                bodyStyle: 'padding-left: 3px'            },
            {
                name: '_key_',
                xtype: 'hidden',
                value: record._key_

            },
            {
                xtype: 'textarea',
                width: '95%',
                height: 100,
                labelAlign: 'top',
                name: 'data',
                value: wmsLayer.defaultSql
            }
        ],
        buttons: [
            {
                //iconCls : 'silk-add',
                text: '<i class="icon-ok btn-gc"></i> Update',
                handler: function () {
                    var f = Ext.getCmp('sqlForm');
                    if (f.form.isValid()) {
                        var values = f.form.getValues();
                        // Submit empty if default sql is not changed. Extjs3 is submitting EmptyText!
                        if (values.data === wmsLayer.defaultSql){
                            values.data = "";
                        }
                        values.data = encodeURIComponent(values.data);
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
                                writeFiles(record.f_table_schema + "." + record.f_table_name);
                                App.setAlert(App.STATUS_NOTICE, "Sql updated");
                            },
                            failure: function (response) {
                                Ext.MessageBox.show({
                                    title: 'Failure',
                                    msg: eval('(' + response.responseText + ')').message,
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
    });
};

wmsLayer.onSubmit = function (response) {
    if (response.success) {
        App.setAlert(App.STATUS_NOTICE, "The layer settings are updated");
        writeFiles();
    } else {
        message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows=5' cols='31'>" + result.message + "</textarea>";
        Ext.MessageBox.show({
            title: 'Failure',
            msg: message,
            buttons: Ext.MessageBox.OK,
            width: 300,
            height: 300
        });
    }
};




