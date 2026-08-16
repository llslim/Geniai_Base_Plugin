/**
 * Grunt configuration for local_aacuracore.
 *
 * This builds the AMD modules in amd/src/ into optimized single-file
 * modules in amd/build/, following Moodle's shifter/requirejs convention.
 * The output format matches Moodle's AMD module builder so the plugin can
 * be loaded by Moodle's requirejs at runtime.
 *
 * @package   local_aacuracore
 * @copyright 2026 AAC-RERC Chatbot Team
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

module.exports = function(grunt) {
    'use strict';

    const path = require('path');
    const amdSrc = 'amd/src';
    const amdBuild = 'amd/build';

    grunt.loadNpmTasks('grunt-contrib-uglify');

    // Build the files map: amd/src/*.js -> amd/build/*.min.js.
    const files = {};
    grunt.file.expand({cwd: amdSrc}, '**/*.js').forEach(function(file) {
        const dest = path.join(amdBuild, path.basename(file, '.js') + '.min.js');
        files[dest] = path.join(amdSrc, file);
    });

    grunt.initConfig({
        uglify: {
            amd: {
                options: {
                    compress: false,
                    mangle: false,
                    beautify: false
                },
                files: files
            }
        },
        watch: {
            amd: {
                files: [amdSrc + '/**/*.js'],
                tasks: ['uglify']
            }
        }
    });

    grunt.registerTask('build', ['uglify']);
    grunt.registerTask('default', ['build']);
};