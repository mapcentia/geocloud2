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
                es3: true,
                devel: false,
                eqnull: true
            },
            all: ['public/js/*.js']
        },
        uglify: {
            publish: {
                files: {
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
                        'public/js/leaflet/leaflet.js'
                    ],
                    //Editor
                    'public/js/build/editor/all.min.js': [
                        'public/js/wfseditor.js',
                        'public/js/attributeform.js',
                        'public/js/filterfield.js',
                        'public/js/filterbuilder.js',
                        'public/js/comparisoncomboBox.js',
                        'public/js/openlayers/proj4js-combined.js'
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
                    "public/apps/widgets/gc2map/js/templates.js": ["public/apps/widgets/gc2map/templates/body.tmpl"]
                }
            }
        },
        processhtml: {
            dist: {
                files: {
                    //'public/store.php': ['public/store.php'],
                    'public/editor.php': ['public/editor.php']
                    //'public/apps/viewer/index.html': ['public/apps/viewer/index.html']
                }
            }
        }
    });
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-contrib-jshint');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-processhtml');
    grunt.loadNpmTasks('grunt-templates-hogan');
    grunt.registerTask('default', ['cssmin', 'jshint', 'hogan', 'uglify']);
    grunt.registerTask('production', ['processhtml']);
};


