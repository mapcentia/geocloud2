var store;
var getvars = getUrlVars();
//var onEditWMSLayer;
Ext.Ajax.disableCaching = false;

// We need to use jQuery load function to make sure that document.namespaces are ready. Only IE
$(window).load(function () {
	"use strict";
    Ext.Container.prototype.bufferResize = false;
    Ext.QuickTips.init();

    var fieldsForStore;
    var columnsForGrid;
	var type;
	var multi;
	var editable = true;
    $.ajax( {
			url : '/controller/tables/' + screenName + '/getcolumns/'
					+ getvars['layer'],
			async : false,
			dataType : 'json',
			type : 'GET',
			success : function(data, textStatus, http) {
				if (http.readyState == 4) {
					if (http.status == 200) {
						var response = eval('(' + http.responseText + ')'); // JSON
			fieldsForStore = response.forStore;
			columnsForGrid = response.forGrid;
			type = response.type;
			multi = response.multi;
			// We add an editor to the fields
			for ( var i in columnsForGrid) {
				columnsForGrid[i].editable = editable;
				// alert(columnsForGrid[i].header+"
			// "+columnsForGrid[i].typeObj.type);
			if (columnsForGrid[i].typeObj !== undefined) {
				if (columnsForGrid[i].typeObj.type == "int") {
					columnsForGrid[i].editor = new Ext.form.NumberField( {
						decimalPrecision : 0,
						decimalSeparator : '¤'// Some strange char nobody is using
					});
				} else if (columnsForGrid[i].typeObj.type == "decimal") {
					columnsForGrid[i].editor = new Ext.form.NumberField( {
						decimalPrecision : columnsForGrid[i].typeObj.scale,
						decimalSeparator : '.'
					// maxLength: columnsForGrid[i].type.precision
							});
				} else if (columnsForGrid[i].typeObj.type == "string") {
					columnsForGrid[i].editor = new Ext.form.TextField();
				}
			}
		}
		;
	}
}

}
		});
    var writer = new Ext.data.JsonWriter({
        writeAllFields: false,
        encode: false
    });
    var reader = new Ext.data.JsonReader({
        //totalProperty: 'total',
        successProperty: 'success',
        idProperty: 'gid',
        root: 'data',
        messageProperty: 'message'
        // <-- New "messageProperty" meta-data
    },
    fieldsForStore);
    var proxy = new Ext.data.HttpProxy({
        api: {
            read: '/controller/tables/' + screenName + '/getallrecords/' + getvars['layer']
            //update: '/controller/tables/' + screenName + '/updaterecord/settings.geometry_columns_join/f_table_name',
            //destroy: '/controller/tables/' + screenName + '/destroy'
        },
        listeners: {
            //write: tableStructure.onWrite,
            exception: function (proxy, type, action, options, response, arg) {
                if (type === 'remote') {
                    // success is false
                    //alert(response.message);
                    var message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows=5' cols='31'>" + response.message + "</textarea>";
                    Ext.MessageBox.show({
                        title: 'Failure',
                        msg: message,
                        buttons: Ext.MessageBox.OK,
                        width: 300,
                        height: 300
                    });
                }
            }
        }
    });

    store = new Ext.data.Store({
        writer: writer,
        reader: reader,
        proxy: proxy,
        autoSave: true
    });
    store.load();
	 // create a grid to display records from the store
    var grid = new Ext.grid.EditorGridPanel({
        title: "Lokalplaner (dobbeltklik for at læse)",
		//autoExpandColumn: "plannavn",
        store: store,
        height: 400,
        split: true,
        region: 'center',
        frame: false,
        border: false,
		viewConfig : {
				forceFit : true
			},
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
                header: "Plannr",
                dataIndex: "plannr",
                sortable: true,
				editable: false,
                width: 60
            },
            {
                header: "Navn",
                dataIndex: "plannavn",
                sortable: true,
                editable: false,
				width: 250
            },
            {
                header: "Distrikt",
                dataIndex: "distrikt",
                sortable: true,
                editable: false,
				width: 150
            },
            {
                header: "Anvendelse",
                dataIndex: "anvendelsegenerel",
                sortable: true,
                editable: false,
				width: 150
            },
            {
                header: "Zone",
                dataIndex: "zonestatus",
                sortable: true,
                editable: false,
				width: 150
            }]
        }),
        listeners: {
            rowdblclick: function(btn, ev) {
				var record = grid.getSelectionModel().getSelected();
				//alert(record.get("doklink"));
				window.open(record.get("doklink"));
			}
        }
    });
	var viewport = new Ext.Viewport({
		layout : 'border',
		items : new Ext.Panel({
			frame: false,
			layout: 'border',
			region: 'center',
			border: true,
			items: [grid]
			})
		});
});
function getUrlVars() {
	var mapvars = {};
	var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi,
			function(m, key, value) {
				mapvars[key] = value;
			});
	return mapvars;
}
