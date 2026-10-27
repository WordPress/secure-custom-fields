import { defineConfig } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

const baseConfig = require( '@wordpress/scripts/config/playwright.config.js' );

// Read port from the test wp-env config if not already set via environment variable
if ( ! process.env.WP_BASE_URL ) {
	const wpEnvConfig = JSON.parse(
		fs.readFileSync(
			path.resolve( process.cwd(), '.wp-env.test.json' ),
			'utf8'
		)
	);

	const baseUrl = new URL( baseConfig.use.baseURL );
	const testsPort =
		process.env.WP_ENV_TESTS_PORT || wpEnvConfig.port || baseUrl.port;
	const testsHost = process.env.WP_ENV_TESTS_HOST || baseUrl.hostname;

	process.env.WP_BASE_URL = `http://${ testsHost }:${ testsPort }`;
}

const config = defineConfig( {
	...baseConfig,
	testDir: './tests/e2e',
	reporter: process.env.CI
		? [ [ 'github' ], [ 'blob' ] ]
		: [ [ 'list' ] ],
	use: {
		...baseConfig.use,
		baseURL: process.env.WP_BASE_URL,
	},
	webServer: undefined, // wp-env is already running, no need to start it again
} );
export default config;
