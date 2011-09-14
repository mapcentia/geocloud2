Ext.namespace('addShape');
addShape.init = function () {
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
    addShape.form = new Ext.FormPanel({
        region: 'center',
		id:"addform",
        fileUpload: true,
        frame: false,
		border: false,
        title: 'ESRI Shape file upload',
        autoHeight: true,
        bodyStyle: 'padding: 10px 10px 0 10px;',
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
            xtype: 'numberfield',
            name: 'srid',
            fieldLabel: 'Projection',
            emptyText: 'Choose EPSG number',
        }, {
            xtype: 'fileuploadfield',
            id: 'form-shp',
            emptyText: 'Select .shp',
            //fieldLabel: 'Shp',
            name: 'shp',
            buttonText: '',
            buttonCfg: {
                iconCls: 'upload-icon'
            }
        }, {
            xtype: 'fileuploadfield',
            id: 'form-dbf',
            emptyText: 'Select .dbf',
            //fieldLabel: 'Dbf',
            name: 'dbf',
            buttonText: '',
            buttonCfg: {
                iconCls: 'upload-icon'
            }
        }, {
            xtype: 'fileuploadfield',
            id: 'form-shx',
            emptyText: 'Select .shx',
            //fieldLabel: 'Shx',
            name: 'shx',
            buttonText: '',
            buttonCfg: {
                iconCls: 'upload-icon'
            }
        }, {
        	xtype: 'checkbox',
        	name: 'pdo',
        	fieldLabel: 'Slow verbose load?'
        }
        ],
        buttons: [{
            text: 'Save',
            handler: function () {
                if (addShape.form.getForm().isValid()) {
                    addShape.form.getForm().submit({
                        url: '/controller/upload/' + screenName + '/shape',
                        waitMsg: 'Uploading your shape file...',
                        success: addShape.onSubmit,
                        failure: addShape.onSubmit
                    });
                }
            }
        }, {
            text: 'Reset',
            handler: function () {
                addShape.form.getForm().reset();
            }
        }]
    })
};
addShape.onSubmit = function (form, action) {
    var result = action.result;
    if (result.success) {
		store.load();
        Ext.MessageBox.alert('Success', result.message);
        //addShape.form.reset();
    } else {
        Ext.MessageBox.alert('Failure', result.message);
    }
}