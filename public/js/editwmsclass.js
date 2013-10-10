Ext.namespace('wmsClasses');
wmsClasses.init = function(record) {
    //alert(record.get("type"));
    wmsClasses.table = record.get("_key_");
    wmsClasses.reader = new Ext.data.JsonReader({
        totalProperty : 'total',
        successProperty : 'success',
        idProperty : 'id',
        root : 'data',
        messageProperty : 'message'
    }, [{
        name : 'id'
    }, {
        name : 'name'
    }, {
        name : 'expression'
    }]);
    wmsClasses.writer = new Ext.data.JsonWriter({
        writeAllFields : false,
        encode : false
    });
    wmsClasses.proxy = new Ext.data.HttpProxy({
        restful: true,
        api : {
            read : '/controllers/classification/index/' + wmsClasses.table,
            create : '/controllers/classification/index/' + wmsClasses.table,
            destroy : '/controllers/classification/index/' + wmsClasses.table
        },
        listeners : {
            write: wmsClasses.onWrite,
            exception : function(proxy, type, action, options, response, arg) {
                if (type === 'remote') {// success is false
                    // alert(response.message);
                    message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows=5' cols='31'>" + response.message + "</textarea>";
                    Ext.MessageBox.show({
                        title : 'Failure',
                        msg : message,
                        buttons : Ext.MessageBox.OK,
                        width : 400,
                        height : 300,
                        icon : Ext.MessageBox.ERROR
                    });
                }
            }
        }
    });
    wmsClasses.store = new Ext.data.Store({
        writer : wmsClasses.writer,
        reader : wmsClasses.reader,
        proxy : wmsClasses.proxy,
        autoSave : true
    });
    wmsClasses.store.load();
    wmsClasses.grid = new Ext.grid.EditorGridPanel({
        region : 'center',
        iconCls : 'silk-grid',
        store : wmsClasses.store,
        height : 200,

        viewConfig : {
            forceFit : true
        },
        region : 'center',
        sm : new Ext.grid.RowSelectionModel({
            singleSelect : true
        }),
        cm : new Ext.grid.ColumnModel({
            defaults : {
                sortable : true,
                editor : {
                    xtype : "textfield"
                }
            },
            columns : [{
                id : "name",
                header : "Name",
                dataIndex : "name",
                sortable : true
            }, {
                id : "expression",
                header : "Expression",
                dataIndex : "expression",
                sortable : true
            }]
        }),
        tbar : [{
            text : '<i class="icon-plus btn-gc"></i> Add class',
            //iconCls : 'silk-add',
            handler : wmsClasses.onAdd
        },{
            text : '<i class="icon-trash btn-gc"></i> Delete class',
            //iconCls : 'silk-delete',
            handler : wmsClasses.onDelete
        }],
        listeners : {
            rowdblclick : onSelectClass
        }
    });
};
wmsClasses.onAdd = function() {
    var requestCg = {
        url : '/controllers/classification/index/' + wmsClasses.table,
        method : 'post',
        callback : function(options, success, http) {
            var response = eval('(' + http.responseText + ')');
            wmsClasses.store.load();
        }
    };
    Ext.Ajax.request(requestCg);
};
wmsClasses.onDelete = function() {
    var record = wmsClasses.grid.getSelectionModel().getSelected();
    if (!record) {
        return false;
    }
    Ext.MessageBox.confirm('Confirm', 'Are you sure you want to do that?', function(btn) {
        if (btn === "yes") {
            wmsClasses.grid.store.remove(record);
        } else {
            return false;
        }
    });
};

wmsClasses.onSave = function() {
    wmsClasses.store.save();
};
wmsClasses.onWrite = function(store, action, result, transaction, rs) {
    if (transaction.success) {
        wmsClasses.store.load();
    }
};

function test() {
    message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows=5' cols='31'>" + response.message + "</textarea>";
    Ext.MessageBox.show({
        title : 'Failure',
        msg : message,
        buttons : Ext.MessageBox.OK,
        width : 400,
        height : 300,
        icon : Ext.MessageBox.ERROR
    });
}

