Ext.namespace('cartomobile');
Ext.namespace('Ext.ux.grid');
Ext.ux.grid.CheckColumn = Ext.extend(
				Ext.grid.Column,
				{
					processEvent : function(name, e, grid, rowIndex, colIndex) {
					   'use strict'
						if (name == 'click'/* 'mousedown' */) {
							var record = grid.store.getAt(rowIndex);
							record.set(this.dataIndex,
									!record.data[this.dataIndex]);
							return false;
						} else {
							return Ext.ux.grid.CheckColumn/* Ext.grid.ActionColumn */.superclass.processEvent
									.apply(this, arguments);
						}
					},
					renderer : function(v, p, record) {
						p.css += ' x-grid3-check-col-td';
						return String.format(
								'<div class="x-grid3-check-col{0}"> </div>',
								v ? '-on' : '');
					},
					init : Ext.emptyFn
				});
cartomobile.init = function(record, screenName) {
   'use strict'

	cartomobile.reader = new Ext.data.JsonReader( {
		totalProperty : 'total',
		successProperty : 'success',
		idProperty : 'id',
		root : 'data',
		messageProperty : 'message'
	}, [{
		name : 'column',
		allowBlank : false
	}, {
		name : 'type',
		allowBlank : false
	}, {
		name : 'available',
		allowBlank : true
	}, {
		name : 'cartomobiletype',
		allowBlank : true
	}, {
		name : 'properties',
		allowBlank : true
	}]);

	cartomobile.writer = new Ext.data.JsonWriter( {
		writeAllFields : true,
		encode : false
	});
	cartomobile.proxy = new Ext.data.HttpProxy(
			{
				api : {
					read : '/controller/geometry_columns/' + screenName
							+ '/getcartomobilesettings/' + record.get("_key_"),
					update : '/controller/geometry_columns/' + screenName
							+ '/updatecartomobilesettings/' + record.get("f_table_schema")
							+ '.' + record.get("f_table_name") + '/'
							+ record.get("_key_")
				},
				listeners : {
					write : cartomobile.onWrite,
					exception : function(proxy, type, action, options,
							response, arg) {
						if (type === 'remote') { // success is false
							// alert(response.message);
							message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows=5' cols='31'>"
									+ response.message + "</textarea>";
							Ext.MessageBox.show( {
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

	cartomobile.store = new Ext.data.Store( {
		writer : cartomobile.writer,
		reader : cartomobile.reader,
		proxy : cartomobile.proxy,
		autoSave : true
	});

	//cartomobile.store.setDefaultSort('sort_id', 'asc');
	cartomobile.store.load();

	cartomobile.grid = new Ext.grid.EditorGridPanel( {
		iconCls : 'silk-grid',
		store : cartomobile.store,
		// autoExpandColumn: "desc",
		height : 345,
		// width: 750,
		ddGroup : 'mygridDD',
		enableDragDrop : false,
		viewConfig : {
			forceFit : true
		},
		region : 'center',
		sm : new Ext.grid.RowSelectionModel( {
			singleSelect : true
		}),
		cm : new Ext.grid.ColumnModel( {
			defaults : {
				sortable : true,
				editor : {
					xtype : "textfield"
				}
			},
			columns : [{
				id : "column",
				header : "Column",
				dataIndex : "column",
				sortable : true,
				editor : new Ext.form.TextField( {
					allowBlank : false
				}),
				width : 35
			}, {
				id : "type",
				header : "Native field type",
				dataIndex : "type",
				sortable : true,
				width : 35
			}, {
				id : "available",
				xtype : 'checkcolumn',
				header : 'Available',
				dataIndex : 'available',
				width : 35
			}, {
				id : "cartomobiletype",
				header : 'Cartomobile field type',
				dataIndex : 'cartomobiletype',
				width : 45,
				editor: {
                    xtype: 'combo',
                    store: new Ext.data.ArrayStore({
                        fields: ['abbr', 'action'],
                        data: [
							['', ''],
                            ['TextBox', 'TextBox'],
                            ['SingleText', 'SingleText'],
                            ['Number', 'Number'],
                            ['CheckBox', 'CheckBox'],
                            ['ChoiceList', 'ChoiceList'],
                            ['Time', 'Time'],
                            ['Date', 'Date'],
                            ['TimeStamp', 'TimeStamp'],
                            ['Picture', 'Picture'],
                            ['GPSLocation', 'GPSLocation']]
                    }),
                    displayField: 'action',
                    valueField: 'abbr',
                    mode: 'local',
                    typeAhead: false,
                    editable: false,
                    triggerAction: 'all'
                }
			}, {
				id : "properties",
				header : "Properties",
				dataIndex : "properties",
				sortable : true,
				//width : 60,
				editor : new Ext.form.TextField( {
					allowBlank : true
				})
			}]
		})
	});
	 
};

cartomobile.onSave = function() {
	cartomobile.store.save();
};
cartomobile.onWrite = function(store, action, result, transaction, rs) {
	// console.log('onwrite', store, action, result, transaction, rs);
	if (transaction.success) {
		cartomobile.store.load();
	}
};
function test() {
	message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows=5' cols='31'>"
			+ response.message + "</textarea>";
	Ext.MessageBox.show( {
		title : 'Failure',
		msg : message,
		buttons : Ext.MessageBox.OK,
		width : 400,
		height : 300,
		icon : Ext.MessageBox.ERROR
	})
}