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
                }
            }
        }
    });
    wmsClasses.store = new Ext.data.Store({
        writer: wmsClasses.writer,
        reader: wmsClasses.reader,
        proxy: wmsClasses.proxy,
        autoSave: true,
        sortInfo: { field: "sortid", direction: "ASC" }
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
                    width: 40
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
        bbar: [
            {
                text: '<i class="icon-plus btn-gc"></i> Add class',
                handler: wmsClasses.onAdd
            },
            {
                text: '<i class="icon-trash btn-gc"></i> Delete class',
                handler: wmsClasses.onDelete
            }
        ],
        listeners: {
            rowclick: function () {
                var record = wmsClasses.grid.getSelectionModel().getSelected(), a3;
                if (!record) {
                    App.setAlert(App.STATUS_NOTICE, "You\'ve to select a layer");
                    return false;
                }
                a3 = Ext.getCmp("a3");
                a3.remove(wmsClass.grid);
                wmsClass.grid = null;
                wmsClass.init(record.get("id"));
                a3.add(wmsClass.grid);
                a3.doLayout();

            }
        }
    });
};
wmsClasses.onAdd = function () {
    var requestCg = {
        url: '/controllers/classification/index/' + wmsClasses.table,
        method: 'post',
        callback: function (options, success, http) {
            var response = eval('(' + http.responseText + ')');
            wmsClasses.store.load();
        }
    };
    Ext.Ajax.request(requestCg);
};
wmsClasses.onDelete = function () {
    var record = wmsClasses.grid.getSelectionModel().getSelected();
    if (!record) {
        return false;
    }
    Ext.MessageBox.confirm('Confirm', 'Are you sure you want to delete the class?', function (btn) {
        if (btn === "yes") {
            wmsClasses.grid.store.remove(record);
            var a3 = Ext.getCmp("a3");
            a3.remove(wmsClass.grid);
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
        writeFiles();
        clearTileCache(wmsClasses.table.split(".")[0] + "." + wmsClasses.table.split(".")[1]);
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
                name: 'label',
                type: 'boolean'
            },
            // Label start
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
                name: "label_angle"
            }
            ,
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
            }
        ],
        listeners: {
            load: {
                fn: function (store, records, options) {
                    // get the property grid component
                    var propGrid = Ext.getCmp('propGrid');
                    // make sure the property grid exists
                    if (propGrid) {
                        // Remove default sorting
                        delete propGrid.getStore().sortInfo;
                        // set sorting of first column to false
                        propGrid.getColumnModel().getColumnById('name').sortable = false;
                        // populate the property grid with store data
                        propGrid.setSource(store.getAt(0).data);
                    }
                }
            }
        }
    });
    var numberEditor = new Ext.form.NumberField({
        decimalPrecision: 0,
        decimalSeparator: '¤'// Some strange char
        // nobody is using
    });
    wmsClass.grid = new Ext.grid.PropertyGrid({
        id: 'propGrid',
        //autoHeight: true,
        height: 350,
        modal: false,
        region: 'center',
        border: false,
        style: {
            borderBottom: '1px solid #d0d0d0'
        },
        propertyNames: {
            sortid: 'Sort id',
            name: 'Name',
            label_size: 'Label: size',
            label: 'Label: on',
            label_force: 'Label: force',
            expression: 'Expression',
            class_minscaledenom: 'Min scale',
            class_maxscaledenom: 'Max scale',
            label_minscaledenom: 'Label: min. scale',
            label_maxscaledenom: 'Label: max scale',
            label_position: 'Label: position',
            label_color: 'Label: color',
            label_outlinecolor: 'Label: outline color',
            label_buffer: 'Label: buffer',
            label_text: 'Label: text',
            label_angle: 'Label: angle',

            leader: 'Leader: on',
            leader_gridstep: 'Leader: gridstep',
            leader_maxdistance: 'Leader: maxdistance',
            leader_color: 'Leader: color',

            outlinecolor: 'Style: outline color',
            symbol: 'Style: symbol',
            color: 'Style: color',
            size: 'Style: symbol size',
            width: 'Style: line width',
            angle: 'Style: symbol angle',
            style_opacity: 'Style: opacity',

            overlaywidth: 'Overlay: line width',
            overlayoutlinecolor: 'Overlay: outline color',
            overlaysymbol: 'Overlay: symbol',
            overlaycolor: 'Overlay: color',
            overlaysize: 'Overlay: symbol size',
            overlayangle: 'Overlay: symbol angle',
            overlaystyle_opacity: 'Overlay: opacity'
        },
        customEditors: {
            'sortid': new Ext.grid.GridEditor(new Ext.ux.form.SpinnerField({
                minValue: -100,
                maxValue: 9999,
                allowDecimals: false,
                decimalPrecision: 0,
                incrementValue: 1,
                accelerate: true}), { }),
            'color': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'outlinecolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'symbol': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: ['', 'circle', 'square', 'triangle', 'hatch1', 'dashed1', 'dot-dot', 'dashed-line-short', 'dashed-line-long', 'dash-dot', 'dash-dot-dot'],
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
            'label_color': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'leader_color': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'label_outlinecolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'overlaycolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'overlayoutlinecolor': new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'overlaysymbol': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: ['', 'circle', 'square', 'triangle', 'hatch1', 'dashed1', 'dot-dot', 'dashed-line-short', 'dashed-line-long', 'dash-dot', 'dash-dot-dot'],
                editable: false,
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

        },
        bbar: [
            {
                text: '<i class="icon-ok btn-gc"></i> Update',
                handler: function () {
                    var grid = Ext.getCmp("propGrid");
                    var id = Ext.getCmp("configStore");
                    var source = grid.getSource();
                    var param = {
                        data: source
                    };
                    param = Ext.util.JSON.encode(param);

                    // Encode the json because it can contain "="
                    param = encodeURIComponent(param);

                    Ext.Ajax.request({
                        url: '/controllers/classification/index/' + wmsClasses.table + '/' + wmsClass.classId,
                        method: 'put',
                        params: param,
                        headers: {
                            'Content-Type': 'application/json; charset=utf-8'
                        },
                        success: function (response) {
                            App.setAlert(App.STATUS_OK, "Style is updated");
                            writeFiles();
                            wmsClasses.store.load();
                            clearTileCache(wmsClasses.table.split(".")[0] + "." + wmsClasses.table.split(".")[1]);
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
    });
};

