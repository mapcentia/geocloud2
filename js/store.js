var form;
var store;
var onEditWMSLayer;
Ext.onReady(function () {
	Ext.Container.prototype.bufferResize = false;
    Ext.QuickTips.init();
    var winAdd;
    var winEdit;
    var winClasses;
	var winWmsLayer;
    var fieldsForStore;
    $.ajax({
        url: '/controller/tables/' + screenName + '/getcolumns/geometry_columns_view',
        async: false,
        dataType: 'json',
        type: 'GET',
        success: function (data, textStatus, http) {
            if (http.readyState == 4) {
                if (http.status == 200) {
                    var response = eval('(' + http.responseText + ')'); // JSON
                    fieldsForStore = response.forStore;
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
        idProperty: 'f_table_name',
        root: 'data',
        messageProperty: 'message' // <-- New "messageProperty" meta-data
    }, fieldsForStore);
    var proxy = new Ext.data.HttpProxy({
        api: {
            read: '/controller/tables/' + screenName + '/getrecords/geometry_columns_view',
            update: '/controller/tables/' + screenName + '/updaterecord/settings.geometry_columns_join/f_table_name',
            destroy: '/controller/tables/' + screenName + '/destroyrecord/geometry_columns/f_table_name'
        },
        listeners: {
            //write: tableStructure.onWrite,
            exception: function (proxy, type, action, options, response, arg) {
                if (type === 'remote') { // success is false
                    //alert(response.message);
                    message = "<p>Sorry, but something went wrong. The whole transaction is rolled back. Try to correct the problem and hit save again. You can look at the error below, maybe it will give you a hint about what's wrong</p><br/><textarea rows=5' cols='31'>" + response.message + "</textarea>";
                    Ext.MessageBox.show({
                        title: 'Failure',
                        msg: message,
                        buttons: Ext.MessageBox.OK,
                        width: 300,
                        height: 300
                    })
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
        title: "Layers in your geocloud",
        store: store,
        autoExpandColumn: "desc",
        height: 400,
        split: true,
        region: 'north',
        frame: false,
        border: false,
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
                header: "Title",
                dataIndex: "f_table_title",
                sortable: true,
                width: 150
            }, {
                header: "Name",
                dataIndex: "f_table_name",
                sortable: true,
                editable: false,
                tooltip: "This can't be changed"
            }, {
                header: "SRS",
                dataIndex: "srid",
                sortable: true,
                editable: false,
                tooltip: "This can't be changed"
            }, {
                header: "Type",
                dataIndex: "type",
                sortable: true,
                editable: false,
                tooltip: "This can't be changed"
            }, {
                id: "desc",
                header: "Description",
                dataIndex: "f_table_abstract",
                sortable: true,
                editable: true,
                tooltip: ""
            },
				{
				            xtype: 'checkcolumn',
				            header: 'Tweet?',
				            dataIndex: 'tweet',
				            width: 55
				},
				{
				            xtype: 'checkcolumn',
				            header: 'Editable?',
				            dataIndex: 'editable',
				            width: 55
				},{
            header: 'Authentication for?',
            dataIndex: 'authentication',
            width: 120,
            tooltip: 'When accessing your layer from external clients, which level of authentication do you want?',
            editor   : {xtype:'combo', 
                        store: new Ext.data.ArrayStore({
                               fields: ['abbr', 'action'],
                               data : [                                         
                                       ['Write', 'Write'],
                                       ['Read/write', 'Read/write'],
                                       ['None', 'None']
                                      ]
                                }),
                               displayField:'action',
                               valueField: 'abbr',
                               mode: 'local',
                              typeAhead: false,
                              editable: false,
                              triggerAction: 'all'                       }
    }
        

				]
        }),
        tbar: [
		{
            text: 'Edit layer',
            iconCls: 'silk-application-view-list',
            handler: onSpatialEdit
        },'-',
        {
            text: 'Map viewer',
            iconCls: 'silk-map',
            handler: onView
        },'-',
		{
            text: 'Add new layer',
            iconCls: 'silk-add',
            handler: onAdd
        },'-',{
            text: 'Styles',
            iconCls: 'silk-palette',
            handler: onEditWMSClasses
        },'-',
		{
            text: 'Structure',
            iconCls: 'silk-cog',
            handler: onEdit
        },'->',
        {
            text: 'Delete',
            iconCls: 'silk-delete',
            handler: onDelete
        }
        ],
        listeners: {
            // rowdblclick: mapPreview
        }
    });

    function onDelete() {
        var record = grid.getSelectionModel().getSelected();
        if (!record) {
			Ext.MessageBox.show({
				   title: 'Hi',
				   msg: 'You\'ve to select a layer',
				   buttons: Ext.MessageBox.OK,
				   icon: Ext.MessageBox.INFO
			   });
			return false;
		}
        Ext.MessageBox.confirm('Confirm', 'Are you sure you want to do that?', function (btn) {
            if (btn == "yes") {
                grid.store.remove(record);
            } else {
                return false;
            }
        });
    }

    function onAdd(btn, ev) {
		 winAdd = null;
			var p = new Ext.Panel({
						id: "uploadpanel",
						frame: false,
						//width: 500,
						//height: 400,
						layout: 'border',
						items: [
							new Ext.Panel({
								region:"center"
							})]
					})
            winAdd = new Ext.Window({
                title: 'Add new layer',
                layout: 'fit',
                modal: true,
                width: 500,
                height: 300,
                closeAction: 'close',
                plain: true,
				items: [p],
				tbar: [{
						text: 'Blank layer',
						//iconCls: 'silk-add',
						handler: function () {
							addScratch.init();
							var c = p.getComponent(0);
							c.remove(0);
							c.add(addScratch.form);
							c.doLayout();
						}},'-',
						{
						text: 'Esri Shape',
						//iconCls: 'silk-add',
						handler: function () {
							addShape.init();
							var c = p.getComponent(0);
							c.remove(0);
							c.add(addShape.form);
							c.doLayout();
						}
						},'-',{
						text: 'GML',
						disabled: true,
						//iconCls: 'silk-add',
						tooltip: "Coming in beta",
						handler: function () {
							addGml.init();
							var c = p.getComponent(0);
							c.remove(0);
							c.add(addGml.form);
							c.doLayout();
						}
						},'-',{
						text: 'MapInfo TAB',
						disabled: true,
						//iconCls: 'silk-add',
						tooltip: "Coming in beta",
						handler: function () {
							addTab.init();
							var c = p.getComponent(0);
							c.remove(0);
							c.add(addTab.form);
							c.doLayout();
						}
						}]
            });
        
        winAdd.show(this);
    }

    function onSpatialEdit(btn, ev) {
        var record = grid.getSelectionModel().getSelected();
		if (!record) {
			Ext.MessageBox.show({
				   title: 'Hi',
				   msg: 'You\'ve to select a layer',
				   buttons: Ext.MessageBox.OK,
				   icon: Ext.MessageBox.INFO
			   });
			 return false;
		}

			var url = "/editor/" + screenName + "?layer=" + record.get("f_table_name");
			window.open(url, 'editor', 'width=1000,height=800');

    }
    function onView(btn, ev) {
			var url = "/apps/viewer/map_list_frame/" + screenName;
			window.open(url, 'viewer', 'width=1000,height=800');
    }

    function onEdit(btn, ev) {
        var record = grid.getSelectionModel().getSelected();
		if (!record) {
			Ext.MessageBox.show({
				   title: 'Hi',
				   msg: 'You\'ve to select a layer',
				   buttons: Ext.MessageBox.OK,
				   icon: Ext.MessageBox.INFO
			   });
			return false;
		}

        tableStructure.grid = null;
        winEdit = null;
        tableStructure.init(record.get("f_table_name"), screenName);
        form = new Ext.FormPanel({
            labelWidth: 100,
            // label settings here cascade unless overridden
            frame: true,
            region: 'center',
            title: 'Add new column',
            items: [{
                xtype: 'textfield',
                flex: 1,
                name: 'column',
                fieldLabel: 'Column',
                allowBlank: false
            }, {
                width: 100,
                xtype: 'combo',
                mode: 'local',
                triggerAction: 'all',
                forceSelection: true,
                editable: false,
                fieldLabel: 'Type',
                name: 'type',
                displayField: 'name',
                valueField: 'value',
                allowBlank: false,
                store: new Ext.data.JsonStore({
                    fields: ['name', 'value'],
                    data: [{
                        name: 'String',
                        value: 'string'
                    }, {
                        name: 'Integer',
                        value: 'int'
                    }, {
                        name: 'Decimal',
                        value: 'float'
                    }]
                })
            }],
            buttons: [{
                iconCls: 'silk-add',
                text: 'Add',
                handler: function () {
                    if (form.form.isValid()) {
                        form.getForm().submit({
                            url: '/controller/tables/' + screenName + '/createcolumn/' + record.get("f_table_name"),
                            waitMsg: 'Saving Data...',
                            submitEmptyText: false,
                            success: onSubmit,
                            failure: onSubmit
                        });
                    } else {
                        var s = '';
                        Ext.iterate(form.form.getValues(), function (key, value) {
                            s += String.format("{0} = {1}<br />", key, value);
                        }, this);
                        //Ext.example.msg('Form Values', s);
                    }
                }
            }]
        });
        winEdit = new Ext.Window({
            title: "Edit layer '" + record.get("f_table_name") + "'",
            modal: true,
            layout: 'fit',
            width: 600,
            height: 500,
            initCenter : false,
    		x : 100,
    		y : 100,
            closeAction: 'close',
            plain: true,
            items: [
            new Ext.Panel({
                frame: false,
                width: 500,
                height: 400,
                layout: 'border',
                items: [tableStructure.grid, form]
            })]
        });
        winEdit.show(this);
    }

    function onSave() {
        store.save();
    }
	function onEditWMSClasses(btn, ev) {
        var record = grid.getSelectionModel().getSelected();
		if (!record) {
			Ext.MessageBox.show({
				   title: 'Hi',
				   msg: 'You\'ve to select a layer',
				   buttons: Ext.MessageBox.OK,
				   icon: Ext.MessageBox.INFO
			   });
			return false;
		}

        wmsClasses.grid = null;
        wmsClasses.init(record.get("f_table_name"), screenName);
		
		//wmsLayer.grid = null;
		//wmsLayer.init(record.get("f_table_name"));
		
		winClasses = null;
        winClasses = new Ext.Window({
            title: "Edit classes '" + record.get("f_table_name") + "'",
            modal: true,
            layout: 'fit',
            width: 500,
            height: 400,
            initCenter : false,
    		x : 50,
    		y : 100,
            closeAction: 'close',
            plain: true,
            items: [
            new Ext.Panel({
                frame: false,
                width: 500,
                height: 400,
                layout: 'border',
                items: [wmsClasses.grid]
            })]
        });
        winClasses.show(this);
    }
	onEditWMSLayer = function (btn, ev) {
	var record = grid.getSelectionModel().getSelected();
    if (!record) {
        Ext.MessageBox.show({
            title: 'Hi',
            msg: 'You\'ve to select a layer',
            buttons: Ext.MessageBox.OK,
            icon: Ext.MessageBox.INFO
        });
        return false;
    }
    wmsLayer.grid = null;
    winWmsLayer = null;
    wmsLayer.init(record.get("f_table_name"));
    winWmsLayer = new Ext.Window({
        title: "Edit theme and label column + more on '" + record.get("f_table_name") + "'",
        modal: true,
        layout: 'fit',
        width: 500,
        height: 400,
        closeAction: 'close',
        plain: true,
        items: [
        new Ext.Panel({
            frame: false,
            width: 500,
            height: 400,
            layout: 'border',
            items: [wmsLayer.grid]
        })]
    });
    winWmsLayer.show(this);
}
    // define a template to use for the detail view
    var bookTplMarkup = ['<table>' +
    						'<tr class="x-grid3-row"><td>Created:</td><td>{created}</td></tr>' +
    						'<tr class="x-grid3-row"><td>Last modified:</td><td>{lastmodified}</td>' +
    						'</tr>' +
    					'</table>'];
    var bookTpl = new Ext.Template(bookTplMarkup);
    var ct = new Ext.Panel({
        frame: false,
        layout: 'border',
		region: 'center',
		border: true,
		split: true,
        items: [
        grid,
        {
            id: 'detailPanel',
            region: 'center',
			border: false,
            bodyStyle: {
                background: '#ffffff',
                padding: '7px'
            },
            html: '<table><tr class="x-grid3-row"><td>When you click on a layer you can more details in this window.</td></tr></tr></table>'
        },{
			region: "south",
			height: 250,
			border: false,
			bodyStyle: {
                
            }}]
    });
    grid.getSelectionModel().on('rowselect', function (sm, rowIdx,r) {
        var detailPanel = Ext.getCmp('detailPanel');
        bookTpl.overwrite(detailPanel.body, r.data);
		
		var south = ct.getComponent(2);
		south.remove("detailform");
		var detailForm = new Ext.FormPanel({
            labelWidth: 100,
            // label settings here cascade unless overridden
            frame: false,
			border: false,
            region: 'center',
            title: 'More layer settings',
			id: "detailform",
			bodyStyle: 'padding: 10px 10px 0 10px;',
            items: [{
				name: 'f_table_name',
				xtype: 'hidden',
				value: r.data.f_table_name
				
			},
				{
                width: 300,
                xtype: 'textfield',
                fieldLabel: 'Meta data URL',
                name: 'meta_url',
				value: r.data.meta_url,
              
            }],
            buttons: [{
                iconCls: 'silk-add',
                text: 'Update',
                handler: function () {
                    if (detailForm.form.isValid()) {
						var values = detailForm.form.getValues();
						var param = {data:values};
						param = Ext.util.JSON.encode(param);
						Ext.Ajax.request({
							url: '/controller/tables/' + screenName + '/updaterecord/settings.geometry_columns_join/f_table_name',
							headers: {'Content-Type':'application/json; charset=utf-8'},
							params: param,
							success: function(){
								store.reload();
								Ext.MessageBox.show({
								title: 'Success!',
								msg: 'Settings updated',
								buttons: Ext.MessageBox.OK,
								width: 300,
								height: 300
								});
							},
                            //failure: test
						});				
                    } else {
                        var s = '';
                        Ext.iterate(detailForm.form.getValues(), function (key, value) {
                            s += String.format("{0} = {1}<br />", key, value);
                        }, this);
                        //Ext.example.msg('Form Values', s);
                    }
                }
            }]
        });
		
		south.add(detailForm);
		south.doLayout();
		
    });
    var onSubmit = function (form, action) {
        var result = action.result;
        if (result.success) {
            tableStructure.store.load();
            form.reset();
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
	var accordion = new Ext.Panel({
				title: 'Settings and stuff',
				layout: 'accordion',
				region: 'center',
				region: 'east',
				width: 300,
				frame: false,
				plain:true,
				closable:false,
				border:true,
				layoutConfig:{animate:true},
				items: [
					{
						title: 'WFS stuff',
						border:false,
						bodyStyle: {
							background: '#ffffff',
							padding: '7px'
						},
						
						html: '<table border="0" class="pretty-tables"><tbody><tr><td>Use this string in GIS that supports WFS:</td></tr><tr><td><input type="text" readonly="readonly" value="http://alpha.mygeocloud.com/wfs/'+screenName+'" size="55"/></td></tr></tbody></table><table border="0"><tbody><tr><td>If you want to use another projection than the default add an EPSG code to the url like:</td></tr><tr><td><input type="text" readonly="readonly" value="http://alpha.mygeocloud.com/wfs/'+screenName+'/4326" size="55"/></td></tr></tbody></table>'
			 
					},{
						title: 'WMS stuff',
						border:false,
						bodyStyle: {
							background: '#ffffff',
							padding: '7px'
						},
						html: '<table border="0"><tbody><tr><td>Use this string in GIS that supports WMS:</td></tr><tr><td><input type="text" readonly="readonly" value="http://alpha.mygeocloud.com/wms/'+screenName+'/" size="55"/></td></tr></tbody></table>'
			 
					},
					httpAuth.form
				]
			})
	var viewport = new Ext.Viewport({
    layout: 'border',
    items:[
     ct,accordion
    ]
   });
});