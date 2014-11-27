Ext.namespace('classWizards');
classWizards.init = function (record) {
    var customIsSet = false;
    classWizards.setting = Ext.util.JSON.decode(record.classwizard);
    if (typeof classWizards.setting.custom !== "undefined" && typeof classWizards.setting.custom.pre !== "undefined") {
        customIsSet = true;
    }
    classWizards.getAddvalues = function (pre) {
        var values = Ext.getCmp(pre + '_addform').form.getValues();
        var f = Ext.getCmp(pre + "Form").form.getValues();
        f.pre = pre;
        values.custom = f;
        return Ext.util.JSON.encode({data: values});
    };
    classWizards.getAddForm = function (pre) {
        var c = ((typeof classWizards.setting.custom !== "undefined" && typeof classWizards.setting.custom.pre !== "undefined") && pre === classWizards.setting.custom.pre) ? true : false;
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
                            title: __('Symbol (Optional)'),
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
                                            html: __("Size") + __("Combo field. Either select an integer attribute or write an integer for symbol size.", true)
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
                                            name: "symbol",
                                            value: (customIsSet && c) ? classWizards.setting.symbol : ""
                                        }),
                                        {
                                            xtype: "combo",
                                            store: wmsLayer.numFieldsForStore,
                                            editable: true,
                                            triggerAction: "all",
                                            name: "angle",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.angle : ""

                                        },
                                        {
                                            xtype: "combo",
                                            store: wmsLayer.numFieldsForStore,
                                            editable: true,
                                            triggerAction: "all",
                                            name: "symbolSize",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.symbolSize : ""
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
                                            value: (customIsSet && c) ? classWizards.setting.outlineColor : ""

                                        }),
                                        new Ext.ux.form.SpinnerField({
                                            name: "lineWidth",
                                            minValue: 1,
                                            maxValue: 10,
                                            allowDecimals: false,
                                            decimalPrecision: 0,
                                            incrementValue: 1,
                                            accelerate: true,
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.lineWidth : ""

                                        }),
                                        new Ext.ux.form.SpinnerField({
                                            name: "opacity",
                                            minValue: 0,
                                            maxValue: 100,
                                            allowDecimals: false,
                                            decimalPrecision: 0,
                                            incrementValue: 1,
                                            accelerate: true,
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.opacity : ""

                                        })
                                    ]
                                }
                            ]
                        },
                        {
                            xtype: 'fieldset',
                            title: __('Label (Optional)'),
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
                                            html: __("Color") + __("Select a label color.", true)
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
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.labelText : ""
                                        },
                                        new Ext.form.ColorField({
                                            name: "labelColor",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.labelColor : ""
                                        }),
                                        {
                                            xtype: "combo",
                                            store: wmsLayer.numFieldsForStore,
                                            editable: true,
                                            triggerAction: "all",
                                            name: "labelSize",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.labelSize : ""
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
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.labelPosition : ""
                                        },
                                        {
                                            xtype: "combo",
                                            store: wmsLayer.numFieldsForStore,
                                            editable: true,
                                            triggerAction: "all",
                                            name: "labelAngle",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.labelAngle : ""
                                        },
                                        new Ext.form.ColorField({
                                            name: "labelBackgroundcolor",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.labelBackgroundcolor : ""
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
        layout: "border",
        split: true,
        items: [
            new Ext.Panel({
                region: 'center',
                border: false,
                layout: "border",
                items: [
                    new Ext.Panel({
                        region: "center",
                        items: [
                            new Ext.TabPanel({
                                activeTab: (function () {
                                    var i, pre;
                                    if (customIsSet) {
                                        pre = classWizards.setting.custom.pre;
                                        if (pre === "single") {
                                            i = 0;
                                        }
                                        if (pre === "unique") {
                                            i = 1;
                                        }
                                        if (pre === "interval") {
                                            i = 2;
                                        }
                                        if (pre === "cluster") {
                                            i = 3;
                                        }
                                        return i;
                                    } else {
                                        return 0;
                                    }
                                })(),
                                border: false,
                                defaults: {
                                    border: false
                                },
                                plain: true,
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
                                                    {
                                                        xtype: 'fieldset',
                                                        title: __('(Required)'),
                                                        defaults: {
                                                            border: false
                                                        },
                                                        items: [

                                                            {
                                                                xtype: "form",
                                                                id: "singleForm",
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
                                                                                html: __("Color") + __("Select color", true)
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
                                                                                name: "color",
                                                                                allowBlank: false,
                                                                                value: (customIsSet) ? classWizards.setting.custom.color : null
                                                                            })
                                                                        ]
                                                                    }
                                                                ]
                                                            }
                                                        ]
                                                    },
                                                    classWizards.getAddForm("single"),
                                                    {
                                                        border: false,
                                                        items: [
                                                            {
                                                                xtype: 'button',
                                                                text: 'Create single class',
                                                                handler: function () {
                                                                    var f = Ext.getCmp('singleForm');
                                                                    if (f.form.isValid()) {
                                                                        var values = f.form.getValues(),
                                                                            params = classWizards.getAddvalues("single");
                                                                        Ext.Ajax.request({
                                                                            url: '/controllers/classification/single/' + record._key_ + '/' + values.color.replace("#", ""),
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
                                                                                store.load();
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
                                                        title: __('(Required)'),
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
                                                                        html: __("Field") + __("Select attribute field.", true)
                                                                    }
                                                                ]
                                                            },
                                                            {
                                                                xtype: "form",
                                                                id: "uniqueForm",
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
                                                                                emptyText: __("Field"),
                                                                                value: (customIsSet && classWizards.setting.custom.pre === "unique") ? classWizards.setting.custom.value : null
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
                                                                    var f = Ext.getCmp('uniqueForm');
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
                                                                                store.load();
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
                                                        title: __('(Required)'),
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
                                                                        html: __("Type") + __("How should the intervals be calculated", true)
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
                                                                id: "intervalForm",
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
                                                                                value: (customIsSet) ? classWizards.setting.custom.type : (record.type === "RASTER") ? 'Equal' : null

                                                                            },
                                                                            {
                                                                                xtype: "combo",
                                                                                store: wmsLayer.numFieldsForStore,
                                                                                editable: false,
                                                                                triggerAction: "all",
                                                                                name: "value",
                                                                                allowBlank: false,
                                                                                value: (customIsSet && classWizards.setting.custom.pre === "interval") ? classWizards.setting.custom.value : (record.type === "RASTER") ? "pixel" : null
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
                                                                                value: (customIsSet) ? classWizards.setting.custom.num : null
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
                                                                                allowBlank: false,
                                                                                value: (customIsSet) ? classWizards.setting.custom.start : null
                                                                            }),
                                                                            new Ext.form.ColorField({
                                                                                name: "end",
                                                                                allowBlank: false,
                                                                                value: (customIsSet) ? classWizards.setting.custom.end : null
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
                                                                    var f = Ext.getCmp('intervalForm');
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
                                                                                store.load();
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
                                                        title: __('(Required)'),
                                                        defaults: {
                                                            border: false
                                                        },
                                                        items: [
                                                            {

                                                                xtype: "form",
                                                                id: "clusterForm",
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
                                                                                disabled: (record.type === "RASTER") ? true : false,
                                                                                value: (customIsSet) ? classWizards.setting.custom.clusterdistance : null
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
                                                                    var f = Ext.getCmp('clusterForm'), values, params;
                                                                    if (f.form.isValid()) {
                                                                        values = f.form.getValues();
                                                                        Ext.Ajax.request({
                                                                            url: '/controllers/classification/cluster/' + record._key_ + '/' + values.clusterdistance,
                                                                            method: 'put',
                                                                            params: Ext.util.JSON.encode({
                                                                                data: {
                                                                                    custom: {
                                                                                        pre: "cluster",
                                                                                        clusterdistance: values.clusterdistance
                                                                                    }
                                                                                }
                                                                            }),
                                                                            headers: {
                                                                                'Content-Type': 'application/json; charset=utf-8'
                                                                            },
                                                                            success: function (response) {
                                                                                Ext.getCmp("a3").remove(wmsClass.grid);
                                                                                wmsClasses.store.load();
                                                                                wmsLayer.store.load();
                                                                                writeFiles(record._key_);
                                                                                store.load();
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
                            })]
                    }),
                    new Ext.Panel({
                        region: 'east',
                        border: true,
                        width: 150,
                        id: 'wizardLegend',
                        html: ""
                    })
                ]
            })
        ]

    });
};


