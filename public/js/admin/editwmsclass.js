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
                            wmsClasses.grid.getSelectionModel().clearSelections();
                            wmsClass.classId = null;
                            Ext.getCmp("classTabs").disable();
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
                var record = wmsClasses.grid.getSelectionModel().getSelected();

                Ext.getCmp("classTabs").enable();


                if (!record) {
                    App.setAlert(App.STATUS_NOTICE, "You\'ve to select a layer");
                    return false;
                }
                var activeTab = Ext.getCmp("classTabs").getActiveTab();
                var a3 = Ext.getCmp("a3"), a8 = Ext.getCmp("a8"), a9 = Ext.getCmp("a9");
                a3.remove(wmsClass.grid);
                a8.remove(wmsClass.grid2);
                a9.remove(wmsClass.grid3);
                wmsClass.grid = null;
                wmsClass.grid2 = null;
                wmsClass.grid3 = null;
                wmsClass.init(record.get("id"));
                a3.add(wmsClass.grid);
                a8.add(wmsClass.grid2);
                a9.add(wmsClass.grid3);
                Ext.getCmp("classTabs").activate(0);
                a3.doLayout();
                Ext.getCmp("classTabs").activate(1);
                a8.doLayout();
                Ext.getCmp("classTabs").activate(2);
                a9.doLayout();
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
            wmsClass.classId = null;
            Ext.getCmp("classTabs").disable();
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

Ext.namespace('wmsClass');

wmsClass.STYLE_FIELDS = ['color', 'outlinecolor', 'pattern', 'linecap', 'symbol', 'size', 'width',
    'angle', 'gap', 'style_opacity', 'geomtransform', 'minsize', 'maxsize',
    'style_offsetx', 'style_offsety', 'style_polaroffsetr', 'style_polaroffsetd'];

wmsClass.LABEL_FIELDS = ['on', 'text', 'force', 'minscaledenom', 'maxscaledenom', 'position', 'size',
    'font', 'fontweight', 'color', 'outlinecolor', 'buffer', 'repeatdistance', 'angle',
    'backgroundcolor', 'backgroundpadding', 'offsetx', 'offsety', 'expression', 'maxsize',
    'minfeaturesize'];

