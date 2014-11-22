Ext.namespace('classWizards');
classWizards.init = function (record) {
    classWizards.getAddvalues = function (pre) {
        return Ext.util.JSON.encode({data: Ext.getCmp(pre + '_addform').form.getValues()});
    };
    classWizards.getAddForm = function (pre) {
        return new Ext.Panel({
            region: "east",
            border: false,
            items: [
                {
                    xtype: "form",
                    id: pre + "_addform",
                    layout: "form",
                    border: false,
                    items: [
                        {
                            xtype: 'fieldset',
                            title: __('Symbol'),
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
                                            html: __("Symbol") + __("Select a symbol for layer drawing. Leave empty for solid line and area style.", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Angle") + __("Combo field. Either select an integer attribute or write an integer for symbol angling.", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html:  __("Size") + __("Combo field. Either select an integer attribute or write an integer for symbol size.", true)
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
                                            store: ['', 'circle', 'square', 'triangle', 'hatch1', 'dashed1', 'dot-dot', 'dashed-line-short', 'dashed-line-long', 'dash-dot', 'dash-dot-dot', 'arrow'],
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
                                            allowBlank: true,

                                        },
                                        {
                                            xtype: "combo",
                                            store: wmsLayer.numFieldsForStore,
                                            editable: true,
                                            triggerAction: "all",
                                            name: "symbolSize",
                                            allowBlank: true,
                                            toolTip: "xsd",
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
                                            html: __("Outline color") + __('Pick color for outlining features.', true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Line width") + __('Pick thickness for outline.', true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Opacity") + __('Set opacity level between 1 and 100, where 100 is solid.', true)
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
                                            allowBlank: true,

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
                        },
                        {
                            xtype: 'fieldset',
                            title: __('Label'),
                            items: [
                                {
                                    xtype: 'container',
                                    layout: 'hbox',
                                    defaults: {
                                        width: 95
                                    },
                                    items: [
                                        {
                                            xtype: 'box',
                                            html: __("Text") + __("Combo field. Select a attribute for label. You write around like &apos;My label [attribute]&apos; or concatenate two or more attributes like &apos;[attribute1] [attribute2]&apos;.", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Color")  + __("Select a label color.", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Size") + __("Combo field. Either select an integer attribute or write an integer for label size.", true)
                                        }
                                    ]
                                },
                                {
                                    xtype: 'container',
                                    layout: 'hbox',
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
                                    defaults: {
                                        width: 95
                                    },
                                    items: [
                                        {
                                            xtype: 'box',
                                            html: __("Position") + __("Select a label position.", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Angle") + __("Combo field. Either select an integer attribute or write an integer for label size.", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Background") + __("Combo field. Either select an integer attribute or write an integer for label size.", true)
                                        }
                                    ]
                                },
                                {
                                    xtype: 'container',
                                    layout: 'hbox',
                                    defaults: {
                                        width: 95
                                    },
                                    items: [
                                        {
                                            xtype: "combo",
                                            store: ['auto', 'ul', 'uc', 'ur', 'cl', 'cc', 'cr', 'll', 'lc', 'lr'],
                                            editable: true,
                                            triggerAction: "all",
                                            name: "labelPosition",
                                            allowBlank: true
                                        },
                                        {
                                            xtype: "combo",
                                            store: wmsLayer.numFieldsForStore,
                                            editable: true,
                                            triggerAction: "all",
                                            name: "labelAngle",
                                            allowBlank: true
                                        },
                                        new Ext.form.ColorField({
                                            name: "labelBackgroundcolor",
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
    classWizards.quantile = new Ext.Panel({
        labelWidth: 1,
        frame: false,
        border: false,
        //autoHeight: true,
        height: 772,
        region: 'center',
        layout: "fit",
        split: true,
        items: [
            new Ext.Panel({
                region: 'center',
                border: false,
                defaults: {
                    border: false
                },
                items: [
                    new Ext.TabPanel({
                        activeTab: 0,
                        defaults: {
                            border: false
                        },
                        items: [
                            {
                                title: __("Single"),
                                defaults: {
                                    border: false
                                },
                                items: [
                                    {
                                        html: '<table>' +
                                        '<tr class="x-grid3-row"><td class="map-thumbs" style="background-image:url(\'/assets/images/single_class.png\')"></td></tr>' +
                                        '</table>'
                                    },
                                    {
                                        padding: "5px",
                                        border: false,
                                        items: [
                                            classWizards.getAddForm("single"),
                                            {
                                                xtype: 'box',
                                                height: 15
                                            },
                                            {
                                                border: false,
                                                items: [
                                                    {
                                                        xtype: 'button',
                                                        text: 'Create single class',
                                                        handler: function () {
                                                            var params = classWizards.getAddvalues("single");
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
                                                                    wmsLayer.store.load();
                                                                    writeFiles(record._key_);
                                                                    App.setAlert(__(App.STATUS_NOTICE), __(Ext.decode(response.responseText).message));
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
                                                        }
                                                    }
                                                ]
                                            }
                                        ]
                                    }
                                ]
                            },
                            {
                                title: __("Unique"),
                                defaults: {
                                    border: false
                                },
                                items: [
                                    {
                                        html: '<table>' +
                                        '<tr class="x-grid3-row"><td class="map-thumbs" style="background-image:url(\'/assets/images/unique_classes.png\')"></td></tr>' +
                                        '</table>'
                                    },
                                    {

                                        padding: "5px",
                                        items: [
                                            {
                                                xtype: 'fieldset',
                                                title: __('Values'),
                                                defaults: {
                                                    border: false
                                                },
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
                                                                html:  __("Field") + __("Select attribute field.", true)
                                                            }
                                                        ]
                                                    },
                                                    {
                                                        xtype: "form",
                                                        id: "uniqueform",
                                                        layout: "form",
                                                        items: [
                                                            {
                                                                xtype: 'container',
                                                                defaults: {
                                                                    width: 95
                                                                },
                                                                items: [
                                                                    {
                                                                        xtype: "combo",
                                                                        store: wmsLayer.fieldsForStore,
                                                                        editable: false,
                                                                        triggerAction: "all",
                                                                        name: "value",
                                                                        allowBlank: false,
                                                                        disabled: (record.type === "RASTER") ? true : false,
                                                                        emptyText: __("Field")
                                                                    }
                                                                ]
                                                            }
                                                        ]
                                                    }
                                                ]
                                            },
                                            classWizards.getAddForm("unique"),
                                            {
                                                layout: 'form',
                                                border: false,
                                                items: [
                                                    {
                                                        xtype: 'button',
                                                        text: 'Create unique values',
                                                        disabled: (record.type === "RASTER") ? true : false,
                                                        handler: function () {
                                                            var f = Ext.getCmp('uniqueform');
                                                            if (f.form.isValid()) {
                                                                var values = f.form.getValues(),
                                                                    params = classWizards.getAddvalues("unique");
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
                                                                        wmsLayer.store.load();
                                                                        writeFiles(record._key_);
                                                                        App.setAlert(__(App.STATUS_NOTICE), __(Ext.decode(response.responseText).message));
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
                                            }
                                        ]
                                    }

                                ]
                            },
                            {
                                title: __("Intervals"),
                                defaults: {
                                    border: false
                                },
                                items: [{
                                    html: '<table>' +
                                    '<tr class="x-grid3-row"><td class="map-thumbs" style="background-image:url(\'/assets/images/interval_classes.png\')"></td></tr>' +
                                    '</table>'
                                },
                                    {

                                        padding: '5px',
                                        items: [
                                            {
                                                xtype: 'fieldset',
                                                title: __('Values'),
                                                defaults: {
                                                    border: false
                                                },
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
                                                                html:  __("Type") + __("How should the intervals be calculated", true)
                                                            },
                                                            {
                                                                xtype: 'box',
                                                                html: __("Numeric field") + __("Select attribute field. Only numeric is shown.", true)
                                                            },
                                                            {
                                                                xtype: 'box',
                                                                html: __("# of colors") + __("How many intervals should be calculated.", true)
                                                            }
                                                        ]
                                                    },
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
                                                                            data: (record.type === "RASTER") ? [
                                                                                ['equal', 'Equal']
                                                                            ] : [
                                                                                ['equal', 'Equal'],
                                                                                ['quantile', 'Quantile']
                                                                            ]

                                                                        }),
                                                                        displayField: 'display',
                                                                        valueField: 'type',
                                                                        editable: false,
                                                                        triggerAction: "all",
                                                                        name: "type",
                                                                        allowBlank: false,
                                                                        mode: 'local',
                                                                        value: (record.type === "RASTER") ? 'Equal' : null
                                                                    },
                                                                    {
                                                                        xtype: "combo",
                                                                        store: wmsLayer.numFieldsForStore,
                                                                        editable: false,
                                                                        triggerAction: "all",
                                                                        name: "value",
                                                                        allowBlank: false,
                                                                        value: (record.type === "RASTER") ? "pixel" : null
                                                                    },
                                                                    new Ext.ux.form.SpinnerField({
                                                                        name: "num",
                                                                        minValue: 1,
                                                                        maxValue: 20,
                                                                        allowDecimals: false,
                                                                        decimalPrecision: 0,
                                                                        incrementValue: 1,
                                                                        accelerate: true,
                                                                        allowBlank: false
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
                                                                    {
                                                                        xtype: 'box',
                                                                        html: __("Start color") + __("Select start color in the color ramp.", true)
                                                                    },
                                                                    {
                                                                        xtype: 'box',
                                                                        html: __("End color") + __("Select end color in the color ramp.", true)
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
                                                                        name: "start",
                                                                        allowBlank: false
                                                                    }),
                                                                    new Ext.form.ColorField({
                                                                        name: "end",
                                                                        allowBlank: false
                                                                    })

                                                                ]
                                                            }
                                                        ]
                                                    }
                                                ]
                                            },

                                            classWizards.getAddForm("interval"),
                                            {
                                                layout: 'form',
                                                border: false,
                                                items: [
                                                    {
                                                        xtype: 'button',
                                                        border: false,
                                                        text: __("Create intervals"),
                                                        width: 'auto',
                                                        handler: function () {
                                                            var f = Ext.getCmp('equalform');
                                                            if (f.form.isValid()) {
                                                                var values = f.form.getValues(),
                                                                    params = classWizards.getAddvalues("interval");
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
                                                                        wmsLayer.store.load();
                                                                        writeFiles(record._key_);
                                                                        App.setAlert(__(App.STATUS_NOTICE), __(Ext.decode(response.responseText).message));
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
                                    }]
                            },
                            {
                                title: __("Clusters"),
                                defaults: {
                                    border: false
                                },
                                items: [{
                                    html: '<table>' +
                                    '<tr class="x-grid3-row"><td class="map-thumbs" style="background-image:url(\'/assets/images/cluster_classes.png\')"></td></tr>' +
                                    '</table>'
                                },
                                    {
                                        padding: "5px",
                                        items: [
                                            {
                                                xtype: 'fieldset',
                                                title: __('Values'),
                                                defaults: {
                                                    border: false
                                                },
                                                items: [
                                                    {

                                                        xtype: "form",
                                                        id: "clusterform",
                                                        layout: "form",
                                                        items: [
                                                            {
                                                                xtype: 'container',
                                                                defaults: {
                                                                    width: 150
                                                                },
                                                                items: [
                                                                    new Ext.ux.form.SpinnerField({
                                                                        name: "clusterdistance",
                                                                        minValue: 1,
                                                                        allowDecimals: false,
                                                                        decimalPrecision: 0,
                                                                        incrementValue: 1,
                                                                        accelerate: true,
                                                                        allowBlank: false,
                                                                        emptyText: __("Cluster distance"),
                                                                        disabled: (record.type === "RASTER") ? true : false
                                                                    })
                                                                ]
                                                            }
                                                        ]
                                                    }
                                                ]
                                            },
                                            {
                                                layout: 'form',
                                                border: false,
                                                items: [
                                                    {
                                                        xtype: 'button',
                                                        text: 'Create clusters',
                                                        disabled: (record.type === "RASTER") ? true : false,
                                                        handler: function () {
                                                            var f = Ext.getCmp('clusterform'), values, params;
                                                            if (f.form.isValid()) {
                                                                values = f.form.getValues();
                                                                //params = classWizards.getAddvalues();
                                                                Ext.Ajax.request({
                                                                    url: '/controllers/classification/cluster/' + record._key_ + '/' + values.clusterdistance,
                                                                    method: 'put',
                                                                    //params: params,
                                                                    headers: {
                                                                        'Content-Type': 'application/json; charset=utf-8'
                                                                    },
                                                                    success: function (response) {
                                                                        Ext.getCmp("a3").remove(wmsClass.grid);
                                                                        wmsClasses.store.load();
                                                                        wmsLayer.store.load();
                                                                        writeFiles(record._key_);
                                                                        App.setAlert(__(App.STATUS_NOTICE), __(Ext.decode(response.responseText).message));
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
                                            }
                                        ]
                                    }]
                            }
                        ]
                    })


                ]
            })
        ]

    });
};

