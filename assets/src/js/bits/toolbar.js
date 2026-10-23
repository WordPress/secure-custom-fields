/**
 * RichText toolbar wiring for SCF bits.
 *
 * Inserts an Add SCF field button into the BlockControls when the current
 * paragraph/heading block is focused. Picking a bit replaces selection with
 * the persisted span shape.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { BlockControls } from '@wordpress/block-editor';
import {
	Button,
	Modal,
	TextControl,
	SelectControl,
} from '@wordpress/components';
import { useState, useMemo, createElement, Fragment } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';

const SUPPORTED_BLOCKS = [ 'core/paragraph', 'core/heading', 'core/list-item' ];

/**
 * Returns the persisted HTML representation of a bit span.
 *
 * @param {Object} attrs Bit attributes (bitName, fieldKey, target, fallback, format).
 * @return {string} HTML span markup safe to store inside a paragraph.
 */
export function renderBitSpan( attrs ) {
	const key = escapeAttr( attrs.fieldKey || attrs.key || '' );
	const target = escapeAttr( attrs.target || 'current' );
	const fallback = escapeAttr( attrs.fallback || '' );
	const format = escapeAttr( attrs.format || 'text' );
	const bitName = escapeAttr( attrs.bitName || 'scf/field' );

	return (
		`<span class="scf-field-bit" data-bit="${ bitName }"` +
		` data-key="${ key }" data-target="${ target }"` +
		` data-fallback="${ fallback }" data-format="${ format }">` +
		`${ fallback }</span>`
	);
}

function escapeAttr( value ) {
	return String( value )
		.replace( /&/g, '&amp;' )
		.replace( /"/g, '&quot;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );
}

/**
 * HOC: adds an SCF-bit button to supported blocks' BlockControls.
 *
 * @return {void}
 */
export function attachBitToolbar() {
	addFilter(
		'editor.BlockEdit',
		'secure-custom-fields/with-bits-toolbar',
		withBitsToolbar
	);
}

function withBitsToolbar( BlockEdit ) {
	return ( props ) => {
		if ( ! SUPPORTED_BLOCKS.includes( props.name ) ) {
			return createElement( BlockEdit, props );
		}

		const buttons = createElement( BitsToolbarButton, {
			onInsert: ( html ) => {
				const next = ( props.attributes?.content || '' ) + html;
				props.setAttributes( { content: next } );
			},
		} );

		return createElement(
			Fragment,
			null,
			createElement( BlockControls, null, buttons ),
			createElement( BlockEdit, props )
		);
	};
}

function BitsToolbarButton( { onInsert } ) {
	const [ isOpen, setOpen ] = useState( false );

	return createElement(
		Fragment,
		null,
		createElement( Button, {
			icon: 'shortcode',
			label: __( 'Insert SCF field', 'secure-custom-fields' ),
			onClick: () => setOpen( true ),
		} ),
		isOpen
			? createElement( BitPickerModal, {
					onCancel: () => setOpen( false ),
					onInsert: ( html ) => {
						setOpen( false );
						onInsert( html );
					},
			  } )
			: null
	);
}

function BitPickerModal( { onInsert, onCancel } ) {
	// eslint-disable-next-line react-hooks/exhaustive-deps -- window.scfFieldBits is mount-time stable.
	const payload = window.scfFieldBits || {};
	const bits = payload.bits || [];
	const fieldEntries = useMemo(
		() => Object.entries( payload.fields || {} ),
		[ payload ]
	);

	const initialBit = bits[ 0 ]?.name || 'scf/field';

	const [ bitName, setBitName ] = useState( initialBit );
	const [ fieldKey, setFieldKey ] = useState( '' );
	const [ fallback, setFallback ] = useState( '' );
	const [ format, setFormat ] = useState( 'text' );

	const fieldOptions = useMemo(
		() =>
			fieldEntries.map( ( [ value, label ] ) => ( {
				value,
				label,
			} ) ),
		[ fieldEntries ]
	);

	const insert = () => {
		const html = renderBitSpan( {
			bitName,
			fieldKey,
			fallback,
			format,
		} );
		onInsert( html );
	};

	return createElement(
		Modal,
		{
			title: __( 'Insert SCF field', 'secure-custom-fields' ),
			onRequestClose: onCancel,
		},
		createElement(
			'div',
			{ style: { display: 'grid', gap: '12px' } },
			bits.length > 1 &&
				createElement( SelectControl, {
					label: __( 'Type', 'secure-custom-fields' ),
					value: bitName,
					options: bits.map( ( b ) => ( {
						value: b.name,
						label: b.label || b.name,
					} ) ),
					onChange: setBitName,
				} ),
			createElement( SelectControl, {
				label: __( 'Field', 'secure-custom-fields' ),
				value: fieldKey,
				options: [
					{
						value: '',
						label: __( 'Select a field…', 'secure-custom-fields' ),
					},
					...fieldOptions,
				],
				onChange: setFieldKey,
			} ),
			createElement( SelectControl, {
				label: __( 'Format', 'secure-custom-fields' ),
				value: format,
				options: [
					{
						value: 'text',
						label: __( 'Plain text', 'secure-custom-fields' ),
					},
					{
						value: 'html',
						label: __(
							'HTML (allowed tags)',
							'secure-custom-fields'
						),
					},
				],
				onChange: setFormat,
			} ),
			createElement( TextControl, {
				label: __( 'Fallback', 'secure-custom-fields' ),
				help: __(
					'Shown when the field value is empty.',
					'secure-custom-fields'
				),
				value: fallback,
				onChange: setFallback,
			} ),
			createElement(
				'div',
				{
					style: {
						display: 'flex',
						gap: '8px',
						justifyContent: 'flex-end',
					},
				},
				createElement(
					Button,
					{ variant: 'tertiary', onClick: onCancel },
					__( 'Cancel', 'secure-custom-fields' )
				),
				createElement(
					Button,
					{
						variant: 'primary',
						onClick: insert,
						disabled: '' === fieldKey && '' === fallback,
					},
					__( 'Insert', 'secure-custom-fields' )
				)
			)
		)
	);
}
