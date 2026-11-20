/**
 * Render tests for the "Connect to a field" panel in the post editor.
 */

import { fireEvent, render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import { addFilter } from '@wordpress/hooks';
import { usePostEditorFields } from '../../../assets/src/js/bindings/hooks';

jest.mock( '@wordpress/hooks', () => ( {
	addFilter: jest.fn(),
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
	sprintf: ( format, ...args ) =>
		args.reduce( ( text, arg ) => text.replace( '%s', arg ), format ),
} ) );

const mockUpdateBlockBindings = jest.fn();

jest.mock( '@wordpress/block-editor', () => ( {
	InspectorControls: ( { children } ) => <div>{ children }</div>,
	useBlockBindingsUtils: () => ( {
		updateBlockBindings: mockUpdateBlockBindings,
		removeAllBlockBindings: jest.fn(),
	} ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	ComboboxControl: ( { label, options, onChange } ) => (
		<select
			aria-label={ label }
			onChange={ ( event ) => onChange( event.target.value ) }
		>
			{ options.map( ( option ) => (
				<option key={ option.value } value={ option.value }>
					{ option.label }
				</option>
			) ) }
		</select>
	),
	ExternalLink: ( { href, children } ) => <a href={ href }>{ children }</a>,
	PanelBody: ( { title, children } ) => (
		<section>
			<h2>{ title }</h2>
			{ children }
		</section>
	),
	__experimentalToolsPanel: ( { label, children } ) => (
		<section>
			<h2>{ label }</h2>
			{ children }
		</section>
	),
	__experimentalToolsPanelItem: ( { children } ) => <>{ children }</>,
} ) );

jest.mock( '../../../assets/src/js/bindings/hooks', () => ( {
	useSiteEditorContext: () => ( {
		isSiteEditor: false,
		templatePostType: null,
	} ),
	usePostEditorFields: jest.fn( () => ( {} ) ),
	useSiteEditorFields: () => ( { fields: {}, isLoading: false } ),
	useBoundFields: () => ( { boundFields: {}, setBoundFields: jest.fn() } ),
} ) );

// Loaded once so it shares the React copy used by the renderer.
require( '../../../assets/src/js/bindings/block-editor' );
const withCustomControls = addFilter.mock.calls[ 0 ][ 2 ];

const BlockEdit = () => <p>block content</p>;
const WrappedBlockEdit = withCustomControls( BlockEdit );

/**
 * Renders a block wrapped by the SCF bindings controls.
 *
 * @param {string} name       The block name.
 * @param {Object} attributes The block attributes.
 * @return {Object} The render result.
 */
function renderBlock( name = 'core/paragraph', attributes = {} ) {
	return render(
		<WrappedBlockEdit name={ name } attributes={ attributes } />
	);
}

/**
 * Stubs the data PHP localizes for the bindings script with acf_localize_data().
 *
 * @param {Object} data The scfBindings data.
 */
const setBindingsData = ( data ) => {
	window.acf.get.mockImplementation( ( key ) =>
		key === 'scfBindings' ? data : undefined
	);
};

const originalAcfGet = window.acf.get.getMockImplementation();

describe( 'Connect to a field panel (post editor)', () => {
	beforeEach( () => {
		usePostEditorFields.mockImplementation( () => ( {} ) );
	} );

	afterEach( () => {
		mockUpdateBlockBindings.mockClear();
		window.acf.get.mockImplementation( originalAcfGet );
	} );

	it( 'renders no panel when the post has no field groups', () => {
		setBindingsData( { hasFieldGroups: false } );

		renderBlock();

		expect( screen.getByText( 'block content' ) ).toBeInTheDocument();
		expect(
			screen.queryByText( 'Connect to a field' )
		).not.toBeInTheDocument();
	} );

	it( 'explains an empty list when the post has field groups but none are in REST', () => {
		setBindingsData( {
			hasFieldGroups: true,
			fieldGroupsUrl: 'http://example.com/wp-admin/edit.php',
		} );

		renderBlock();

		expect( screen.getByText( 'Connect to a field' ) ).toBeInTheDocument();
		expect(
			screen.getByText(
				'No fields on this post can be connected to this block.'
			)
		).toBeInTheDocument();
		expect(
			screen.getByText( /turn on Show in REST API/ )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				'This block supports these field types: text, textarea, date picker, number, range.'
			)
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Manage field groups' } )
		).toHaveAttribute( 'href', 'http://example.com/wp-admin/edit.php' );
	} );

	it( 'omits the field groups link for users who cannot manage SCF', () => {
		setBindingsData( {
			hasFieldGroups: true,
			fieldGroupsUrl: '',
		} );

		renderBlock();

		expect( screen.getByText( /turn on Show in REST API/ ) ).toBeVisible();
		expect( screen.queryByRole( 'link' ) ).not.toBeInTheDocument();
	} );

	it( 'shows the hint when fields exist but none fit the block', () => {
		setBindingsData( { hasFieldGroups: true } );
		usePostEditorFields.mockImplementation( () => ( {
			photo: { label: 'Photo', type: 'image', allow_in_bindings: true },
		} ) );

		renderBlock( 'core/paragraph' );

		expect(
			screen.getByText(
				'No fields on this post can be connected to this block.'
			)
		).toBeInTheDocument();
		expect( screen.queryByRole( 'combobox' ) ).not.toBeInTheDocument();
	} );

	it( 'hides fields with Allow Access to Value in Editor UI off', () => {
		setBindingsData( { hasFieldGroups: true } );
		usePostEditorFields.mockImplementation( () => ( {
			subtitle: {
				label: 'Subtitle',
				type: 'text',
				allow_in_bindings: true,
			},
			secret: {
				label: 'Secret',
				type: 'text',
				allow_in_bindings: false,
			},
		} ) );

		renderBlock( 'core/paragraph' );

		expect(
			screen.getByRole( 'option', { name: 'Subtitle' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'option', { name: 'Secret' } )
		).not.toBeInTheDocument();
		expect(
			screen.queryByText(
				'No fields on this post can be connected to this block.'
			)
		).not.toBeInTheDocument();
	} );

	it( 'shows the hint when every compatible field is not allowed in bindings', () => {
		setBindingsData( { hasFieldGroups: true } );
		usePostEditorFields.mockImplementation( () => ( {
			secret: {
				label: 'Secret',
				type: 'text',
				allow_in_bindings: false,
			},
		} ) );

		renderBlock( 'core/paragraph' );

		expect(
			screen.getByText(
				'No fields on this post can be connected to this block.'
			)
		).toBeInTheDocument();
	} );

	it( 'renders nothing for blocks without bindable attributes', () => {
		setBindingsData( { hasFieldGroups: true } );

		renderBlock( 'core/list' );

		expect(
			screen.queryByText( 'Connect to a field' )
		).not.toBeInTheDocument();
	} );

	it( 'leaves fields without a name out of the list', () => {
		setBindingsData( { hasFieldGroups: true } );
		usePostEditorFields.mockImplementation( () => ( {
			'': { label: '', type: 'text', allow_in_bindings: true },
			subtitle: {
				label: 'Subtitle',
				type: 'text',
				allow_in_bindings: true,
			},
		} ) );

		renderBlock( 'core/paragraph' );

		expect( screen.getAllByRole( 'option' ) ).toHaveLength( 1 );
		expect(
			screen.getByRole( 'option', { name: 'Subtitle' } )
		).toBeInTheDocument();
	} );

	it( 'does not send an empty binding for an attribute that is not bound', () => {
		setBindingsData( { hasFieldGroups: true } );
		usePostEditorFields.mockImplementation( () => ( {
			subtitle: {
				label: 'Subtitle',
				type: 'text',
				allow_in_bindings: true,
			},
		} ) );

		renderBlock( 'core/paragraph' );
		fireEvent.change( screen.getByRole( 'combobox' ), {
			target: { value: '' },
		} );

		expect( mockUpdateBlockBindings ).not.toHaveBeenCalled();
	} );

	it( 'removes a binding when the bound attribute is cleared', () => {
		setBindingsData( { hasFieldGroups: true } );
		usePostEditorFields.mockImplementation( () => ( {
			subtitle: {
				label: 'Subtitle',
				type: 'text',
				allow_in_bindings: true,
			},
		} ) );

		renderBlock( 'core/paragraph', {
			metadata: {
				bindings: {
					content: {
						source: 'acf/field',
						args: { key: 'subtitle' },
					},
				},
			},
		} );
		fireEvent.change( screen.getByRole( 'combobox' ), {
			target: { value: '' },
		} );

		expect( mockUpdateBlockBindings ).toHaveBeenCalledWith( {
			content: undefined,
		} );
	} );

	it( 'binds the selected field', () => {
		setBindingsData( { hasFieldGroups: true } );
		usePostEditorFields.mockImplementation( () => ( {
			subtitle: {
				label: 'Subtitle',
				type: 'text',
				allow_in_bindings: true,
			},
		} ) );

		renderBlock( 'core/paragraph' );
		fireEvent.change( screen.getByRole( 'combobox' ), {
			target: { value: 'subtitle' },
		} );

		expect( mockUpdateBlockBindings ).toHaveBeenCalledWith( {
			content: { source: 'acf/field', args: { key: 'subtitle' } },
		} );
	} );
} );
