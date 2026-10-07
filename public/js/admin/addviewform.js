/*
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

Ext.namespace('addView');
addView.init = function () {
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
    var schemasStore = new Ext.data.Store({
        reader: new Ext.data.JsonReader({
            successProperty: 'success',
            root: 'data'
        }, [
            {
                "name": "schema"
            }
        ]),
        url: '/controllers/database/schemas'
    });
    schemasStore.load()
    addView.form = new Ext.FormPanel({
        region: 'center',
        id: "addView",
        frame: false,
        border: false,
        title: __('Create layer from database view'),
        autoHeight: true,
        bodyStyle: 'padding: 10px 10px 0 10px;',
        labelWidth: 1,
        html: __("You can create a view over a SELECT query, which gives a name to the query that you can refer to like an ordinary table."),
        defaults: {
            anchor: '99%',
            allowBlank: false,
            msgTarget: 'side'
        },
        items: [
            {
                xtype: 'textfield',
                emptyText: __('Name'),
                name: 'name',
                id: 'view-name'
            },
            {
                xtype: 'textarea',
                name: 'select',
                id: 'view-select',
                emptyText: __('SELECT ...')
            },
            {
                xtype: 'container',
                html: __('Materialize')
            },
            {
                xtype: 'checkbox',
                name: 'matview'
            },

            {
                xtype: 'fieldset',
                title: __('Create view from'),
                checkboxToggle: false,
                collapsed: false,
                layput: 'form',
                defaults: {
                    anchor: '100%'
                },
                labelWidth: 100,
                items: [
                    {
                        xtype: "combo",
                        fieldLabel: __('Schema'),
                        store: schemasStore,
                        displayField: 'schema',
                        editable: false,
                        mode: 'local',
                        triggerAction: 'all',
                        lazyRender: true,
                        name: 'schema',
                        allowBlank: false,
                        listeners: {
                            'select': function (combo, value, index) {
                                Ext.getCmp('createSelectView').clearValue();
                                (function () {
                                    Ext.Ajax.request({
                                        url: '/controllers/layer/records/' + combo.getValue(),
                                        method: 'GET',
                                        headers: {
                                            'Content-Type': 'application/json; charset=utf-8'
                                        },
                                        success: function (response) {
                                            Ext.getCmp('createSelectView').store.loadData(
                                                Ext.decode(response.responseText)
                                            );
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
                                }());
                            }
                        }
                    },
                    {
                        xtype: "combo",
                        fieldLabel: __('Layer'),
                        id: "createSelectView",
                        store: new Ext.data.Store({
                            reader: new Ext.data.JsonReader({
                                successProperty: 'success',
                                root: 'data'
                            }, [
                                {
                                    "name": "f_table_name"
                                },
                                {
                                    "name": "_key_"
                                }
                            ]),
                            url: '/controllers/layer/groups'
                        }),
                        displayField: 'f_table_name',
                        valueField: '_key_',
                        editable: false,
                        mode: 'local',
                        triggerAction: 'all',
                        name: 'key',
                        allowBlank: false,
                        listeners: {
                            'select': function (combo, value, index) {
                                const split = Ext.getCmp('createSelectView').value.split('.');
                                const view = `SELECT * from ${split[0]}.${split[1]}`;
                                const name = `${split[1]}_view`;
                                console.log(view)
                                Ext.getCmp('view-select').setValue(view)
                                Ext.getCmp('view-name').setValue(name)
                            }
                        }
                    },

                ]
            }
        ],

        buttons: [
            {
                text: __('Create'),
                handler: function () {
                    var f = Ext.getCmp('addView');
                    if (f.form.isValid()) {
                        var values = f.form.getValues(),
                            safeName = values.name.replace(/[^\w\s]/gi, '').replace(' ', '');
                        if (Ext.isNumber(safeName.charAt(0))) {
                            safeName = "_" + safeName;
                        }
                        var param = {
                            q: btoa(values.select).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''),
                            mat: values.matview === "on",
                            name: schema + '.' + safeName,
                        };
                        Ext.Ajax.request({
                            url: '/controllers/layer/view',
                            method: 'post',
                            params: Ext.util.JSON.encode(param),
                            headers: {
                                'Content-Type': 'application/json; charset=utf-8'
                            },
                            success: function () {
                                reLoadTree();
                                writeFiles();
                                writeMapCacheFile();
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
                    addView.form.getForm().reset();
                }
            }
        ]
    });
};