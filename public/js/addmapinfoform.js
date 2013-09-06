Ext.namespace('addMapInfo');
addMapInfo.init = function () {
    Ext.QuickTips.init();
    var msg = function (title, msg) {
        Ext.Msg.show({
            title: title,
            msg: msg,
            minWidth: 200,
            modal: true,
            icon: Ext.Msg.INFO,
            buttons: Ext.Msg.OK
        });
    };
    addMapInfo.form = new Ext.FormPanel({
        region: 'center',
		id:"addform",
        fileUpload: true,
        frame: false,
		border: false,
        title: 'MapInfo tab file upload',
        autoHeight: true,
        bodyStyle: 'padding: 10px 10px 0 10px',
        labelWidth: 60,
        defaults: {
            anchor: '95%',
            allowBlank: false,
            msgTarget: 'side'
        },
        items: [/*{
                width: 100,
                emptyText: 'Choose EPSG number',
                xtype: 'combo',
                mode: 'local',
                triggerAction: 'all',
                forceSelection: true,
                editable: false,
                fieldLabel: 'Projection',
                name: 'srid',
                displayField: 'name',
                valueField: 'value',
                allowBlank: false,
                store: new Ext.data.JsonStore({
                    fields: ['name', 'value'],
                    data: [{
                        name: '25832 utm',
                        value: '25832'
                    }, {
                        name: '4326',
                        value: '4326'
                    }]
                })
           
        },*/{
            xtype: 'textfield',
            name: 'name',
            emptyText: 'Name of table',
			allowBlank: false,
        }, {
            xtype: 'numberfield',
            name: 'srid',
            emptyText: 'Choose EPSG number'
        }, {
            xtype: 'fileuploadfield',
            id: 'form-tab',
            emptyText: 'Select .tab',
            //fieldLabel: 'Tab',
            name: 'tab',
            buttonText: '',
            buttonCfg: {
                iconCls: 'upload-icon'
            }
        }, {
            xtype: 'fileuploadfield',
            id: 'form-map',
            emptyText: 'Select .map',
            //fieldLabel: 'Map',
            name: 'map',
            buttonText: '',
            buttonCfg: {
                iconCls: 'upload-icon'
            }
        }, {
            xtype: 'fileuploadfield',
            id: 'form-id',
            emptyText: 'Select .id',
            //fieldLabel: 'Shx',
            name: 'id',
            buttonText: '',
            buttonCfg: {
                iconCls: 'upload-icon'
            }
        }, {
            xtype: 'fileuploadfield',
            id: 'form-dat',
            emptyText: 'Select .dat',
            //fieldLabel: 'Dat',
            name: 'dat',
            buttonText: '',
            buttonCfg: {
                iconCls: 'upload-icon'
            }
			},
			{
                width: 100,
                xtype: 'combo',
                mode: 'local',
                triggerAction: 'all',
                forceSelection: true,
                editable: false,
                //fieldLabel: 'type',
                name: 'type',
                displayField: 'name',
                valueField: 'value',
                allowBlank: false,
				emptyText: 'Type',
                store: new Ext.data.JsonStore({
                    fields: ['name', 'value'],
                    data: [{
                        name: 'Point',
                        value: 'Point'
                    }, {
                        name: 'Line',
                        value: 'Line'
                    }, {
                        name: 'Polygon',
                        value: 'Polygon'
                    }, {
                        name: 'Geometry',
                        value: 'Geometry'
                    }]
                })
            }
        ],
        buttons: [{
            text: 'Save',
            handler: function () {
                if (addMapInfo.form.getForm().isValid()) {
                    addMapInfo.form.getForm().submit({
                        url: '/controller/upload/' + screenName + '/mapinfo',
                        waitMsg: 'Uploading your tab file...',
                        success: addMapInfo.onSubmit,
                        failure: addMapInfo.onSubmit
                    });
                }
            }
        }, {
            text: 'Reset',
            handler: function () {
                addMapInfo.form.getForm().reset();
            }
        }]
    })
};
addMapInfo.onSubmit = function (form, action) {
    var result = action.result;
    if (result.success) {
		store.load();
        Ext.MessageBox.alert('Info', "Pt. kan der ikke gives en succes/fejl-meddelelse"+result.message);
        //addMapInfo.form.reset();
    } else {
        Ext.MessageBox.alert('Failure', result.message);
    }
}
