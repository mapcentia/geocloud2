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
        success: function (data) {
            var response = data,
                forStore = response.forStore,
                i;
            wmsLayer.fieldsForStore.push("");
            wmsLayer.numFieldsForStore.push("");
            wmsLayer.fieldsForStoreBrackets.push("");
            for (i in forStore) {
                if (forStore.hasOwnProperty(i)) {
                    wmsLayer.fieldsForStore.push(forStore[i].name);
                    wmsLayer.fieldsForStoreBrackets.push("[" + forStore[i].name + "]");
                    if (forStore[i].type === "number" || forStore[i].type === "int") {
                        wmsLayer.numFieldsForStore.push(forStore[i].name);
                    }
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
            },
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
            label_column: __('Label item'),
            theme_column: 'Class item',
            opacity: 'Opacity',
            label_max_scale: 'Label min scale', //LABELMAXSCALEDENOM
            label_min_scale: 'Label max scale', //LABELMINSCALEDENOM
            cluster: 'Clustering distance',
            maxscaledenom: __('Min scale') + __('Minimum scale at which this layer is labeled. Scale is given as the denominator of the actual scale fraction, for example for a map at a scale of 1:24,000 use 24000.', true),
            minscaledenom: __('Max scale') + __('Maximum scale at which this layer is labeled. Scale is given as the denominator of the actual scale fraction, for example for a map at a scale of 1:24,000 use 24000.', true),
            symbolscaledenom: 'Symbole scale' + __("The scale at which symbols and/or text appear full size. This allows for dynamic scaling of objects based on the scale of the map. If not set then this layer will always appear at the same size. Scale is given as the denominator of the actual scale fraction, for example for a map at a scale of 1:24,000 use 24000.", true),
            geotype: 'Geom type',
            offsite: 'Offsite'
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
            }), {}),
            'cluster': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {})
        },
        viewConfig: {
            forceFit: true,
            scrollOffset: 2 // the grid will never have scrollbars
        },
        tbar: [
            {
                text: '<i class="fa fa-check"></i> ' + __('Update'),
                //iconCls : 'silk-accept',
                handler: function () {
                    var grid = Ext.getCmp("propGridLayer");
                    var id = Ext.getCmp("configStore");
                    var source = grid.getSource();
                    var param = {
                        data: source
                    };
                    param = Ext.util.JSON.encode(param);

                    Ext.Ajax.request({
                        url: '/controllers/tile/index/' + wmsLayer.classId,
                        method: 'put',
                        params: param,
                        headers: {
                            'Content-Type': 'application/json; charset=utf-8'
                        },
                        success: function (response) {
                            writeFiles(record._key_);
                            App.setAlert(App.STATUS_NOTICE, __("The layer settings are updated"));
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
                bodyStyle: 'padding-left: 3px'
            },
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
                text: '<i class="fa fa-check"></i> ' + __('Update SQL'),
                handler: function () {
                    var f = Ext.getCmp('sqlForm');
                    if (f.form.isValid()) {
                        var values = f.form.getValues();
                        // Submit empty if default sql is not changed. Extjs3 is submitting EmptyText!
                        if (values.data === wmsLayer.defaultSql) {
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
                                writeFiles(record._key_);
                                App.setAlert(App.STATUS_NOTICE, "Sql updated");
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
    });
};




