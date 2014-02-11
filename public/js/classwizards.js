Ext.namespace('classWizards');
classWizards.init = function (record) {
    classWizards.quantile = new Ext.FormPanel({
        method: 'post',
        labelWidth: 1,
        frame: false,
        border: false,
        autoHeight: false,
        region: 'center',
        id: "uniqueform",
        defaults: {
            border: false,
            bodyStyle: 'padding:5px;'
        },
        items: [
            {
                html: "Single class"
            },
            {
                defaults: {
                    border: false
                },

                items: [

                    {
                        layout: 'form',
                        bodyStyle: 'margin-right:5px;',
                        items: [
                            {
                                xtype: 'button',
                                text: 'Create',
                                handler: function () {
                                    Ext.Ajax.request({
                                        url: '/controllers/classification/single/' + record._key_,
                                        method: 'put',
                                        headers: {
                                            'Content-Type': 'application/json; charset=utf-8'
                                        },
                                        success: function (response) {
                                            Ext.getCmp("a3").remove(wmsClass.grid);
                                            wmsClasses.store.load();
                                            writeFiles();
                                            clearTileCache(record.f_table_schema + "." + record.f_table_name);
                                            App.setAlert(App.STATUS_NOTICE, eval('(' + response.responseText + ')').message);
                                        },
                                        failure: function (response) {
                                            Ext.MessageBox.show({
                                                title: 'Failure',
                                                msg: eval('(' + response.responseText + ')').message,
                                                buttons: Ext.MessageBox.OK,
                                                width: 400,
                                                height: 300,
                                                icon: Ext.MessageBox.ERROR
                                            });
                                        }
                                    });
                                }
                            }
                        ]
                    }
                ]
            },
            {
                html: "Unique classes"
            },
            {
                defaults: {
                    border: false
                },
                layout: 'column',
                items: [
                    {
                        layout: 'form',
                        bodyStyle: 'margin-right:5px;',
                        items: [
                            {
                                xtype: 'button',
                                text: 'Create',
                                handler: function () {
                                    var f = Ext.getCmp('uniqueform');
                                    if (f.form.isValid()) {
                                        var values = f.form.getValues();
                                        Ext.Ajax.request({
                                            url: '/controllers/classification/unique/' + record._key_ + '/' + values.value,
                                            method: 'put',
                                            headers: {
                                                'Content-Type': 'application/json; charset=utf-8'
                                            },
                                            success: function (response) {
                                                Ext.getCmp("a3").remove(wmsClass.grid);
                                                wmsClasses.store.load();
                                                writeFiles();
                                                clearTileCache(record.f_table_schema + "." + record.f_table_name);
                                                App.setAlert(App.STATUS_NOTICE, eval('(' + response.responseText + ')').message);
                                            },
                                            failure: function (response) {
                                                Ext.MessageBox.show({
                                                    title: 'Failure',
                                                    msg: eval('(' + response.responseText + ')').message,
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
                            }
                        ]
                    },
                    {
                        xtype: "combo",
                        store: wmsLayer.fieldsForStore,
                        editable: false,
                        triggerAction: "all",
                        name: "value",
                        emptyText: "Field",
                        allowBlank: false
                    }
                ]
            }
        ]
    });
};