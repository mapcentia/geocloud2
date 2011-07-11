Ext.namespace('addScratch');
addScratch.init = function () {
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
    addScratch.form = new Ext.FormPanel({
        region: 'center',
		id:"addScratch",
        frame: false,
		border: false,
        title: 'Create layer from scratch',
        autoHeight: true,
        bodyStyle: 'padding: 10px 10px 0 10px;',
        labelWidth: 10,
        defaults: {
            anchor: '95%',
            allowBlank: false,
            msgTarget: 'side'
        },
        items: [ /*{
           
			xtype: 'textfield',
            name: 'srid',
            fieldLabel: 'Projection',
            emptyText: 'Choose EPSG number',
			value: '4326',
			editable: false
        },*/{
            xtype: 'textfield',
            emptyText: 'layer name',
            name: 'name'
        },{
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
                        name: 'POINT',
                        value: 'POINT'
                    }, {
                        name: 'MULTILINESTRING',
                        value: 'MULTILINESTRING'
                    }, {
                        name: 'MULTIPOLYGON',
                        value: 'MULTIPOLYGON'
                    }]
                })
            }
			],
        buttons: [{
            text: 'Save',
            handler: function () {
                if (addScratch.form.getForm().isValid()) {
                    addScratch.form.getForm().submit({
                        url: '/controller/upload/' + screenName + '/scratch',
                        waitMsg: 'Creating your new layer',
                        success: addScratch.onSubmit,
                        failure: addScratch.onSubmit
                    });
                }
            }
        }, {
            text: 'Reset',
            handler: function () {
                addScratch.form.getForm().reset();
            }
        }]
    })
};
addScratch.onSubmit = function (form, action) {
    var result = action.result;
    if (result.success) {
		store.load();
        Ext.MessageBox.alert('Success', result.message);
        //addScratch.form.reset();
    } else {
        Ext.MessageBox.alert('Failure', result.message);
    }
}