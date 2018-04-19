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
        html: __("Create a view on top of the OSM database. If you choose to create a table instead of a view, the data will be copied. Use this option for large data sets. The extent of the map will be used as filter. You can also filter by OSM tags."),
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
                }),
                value: window.gc2Options.osmConfig.schemas[0]
            },
            {
                xtype: 'textarea',
                height: 50,
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
            },
            {
                xtype: 'container',
                html: __('Create table instead of a view?')
            },
            {
                xtype: 'combo',
                store: new Ext.data.ArrayStore({
                    fields: ['name', 'value'],
                    data: [
                        ['true', true],
                        ['false', false]
                    ]
                }),
                displayField: 'name',
                valueField: 'value',
                mode: 'local',
                typeAhead: false,
                editable: false,
                triggerAction: 'all',
                name: 'createtable',
                value: false
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
                        param.data.extent = map.getExtent();
                        var width = (param.data.extent.left - param.data.extent.right);
                        var height = (param.data.extent.top - param.data.extent.bottom);
                        var max = 50000;
                        if (((width < 0 ? width * -1 : width) > max || (height < 0 ? height * -1 : width) > max) && values.createtable === 'false') {
                            Ext.MessageBox.show({
                                title: 'Info',
                                msg: __('The width or height of map extent exceed the limit. Create a table instead for better performance.'),
                                buttons: Ext.MessageBox.OK,
                                width: 400,
                                height: 300,
                                icon: Ext.MessageBox.INFO
                            });
                            return false;
                        }
                        if ((width < 0 ? width * -1 : width) > 200000) {
                            Ext.MessageBox.show({
                                title: __('Info'),
                                msg: __('The width or height of map extent exceed 200km, which are the limit for the map extent.'),
                                buttons: Ext.MessageBox.OK,
                                width: 400,
                                height: 300,
                                icon: Ext.MessageBox.INFO
                            });
                            return false;
                        }
                        param = Ext.util.JSON.encode(param);
                        Ext.Ajax.request({
                            url: values.createtable === 'true' ? '/controllers/osm/table' : '/controllers/osm/view',
                            method: 'put',
                            params: param,
                            headers: {
                                'Content-Type': 'application/json; charset=utf-8'
                            },
                            success: function () {
                                reLoadTree();
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