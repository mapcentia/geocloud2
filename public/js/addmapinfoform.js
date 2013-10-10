Ext.namespace('addMapInfo');
addMapInfo.init = function () {
    var me = this;
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
    me.form = new Ext.FormPanel({
            region: 'center',
            id: "addform",
            fileUpload: true,
            frame: false,
            border: false,
            title: 'MapInfo tab file upload',
            autoHeight: true,
            bodyStyle: 'padding: 10px 10px 0 10px',
            labelWidth: 1,
            defaults: {
                anchor: '97%',
                allowBlank: false,
                msgTarget: 'side'
            },
            items: [
                {
                    xtype: 'textfield',
                    name: 'name',
                    emptyText: 'Name of table',
                    allowBlank: false,
                },
                {
                    xtype: 'numberfield',
                    name: 'srid',
                    emptyText: 'Choose EPSG number'
                },
                {
                    xtype: 'fileuploadfield',
                    id: 'form-tab',
                    emptyText: 'Select .tab',
                    //fieldLabel: 'Tab',
                    name: 'tab',
                    buttonText: '',
                    buttonCfg: {
                        iconCls: 'upload-icon'
                    }
                },
                {
                    xtype: 'fileuploadfield',
                    id: 'form-map',
                    emptyText: 'Select .map',
                    //fieldLabel: 'Map',
                    name: 'map',
                    buttonText: '',
                    buttonCfg: {
                        iconCls: 'upload-icon'
                    }
                },
                {
                    xtype: 'fileuploadfield',
                    id: 'form-id',
                    emptyText: 'Select .id',
                    //fieldLabel: 'Shx',
                    name: 'id',
                    buttonText: '',
                    buttonCfg: {
                        iconCls: 'upload-icon'
                    }
                },
                {
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
                        data: [
                            {
                                name: 'Point',
                                value: 'Point'
                            },
                            {
                                name: 'Line',
                                value: 'Line'
                            },
                            {
                                name: 'Polygon',
                                value: 'Polygon'
                            },
                            {
                                name: 'Geometry',
                                value: 'Geometry'
                            }
                        ]
                    })
                },
              /*  {
                    xtype: "panel",
                    border: false,
                    defaults: {
                        bodyStyle: 'padding-left: 5px',
                        border: false
                    },
                    items: [
                        {
                            html: "Skip failures"

                        },
                        {
                            xtype: "panel",
                            items: {
                                xtype: 'checkbox',
                                name: 'skipfailures'
                            }
                        }
                    ]
                }*/
            ],
            buttons: [
                {
                    text: 'Save',
                    handler: function () {
                        if (me.form.getForm().isValid()) {
                            me.form.getForm().submit({
                                url: '/controllers/upload/mapinfo',
                                //waitMsg: 'Uploading your tab file...',
                                success: me.onSubmit,
                                failure: me.onSubmit
                            });
                        }
                    }
                },
                {
                    text: 'Reset',
                    handler: function () {
                        me.form.getForm().reset();
                    }
                }
            ]
        }
    )
    ;
    me.onSubmit = function (form, action) {
        var result = action.result;
        if (result.success) {
            store.load();
            App.setAlert(App.STATUS_NOTICE, result.message);
            //Ext.MessageBox.alert('Info', "Pt. kan der ikke gives en succes/fejl-meddelelse" + result.message);
            //addMapInfo.form.reset();
        } else {
            store.load();
            Ext.MessageBox.alert('Failure', result.message);
        }
    };
}
;

