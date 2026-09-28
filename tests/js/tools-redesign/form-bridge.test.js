/**
 * Unit tests for the tools redesign form bridge.
 * Verifies that submissions post hidden forms matching the classic
 * Tools page contract (nonce field plus tool specific args).
 */

import { submitToolForm } from '../../../assets/src/js/tools-redesign/form-bridge';

describe( 'form-bridge', () => {
	let submittedForm;

	beforeEach( () => {
		submittedForm = null;

		HTMLFormElement.prototype.submit = jest.fn( function () {
			submittedForm = this;
		} );
	} );

	test( 'posts the nonce field expected by acf_verify_nonce', () => {
		submitToolForm( {
			nonce: 'test-nonce',
			args: { action: 'download' },
		} );

		expect( submittedForm.method ).toBe( 'post' );
		const nonceInput = submittedForm.querySelector( '[name="_acf_nonce"]' );
		expect( nonceInput ).not.toBeNull();
		expect( nonceInput.value ).toBe( 'test-nonce' );
	} );

	test( 'adds hidden fields for scalar args', () => {
		submitToolForm( {
			nonce: 'test-nonce',
			args: { action: 'generate' },
		} );

		const actionInput = submittedForm.querySelector( '[name="action"]' );
		expect( actionInput.value ).toBe( 'generate' );
	} );

	test( 'adds one field per array item', () => {
		submitToolForm( {
			nonce: 'test-nonce',
			args: { 'keys[]': [ 'group_1', 'group_2' ] },
		} );

		const keyInputs = submittedForm.querySelectorAll( '[name="keys[]"]' );
		expect( keyInputs ).toHaveLength( 2 );
		expect( keyInputs[ 0 ].value ).toBe( 'group_1' );
		expect( keyInputs[ 1 ].value ).toBe( 'group_2' );
	} );

	test( 'uses multipart encoding and carries the file when provided', () => {
		const file = new File( [ '[]' ], 'export.json', {
			type: 'application/json',
		} );

		submitToolForm( {
			nonce: 'test-nonce',
			args: { import_type: 'json' },
			file,
		} );

		expect( submittedForm.enctype ).toBe( 'multipart/form-data' );
		const fileInput = submittedForm.querySelector(
			'[name="acf_import_file"]'
		);
		expect( fileInput ).not.toBeNull();
	} );

	test( 'omits multipart encoding when no file is provided', () => {
		submitToolForm( {
			nonce: 'test-nonce',
			args: { action: 'download' },
		} );

		expect( submittedForm.enctype ).toBe(
			'application/x-www-form-urlencoded'
		);
		expect(
			submittedForm.querySelector( '[name="acf_import_file"]' )
		).toBeNull();
	} );
} );
