Ext.namespace('addOsm');
addOsm.init = function () {
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
    addOsm.schemas = [];
    Ext.each(window.gc2Options.osmConfig.schemas, function (item) {
        addOsm.schemas.push({name: item, value: item});
    });
    addOsm.form = new Ext.FormPanel({
        region: 'center',
        id: "addOsm",
        frame: false,
        border: false,
        title: __('Create layer from OSM view'),
        autoHeight: true,
        bodyStyle: 'padding: 10px 10px 0 10px;',
        labelWidth: 1,
        html: "Create a view on top of the OSM database. The extent of the map will be used as filter. You can also filter by OSM tags. ",
        defaults: {
            anchor: '99%',
            msgTarget: 'side'
        },
        items: [
            {
                xtype: 'textfield',
                emptyText: __('Name'),
                name: 'name',
                allowBlank: false
            },
            {
                width: 100,
                xtype: 'combo',
                mode: 'local',
                triggerAction: 'all',
                forceSelection: true,
                editable: false,
                //fieldLabel: 'type',
                name: 'table',
                displayField: 'name',
                valueField: 'value',
                allowBlank: false,
                emptyText: __('OSM type'),
                store: new Ext.data.JsonStore({
                    fields: ['name', 'value'],
                    data: [
                        {
                            name: 'POINT',
                            value: 'POINT'
                        },
                        {
                            name: 'LINE',
                            value: 'LINE'
                        },
                        {
                            name: 'AREA',
                            value: 'AREA'
                        },
                        {
                            name: 'ROADS',
                            value: 'ROADS'
                        }
                    ]
                })
            },
            {
                width: 100,
                xtype: 'combo',
                mode: 'local',
                triggerAction: 'all',
                forceSelection: true,
                editable: false,
                //fieldLabel: 'type',
                name: 'region',
                //value: 'DK',
                displayField: 'name',
                valueField: 'value',
                allowBlank: false,
                emptyText: __('Region'),
                store: new Ext.data.JsonStore({
                    fields: ['name', 'value'],
                    data: addOsm.schemas
                })
            },
            {
                xtype: 'textarea',
                name: 'tags',
                emptyText: __('tag=value\ntag=value\ntag=value')
            },
            {
                xtype: 'container',
                html: __('Match all, any or none of the above')
            },
            {
                width: 100,
                xtype: 'combo',
                mode: 'local',
                triggerAction: 'all',
                forceSelection: true,
                editable: false,
                name: 'match',
                value: 'ALL',
                displayField: 'name',
                valueField: 'value',
                allowBlank: false,
                store: new Ext.data.JsonStore({
                    fields: ['name', 'value'],
                    data: [
                        {
                            name: 'ALL',
                            value: 'ALL'
                        },
                        {
                            name: 'ANY',
                            value: 'ANY'
                        },
                        {
                            name: 'NONE',
                            value: 'NONE'
                        }
                    ]
                })
            }
        ],
        buttons: [
            {
                text: __('Create'),
                handler: function () {
                    var f = Ext.getCmp('addOsm');
                    if (f.form.isValid()) {
                        var values = f.form.getValues();
                        if (values.tags === 'tag=value\ntag=value\ntag=value') {
                            values.tags = null;
                        }
                        var param = {
                            data: values
                        };
                        param.data.extent = document.getElementById("wfseditor").contentWindow.window.map.getExtent();
                        param = Ext.util.JSON.encode(param);
                        Ext.Ajax.request({
                            url: '/controllers/osm/view',
                            method: 'put',
                            params: param,
                            headers: {
                                'Content-Type': 'application/json; charset=utf-8'
                            },
                            success: function () {
                                store.reload();
                                document.getElementById("wfseditor").contentWindow.window.reLoadTree();
                                App.setAlert(App.STATUS_NOTICE, __("View created"));
                            },
                            failure: function (response) {
                                Ext.MessageBox.show({
                                    title: 'Failure',
                                    msg: __(Ext.decode(response.responseText).message),
                                    buttons: Ext.MessageBox.OK,
                                    width: 400,
                                    height: 300,
                                    icon: Ext.MessageBox.ERROR
                                });
                            }
                        });
                    } else {
                        var s = '';
                        Ext.iterate(f.form.getValues(), function (key, value) {
                            s += String.format("{0} = {1}<br />", key, value);
                        }, this);
                    }
                }
            },
            {
                text: __('Reset'),
                handler: function () {
                    addOsm.form.getForm().reset();
                }
            }
        ]
    });
};