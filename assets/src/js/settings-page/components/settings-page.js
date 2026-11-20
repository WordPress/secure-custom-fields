/**
 * The default tab UI: sections of fields, saved with the header Save button.
 *
 * @since SCF 6.9.10
 */

import { useEffect, useRef, useState } from '@wordpress/element';

import FieldRow from './field-row';
import { useHeaderSaveButton } from '../header-save-button';
import {
	applyConfirmedSettings,
	getChangedSettings,
	getInitialSettings,
	getSections,
	saveSettings,
} from '../utils';

/**
 * Renders a tab's sections and saves its changed settings.
 *
 * @param {Object}   props                        Props.
 * @param {Object}   props.config                 The settings page config for this tab.
 * @param {Function} props.addNotice              Shows a snackbar notice.
 * @param {Function} props.onUnsavedChangesChange Called when the unsaved changes state changes.
 */
export default function SettingsPage( {
	config,
	addNotice,
	onUnsavedChangesChange,
} ) {
	const strings = config.strings || {};
	const sections = getSections( config.tabData );
	const [ values, setValues ] = useState( () =>
		getInitialSettings( sections )
	);
	const [ saved, setSaved ] = useState( () =>
		getInitialSettings( sections )
	);
	const [ isSaving, setIsSaving ] = useState( false );
	const valuesRef = useRef( values );
	valuesRef.current = values;

	const changed = getChangedSettings( sections, values, saved );
	const isDirty = Object.keys( changed ).length > 0;

	// The app owns navigation, so report unsaved changes up for it to guard tab switches and page unloads.
	useEffect( () => {
		onUnsavedChangesChange?.( isDirty );
	}, [ isDirty, onUnsavedChangesChange ] );

	useEffect(
		() => () => onUnsavedChangesChange?.( false ),
		[ onUnsavedChangesChange ]
	);

	const save = async () => {
		const sent = getChangedSettings( sections, valuesRef.current, saved );

		if ( isSaving || Object.keys( sent ).length === 0 ) {
			return;
		}

		setIsSaving( true );

		try {
			const data = await saveSettings( config, sent );
			const result = applyConfirmedSettings(
				sections,
				valuesRef.current,
				sent,
				data.settings
			);

			setSaved( ( prev ) => ( { ...prev, ...result.saved } ) );
			setValues( ( prev ) => {
				const next = { ...prev };
				Object.keys( result.saved ).forEach( ( key ) => {
					next[ key ] = result.values[ key ];
				} );
				return next;
			} );

			// Some settings may have been skipped or failed to save while others saved. They stay unsaved, so say which.
			const notSaved = [
				...( Array.isArray( data.skipped ) ? data.skipped : [] ),
				...( Array.isArray( data.failed ) ? data.failed : [] ),
			];

			if ( notSaved.length > 0 ) {
				addNotice(
					strings.settingsNotSaved
						? strings.settingsNotSaved.replace(
								'%s',
								notSaved.join( ', ' )
						  )
						: strings.saveErrorMessage,
					'error'
				);
			} else {
				addNotice( strings.saveSuccessMessage );
			}
		} catch ( error ) {
			addNotice( error.message || strings.saveErrorMessage, 'error' );
		} finally {
			setIsSaving( false );
		}
	};

	useHeaderSaveButton( {
		visible: sections.length > 0,
		disabled: ! isDirty || isSaving,
		busy: isSaving,
		label: isSaving ? strings.saving : strings.save,
		onClick: save,
	} );

	if ( sections.length === 0 ) {
		return null;
	}

	return (
		<form
			className="scf-settings-form"
			onSubmit={ ( event ) => {
				event.preventDefault();
				save();
			} }
		>
			{ sections.map( ( section, index ) => (
				<section
					key={ `${ index }-${ section.title }` }
					className="scf-settings-section"
				>
					{ section.title && (
						<div className="scf-settings-section__header">
							<h2 className="scf-settings-section__title">
								{ section.title }
							</h2>
						</div>
					) }
					{ section.fields.map( ( field ) => (
						<FieldRow
							key={ field.key }
							field={ field }
							value={ values[ field.key ] }
							strings={ strings }
							onChange={ ( next ) =>
								setValues( ( prev ) => ( {
									...prev,
									[ field.key ]: next,
								} ) )
							}
						/>
					) ) }
				</section>
			) ) }
		</form>
	);
}
