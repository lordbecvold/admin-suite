/*
 * admin-suite frontend webpack builder
 */
const Encore = require('@symfony/webpack-encore');

Encore
    // set build path
    .setOutputPath('public/build/')
    .setPublicPath('/build')

    // register css entries
    .addEntry('main-css', './assets/css/main.css')
    .addEntry('scrollbar-css', './assets/css/scrollbar.css')
    .addEntry('fontawesome-css', './node_modules/@fortawesome/fontawesome-free/css/all.css')

    // copy static assets
    .copyFiles(
        {
            from: './assets/images', 
            to: 'images/[path][name].[ext]' 
        }
    )

    // other webpack configs
    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    // postcss configs (tailwindcss)
    .enablePostCssLoader((options) => {
        options.postcssOptions = {
            plugins: {
                tailwindcss: {
                    content: [
                        "./assets/**/*.js",
                        "./templates/**/*.html.twig",
                    ],
                    theme: {
                        extend: {},
                    },
                    plugins: [],
                },
                autoprefixer: {},
            }
        };
    })

    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.23';
    })
;

module.exports = Encore.getWebpackConfig();
