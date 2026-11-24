/**
 * Jest configuration for unit tests
 */
module.exports = {
	preset: '@wordpress/jest-preset-default',
	transform: {
		'\\.[jt]sx?$': [
			'babel-jest',
			{ presets: [ '@wordpress/babel-preset-default' ] },
		],
	},
	testMatch: [ '**/tests/js/**/*.test.js', '**/tests/js/**/*.test.jsx' ],
	testPathIgnorePatterns: [ '/node_modules/', '/.claude/' ],
	setupFilesAfterEnv: [ '<rootDir>/tests/js/setup-tests.js' ],
	testEnvironment: 'jsdom',
	moduleNameMapper: {
		'\\.(css|less|scss|sass)$': 'identity-obj-proxy',
		'^jquery$': '<rootDir>/tests/js/mocks/jquery.js',
	},
	collectCoverageFrom: [
		'assets/src/js/**/*.{js,jsx}',
		'!assets/src/js/**/*.min.js',
		'!**/node_modules/**',
		'!**/vendor/**',
	],
	coverageDirectory: 'coverage/unit',
	coverageReporters: [ 'lcov', 'text', 'html' ],
	transformIgnorePatterns: [ 'node_modules/(?!(react-jsx-parser)/)' ],
};
