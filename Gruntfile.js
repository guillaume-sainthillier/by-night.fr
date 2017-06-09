module.exports = function (grunt) {
    require('load-grunt-tasks')(grunt);
    var YAML = require('yamljs');
    
    // Configuration du projet
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),

        //Génération de sprites pour le programme TV
        sprite: {
            all: {
                src: '<%= pkg.baseCss %>/img/programmes/*.png',
                dest: '<%= pkg.baseCss %>/img/spritesheet.png',
                destCss: '<%= pkg.baseCss %>/sprites.css'
            }
        },

        //Symlink pour liens relatifs dans les CSS
        symlink: {
            options: {
                overwrite: true
            },
            expanded: {
                files: [
                    {
                        expand: true,
                        src: ['*'],
                        dest: '<%= pkg.baseDist %>/evenements/font',
                        cwd: 'web/fonts'
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
                        dest: '<%= pkg.baseDist %>/main/fonts',
                        cwd: '<%= pkg.baseVendor %>/bootstrap/dist/fonts'
                    },
                    {
                        expand: true,
                        src: ['*'],
                        dest: '<%= pkg.baseDist %>/evenements/images',
                        cwd: 'web/img'
                    },
                    {
                        expand: true,
                        src: ['*'],
                        dest: '<%= pkg.baseDist %>/widgets/images',
                        cwd: 'web/img'
                    },
                    {
                        expand: true,
                        src: ['*'],
                        dest: '<%= pkg.baseDist %>/main/img/icons',
                        cwd: 'web/img/icons'
                    },
                    {
                        expand: true,
                        src: ['*'],
                        dest: '<%= pkg.baseDist %>/espace-perso/evenements/css/font',
                        cwd: '<%= pkg.baseVendor %>/summernote/dist/font'
                    }
                ]
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
                    '<%= pkg.baseVendor %>/bootstrap/dist/css/bootstrap.min.css',
                    '<%= pkg.baseVendor %>/font-awesome/css/font-awesome.min.css',
                    '<%= pkg.baseCss %>/erreurs.css'
                ],
                dest: '<%= pkg.baseDist %>/css/erreurs.css',
                nonull: true
            },
            mainCss: {
                src: [
                    '<%= pkg.baseVendor %>/jquery.cookiebar/jquery.cookiebar.css',
                    '<%= pkg.baseVendor %>/bootstrap/dist/css/bootstrap.min.css',
                    '<%= pkg.baseVendor %>/font-awesome/css/font-awesome.min.css',
                    '<%= pkg.baseVendor %>/bootstrap-material-design/dist/css/ripples.css',
                    '<%= pkg.baseVendor %>/fancybox/dist/jquery.fancybox.css',
                    '<%= pkg.baseCss %>/components/typeahead.css',
                    '<%= pkg.baseCss %>/material/theme.blue.css',
                    '<%= pkg.baseCss %>/components/social_icons.css',
                    '<%= pkg.baseCss %>/commons.css',
                    '<%= pkg.baseCss %>/footer.css',
                    '<%= pkg.baseCss %>/event.css',
                    '<%= pkg.baseCss %>/style.css',
                    '<%= pkg.baseCss %>/respond.css'
                ],
                dest: '<%= pkg.baseDist %>/main/css/style.css',
                nonull: true
            },
            indexCss: {
                src: [
                    '<%= pkg.baseVendor %>/dropdown.js/jquery.dropdown.css',
                    '<%= pkg.baseCss %>/flaticon.css',
                    '<%= pkg.baseCss %>/components/comment.css',
                    '<%= pkg.baseCss %>/components/menu_droit.css',
                    '<%= pkg.baseCss %>/components/criteres.css',
                    '<%= pkg.baseCss %>/pages/event_index.css'
                ],
                dest: '<%= pkg.baseDist %>/evenements/css/index.css',
                nonull: true
            },
            agendaCss: {
                src: [
                    '<%= pkg.baseVendor %>/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css',
                    '<%= pkg.baseVendor %>/bootstrap-select/dist/css/bootstrap-select.min.css',
                    '<%= pkg.baseVendor %>/dropdown.js/jquery.dropdown.css',
                    '<%= pkg.baseCss %>/components/menu_droit.css',
                    '<%= pkg.baseCss %>/components/criteres.css'
                ],
                dest: '<%= pkg.baseDist %>/evenements/css/agenda.css',
                nonull: true
            },
            adminInfoCss: {
                src: [
                    '<%= pkg.baseCss %>/components/social_login.css'
                ],
                dest: '<%= pkg.baseDist %>/admin/info/css/style.css',
                nonull: true
            },
            widgetCss: {
                src: [
                    '<%= pkg.baseCss %>/components/widgets.css',
                    '<%= pkg.baseCss %>/sprites.css'
                ],
                dest: '<%= pkg.baseDist %>/widgets/css/widgets.css',
                nonull: true
            },
            detailEventCss: {
                src: [
                    '<%= pkg.baseCss %>/components/comment.css',
                    '<%= pkg.baseCss %>/components/menu_droit.css',
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
                    '<%= pkg.baseVendor %>/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css',
                    '<%= pkg.baseVendor %>/summernote/dist/summernote.css',
                    '<%= pkg.baseCss %>/pages/user_event_crud.css',
                    '<%= pkg.baseCss %>/components/social_login.css'
                ],
                dest: '<%= pkg.baseDist %>/espace-perso/evenements/css/manager.css',
                nonull: true
            },
            espacePersoDetailCss: {
                src: [
                    '<%= pkg.baseVendor %>/morris.js/morris.css',
                    '<%= pkg.baseCss %>/components/charts.css',
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

        //Watch des fichiers js / css
        watch: {
            css: {
                files: ['<%= pkg.baseCss %>/*.css', '<%= pkg.baseCss %>/*/*.css', '!<%= pkg.baseCss %>/sprites.css'],
                tasks: ['css']
            },
            javascript: {
                files: ['<%= pkg.baseJs %>/*.js', '<%= pkg.baseJs %>/*/*.js'],
                tasks: ['js']
            }
        },

        //Concaténation et minification des fichiers JS
        uglify: {
            dist: {
                files: {
                    '<%= pkg.baseDist %>/main/js/scripts.min.js': [
                        '<%= pkg.baseVendor %>/jquery/dist/jquery.min.js',
                        '<%= pkg.baseVendor %>/bootstrap/dist/js/bootstrap.min.js',
                        '<%= pkg.baseVendor %>/bootstrap-material-design/dist/js/ripples.js',
                        '<%= pkg.baseVendor %>/bootstrap-material-design/dist/js/material.js',
                        '<%= pkg.baseVendor %>/jquery.cookiebar/jquery.cookiebar.js',
                        '<%= pkg.baseVendor %>/jquery.scrollTo/jquery.scrollTo.min.js',
                        '<%= pkg.baseVendor %>/jquery-unveil/jquery.unveil.min.js',
                        '<%= pkg.baseVendor %>/fancybox/dist/jquery.fancybox.js',
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
                        '<%= pkg.baseJs %>/i18n/summernote/summernote-fr-FR.js',
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
            js: ['<%= pkg.baseDist %>/**/*.*.js', '!<%= pkg.baseDist %>/**/*.min.js'],
            css: ['<%= pkg.baseDist %>/**/*.*.css', '!<%= pkg.baseDist %>/**/*.min.css'],
        },
        cacheBust: {
            js: {
                options: {
                    jsonOutput: true,
                    jsonOutputFilename: 'assets_mapping.json',
                    assets: ['<%= pkg.baseDist %>/**/*.js', '<%= pkg.baseDist %>/**/*.css']
                },
                src: []
            }
        }
    });

    // Default task(s).
    grunt.registerTask('convert_mapping', 'Converti le fichier issu de cache pour intégration dans Symfony ', function() {
        var mapping = grunt.file.readJSON(grunt.config('cacheBust.js.options.jsonOutputFilename'));
        var config = {
            'parameters': {
                'mapping_assets': mapping
            }
        };
        var content = YAML.stringify(config).replace(/web\//g, "");
        grunt.file.write('app/config/mapping_assets.yml', content);
    });

    grunt.registerTask('default', ['css', 'js']);
    grunt.registerTask('css', ['concat', 'cssmin']);
    grunt.registerTask('cache', ['clean', 'cacheBust', 'convert_mapping']);
    grunt.registerTask('js', ['uglify']);
    grunt.registerTask('assets:install', ['symlink']);
    grunt.registerTask('deploy', ['symlink', 'sprite', 'default']);
};
