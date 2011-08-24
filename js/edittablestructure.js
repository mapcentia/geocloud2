Ext.namespace('tableStructure');
tableStructure.init = function (table,screenName) {
	tableStructure.reader = new Ext.data.JsonReader({
		totalProperty: 'total',
		successProperty: 'success',
		idProperty: 'id',
		root: 'data',
		messageProperty: 'message'
	}, [{
		name: 'column',
		allowBlank: false
	}, {
		name: 'type',
		allowBlank: false
	},
	{
		name: 'querable',
		allowBlank: true
	},
	{
		name: 'alias',
		allowBlank: true
	}]);

	tableStructure.writer = new Ext.data.JsonWriter({
		writeAllFields: true,
		encode: false
	});
	tableStructure.proxy = new Ext.data.HttpProxy({
		api: {
			read: '/controller/tables/' + screenName + '/getstructure/' + table,
			create: '/controller/tables/' + screenName + '/createcolumn/' + table,
			update: '/controller/tables/' + screenName + '/updatecolumn/' + table,
			destroy: '/controller/tables/' + screenName + '/destroycolumn/' + table
		},
		listeners: {
			write: tableStructure.onWrite,
			exception: function(proxy, type, action, options, response, arg) {
            if(type === 'remote') { // success is false
				//alert(response.message);
				message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows=5' cols='31'>"+response.message+"</textarea>";
				Ext.MessageBox.show({
		           title: 'Failure',
		           msg: message,
		           buttons: Ext.MessageBox.OK,
				   width: 400,
				   height:300,
				   icon: Ext.MessageBox.ERROR
				   })
            }}
		}
	});

	tableStructure.store = new Ext.data.Store({
		writer: tableStructure.writer,
		reader: tableStructure.reader,
		proxy: tableStructure.proxy,
		autoSave: true
	});

	tableStructure.store.load();
	

	tableStructure.grid = new Ext.grid.EditorGridPanel({
		iconCls: 'silk-grid',
		store: tableStructure.store,
		//autoExpandColumn: "desc",
		height: 345,
		//width: 750,
		viewConfig: {
			forceFit: true
		},
		region: 'north',
		sm: new Ext.grid.RowSelectionModel({
			singleSelect: true
		}),
		cm: new Ext.grid.ColumnModel({
			defaults: {
				sortable: true,
				editor: {
					xtype: "textfield"
				}
			},
			columns: [{
				id: "column",
				header: "Column",
				dataIndex: "column",
				sortable: true,
				editor: new Ext.form.TextField({
					allowBlank: false
				})
			}, {
				id: "type",
				header: "Type",
				dataIndex: "type",
				sortable: true,
				editor: new Ext.form.ComboBox({
					typeAhead: false,
					triggerAction: 'all',
					mode: 'local',
					editable: false,
					allowBlank: false,
					readOnly: true,
					store: new Ext.data.ArrayStore({
						id: 0,
						fields: ['type'],
						data: [
							['int'],
							['string']
						]
					}),
					valueField: 'type',
					displayField: 'type'
				})
			},{
				id: "alias",
				header: "Alias",
				dataIndex: "alias",
				sortable: true,
				editor: new Ext.form.TextField({
					allowBlank: true
				})
			},
			{
				            xtype: 'checkcolumn',
				            header: 'querable',
				            dataIndex: 'querable',
				            width: 55
				}]
		}),
		tbar: [
		{
			text: 'Delete',
			iconCls: 'silk-delete',
			handler: tableStructure.onDelete
		}]
	});
};
tableStructure.onDelete = function () {
	var record = tableStructure.grid.getSelectionModel().getSelected();
	if (!record) {
		return false;
	}
	Ext.MessageBox.confirm('Confirm', 'Are you sure you want to do that?', function (btn) {
		if (btn === "yes") {
			tableStructure.grid.store.remove(record);
		} else {
			return false;
		}
	});
};
tableStructure.onAdd = function (btn, ev) {
	var field = tableStructure.grid.getStore().recordType;
	var u = new field({
		column: "New_field",
		type: "string"
	});
	//tableStructure.grid.stopEditing();
	tableStructure.grid.store.insert(0, u);
	//tableStructure.grid.startEditing(0, 1);
};
tableStructure.onSave = function () {
	tableStructure.store.save();
};
tableStructure.onWrite = function (store, action, result, transaction, rs) {
	//console.log('onwrite', store, action, result, transaction, rs);
	if (transaction.success) {
		tableStructure.store.load();
	} 
};
function test() {
	message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows=5' cols='31'>"+response.message+"</textarea>";
				Ext.MessageBox.show({
		           title: 'Failure',
		           msg: message,
		           buttons: Ext.MessageBox.OK,
				   width: 400,
				   height:300,
				   icon: Ext.MessageBox.ERROR
				   })
}