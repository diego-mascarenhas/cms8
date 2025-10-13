const {
  EnvironmentPlugin,
  IgnorePlugin
} = require('webpack');
const mix = require('laravel-mix');
const glob = require('glob');
const path = require('path');

/*
 |--------------------------------------------------------------------------
 | Configure mix
 |--------------------------------------------------------------------------
 */

mix.options({
  resourceRoot: process.env.ASSET_URL || undefined,
  processCssUrls: false,
  postCss: [require('autoprefixer')]
});

/*
 |--------------------------------------------------------------------------
 | Configure Webpack
 |--------------------------------------------------------------------------
 */

mix.webpackConfig({
  output: {
    publicPath: process.env.ASSET_URL || undefined,
    libraryTarget: 'umd'
  },

  plugins: [
    new IgnorePlugin({
      checkResource(resource, context) {
        return [
          path.join(__dirname, 'resources/assets/vendor/libs/@form-validation')
          // Add more paths to ignore as needed
        ].some(pathToIgnore => resource.startsWith(pathToIgnore));
      }
    }),
    new EnvironmentPlugin({
      // Application's public url
      BASE_URL: process.env.ASSET_URL ? `${process.env.ASSET_URL}/` : '/'
    })
  ],
  module: {
    rules: [{
      test: /\.es6$|\.js$/,
      include: [
        path.join(__dirname, 'node_modules/bootstrap/'),
        path.join(__dirname, 'node_modules/popper.js/'),
        path.join(__dirname, 'node_modules/shepherd.js/')
      ],
      loader: 'babel-loader',
      options: {
        presets: [
          ['@babel/preset-env', {
            targets: 'last 2 versions, ie >= 10'
          }]
        ],
        plugins: [
          '@babel/plugin-transform-destructuring',
          '@babel/plugin-proposal-object-rest-spread',
          '@babel/plugin-transform-template-literals'
        ],
        babelrc: false
      }
    }]
  },
  externals: {
    jquery: 'jQuery',
    moment: 'moment',
    jsdom: 'jsdom',
    velocity: 'Velocity',
    hammer: 'Hammer',
    pace: '"pace-progress"',
    chartist: 'Chartist',
    'popper.js': 'Popper',

    // blueimp-gallery plugin
    './blueimp-helper': 'jQuery',
    './blueimp-gallery': 'blueimpGallery',
    './blueimp-gallery-video': 'blueimpGallery'
  }
});

/*
 |--------------------------------------------------------------------------
 | Vendor assets
 |--------------------------------------------------------------------------
 */

function mixAssetsDir(query, cb) {
  (glob.sync('resources/assets/' + query) || []).forEach(f => {
    f = f.replace(/[\\\/]+/g, '/');
    cb(f, f.replace('resources/assets/', 'public/assets/'));
  });
}

/*
 |--------------------------------------------------------------------------
 | Configure sass
 |--------------------------------------------------------------------------
 */

const sassOptions = {
  precision: 5
};

// Core stylesheets
mixAssetsDir('vendor/scss/**/!(_)*.scss', (src, dest) =>
  mix.sass(src, dest.replace(/(\\|\/)scss(\\|\/)/, '$1css$2').replace(/\.scss$/, '.css'), {
    sassOptions
  })
);

// Core JavaScripts
mixAssetsDir('vendor/js/**/*.js', (src, dest) => mix.js(src, dest));

// Libs
mixAssetsDir('vendor/libs/**/*.js', (src, dest) => mix.js(src, dest));
mixAssetsDir('vendor/libs/**/!(_)*.scss', (src, dest) =>
  mix.sass(src, dest.replace(/\.scss$/, '.css'), {
    sassOptions
  })
);
mixAssetsDir('vendor/libs/**/*.{png,jpg,jpeg,gif}', (src, dest) => mix.copy(src, dest));
// Copy source maps for debugging
mixAssetsDir('vendor/libs/**/*.map', (src, dest) => mix.copy(src, dest));
// Copy task for form validation plugin as premium plugin don't have npm package
mixAssetsDir('vendor/libs/@form-validation/umd', (src, dest) => mix.copyDirectory(src, dest));

// Fonts
mixAssetsDir('vendor/fonts/*/*', (src, dest) => mix.copy(src, dest));
mixAssetsDir('vendor/fonts/!(_)*.scss', (src, dest) =>
  mix.sass(src, dest.replace(/(\\|\/)scss(\\|\/)/, '$1css$2').replace(/\.scss$/, '.css'), {
    sassOptions
  })
);

/*
 |--------------------------------------------------------------------------
 | Application assets
 |--------------------------------------------------------------------------
 */

mixAssetsDir('js/**/*.js', (src, dest) => mix.scripts(src, dest));
mixAssetsDir('css/**/*.css', (src, dest) => mix.copy(src, dest));

mix.js('resources/js/laravel-user-management.js', 'public/js/');
mix.js('resources/js/openai.js', 'public/js/');
mix.js('resources/js/helpdesk-chat.js', 'public/js/');
mix.copyDirectory('lang/datatables', 'public/js/datatables');

mix.copy('node_modules/flag-icons/flags/1x1/*', 'public/assets/vendor/fonts/flags/1x1');
mix.copy('node_modules/flag-icons/flags/4x3/*', 'public/assets/vendor/fonts/flags/4x3');
mix.copy('node_modules/@fortawesome/fontawesome-free/webfonts/*', 'public/assets/vendor/fonts/fontawesome');
mix.copy('node_modules/katex/dist/fonts/*', 'public/assets/vendor/libs/quill/fonts');

// Copy source maps for debugging
mix.copy('node_modules/perfect-scrollbar/dist/perfect-scrollbar.js.map', 'public/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js.map');
mix.copy('node_modules/@popperjs/core/dist/umd/popper.min.js.map', 'public/assets/vendor/libs/popper/popper.min.js.map');

mix.version();

/*
 |--------------------------------------------------------------------------
 | Browsersync Reloading (DISABLED)
 |--------------------------------------------------------------------------
 |
 | BrowserSync is DISABLED because:
 | 1. Not compatible with Laravel Herd (.test domains)
 | 2. Causes build failures in production (npm run build)
 | 3. Not needed for Herd's hot reload functionality
 |
 | If you need BrowserSync, enable it conditionally:
 | if (!mix.inProduction()) { mix.browserSync('http://humano.test/'); }
 */

// mix.browserSync('http://127.0.0.1:8000/');