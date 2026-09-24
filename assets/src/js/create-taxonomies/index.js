/**
 * Create Taxonomy editor sidebar panel.
 *
 * Adds a document settings panel to the block editor for creating a new SCF
 * taxonomy in context, bound to the post type currently being edited. Gated
 * in PHP on the create_taxonomies beta feature.
 *
 * @since SCF 6.9.6
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { store as editorStore } from '@wordpress/editor';
import { store as coreDataStore } from '@wordpress/core-data';
import { useSelect, useDispatch, select } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	TextControl,
	ToggleControl,
	Spinner,
} from '@wordpress/components';

import {
	buildTaxonomyPayload,
	slugifyTaxonomyKey,
	validateTaxonomyForm,
} from './helpers';

// PluginDocumentSettingPanel moved into the @wordpress/editor package in
// WordPress 6.6. On older versions it is only exposed from @wordpress/edit-post.
// Resolve it at runtime so the panel keeps working on every supported version,
// since the package held the component too late to import it from one place.
const PluginDocumentSettingPanel =
	window.wp?.editor?.PluginDocumentSettingPanel ||
	window.wp?.editPost?.PluginDocumentSettingPanel;

const CreateTaxonomyPanel = () => {
	const postType = useSelect(
		( selectStore ) =>
			selectStore( editorStore )?.getCurrentPostType?.() ?? '',
		[]
	);

	// The panel only makes sense for content post types, so it is hidden on the
	// internal wp_* entities (templates, reusable blocks) the post editor also
	// renders, and on any post type that is not shown in the admin.
	const isSupportedPostType = useSelect(
		( selectStore ) => {
			if ( ! postType || postType.startsWith( 'wp_' ) ) {
				return false;
			}

			const postTypeObject =
				selectStore( coreDataStore )?.getPostType?.( postType );

			return Boolean(
				postTypeObject?.viewable && postTypeObject?.visibility?.show_ui
			);
		},
		[ postType ]
	);

	const { savePost } = useDispatch( editorStore );

	const [ isOpen, setIsOpen ] = useState( false );
	const [ singularLabel, setSingularLabel ] = useState( '' );
	const [ pluralLabel, setPluralLabel ] = useState( '' );
	const [ taxonomy, setTaxonomy ] = useState( '' );
	const [ hierarchical, setHierarchical ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ isSaving, setIsSaving ] = useState( false );

	// Keep the key in step with the singular label until the user edits it.
	const onSingularLabelChange = ( value ) => {
		setSingularLabel( value );

		if ( ! taxonomy || taxonomy === slugifyTaxonomyKey( singularLabel ) ) {
			setTaxonomy( slugifyTaxonomyKey( value ) );
		}
	};

	const onSubmit = () => {
		const values = { singularLabel, pluralLabel, taxonomy, hierarchical };
		const errors = validateTaxonomyForm( values );

		if ( Object.keys( errors ).length ) {
			setError( Object.values( errors )[ 0 ] );
			return;
		}

		setError( '' );
		setIsSaving( true );

		apiFetch( {
			path: '/scf/v1/taxonomies',
			method: 'POST',
			data: buildTaxonomyPayload( values, postType ),
		} )
			.then( async () => {
				// A new taxonomy is registered on the next request, so reload to
				// make it available on the current screen. Save first when the post
				// has unsaved edits, otherwise the reload would discard them.
				if ( select( editorStore ).isEditedPostDirty?.() && savePost ) {
					await savePost();
				}
				window.location.reload();
			} )
			.catch( ( response ) => {
				setIsSaving( false );
				setError(
					response?.message ||
						__(
							'The taxonomy could not be created.',
							'secure-custom-fields'
						)
				);
			} );
	};

	if ( ! isSupportedPostType ) {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name="scf-create-taxonomy"
			title={ __( 'Create Taxonomy', 'secure-custom-fields' ) }
			className="scf-create-taxonomy-panel"
		>
			{ ! isOpen && (
				<Button variant="secondary" onClick={ () => setIsOpen( true ) }>
					{ __( 'New Taxonomy', 'secure-custom-fields' ) }
				</Button>
			) }

			{ isOpen && (
				<>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }

					<TextControl
						label={ __( 'Singular Label', 'secure-custom-fields' ) }
						value={ singularLabel }
						onChange={ onSingularLabelChange }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<TextControl
						label={ __( 'Plural Label', 'secure-custom-fields' ) }
						value={ pluralLabel }
						onChange={ setPluralLabel }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<TextControl
						label={ __( 'Taxonomy Key', 'secure-custom-fields' ) }
						value={ taxonomy }
						onChange={ setTaxonomy }
						maxLength={ 32 }
						help={ __(
							'Lower case letters, numbers, underscores and dashes only. Max 32 characters.',
							'secure-custom-fields'
						) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<ToggleControl
						label={ __( 'Hierarchical', 'secure-custom-fields' ) }
						checked={ hierarchical }
						onChange={ setHierarchical }
						help={ __(
							'Hierarchical taxonomies can have descendants, like categories.',
							'secure-custom-fields'
						) }
						__nextHasNoMarginBottom
					/>

					<div className="scf-create-taxonomy-panel__actions">
						<Button
							variant="primary"
							onClick={ onSubmit }
							isBusy={ isSaving }
							disabled={ isSaving }
						>
							{ __( 'Create Taxonomy', 'secure-custom-fields' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => {
								setIsOpen( false );
								setError( '' );
							} }
							disabled={ isSaving }
						>
							{ __( 'Cancel', 'secure-custom-fields' ) }
						</Button>
						{ isSaving && <Spinner /> }
					</div>
				</>
			) }
		</PluginDocumentSettingPanel>
	);
};

registerPlugin( 'scf-create-taxonomy', {
	render: PluginDocumentSettingPanel ? CreateTaxonomyPanel : () => null,
} );
