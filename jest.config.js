/**
 * Jest configuration for unit tests
 */
module.exports = {
	...require( '@wordpress/scripts/config/jest-unit.config' ),
	testMatch: [ '**/tests/js/**/*.test.js', '**/tests/js/**/*.test.jsx' ],
	setupFilesAfterEnv: [ '<rootDir>/tests/js/setup-tests.js' ],
	testEnvironment: 'jsdom',
	moduleNameMapper: {
		'\\.(css|less|scss|sass)$': 'identity-obj-proxy',
	},
	collectCoverageFrom: [
		'assets/src/js/**/*.{js,jsx}',
		'!assets/src/js/**/*.min.js',
		'!**/node_modules/**',
		'!**/vendor/**',
	],
	transformIgnorePatterns: [ 'node_modules/(?!(react-jsx-parser)/)' ],
};