wmsClass.save = function (onSuccess) {
    if (wmsClass.classId === null || !wmsClass.loaded || !Ext.getCmp("propGrid")) {
        return;
    }
    var data = Ext.getCmp("propGrid").getSource();
    data.styles = wmsClass.styles;
    data.labels = wmsClass.labels;
    Ext.Ajax.request({
        url: '/controllers/classification/index/' + wmsClasses.table + '/' + wmsClass.classId,
        method: 'put',
        params: Ext.util.JSON.encode({data: data}),
        headers: {
            'Content-Type': 'application/json; charset=utf-8'
        },
        success: function () {
            App.setAlert(App.STATUS_OK, __("Style is updated"));
            writeFiles(wmsClasses.table);
            wmsClasses.store.load();
            if (onSuccess) {
                onSuccess();
            }
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
    wmsClass.styles = [];
    wmsClass.labels = [];
    wmsClass.loaded = false;

    var buildStylePropertyNames = function () {
        return {
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
        };
    };

    var buildStyleEditors = function () {
        return {
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
            'width': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
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
        };
    };

    var buildLabelPropertyNames = function () {
        return {
            on: 'On',
            force: 'Force',
            minscaledenom: __('Min scale denominator') + __("Minimum scale at which this LABEL is drawn. Scale is given as the denominator of the actual scale fraction, for example for a map at a scale of 1:24,000 use 24000.", true),
            maxscaledenom: __('Max scale denominator') + __("Maximum scale at which this LABEL is drawn. Scale is given as the denominator of the actual scale fraction, for example for a map at a scale of 1:24,000 use 24000.", true),
            position: 'Position',
            color: 'Color',
            outlinecolor: 'Outline color',
            buffer: 'Buffer',
            text: 'Text',
            size: 'Size',
            angle: 'Angle',
            repeatdistance: 'Repeat distance',
            backgroundcolor: 'Background',
            backgroundpadding: 'Padding',
            offsetx: 'Offset X',
            offsety: 'Offset Y',
            font: 'Font',
            fontweight: 'Font weight',
            expression: 'Expression',
            maxsize: 'Max size' + __("Maximum font size to use when scaling text (pixels). Default is 256.", true),
            minfeaturesize: 'Min feature size' + __("Minimum size a feature must be to be labeled. Given in pixels. For line data the overall length of the displayed line is used, for polygons features the smallest dimension of the bounding box is used. “Auto” keyword tells MapServer to only label features that are larger than their corresponding label.", true)
        };
    };

    var buildLabelEditors = function () {
        return {
            'on': new Ext.grid.GridEditor(new Ext.form.Checkbox({}), {}),
            'force': new Ext.grid.GridEditor(new Ext.form.Checkbox({}), {}),
            'offsetx': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: -100,
                maxValue: 100,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            }), {}),
            'offsety': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: -100,
                maxValue: 100,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            }), {}),
            'size': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'angle': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'minscaledenom': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'maxscaledenom': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'buffer': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'position': new Ext.grid.GridEditor(labelPositionCombo, {
                renderer: Ext.util.Format.comboRenderer(labelPositionCombo)
            }),
            'font': new Ext.grid.GridEditor(new Ext.form.ComboBox({
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
            'fontweight': new Ext.grid.GridEditor(new Ext.form.ComboBox({
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
            'repeatdistance': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'color': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'backgroundcolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'backgroundpadding': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                maxValue: 15,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            }), {}),
            'outlinecolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'text': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.fieldsForStoreBrackets,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'maxsize': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            })),
            'minfeaturesize': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: 0,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true
            }))
        };
    };

    // ---------- Base tab ----------
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
            leader: checkboxRender,
            leader_color: cc
        },
        customEditors: {
            'leader': new Ext.grid.GridEditor(new Ext.form.Checkbox({}), {}),
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

    // ---------- Shared master/detail builder ----------
    // kind: 'styles' | 'labels'
    var buildItemPanel = function (kind, propGridId, propertyNames, customEditors, customRenderers, fields) {
        propertyNames.sortid = 'Sort id';
        propertyNames.name = 'Name';
        customEditors['sortid'] = new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
            minValue: -100,
            maxValue: 9999,
            allowDecimals: false,
            decimalPrecision: 0,
            incrementValue: 1,
            accelerate: true
        }));
        var listStore = new Ext.data.JsonStore({
            fields: ['idx', 'sortid', 'name'],
            data: []
        });
        var currentIdx = null;
        var arr = function () {
            return wmsClass[kind];
        };
        var reload = function (selectIdx) {
            var rows = [];
            Ext.each(arr(), function (item, i) {
                rows.push({idx: i, sortid: item.sortid, name: item.name || ""});
            });
            listStore.loadData(rows);
            if (selectIdx !== undefined && selectIdx !== null) {
                var record = listStore.getAt(selectIdx);
                if (record) {
                    listGrid.getSelectionModel().selectRecords([record]);
                    showItem(selectIdx);
                }
            }
        };
        var propGrid = new Ext.grid.PropertyGrid({
            id: propGridId,
            region: 'center',
            border: false,
            propertyNames: propertyNames,
            customEditors: customEditors,
            customRenderers: customRenderers,
            viewConfig: {
                forceFit: true
            },
            source: {},
            listeners: {
                propertychange: function (source, recordId, value) {
                    if (currentIdx !== null && arr()[currentIdx]) {
                        arr()[currentIdx][recordId] = value;
                        if (recordId === 'sortid' || recordId === 'name') {
                            var rIdx = listStore.findExact('idx', currentIdx);
                            if (rIdx !== -1) {
                                listStore.getAt(rIdx).set(recordId, value);
                                listStore.getAt(rIdx).commit();
                            }
                        }
                    }
                }
            }
        });
        var showItem = function (idx) {
            currentIdx = idx;
            var item = arr()[idx] || {};
            var source = {};
            Ext.each(['sortid', 'name'].concat(fields), function (f) {
                if (f === 'on' || f === 'force') {
                    source[f] = !!item[f];
                } else {
                    source[f] = (item[f] !== undefined && item[f] !== null) ? item[f] : "";
                }
            });
            delete propGrid.getStore().sortInfo;
            propGrid.getColumnModel().getColumnById('name').sortable = false;
            propGrid.setSource(source);
        };
        var listGrid = new Ext.grid.GridPanel({
            region: 'north',
            height: 120,
            split: true,
            border: false,
            store: listStore,
            sm: new Ext.grid.RowSelectionModel({
                singleSelect: true
            }),
            viewConfig: {
                forceFit: true
            },
            cm: new Ext.grid.ColumnModel({
                defaults: {
                    sortable: false,
                    menuDisabled: true
                },
                columns: [
                    {
                        header: "Sort id",
                        dataIndex: "sortid",
                        width: 50
                    },
                    {
                        header: "Name",
                        dataIndex: "name"
                    }
                ]
            }),
            tbar: [
                {
                    text: '<i class="fa fa-plus"></i> ' + __("Add"),
                    handler: function () {
                        var maxSort = 0;
                        Ext.each(arr(), function (item) {
                            var v = parseInt(item.sortid, 10);
                            if (!isNaN(v) && v > maxSort) {
                                maxSort = v;
                            }
                        });
                        var entry = {sortid: maxSort + 10, name: ""};
                        if (kind === 'labels') {
                            entry.on = true;
                        }
                        arr().push(entry);
                        wmsClass.save(function () {
                            reload(arr().length - 1);
                        });
                    }
                },
                '-',
                {
                    text: '<i class="fa fa-cut"></i> ' + __("Delete"),
                    handler: function () {
                        var record = listGrid.getSelectionModel().getSelected();
                        if (!record) {
                            return false;
                        }
                        Ext.MessageBox.confirm(__('Confirm'), __('Are you sure you want to delete it?'), function (btn) {
                            if (btn === "yes") {
                                arr().splice(record.data.idx, 1);
                                currentIdx = null;
                                propGrid.setSource({});
                                wmsClass.save(function () {
                                    reload();
                                });
                            }
                        });
                    }
                }
            ],
            listeners: {
                rowclick: function (grid, rowIndex) {
                    showItem(listStore.getAt(rowIndex).data.idx);
                }
            }
        });
        var panel = new Ext.Panel({
            layout: 'border',
            border: false,
            items: [listGrid, propGrid]
        });
        panel.reloadList = reload;
        return panel;
    };

    // ---------- Symbols tab ----------
    wmsClass.grid2 = buildItemPanel(
        'styles',
        'symbolProps',
        buildStylePropertyNames(),
        buildStyleEditors(),
        {
            color: cc,
            outlinecolor: cc
        },
        wmsClass.STYLE_FIELDS
    );

    // ---------- Labels tab ----------
    wmsClass.grid3 = buildItemPanel(
        'labels',
        'labelProps',
        buildLabelPropertyNames(),
        buildLabelEditors(),
        {
            on: checkboxRender,
            force: checkboxRender,
            color: cc,
            outlinecolor: cc,
            backgroundcolor: cc,
            position: Ext.util.Format.comboRenderer(labelPositionCombo)
        },
        wmsClass.LABEL_FIELDS
    );

    // ---------- Load data ----------
    Ext.Ajax.request({
        url: '/controllers/classification/index/' + wmsClasses.table + '/' + id,
        method: 'get',
        success: function (response) {
            var data = Ext.decode(response.responseText).data[0];
            wmsClass.styles = data.styles || [];
            wmsClass.labels = data.labels || [];
            var baseGrid = Ext.getCmp('propGrid');
            if (baseGrid) {
                delete baseGrid.getStore().sortInfo;
                baseGrid.getColumnModel().getColumnById('name').sortable = false;
                var baseSource = {}, baseFields = [
                    'sortid', 'name', 'expression', 'class_minscaledenom', 'class_maxscaledenom',
                    'leader', 'leader_gridstep', 'leader_maxdistance', 'leader_color'
                ];
                Ext.each(baseFields, function (f) {
                    baseSource[f] = (data[f] !== undefined && data[f] !== null) ? data[f] : "";
                });
                baseSource.leader = !!data.leader;
                baseGrid.setSource(baseSource);
            }
            wmsClass.grid2.reloadList();
            wmsClass.grid3.reloadList();
            wmsClass.loaded = true;
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
