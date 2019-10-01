module.exports = function (grunt) {
    const sass = require('node-sass');
    const tildeImporter = require('node-sass-tilde-importer');
    grunt.loadNpmTasks('grunt-contrib-uglify-es');
    require('load-grunt-tasks')(grunt);

    // Configuration du projet
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),

        //Génération de sprites pour le programme TV
        sprite: {
            all: {
                src: '<%= pkg.baseImg %>/programmes/*.png',
                dest: '<%= pkg.baseDist %>/img/spritesheet.png',
                destCss: '<%= pkg.baseDist %>/css/sprites.css'
            }
        },

        //Symlink pour liens relatifs dans les CSS
        symlink: {
            options: {
                overwrite: false
            },
            expanded: {
                files: [
                    {
                        expand: true,
                        src: ['**/*.*', '!**/programmes/*', '!**/sites/originals/*', '!**/spritesheet.png'],
                        dest: '<%= pkg.baseDist %>/widgets/images',
                        cwd: '<%= pkg.baseDist %>/img'
                    },
                    {
                        expand: true,
                        src: ['spritesheet.png'],
                        dest: '<%= pkg.baseDist %>/widgets/img',
                        cwd: '<%= pkg.baseDist %>/img'
                    },
                ]
            }
        },

        copy: {
            main: {
                files: [
                    {
                        expand: true,
                        src: ['**/*.*', '!**/programmes/*', '!**/sites/originals/*'],
                        dest: '<%= pkg.baseDist %>/img',
                        cwd: '<%= pkg.baseImg %>'
                    },
                    {
                        expand: true,
                        src: ['*'],
                        dest: '<%= pkg.baseDist %>/evenements/font',
                        cwd: '<%= pkg.baseFont %>'
                    },
                    {
                        expand: true,
                        src: ['**/*.*', '!**/programmes/*', '!**/sites/originals/*'],
                        dest: '<%= pkg.baseDist %>/evenements/images',
                        cwd: '<%= pkg.baseImg %>'
                    },
                    {
                        expand: true,
                        src: ['*'],
                        dest: '<%= pkg.baseDist %>/main/img',
                        cwd: '<%= pkg.baseVendor %>/fancybox/dist/img'
                    },
                    {
                        expand: true,
                        src: ['*'],
                        dest: '<%= pkg.baseDist %>/main/fonts',
                        cwd: '<%= pkg.baseVendor %>/font-awesome/fonts'
                    },
                    {
                        expand: true,
                        src: ['*'],
                        dest: '<%= pkg.baseDist %>/fonts',
                        cwd: '<%= pkg.baseVendor %>/font-awesome/fonts'
                    },
                    {
                        expand: true,
                        src: ['*'],
                        dest: '<%= pkg.baseDist %>/espace-perso/evenements/css/font',
                        cwd: '<%= pkg.baseVendor %>/summernote/dist/font'
                    },
                    {
                        expand: true,
                        src: ['*'],
                        dest: '<%= pkg.baseDist %>/main/fonts',
                        cwd: '<%= pkg.baseVendor %>/bootstrap/dist/fonts'
                    },
                    {
                        expand: true,
                        src: ['*'],
                        dest: '<%= pkg.baseDist %>/main/img',
                        cwd: '<%= pkg.baseVendor %>/fancybox/dist/img'
                    },
                    {
                        expand: true,
                        src: ['*'],
                        dest: '<%= pkg.baseDist %>/main/img',
                        cwd: '<%= pkg.baseVendor %>/fancybox/dist/img'
                    },
                ]
            }
        },

        sass: {
            options: {
                implementation: sass,
                sourceMap: true,
                importer: tildeImporter
            },
            dist: {
                files: {
                    './assets/css/style.css': './assets/scss/style.scss',
                    './assets/css/pages/agenda.css': './assets/scss/pages/agenda.scss',
                    './assets/css/pages/en_savoir_plus.css': './assets/scss/pages/en_savoir_plus.scss',
                    './assets/css/pages/event_details.css': './assets/scss/pages/event_details.scss',
                    './assets/css/pages/event_index.css': './assets/scss/pages/event_index.scss',
                    './assets/css/pages/user_details.css': './assets/scss/pages/user_details.scss',
                    './assets/css/pages/user_event_crud.css': './assets/scss/pages/user_event_crud.scss',
                }
            }
        },

        //Concaténation des fichiers CSS
        concat: {
            print: {
                src: [
                    '<%= pkg.baseCss %>/print.css'
                ],
                dest: '<%= pkg.baseDist %>/css/print.css',
                nonull: true
            },
            errors: {
                src: [
                    '<%= pkg.baseVendor %>/font-awesome/css/font-awesome.min.css',
                    '<%= pkg.baseCss %>/erreurs.css'
                ],
                dest: '<%= pkg.baseDist %>/css/erreurs.css',
                nonull: true
            },
            mainCss: {
                src: [
                    '<%= pkg.baseVendor %>/jquery-cookiebar/jquery.cookiebar.css',
                    '<%= pkg.baseVendor %>/font-awesome/css/font-awesome.min.css',
                    '<%= pkg.baseVendor %>/fancybox/dist/css/jquery.fancybox.css',
                    '<%= pkg.baseCss %>/style.css'
                ],
                dest: '<%= pkg.baseDist %>/main/css/style.css',
                nonull: true
            },
            indexCss: {
                src: [
                    '<%= pkg.baseVendor %>/dropdown.js/jquery.dropdown.css',
                    '<%= pkg.baseCss %>/pages/event_index.css'
                ],
                dest: '<%= pkg.baseDist %>/evenements/css/index.css',
                nonull: true
            },
            agendaCss: {
                src: [
                    '<%= pkg.baseVendor %>/bootstrap-datepicker/dist/css/bootstrap-datepicker3.min.css',
                    '<%= pkg.baseVendor %>/bootstrap-select/dist/css/bootstrap-select.min.css',
                    '<%= pkg.baseVendor %>/dropdown.js/jquery.dropdown.css',
                    '<%= pkg.baseCss %>/pages/agenda.css',
                ],
                dest: '<%= pkg.baseDist %>/evenements/css/agenda.css',
                nonull: true
            },
            adminInfoCss: {
                src: [
                    '<%= pkg.baseCss %>/pages/admin_infos.css'
                ],
                dest: '<%= pkg.baseDist %>/admin/info/css/style.css',
                nonull: true
            },
            widgetCss: {
                src: [
                    '<%= pkg.baseDist %>/css/sprites.css'
                ],
                dest: '<%= pkg.baseDist %>/widgets/css/widgets.css',
                nonull: true
            },
            detailEventCss: {
                src: [
                    '<%= pkg.baseCss %>/pages/event_details.css'
                ],
                dest: '<%= pkg.baseDist %>/evenements/css/details.css',
                nonull: true
            },
            searchCss: {
                src: [
                    '<%= pkg.baseVendor %>/dropdown.js/jquery.dropdown.css'
                ],
                dest: '<%= pkg.baseDist %>/search/css/search.css',
                nonull: true
            },
            espacePersoListeCss: {
                src: [
                    '<%= pkg.baseVendor %>/bootstrap-datepicker/dist/css/bootstrap-datepicker3.min.css',
                    '<%= pkg.baseVendor %>/summernote/dist/summernote.css',
                    '<%= pkg.baseCss %>/components/social_login.css'
                ],
                dest: '<%= pkg.baseDist %>/espace-perso/evenements/css/manager.css',
                nonull: true
            },
            espacePersoDetailCss: {
                src: [
                    '<%= pkg.baseVendor %>/morris.js/morris.css',
                    '<%= pkg.baseCss %>/pages/user_details.css'
                ],
                dest: '<%= pkg.baseDist %>/membres/css/detail.css',
                nonull: true
            },
            espacePersoLoginCss: {
                src: [
                    '<%= pkg.baseCss %>/components/social_login.css'
                ],
                dest: '<%= pkg.baseDist %>/espace-perso/login/css/login.css',
                nonull: true
            },
            enSavoirPlusCss: {
                src: [
                    '<%= pkg.baseCss %>/pages/en_savoir_plus.css'
                ],
                dest: '<%= pkg.baseDist %>/plus/css/style.css',
                nonull: true
            }
        },

        //gr des fichiers js / css
        watch: {
            css: {
                files: ['./assets/scss/**'],
                tasks: ['css']
            },
            javascript: {
                files: ['<%= pkg.baseJs %>/**'],
                tasks: ['js']
            }
        },

        //Concaténation et minification des fichiers JS
        uglify: {
            dist: {
                files: {
                    '<%= pkg.baseDist %>/main/js/scripts.min.js': [
                        '<%= pkg.baseVendor %>/jquery/dist/jquery.min.js',
                        '<%= pkg.baseVendor %>/popper.js/dist/umd/popper.min.js',
                        '<%= pkg.baseVendor %>/bootstrap-material-design/dist/js/bootstrap-material-design.min.js',
                        '<%= pkg.baseVendor %>/jquery-cookiebar/jquery.cookiebar.js',
                        '<%= pkg.baseVendor %>/jquery.scrollTo/jquery.scrollTo.min.js',
                        '<%= pkg.baseVendor %>/jquery-unveil/jquery.unveil.js',
                        '<%= pkg.baseVendor %>/fancybox/dist/js/jquery.fancybox.pack.js',
                        '<%= pkg.baseVendor %>/typeahead.js/dist/bloodhound.min.js',
                        '<%= pkg.baseVendor %>/typeahead.js/dist/typeahead.bundle.min.js',
                        '<%= pkg.baseVendor %>/typeahead-addresspicker/dist/typeahead-addresspicker.min.js',
                        '<%= pkg.baseJs %>/overrides.js',
                        '<%= pkg.baseJs %>/socials.js',
                        '<%= pkg.baseJs %>/App.js'
                    ],
                    '<%= pkg.baseDist %>/evenements/js/index.min.js': [
                        '<%= pkg.baseVendor %>/dropdown.js/jquery.dropdown.js',
                        '<%= pkg.baseJs %>/pages/event_index.js'
                    ],
                    '<%= pkg.baseDist %>/js/index.min.js': [
                        '<%= pkg.baseJs %>/pages/index.js'
                    ],
                    '<%= pkg.baseDist %>/espace-perso/login/js/login.min.js': [
                        '<%= pkg.baseJs %>/components/SocialLogin.js'
                    ],
                    '<%= pkg.baseDist %>/espace-perso/profile/js/edit.min.js': [
                        '<%= pkg.baseJs %>/components/SocialLogin.js',
                        '<%= pkg.baseJs %>/components/UserProfile.js'
                    ],
                    '<%= pkg.baseDist %>/membres/js/detail.min.js': [
                        '<%= pkg.baseVendor %>/raphael/raphael.min.js',
                        '<%= pkg.baseVendor %>/morris.js/morris.min.js',
                        '<%= pkg.baseJs %>/components/UserDetails.js'
                    ],
                    '<%= pkg.baseDist %>/espace-perso/evenements/js/liste.min.js': [
                        '<%= pkg.baseJs %>/components/UserEventsList.js'
                    ],
                    '<%= pkg.baseDist %>/espace-perso/evenements/js/manager.min.js': [
                        '<%= pkg.baseVendor %>/bootstrap-datepicker/dist/js/bootstrap-datepicker.js',
                        '<%= pkg.baseVendor %>/bootstrap-datepicker/dist/locales/bootstrap-datepicker.fr.min.js',
                        '<%= pkg.baseVendor %>/summernote/dist/summernote.js',
                        '<%= pkg.baseJs %>/summernote/dist/lang/summernote-fr-FR.js',
                        '<%= pkg.baseJs %>/components/SocialLogin.js',
                        '<%= pkg.baseJs %>/components/UserEventHandler.js'
                    ],
                    '<%= pkg.baseDist %>/search/js/search.min.js': [
                        '<%= pkg.baseVendor %>/dropdown.js/jquery.dropdown.js'
                    ],
                    '<%= pkg.baseDist %>/evenements/js/details.min.js': [
                        '<%= pkg.baseJs %>/components/CommentApp.js',
                        '<%= pkg.baseJs %>/pages/event_details.js'
                    ],
                    '<%= pkg.baseDist %>/widgets/js/widgets.min.js': [
                        '<%= pkg.baseVendor %>/iscroll/build/iscroll.js',
                        '<%= pkg.baseJs %>/components/Widgets.js'
                    ],
                    '<%= pkg.baseDist %>/admin/info/js/login.min.js': [
                        '<%= pkg.baseJs %>/components/SocialLogin.js'
                    ],
                    '<%= pkg.baseDist %>/evenements/js/agenda.min.js': [
                        '<%= pkg.baseVendor %>/bootstrap-datepicker/dist/js/bootstrap-datepicker.js',
                        '<%= pkg.baseVendor %>/bootstrap-datepicker/dist/locales/bootstrap-datepicker.fr.min.js',
                        '<%= pkg.baseVendor %>/bootstrap-select/dist/js/bootstrap-select.min.js',
                        '<%= pkg.baseVendor %>/dropdown.js/jquery.dropdown.js',
                        '<%= pkg.baseVendor %>/bootstrap-select/js/i18n/defaults-fr_FR.js',
                        '<%= pkg.baseJs %>/pages/event_list.js'
                    ],
                }
            }
        },

        //Minification des CSS
        cssmin: {
            options: {
                shorthandCompacting: false,
                roundingPrecision: -1
            },
            target: {
                files: [{
                    expand: true,
                    cwd: '<%= pkg.baseDist %>/evenements/css',
                    src: ['*.css', '!*.min.css', '!*.*.css'],
                    dest: '<%= pkg.baseDist %>/evenements/css',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: '<%= pkg.baseDist %>/css/',
                    src: ['*.css', '!*.min.css', '!*.*.css'],
                    dest: '<%= pkg.baseDist %>/css/',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: '<%= pkg.baseDist %>/admin/info/css',
                    src: ['*.css', '!*.min.css', '!*.*.css'],
                    dest: '<%= pkg.baseDist %>/admin/info/css',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: '<%= pkg.baseDist %>/admin/sites/css',
                    src: ['*.css', '!*.min.css', '!*.*.css'],
                    dest: '<%= pkg.baseDist %>/admin/sites/css',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: '<%= pkg.baseDist %>/espace-perso/evenements/css',
                    src: ['*.css', '!*.min.css', '!*.*.css'],
                    dest: '<%= pkg.baseDist %>/espace-perso/evenements/css',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: '<%= pkg.baseDist %>/espace-perso/login/css',
                    src: ['*.css', '!*.min.css', '!*.*.css'],
                    dest: '<%= pkg.baseDist %>/espace-perso/login/css',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: '<%= pkg.baseDist %>/main/css',
                    src: ['*.css', '!*.min.css', '!*.*.css'],
                    dest: '<%= pkg.baseDist %>/main/css',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: '<%= pkg.baseDist %>/membres/css',
                    src: ['*.css', '!*.min.css', '!*.*.css'],
                    dest: '<%= pkg.baseDist %>/membres/css',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: '<%= pkg.baseDist %>/search/css',
                    src: ['*.css', '!*.min.css', '!*.*.css'],
                    dest: '<%= pkg.baseDist %>/search/css',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: '<%= pkg.baseDist %>/widgets/css',
                    src: ['*.css', '!*.min.css', '!*.*.css'],
                    dest: '<%= pkg.baseDist %>/widgets/css',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: '<%= pkg.baseDist %>/plus/css',
                    src: ['*.css', '!*.min.css', '!*.*.css'],
                    dest: '<%= pkg.baseDist %>/plus/css',
                    ext: '.min.css'
                },
                ]
            }
        },
        clean: {
            js: ['<%= pkg.baseDist %>/**/*.*.js', '<%= pkg.baseDist %>/**/*.js', '!<%= pkg.baseDist %>/**/*.min.js'],
            css: ['<%= pkg.baseDist %>/**/*.*.css', '<%= pkg.baseDist %>/**/*.css', '!<%= pkg.baseDist %>/**/*.min.css', '!<%= pkg.baseDist %>/css/sprites.css'],
            images: ['<%= pkg.baseDist %>/**/*.*.{jpg,jpeg,png,gif}'],
            fonts: ['<%= pkg.baseDist %>/**/*.*.{otf,eot,svg,ttf,woff,woff2}'],
            mappings: ['<%= pkg.baseDist %>/*.json'],
        },
        cacheBust: {
            js: {
                options: {
                    jsonOutput: true,
                    jsonOutputFilename: '<%= pkg.baseDist %>/js_mapping.json',
                    assets: ['<%= pkg.baseDist %>/**/*.min.js']
                },
                src: []
            },
            css: {
                options: {
                    jsonOutput: true,
                    jsonOutputFilename: '<%= pkg.baseDist %>/css_mapping.json',
                    assets: ['<%= pkg.baseDist %>/**/*.min.css']
                },
                src: []
            },
            images: {
                options: {
                    jsonOutput: true,
                    jsonOutputFilename: '<%= pkg.baseDist %>/images_mapping.json',
                    assets: ['<%= pkg.baseDist %>/**/*.{jpg,jpeg,png,gif}', '!<%= pkg.baseDist %>/**/programmes/*.{jpg,jpeg,png,gif}']
                },
                src: ['<%= pkg.baseDist %>/**/*.min.*.css']
            },
            fonts: {
                options: {
                    assets: ['<%= pkg.baseDist %>/**/*.{otf,eot,svg,ttf,woff,woff2}']
                },
                src: ['<%= pkg.baseDist %>/**/*.min.*.css']
            }
        },
        "merge-json": {
            "mapping": {
                files: {
                    "<%= pkg.baseDist %>/mapping.json": ["<%= pkg.baseDist %>/*_mapping.json"],
                }
            }
        }
    });

    // Default task(s).
    grunt.registerTask('convert_mapping', 'Converti le fichier issu de cache pour intégration dans Symfony ', function () {
        var mapping = grunt.file.readJSON(grunt.config.get('pkg.baseDist') + "/mapping.json");
        var config = {
            'parameters': {
                'mapping_assets': mapping
            }
        };
        var YAML = require('yamljs');
        var content = YAML.stringify(config).replace(/public\//g, "");
        grunt.file.write('config/packages/prod/mapping_assets.yaml', content);
    });

    grunt.registerTask('default', ['sprite', 'css', 'js', 'copy', 'symlink', 'cache']);
    grunt.registerTask('css', ['sass', 'concat', 'cssmin']);
    grunt.registerTask('js', ['uglify']);
    grunt.registerTask('cache', ['clean', 'cacheBust', 'merge-json', 'convert_mapping']);
};
