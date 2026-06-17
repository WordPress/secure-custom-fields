/**
 * Validation and error-rendering helpers for the SCF front-end form view
 * bundle.
 *
 * These helpers are intentionally pure (no module-level state, no framework
 * imports) so they can be unit tested in isolation. They reproduce the exact
 * markup and CSS classes produced by the classic jQuery validator
 * (assets/src/js/_acf-validation.js and assets/src/js/_acf-notice.js):
 *
 * - The field wrapper (`.acf-field`) gains the `acf-error` class.
 * - The message is rendered as
 *   `<div class="acf-notice -error acf-error-message"><p>…</p></div>`
 *   prepended to the field's `.acf-input` wrap.
 * - A form-level summary notice with the same classes is prepended to the
 *   form element.
 */

/**
 * Escapes a value for use inside a quoted CSS attribute selector.
 *
 * @param {string} value The raw attribute value.
 * @return {string} The escaped value.
 */
function escapeAttributeValue( value ) {
	return String( value ).replace( /(["\\])/g, '\\$1' );
}

/**
 * Creates an error notice element matching the classic validator markup.
 *
 * @param {string} message The error message (plain text).
 * @return {HTMLElement} The notice element.
 */
export function createNotice( message ) {
	const notice = document.createElement( 'div' );
	notice.className = 'acf-notice -error acf-error-message';

	const paragraph = document.createElement( 'p' );
	paragraph.textContent = String( message );
	notice.appendChild( paragraph );

	return notice;
}

/**
 * Collects native constraint-validation errors (required, email/url format,
 * number min/max/step, …) for every named control in the form.
 *
 * Mirrors the classic stack, which surfaces the browser's own
 * `validationMessage` for fields that fail native validation.
 *
 * @param {HTMLFormElement} form The form element.
 * @return {Array<{input: string, message: string}>} The collected errors.
 */
export function getNativeErrors( form ) {
	const errors = [];
	const seen = [];

	Array.from( form.elements ).forEach( ( element ) => {
		if ( ! element.name || element.disabled ) {
			return;
		}
		if ( typeof element.checkValidity !== 'function' ) {
			return;
		}
		if ( element.willValidate === false ) {
			return;
		}
		if ( element.checkValidity() ) {
			return;
		}
		if ( seen.indexOf( element.name ) !== -1 ) {
			return;
		}
		seen.push( element.name );
		errors.push( {
			input: element.name,
			message: element.validationMessage,
		} );
	} );

	return errors;
}

/**
 * Finds the `.acf-field` wrapper for a given input name.
 *
 * Matches the classic validator's lookup: exact `name` match first, then a
 * prefix match for array-style names (e.g. checkbox groups posting
 * `acf[field_x][]`).
 *
 * @param {HTMLFormElement} form The form element.
 * @param {string}          name The input's name attribute.
 * @return {HTMLElement|null} The field wrapper, or null when not found.
 */
export function findFieldByInputName( form, name ) {
	const escaped = escapeAttributeValue( name );
	const input =
		form.querySelector( '[name="' + escaped + '"]' ) ||
		form.querySelector( '[name^="' + escaped + '"]' );

	if ( ! input ) {
		return null;
	}

	return input.closest( '.acf-field' );
}

/**
 * Renders a single field-level error.
 *
 * @param {HTMLFormElement}                  form  The form element.
 * @param {{input: string, message: string}} error The error to render.
 * @return {HTMLElement|null} The errored field wrapper, or null when the
 *                            input could not be located.
 */
export function showFieldError( form, error ) {
	const field = findFieldByInputName( form, error.input );
	if ( ! field ) {
		return null;
	}

	field.classList.add( 'acf-error' );

	const wrap = field.querySelector( '.acf-input' ) || field;
	const existing = wrap.querySelector( ':scope > .acf-notice' );
	if ( existing ) {
		existing.remove();
	}
	wrap.insertBefore( createNotice( error.message ), wrap.firstChild );

	return field;
}

/**
 * Clears the error state of a single field wrapper.
 *
 * @param {HTMLElement} field The `.acf-field` wrapper.
 */
export function clearFieldError( field ) {
	field.classList.remove( 'acf-error' );
	field
		.querySelectorAll( '.acf-notice.acf-error-message' )
		.forEach( ( notice ) => notice.remove() );
}

/**
 * Clears every field-level error and the form-level summary notice.
 *
 * @param {HTMLFormElement} form The form element.
 */
export function clearAllErrors( form ) {
	form.querySelectorAll( '.acf-field.acf-error' ).forEach( clearFieldError );
	form.querySelectorAll( ':scope > .acf-notice.acf-error-message' ).forEach(
		( notice ) => notice.remove()
	);
}

/**
 * Builds the form-level summary message, mirroring the classic validator's
 * wording ("Validation failed. 1 field requires attention").
 *
 * @param {Array<{input: (string|false), message: string}>} globalErrors    Errors without an input.
 * @param {number}                                          fieldErrorCount Number of rendered field errors.
 * @param {Object}                                          i18n            Translated strings.
 * @return {string} The summary message.
 */
export function buildSummaryMessage( globalErrors, fieldErrorCount, i18n ) {
	let message = i18n.validationFailed || 'Validation failed';

	globalErrors.forEach( ( error ) => {
		message += '. ' + error.message;
	} );

	if ( fieldErrorCount === 1 ) {
		message +=
			'. ' +
			( i18n.oneFieldRequiresAttention || '1 field requires attention' );
	} else if ( fieldErrorCount > 1 ) {
		message +=
			'. ' +
			(
				i18n.fieldsRequireAttention || '%d fields require attention'
			).replace( '%d', fieldErrorCount );
	}

	return message;
}

/**
 * Renders a set of validation errors: field-level notices plus a form-level
 * summary, using the classic validator's markup and wording.
 *
 * @param {HTMLFormElement}                                 form   The form element.
 * @param {Array<{input: (string|false), message: string}>} errors The errors to render.
 * @param {Object}                                          i18n   Translated strings.
 */
export function showErrors( form, errors, i18n ) {
	const fieldErrors = [];
	const fieldInputs = [];
	const globalErrors = [];

	errors.forEach( ( error ) => {
		if ( ! error.input ) {
			globalErrors.push( error );
			return;
		}
		const index = fieldInputs.indexOf( error.input );
		if ( index > -1 ) {
			fieldErrors[ index ] = error;
		} else {
			fieldErrors.push( error );
			fieldInputs.push( error.input );
		}
	} );

	let errorCount = 0;
	let scrollTo = null;

	fieldErrors.forEach( ( error ) => {
		const field = showFieldError( form, error );
		if ( field ) {
			errorCount++;
			if ( ! scrollTo ) {
				scrollTo = field;
			}
		}
	} );

	const summary = createNotice(
		buildSummaryMessage( globalErrors, errorCount, i18n )
	);
	form.insertBefore( summary, form.firstChild );

	const scrollTarget = scrollTo || summary;
	if ( typeof scrollTarget.scrollIntoView === 'function' ) {
		scrollTarget.scrollIntoView( { block: 'center' } );
	}
}

/**
 * Returns the wrapper that contains the form's submit button and spinner.
 *
 * @param {HTMLFormElement} form The form element.
 * @return {HTMLElement} The submit wrapper (falls back to the form).
 */
function findSubmitWrap( form ) {
	return form.querySelector( '.acf-form-submit' ) || form;
}

/**
 * Locks a form during async work: disables the submit button(s) and shows
 * the `.acf-spinner`, mirroring the classic `acf.lockForm()`.
 *
 * @param {HTMLFormElement} form The form element.
 */
export function lockForm( form ) {
	const wrap = findSubmitWrap( form );

	wrap.querySelectorAll( '.button, [type="submit"]' ).forEach( ( submit ) => {
		submit.classList.add( 'disabled' );
		submit.setAttribute( 'disabled', 'disabled' );
	} );

	const spinners = wrap.querySelectorAll( '.spinner, .acf-spinner' );
	spinners.forEach( ( spinner ) => {
		spinner.classList.remove( 'is-active' );
		spinner.style.display = 'none';
	} );

	const spinner = spinners[ spinners.length - 1 ];
	if ( spinner ) {
		spinner.classList.add( 'is-active' );
		spinner.style.display = 'inline-block';
	}
}

/**
 * Unlocks a form: re-enables the submit button(s) and hides spinners,
 * mirroring the classic `acf.unlockForm()`.
 *
 * @param {HTMLFormElement} form The form element.
 */
export function unlockForm( form ) {
	const wrap = findSubmitWrap( form );

	wrap.querySelectorAll( '.button, [type="submit"]' ).forEach( ( submit ) => {
		submit.classList.remove( 'disabled' );
		submit.removeAttribute( 'disabled' );
	} );

	wrap.querySelectorAll( '.spinner, .acf-spinner' ).forEach( ( spinner ) => {
		spinner.classList.remove( 'is-active' );
		spinner.style.display = 'none';
	} );
}
