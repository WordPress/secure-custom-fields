import { DataForm } from '@wordpress/dataviews/wp';
import { createRoot, createElement, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	getTaxonomyEdits,
	getTaxonomyFields,
	getTaxonomyFormData,
} from './scf-taxonomy-editor-adapter';

const MOUNTED_CLASS = 'scf-taxonomy-editor-active';

/**
 * Returns the classic markup wrapper that holds the taxonomy fields.
 *
 * @return {HTMLElement|null} The field wrapper, or null when not present.
 */
const getFieldWrapper = () =>
	document.getElementById( 'scf-taxonomy-editor-fields' );

/**
 * Writes a value into the classic input used for form submission.
 *
 * True/false fields render a hidden input and a checkbox with the same name,
 * so the checkbox is updated first and the hidden fallback is kept in sync.
 *
 * @param {string} name  The input name.
 * @param {*}      value The value to store.
 */
export const syncClassicInput = ( name, value ) => {
	const wrapper = getFieldWrapper();
	if ( ! wrapper ) {
		return;
	}

	const escaped =
		window.CSS && window.CSS.escape ? window.CSS.escape( name ) : name;
	const checkbox = wrapper.querySelector(
		`input[type="checkbox"][name="${ escaped }"]`
	);
	const hidden = wrapper.querySelector(
		`input[type="hidden"][name="${ escaped }"]`
	);

	if ( checkbox ) {
		checkbox.checked = Boolean( value );
		checkbox.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	if ( hidden ) {
		hidden.value = value ? '1' : '0';
		return;
	}

	if ( checkbox ) {
		return;
	}

	// Plain text inputs do not have a hidden companion.
	const input = wrapper.querySelector( `input[name="${ escaped }"]` );
	if ( input ) {
		input.value = value ?? '';
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}
};

const root = document.getElementById( 'scf-taxonomy-editor' );

if ( root && window.scfTaxonomyEditor ) {
	const fields = getTaxonomyFields( __ );
	const form = {
		layout: { type: 'regular' },
		fields: fields.map( ( field ) => field.id ),
	};

	const TaxonomyEditor = () => {
		const [ data, setData ] = useState( () =>
			getTaxonomyFormData( window.scfTaxonomyEditor.data )
		);

		const onChange = ( edits ) => {
			setData( ( current ) => ( { ...current, ...edits } ) );

			Object.entries( getTaxonomyEdits( edits ) ).forEach(
				( [ inputName, value ] ) => {
					syncClassicInput( inputName, value );
				}
			);
		};

		return createElement( DataForm, {
			data,
			fields,
			form,
			onChange,
		} );
	};

	// Hide the duplicated classic fields only after the editor has mounted, so a
	// script failure leaves the original fields usable.
	getFieldWrapper()?.classList.add( MOUNTED_CLASS );

	createRoot( root ).render( createElement( TaxonomyEditor ) );
}
