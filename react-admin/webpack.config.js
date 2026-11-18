const path = require('path');
const BrowserSyncPlugin = require('browser-sync-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');

// WordPress site URL - change this to your local WordPress URL
const WORDPRESS_SITE_URL = 'http://localhost/your-wordpress-site';

module.exports = {
  entry: './src/index.js', // your main React file
  output: {
    filename: 'backend.js',
    path: path.resolve(__dirname, '../assets/js'), // Adjust this path if needed
    clean: { keep: /(media|deactivation-feedback)\.js$/ }, // Clean the output directory before emit (keeps hand-written, unbundled scripts)
  },
  module: {
    rules: [
      {
        test: /\.(js|jsx)$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: [
              ['@babel/preset-env', { targets: "defaults" }],
              // Classic runtime: WordPress only provides react-jsx-runtime from 6.6, React itself is always available.
              ['@babel/preset-react', { runtime: 'classic' }]
            ],
            plugins: [
              // Add any Babel plugins you need here
            ]
          },
        },
      },
      // Add CSS handling if needed
      {
        test: /\.css$/,
        use: ['style-loader', 'css-loader'],
      },
      // Add file handling if needed
      {
        test: /\.(png|svg|jpg|jpeg|gif)$/i,
        type: 'asset/resource',
        generator: {
          filename: '../images/[name][ext]', // Output relative to your js folder
        },
      },
    ],
  },
  // Keep "translators:" comments so WP-CLI make-pot can pass them to translators.
  optimization: {
    minimizer: [
      new TerserPlugin({
        extractComments: false,
        terserOptions: {
          format: {
            comments: /translators:|@license|@preserve|^!/i,
          },
        },
      }),
    ],
  },
  // WordPress provides these packages at runtime (script handles: react, react-dom, wp-element, wp-i18n),
  // so they are not bundled a second time.
  externals: {
    react: 'React',
    'react-dom': 'ReactDOM',
    '@wordpress/element': ['wp', 'element'],
    '@wordpress/i18n': ['wp', 'i18n'],
  },
  resolve: {
    extensions: ['.js', '.jsx'],
    alias: {
      '@': path.resolve(__dirname, 'src'), // Enable import shortcuts with @
    },
  },
  plugins: [
    // BrowserSync for auto-reloading WordPress
    new BrowserSyncPlugin({
      host: 'localhost',
      port: 3000,
      proxy: WORDPRESS_SITE_URL,
      files: [
        '../assets/js/backend.js', // Watch the compiled JS file
        '../**/*.php', // Watch PHP files for changes
      ],
      injectChanges: true,
      notify: true
    })
  ],
  // Different configurations based on environment
  mode: process.env.NODE_ENV === 'production' ? 'production' : 'development',
  devtool: process.env.NODE_ENV === 'production' ? false : 'source-map',
  // Performance settings
  performance: {
    hints: process.env.NODE_ENV === 'production' ? 'warning' : false,
  },
  // Only watch in development mode
  watch: process.env.NODE_ENV === 'development',
  watchOptions: {
    ignored: /node_modules/,
    aggregateTimeout: 300,
    poll: 1000,
  },
};