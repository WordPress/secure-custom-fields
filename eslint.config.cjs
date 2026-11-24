const wordpressConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );
const jestPlugin = require( 'eslint-plugin-jest' );

module.exports = [
	{
		ignores: [
			'node_modules/**',
			'vendor/**',
			'wordpress/**',
			'assets/build/**',
			'coverage/**',
			'.nyc_output/**',
			'.php-coverage/**',
			'artifacts/**',
			'test-results/**',
			'playwright-report/**',
			'blob-report/**',
			'release/**',
			'**/.claude/**',
		],
	},
	...wordpressConfig,
	// The scripts config targets Vitest for unit tests, but this suite runs on Jest.
	{
		...jestPlugin.configs[ 'flat/recommended' ],
		files: [ 'tests/js/**/*.{js,jsx}' ],
	},
];
