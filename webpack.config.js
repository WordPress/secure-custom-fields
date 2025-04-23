const path = require( 'path' );
const TerserPlugin = require( 'terser-webpack-plugin' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const CssMinimizerPlugin = require( 'css-minimizer-webpack-plugin' );
const RemoveEmptyScriptsPlugin = require( 'webpack-remove-empty-scripts' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

// Common configuration for both builds
const commonConfig = {
	entry: {
		// JavaScript files
		'js/acf-escaped-html-notice':
			'./assets/src/js/acf-escaped-html-notice.js',
		'js/acf-field-group': './assets/src/js/acf-field-group.js',
		'js/acf-input': './assets/src/js/acf-input.js',
		'js/acf-internal-post-type':
			'./assets/src/js/acf-internal-post-type.js',
		'js/acf': './assets/src/js/acf.js',
		'js/pro/acf-pro-blocks': './assets/src/js/pro/acf-pro-blocks.js',
		'js/pro/acf-pro-field-group':
			'./assets/src/js/pro/acf-pro-field-group.js',
		'js/pro/acf-pro-input': './assets/src/js/pro/acf-pro-input.js',
		'js/pro/acf-pro-ui-options-page':
			'./assets/src/js/pro/acf-pro-ui-options-page.js',
		'js/acf-experiments': './assets/src/js/_acf-experiments.ts',
		// CSS files
		'css/acf-dark': './assets/src/sass/acf-dark.scss',
		'css/acf-field-group': './assets/src/sass/acf-field-group.scss',
		'css/acf-global': './assets/src/sass/acf-global.scss',
		'css/acf-input': './assets/src/sass/acf-input.scss',
		'css/pro/acf-pro-field-group':
			'./assets/src/sass/pro/acf-pro-field-group.scss',
		'css/pro/acf-pro-input': './assets/src/sass/pro/acf-pro-input.scss',
	},
	output: {
		path: path.resolve( __dirname, 'assets/build/' ),
	},
	module: {
		rules: [
			{
				test: /\.(js|jsx|ts|tsx)$/,
				exclude: /node_modules/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: [
							'@babel/preset-react',
							'@babel/preset-typescript',
						],
					},
				},
			},
			{
				test: /\.scss$/,
				use: [
					MiniCssExtractPlugin.loader, // Extract CSS into separate files.
					{
						loader: 'css-loader',
						options: {
							url: false, // Don't resolve URLs.
						},
					},
					'sass-loader',
				],
			},
		],
	},
	resolve: {
		extensions: [ '.js', '.jsx', '.ts', '.tsx' ],
	},
};

// Unminified build
const unminifiedConfig = {
	...commonConfig,
	mode: 'development',
	output: {
		...commonConfig.output,
		filename: '[name].js', // Unminified output
	},
	devtool: 'source-map',
	optimization: {
		minimize: false, // No minification for this config
	},
	plugins: [
		new RemoveEmptyScriptsPlugin(),
		new MiniCssExtractPlugin( {
			filename: '[name].css', // Output CSS as .css
		} ),
		new DependencyExtractionWebpackPlugin( {
			injectPolyfill: true,
			useCombinedAssetFile: true,
		} ),
	],
};

// Minified build
const minifiedConfig = {
	...commonConfig,
	mode: 'production',
	output: {
		...commonConfig.output,
		filename: '[name].min.js', // Minified output
	},
	optimization: {
		minimize: true, // Enable minification
		minimizer: [
			new TerserPlugin( {
				terserOptions: {
					format: {
						comments: false, // Remove comments
					},
				},
				extractComments: false,
			} ),
			new CssMinimizerPlugin(), // Minify CSS
		],
	},
	plugins: [
		new RemoveEmptyScriptsPlugin(),
		new MiniCssExtractPlugin( {
			filename: '[name].min.css', // Changed to output .min.css files
		} ),
		new DependencyExtractionWebpackPlugin( {
			injectPolyfill: true,
			useCombinedAssetFile: true,
		} ),
	],
};

// Export both configurations
module.exports = [ unminifiedConfig, minifiedConfig ];
