Ext.namespace('addGml');
addGml.init = function () {
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
    addGml.form = new Ext.FormPanel({
        region: 'center',
		id:"addgml",
        fileUpload: true,
        frame: false,
		border: false,
        title: 'GML file upload',
        autoHeight: true,
        bodyStyle: 'padding: 10px 10px 0 10px;',
        labelWidth: 60,
        defaults: {
            anchor: '95%',
            allowBlank: false,
            msgTarget: 'side'
        },
        items: [{
            xtype: 'textfield',
            name: 'srid',
            fieldLabel: 'Projection',
            emptyText: 'Choose EPSG number'
        }, {
            xtype: 'fileuploadfield',
            id: 'form-gml',
            emptyText: 'Select .gml',
            //fieldLabel: 'Shp',
            name: 'gml',
            buttonText: '',
            buttonCfg: {
                iconCls: 'upload-icon'
            }
        }],
        buttons: [{
            text: 'Save',
            handler: function () {
                if (addGml.form.getForm().isValid()) {
                    addGml.form.getForm().submit({
                        url: 'http://mygeocloud.com/controller/upload/' + screenName + '/gml',
                        waitMsg: 'Uploading your shape file...',
                        success: addGml.onSubmit,
                        failure: addGml.onSubmit
                    });
                }
            }
        }, {
            text: 'Reset',
            handler: function () {
                addGml.form.getForm().reset();
            }
        }]
    })
};
addGml.onSubmit = function (form, action) {
    var result = action.result;
    if (result.success) {
        Ext.MessageBox.alert('Success', result.message);
        //addGml.form.reset();
    } else {
        Ext.MessageBox.alert('Failure', result.message);
    }
}