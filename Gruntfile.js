module.exports = function (grunt) {
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        concat: {
            options: {
                stripBanners: true,
                banner: '/*! <%= pkg.name %> - v<%= pkg.version %> - ' +
                    '<%= grunt.template.today("yyyy-mm-dd") %> */'
            },
            css: {
                src: [
                    'public/css/styles.css'
                ],
                dest: 'public/css/build/all.css'
            }
        },
        cssmin: {
            build: {
                expand: true,
                cwd: 'public/css/build',
                src: ['all.css'],
                dest: 'public/css/build',
                ext: '.min.css'
            }
        },
        processhtml: {
            dist: {
                files: {
                    'public/store.php': ['public/store.php'],
                    'public/editor.php': ['public/editor.php']
                }
            }
        }
    });
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-processhtml');
    grunt.registerTask('default', ['concat', 'cssmin']);
    grunt.registerTask('production', ['processhtml']);
};

