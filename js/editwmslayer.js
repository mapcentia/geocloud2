Ext.namespace('wmsLayer');
wmsLayer.init = function (id) {
var fieldsForStore = [];
$.ajax({
        url: '/controller/tables/' + screenName + '/getcolumns/' + id,
        async: false,
        dataType: 'json',
        type: 'GET',
        success: function (data, textStatus, http) {
            if (http.readyState == 4) {
                if (http.status == 200) {
                    var response = eval('(' + http.responseText + ')'); // JSON
                    var forStore = response.forStore;
					fieldsForStore.push("");
                    for (var i in forStore) {
                    	fieldsForStore.push(forStore[i].name)
                    }
                }
            }
        }
    });
	wmsLayer.classId = id; 
    wmsLayer.store = new Ext.data.JsonStore({
        // store config
        autoLoad: true,
        url: '/controller/wmslayers/' + screenName + '/get/' + id,
        baseParams: {
            xaction: 'read'
        },
        storeId: 'configStore',
        // reader config
        successProperty: 'success',
        idProperty: 'id',
        root: 'data',
        fields: [{
            name: 'theme_column'
        },{
            name: 'label_column'
        },{
            name: 'query_buffer'
        },{
            name: 'opacity'
        },{
            name: 'label_max_scale'
        },{
            name: 'label_min_scale'
        }],
        listeners: {
            load: {
                fn: function (store, records, options) {
                    // get the property grid component
                    var propGrid = Ext.getCmp('propGrid');
                    // make sure the property grid exists
                    if (propGrid) {
                        // populate the property grid with store data
                        propGrid.setSource(store.getAt(0).data);
                    }
                }
            }
        }
    });
    wmsLayer.grid = new Ext.grid.PropertyGrid({
		region: 'north',
        id: 'propGrid',
        width: 462,
        autoHeight: true,
        modal: false,
        region: 'center',
        propertyNames: {
            label_column: 'Label field',
            theme_column: 'Theme field',
            query_buffer: 'Query buffer',
            opacity: 'Opacity',
            label_max_scale: 'Label max scale',
            label_min_scale: 'Label min scale'
        },
        customEditors: {
      
        'label_column': new Ext.grid.GridEditor(new Ext.form.ComboBox({
            store: fieldsForStore,
            editable: false,
            triggerAction: 'all'
        }), {}),
        'theme_column': new Ext.grid.GridEditor(new Ext.form.ComboBox({
            store: fieldsForStore,
            editable: false,
            triggerAction: 'all'
        }), {}),
		'query_buffer': new Ext.grid.GridEditor(new Ext.form.NumberField({
        		decimalPrecision:0,
        		decimalSeparator:'¤'// Some strange char nobody is using							
        }), {}),
		'opacity': new Ext.grid.GridEditor(new Ext.form.NumberField({
        		decimalPrecision:0,
        		decimalSeparator:'¤'// Some strange char nobody is using							
        }),{}),
		'label_max_scale': new Ext.grid.GridEditor(new Ext.form.NumberField({
        		decimalPrecision:0,
        		decimalSeparator:'¤'// Some strange char nobody is using							
        }),{}),
		'label_min_scale': new Ext.grid.GridEditor(new Ext.form.NumberField({
        		decimalPrecision:0,
        		decimalSeparator:'¤'// Some strange char nobody is using							
        }),{})
        
            },
        viewConfig: {
            forceFit: true,
            scrollOffset: 2 // the grid will never have scrollbars
        },
        tbar: [{
            text: 'Update',
            iconCls: 'silk-accept',
            handler: function () {
                var grid = Ext.getCmp("propGrid");
                var id = Ext.getCmp("configStore");
                var source = grid.getSource();
                var jsonDataStr = null;
                jsonDataStr = Ext.encode(source);
                var requestCg = {
                    url: '/controller/wmslayers/' + screenName + '/update/' + wmsLayer.classId,
                    method: 'post',
                    params: {
                        data: jsonDataStr
                    },
                    timeout: 120000,
                    callback: function (options, success, http) {
                        var response = eval('(' + http.responseText + ')');
						wmsLayer.onSubmit(response);                    }
                };
                Ext.Ajax.request(requestCg);
            }
        }]
    });
}
wmsLayer.onSubmit = function (response) {
        if (response.success) {
			Ext.MessageBox.show({
                        title: 'Success!',
                        msg: 'The layer settings are updated',
                        buttons: Ext.MessageBox.OK,
                        width: 300,
                        height: 300
                    })

        } else {
            message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows=5' cols='31'>" + result.message + "</textarea>";
                    Ext.MessageBox.show({
                        title: 'Failure',
                        msg: message,
                        buttons: Ext.MessageBox.OK,
                        width: 300,
                        height: 300
                    })
        }
    };