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
                    // The Viewer
                    'public/css/build/styles.min.css': [
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
                    // geocloud.js
                    'public/api/v3/js/geocloud.min.js': ['public/api/v3/js/geocloud.js'],
                    // The Viewer
                    'public/apps/viewer/js/build/all.min.js': [
                        'public/js/jquery/1.10.0/jquery.min.js',
                        'public/js/bootstrap3/js/bootstrap.min.js',
                        'public/js/hogan/hogan-2.0.0.js',
                        'public/js/div/jRespond.js',
                        'public/js/common.js',
                        'public/js/MultiLevelPushMenu/jquery.multilevelpushmenu.js',
                        'public/apps/viewer/js/templates.js',
                        'public/apps/viewer/js/viewer.js',
                        'public/js/leaflet/leaflet-all.js'
                    ],
                    //admin
                    'public/js/admin/build/all.min.js': [
                        'public/js/jquery/1.10.0/jquery.min.js',
                        'public/js/msg.js',
                        'public/js/admin.js',
                        'public/js/edittablestructure.js',
                        'public/js/cartomobilesetup.js',
                        'public/js/elasticsearchmapping.js',
                        'public/js/editwmsclass.js',
                        'public/js/editwmslayer.js',
                        'public/js/edittilelayer.js',
                        'public/js/classwizards.js',
                        'public/js/addshapeform.js',
                        'public/js/addbitmapform.js',
                        'public/js/addrasterform.js',
                        'public/js/addfromscratch.js',
                        'public/js/addviewform.js',
                        'public/js/addosmform.js',
                        'public/js/addqgisform.js',
                        'public/js/colorfield.js',
                        'public/js/httpauthform.js',
                        'public/js/apikeyform.js',
                        'public/js/plupload/js/moxie.min.js',
                        'public/js/plupload/js/plupload.min.js',
                        'public/js/plupload/js/jquery.plupload.queue/jquery.plupload.queue.min.js',
                        'public/js/GeoExt/script/GeoExt.js',
                        'public/api/v1/js/api.js',
                        'public/api/v3/js/geocloud.js',
                        'public/js/attributeform.js',
                        'public/js/filterfield.js',
                        'public/js/filterbuilder.js',
                        'public/js/comparisoncomboBox.js',
                        'public/js/openlayers/proj4js-combined.js'
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
            options: {
                encoding: 'utf8',
                algorithm: 'md5',
                length: 16,
                rename: false,
                enableUrlFragmentHint: true,
                baseDir: "public/",
                ignorePatterns: ['php']
            },
            assets: {
                files: [{
                    src: [
                        'public/store.php',
                        'public/editor.php',
                        'public/apps/viewer/index.html',
                        'public/apps/widgets/gc2map/index.html',
                        'public/api/v3/js/async_loader.js',
                        'public/api/v3/js/geocloud.js',
                        'public/apps/widgets/gc2map/js/gc2map.js'
                    ]
                }]
            }
        },
        processhtml: {
            dist: {
                files: {
                    'public/store.php': ['public/store.php'],
                    'public/editor.php': ['public/editor.php'],
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
            migration: {
                command: 'cd /var/www/geocloud2/app/conf/migration/ && ./run'
            },
            move_bitmaps: {
                command: 'cd /var/www/geocloud2/app/conf/migration/ && ./move_bitmaps'
            },
            chown: {
                command: 'chown www-data:www-data -R /var/www/geocloud2/app/wms/files'
            },
            composer: {
                command: 'cd app && php composer.phar install'
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
    grunt.loadNpmTasks('grunt-npm-install');
    grunt.loadNpmTasks('grunt-contrib-less');

    grunt.registerTask('default', ['npm-install', 'less', 'cssmin', 'jshint', 'hogan', 'preprocess:debug', 'cacheBust']);
    grunt.registerTask('production', ['gitreset', 'gitpull', 'npm-install', 'less', 'cssmin', 'hogan', 'uglify', 'processhtml', 'preprocess:production', 'cacheBust', 'shell:move_bitmaps', 'shell:chown', 'shell:composer']);
    grunt.registerTask('migration', ['shell:migration']);
};




