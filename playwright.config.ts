import { defineConfig, devices } from '@playwright/test';

const baseConfig = require( '@wordpress/scripts/config/playwright.config.js' );
const config = defineConfig( {
  ...baseConfig,
  testDir: './tests/e2e',
  projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
			grepInvert: /-chromium/,
		},
	],
} );
export default config;