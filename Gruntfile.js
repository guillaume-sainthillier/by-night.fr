module.exports = function (grunt) {
    grunt.loadNpmTasks('grunt-contrib-symlink');
    grunt.loadNpmTasks('grunt-contrib-less');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-spritesmith');

    // Configuration du projet
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),

        sprite: {
            all: {
                src: 'web/bundles/tbnagenda/images/programmes/*.png',
                dest: 'web/bundles/tbnagenda/images/spritesheet.png',
                destCss: 'web/bundles/tbnagenda/css/sprites.css'
            }
        },

        cssmin: {
            options: {
                shorthandCompacting: false,
                roundingPrecision: -1
            },
            target: {
                files: [{
                    expand: true,
                    cwd: 'web/prod/evenements/css',
                    src: ['*.css', '!*.min.css'],
                    dest: 'web/prod/evenements/css',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: 'web/prod/css/',
                    src: ['*.css', '!*.min.css'],
                    dest: 'web/prod/css/',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: 'web/prod/admin/info/css',
                    src: ['*.css', '!*.min.css'],
                    dest: 'web/prod/admin/info/css',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: 'web/prod/admin/sites/css',
                    src: ['*.css', '!*.min.css'],
                    dest: 'web/prod/admin/sites/css',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: 'web/prod/espace-perso/evenements/css',
                    src: ['*.css', '!*.min.css'],
                    dest: 'web/prod/espace-perso/evenements/css',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: 'web/prod/espace-perso/login/css',
                    src: ['*.css', '!*.min.css'],
                    dest: 'web/prod/espace-perso/login/css',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: 'web/prod/main/css',
                    src: ['*.css', '!*.min.css'],
                    dest: 'web/prod/main/css',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: 'web/prod/membres/css',
                    src: ['*.css', '!*.min.css'],
                    dest: 'web/prod/membres/css',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: 'web/prod/search/css',
                    src: ['*.css', '!*.min.css'],
                    dest: 'web/prod/search/css',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: 'web/prod/widgets/css',
                    src: ['*.css', '!*.min.css'],
                    dest: 'web/prod/widgets/css',
                    ext: '.min.css'
                }, {
                    expand: true,
                    cwd: 'web/prod/plus/css',
                    src: ['*.css', '!*.min.css'],
                    dest: 'web/prod/plus/css',
                    ext: '.min.css'
                },
                ]
            }
        },

        // Définition de la tache 'symlink'
        // https://github.com/gruntjs/grunt-contrib-symlink
        symlink: {
            options: {
                overwrite: false
            },
            expanded: {
                files: [
                    {
                        expand: true,
                        src: ['*'],
                        dest: 'web/prod/evenements/font',
                        cwd: 'web/bundles/tbnagenda/font'
                    },
                    {
                        expand: true,
                        src: ['*'],
                        dest: 'web/prod/main/fonts',
                        cwd: 'web/bower/font-awesome/fonts'
                    },
                    {
                        expand: true,
                        src: ['*'],
                        dest: 'web/prod/evenements/images',
                        cwd: 'web/bundles/tbnagenda/images'
                    },
                    {
                        expand: true,
                        src: ['*'],
                        dest: 'web/prod/widgets/images',
                        cwd: 'web/bundles/tbnagenda/images'
                    },
                    {
                        expand: true,
                        src: ['*'],
                        dest: 'web/prod/main/img/icons',
                        cwd: 'web/img/icons'
                    },
                    {
                        expand: true,
                        src: ['*'],
                        dest: 'web/prod/espace-perso/evenements/css/font',
                        cwd: 'web/bower/summernote/dist/font'
                    }
                ]
            }
        },
        concat: {
            print: {
                src: [
                    'web/css/print.css'
                ],
                dest: 'web/prod/css/print.css'
            },
            errors: {
                src: [
                    'web/bower/bootstrap/dist/css/bootstrap.min.css',
                    'web/bower/font-awesome/css/font-awesome.min.css',
                    'web/css/erreurs.css'
                ],
                dest: 'web/prod/css/erreurs.css'
            },
            mainJs: {
                src: [
                    'web/bower/jquery/dist/jquery.min.js',
                    'web/bower/bootstrap/dist/js/bootstrap.min.js',
                    'web/bower/bootstrap-material-design/dist/js/ripples.js',
                    'web/bower/bootstrap-material-design/dist/js/material.js',
                    'web/bower/jquery.cookiebar/jquery.cookiebar.js',
                    'web/bower/jquery.scrollTo/jquery.scrollTo.min.js',
                    'web/bower/jquery-unveil/jquery.unveil.min.js',
                    'web/bower/fancybox/source/jquery.fancybox.js',
                    'web/js/overrides.js',
                    'web/js/socials.js',
                    'web/js/App.js'
                ],
                dest: 'web/prod/main/js/scripts.js'
            },
            mainCss: {
                src: [
                    'web/bower/jquery.cookiebar/jquery.cookiebar.css',
                    'web/bower/bootstrap/dist/css/bootstrap.min.css',
                    'web/bower/font-awesome/css/font-awesome.min.css',
                    'web/bower/bootstrap-material-design/dist/css/ripples.css',
                    'web/bower/fancybox/source/jquery.fancybox.css',
                    'web/css/material/theme.blue.css',
                    'web/css/social-icons.css',
                    'web/css/commons.css',
                    'web/css/footer.css',
                    'web/css/event.css',
                    'web/css/style.css',
                    'web/css/respond.css'
                ],
                dest: 'web/prod/main/css/style.css'
            },
            indexJs: {
                src: [
                    'web/bower/dropdown.js/jquery.dropdown.js',
                    'web/bundles/tbnagenda/js/index.js'
                ],
                dest: 'web/prod/evenements/js/index.js'
            },
            indexCss: {
                src: [
                    'web/bower/dropdown.js/jquery.dropdown.css',
                    'web/bundles/tbnagenda/css/flaticon.css',
                    'web/bundles/tbnagenda/css/liste.css',
                    'web/bundles/tbnagenda/css/index.css'
                ],
                dest: 'web/prod/evenements/css/index.css'
            },
            agendaJs: {
                src: [
                    'web/bower/bootstrap-datepicker/dist/js/bootstrap-datepicker.js',
                    'web/bower/bootstrap-datepicker/dist/locales/bootstrap-datepicker.fr.min.js',
                    'web/bower/bootstrap-select/dist/js/bootstrap-select.min.js',
                    'web/bower/dropdown.js/jquery.dropdown.js',
                    'web/bower/bootstrap-select/js/i18n/defaults-fr_FR.js',
                    'web/bundles/tbnagenda/js/agenda.js'
                ],
                dest: 'web/prod/evenements/js/agenda.js'
            },
            agendaCss: {
                src: [
                    'web/bower/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css',
                    'web/bower/bootstrap-select/dist/css/bootstrap-select.min.css',
                    'web/bower/dropdown.js/jquery.dropdown.css',
                    'web/bundles/tbnagenda/css/liste.css'
                ],
                dest: 'web/prod/evenements/css/agenda.css'
            },
            adminInfoJS: {
                src: [
                    'web/bundles/tbnsocial/js/SocialLogin.js'
                ],
                dest: 'web/prod/admin/info/js/login.js'
            },
            adminInfoCss: {
                src: [
                    'web/bundles/tbnsocial/css/social_login.css'
                ],
                dest: 'web/prod/admin/info/css/style.css'
            },
            adminSiteJs: {
                src: [
                    'web/js/collections.js'
                ],
                dest: 'web/prod/admin/sites/js/site.min.js'
            },
            widgetJs: {
                src: [
                    'web/bower/malihu-custom-scrollbar-plugin/jquery.mCustomScrollbar.concat.min.js',
                    'web/bundles/tbnagenda/js/widgets.js'
                ],
                dest: 'web/prod/widgets/js/widgets.js'
            },
            widgetCss: {
                src: [
                    'web/bundles/tbnagenda/css/widgets.css',
                    'web/bundles/tbnagenda/css/sprites.css',
                    'web/bundles/tbnagenda/css/style.css',
                    'web/bundles/tbnagenda/css/jquery.mCustomScrollbar.css'
                ],
                dest: 'web/prod/widgets/css/widgets.css'
            },
            detailEventJs: {
                src: [
                    'web/bundles/tbncomment/js/CommentApp.js',
                    'web/bundles/tbnagenda//js/details.js'
                ],
                dest: 'web/prod/evenements/js/details.js'
            },
            detailEventCss: {
                src: [
                    'web/bundles/tbnagenda/css/details.css'
                ],
                dest: 'web/prod/evenements/css/details.css'
            },
            searchJs: {
                src: [
                    'web/bower/dropdown.js/jquery.dropdown.js'
                ],
                dest: 'web/prod/search/js/search.js'
            },
            searchCss: {
                src: [
                    'web/bower/dropdown.js/jquery.dropdown.css'
                ],
                dest: 'web/prod/search/css/search.css'
            },
            espacePersoListeJs: {
                src: [
                    'web/bower/bootstrap-datepicker/dist/js/bootstrap-datepicker.js',
                    'web/bower/bootstrap-datepicker/dist/locales/bootstrap-datepicker.fr.min.js',
                    'web/bower/summernote/dist/summernote.js',
                    'web/bower/summernote/lang/summernote-fr-FR.js',
                    'web/bower/typeahead.js/dist/typeahead.bundle.min.js',
                    'web/bower/typeahead-addresspicker/dist/typeahead-addresspicker.min.js',
                    'web/tbnsocial/js/SocialLogin.js',
                    'web/bundles/tbnuser/js/EventHandler.js'
                ],
                dest: 'web/prod/espace-perso/evenements/js/manager.js'
            },
            espacePersoListeCss: {
                src: [
                    'web/bower/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css',
                    'web/bower/summernote/dist/summernote.css',
                    'web/bundles/tbnuser/css/espace_perso.css',
                    'web/bundles/tbnsocial/css/social_login.css'
                ],
                dest: 'web/prod/espace-perso/evenements/css/manager.css'
            },
            espacePersoJs: {
                src: [
                    'web/bundles/tbnuser/js/ListEvents.js'
                ],
                dest: 'web/prod/espace-perso/evenements/js/liste.js'
            },
            espacePersoDetailJs: {
                src: [
                    'web/bower/raphael/raphael-min.js',
                    'web/bower/morris.js/morris.min.js',
                    'web/bundles/tbnuser/js/UserDetails.js'
                ],
                dest: 'web/prod/membres/js/detail.js'
            },
            espacePersoDetailCss: {
                src: [
                    'web/bower/morris.js/morris.css',
                    'web/bundles/tbnuser/css/membre.css'
                ],
                dest: 'web/prod/membres/css/detail.css'
            },
            espacePersoLoginJs: {
                src: [
                    'web/bundles/tbnsocial/js/SocialLogin.js'
                ],
                dest: 'web/prod/espace-perso/login/js/login.js'
            },
            espacePersoLoginCss: {
                src: [
                    'web/bundles/tbnsocial/css/social_login.css'
                ],
                dest: 'web/prod/espace-perso/login/css/login.css'
            },
            enSavoirPlusCss: {
                src: [
                    'web/bundles/tbnagenda/css/plus.css'
                ],
                dest: 'web/prod/plus/css/style.css'
            },
        },
        watch: {
            css: {
                files: ['web/bundles/*/css/*.css', 'web/css/*.css', 'web/css/*/*.css', '!web/bundles/tbnagenda/css/sprites.css'],
                tasks: ['css']
            },
            javascript: {
                files: ['web/bundles/*/js/*.js', 'web/js/*.js'],
                tasks: ['javascript']
            }
        },
        uglify: {
            dist: {
                files: {
                    'web/prod/main/js/scripts.min.js': ['web/prod/main/js/scripts.js'],
                    'web/prod/evenements/js/index.min.js': ['web/prod/evenements/js/index.js'],
                    'web/prod/espace-perso/login/js/login.min.js': ['web/prod/espace-perso/login/js/login.js'],
                    'web/prod/membres/js/detail.min.js': ['web/prod/membres/js/detail.js'],
                    'web/prod/espace-perso/evenements/js/liste.min.js': ['web/prod/espace-perso/evenements/js/liste.js'],
                    'web/prod/espace-perso/evenements/js/manager.min.js': ['web/prod/espace-perso/evenements/js/manager.js'],
                    'web/prod/search/js/search.min.js': ['web/prod/search/js/search.js'],
                    'web/prod/evenements/js/details.min.js': ['web/prod/evenements/js/details.js'],
                    'web/prod/widgets/js/widgets.min.js': ['web/prod/widgets/js/widgets.js'],
                    'web/prod/admin/info/js/login.min.js': ['web/prod/admin/info/js/login.js'],
                    'web/prod/evenements/js/agenda.min.js': ['web/prod/evenements/js/agenda.js'],
                }
            }
        }
    });

    // Default task(s).

    grunt.registerTask('default', ['css', 'javascript']);
    grunt.registerTask('css', ['sprite', 'concat', 'cssmin']);
    grunt.registerTask('javascript', ['concat', 'uglify']);
    grunt.registerTask('assets:install', ['symlink']);
    grunt.registerTask('deploy', ['assets:install', 'default']);
};