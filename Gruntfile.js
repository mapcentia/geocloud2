module.exports = function (grunt) {
    "use strict";
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        less: {
            publish: {
                options: {
                    compress: false,
                    optimization: 2
                },
                files: {
                    "public/apps/widgets/gc2map/css/styles.css": "public/apps/widgets/gc2map/less/styles.less"
                }
            }
        },
        cssmin: {
            build: {
                files: {
                    // The Viewer
                    'public/apps/viewer/css/build/all.min.css': [
                        'public/js/bootstrap3/css/bootstrap.min.css',
                        'public/js/MultiLevelPushMenu/jquery.multilevelpushmenu.css',
                        'public/apps/viewer/css/styles.css'
                    ],
                    // Admin
                    'public/css/build/styles.min.css': [
                        //'public/js/ext/examples/ux/superboxselect/superboxselect.css',
                        //'public/js/ext/resources/css/ext-all-notheme.css',
                        //'public/js/ext/resources/css/xtheme-dark.css',
                        //'public/js/ext/examples/ux/gridfilters/css/GridFilters.css',
                        //'public/js/ext/examples/ux/gridfilters/css/RangeMenu.css',
                        'public/js/bootstrap/css/bootstrap.icons.min.css',
                        'public/css/jquery.plupload.queue.css',
                        'public/css/styles.css'
                    ],
                    // The widget
                    'public/apps/widgets/gc2map/css/build/all.min.css': [
                        'public/apps/widgets/gc2map/css/styles.css',
                        'public/js/leaflet/plugins/markercluster/MarkerCluster.css',
                        'public/js/leaflet/plugins/markercluster/MarkerCluster.Default.css'
                    ]
                }
            }
        },
        jshint: {
            options: {
                funcscope: true,
                shadow: true,
                evil: true,
                validthis: true,
                asi: true,
                newcap: false,
                notypeof: false,
                eqeqeq: false,
                loopfunc: true,
                devel: false,
                eqnull: true
            },
            all: ['public/js/*.js']
        },
        uglify: {
            //adhoc: {files: {'public/js/openlayers/OpenLayers.js': ['public/js/openlayers/OpenLayers.js']}},
            options: {
                mangle: false,
                compress: false
            },
            publish: {
                files: {
                    'public/js/leaflet/leaflet-plugins-all.js': [
                        'public/js/leaflet/plugins/markercluster/leaflet.markercluster-src.js',
                        'public/js/leaflet/plugins/Leaflet.heat/leaflet-heat.js',
                        'public/js/leaflet/plugins/Leaflet.draw/leaflet.draw.js',
                        'public/js/leaflet/plugins/Leaflet.label/leaflet.label.js',
                        'public/js/leaflet/plugins/Leaflet.print/leaflet.print-src.js',
                        'public/js/leaflet/plugins/Leaflet.Editable/Leaflet.Editable.js',
                        'public/js/leaflet/plugins/Leaflet.GraphicScale/Leaflet.GraphicScale.min.js',
                        'public/js/leaflet/plugins/Leaflet.Locate/Leaflet.Locate.js',
                        'public/js/leaflet/plugins/Leaflet.Toolbar/leaflet.toolbar-src.js'
                    ],
                    'public/js/leaflet/cartodb-all.js': [
                        'public/js/cartodbjs/cartodb.uncompressed.js',
                        'public/js/leaflet/leaflet-plugins-all.js'
                    ],
                    'public/js/leaflet/leaflet-all.js': [
                        'public/js/leaflet/leaflet-0.7.7-src.js',
                        'public/js/leaflet/leaflet-plugins-all.js'
                    ],
                    'public/js/leaflet1/leaflet-1.2.0-all.js': [
                        'public/js/leaflet1/leaflet.js',
                        'public/js/leaflet1/plugins/Leaflet.Draw/leaflet.draw.js',
                        'public/js/leaflet1/plugins/Leaflet.Editable/src/Leaflet.Editable.js',
                        'public/js/leaflet1/plugins/Leaflet.GraphicScale/Leaflet.GraphicScale.min.js',
                        'public/js/leaflet1/plugins/Leaflet.Locate/dist/L.Control.Locate.min.js',
                        'public/js/leaflet1/plugins/Leaflet.Toolbar/dist/leaflet.toolbar.min.js'
                    ],
                    // geocloud.js
                    'public/api/v3/js/geocloud.min.js': ['public/api/v3/js/geocloud.js'],
                    // The Viewer
                    'public/apps/viewer/js/build/all.min.js': [
                        'public/js/jquery/1.10.0/jquery.min.js',
                        'public/js/bootstrap3/js/bootstrap.min.js',
                        'public/js/hogan/hogan-2.0.0.js',
                        'public/js/div/jRespond.js',
                        'public/js/admin/common.js',
                        'public/js/MultiLevelPushMenu/jquery.multilevelpushmenu.js',
                        'public/apps/viewer/js/templates.js',
                        'public/apps/viewer/js/viewer.js',
                        'public/js/leaflet/leaflet-all.js'
                    ],
                    //admin
                    'public/js/admin/build/all.min.js': [
                        'public/js/canvasResize/binaryajax.js',
                        'public/js/canvasResize/exif.js',
                        'public/js/canvasResize/canvasResize.js',

                        'public/js/ext/adapter/ext/ext-base-debug.js',
                        'public/js/ext/ext-all-debug.js',
                        'public/js/ext/examples/ux/fileuploadfield/FileUploadField.js',
                        'public/js/ext/examples/ux/Spinner.js',
                        'public/js/ext/examples/ux/SpinnerField.js',
                        'public/js/ext/examples/ux/CheckColumn.js',
                        'public/js/ext/examples/ux/gridfilters/menu/RangeMenu.js',
                        'public/js/ext/examples/ux/gridfilters/menu/ListMenu.js',
                        'public/js/ext/examples/ux/superboxselect/SuperBoxSelect.js',
                        'public/js/ext/examples/ux/gridfilters/GridFilters.js',
                        'public/js/ext/examples/ux/gridfilters/filter/Filter.js',
                        'public/js/ext/examples/ux/gridfilters/filter/StringFilter.js',

                        'public/js/jquery/1.10.0/jquery.min.js',
                        'public/js/openlayers/proj4js-combined.js',
                        'public/js/GeoExt/script/GeoExt.js',
                        'public/js/plupload/js/moxie.min.js',
                        'public/js/plupload/js/plupload.min.js',
                        'public/js/plupload/js/jquery.plupload.queue/jquery.plupload.queue.min.js',

                        'public/js/admin/msg.js',
                        'public/js/admin/admin.js',
                        'public/js/admin/edittablestructure.js',
                        'public/js/admin/elasticsearchmapping.js',
                        'public/js/admin/editwmsclass.js',
                        'public/js/admin/editwmslayer.js',
                        'public/js/admin/edittilelayer.js',
                        'public/js/admin/classwizards.js',
                        'public/js/admin/addshapeform.js',
                        'public/js/admin/addbitmapform.js',
                        'public/js/admin/addrasterform.js',
                        'public/js/admin/addfromscratch.js',
                        'public/js/admin/addviewform.js',
                        'public/js/admin/addosmform.js',
                        'public/js/admin/addqgisform.js',
                        'public/js/admin/colorfield.js',
                        'public/js/admin/httpauthform.js',
                        'public/js/admin/apikeyform.js',
                        'public/js/admin/attributeform.js',
                        'public/js/admin/filterfield.js',
                        'public/js/admin/filterbuilder.js',
                        'public/js/admin/comparisoncomboBox.js',
                        'public/js/openlayers/defs/EPSG3857.js'
                    ],

                    // The widget
                    'public/apps/widgets/gc2map/js/build/all.min.js': [
                        'public/js/leaflet/leaflet-all.js',
                        'public/js/openlayers/proj4js-combined.js',
                        'public/js/bootstrap3/js/bootstrap.min.js',
                        'public/js/hogan/hogan-2.0.0.js',
                        'public/apps/widgets/gc2map/js/bootstrap-alert.js',
                        'public/api/v3/js/geocloud.js',
                        'public/apps/widgets/gc2map/js/main.js',
                        'public/apps/widgets/gc2map/js/templates.js',
                        'public/apps/widgets/gc2map/config/config.js'
                    ]
                }
            },
            devel: {
                files: {
                    // The widget
                    'public/apps/widgets/gc2map/js/build/all.min.js': [
                        'public/js/leaflet/leaflet-all.js',
                        'public/js/openlayers/proj4js-combined.js',
                        'public/js/bootstrap3/js/bootstrap.min.js',
                        'public/js/hogan/hogan-2.0.0.js',
                        'public/apps/widgets/gc2map/js/bootstrap-alert.js',
                        'public/api/v3/js/geocloud.js',
                        'public/apps/widgets/gc2map/js/main.js',
                        'public/apps/widgets/gc2map/js/templates.js',
                        'public/apps/widgets/gc2map/config/config.js'
                    ]
                }
            }
        },
        hogan: {
            publish: {
                options: {
                    defaultName: function (filename) {
                        return filename.split('/').pop();
                    }
                },
                files: {
                    "public/apps/viewer/js/templates.js": ["public/apps/viewer/templates/body.tmpl"],
                    "public/apps/widgets/gc2map/js/templates.js": [
                        "public/apps/widgets/gc2map/templates/body.tmpl",
                        "public/apps/widgets/gc2map/templates/body2.tmpl"
                    ]
                }
            }
        },
        cacheBust: {
            taskName: {
                options: {
                    assets: ['js/admin/build/*', 'api/v1/js/*', 'api/v3/js/*', 'css/build/*', '/js/OpenLayers-2.12/OpenLayers.gc2.js'],
                    encoding: 'utf8',
                    algorithm: 'md5',
                    length: 16,
                    rename: false,
                    enableUrlFragmentHint: true,
                    baseDir: "public/",
                    ignorePatterns: ['php']
                },
                src: [
                    'public/admin.php',
                    'public/apps/viewer/index.html',
                    'public/apps/widgets/gc2map/index.html',
                    'public/api/v3/js/async_loader.js',
                    'public/api/v3/js/geocloud.js',
                    'public/apps/widgets/gc2map/js/gc2map.js'
                ]
            }
        },
        processhtml: {
            dist: {
                files: {
                    'public/admin.php': ['public/admin.php'],
                    'public/apps/viewer/index.html': ['public/apps/viewer/index.html']
                }
            }
        },
        preprocess: {
            debug: {
                options: {
                    context: {
                        DEBUG: true
                    }
                },
                files: {
                    "public/apps/widgets/gc2map/js/gc2map.js": "public/apps/widgets/gc2map/js/gc2map.preprocessed.js",
                    "public/api/v3/js/async_loader.js": "public/api/v3/js/async_loader.preprocessed.js"
                }
            },
            production: {
                options: {
                    context: {
                        DEBUG: false
                    }
                },
                files: {
                    "public/apps/widgets/gc2map/js/gc2map.js": "public/apps/widgets/gc2map/js/gc2map.preprocessed.js",
                    "public/api/v3/js/async_loader.js": "public/api/v3/js/async_loader.preprocessed.js"
                }
            }
        },
        gitpull: {
            production: {
                options: {}
            }
        },
        gitreset: {
            production: {
                options: {
                    mode: 'hard'
                }
            }
        },
        shell: {
            chown: {
                command: 'chown www-data:www-data -R /var/www/geocloud2/app/wms/files'
            },
            composer: {
                command: 'cd app && php composer.phar install'
            },
            swagger_v2: {
                command: 'cd app && ./vendor/zircote/swagger-php/bin/openapi -o ../public/swagger/v2/api.json api/v2/'
            },
            swagger_v3: {
                command: 'cd app && ./vendor/zircote/swagger-php/bin/openapi -o ../public/swagger/v3/api.json api/v3/'
            },
            buildDocs: {
                command: 'sphinx-build ./docs/da ./docs/html'
            }
        }
    });
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-contrib-jshint');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-processhtml');
    grunt.loadNpmTasks('grunt-templates-hogan');
    grunt.loadNpmTasks('grunt-cache-bust');
    grunt.loadNpmTasks('grunt-preprocess');
    grunt.loadNpmTasks('grunt-git');
    grunt.loadNpmTasks('grunt-shell');
    grunt.loadNpmTasks('grunt-contrib-less');

    grunt.registerTask('default', ['less', 'cssmin', 'jshint', 'hogan', 'preprocess:debug', 'cacheBust', 'shell:composer']);
    grunt.registerTask('production', ['less', 'cssmin', 'hogan', 'uglify', 'processhtml', 'preprocess:production', 'cacheBust', 'shell:chown', 'shell:composer']);
};
