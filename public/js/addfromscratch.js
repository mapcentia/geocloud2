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
        id: "addScratch",
        frame: false,
        border: false,
        title: __('Create layer from scratch'),
        autoHeight: true,
        bodyStyle: 'padding: 10px 10px 0 10px;',
        labelWidth: 10,
        defaults: {
            anchor: '95%',
            allowBlank: false,
            msgTarget: 'side'
        },
        items: [
            {
                xtype: 'textfield',
                emptyText: __('Name'),
                name: 'name'
            },
            {

                xtype: 'textfield',
                name: 'srid',
                emptyText: __('EPSG number'),
                //value: '4326'
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
                emptyText: __('Type'),
                store: new Ext.data.JsonStore({
                    fields: ['name', 'value'],
                    data: [
                        {
                            name: 'POINT',
                            value: 'POINT'
                        },
                        {
                            name: 'MULTILINESTRING',
                            value: 'MULTILINESTRING'
                        },
                        {
                            name: 'MULTIPOLYGON',
                            value: 'MULTIPOLYGON'
                        }
                    ]
                })
            }
        ],
        buttons: [
            {
                text: __('Save'),
                handler: function () {
                    if (addScratch.form.getForm().isValid()) {
                        addScratch.form.getForm().submit({
                            url: '/controllers/table/records',
                            waitMsg: 'Creating your new layer',
                            success: function (form, action) {
                                App.setAlert(App.STATUS_NOTICE, action.result.message);
                                document.getElementById("wfseditor").contentWindow.window.reLoadTree();
                                store.load();
                            },
                            failure: function (form, action) {
                                Ext.MessageBox.show({
                                    title: 'Failure',
                                    msg: __(Ext.decode(action.response.responseText).message),
                                    buttons: Ext.MessageBox.OK,
                                    width: 400,
                                    height: 300,
                                    icon: Ext.MessageBox.ERROR
                                });
                            }
                        });
                    }
                }
            },
            {
                text: __('Reset'),
                handler: function () {
                    addScratch.form.getForm().reset();
                }
            }
        ]
    });
};