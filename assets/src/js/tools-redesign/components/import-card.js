/**
 * Import Card
 *
 * File upload card for importing SCF JSON, plus the Custom Post Type UI
 * import options when that plugin is active.
 *
 * @since SCF 6.5.0
 */

import { __ } from '@wordpress/i18n';
import {
	BaseControl,
	Button,
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
	FormFileUpload,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { submitToolForm } from '../form-bridge';

/**
 * Renders the import tool card.
 *
 * @param {Object} props        Component props.
 * @param {Object} props.config Data injected by the server.
 * @return {JSX.Element} The import card.
 */
export function ImportCard( { config } ) {
	const [ file, setFile ] = useState( null );
	const [ cptui, setCptui ] = useState( {} );
	const nonces = config.nonces || {};
	const cptuiData = config.cptui || {};
	const cptuiChoices = Object.keys( cptuiData.choices || {} );

	/**
	 * Posts the JSON import form with the selected file.
	 */
	function importJson() {
		if ( ! file ) {
			return;
		}

		submitToolForm( {
			nonce: nonces.import,
			args: {
				import_type: 'json',
			},
			file,
		} );
	}

	/**
	 * Posts the Custom Post Type UI import form.
	 */
	function importCptui() {
		const selected = Object.keys( cptui ).filter( ( key ) => cptui[ key ] );
		if ( ! selected.length ) {
			return;
		}

		submitToolForm( {
			nonce: nonces.import,
			args: {
				import_type: 'cptui',
				'acf_import_cptui[]': selected,
			},
		} );
	}

	return (
		<Card data-testid="import-card">
			<CardHeader>
				<h2 className="scf-tools-redesign__card-title">
					{ __( 'Import', 'secure-custom-fields' ) }
				</h2>
			</CardHeader>
			<CardBody>
				<FormFileUpload
					accept=".json,application/json"
					onChange={ ( event ) => setFile( event.target.files[ 0 ] ) }
					data-testid="import-file-input"
				>
					{ __( 'Select JSON File', 'secure-custom-fields' ) }
				</FormFileUpload>
				{ file && (
					<p
						className="scf-tools-redesign__file-name"
						data-testid="import-file-name"
					>
						{ file.name }
					</p>
				) }
				<BaseControl>
					<Button
						variant="primary"
						onClick={ importJson }
						disabled={ ! file }
						data-testid="import-json-button"
					>
						{ __( 'Import JSON', 'secure-custom-fields' ) }
					</Button>
				</BaseControl>
				{ cptuiChoices.length > 0 && (
					<>
						<h3 className="scf-tools-redesign__card-subtitle">
							{ __(
								'Import from Custom Post Type UI',
								'secure-custom-fields'
							) }
						</h3>
						{ cptuiChoices.map( ( key ) => (
							<CheckboxControl
								key={ key }
								label={ cptuiData.choices[ key ] }
								checked={ !! cptui[ key ] }
								onChange={ ( value ) =>
									setCptui( { ...cptui, [ key ]: value } )
								}
							/>
						) ) }
						{ cptuiData.overwriteWarning && (
							<p className="scf-tools-redesign__notice">
								{ __(
									'Importing a Post Type or Taxonomy with the same key as one that already exists will overwrite the settings for the existing Post Type or Taxonomy with those of the import.',
									'secure-custom-fields'
								) }
							</p>
						) }
						<Button
							variant="secondary"
							onClick={ importCptui }
							data-testid="import-cptui-button"
						>
							{ __(
								'Import from Custom Post Type UI',
								'secure-custom-fields'
							) }
						</Button>
					</>
				) }
			</CardBody>
		</Card>
	);
}
