/*global Ext:false */
/*global $:false */
/*global App:false */
/*global document:false */
/*global writeFiles:false */
/*global store:false */
/*global addRasterFile:false */
Ext.namespace('addRasterFile');
addRasterFile.init = function () {
    "use strict";
    Ext.QuickTips.init();
    var me = this;
    me.form = new Ext.Panel({
        region: 'center',
        id: "addform",
        frame: false,
        bodyStyle: 'padding: 0',
        border: false,
        autoHeight: true,
        html: "<div id='shape_uploader'>You need Flash or a modern browser, which supports HTML5</div>",
        afterRender: function () {
            var arr = [], ext = ["asc", "tif", "tiff", "gen"], srs, flag = false;
            $("#shape_uploader").pluploadQueue({
                runtimes: 'html5, flash',
                url: '/controllers/upload/raster',
                max_file_size: '2000mb',
                chunk_size: '1mb',
                unique_names: true,
                urlstream_upload: true,
                init: {
                    UploadComplete: function (up, files) {
                        Ext.each(arr, function (e) {
                            flag = true;
                            $.ajax({
                                url: '/controllers/upload/processraster',
                                data: "srid=" + srs + "&file=" + e + "&name=" + e.split(".")[0],
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
                        if (!flag) {
                            Ext.MessageBox.alert('Failure', "No files you uploaded seems to be recognized as a valid raster format.");
                        }
                    },
                    FilesAdded: function (up, files) {
                        Ext.each(files, function (item) {
                            Ext.each(ext, function (e) {
                                if (item.name.split(".").reverse()[0].toLowerCase() === e) {
                                    arr.push(item.name);
                                }
                            });
                        });
                    },
                    BeforeUpload: function (up, file) {
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

            }
        ]
    });
    me.onSubmit = function (form, action) {
        var result = action.result;
        if (result.success) {
            store.load();
            App.setAlert(App.STATUS_NOTICE, result.message);
        } else {
            Ext.MessageBox.alert('Failure', result.message);
        }
    };
};