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
                if (type === 'remote') {// success is false
                    // alert(response.message);
                    message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows=5' cols='31'>" + response.message + "</textarea>";
                    Ext.MessageBox.show({
                        title: 'Failure',
                        msg: message,
                        buttons: Ext.MessageBox.OK,
                        width: 400,
                        height: 300,
                        icon: Ext.MessageBox.ERROR
                    });
                } else {
                    Ext.MessageBox.show({
                        title: 'Failure',
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
    var cc = function (value, meta) {
        meta.style = meta.style + "background-color:" + value;
        return value;
    };
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
                name: 'style_opacity'
            },
            {
                name: "pattern"
            },
            {
                name: "linecap"
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
                name: 'overlaystyle_opacity'
            },
            {
                name: "overlaypattern"
            },
            {
                name: "overlaylinecap"
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
                            'style_opacity'
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
                            'overlaystyle_opacity'
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
                            'label_expression'
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
                            'label2_expression'
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
        height: 350,
        modal: false,
        region: 'center',
        border: false,
        propertyNames: {
            sortid: 'Sort id',
            name: 'Name',
            expression: 'Expression',
            class_minscaledenom: 'Max scale',
            class_maxscaledenom: 'Min scale',
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
            }), {}),
            'class_minscaledenom': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'class_maxscaledenom': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'leader_gridstep': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'leader_maxdistance': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'leader_color': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {})
        },
        viewConfig: {
            forceFit: true
        }
    });
    wmsClass.grid2 = new Ext.grid.PropertyGrid({
        id: 'propGrid2',
        height: 350,
        modal: false,
        region: 'center',
        border: false,
        propertyNames: {
            outlinecolor: 'Style: outline color',
            symbol: 'Style: symbol',
            color: 'Style: color',
            size: 'Style: symbol size',
            width: 'Style: line width',
            angle: 'Style: symbol angle',
            style_opacity: 'Style: opacity',
            linecap: 'Style: line cap',
            pattern: 'Style: pattern'
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
                store: ['', 'circle', 'square', 'triangle', 'hatch1', 'dashed1', 'dot-dot', 'dashed-line-short', 'dashed-line-long', 'dash-dot', 'dash-dot-dot', 'arrow'],
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
            'width': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'style_opacity': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'class_minscaledenom': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'class_maxscaledenom': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'label_size': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'label_minscaledenom': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'label_maxscaledenom': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'label_buffer': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'label_position': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: ['auto', 'ul', 'uc', 'ur', 'cl', 'cc', 'cr', 'll', 'lc', 'lr'],
                editable: false,
                triggerAction: 'all'
            }), {}),
            'leader_gridstep': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'leader_maxdistance': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'label_repeatdistance': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
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
            'leader_color': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'label_outlinecolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'overlaycolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'overlayoutlinecolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'overlaysymbol': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: ['', 'circle', 'square', 'triangle', 'hatch1', 'dashed1', 'dot-dot', 'dashed-line-short', 'dashed-line-long', 'dash-dot', 'dash-dot-dot', 'arrow'],
                editable: true,
                triggerAction: 'all'
            }), {}),
            'overlaysize': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'overlaywidth': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'angle': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'overlayangle': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'label_text': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.fieldsForStoreBrackets,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'overlaystyle_opacity': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {})
        },
        viewConfig: {
            forceFit: true
        }
    });
    wmsClass.grid3 = new Ext.grid.PropertyGrid({
        id: 'propGrid3',
        height: 350,
        modal: false,
        region: 'center',
        border: false,
        propertyNames: {
            overlaywidth: 'Style: line width',
            overlayoutlinecolor: 'Style: outline color',
            overlaysymbol: 'Style: symbol',
            overlaycolor: 'Style: color',
            overlaysize: 'Style: symbol size',
            overlayangle: 'Style: symbol angle',
            overlaystyle_opacity: 'Style: opacity',
            overlaylinecap: 'Style: line cap',
            overlaypattern: 'Style: pattern'
        },
        customRenderers: {
            overlaycolor: cc,
            overlayoutlinecolor: cc
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
                store: ['', 'circle', 'square', 'triangle', 'hatch1', 'dashed1', 'dot-dot', 'dashed-line-short', 'dashed-line-long', 'dash-dot', 'dash-dot-dot', 'arrow'],
                editable: false,
                triggerAction: 'all'
            }), {}),
            'overlaylinecap': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: ['round', 'butt', 'square'],
                editable: false,
                triggerAction: 'all'
            }), {}),
            'size': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'width': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'style_opacity': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'class_minscaledenom': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'class_maxscaledenom': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'label_size': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'label_minscaledenom': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'label_maxscaledenom': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'label_buffer': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'label_position': new Ext.grid.GridEditor(new Ext.form.ComboBox({
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
            }), {}),
            'leader_gridstep': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'leader_maxdistance': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'label_repeatdistance': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
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
            'leader_color': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'label_outlinecolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'overlaycolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'overlayoutlinecolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'overlaysymbol': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: ['', 'circle', 'square', 'triangle', 'hatch1', 'dashed1', 'dot-dot', 'dashed-line-short', 'dashed-line-long', 'dash-dot', 'dash-dot-dot', 'arrow'],
                editable: true,
                triggerAction: 'all'
            }), {}),
            'overlaysize': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'overlaywidth': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'angle': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'overlayangle': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.numFieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'label_text': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.fieldsForStoreBrackets,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'overlaystyle_opacity': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {})
        },
        viewConfig: {
            forceFit: true
        }
    });
    wmsClass.grid4 = new Ext.grid.PropertyGrid({
        id: 'propGrid4',
        height: 350,
        modal: false,
        region: 'center',
        border: false,
        propertyNames: {
            label: 'Label: on',
            label_force: 'Label: force',
            label_minscaledenom: 'Label: max scale',
            label_maxscaledenom: 'Label: min scale',
            label_position: 'Label: position',
            label_color: 'Label: color',
            label_outlinecolor: 'Label: outline color',
            label_buffer: 'Label: buffer',
            label_text: 'Label: text',
            label_size: 'Label: size',
            label_angle: 'Label: angle',
            label_repeatdistance: 'Label: repeat distance',
            label_backgroundcolor: 'Label: background color',
            label_backgroundpadding: 'Label: background padding',
            label_offsetx: 'Label: offset X',
            label_offsety: 'Label: offset Y',
            label_font: 'Label: font',
            label_fontweight: 'Label: font weight',
            label_expression: 'Label: expression'
        },
        customRenderers: {
            label_color: cc,
            label_outlinecolor: cc,
            label_backgroundcolor: cc
        },
        customEditors: {
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
            'label_minscaledenom': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'label_maxscaledenom': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'label_buffer': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'label_position': new Ext.grid.GridEditor(new Ext.form.ComboBox({
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
            }), {}),
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
            'label_repeatdistance': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
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
            }), {})
        },
        viewConfig: {
            forceFit: true
        }
    });
    wmsClass.grid5 = new Ext.grid.PropertyGrid({
        id: 'propGrid5',
        height: 350,
        modal: false,
        region: 'center',
        border: false,
        propertyNames: {
            label2: 'Label: on',
            label2_force: 'Label: force',
            label2_minscaledenom: 'Label: max scale',
            label2_maxscaledenom: 'Label: min scale',
            label2_position: 'Label: position',
            label2_color: 'Label: color',
            label2_outlinecolor: 'Label: outline color',
            label2_buffer: 'Label: buffer',
            label2_text: 'Label: text',
            label2_size: 'Label: size',
            label2_angle: 'Label: angle',
            label2_repeatdistance: 'Label: repeat distance',
            label2_backgroundcolor: 'Label: background color',
            label2_backgroundpadding: 'Label: background padding',
            label2_offsetx: 'Label: offset X',
            label2_offsety: 'Label: offset Y',
            label2_font: 'Label: font',
            label2_fontweight: 'Label: font weight',
            label2_expression: 'Label: expression'
        },
        customRenderers: {
            label2_color: cc,
            label2_outlinecolor: cc,
            label2_backgroundcolor: cc
        },
        customEditors: {
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
            'label2_minscaledenom': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'label2_maxscaledenom': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'label2_buffer': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
            'label2_position': new Ext.grid.GridEditor(new Ext.form.ComboBox({
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
            }), {}),
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
            'label2_repeatdistance': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char
                // nobody is using
            }), {}),
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
            }), {})
        },
        viewConfig: {
            forceFit: true
        }
    });
};