Ext.namespace('wmsClass');
wmsClass.init = function(id) {
    wmsClass.classId = id;
    wmsClass.store = new Ext.data.JsonStore({
        // store config
        autoLoad : true,
        url : '/controllers/classification/index/' + wmsClasses.table + '/' + id,
        storeId : 'configStore',
        // reader config
        successProperty : 'success',
        // idProperty: 'id',
        root : 'data',
        // fields: 'fields',
        fields : [{
            name : 'name'
        }, {
            name : 'expression'
        }, {
            name : 'label'
        }, {
            name : 'label_size'
        },
        // Base style start
        {
            name : 'color'
        }, {
            name : 'outlinecolor'
        }, {
            name : 'symbol'
        }, {
            name : 'size'
        }, {
            name : 'width'
        },
        // Overlay style start
        {
            name : 'overlaycolor'
        }, {
            name : 'overlayoutlinecolor'
        }, {
            name : 'overlaysymbol'
        }, {
            name : 'overlaysize'
        }, {
            name : 'overlaywidth'
        }],
        listeners : {
            load : {
                fn : function(store, records, options) {
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
    wmsClass.grid = new Ext.grid.PropertyGrid({
        id : 'propGrid',
        width : 462,
        autoHeight : true,
        modal : false,
        region : 'center',
        frame : false,
        border : false,
        propertyNames : {
            name : 'Name',
            label_size : 'Label size',
            label : 'Label',
            expression : 'Expression',

            outlinecolor : 'Base outline color',
            symbol : 'Base symbol',
            color : 'Base color',
            size : 'Base symbol size',
            width : 'Base line width',

            overlaywidth : 'Overlay line width',
            overlayoutlinecolor : 'Overlay outline color',
            overlaysymbol : 'Overlay symbol',
            overlaycolor : 'Overlay color',
            overlaysize : 'Overlay symbol size'
        },
        customEditors : {
            'color' : new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'outlinecolor' : new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'symbol' : new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store : ['', 'circle', 'square', 'triangle', 'hatch1', 'dashed1', 'dot-dot', 'dashed-line-short', 'dashed-line-long', 'dash-dot', 'dash-dot-dot'],
                editable : false,
                triggerAction : 'all'
            }), {}),
            'size' : new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision : 0,
                decimalSeparator : '¤'// Some strange char nobody is
                // using
            }), {}),
            'width' : new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision : 0,
                decimalSeparator : '¤'// Some strange char
                // nobody is using
            }), {}),
            'label_size' : new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision : 0,
                decimalSeparator : '¤'// Some strange char
                // nobody is using
            }), {}),
            'overlaycolor' : new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'overlayoutlinecolor' : new Ext.grid.GridEditor(new Ext.form.ColorField({}), {}),
            'overlaysymbol' : new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store : ['', 'circle', 'square', 'triangle', 'hatch1', 'dashed1', 'dot-dot', 'dashed-line-short', 'dashed-line-long', 'dash-dot', 'dash-dot-dot'],
                editable : false,
                triggerAction : 'all'
            }), {}),
            'overlaysize' : new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision : 0,
                decimalSeparator : '¤'// Some strange char
                // nobody is
                // using
            }), {}),
            'overlaywidth' : new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision : 0,
                decimalSeparator : '¤'// Some strange char
                // nobody is using
            }), {})
        },
        viewConfig : {
            forceFit : true,
            scrollOffset : 2
            // the grid will never have scrollbars
        },
        tbar : [{
            text : '<i class="icon-ok btn-gc"></i> Update',
            //iconCls : 'silk-accept',
            handler : function() {
                var grid = Ext.getCmp("propGrid");
                var id = Ext.getCmp("configStore");
                var source = grid.getSource();
                // source.id = wmsClass.classId;
                // var jsonDataStr = null;
                // jsonDataStr = Ext.encode(source);
                var param = {
                    data : source
                };
                param = Ext.util.JSON.encode(param);

                var requestCg = {
                    url : '/controllers/classification/index/' + wmsClasses.table + '/' + wmsClass.classId,
                    method: 'put',
                    params : param,
                    headers : {
                        'Content-Type' : 'application/json; charset=utf-8'
                    },
                    callback : function(options, success, http) {
                        var response = eval('(' + http.responseText + ')');
                        wmsClasses.store.load();
                        wmsClasses.onSubmit(response);
                    }
                };
                Ext.Ajax.request(requestCg);
            }
        }]
    });
};

function onSelectClass(btn, ev) {
    var record = wmsClasses.grid.getSelectionModel().getSelected();
    if (!record) {
        App.setAlert(App.STATUS_NOTICE, "You\'ve to select a layer");
        return false;
    }
    wmsClass.grid = null;
    winClass = null;
    wmsClass.init(record.get("id"));
    winClass = new Ext.Window({
        title : "Edit class",
        modal : true,
        layout : 'fit',
        width : 500,
        autoHeight : true,
        closeAction : 'close',
        plain : true,
        items : [wmsClass.grid]

    });
    winClass.show(this);
}

wmsClasses.onSubmit = function(response) {
    if (response.success) {
        App.setAlert(App.STATUS_OK, "Style is updated");
        writeFiles();

    } else {
        message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows=5' cols='31'>" + result.message + "</textarea>";
        App.setAlert(App.STATUS_NOTICE, message);
    }
}; 
