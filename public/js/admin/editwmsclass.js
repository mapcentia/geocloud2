/*
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

Ext.namespace('wmsClasses');
wmsClasses.init = function (record) {
    wmsClasses.table = record._key_;
    wmsClasses.reader = new Ext.data.JsonReader({
        totalProperty: 'total',
        successProperty: 'success',
        idProperty: 'id',
        root: 'data',
        messageProperty: 'message'
    }, [
        {
            name: 'id'
        },
        {
            name: 'sortid'
        },
        {
            name: 'name'
        },
        {
            name: 'expression'
        }
    ]);
    wmsClasses.writer = new Ext.data.JsonWriter({
        writeAllFields: false,
        encode: false
    });
    wmsClasses.proxy = new Ext.data.HttpProxy({
        restful: true,
        api: {
            read: '/controllers/classification/index/' + wmsClasses.table,
            create: '/controllers/classification/index/' + wmsClasses.table,
            destroy: '/controllers/classification/index/' + wmsClasses.table
        },
        listeners: {
            write: wmsClasses.onWrite,
            exception: function (proxy, type, action, options, response, arg) {
                if (response.status !== 200) {
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
    wmsClasses.store = new Ext.data.Store({
        writer: wmsClasses.writer,
        reader: wmsClasses.reader,
        proxy: wmsClasses.proxy,
        autoSave: true,
        sortInfo: {field: "sortid", direction: "ASC"}
    });
    wmsClasses.store.load();
    wmsClasses.grid = new Ext.grid.GridPanel({
        iconCls: 'silk-grid',
        store: wmsClasses.store,
        border: false,
        style: {
            borderBottom: '1px solid #d0d0d0'
        },
        viewConfig: {
            forceFit: true
        },
        region: 'center',
        sm: new Ext.grid.RowSelectionModel({
            singleSelect: true
        }),
        cm: new Ext.grid.ColumnModel({
            defaults: {
                sortable: false,
                menuDisabled: true,
                editor: {
                    xtype: "textfield"
                }
            },
            columns: [
                {
                    id: "sortid",
                    header: "Sort id",
                    dataIndex: "sortid",
                    width: 50
                },
                {
                    id: "name",
                    header: "Name",
                    dataIndex: "name"
                },
                {
                    id: "expression",
                    header: "Expression",
                    dataIndex: "expression"
                }
            ]
        }),
        tbar: [
            {
                text: '<i class="fa fa-plus"></i> ' + __("Add"),
                handler: wmsClasses.onAdd
            },
            '-',
            {
                text: '<i class="fa fa-cut"></i> ' + __("Delete"),
                handler: wmsClasses.onDelete
            },
            '-',
            {
                text: '<i class="fa fa-copy"></i> ' + __("Copy from"),
                tooltip: __("Select a layer from which you want to copy the classes"),
                handler: function () {
                    var layer = Ext.getCmp("copylayerbox").value;
                    if (layer === "") {
                        App.setAlert(App.STATUS_NOTICE, __("Select a layer from which you want to copy the classes"));
                        return false;
                    }
                    Ext.Ajax.request({
                        url: '/controllers/classification/copy/' + wmsClasses.table + '/' + Ext.getCmp("copylayerbox").value,
                        method: 'put',
                        headers: {
                            'Content-Type': 'application/json; charset=utf-8'
                        },
                        success: function () {
                            wmsClasses.store.load();
                            Ext.getCmp("a3").remove(wmsClass.grid);
                            Ext.getCmp("a8").remove(wmsClass.grid2);
                            Ext.getCmp("a9").remove(wmsClass.grid3);
                            Ext.getCmp("a10").remove(wmsClass.grid4);
                            Ext.getCmp("a11").remove(wmsClass.grid5);
                            wmsClasses.grid.getSelectionModel().clearSelections();
                            writeFiles(wmsClasses.table);
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
            new Ext.form.ComboBox({
                id: "copylayerbox",
                store: store,
                displayField: 'f_table_name',
                valueField: '_key_',
                editable: false,
                mode: 'local',
                triggerAction: 'all',
                value: '',
                width: 140
            })
        ],
        listeners: {
            rowclick: function () {
                var record = wmsClasses.grid.getSelectionModel().getSelected(), a3, a8, a9, a10, a11;

                Ext.getCmp("classTabs").enable();


                if (!record) {
                    App.setAlert(App.STATUS_NOTICE, "You\'ve to select a layer");
                    return false;
                }
                var activeTab = Ext.getCmp("classTabs").getActiveTab();
                a3 = Ext.getCmp("a3");
                a8 = Ext.getCmp("a8");
                a9 = Ext.getCmp("a9");
                a10 = Ext.getCmp("a10");
                a11 = Ext.getCmp("a11");
                a3.remove(wmsClass.grid);
                a8.remove(wmsClass.grid2);
                a9.remove(wmsClass.grid3);
                a10.remove(wmsClass.grid4);
                a11.remove(wmsClass.grid5);
                wmsClass.grid = null;
                wmsClass.grid2 = null;
                wmsClass.grid3 = null;
                wmsClass.grid4 = null;
                wmsClass.grid5 = null;
                wmsClass.init(record.get("id"));
                a3.add(wmsClass.grid);
                a8.add(wmsClass.grid2);
                a9.add(wmsClass.grid3);
                a10.add(wmsClass.grid4);
                a11.add(wmsClass.grid5);
                Ext.getCmp("classTabs").activate(0);
                a3.doLayout();
                Ext.getCmp("classTabs").activate(1);
                a8.doLayout();
                Ext.getCmp("classTabs").activate(2);
                a9.doLayout();
                Ext.getCmp("classTabs").activate(3);
                a10.doLayout();
                Ext.getCmp("classTabs").activate(4);
                a11.doLayout();
                Ext.getCmp("classTabs").activate(activeTab);

            }
        }
    });
};
wmsClasses.onAdd = function () {
    Ext.Ajax.request({
        url: '/controllers/classification/index/' + wmsClasses.table,
        method: 'post',
        headers: {
            'Content-Type': 'application/json; charset=utf-8'
        },
        success: function () {
            wmsClasses.store.load();
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
};
wmsClasses.onDelete = function () {
    var record = wmsClasses.grid.getSelectionModel().getSelected();
    if (!record) {
        return false;
    }
    Ext.MessageBox.confirm(__('Confirm'), __('Are you sure you want to delete the class?'), function (btn) {
        if (btn === "yes") {
            wmsClasses.grid.store.remove(record);
            Ext.getCmp("a3").remove(wmsClass.grid);
            Ext.getCmp("a8").remove(wmsClass.grid2);
            Ext.getCmp("a9").remove(wmsClass.grid3);
            Ext.getCmp("a10").remove(wmsClass.grid4);
            Ext.getCmp("a11").remove(wmsClass.grid5);
        } else {
            return false;
        }
    });
};

wmsClasses.onSave = function () {
    wmsClasses.store.save();
};
wmsClasses.onWrite = function (store, action, result, transaction, rs) {
    if (transaction.success) {
        wmsClasses.store.load();
        writeFiles(wmsClasses.table);
    }
};

function test() {
    message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows=5' cols='31'>" + response.message + "</textarea>";
    Ext.MessageBox.show({
        title: 'Failure',
        msg: message,
        buttons: Ext.MessageBox.OK,
        width: 400,
        height: 300,
        icon: Ext.MessageBox.ERROR
    });
}

Ext.namespace('wmsClass');
wmsClass.init = function (id) {
    var checkboxRender = function (d) {
        var checked = d ? 'property-grid-check-on' : '';
        return '<div class="' + checked + '">';
    };
    var cc = function (value, meta) {
        meta.style = meta.style + "background-color:" + value;
        return value;
    };
    var labelPositionCombo = new Ext.form.ComboBox({
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
        editable: false,
        triggerAction: 'all'
    });
    wmsClass.classId = id;
    wmsClass.store = new Ext.data.JsonStore({
        autoLoad: true,
        url: '/controllers/classification/index/' + wmsClasses.table + '/' + id,
        storeId: 'configStore',
        successProperty: 'success',
        root: 'data',
        fields: [
            {
                name: 'sortid'
            },
            {
                name: 'name'
            },
            {
                name: 'expression'
            },
            {
                name: 'class_minscaledenom'
            },
            {
                name: 'class_maxscaledenom'
            },

            // Base style start
            {
                name: 'color'
            },
            {
                name: 'outlinecolor'
            },
            {
                name: 'symbol'
            },
            {
                name: 'size'
            },
            {
                name: 'width'
            },
            {
                name: 'angle'
            },
            {
                name: 'gap'
            },
            {
                name: 'style_opacity'
            },
            {
                name: "pattern"
            },
            {
                name: "linecap"
            },
            {
                name: "geomtransform"
            },
            {
                name: "minsize"
            },
            {
                name: "maxsize"
            },
            {
                name: "style_offsetx"
            },
            {
                name: "style_offsety"
            },
            {
                name: "style_polaroffsetr"
            },
            {
                name: "style_polaroffsetd"
            },
            // Label start
            {
                name: 'label',
                type: 'boolean'
            },
            {
                name: 'label_force',
                type: 'boolean'
            },
            {
                name: 'label_minscaledenom'
            },
            {
                name: 'label_maxscaledenom'
            },
            {
                name: 'label_position'
            },
            {
                name: 'label_size'
            },
            {
                name: 'label_color'
            },
            {
                name: 'label_outlinecolor'
            },
            {
                name: 'label_buffer'
            },
            {
                name: "label_text"
            },
            {
                name: "label_repeatdistance"
            },
            {
                name: "label_angle"
            },
            {
                name: "label_backgroundcolor"
            },
            {
                name: "label_backgroundpadding"
            },
            {
                name: "label_offsetx"
            },
            {
                name: "label_offsety"
            },
            {
                name: "label_font"
            },
            {
                name: "label_fontweight"
            },
            {
                name: "label_expression"
            },
            {
                name: "label_maxsize"
            },
            {
                name: "label_minfeaturesize"
            },

            // label22 start
            {
                name: 'label2',
                type: 'boolean'
            },
            {
                name: 'label2_force',
                type: 'boolean'
            },
            {
                name: 'label2_minscaledenom'
            },
            {
                name: 'label2_maxscaledenom'
            },
            {
                name: 'label2_position'
            },
            {
                name: 'label2_size'
            },
            {
                name: 'label2_color'
            },
            {
                name: 'label2_outlinecolor'
            },
            {
                name: 'label2_buffer'
            },
            {
                name: "label2_text"
            },
            {
                name: "label2_repeatdistance"
            },
            {
                name: "label2_angle"
            },
            {
                name: "label2_backgroundcolor"
            },
            {
                name: "label2_backgroundpadding"
            },
            {
                name: "label2_offsetx"
            },
            {
                name: "label2_offsety"
            },
            {
                name: "label2_font"
            },
            {
                name: "label2_fontweight"
            },
            {
                name: "label2_expression"
            },
            {
                name: "label2_maxsize"
            },
            {
                name: "label2_minfeaturesize"
            },

            // Leader start
            {
                name: 'leader',
                type: 'boolean'
            },
            {
                name: 'leader_gridstep'
            },
            {
                name: 'leader_maxdistance'
            },
            {
                name: 'leader_color'
            },
            // Overlay style start
            {
                name: 'overlaycolor'
            },
            {
                name: 'overlayoutlinecolor'
            },
            {
                name: 'overlaysymbol'
            },
            {
                name: 'overlaysize'
            },
            {
                name: 'overlaywidth'
            },
            {
                name: 'overlayangle'
            },
            {
                name: 'overlaygap'
            },
            {
                name: 'overlaystyle_opacity'
            },
            {
                name: "overlaypattern"
            },
            {
                name: "overlaylinecap"
            },
            {
                name: "overlaygeomtransform"
            },
            {
                name: "overlayminsize"
            },
            {
                name: "overlaymaxsize"
            },
            {
                name: "overlaystyle_offsetx"
            },
            {
                name: "overlaystyle_offsety"
            },
            ,
            {
                name: "overlaystyle_polaroffsetr"
            },
            {
                name: "overlaystyle_polaroffsetd"
            }
        ],
        listeners: {
            load: {
                fn: function (store, records, options) {
                    // get the property grid component
                    var propGrid = Ext.getCmp('propGrid');
                    var propGrid2 = Ext.getCmp('propGrid2');
                    var propGrid3 = Ext.getCmp('propGrid3');
                    var propGrid4 = Ext.getCmp('propGrid4');
                    var propGrid5 = Ext.getCmp('propGrid5');
                    // make sure the property grid exists
                    if (propGrid) {
                        delete propGrid.getStore().sortInfo;
                        propGrid.getColumnModel().getColumnById('name').sortable = false;
                        var obj1 = {}, arr1 = [
                            'sortid',
                            'name',
                            'expression',
                            'class_minscaledenom',
                            'class_maxscaledenom',
                            'leader',
                            'leader_gridstep',
                            'leader_maxdistance',
                            'leader_color'

                        ];
                        Ext.each(arr1, function (i, v) {
                            obj1[i] = store.getAt(0).data[i];
                        })
                        propGrid.setSource(obj1);
                    }
                    if (propGrid2) {
                        delete propGrid2.getStore().sortInfo;
                        propGrid2.getColumnModel().getColumnById('name').sortable = false;
                        var obj2 = {}, arr2 = [
                            'color',
                            'outlinecolor',
                            'pattern',
                            'linecap',
                            'symbol',
                            'size',
                            'width',
                            'angle',
                            'gap',
                            'style_opacity',
                            'geomtransform',
                            'minsize',
                            'maxsize',
                            'style_offsetx',
                            'style_offsety',
                            'style_polaroffsetr',
                            'style_polaroffsetd'
                        ];
                        Ext.each(arr2, function (i, v) {
                            obj2[i] = store.getAt(0).data[i];
                        });
                        propGrid2.setSource(obj2);
                    }
                    if (propGrid3) {
                        delete propGrid3.getStore().sortInfo;
                        propGrid3.getColumnModel().getColumnById('name').sortable = false;
                        var obj3 = {}, arr3 = [
                            'overlaycolor',
                            'overlayoutlinecolor',
                            'overlaypattern',
                            'overlaylinecap',
                            'overlaysymbol',
                            'overlaysize',
                            'overlaywidth',
                            'overlayangle',
                            'overlaygap',
                            'overlaystyle_opacity',
                            'overlaygeomtransform',
                            'overlayminsize',
                            'overlaymaxsize',
                            'overlaystyle_offsetx',
                            'overlaystyle_offsety',
                            'overlaystyle_polaroffsetr',
                            'overlaystyle_polaroffsetd'
                        ];
                        Ext.each(arr3, function (i, v) {
                            obj3[i] = store.getAt(0).data[i];
                        });
                        propGrid3.setSource(obj3);
                    }
                    if (propGrid4) {
                        delete propGrid4.getStore().sortInfo;
                        propGrid4.getColumnModel().getColumnById('name').sortable = false;
                        var obj4 = {}, arr4 = [
                            'label',
                            'label_text',
                            'label_force',
                            'label_minscaledenom',
                            'label_maxscaledenom',
                            'label_position',
                            'label_size',
                            'label_font',
                            'label_fontweight',
                            'label_color',
                            'label_outlinecolor',
                            'label_buffer',
                            'label_repeatdistance',
                            'label_angle',
                            'label_backgroundcolor',
                            'label_backgroundpadding',
                            'label_offsetx',
                            'label_offsety',
                            'label_expression',
                            'label_maxsize',
                            'label_minfeaturesize'
                        ];
                        Ext.each(arr4, function (i, v) {
                            obj4[i] = store.getAt(0).data[i];
                        });
                        propGrid4.setSource(obj4);
                    }
                    if (propGrid5) {
                        delete propGrid5.getStore().sortInfo;
                        propGrid5.getColumnModel().getColumnById('name').sortable = false;
                        var obj5 = {}, arr5 = [
                            'label2',
                            'label2_text',
                            'label2_force',
                            'label2_minscaledenom',
                            'label2_maxscaledenom',
                            'label2_position',
                            'label2_size',
                            'label2_font',
                            'label2_fontweight',
                            'label2_color',
                            'label2_outlinecolor',
                            'label2_buffer',
                            'label2_repeatdistance',
                            'label2_angle',
                            'label2_backgroundcolor',
                            'label2_backgroundpadding',
                            'label2_offsetx',
                            'label2_offsety',
                            'label2_expression',
                            'label2_maxsize',
                            'label2_minfeaturesize'
                        ];
                        Ext.each(arr5, function (i, v) {
                            obj5[i] = store.getAt(0).data[i];
                        });
                        propGrid5.setSource(obj5);
                    }
                }
            }
        }
    });
    wmsClass.grid = new Ext.grid.PropertyGrid({
        id: 'propGrid',
        modal: false,
        region: 'center',
        border: false,
        propertyNames: {
            sortid: 'Sort id',
            name: 'Name',
            expression: 'Expression',
            class_minscaledenom: __('Min scale denominator') + __("Maximum scale at which this CLASS is drawn. Scale is given as the denominator of the actual scale fraction, for example for a map at a scale of 1:24,000 use 24000.", true),
            class_maxscaledenom: __('Max scale denominator') + __("Minimum scale at which this CLASS is drawn. Scale is given as the denominator of the actual scale fraction, for example for a map at a scale of 1:24,000 use 24000.", true),
            leader: 'Leader: on',
            leader_gridstep: 'Leader: gridstep',
            leader_maxdistance: 'Leader: maxdistance',
            leader_color: 'Leader: color'
        },
        customRenderers: {
            leader_color: cc
        },
        customEditors: {
            'sortid': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: -100,
                maxValue: 9999,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'class_minscaledenom': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'class_maxscaledenom': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'leader_gridstep': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'leader_maxdistance': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'leader_color': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {})
        },
        viewConfig: {
            forceFit: true
        }
    });
    wmsClass.grid2 = new Ext.grid.PropertyGrid({
        id: 'propGrid2',
        modal: false,
        region: 'center',
        border: false,
        propertyNames: {
            outlinecolor: 'Outline color',
            symbol: 'Symbol',
            color: 'Color',
            size: 'Size',
            width: 'Line width',
            angle: 'Angle',
            gap: 'Gap' + __("specifies the distance between SYMBOLs (center to center) for decorated lines and polygon fills in layer SIZEUNITS. For polygon fills, GAP specifies the distance between SYMBOLs in both the X and the Y direction. For lines, the centers of the SYMBOLs are placed on the line. For lines, a negative GAP value will cause the symbols’ X axis to be aligned relative to the tangent of the line. For lines, a positive GAP value aligns the symbols’ X axis relative to the X axis of the output device.", true),
            style_opacity: 'Opacity',
            linecap: 'line cap' + __('Sets the line cap type for lines. Default is round.', true),
            pattern: 'Pattern' + __('Used to define a dash pattern for line work (lines, polygon outlines, hatch lines, …). The numbers (doubles) specify the lengths of the dashes and gaps of the dash pattern in layer SIZEUNITS. When scaling of symbols is in effect (SYMBOLSCALEDENOM is specified for the LAYER), the numbers specify the lengths of the dashes and gaps in layer SIZEUNITS at the map scale 1:SYMBOLSCALEDENOM.', true),
            geomtransform: 'Geomtransform',
            minsize: 'Min size' + __("Minimum size in pixels to draw a symbol. Default is 0. The value can also be a decimal value (and not only integer)", true),
            maxsize: 'Max size' + __("Maximum size in pixels to draw a symbol. Default is 500. The value can also be a decimal value (and not only integer)", true),
            style_offsetx: 'Offset X' + __("Geometry offset values in layer SIZEUNITS. In the general case, SIZEUNITS will be pixels. The parameter corresponds to a shift on the horizontal - x", true),
            style_offsety: 'Offset Y' + __("Geometry offset values in layer SIZEUNITS. In the general case, SIZEUNITS will be pixels. The parameter corresponds to a shift on the horizontal - Y", true),
            style_polaroffsetr: 'Polar offset radius' + __("Offset given in polar coordinates - radius/distance.", true),
            style_polaroffsetd: 'Polar offset angle' + __("Offset given in polar coordinates - angle (counter clockwise).", true)
        },
        customRenderers: {
            color: cc,
            outlinecolor: cc
        },
        customEditors: {
            'sortid': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: -100,
                maxValue: 9999,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            }), {}),
            'color': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'outlinecolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'symbol': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: ['', 'circle', 'square', 'triangle', 'hatch1', 'dashed1', 'dot-dot', 'dashed-line-short', 'dashed-line-long', 'dash-dot', 'dash-dot-dot', 'arrow', 'arrow2'],
                editable: true,
                triggerAction: 'all'
            }), {}),
            'geomtransform': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: ['', 'bbox', 'centroid', 'end', 'labelpnt', 'labelpoly', 'start', 'vertices'],
                editable: true,
                triggerAction: 'all'
            }), {}),
            'linecap': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: ['round', 'butt', 'square'],
                editable: false,
                triggerAction: 'all'
            }), {}),
            'size': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'width': new Ext.grid.GridEditor(new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            }))),
            'gap': new Ext.grid.GridEditor(new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            }))),
            'style_opacity': new Ext.grid.GridEditor(new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                maxValue: 100,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            }))),
            'angle': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'minsize': new Ext.grid.GridEditor(new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            }))),
            'maxsize': new Ext.grid.GridEditor(new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            }))),
            'style_offsetx': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'style_offsety': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'style_polaroffsetr': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'style_polaroffsetd': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {})
        },
        viewConfig: {
            forceFit: true
        }
    });
    wmsClass.grid3 = new Ext.grid.PropertyGrid({
        id: 'propGrid3',
        modal: false,
        region: 'center',
        border: false,
        propertyNames: {
            overlaywidth: 'Line width',
            overlayoutlinecolor: 'Outline color',
            overlaysymbol: 'Symbol',
            overlaycolor: 'Color',
            overlaysize: 'Size',
            overlayangle: 'Angle',
            overlaygap: 'Gap' + __("specifies the distance between SYMBOLs (center to center) for decorated lines and polygon fills in layer SIZEUNITS. For polygon fills, GAP specifies the distance between SYMBOLs in both the X and the Y direction. For lines, the centers of the SYMBOLs are placed on the line. For lines, a negative GAP value will cause the symbols’ X axis to be aligned relative to the tangent of the line. For lines, a positive GAP value aligns the symbols’ X axis relative to the X axis of the output device.", true),
            overlaystyle_opacity: 'Opacity',
            overlaylinecap: 'line cap' + __('Sets the line cap type for lines. Default is round.', true),
            overlaypattern: 'Pattern' + __('Used to define a dash pattern for line work (lines, polygon outlines, hatch lines, …). The numbers (doubles) specify the lengths of the dashes and gaps of the dash pattern in layer SIZEUNITS. When scaling of symbols is in effect (SYMBOLSCALEDENOM is specified for the LAYER), the numbers specify the lengths of the dashes and gaps in layer SIZEUNITS at the map scale 1:SYMBOLSCALEDENOM.', true),
            overlaygeomtransform: 'Geomtransform',
            overlayminsize: 'Min size' + __("Minimum size in pixels to draw a symbol. Default is 0. The value can also be a decimal value (and not only integer)", true),
            overlaymaxsize: 'Max size' + __("Maximum size in pixels to draw a symbol. Default is 500. The value can also be a decimal value (and not only integer)", true),
            overlaystyle_offsetx: 'Offset X' + __("Geometry offset values in layer SIZEUNITS. In the general case, SIZEUNITS will be pixels. The parameter corresponds to a shift on the horizontal - x", true),
            overlaystyle_offsety: 'Offset Y' + __("Geometry offset values in layer SIZEUNITS. In the general case, SIZEUNITS will be pixels. The parameter corresponds to a shift on the horizontal - Y", true),
            overlaystyle_polaroffsetr: 'Polar offset radius' + __("Offset given in polar coordinates - radius/distance.", true),
            overlaystyle_polaroffsetd: 'Polar offset angle' + __("Offset given in polar coordinates - angle (counter clockwise).", true)
        },
        customRenderers: {
            overlaycolor: cc,
            overlayoutlinecolor: cc,
            label_position: Ext.util.Format.comboRenderer(labelPositionCombo)
        },
        customEditors: {
            'overlaylinecap': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: ['round', 'butt', 'square'],
                editable: false,
                triggerAction: 'all'
            }), {}),
            'overlaycolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'overlayoutlinecolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'overlaysymbol': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: ['', 'circle', 'square', 'triangle', 'hatch1', 'dashed1', 'dot-dot', 'dashed-line-short', 'dashed-line-long', 'dash-dot', 'dash-dot-dot', 'arrow', 'arrow2'],
                editable: true,
                triggerAction: 'all'
            }), {}),
            'overlaygeomtransform': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: ['', 'bbox', 'centroid', 'end', 'labelpnt', 'labelpoly', 'start', 'vertices'],
                editable: true,
                triggerAction: 'all'
            }), {}),
            'overlaysize': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'overlaywidth': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'overlaygap': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'overlayangle': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'overlaystyle_opacity': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                maxValue: 100,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'overlayminsize': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'overlaymaxsize': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'overlaystyle_offsetx': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'overlaystyle_offsety': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'overlaystyle_polaroffsetr': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'overlaystyle_polaroffsetd': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {})
        },
        viewConfig: {
            forceFit: true
        }
    });
    wmsClass.grid4 = new Ext.grid.PropertyGrid({
        id: 'propGrid4',
        modal: false,
        region: 'center',
        border: false,
        propertyNames: {
            label: 'On',
            label_force: 'Force',
            label_minscaledenom: __('Min scale denominator') + __("Minimum scale at which this LABEL is drawn. Scale is given as the denominator of the actual scale fraction, for example for a map at a scale of 1:24,000 use 24000.", true),
            label_maxscaledenom: __('Max scale denominator') + __("Maximum scale at which this LABEL is drawn. Scale is given as the denominator of the actual scale fraction, for example for a map at a scale of 1:24,000 use 24000.", true),
            label_position: 'Position',
            label_color: 'Color',
            label_outlinecolor: 'Outline color',
            label_buffer: 'Buffer',
            label_text: 'Text',
            label_size: 'Size',
            label_angle: 'Angle',
            label_repeatdistance: 'Repeat distance',
            label_backgroundcolor: 'Background',
            label_backgroundpadding: 'Padding',
            label_offsetx: 'Offset X',
            label_offsety: 'Offset Y',
            label_font: 'Font',
            label_fontweight: 'Font weight',
            label_expression: 'Expression',
            label_maxsize: 'Max size' + __("Maximum font size to use when scaling text (pixels). Default is 256.", true),
            label_minfeaturesize: 'Min feature size' + __("Minimum size a feature must be to be labeled. Given in pixels. For line data the overall length of the displayed line is used, for polygons features the smallest dimension of the bounding box is used. “Auto” keyword tells MapServer to only label features that are larger than their corresponding label.", true)
        },
        customRenderers: {
            label: checkboxRender,
            label_force: checkboxRender,
            label_color: cc,
            label_outlinecolor: cc,
            label_backgroundcolor: cc,
            label_position: Ext.util.Format.comboRenderer(labelPositionCombo)
        },
        customEditors: {
            'label': new Ext.grid.GridEditor(new Ext.form.Checkbox({}), {}),
            'label_force': new Ext.grid.GridEditor(new Ext.form.Checkbox({}), {}),
            'label_offsetx': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: -100,
                maxValue: 100,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            }), {}),
            'label_offsety': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: -100,
                maxValue: 100,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            }), {}),
            'label_size': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'label_angle': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'label_minscaledenom': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'label_maxscaledenom': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'label_buffer': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'label_position': new Ext.grid.GridEditor(labelPositionCombo, {
                renderer: Ext.util.Format.comboRenderer(labelPositionCombo)
            }),
            'label_font': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                displayField: 'name',
                valueField: 'value',
                mode: 'local',
                triggerAction: "all",
                editable: false,
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
            }), {}),
            'label_fontweight': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                displayField: 'name',
                valueField: 'value',
                mode: 'local',
                triggerAction: "all",
                editable: false,
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
            }), {}),
            'label_repeatdistance': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'label_color': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'label_backgroundcolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'label_backgroundpadding': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                maxValue: 15,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            }), {}),
            'label_outlinecolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'label_text': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.fieldsForStoreBrackets,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'label_maxsize': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'label_minfeaturesize': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
        },
        viewConfig: {
            forceFit: true
        }
    });
    wmsClass.grid5 = new Ext.grid.PropertyGrid({
        id: 'propGrid5',
        modal: false,
        region: 'center',
        border: false,
        propertyNames: {
            label2: 'On',
            label2_force: 'Force',
            label2_minscaledenom: __('Min scale denominator') + __("Minimum scale at which this LABEL is drawn. Scale is given as the denominator of the actual scale fraction, for example for a map at a scale of 1:24,000 use 24000.", true),
            label2_maxscaledenom: __('Max scale denominator') + __("Maximum scale at which this LABEL is drawn. Scale is given as the denominator of the actual scale fraction, for example for a map at a scale of 1:24,000 use 24000.", true),
            label2_position: 'Position',
            label2_color: 'Color',
            label2_outlinecolor: 'Outline color',
            label2_buffer: 'Buffer',
            label2_text: 'Text',
            label2_size: 'Size',
            label2_angle: 'Angle',
            label2_repeatdistance: 'Repeat distance',
            label2_backgroundcolor: 'Background',
            label2_backgroundpadding: 'Padding',
            label2_offsetx: 'Offset X',
            label2_offsety: 'Offset Y',
            label2_font: 'Font',
            label2_fontweight: 'Font weight',
            label2_expression: 'Expression',
            label2_maxsize: 'Max size' + __("Maximum font size to use when scaling text (pixels). Default is 256.", true),
            label2_minfeaturesize: 'Min feature size' + __("Minimum size a feature must be to be labeled. Given in pixels. For line data the overall length of the displayed line is used, for polygons features the smallest dimension of the bounding box is used. “Auto” keyword tells MapServer to only label features that are larger than their corresponding label.", true)
        },
        customRenderers: {
            label2: checkboxRender,
            label2_force: checkboxRender,
            label2_color: cc,
            label2_outlinecolor: cc,
            label2_backgroundcolor: cc,
            label2_position: Ext.util.Format.comboRenderer(labelPositionCombo)

        },
        customEditors: {
            'label2': new Ext.grid.GridEditor(new Ext.form.Checkbox({}), {}),
            'label2_force': new Ext.grid.GridEditor(new Ext.form.Checkbox({}), {}),
            'label2_offsetx': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: -100,
                maxValue: 100,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            }), {}),
            'label2_offsety': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: -100,
                maxValue: 100,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            }), {}),
            'label2_size': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'label2_angle': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'label2_minscaledenom': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'label2_maxscaledenom': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'label2_buffer': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'label2_position': new Ext.grid.GridEditor(labelPositionCombo, {
                renderer: Ext.util.Format.comboRenderer(labelPositionCombo)
            }),
            'label2_font': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                displayField: 'name',
                valueField: 'value',
                mode: 'local',
                triggerAction: "all",
                editable: false,
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

            }), {}),
            'label2_fontweight': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                displayField: 'name',
                valueField: 'value',
                mode: 'local',
                triggerAction: "all",
                editable: false,
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
            }), {}),
            'label2_repeatdistance': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'label2_color': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'label2_outlinecolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'label2_backgroundcolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'label2_backgroundpadding': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                maxValue: 15,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            }), {}),
            'label2_text': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.fieldsForStoreBrackets,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'label2_maxsize': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'label2_minfeaturesize': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
        },
        viewConfig: {
            forceFit: true
        }
    });
};

