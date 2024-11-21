const path = require('path')
const glob = require('glob-all')
const ESLintWebpackPlugin = require("eslint-webpack-plugin")
const Encore = require('@symfony/webpack-encore')
const MomentLocalesPlugin = require('moment-locales-webpack-plugin')
const { PurgeCSSPlugin } = require('purgecss-webpack-plugin')

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev')
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // public path used by the web server to access the output path
    .setPublicPath('/build')
    // only needed for CDN's or sub-directory deploy
    // .setManifestKeyPrefix('build/')

    /*
     * ENTRY CONFIG
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
     */
    .addEntry('app', './assets/js/app.js')
    .addEntry('index', './assets/js/pages/index.js')
    .addEntry('admin_infos', './assets/js/pages/admin_infos.js')
    .addEntry('event_index', './assets/js/pages/event_index.js')
    .addEntry('event_details', './assets/js/pages/event_details.js')
    .addEntry('agenda', './assets/js/pages/agenda.js')
    .addEntry('profile', './assets/js/pages/profile.js')
    .addEntry('user', './assets/js/pages/user.js')
    .addEntry('espace_perso_list', './assets/js/pages/espace_perso_list.js')
    .addEntry('espace_perso_event', './assets/js/pages/espace_perso_event.js')
    .addEntry('search', './assets/js/pages/search.js')

    .copyFiles([
        {
            from: './assets/images',
            to: Encore.isProduction() ? 'images/[path][name].[hash:8].[ext]' : 'images/[path][name].[ext]',
        },
    ])
    // enables the Symfony UX Stimulus bridge (used in assets/bootstrap.js)
    // .enableStimulusBridge('./assets/controllers.json')

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()
    .configureSplitChunks(function (splitChunks) {
        // https://github.com/webpack/webpack/blob/master/examples/many-pages/README.md
        splitChunks.maxInitialRequests = 20 // for HTTP2
        splitChunks.maxAsyncRequests = 20
    })

    // will require an extra script tag for runtime.js
    // but, you probably want this, unless you're building a single-page app
    .enableSingleRuntimeChunk()

    /*
     * FEATURE CONFIG
     *
     * Enable & configure other features below. For a full
     * list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // enables @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage'
        config.corejs = '3.23'
    })
    .addPlugin(
        new MomentLocalesPlugin({
            localesToKeep: ['fr'],
        })
    )
    .addAliases({
        '@': path.join(__dirname, 'assets'),
        jQuery: 'jquery', // Summernote
        jquery: path.resolve(__dirname, 'node_modules/jquery/dist/jquery.js'),
        $: path.resolve(__dirname, 'node_modules/jquery/dist/jquery.js'),
    })

    // enables Sass/SCSS support
    .enableSassLoader((options) => {
        options.api = 'legacy'
    })

    // uncomment if you use TypeScript
    // .enableTypeScriptLoader()

    // uncomment if you use React
    // .enableReactPreset()

    // uncomment to get integrity="..." attributes on your script & link tags
    // requires WebpackEncoreBundle 1.4 or higher
    // .enableIntegrityHashes(Encore.isProduction())

    // uncomment if you're having problems with a jQuery plugin
    // .autoProvidejQuery()
    .autoProvideVariables({
        $: 'jquery',
        jQuery: 'jquery',
        'window.jQuery': 'jquery',
        'window.$': 'jquery',
    })

if(Encore.isDev()) {
    Encore.addPlugin(new ESLintWebpackPlugin({
        fix: true,
        configType: 'flat',
        exclude: [
            'node_modules',
            'var',
            'vendor',
        ]
    }))
}

if (Encore.isProduction()) {
    Encore.addPlugin(
        new PurgeCSSPlugin({
            paths: glob.sync(
                [
                    path.join(__dirname, 'templates/**/*.html.twig'),
                    path.join(__dirname, 'assets/**/*.js'),
                    path.join(__dirname, 'src/**/*.php'),
                    path.join(__dirname, 'node_modules/bootstrap/js/src/**/*.js'),
                    path.join(__dirname, 'node_modules/daterangepicker/daterangepicker.js'),
                    path.join(__dirname, 'node_modules/jquery-cookiebar/jquery.cookiebar.js'),
                    path.join(__dirname, 'node_modules/fancybox/dist/js/jquery.fancybox.js'),
                    path.join(__dirname, 'node_modules/lazysizes/lazysizes.js'),
                    path.join(__dirname, 'node_modules/morris.js/morris.js'),
                    path.join(__dirname, 'node_modules/raphael/raphael.js'),
                    path.join(__dirname, 'node_modules/select2/src/js/**/*.js'),
                    path.join(__dirname, 'node_modules/summernote/src/js/**/*.js'),
                    path.join(__dirname, 'node_modules/summernote/src/styles/bs5/*.js'),
                    path.join(__dirname, 'node_modules/typeahead.js/src/**/*.js'),
                ],
                {nodir: true}
            ),
            safelist: [
                /^custom-/,
                /^note-/,
                /^select2-container--bootstrap-5/,
                /^fa-(plus|xmark|masks-theater|vest|file-pen|calendar|location-crosshairs|twitter|facebook)/,
            ],
        })
    )
}

module.exports = Encore.getWebpackConfig()
