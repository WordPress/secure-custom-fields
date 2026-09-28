/**
 * PHP Export Result
 *
 * Shows the generated PHP for the selected items when the export tool
 * is loaded in its keys mode.
 *
 * @since SCF 6.5.0
 */

import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	TextareaControl,
} from '@wordpress/components';

/**
 * Renders the generated PHP code with a copy button.
 *
 * @param {Object} props      Component props.
 * @param {string} props.code Generated PHP code.
 * @return {JSX.Element} The PHP export result.
 */
export function PhpResult( { code } ) {
	/**
	 * Copies the generated code to the clipboard.
	 */
	function copyCode() {
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( code );
			return;
		}

		// Clipboard API is unavailable on insecure origins.
		const textarea = document.querySelector(
			'[data-testid="php-export-textarea"]'
		);
		if ( ! textarea ) {
			return;
		}

		textarea.select();
		try {
			document.execCommand( 'copy' );
		} catch ( error ) {
			// Do nothing.
		}
	}

	return (
		<Card data-testid="php-result">
			<CardHeader>
				<h2 className="scf-tools-redesign__card-title">
					{ __( 'Export - Generate PHP', 'secure-custom-fields' ) }
				</h2>
			</CardHeader>
			<CardBody>
				<TextareaControl
					readOnly
					value={ code }
					rows={ 16 }
					data-testid="php-export-textarea"
				/>
				<Button
					variant="secondary"
					onClick={ copyCode }
					data-testid="php-copy-button"
				>
					{ __( 'Copy to clipboard', 'secure-custom-fields' ) }
				</Button>
			</CardBody>
		</Card>
	);
}

/**
 * Renders the back link to the tools grid.
 *
 * @return {JSX.Element} The back link.
 */
export function PhpResultBackLink() {
	const url = new URL( window.location.href );
	url.searchParams.delete( 'tool' );
	url.searchParams.delete( 'keys' );
	url.searchParams.delete( 'post_type_keys' );
	url.searchParams.delete( 'taxonomy_keys' );
	url.searchParams.delete( 'ui_options_page_keys' );

	return (
		<p>
			<a href={ url.href } data-testid="php-back-link">
				{ __( 'Back to all tools', 'secure-custom-fields' ) }
			</a>
		</p>
	);
}
