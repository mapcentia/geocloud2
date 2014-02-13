Ext.namespace('classWizards');
classWizards.init = function (record) {
    classWizards.getAddvalues = function () {
        return Ext.util.JSON.encode({data: Ext.getCmp('addform').form.getValues()});
    };
    classWizards.quantile = new Ext.Panel({
        labelWidth: 1,
        frame: false,
        border: false,
        autoHeight: true,
        region: 'center',
        defaults: {
            border: false,
            bodyStyle: 'padding:5px;'
        },
        items: [
            {
                html: '<table>' +
                    '<tr class="x-grid3-row"><td><hr></td></tr>' +
                    '<tr class="x-grid3-row"><td><b>Single</b></td></tr>' +
                    '<tr class="x-grid3-row"><td><img style="width: 283px" src="/assets/images/single_class.png"></td></tr>' +
                    '</table>'
            },
            {
                defaults: {
                    border: false
                },
                items: [
                    {
                        layout: 'form',
                        bodyStyle: 'margin-right:5px; padding-left: 5px',
                        items: [
                            {
                                xtype: 'button',
                                text: 'Create',
                                handler: function () {
                                    var params = classWizards.getAddvalues();
                                    Ext.Ajax.request({
                                        url: '/controllers/classification/single/' + record._key_,
                                        method: 'put',
                                        params: params,
                                        headers: {
                                            'Content-Type': 'application/json; charset=utf-8'
                                        },
                                        success: function (response) {
                                            Ext.getCmp("a3").remove(wmsClass.grid);
                                            wmsClasses.store.load();
                                            writeFiles();
                                            clearTileCache(record.f_table_schema + "." + record.f_table_name);
                                            App.setAlert(App.STATUS_NOTICE, eval('(' + response.responseText + ')').message);
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
                                }
                            }
                        ]
                    }
                ]
            },
            {
                html: '<table>' +
                    '<tr class="x-grid3-row"><td><hr></td></tr>' +
                    '<tr class="x-grid3-row"><td><b>Unique values</b></td></tr>' +
                    '<tr class="x-grid3-row"><td><img style="width: 283px" src="/assets/images/unique_classes.png"></td></tr>' +
                    '</table>'

            },
            {
                defaults: {
                    border: false
                },
                layout: 'column',
                items: [
                    {
                        xtype: "form",
                        id: "uniqueform",
                        layout: "form",
                        items: [
                            {
                                xtype: 'container',
                                layout: 'hbox',
                                defaults: {
                                    width: 100
                                },
                                items: [
                                    {
                                        xtype: "combo",
                                        store: wmsLayer.fieldsForStore,
                                        editable: false,
                                        triggerAction: "all",
                                        name: "value",
                                        emptyText: "Field",
                                        allowBlank: false
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        layout: 'form',
                        bodyStyle: 'margin-right:5px; padding-left: 5px',
                        items: [
                            {
                                xtype: 'button',
                                text: 'Create',
                                handler: function () {
                                    var f = Ext.getCmp('uniqueform');
                                    if (f.form.isValid()) {
                                        var values = f.form.getValues(),
                                            params = classWizards.getAddvalues();
                                        Ext.Ajax.request({
                                            url: '/controllers/classification/unique/' + record._key_ + '/' + values.value,
                                            method: 'put',
                                            params: params,
                                            headers: {
                                                'Content-Type': 'application/json; charset=utf-8'
                                            },
                                            success: function (response) {
                                                Ext.getCmp("a3").remove(wmsClass.grid);
                                                wmsClasses.store.load();
                                                writeFiles();
                                                clearTileCache(record.f_table_schema + "." + record.f_table_name);
                                                App.setAlert(App.STATUS_NOTICE, eval('(' + response.responseText + ')').message);
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
                    }
                ]
            },
            {
                html: '<table>' +
                    '<tr class="x-grid3-row"><td><hr></td></tr>' +
                    '<tr class="x-grid3-row"><td><b>Intervals</b></td></tr>' +
                    '<tr class="x-grid3-row"><td><img style="width: 283px" src="/assets/images/interval_classes.png"></td></tr>' +
                    '</table>'

            },
            {
                defaults: {
                    border: false
                },
                //layout: 'column',
                items: [
                    {
                        xtype: "form",
                        id: "equalform",
                        layout: "form",
                        items: [
                            {
                                xtype: 'container',
                                layout: 'hbox',
                                defaults: {
                                    width: 95
                                },
                                items: [
                                    {
                                        xtype: "combo",
                                        store: new Ext.data.ArrayStore({
                                            fields: ['type', 'display'],
                                            data: [
                                                ['equal', 'Equal'],
                                                ['quantile', 'Quantile']
                                            ]
                                        }),
                                        displayField: 'display',
                                        valueField: 'type',
                                        editable: false,
                                        triggerAction: "all",
                                        name: "type",
                                        emptyText: "Type",
                                        allowBlank: false,
                                        mode: 'local'
                                    },
                                    {
                                        xtype: "combo",
                                        store: wmsLayer.numFieldsForStore,
                                        editable: false,
                                        triggerAction: "all",
                                        name: "value",
                                        emptyText: "Numeric field",
                                        allowBlank: false
                                    },
                                    new Ext.ux.form.SpinnerField({
                                        name: "num",
                                        minValue: 1,
                                        maxValue: 20,
                                        allowDecimals: false,
                                        decimalPrecision: 0,
                                        incrementValue: 1,
                                        accelerate: true,
                                        allowBlank: false,
                                        emptyText: "# of colors"
                                    })
                                ]
                            },
                            {
                                xtype: 'box',
                                height: 7
                            },
                            {
                                xtype: 'container',
                                layout: 'hbox',
                                width: 285,
                                defaults: {
                                    width: 95
                                },
                                items: [
                                    new Ext.form.ColorField({
                                        name: "start",
                                        emptyText: "Start color",
                                        allowBlank: false
                                    }),
                                    new Ext.form.ColorField({
                                        name: "end",
                                        emptyText: "End color",
                                        allowBlank: false
                                    }),
                                    {
                                        layout: 'form',
                                        bodyStyle: 'margin-right:5px; padding-left: 5px',
                                        border: false,
                                        items: [
                                            {
                                                xtype: 'button',
                                                text: 'Create',
                                                width: 'auto',
                                                handler: function () {
                                                    var f = Ext.getCmp('equalform');
                                                    if (f.form.isValid()) {
                                                        var values = f.form.getValues(),
                                                            params = classWizards.getAddvalues();
                                                        Ext.Ajax.request({
                                                            url: '/controllers/classification/' + values.type.toLowerCase() + '/' + record._key_ + '/' +
                                                                values.value + '/' +
                                                                values.num + '/' +
                                                                values.start.replace("#", "") + '/' +
                                                                values.end.replace("#", "") + '/',
                                                            method: 'put',
                                                            params: params,
                                                            headers: {
                                                                'Content-Type': 'application/json; charset=utf-8'
                                                            },
                                                            success: function (response) {
                                                                Ext.getCmp("a3").remove(wmsClass.grid);
                                                                wmsClasses.store.load();
                                                                writeFiles();
                                                                clearTileCache(record.f_table_schema + "." + record.f_table_name);
                                                                App.setAlert(App.STATUS_NOTICE, eval('(' + response.responseText + ')').message);
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
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        html: '<table>' +
                            '<tr class="x-grid3-row"><td><hr style="width:100%"></td></tr>' +
                            '<tr class="x-grid3-row"><td><b>Additional settings</b></td></tr>' +
                            '<tr class="x-grid3-row"><td>These setting are applied to all created classes. Set them before hitting a create button above.</td></tr>' +
                            '<tr class="x-grid3-row"><td><img style="width: 283px; height: 0" src="/assets/images/single_class.png"></td></tr>' +

                            '</table>'
                    },
                    {
                        xtype: "form",
                        id: "addform",
                        layout: "form",
                        items: [
                            {
                                xtype: 'container',
                                layout: 'hbox',
                                width: 285,
                                defaults: {
                                    width: 95
                                },
                                items: [
                                    {
                                        xtype: 'box',
                                        html: "Label text"
                                    },
                                    {
                                        xtype: 'box',
                                        html: "Label color"
                                    },
                                    {
                                        xtype: 'box',
                                        html: "Label size"
                                    }
                                ]
                            },
                            {
                                xtype: 'container',
                                layout: 'hbox',
                                width: 285,
                                defaults: {
                                    width: 95
                                },
                                items: [
                                    {
                                        xtype: "combo",
                                        store: wmsLayer.fieldsForStoreBrackets,
                                        editable: true,
                                        triggerAction: "all",
                                        name: "labelText",
                                        allowBlank: true
                                    },
                                    new Ext.form.ColorField({
                                        name: "labelColor",
                                        allowBlank: true
                                    }),
                                    {
                                        xtype: "combo",
                                        store: wmsLayer.numFieldsForStore,
                                        editable: true,
                                        triggerAction: "all",
                                        name: "labelSize",
                                        allowBlank: true
                                    }
                                ]
                            },
                            {
                                xtype: 'box',
                                height: 7
                            },
                            {
                                xtype: 'container',
                                layout: 'hbox',
                                width: 285,
                                defaults: {
                                    width: 95
                                },
                                items: [
                                    {
                                        xtype: 'box',
                                        html: "Symbol"
                                    },
                                    {
                                        xtype: 'box',
                                        html: "Symbol angle"
                                    },
                                    {
                                        xtype: 'box',
                                        html: "Symbol size"
                                    }
                                ]
                            },
                            {
                                xtype: 'container',
                                layout: 'hbox',
                                width: 285,
                                defaults: {
                                    width: 95
                                },
                                items: [
                                    new Ext.form.ComboBox({
                                        store: ['', 'circle', 'square', 'triangle', 'hatch1', 'dashed1', 'dot-dot', 'dashed-line-short', 'dashed-line-long', 'dash-dot', 'dash-dot-dot'],
                                        editable: false,
                                        triggerAction: 'all',
                                        name: "symbol"
                                    }),
                                    {
                                        xtype: "combo",
                                        store: wmsLayer.numFieldsForStore,
                                        editable: true,
                                        triggerAction: "all",
                                        name: "angle",
                                        allowBlank: true
                                    },
                                    {
                                        xtype: "combo",
                                        store: wmsLayer.numFieldsForStore,
                                        editable: true,
                                        triggerAction: "all",
                                        name: "symbolSize",
                                        allowBlank: true,
                                        toolTip: "xsd"
                                    }
                                ]
                            },
                            {
                                xtype: 'box',
                                height: 7
                            },
                            {
                                xtype: 'container',
                                layout: 'hbox',
                                width: 285,
                                defaults: {
                                    width: 95
                                },
                                items: [
                                    {
                                        xtype: 'box',
                                        html: "Outline color"
                                    },
                                    {
                                        xtype: 'box',
                                        html: "Outline width"
                                    },
                                    {
                                        xtype: 'box',
                                        html: "Opacity"
                                    }
                                ]
                            },
                            {
                                xtype: 'container',
                                layout: 'hbox',
                                width: 285,
                                defaults: {
                                    width: 95
                                },
                                items: [
                                    new Ext.form.ColorField({
                                        name: "outlineColor",
                                        allowBlank: true
                                    }),
                                    new Ext.ux.form.SpinnerField({
                                        name: "lineWidth",
                                        minValue: 1,
                                        maxValue: 10,
                                        allowDecimals: false,
                                        decimalPrecision: 0,
                                        incrementValue: 1,
                                        accelerate: true,
                                        allowBlank: true
                                    }),
                                    new Ext.ux.form.SpinnerField({
                                        name: "opacity",
                                        minValue: 0,
                                        maxValue: 100,
                                        allowDecimals: false,
                                        decimalPrecision: 0,
                                        incrementValue: 1,
                                        accelerate: true,
                                        allowBlank: true
                                    })
                                ]
                            }
                        ]
                    }
                ]
            }
        ]

    });
};
