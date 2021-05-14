const path = require('path');
const glob = require('glob-all');
const Encore = require('@symfony/webpack-encore');
const MomentLocalesPlugin = require('moment-locales-webpack-plugin');
const PurgecssPlugin = require('purgecss-webpack-plugin');

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // public path used by the web server to access the output path
    .setPublicPath('/build')
    // only needed for CDN's or sub-directory deploy
    //.setManifestKeyPrefix('build/')

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

    .splitEntryChunks()
    .configureSplitChunks(function (splitChunks) {
        //https://github.com/webpack/webpack/blob/master/examples/many-pages/README.md
        splitChunks.maxInitialRequests = 20; // for HTTP2
        splitChunks.maxAsyncRequests = 20;
    })
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
    .enableVersioning(Encore.isProduction())

    // enables @babel/preset-env polyfills
    .configureBabel(() => {}, {
        useBuiltIns: 'usage',
        corejs: 3,
        includeNodeModules: ['bootstrap'],
    })
    .enableSassLoader()
    //.enableIntegrityHashes(Encore.isProduction())

    // uncomment if you're having problems with a jQuery plugin
    .autoProvidejQuery()
    .autoProvideVariables({
        $: 'jquery',
        jQuery: 'jquery',
        'window.jQuery': 'jquery',
        'window.$': 'jquery',
        Popper: ['popper.js', 'default'],
    })
    .addPlugin(
        new MomentLocalesPlugin({
            localesToKeep: ['fr'],
        })
    )
    .addPlugin(
        new PurgecssPlugin({
            paths: glob.sync(
                [
                    path.join(__dirname, 'templates/**/*.html.twig'),
                    path.join(__dirname, 'assets/**/*.js'),
                    path.join(__dirname, 'src/**/*.php'),
                    path.join(__dirname, 'node_modules/bootstrap/js/src/**/*.js'),
                    path.join(__dirname, 'node_modules/bootstrap-select/js/bootstrap-select.js'),
                    path.join(__dirname, 'node_modules/daterangepicker/daterangepicker.js'),
                    path.join(__dirname, 'node_modules/jquery-cookiebar/jquery.cookiebar.js'),
                    path.join(__dirname, 'node_modules/fancybox/dist/js/jquery.fancybox.js'),
                    path.join(__dirname, 'node_modules/lazysizes/lazysizes.js'),
                    path.join(__dirname, 'node_modules/morris.js/morris.js'),
                    path.join(__dirname, 'node_modules/raphael/raphael.js'),
                    path.join(__dirname, 'node_modules/summernote/src/js/**/*.js'),
                    path.join(__dirname, 'node_modules/typeahead.js/src/**/*.js'),
                ],
                { nodir: true }
            ),
            whitelistPatterns: [/^custom-/],
        })
    )
    .addAliases({
        jquery: path.resolve(__dirname, 'node_modules/jquery/src/jquery'),
        $: path.resolve(__dirname, 'node_modules/jquery/src/jquery'),
    });

module.exports = Encore.getWebpackConfig();
