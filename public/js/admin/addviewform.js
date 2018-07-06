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
    addView.form = new Ext.FormPanel({
        region: 'center',
        id: "addView",
        frame: false,
        border: false,
        title: __('Create layer from database view'),
        autoHeight: true,
        bodyStyle: 'padding: 10px 10px 0 10px;',
        labelWidth: 1,
        html: __("You can create a view over a SELECT query, which gives a name to the query that you can refer to like an ordinary table.<br/><br/>Views can be used in almost any place a real table can be used.<br/><br/>Be sure that the view includes a geometry field."),
        defaults: {
            anchor: '99%',
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
                xtype: 'textarea',
                name: 'select',
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
                xtype: 'container',
                height: 20
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
                        var param = "q=CREATE " + (values.matview === "on" ? "MATERIALIZED" : "") + " VIEW " + schema + "." + safeName + " AS " + encodeURIComponent(values.select) + "&key=" + settings.api_key;
                        Ext.Ajax.request({
                            url: '/api/v1/sql/' + screenName,
                            method: 'post',
                            params: param,
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