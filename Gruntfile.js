module.exports = function (grunt) {
    "use strict";
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
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
                        'public/apps/widgets/gc2map/css/bootstrap.css',
                        'public/apps/widgets/gc2map/css/bootstrap-alert.css',
                        'public/apps/widgets/gc2map/css/non-responsive.css',
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
                    'public/js/leaflet/leaflet-all.js': [
                        'public/js/leaflet/leaflet.js',
                        'public/js/leaflet/plugins/markercluster/leaflet.markercluster-src.js',
                        'public/js/leaflet/plugins/Leaflet.heat/leaflet-heat.js',
                        'public/js/leaflet/plugins/Leaflet.draw/leaflet.draw.js',
                        'public/js/leaflet/plugins/Leaflet.label/leaflet.label.js',
                        'public/js/leaflet/plugins/Leaflet.print/leaflet.print-src.js'
                    ],
                    // geocloud.js
                    'public/api/v3/js/geocloud.min.js': ['public/api/v3/js/geocloud.js'],
                    // The Viewer
                    'public/apps/viewer/js/build/all.min.js': [
                        'public/js/jquery/1.10.0/jquery.min.js',
                        'public//js/bootstrap3/js/bootstrap.min.js',
                        'public/js/hogan/hogan-2.0.0.js',
                        'public/js/div/jRespond.js',
                        'public/js/common.js',
                        'public/js/MultiLevelPushMenu/jquery.multilevelpushmenu.js',
                        'public/apps/viewer/js/templates.js',
                        'public/apps/viewer/js/viewer.js',
                        'public/js/leaflet/leaflet-all.js'
                    ],
                    //store
                    'public/js/build/store/all.min.js': [
                        'public/js/jquery/1.10.0/jquery.min.js',
                        'public/js/msg.js',
                        'public/js/store.js',
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
                        'public/js/colorfield.js',
                        'public/js/httpauthform.js',
                        'public/js/apikeyform.js',
                        'public/js/plupload/js/moxie.min.js',
                        'public/js/plupload/js/plupload.min.js',
                        'public/js/plupload/js/jquery.plupload.queue/jquery.plupload.queue.min.js'
                    ],
                    //Editor
                    'public/js/build/editor/all.min.js': [
                        'public/js/jquery/1.10.0/jquery.min.js',
                        'public/js/msg.js',
                        'public/js/GeoExt/script/GeoExt.js',
                        'public/api/v1/js/api.js',
                        'public/api/v3/js/geocloud.js',
                        'public/js/wfseditor.js',
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
                        'public/apps/widgets/gc2map/js/templates.js'
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

    grunt.registerTask('default', ['npm-install', 'cssmin', 'jshint', 'hogan', 'preprocess:debug', 'cacheBust']);
    grunt.registerTask('production', ['gitreset', 'gitpull', 'npm-install', 'cssmin', 'jshint', 'hogan', 'uglify', 'processhtml', 'preprocess:production', 'cacheBust']);
    grunt.registerTask('migration', ['shell:migration']);
};




