/**
 * Unit tests for the SCF front-end form view validation helpers.
 *
 * These helpers must reproduce the classic validator's markup exactly:
 * `.acf-error` on the field wrapper and an
 * `<div class="acf-notice -error acf-error-message"><p>…</p></div>` notice
 * prepended to the `.acf-input` wrap (field level) or the form (summary).
 */
import {
	buildSummaryMessage,
	clearAllErrors,
	clearFieldError,
	createNotice,
	findFieldByInputName,
	getNativeErrors,
	lockForm,
	showErrors,
	showFieldError,
	unlockForm,
} from '../../../assets/src/js/frontend/scf-form-validation';

const I18N = {
	validationFailed: 'Validation failed',
	oneFieldRequiresAttention: '1 field requires attention',
	fieldsRequireAttention: '%d fields require attention',
};

/**
 * Builds an acf_form()-shaped DOM fixture.
 *
 * @return {HTMLFormElement} The form element.
 */
function buildForm() {
	document.body.innerHTML = `
		<form class="acf-form" novalidate>
			<div class="acf-fields acf-form-fields -top">
				<div class="acf-field acf-field-text" data-name="required_text">
					<div class="acf-label"><label>Required Text</label></div>
					<div class="acf-input">
						<input type="text" name="acf[field_req_text]" required />
					</div>
				</div>
				<div class="acf-field acf-field-checkbox" data-name="checks">
					<div class="acf-label"><label>Checks</label></div>
					<div class="acf-input">
						<input type="checkbox" name="acf[field_checks][]" value="a" />
					</div>
				</div>
			</div>
			<div class="acf-form-submit">
				<input type="submit" class="acf-button button" value="Update" />
				<span class="acf-spinner"></span>
			</div>
		</form>
	`;
	return document.querySelector( 'form' );
}

describe( 'createNotice', () => {
	it( 'creates the classic error notice markup', () => {
		const notice = createNotice( 'Something went wrong' );

		expect( notice.tagName ).toBe( 'DIV' );
		expect( notice.classList.contains( 'acf-notice' ) ).toBe( true );
		expect( notice.classList.contains( '-error' ) ).toBe( true );
		expect( notice.classList.contains( 'acf-error-message' ) ).toBe( true );
		expect( notice.querySelector( 'p' ).textContent ).toBe(
			'Something went wrong'
		);
	} );

	it( 'renders the message as text, not HTML', () => {
		const notice = createNotice( '<script>alert(1)</script>' );

		expect( notice.querySelector( 'script' ) ).toBeNull();
		expect( notice.querySelector( 'p' ).textContent ).toBe(
			'<script>alert(1)</script>'
		);
	} );
} );

describe( 'getNativeErrors', () => {
	it( 'collects required-field violations with the native message', () => {
		const form = buildForm();

		const errors = getNativeErrors( form );

		expect( errors ).toHaveLength( 1 );
		expect( errors[ 0 ].input ).toBe( 'acf[field_req_text]' );
		expect( typeof errors[ 0 ].message ).toBe( 'string' );
		expect( errors[ 0 ].message.length ).toBeGreaterThan( 0 );
	} );

	it( 'returns no errors when constraints are satisfied', () => {
		const form = buildForm();
		form.querySelector( '[name="acf[field_req_text]"]' ).value = 'Filled';

		expect( getNativeErrors( form ) ).toHaveLength( 0 );
	} );

	it( 'skips disabled controls', () => {
		const form = buildForm();
		form.querySelector( '[name="acf[field_req_text]"]' ).disabled = true;

		expect( getNativeErrors( form ) ).toHaveLength( 0 );
	} );

	it( 'reports each control name once', () => {
		const form = buildForm();
		const wrap = form.querySelector(
			'.acf-field[data-name="required_text"] .acf-input'
		);
		const clone = wrap.querySelector( 'input' ).cloneNode();
		wrap.appendChild( clone );

		const errors = getNativeErrors( form );

		expect( errors ).toHaveLength( 1 );
	} );
} );

describe( 'findFieldByInputName', () => {
	it( 'finds a field by exact input name', () => {
		const form = buildForm();

		const field = findFieldByInputName( form, 'acf[field_req_text]' );

		expect( field ).not.toBeNull();
		expect( field.dataset.name ).toBe( 'required_text' );
	} );

	it( 'falls back to a prefix match for array-style names', () => {
		const form = buildForm();

		const field = findFieldByInputName( form, 'acf[field_checks]' );

		expect( field ).not.toBeNull();
		expect( field.dataset.name ).toBe( 'checks' );
	} );

	it( 'returns null for unknown names', () => {
		const form = buildForm();

		expect( findFieldByInputName( form, 'acf[missing]' ) ).toBeNull();
	} );
} );

