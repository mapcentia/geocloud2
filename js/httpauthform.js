Ext.namespace('httpAuth');
httpAuth.form = new Ext.FormPanel({
        region: 'center',
        frame: false,
		border: false,
        title: 'Authentication stuff',
        autoHeight: true,
        bodyStyle: 'padding: 10px 10px 0 10px;',
        labelWidth: 10,
        defaults: {
            anchor: '95%',
            allowBlank: false,
            msgTarget: 'side'
        },
        items: [ {
            xtype: 'textfield',
			inputType:'password',
            id: 'form-name',
            emptyText: 'Set/update password',
            name: 'pw',
        }
			],
        buttons: [{
            text: 'Update',
            handler: function () {
                if (httpAuth.form.getForm().isValid()) {
                    httpAuth.form.getForm().submit({
                        url: '/controller/users/' + screenName + '/updatepw',
                        waitMsg: 'Saving your password',
                        success: httpAuth.onSubmit,
                        failure: httpAuth.onSubmit
                    });
                }
            }
        }]
    });
httpAuth.onSubmit = function (form, action) {
    var result = action.result;
    if (result.success) {
        Ext.MessageBox.alert('Success', result.message);
        //httpAuth.form.reset();
    } else {
        Ext.MessageBox.alert('Failure', result.message);
    }
}