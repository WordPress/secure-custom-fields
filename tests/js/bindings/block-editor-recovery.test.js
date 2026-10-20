/**
 * Inspector recovery from shared Site Editor schema failures.
 */
import { fireEvent, render, screen } from '@testing-library/react';
import { addFilter } from '@wordpress/hooks';
import {
	useSiteEditorContext,
	useSiteEditorFields,
} from '../../../assets/src/js/bindings/hooks';

jest.mock( '@wordpress/hooks', () => ( { addFilter: jest.fn() } ) );
jest.mock( '@wordpress/compose', () => ( {
	createHigherOrderComponent: ( callback ) => callback,
} ) );
jest.mock( '@wordpress/block-editor', () => ( {
	InspectorControls: ( { children } ) => <div>{ children }</div>,
	useBlockBindingsUtils: () => ( {
		updateBlockBindings: jest.fn(),
		removeAllBlockBindings: jest.fn(),
	} ),
} ) );
jest.mock( '@wordpress/components', () => ( {
	Notice: ( { children } ) => <div role="alert">{ children }</div>,
	Button: ( { children, onClick, disabled, isBusy } ) => (
		<button onClick={ onClick } disabled={ disabled } aria-busy={ isBusy }>
			{ children }
		</button>
	),
	ComboboxControl: ( { options } ) => (
		<ul>
			{ options.map( ( option ) => (
				<li key={ option.value }>{ option.label }</li>
			) ) }
		</ul>
	),
	__experimentalToolsPanel: ( { children } ) => <div>{ children }</div>,
	__experimentalToolsPanelItem: ( { children } ) => <div>{ children }</div>,
} ) );
jest.mock( '../../../assets/src/js/bindings/hooks', () => ( {
	useSiteEditorContext: jest.fn(),
	useSiteEditorFields: jest.fn(),
	usePostEditorFields: () => ( {
		field1: { label: 'Field 1', type: 'text' },
	} ),
	useBoundFields: () => ( { boundFields: {}, setBoundFields: jest.fn() } ),
} ) );

require( '../../../assets/src/js/bindings/block-editor' );
const withControls = addFilter.mock.calls[ 0 ][ 2 ];
const BlockEdit = () => <div>Block content</div>;
const EnhancedBlock = withControls( BlockEdit );
const renderBlock = () => (
	<EnhancedBlock name="core/paragraph" attributes={ {} } />
);

describe( 'Site Editor field recovery', () => {
	let state;

	beforeEach( () => {
		state = {
			fields: {},
			isLoading: false,
			error: new Error( 'Private REST error details' ),
			refresh: jest.fn(),
		};
		useSiteEditorFields.mockImplementation( () => state );
		useSiteEditorContext.mockReturnValue( {
			isSiteEditor: true,
			templatePostType: 'product',
		} );
	} );

	it( 'shows a generic error and retries only on user action', () => {
		const { rerender } = render( renderBlock() );
		expect( screen.getByRole( 'alert' ).textContent ).toContain(
			'Unable to load custom fields. Please try again.'
		);
		expect( screen.queryByText( 'Private REST error details' ) ).toBeNull();
		rerender( renderBlock() );
		expect( state.refresh ).not.toHaveBeenCalled();
		fireEvent.click( screen.getByRole( 'button', { name: 'Retry' } ) );
		expect( state.refresh ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'disables retry while loading and keeps cached field options', () => {
		state.isLoading = true;
		state.fields = { field1: { label: 'Field 1', type: 'text' } };
		render( renderBlock() );
		const retry = screen.getByRole( 'button', { name: 'Retry' } );
		expect( retry.disabled ).toBe( true );
		expect( retry.getAttribute( 'aria-busy' ) ).toBe( 'true' );
		fireEvent.click( retry );
		expect( state.refresh ).not.toHaveBeenCalled();
		expect( screen.getByText( 'Field 1' ) ).toBeTruthy();
	} );

	it( 'removes the notice and shows field options after successful recovery', () => {
		const { rerender } = render( renderBlock() );
		fireEvent.click( screen.getByRole( 'button', { name: 'Retry' } ) );
		state.error = null;
		state.fields = { field1: { label: 'Field 1', type: 'text' } };
		rerender( renderBlock() );
		expect( screen.queryByRole( 'alert' ) ).toBeNull();
		expect( screen.queryByRole( 'button', { name: 'Retry' } ) ).toBeNull();
		expect( screen.getByText( 'Field 1' ) ).toBeTruthy();
		expect( state.refresh ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'does not show the Site Editor error in the Post Editor', () => {
		useSiteEditorContext.mockReturnValue( {
			isSiteEditor: false,
			templatePostType: null,
		} );
		render( renderBlock() );
		expect( screen.queryByRole( 'alert' ) ).toBeNull();
		expect( screen.queryByRole( 'button', { name: 'Retry' } ) ).toBeNull();
		expect( screen.getByText( 'Field 1' ) ).toBeTruthy();
		expect( state.refresh ).not.toHaveBeenCalled();
	} );
} );