describe( 'showFieldError / clearFieldError', () => {
	it( 'adds the acf-error class and prepends a notice to .acf-input', () => {
		const form = buildForm();

		const field = showFieldError( form, {
			input: 'acf[field_req_text]',
			message: 'Required Text value is required',
		} );

		expect( field.classList.contains( 'acf-error' ) ).toBe( true );
		const notice = field.querySelector( '.acf-input' ).firstElementChild;
		expect( notice.className ).toBe(
			'acf-notice -error acf-error-message'
		);
		expect( notice.textContent ).toBe( 'Required Text value is required' );
	} );

	it( 'replaces an existing notice instead of stacking them', () => {
		const form = buildForm();

		showFieldError( form, {
			input: 'acf[field_req_text]',
			message: 'First',
		} );
		showFieldError( form, {
			input: 'acf[field_req_text]',
			message: 'Second',
		} );

		const notices = form.querySelectorAll(
			'.acf-field[data-name="required_text"] .acf-notice'
		);
		expect( notices ).toHaveLength( 1 );
		expect( notices[ 0 ].textContent ).toBe( 'Second' );
	} );

	it( 'clearFieldError removes the class and the notice', () => {
		const form = buildForm();
		const field = showFieldError( form, {
			input: 'acf[field_req_text]',
			message: 'Required',
		} );

		clearFieldError( field );

		expect( field.classList.contains( 'acf-error' ) ).toBe( false );
		expect( field.querySelector( '.acf-notice' ) ).toBeNull();
	} );
} );

describe( 'buildSummaryMessage', () => {
	it( 'mirrors the classic single-field wording', () => {
		expect( buildSummaryMessage( [], 1, I18N ) ).toBe(
			'Validation failed. 1 field requires attention'
		);
	} );

	it( 'mirrors the classic multi-field wording', () => {
		expect( buildSummaryMessage( [], 3, I18N ) ).toBe(
			'Validation failed. 3 fields require attention'
		);
	} );

	it( 'appends global error messages', () => {
		expect(
			buildSummaryMessage(
				[ { input: false, message: 'Spam Detected' } ],
				0,
				I18N
			)
		).toBe( 'Validation failed. Spam Detected' );
	} );
} );

describe( 'showErrors / clearAllErrors', () => {
	it( 'renders field errors plus a form-level summary', () => {
		const form = buildForm();

		showErrors(
			form,
			[
				{
					input: 'acf[field_req_text]',
					message: 'Required Text value is required',
				},
			],
			I18N
		);

		expect( form.querySelectorAll( '.acf-field.acf-error' ) ).toHaveLength(
			1
		);
		const summary = form.firstElementChild;
		expect( summary.className ).toBe(
			'acf-notice -error acf-error-message'
		);
		expect( summary.textContent ).toBe(
			'Validation failed. 1 field requires attention'
		);
	} );

	it( 'deduplicates errors for the same input, keeping the last', () => {
		const form = buildForm();

		showErrors(
			form,
			[
				{ input: 'acf[field_req_text]', message: 'First' },
				{ input: 'acf[field_req_text]', message: 'Second' },
			],
			I18N
		);

		const notices = form.querySelectorAll(
			'.acf-field .acf-notice.acf-error-message'
		);
		expect( notices ).toHaveLength( 1 );
		expect( notices[ 0 ].textContent ).toBe( 'Second' );
	} );

	it( 'counts only errors whose input could be located', () => {
		const form = buildForm();

		showErrors(
			form,
			[
				{ input: 'acf[field_req_text]', message: 'Required' },
				{ input: 'acf[field_missing]', message: 'Ghost' },
			],
			I18N
		);

		expect( form.firstElementChild.textContent ).toBe(
			'Validation failed. 1 field requires attention'
		);
	} );

	it( 'clearAllErrors removes field errors and the summary', () => {
		const form = buildForm();
		showErrors(
			form,
			[ { input: 'acf[field_req_text]', message: 'Required' } ],
			I18N
		);

		clearAllErrors( form );

		expect( form.querySelector( '.acf-error' ) ).toBeNull();
		expect( form.querySelector( '.acf-notice' ) ).toBeNull();
	} );
} );

describe( 'lockForm / unlockForm', () => {
	it( 'lockForm disables the submit button and activates the spinner', () => {
		const form = buildForm();

		lockForm( form );

		const submit = form.querySelector( '[type="submit"]' );
		const spinner = form.querySelector( '.acf-spinner' );
		expect( submit.classList.contains( 'disabled' ) ).toBe( true );
		expect( submit.hasAttribute( 'disabled' ) ).toBe( true );
		expect( spinner.classList.contains( 'is-active' ) ).toBe( true );
		expect( spinner.style.display ).toBe( 'inline-block' );
	} );

	it( 'unlockForm re-enables the submit button and hides the spinner', () => {
		const form = buildForm();
		lockForm( form );

		unlockForm( form );

		const submit = form.querySelector( '[type="submit"]' );
		const spinner = form.querySelector( '.acf-spinner' );
		expect( submit.classList.contains( 'disabled' ) ).toBe( false );
		expect( submit.hasAttribute( 'disabled' ) ).toBe( false );
		expect( spinner.classList.contains( 'is-active' ) ).toBe( false );
		expect( spinner.style.display ).toBe( 'none' );
	} );
} );
