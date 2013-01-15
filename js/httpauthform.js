Ext.namespace('httpAuth');
httpAuth.form = new Ext.FormPanel({
        frame: false,
		border: false,
        autoHeight: false,
        //bodyStyle: 'padding: 10px 10px 0 10px;',
        labelWidth: 1,
        defaults: {
            anchor: '95%',
            allowBlank: false,
            msgTarget: 'side'
        },
        items: [ new Ext.Panel({
				frame: false,
        border: false,
				bodyStyle: 'padding: 7px 7px 10px 7px;',
				contentEl: "authentication"
			}),{
            xtype: 'textfield',
			inputType:'password',
            id: 'httpAuthForm',
            name: 'pw',
            emptyText: 'Password'
        }
			],
        buttons: [{
            text: 'Update',
            handler: function () {
				"use strict";
                if (httpAuth.form.getForm().isValid()) {
                    httpAuth.form.getForm().submit({
                        url: '/controller/settings_viewer/' + screenName + '/updatepw',
                        waitMsg: 'Saving your password',
                        success: httpAuth.onSubmit,
                        failure: httpAuth.onSubmit
                    });
                }
            }
        }]
        //html: "Set password for WFS http authentication"
    });
httpAuth.onSubmit = function (form, action) {
	"use strict";
    var result = action.result;
    if (result.success) {
        Ext.MessageBox.alert('Success', result.message);
        //httpAuth.form.reset();
    } else {
        Ext.MessageBox.alert('Failure', result.message);
    }
};
