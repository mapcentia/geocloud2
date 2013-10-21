Ext.namespace('addShape');
addShape.init = function () {
    "use strict";
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
    me.form = new Ext.Panel({
        region: 'center',
        id: "addform",
        frame: false,
        bodyStyle: 'padding: 0',
        border: false,
        autoHeight: true,
        html: "<div id='shape_uploader'></div>",
        afterRender: function () {
            var arr = [], ext = ["shp", "tab", "geojson", "gml"], geoType, srs;
            $("#shape_uploader").pluploadQueue({
                // General settings
                runtimes: 'html5,html4',
                url: '/controllers/upload/file',
                max_file_size: '200mb',
                chunk_size: '1mb',
                unique_names: true,
                init: {
                    UploadComplete: function (up, files) {
                        Ext.each(arr, function (e) {
                            geoType = (e.split(".")[1].toLowerCase() === "shp") ? "PROMOTE_TO_MULTI" : geoType;
                            $.ajax({
                                url: '/controllers/upload/process',
                                data: "srid=" + srs + "&file=" + e + "&name=" + e.split(".")[0] + "&type=" + geoType,
                                dataType: 'json',
                                type: 'GET',
                                success: function (response, textStatus, http) {
                                    store.load();
                                    if (response.success) {
                                        store.load();
                                        App.setAlert(App.STATUS_NOTICE, response.message);
                                    } else {
                                        Ext.MessageBox.alert('Failure', response.message);
                                    }
                                }
                            });
                        });
                    },
                    FilesAdded: function (up, files) {
                        Ext.each(files, function (item) {
                            //console.log(item.name);
                            Ext.each(ext, function (e) {
                                if (item.name.split(".")[1].toLowerCase() === e) {
                                    arr.push(item.name);
                                }
                            });
                        });
                    },
                    BeforeUpload: function (up, file) {
                        geoType = Ext.getCmp('geotype').getValue();
                        srs = Ext.getCmp('srs').getValue();
                        up.settings.multipart_params = {
                            name: file.name
                        };
                    }
                }
            });
        },
        tbar: [
            {
                text: 'Epsg:'
            },
            {
                width: 60,
                xtype: 'textfield',
                id: 'srs',
                value: '4326'

            },
            {
                text: 'Type:'
            },
            {
                width: 80,
                xtype: 'combo',
                mode: 'local',
                triggerAction: 'all',
                forceSelection: true,
                editable: false,
                id: 'geotype',
                displayField: 'name',
                valueField: 'value',
                value: 'Auto',
                allowBlank: false,
                store: new Ext.data.JsonStore({
                    fields: ['name', 'value'],
                    data: [
                        {
                            name: 'Auto',
                            value: 'Auto'
                        },
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
                        }
                    ]
                })
            }
        ]
    });

    /*me.form = new Ext.FormPanel({
     region: 'center',
     id: "addform",
     fileUpload: true,
     frame: false,
     border: false,
     title: 'ESRI Shape file upload',
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
     allowBlank: false
     },
     {
     xtype: 'numberfield',
     name: 'srid',
     emptyText: 'Choose EPSG number'
     },
     {
     xtype: 'fileuploadfield',
     id: 'form-shp',
     emptyText: 'Select .shp',
     //fieldLabel: 'Shp',
     name: 'shp',
     buttonText: '',
     buttonCfg: {
     iconCls: 'upload-icon'
     }
     },
     {
     xtype: 'fileuploadfield',
     id: 'form-dbf',
     emptyText: 'Select .dbf',
     //fieldLabel: 'Dbf',
     name: 'dbf',
     buttonText: '',
     buttonCfg: {
     iconCls: 'upload-icon'
     }
     },
     {
     xtype: 'fileuploadfield',
     id: 'form-shx',
     emptyText: 'Select .shx',
     //fieldLabel: 'Shx',
     name: 'shx',
     buttonText: '',
     buttonCfg: {
     iconCls: 'upload-icon'
     }
     }
     ],
     buttons: [
     {
     text: 'Save',
     handler: function () {
     if (me.form.getForm().isValid()) {
     me.form.getForm().submit({
     url: '/controllers/upload/shape',
     //waitMsg: 'Uploading your shape file...',
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
     });*/
    me.onSubmit = function (form, action) {
        "use strict";
        var result = action.result;
        if (result.success) {
            store.load();
            App.setAlert(App.STATUS_NOTICE, result.message);
            //addShape.form.reset();
        } else {
            Ext.MessageBox.alert('Failure', result.message);
        }
    };
};
