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
        html: "<div id='shape_uploader'>You need Flash or a modern browser, which supports HTML5</div>",
        afterRender: function () {
            var arr = [], ext = ["shp", "tab", "geojson", "gml", "kml", "mif"], geoType, encoding, srs;
            $("#shape_uploader").pluploadQueue({
                // General settings
                runtimes: 'html5, flash',
                url: '/controllers/upload/vector',
                max_file_size: '200mb',
                chunk_size: '1mb',
                unique_names: true,
                urlstream_upload: true,
                init: {
                    UploadComplete: function (up, files) {
                        Ext.each(arr, function (e) {
                            geoType = (e.split(".").reverse()[0].toLowerCase() === "shp") ? "PROMOTE_TO_MULTI" : geoType;
                            $.ajax({
                                url: '/controllers/upload/processvector',
                                data: "srid=" + srs + "&file=" + e + "&name=" + e.split(".")[0] + "&type=" + geoType + "&encoding=" + encoding,
                                dataType: 'json',
                                type: 'GET',
                                success: function (response, textStatus, http) {
                                    if (response.success) {
                                        App.setAlert(App.STATUS_NOTICE, response.message);
                                        writeFiles();
                                        document.getElementById("wfseditor").contentWindow.window.reLoadTree();
                                        store.load();
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
                                if (item.name.split(".").reverse()[0].toLowerCase() === e) {
                                    arr.push(item.name);
                                }
                            });
                        });
                    },
                    BeforeUpload: function (up, file) {
                        geoType = Ext.getCmp('geotype').getValue();
                        encoding = Ext.getCmp('encoding').getValue();
                        srs = Ext.getCmp('srs').getValue();
                        up.settings.multipart_params = {
                            name: file.name
                        };
                    }
                },
                // Flash settings
                flash_swf_url: '/js/plupload/js/Moxie.swf'
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
            },
            {
                text: 'Encoding:'
            },
            {
                width: 150,
                xtype: 'combo',
                mode: 'local',
                triggerAction: 'all',
                forceSelection: true,
                editable: false,
                id: 'encoding',
                displayField: 'name',
                valueField: 'value',
                value: 'LATIN1',
                allowBlank: false,
                store: new Ext.data.JsonStore({
                    fields: ['name', 'value'],
                    data: [
                        {name: "BIG5", value: "BIG5"},
                        {name: "EUC_CN", value: "EUC_CN"},
                        {name: "EUC_JP", value: "EUC_JP"},
                        {name: "EUC_JIS_2004", value: "EUC_JIS_2004"},
                        {name: "EUC_KR", value: "EUC_KR"},
                        {name: "EUC_TW", value: "EUC_TW"},
                        {name: "GB18030", value: "GB18030"},
                        {name: "GBK", value: "GBK"},
                        {name: "ISO_8859_5", value: "ISO_8859_5"},
                        {name: "ISO_8859_6", value: "ISO_8859_6"},
                        {name: "ISO_8859_7", value: "ISO_8859_7"},
                        {name: "ISO_8859_8", value: "ISO_8859_8"},
                        {name: "JOHAB", value: "JOHAB"},
                        {name: "KOI8R", value: "KOI8R"},
                        {name: "KOI8U", value: "KOI8U"},
                        {name: "LATIN1", value: "LATIN1"},
                        {name: "LATIN2", value: "LATIN2"},
                        {name: "LATIN3", value: "LATIN3"},
                        {name: "LATIN4", value: "LATIN4"},
                        {name: "LATIN5", value: "LATIN5"},
                        {name: "LATIN6", value: "LATIN6"},
                        {name: "LATIN7", value: "LATIN7"},
                        {name: "LATIN8", value: "LATIN8"},
                        {name: "LATIN9", value: "LATIN9"},
                        {name: "LATIN10", value: "LATIN10"},
                        {name: "MULE_INTERNAL", value: "MULE_INTERNAL"},
                        {name: "SJIS", value: "SJIS"},
                        {name: "SHIFT_JIS_2004", value: "SHIFT_JIS_2004"},
                        {name: "SQL_ASCII", value: "SQL_ASCII"},
                        {name: "UHC", value: "UHC"},
                        {name: "UTF8", value: "UTF8"},
                        {name: "WIN866", value: "WIN866"},
                        {name: "WIN874", value: "WIN874"},
                        {name: "WIN1250", value: "WIN1250"},
                        {name: "WIN1251", value: "WIN1251"},
                        {name: "WIN1252", value: "WIN1252"},
                        {name: "WIN1253", value: "WIN1253"},
                        {name: "WIN1254", value: "WIN1254"},
                        {name: "WIN1255", value: "WIN1255"},
                        {name: "WIN1256", value: "WIN1256"},
                        {name: "WIN1257", value: "WIN1257"},
                        {name: "WIN1258", value: "WIN1258"}
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
        } else {
            Ext.MessageBox.alert('Failure', result.message);
        }
    };
};
