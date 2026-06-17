/**
 * SCF front-end form view bundle.
 *
 * A jQuery-free, @wordpress/interactivity-powered replacement for the classic
 * `acf-input` stack on `acf_form()` pages, enabled by the opt-in
 * `frontend_interactivity_form` setting and only when every rendered field is
 * a "simple" type (see includes/forms/form-front-view.php).
 *
 * Validation contract (matching the classic stack's semantics):
 *
 * 1. Native constraint validation (required, email/url format, number
 *    min/max/step) runs first as a no-network fast path, surfacing the
 *    browser's `validationMessage` in classic SCF error markup — exactly the
 *    message source the classic validator uses for `invalid` events.
 * 2. The same `acf/validate_save_post` admin-ajax endpoint the classic stack
 *    calls is then queried via `fetch()`, so server-side rules (required
 *    checkbox groups, custom `acf/validate_value` filters, honeypot) remain
 *    authoritative.
 * 3. On success the form is submitted natively, leaving the
 *    includes/forms/form-front.php POST processing path completely unchanged.
 */
import * as interactivity from '@wordpress/interactivity';
import {
	clearAllErrors,
	clearFieldError,
	getNativeErrors,
	lockForm,
	showErrors,
	unlockForm,
} from './scf-form-validation';

const { store } = interactivity;

// `withSyncEvent` only exists in newer Interactivity API versions (WP 6.8+).
// On older supported versions (6.5–6.7) handlers are synchronous by default,
// so an identity fallback preserves the same behavior. The lookup must stay a
// runtime property access on the namespace object (not a named import, which
// would fail to link on WP < 6.8), hence the hasOwnProperty guard that forces
// webpack to keep the `import * as` form.
const withSyncEvent = Object.prototype.hasOwnProperty.call(
	interactivity,
	'withSyncEvent'
)
	? interactivity.withSyncEvent
	: ( callback ) => callback;

const { state } = store( 'scf/form', {
	actions: {
		/**
		 * Intercepts form submission to validate before posting.
		 *
		 * @param {Event} event The submit event.
		 */
		handleSubmit: withSyncEvent( ( event ) => {
			// If the classic stack is also present on this page (e.g. another
			// form fell back to it), defer entirely to its validator to avoid
			// double-handling.
			if ( window.acf && window.acf.validateForm ) {
				return;
			}

			const form = event.currentTarget;
			event.preventDefault();

			clearAllErrors( form );

			const i18n = state.i18n || {};

			// Fast path: native constraint validation, no network needed.
			const nativeErrors = getNativeErrors( form );
			if ( nativeErrors.length ) {
				showErrors( form, nativeErrors, i18n );
				return;
			}

			lockForm( form );

			// Authoritative path: the same AJAX validation endpoint the
			// classic stack uses before submitting.
			const body = new window.FormData( form );
			body.append( 'action', 'acf/validate_save_post' );
			body.append( 'nonce', state.nonce || '' );

			window
				.fetch( state.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body,
				} )
				.then( ( response ) => response.json() )
				.then( ( json ) => {
					const data = json && json.data ? json.data : null;

					if ( data && data.valid ) {
						// Server-validated: submit natively (bypassing this
						// handler) so the unchanged form-front.php POST
						// pipeline validates again and saves.
						form.submit();
						return;
					}

					unlockForm( form );
					const errors =
						data && data.errors && data.errors.length
							? data.errors
							: [
									{
										input: false,
										message:
											i18n.validationFailed ||
											'Validation failed',
									},
							  ];
					showErrors( form, errors, i18n );
				} )
				.catch( () => {
					// Network/parse failure: fall back to a native POST. The
					// server remains the validation authority on that path.
					form.submit();
				} );
		} ),

		/**
		 * Clears a field's error state as soon as the user edits it.
		 *
		 * @param {Event} event The input/change event.
		 */
		clearError( event ) {
			const target = event.target;
			if ( ! target || typeof target.closest !== 'function' ) {
				return;
			}
			const field = target.closest( '.acf-field.acf-error' );
			if ( field ) {
				clearFieldError( field );
			}
		},
	},
} );
