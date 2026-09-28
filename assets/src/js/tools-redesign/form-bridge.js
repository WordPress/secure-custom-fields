/**
 * Form bridge helpers.
 *
 * The prototype does not add new endpoints. Submitting an action builds a
 * form and posts it to the Tools page so the existing nonce checks and
 * tool submit() methods keep running unchanged.
 *
 * @since SCF 6.5.0
 */

/**
 * Submits a form to the Tools page.
 *
 * @param {Object} options       Submission options.
 * @param {string} options.nonce Nonce created for the tool being submitted.
 * @param {Object} options.args  Additional hidden fields posted with the form.
 * @param {File}   options.file  Optional file to upload with the submission.
 */
export function submitToolForm( { nonce, args = {}, file } = {} ) {
	const form = document.createElement( 'form' );
	form.method = 'POST';
	form.action = window.location.href;

	if ( file ) {
		form.enctype = 'multipart/form-data';
	}

	appendHiddenField( form, '_acf_nonce', nonce );

	Object.keys( args ).forEach( ( name ) => {
		const value = args[ name ];
		if ( Array.isArray( value ) ) {
			value.forEach( ( item ) => appendHiddenField( form, name, item ) );
		} else {
			appendHiddenField( form, name, value );
		}
	} );

	if ( file ) {
		appendFileField( form, file );
	}

	document.body.appendChild( form );
	form.submit();
}

/**
 * Appends a hidden input to a form.
 *
 * @param {HTMLFormElement} form  The form element.
 * @param {string}          name  Field name.
 * @param {string}          value Field value.
 */
function appendHiddenField( form, name, value ) {
	const input = document.createElement( 'input' );
	input.type = 'hidden';
	input.name = name;
	input.value = String( value ?? '' );
	form.appendChild( input );
}

/**
 * Appends a file input carrying the selected file to a form.
 *
 * @param {HTMLFormElement} form The form element.
 * @param {File}            file The file to upload.
 */
function appendFileField( form, file ) {
	const input = document.createElement( 'input' );
	input.type = 'file';
	input.name = 'acf_import_file';

	if ( typeof DataTransfer === 'function' ) {
		const transfer = new DataTransfer();
		transfer.items.add( file );
		input.files = transfer.files;
	}

	form.appendChild( input );
}
