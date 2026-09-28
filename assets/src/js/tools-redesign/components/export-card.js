/**
 * Export Card
 *
 * Selection lists for field groups, post types, taxonomies, and options
 * pages with the JSON export and PHP generation actions.
 *
 * @since SCF 6.5.0
 */

import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { submitToolForm } from '../form-bridge';

const ITEM_GROUPS = [
	{
		type: 'acf-field-group',
		label: __( 'Select Field Groups', 'secure-custom-fields' ),
		postKey: 'keys',
	},
	{
		type: 'acf-post-type',
		label: __( 'Select Post Types', 'secure-custom-fields' ),
		postKey: 'post_type_keys',
	},
	{
		type: 'acf-taxonomy',
		label: __( 'Select Taxonomies', 'secure-custom-fields' ),
		postKey: 'taxonomy_keys',
	},
	{
		type: 'acf-ui-options-page',
		label: __( 'Select Options Pages', 'secure-custom-fields' ),
		postKey: 'ui_options_page_keys',
	},
];

/**
 * Renders the export tool card.
 *
 * @param {Object} props        Component props.
 * @param {Object} props.config Data injected by the server.
 * @return {JSX.Element} The export card.
 */
export function ExportCard( { config } ) {
	const [ selected, setSelected ] = useState( {} );
	const nonces = config.nonces || {};
	const items = config.items || {};

	/**
	 * Toggles the selection state of one exportable item.
	 *
	 * @param {string}  key   Item key.
	 * @param {boolean} value Whether the item is selected.
	 */
	function toggleItem( key, value ) {
		setSelected( { ...selected, [ key ]: value } );
	}

	/**
	 * Collects the selected keys for one internal post type.
	 *
	 * @param {string} type Internal post type.
	 * @return {Array} Selected keys.
	 */
	function selectedKeys( type ) {
		return ( items[ type ] || [] )
			.filter( ( item ) => selected[ item.key ] )
			.map( ( item ) => item.key );
	}

	/**
	 * Posts the export form with the chosen action.
	 *
	 * @param {string} action Either download or generate.
	 */
	function exportAction( action ) {
		const args = { action };
		let hasSelection = false;

		ITEM_GROUPS.forEach( ( { type, postKey } ) => {
			const keys = selectedKeys( type );
			if ( keys.length ) {
				hasSelection = true;
				args[ `${ postKey }[]` ] = keys;
			}
		} );

		if ( ! hasSelection ) {
			return;
		}

		submitToolForm( {
			nonce: nonces.export,
			args,
		} );
	}

	return (
		<Card data-testid="export-card">
			<CardHeader>
				<h2 className="scf-tools-redesign__card-title">
					{ __( 'Export', 'secure-custom-fields' ) }
				</h2>
			</CardHeader>
			<CardBody>
				{ ITEM_GROUPS.map( ( { type, label } ) => {
					const typeItems = items[ type ] || [];

					if ( ! typeItems.length ) {
						return null;
					}

					return (
						<fieldset
							key={ type }
							data-testid={ `export-group-${ type }` }
						>
							<legend className="scf-tools-redesign__card-subtitle">
								{ label }
							</legend>
							{ typeItems.map( ( item ) => (
								<CheckboxControl
									key={ item.key }
									label={ item.title }
									checked={ !! selected[ item.key ] }
									onChange={ ( value ) =>
										toggleItem( item.key, value )
									}
								/>
							) ) }
						</fieldset>
					);
				} ) }
				<Button
					variant="primary"
					onClick={ () => exportAction( 'download' ) }
					data-testid="export-json-button"
				>
					{ __( 'Export As JSON', 'secure-custom-fields' ) }
				</Button>
				<Button
					variant="secondary"
					onClick={ () => exportAction( 'generate' ) }
					data-testid="export-php-button"
				>
					{ __( 'Generate PHP', 'secure-custom-fields' ) }
				</Button>
			</CardBody>
		</Card>
	);
}
