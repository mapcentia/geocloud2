/*
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

/*global Ext:false */
/*global classWizards:false */
/*global store:false */
/*global App:false */
/*global wmsLayer:false */
/*global __:false */
Ext.namespace('classWizards');
classWizards.init = function (record) {
    "use strict";
    var customIsSet = false, legendPanel, classGrid,
        classes,
        classStore = new Ext.data.Store({
            fields: ['id', 'name', 'color'],
            writer: new Ext.data.JsonWriter({
                writeAllFields: false,
                encode: false
            }),
            reader: new Ext.data.ArrayReader({
                idIndex: 0,
                root: 'data'
            }, Ext.data.Record.create([
                {name: 'id'},
                {name: 'name'},
                {name: 'color'}
            ])),
            proxy: new Ext.data.HttpProxy({
                restful: true,
                type: 'json',
                api: {
                    update: '/controllers/classification/index/' + record._key_
                },
                listeners: {
                    write: function () {
                        classWizards.clearAfterUpdate();
                        wmsClasses.store.load();
                        writeFiles(record._key_, map);
                        store.load();
                    },
                    exception: function (proxy, type, action, options, response, arg) {
                        if (response.status !== 200) {
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
        }),
        updateClassGrid = function () {
            var myData = [], i;
            classes = Ext.decode(store.getById(record._key_).json["class"]);
            for (i = 0; i < classes.length; i = i + 1) {
                myData.push([i, classes[i].name, classes[i].color]);
            }
            classStore.loadData({data: myData});
        };

    store.load({
        callback: function () {
            updateClassGrid();
        }
    });

    classWizards.setting = Ext.util.JSON.decode(record.classwizard);
    if (typeof classWizards.setting.custom !== "undefined" && typeof classWizards.setting.custom.pre !== "undefined") {
        customIsSet = true;
    }

    classWizards.getAddvalues = function (pre) {
        var values = Ext.getCmp(pre + '_addform').form.getFieldValues(),
            f = Ext.getCmp(pre + "Form").form.getValues();
        f.pre = pre;
        values.custom = f;
        values.custom.force = Ext.getCmp("forceRecreation").getValue();
        return Ext.util.JSON.encode({data: values});
    };
    classWizards.getAddForm = function (pre) {
        var c = ((typeof classWizards.setting.custom !== "undefined" && typeof classWizards.setting.custom.pre !== "undefined") && pre === classWizards.setting.custom.pre) ? true : false;
        return new Ext.Panel({
            region: "east",
            border: false,
            forceLayout: true,
            items: [
                {
                    border: false,
                    xtype: "form",
                    id: pre + "_addform",
                    layout: "form",
                    items: [
                        {
                            xtype: 'fieldset',
                            title: __('Symbol (Optional)'),
                            items: [
                                {
                                    xtype: 'container',
                                    layout: 'hbox',
                                    defaults: {
                                        width: 95,
                                        style: 'padding-bottom: 5px'
                                    },
                                    items: [
                                        {
                                            xtype: 'box',
                                            html: __("Outline color") + __('Color to use for outlining polygons and certain marker symbols (ellipse, vector polygons and truetype). Has no effect for lines.', true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Pattern") + __('Used to define a dash pattern for line work (lines, polygon outlines, hatch lines, …). The numbers (doubles) specify the lengths of the dashes and gaps of the dash pattern in layer SIZEUNITS. When scaling of symbols is in effect (SYMBOLSCALEDENOM is specified for the LAYER), the numbers specify the lengths of the dashes and gaps in layer SIZEUNITS at the map scale 1:SYMBOLSCALEDENOM.', true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Line cap") + __('Sets the line cap type for lines. Default is round.', true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Symbol") + __("Select a symbol for layer drawing. Leave empty for solid line and area style.", true)
                                        },
                                    ]
                                },
                                {
                                    xtype: 'container',
                                    layout: 'hbox',
                                    defaults: {
                                        width: 95
                                    },
                                    items: [
                                        new Ext.form.ColorField({
                                            name: "outlineColor",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.outlineColor : ""
                                        }),
                                        new Ext.form.TextField({
                                            name: "pattern",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.pattern : "",
                                            style: {
                                                borderRight: "15px"
                                            }
                                        }),
                                        new Ext.form.ComboBox({
                                            store: ['round', 'butt', 'square'],
                                            editable: false,
                                            triggerAction: 'all',
                                            name: "linecap",
                                            value: (customIsSet && c) ? classWizards.setting.linecap : ""
                                        }),
                                        new Ext.form.ComboBox({
                                            store: ['', 'circle', 'square', 'triangle', 'hatch1', 'dashed1', 'dot-dot', 'dashed-line-short', 'dashed-line-long', 'dash-dot', 'dash-dot-dot', 'arrow'],
                                            editable: false,
                                            triggerAction: 'all',
                                            name: "symbol",
                                            value: (customIsSet && c) ? classWizards.setting.symbol : ""
                                        }),
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
                                        width: 95,
                                        style: 'padding-bottom: 5px'
                                    },
                                    items: [
                                        {
                                            xtype: 'box',
                                            html: __("Size") + __("Height, in pixels, of the symbol/pattern to be used. Combo field: Either select an integer attribute or write an integer.", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Line width") + __('Thickness of line work drawn, in pixels.', true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Angle") + __("Angle, given in degrees, to rotate the symbol (counter clockwise). Combo field: Either select an integer attribute or write an integer.", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Gap") + __('specifies the distance between SYMBOLs (center to center) for decorated lines and polygon fills in layer SIZEUNITS. For polygon fills, GAP specifies the distance between SYMBOLs in both the X and the Y direction. For lines, the centers of the SYMBOLs are placed on the line. For lines, a negative GAP value will cause the symbols’ X axis to be aligned relative to the tangent of the line. For lines, a positive GAP value aligns the symbols’ X axis relative to the X axis of the output device.', true)
                                        },

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
                                            store: wmsLayer.numFieldsForStore,
                                            editable: true,
                                            triggerAction: "all",
                                            name: "symbolSize",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.symbolSize : ""
                                        },
                                        new Ext.ux.form.SpinnerField({
                                            name: "lineWidth",
                                            minValue: 0,
                                            maxValue: 10,
                                            allowDecimals: false,
                                            decimalPrecision: 0,
                                            incrementValue: 1,
                                            accelerate: true,
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.lineWidth : ""

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
                                        new Ext.ux.form.SpinnerField({
                                            name: "gap",
                                            allowDecimals: false,
                                            decimalPrecision: 0,
                                            incrementValue: 1,
                                            accelerate: true,
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.gap : ""
                                        }),

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
                                        width: 95,
                                        style: 'padding-bottom: 5px'
                                    },
                                    items: [
                                        {
                                            xtype: 'box',
                                            html: __("Opacity") + __('Set opacity level between 1 and 100, where 100 is solid.', true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Geotransform") + __('...', true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Min size") + __('...', true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Max size") + __('...', true)
                                        },
                                    ]
                                },
                                {
                                    xtype: 'container',
                                    layout: 'hbox',
                                    defaults: {
                                        width: 95
                                    },
                                    items: [
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
                                        }),
                                        new Ext.form.ComboBox({
                                            store: ['', 'bbox', 'centroid', 'end', 'labelpnt', 'labelpoly', 'start', 'vertices'],
                                            editable: false,
                                            triggerAction: 'all',
                                            name: "geotransform",
                                            value: (customIsSet && c) ? classWizards.setting.geotransform : ""
                                        }),
                                        new Ext.ux.form.SpinnerField({
                                            name: "minsize",
                                            minValue: 0,
                                            allowDecimals: false,
                                            decimalPrecision: 0,
                                            incrementValue: 1,
                                            accelerate: true,
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.minsize : ""
                                        }),
                                        new Ext.ux.form.SpinnerField({
                                            name: "maxsize",
                                            minValue: 0,
                                            allowDecimals: false,
                                            decimalPrecision: 0,
                                            incrementValue: 1,
                                            accelerate: true,
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.maxsize : ""
                                        }),

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
                                        width: 95,
                                        style: 'padding-bottom: 5px'
                                    },
                                    items: [
                                        {
                                            xtype: 'box',
                                            html: __("Offset X") + __('...', true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Offset Y") + __('...', true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Polar offset radius") + __('...', true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Polar offset abgle") + __('...', true)
                                        },
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
                                            store: wmsLayer.numFieldsForStore,
                                            editable: true,
                                            triggerAction: "all",
                                            name: "style_offsetx",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.style_offsetx : ""
                                        },
                                        {
                                            xtype: "combo",
                                            store: wmsLayer.numFieldsForStore,
                                            editable: true,
                                            triggerAction: "all",
                                            name: "style_offsety",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.style_offsety : ""
                                        },
                                        {
                                            xtype: "combo",
                                            store: wmsLayer.numFieldsForStore,
                                            editable: true,
                                            triggerAction: "all",
                                            name: "style_polaroffsetr",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.style_polaroffsetr : ""
                                        },
                                        {
                                            xtype: "combo",
                                            store: wmsLayer.numFieldsForStore,
                                            editable: true,
                                            triggerAction: "all",
                                            name: "style_polaroffsetd",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.style_polaroffsetd : ""
                                        },
                                    ]
                                },
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
                                        width: 95,
                                        style: 'padding-bottom: 5px'
                                    },
                                    items: [
                                        {
                                            xtype: 'box',
                                            html: __("Text") + __("Text to label features with. Combo field: You write around like &apos;My label [attribute]&apos; or concatenate two or more attributes like &apos;[attribute1] [attribute2]&apos;.", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Force") + __("...", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Min scale denominator") + __("Minimum scale at which this LABEL is drawn. Scale is given as the denominator of the actual scale fraction, for example for a map at a scale of 1:24,000 use 24000.", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Max scale denominator") + __("Maximum scale at which this LABEL is drawn. Scale is given as the denominator of the actual scale fraction, for example for a map at a scale of 1:24,000 use 24000.", true)
                                        },
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
                                            xtype: "combo",
                                            store: wmsLayer.fieldsForStoreBrackets,
                                            editable: true,
                                            triggerAction: "all",
                                            name: "labelText",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.labelText : ""
                                        },
                                        {
                                            xtype: "combo",
                                            editable: false,
                                            displayField: 'name',
                                            valueField: 'value',
                                            mode: 'local',
                                            store: new Ext.data.JsonStore({
                                                fields: ['name', 'value'],
                                                data: [
                                                    {
                                                        name: 'True',
                                                        value: true
                                                    }, {
                                                        name: 'False',
                                                        value: false
                                                    }
                                                ]
                                            }),
                                            triggerAction: "all",
                                            name: "force",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.force : ""
                                        },
                                        new Ext.ux.form.SpinnerField({
                                            name: "label_minscaledenom",
                                            minValue: 1,
                                            allowDecimals: false,
                                            decimalPrecision: 0,
                                            incrementValue: 1,
                                            accelerate: true,
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.label_minscaledenom : ""
                                        }),
                                        new Ext.ux.form.SpinnerField({
                                            name: "label_maxscaledenom",
                                            minValue: 1,
                                            allowDecimals: false,
                                            decimalPrecision: 0,
                                            incrementValue: 1,
                                            accelerate: true,
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.label_maxscaledenom : ""
                                        }),

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
                                        width: 95,
                                        style: 'padding-bottom: 5px'
                                    },
                                    items: [
                                        {
                                            xtype: 'box',
                                            html: __("Position") + __("Position of the label relative to the labeling point.", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Size") + __("Size of the text in pixels. Combo field: Either select an integer attribute or write an integer.", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Font") + __("Font to use for labeling.", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Font weight") + __("Font weight.", true)
                                        },
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
                                            editable: false,
                                            displayField: 'name',
                                            valueField: 'value',
                                            mode: 'local',
                                            store: new Ext.data.JsonStore({
                                                fields: ['name', 'value'],
                                                data: [
                                                    {
                                                        name: 'Auto',
                                                        value: 'auto'
                                                    }, {
                                                        name: '↖',
                                                        value: 'ul'
                                                    }, {
                                                        name: '↑',
                                                        value: 'uc'
                                                    }, {
                                                        name: '↗',
                                                        value: 'ur'
                                                    }, {
                                                        name: '←',
                                                        value: 'cl'
                                                    }, {
                                                        name: '.',
                                                        value: 'cc'
                                                    }, {
                                                        name: '→',
                                                        value: 'cr'
                                                    }, {
                                                        name: '↙',
                                                        value: 'll'
                                                    }, {
                                                        name: '↓',
                                                        value: 'lc'
                                                    }, {
                                                        name: '↘',
                                                        value: 'lr'
                                                    }
                                                ]
                                            }),
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
                                            name: "labelSize",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.labelSize : ""
                                        },
                                        {
                                            xtype: "combo",
                                            editable: false,
                                            displayField: 'name',
                                            valueField: 'value',
                                            mode: 'local',
                                            triggerAction: "all",
                                            name: "labelFont",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.labelFont : "",
                                            store: new Ext.data.JsonStore({
                                                fields: ['name', 'value'],
                                                data: [
                                                    {
                                                        name: 'Arial',
                                                        value: 'arial'
                                                    }, {
                                                        name: 'Courier new',
                                                        value: 'courier'
                                                    }
                                                ]
                                            })
                                        },
                                        {
                                            xtype: "combo",
                                            editable: false,
                                            displayField: 'name',
                                            valueField: 'value',
                                            mode: 'local',
                                            triggerAction: "all",
                                            name: "labelFontWeight",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.labelFontWeight : "",
                                            store: new Ext.data.JsonStore({
                                                fields: ['name', 'value'],
                                                data: [
                                                    {
                                                        name: 'Normal',
                                                        value: 'normal'
                                                    }, {
                                                        name: 'Bold',
                                                        value: 'bold'
                                                    }, {
                                                        name: 'Italic',
                                                        value: 'italic'
                                                    },
                                                    {
                                                        name: 'Bold italic',
                                                        value: 'bolditalic'
                                                    }
                                                ]
                                            })
                                        },
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
                                        width: 95,
                                        style: 'padding-bottom: 5px'
                                    },
                                    items: [
                                        {
                                            xtype: 'box',
                                            html: __("Color") + __("Color to draw text with.", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Outline color") + __("...", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Buffer") + __("...", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Repeat distance") + __("...", true)
                                        },
                                    ]
                                },
                                {
                                    xtype: 'container',
                                    layout: 'hbox',
                                    defaults: {
                                        width: 95
                                    },
                                    items: [
                                        new Ext.form.ColorField({
                                            name: "labelColor",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.labelColor : ""
                                        }),
                                        new Ext.form.ColorField({
                                            name: "label_outlinecolor",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.label_outlinecolor : ""
                                        }),
                                        new Ext.ux.form.SpinnerField({
                                            name: "label_buffer",
                                            minValue: 0,
                                            allowDecimals: false,
                                            decimalPrecision: 0,
                                            incrementValue: 1,
                                            accelerate: true,
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.label_buffer : ""
                                        }),
                                        new Ext.ux.form.SpinnerField({
                                            name: "label_repeatdistance",
                                            minValue: 0,
                                            allowDecimals: false,
                                            decimalPrecision: 0,
                                            incrementValue: 1,
                                            accelerate: true,
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.label_repeatdistance : ""
                                        }),
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
                                        width: 95,
                                        style: 'padding-bottom: 5px'
                                    },
                                    items: [
                                        {
                                            xtype: 'box',
                                            html: __("Angle") + __("Angle, counterclockwise, given in degrees, to draw the label. Combo field: Either select an integer attribute or write an integer.", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Background") + __("Color to draw a background rectangle (i.e. billboard)", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Padding") + __("...", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Offset x") + __("...", true)
                                        },
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
                                        }),
                                        new Ext.ux.form.SpinnerField({
                                            name: "label_backgroundpadding",
                                            minValue: 0,
                                            allowDecimals: false,
                                            decimalPrecision: 0,
                                            incrementValue: 1,
                                            accelerate: true,
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.label_backgroundpadding : ""
                                        }),
                                        new Ext.ux.form.SpinnerField({
                                            name: "label_offsetx",
                                            minValue: -100,
                                            maxValue: 100,
                                            allowDecimals: false,
                                            decimalPrecision: 0,
                                            incrementValue: 1,
                                            accelerate: true,
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.label_offsetx : ""
                                        }),
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
                                        width: 95,
                                        style: 'padding-bottom: 5px'
                                    },
                                    items: [
                                        {
                                            xtype: 'box',
                                            html: __("Offset y") + __("...", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Expression") + __("...", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Max size") + __("Maximum font size to use when scaling text (pixels). Default is 256.", true)
                                        },
                                        {
                                            xtype: 'box',
                                            html: __("Min feature size") + __("Minimum size a feature must be to be labeled. Given in pixels. For line data the overall length of the displayed line is used, for polygons features the smallest dimension of the bounding box is used. “Auto” keyword tells MapServer to only label features that are larger than their corresponding label.", true)
                                        },
                                    ]
                                },
                                {
                                    xtype: 'container',
                                    layout: 'hbox',
                                    defaults: {
                                        width: 95,
                                    },
                                    items: [
                                        new Ext.ux.form.SpinnerField({
                                            name: "label_offsety",
                                            minValue: -100,
                                            maxValue: 100,
                                            allowDecimals: false,
                                            decimalPrecision: 0,
                                            incrementValue: 1,
                                            accelerate: true,
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.label_offsety : "",
                                        }),
                                        new Ext.form.TextField({
                                            name: "label_expression",
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.label_expression : "",
                                            style: {
                                                borderRight: "15px"
                                            }
                                        }),
                                        new Ext.ux.form.SpinnerField({
                                            name: "label_maxsize",
                                            minValue: 1,
                                            allowDecimals: false,
                                            decimalPrecision: 0,
                                            incrementValue: 1,
                                            accelerate: true,
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.label_maxsize : "",
                                        }),
                                        new Ext.ux.form.SpinnerField({
                                            name: "label_minfeaturesize",
                                            minValue: 1,
                                            allowDecimals: false,
                                            decimalPrecision: 0,
                                            incrementValue: 1,
                                            accelerate: true,
                                            allowBlank: true,
                                            value: (customIsSet && c) ? classWizards.setting.label_minfeaturesize : "",
                                        }),
                                    ]
                                },
                            ]
                        },
                    ]
                }
            ]
        });
    };
    classWizards.quantile = new Ext.Panel({
        labelWidth: 1,
        frame: false,
        border: false,
        region: 'center',
        split: true,
        items: [
            new Ext.Panel({
                region: 'center',
                border: true,
                defaults: {
                    border: false
                },
                height: 810,
                items: [
                    new Ext.Panel({
                        region: "center",
                        items: [
                            new Ext.TabPanel({
                                resizeTabs: false,
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
                                }()),
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
                                                html: '<table class="map-thumbs-table">' +
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
                                                                                value: customIsSet ? classWizards.setting.custom.color : null
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
                                                                                classWizards.clearAfterUpdate();
                                                                                wmsClasses.store.load();
                                                                                writeFiles(record._key_, map);
                                                                                store.load({
                                                                                    callback: function () {
                                                                                        updateClassGrid();
                                                                                    }
                                                                                });
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
                                                html: '<table class="map-thumbs-table">' +
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
                                                                        items: [
                                                                            {
                                                                                xtype: 'container',
                                                                                layout: 'hbox',
                                                                                items: [
                                                                                    {
                                                                                        xtype: "combo",
                                                                                        store: wmsLayer.fieldsForStore,
                                                                                        editable: false,
                                                                                        triggerAction: "all",
                                                                                        name: "value",
                                                                                        width: 100,
                                                                                        allowBlank: false,
                                                                                        disabled: (record.type === "RASTER") ? true : false,
                                                                                        emptyText: __("Field"),
                                                                                        value: (customIsSet && classWizards.setting.custom.pre === "unique") ? classWizards.setting.custom.value : null
                                                                                    },
                                                                                    {
                                                                                        boxLabel: __("Random colors"),
                                                                                        xtype: "radio",
                                                                                        name: "colorramp",
                                                                                        style: {
                                                                                            marginLeft: "4px"
                                                                                        },
                                                                                        inputValue: "-1",
                                                                                        checked: (!customIsSet || (customIsSet && (typeof classWizards.setting.custom.colorramp === "undefined" || classWizards.setting.custom.colorramp === "-1"))) ? true : null
                                                                                    }
                                                                                ]
                                                                            },
                                                                            {
                                                                                xtype: 'box',
                                                                                height: 7
                                                                            },
                                                                            {

                                                                                xtype: 'radiogroup',
                                                                                layout: 'hbox',
                                                                                defaults: {
                                                                                    xtype: "radio",
                                                                                    name: "colorramp",
                                                                                    width: 4,
                                                                                    style: {
                                                                                        margin: "4px"
                                                                                    }
                                                                                },
                                                                                items: [
                                                                                    {
                                                                                        boxLabel: '<span class="color-ramp" style="background-color:#a6cee3;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#1f78b4;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#b2df8a;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#33a02c;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#fb9a99;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#e31a1c;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#fdbf6f;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#ff7f00;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#cab2d6;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#6a3d9a;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#ffff99;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#b15928;"></span>',
                                                                                        inputValue: "0",
                                                                                        checked: (customIsSet && classWizards.setting.custom.colorramp === "0") ? true : null
                                                                                    },
                                                                                    {
                                                                                        boxLabel: '<span class="color-ramp" style="background-color:#e41a1c;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#377eb8;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#4daf4a;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#984ea3;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#ff7f00;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#ffff33;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#a65628;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#f781bf;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#999999;"></span>',
                                                                                        inputValue: "3",
                                                                                        checked: (customIsSet && classWizards.setting.custom.colorramp === "3") ? true : null

                                                                                    },
                                                                                    {
                                                                                        boxLabel: '<span class="color-ramp" style="background-color:#7fc97f;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#beaed4;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#fdc086;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#ffff99;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#386cb0;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#f0027f;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#bf5b17;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#666666;"></span>',
                                                                                        inputValue: "4",
                                                                                        checked: (customIsSet && classWizards.setting.custom.colorramp === "4") ? true : null

                                                                                    },
                                                                                    {
                                                                                        boxLabel: '<span class="color-ramp" style="background-color:#1b9e77;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#d95f02;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#7570b3;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#e7298a;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#66a61e;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#e6ab02;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#a6761d;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#666666;"></span>',
                                                                                        inputValue: "5",
                                                                                        checked: (customIsSet && classWizards.setting.custom.colorramp === "5") ? true : null

                                                                                    }
                                                                                ]
                                                                            },
                                                                            {

                                                                                xtype: 'radiogroup',
                                                                                layout: 'hbox',
                                                                                defaults: {
                                                                                    xtype: "radio",
                                                                                    name: "colorramp",
                                                                                    width: 4,
                                                                                    style: {
                                                                                        margin: "4px"
                                                                                    }
                                                                                },
                                                                                items: [
                                                                                    {
                                                                                        boxLabel: '<span class="color-ramp" style="background-color:#8dd3c7;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#ffffb3;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#bebada;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#fb8072;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#80b1d3;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#fdb462;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#b3de69;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#fccde5;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#d9d9d9;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#bc80bd;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#ccebc5;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#ffed6f;"></span>',
                                                                                        inputValue: "1",
                                                                                        checked: (customIsSet && classWizards.setting.custom.colorramp === "1") ? true : null

                                                                                    },
                                                                                    {
                                                                                        boxLabel: '<span class="color-ramp" style="background-color:#fbb4ae;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#b3cde3;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#ccebc5;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#decbe4;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#fed9a6;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#ffffcc;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#e5d8bd;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#fddaec;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#f2f2f2;"></span>',
                                                                                        inputValue: "2",
                                                                                        checked: (customIsSet && classWizards.setting.custom.colorramp === "2") ? true : null

                                                                                    },
                                                                                    {
                                                                                        boxLabel: '<span class="color-ramp" style="background-color:#66c2a5;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#fc8d62;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#8da0cb;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#e78ac3;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#a6d854;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#ffd92f;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#e5c494;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#b3b3b3;"></span>',
                                                                                        inputValue: "8",
                                                                                        checked: (customIsSet && classWizards.setting.custom.colorramp === "8") ? true : null

                                                                                    },
                                                                                    {
                                                                                        boxLabel: '<span class="color-ramp" style="background-color:#b3e2cd;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#fdcdac;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#cbd5e8;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#f4cae4;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#e6f5c9;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#fff2ae;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#f1e2cc;"></span>' +
                                                                                            '<span class="color-ramp" style="background-color:#cccccc;"></span>',
                                                                                        inputValue: "7",
                                                                                        checked: (customIsSet && classWizards.setting.custom.colorramp === "7") ? true : null
                                                                                    }
                                                                                ]
                                                                            },
                                                                            {
                                                                                xtype: 'box',
                                                                                height: 7
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
                                                                                classWizards.clearAfterUpdate();
                                                                                wmsClasses.store.load();
                                                                                writeFiles(record._key_, map);
                                                                                store.load({
                                                                                    callback: function () {
                                                                                        updateClassGrid();
                                                                                    }
                                                                                });
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
                                            html: '<table class="map-thumbs-table">' +
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
                                                                                value: customIsSet ? classWizards.setting.custom.type : (record.type === "RASTER") ? 'Equal' : null

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
                                                                                maxValue: 100,
                                                                                allowDecimals: false,
                                                                                decimalPrecision: 0,
                                                                                incrementValue: 1,
                                                                                accelerate: true,
                                                                                allowBlank: false,
                                                                                value: customIsSet ? classWizards.setting.custom.num : null
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
                                                                                classWizards.clearAfterUpdate();
                                                                                wmsClasses.store.load();
                                                                                writeFiles(record._key_, map);
                                                                                store.load({
                                                                                    callback: function () {
                                                                                        updateClassGrid();
                                                                                    }
                                                                                });
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
                                            html: '<table class="map-thumbs-table">' +
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
                                                                                    force: false,
                                                                                    custom: {
                                                                                        pre: "cluster",
                                                                                        force: Ext.getCmp("forceRecreation").getValue(),
                                                                                        clusterdistance: values.clusterdistance
                                                                                    }
                                                                                }
                                                                            }),
                                                                            headers: {
                                                                                'Content-Type': 'application/json; charset=utf-8'
                                                                            },
                                                                            success: function (response) {
                                                                                classWizards.clearAfterUpdate();
                                                                                wmsClasses.store.load();
                                                                                writeFiles(record._key_, map);
                                                                                store.load({
                                                                                    callback: function () {
                                                                                        updateClassGrid();
                                                                                    }
                                                                                });
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
                            }),
                            {
                                xtype: 'panel',
                                bodyStyle: "padding : 0 0 0 7px",
                                items: [
                                    {
                                        xtype: 'container',
                                        layout: 'hbox',
                                        items: [
                                            {
                                                xtype: "checkbox",
                                                triggerAction: "all",
                                                name: "force",
                                                id: "forceRecreation",
                                                allowBlank: true,

                                            },
                                            {
                                                xtype: 'box',
                                                html: __("Force") + __("Force re-creation of all classes. Any external changes will be overwritten", true)
                                            },
                                        ]
                                    }
                                ]
                            }
                        ]
                    }),
                    // legendPanel
                ]
            })
        ]
    });
};

classWizards.placeHolder = function () {
    return new Ext.Panel({
        labelWidth: 1,
        frame: false,
        border: false,
        autoHeight: true,
        region: 'center',
        split: true,
        items: [
            {
                xtype: "panel",
                autoScroll: true,
                region: 'center',
                frame: true,
                plain: true,
                border: false,
                html: __("Choose a layer to create a classification for.")
            }
        ]
    })
}

classWizards.clearAfterUpdate = function () {
    Ext.getCmp("a3").removeAll();
    Ext.getCmp("a8").removeAll();
    Ext.getCmp("a9").removeAll();
    Ext.getCmp("a10").removeAll();
    Ext.getCmp("a11").removeAll();
    wmsClasses.grid.getSelectionModel().clearSelections();
    Ext.getCmp("classTabs").disable();
}