/*
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

/*global Ext:false */
/*global $:false */
/*global App:false */
/*global document:false */
/*global writeFiles:false */
/*global store:false */
/*global addQgis:false */
/*global window:false */

"use strict";

/**
 *
 */
Ext.namespace('addQgis');
addQgis.init = function () {
    Ext.QuickTips.init();
    var me = this;
    me.form = new Ext.Panel({
        region: 'center',
        id: "addform",
        frame: false,
        bodyStyle: 'padding: 0',
        border: false,
        autoHeight: true,
        html: "<div id='shape_uploader'>" + __("You need Flash or a modern browser, which supports HTML5") + "</div>",
        afterRender: function () {
            var arr = [], ext = ["qgs"], flag = false, createWms, createComp;
            $("#shape_uploader").pluploadQueue({
                runtimes: 'html5',
                url: '/controllers/upload/qgis',
                max_file_size: '2000mb',
                chunk_size: '1mb',
                unique_names: true,
                urlstream_upload: true,
                init: {
                    UploadComplete: function (up, files) {
                        // *****
                        var count = 0, errors = [], layers = [], i, message;
                        (function iter() {
                            var e = arr[count], strings = [];
                            if (arr.length === count) {
                                if (flag) {
                                    App.setAlert(App.STATUS_NOTICE, __("All files processed"));
                                    reLoadTree();
                                    writeFiles();
                                    writeMapCacheFile();
                                    if (errors.length > 0) {
                                        for (i = 0; i < errors.length; i = i + 1) {
                                            strings.push(errors[i]);
                                        }
                                        message = "<p>" + __("Some file processing resulted in errors or warnings.") + "</p><br/><textarea rows=7' cols='74'>" + strings.join("\n") + "</textarea>";
                                        Ext.MessageBox.show({
                                            title: __('Failure'),
                                            msg: message,
                                            buttons: Ext.MessageBox.OK,
                                            width: 500,
                                            height: 400
                                        });
                                    } else {
                                        Ext.each(layers, function (v1) {
                                            Ext.each(v1, function (v2) {
                                                strings.push(v2);
                                            });
                                        });
                                        message = "<p>" + __("These GC2 layers now use the QGIS styles from the project file(s)") + "</p><br/><textarea rows=7' cols='74'>" + strings.join("\n") + "</textarea>";
                                        Ext.MessageBox.show({
                                            title: __('Success'),
                                            msg: message,
                                            buttons: Ext.MessageBox.OK,
                                            width: 500,
                                            height: 400
                                        });
                                    }
                                }
                                spinner(false);
                                return;
                            } else {
                                spinner(true, __("processing " + e.split(".")[0]));
                                flag = true;
                                $.ajax({
                                    url: '/controllers/upload/processqgis',
                                    data: "file=" + e + "&createWms=" + createWms + "&createComp=" + createComp,
                                    dataType: 'json',
                                    type: 'GET',
                                    success: function (response) {
                                        count = count + 1;
                                        if (!response.success) {
                                            errors.push(__(response.message));
                                        }
                                        layers.push(response.layers);
                                        iter();
                                    },
                                    error: function (response) {
                                        count = count + 1;
                                        errors.push(__(Ext.decode(response.responseText).message));
                                        iter();
                                    }
                                });
                            }
                        }());
                        if (!flag) {
                            Ext.MessageBox.alert(__('Failure'), __("No files you uploaded seems to be recognized as a valid QGIS project file."));
                        }
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
                        createWms = Ext.getCmp('createWms').getValue();
                        createComp = Ext.getCmp('createComp').getValue();


                        up.settings.multipart_params = {
                            name: file.name
                        };
                    }
                }
            });
            window.setTimeout(function () {
                var e = $(".plupload_droptext");
                window.setTimeout(function () {
                    e.fadeOut(500).fadeIn(500);
                }, 500);
                window.setTimeout(function () {
                    e.html(__("QGIS project format") + ": " + ".qgs" + "<br><br>" + __("Import styles of WFS/PostGIS layers from QGIS project files."));
                }, 1000);
            }, 200);
        },
        tbar: [
            { // Add an hidden input field to get the height right
                width: 10,
                xtype: 'textfield',
                id: '',
                value: '',
                style: {visibility: 'hidden'}

            },
            ' ',
            __('Create WMS layers') + __("This will create a new WMS layer in GC2 for every WMS layer in the GGIS project. It is like importing WMS layers from QGIS. The new layers will be placed in the current schema.", true),
            {
                xtype: 'checkbox',
                id: 'createWms'
            },
            ' ',
            __('Create composite layer') + __("This will create one new composite layer with all layers in the GGIS project. Blending mode from QGIS will be used. This is great for creating a base layer. The new layer will take name from the qgs-file and be placed in the current schema", true),
            {
                xtype: 'checkbox',
                id: 'createComp'
            }
        ]
    });
    me.onSubmit = function (form, action) {
        var result = action.result;
        if (result.success) {
            store.load();
            App.setAlert(App.STATUS_NOTICE, __(result.message));
        } else {
            Ext.MessageBox.alert(__('Failure'), __(result.message));
        }
    };
};