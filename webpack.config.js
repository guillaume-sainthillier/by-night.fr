var Encore = require('@symfony/webpack-encore');

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

    .copyFiles([{
        from: './assets/images',
        to: Encore.isProduction() ? 'images/[path][name].[hash:8].[ext]' : 'images/[path][name].[ext]',
    }])

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()
    .configureSplitChunks(function (splitChunks) {
        // change the configuration
        splitChunks.minSize = 0;
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
    .enableSourceMaps(Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    // enables @babel/preset-env polyfills
    .configureBabel(() => {
    }, {
        useBuiltIns: 'usage',
        corejs: 3
    })
    .enableSassLoader()
    .enableIntegrityHashes(Encore.isProduction())

    // uncomment if you're having problems with a jQuery plugin
    .autoProvidejQuery()
    .autoProvideVariables({
        $: 'jquery',
        jQuery: 'jquery',
        'window.jQuery': 'jquery',
        'window.$': 'jquery',
        Popper: ['popper.js', 'default'],
    })

// uncomment if you use API Platform Admin (composer req api-admin)
//.enableReactPreset()
//.addEntry('admin', './assets/js/admin.js')
;

module.exports = Encore.getWebpackConfig();
