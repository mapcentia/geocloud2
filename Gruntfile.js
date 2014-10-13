module.exports = function (grunt) {
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
            my_target: {
                files: {
                    // geoclaoud.js
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
                    ]
                }
            }
        },
        processhtml: {
            dist: {
                files: {
                    //'public/store.php': ['public/store.php'],
                    //'public/editor.php': ['public/editor.php'],
                    'public/apps/viewer/index.html': ['public/apps/viewer/index.html']
                }
            }
        }
    });
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-contrib-jshint');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-processhtml');
    grunt.registerTask('default', ['cssmin', 'jshint', 'uglify']);
    grunt.registerTask('production', ['processhtml']);
};


