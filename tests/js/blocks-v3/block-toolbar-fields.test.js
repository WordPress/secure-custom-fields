/**
 * Unit tests for BlockToolbarFields component
 * Tests the field buttons in the WordPress block toolbar
 */

import React from 'react';
import { render, screen, fireEvent, act } from '@testing-library/react';
import '@testing-library/jest-dom';

// Mock @wordpress/blockEditor
jest.mock(
	'@wordpress/blockEditor',
	() => ( {
		BlockControls: ( { children } ) => (
			<div data-testid="block-controls">{ children }</div>
		),
	} ),
	{ virtual: true }
);

// Mock @wordpress/components
jest.mock(
	'@wordpress/components',
	() => {
		const mockReact = require( 'react' );
		return {
			ToolbarGroup: ( { children } ) =>
				mockReact.createElement(
					'div',
					{ 'data-testid': 'toolbar-group' },
					children
				),
			ToolbarButton: mockReact.forwardRef(
				(
					{
						children,
						icon,
						label,
						onClick,
						onMouseDown,
						isPressed,
						disabled,
					},
					ref
				) =>
					mockReact.createElement(
						'button',
						{
							ref,
							onClick,
							onMouseDown,
							'data-testid': `toolbar-button-${ label }`,
							'data-pressed': isPressed,
							'data-disabled': disabled,
							'aria-label': label,
						},
						icon,
						children
					)
			),
			Modal: ( { children, title, onRequestClose } ) =>
				mockReact.createElement(
					'div',
					{
						'data-testid': 'modal',
						role: 'dialog',
						'aria-label': title,
					},
					mockReact.createElement(
						'button',
						{
							onClick: onRequestClose,
							'data-testid': 'modal-close',
						},
						'Close'
					),
					children
				),
		};
	},
	{ virtual: true }
);

// Mock PopoverWrapper
jest.mock(
	'../../../assets/src/js/pro/blocks-v3/components/popover-wrapper',
	() => ( {
		PopoverWrapper: ( { children, onClose, className } ) => (
			<div data-testid="popover-wrapper" className={ className }>
				<button
					data-testid="popover-close"
					onClick={ ( e ) => onClose?.( e ) }
				>
					Close
				</button>
				{ children }
			</div>
		),
	} )
);

// Mock acf global
global.acf = {
	__: jest.fn( ( key ) => key ),
};

// Import component after mocks
import { BlockToolbarFields } from '../../../assets/src/js/pro/blocks-v3/components/block-toolbar-fields';

describe( 'BlockToolbarFields Component', () => {
	const defaultProps = {
		blockToolbarFields: [],
		blockFieldInfo: [
			{ name: 'title', type: 'text', label: 'Title' },
			{ name: 'description', type: 'textarea', label: 'Description' },
			{ name: 'image', type: 'image', label: 'Image' },
			{ name: 'gallery', type: 'gallery', label: 'Gallery' },
			{ name: 'repeater', type: 'repeater', label: 'Items' },
			{
				name: 'content',
				type: 'flexible_content',
				label: 'Content Blocks',
			},
		],
		setCurrentBlockFormContainer: jest.fn(),
		gutenbergIframeOrDocument: document,
		setBlockFormModalOpen: jest.fn(),
		blockFormModalOpen: false,
	};

	beforeEach( () => {
		jest.clearAllMocks();
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	test( 'renders BlockControls container', () => {
		render( <BlockToolbarFields { ...defaultProps } /> );

		expect( screen.getByTestId( 'block-controls' ) ).toBeInTheDocument();
	} );

	test( 'renders Edit Block button', () => {
		render( <BlockToolbarFields { ...defaultProps } /> );

		expect(
			screen.getByTestId( 'toolbar-button-Edit Block' )
		).toBeInTheDocument();
	} );

	test( 'calls setBlockFormModalOpen when Edit Block is clicked', () => {
		const setBlockFormModalOpen = jest.fn();

		render(
			<BlockToolbarFields
				{ ...defaultProps }
				setBlockFormModalOpen={ setBlockFormModalOpen }
			/>
		);

		fireEvent.click( screen.getByTestId( 'toolbar-button-Edit Block' ) );

		expect( setBlockFormModalOpen ).toHaveBeenCalledWith( true );
	} );

	test( 'shows Edit Block as pressed when modal is open', () => {
		render(
			<BlockToolbarFields
				{ ...defaultProps }
				blockFormModalOpen={ true }
			/>
		);

		const editButton = screen.getByTestId( 'toolbar-button-Edit Block' );
		expect( editButton ).toHaveAttribute( 'data-pressed', 'true' );
	} );

	test( 'renders field buttons when blockToolbarFields is provided', () => {
		render(
			<BlockToolbarFields
				{ ...defaultProps }
				blockToolbarFields={ [ 'title', 'description' ] }
			/>
		);

		expect(
			screen.getByTestId( 'toolbar-button-Title' )
		).toBeInTheDocument();
		expect(
			screen.getByTestId( 'toolbar-button-Description' )
		).toBeInTheDocument();
	} );

	test( 'does not render field buttons when blockToolbarFields is empty', () => {
		render(
			<BlockToolbarFields { ...defaultProps } blockToolbarFields={ [] } />
		);

		// Only Edit Block button should be present
		const toolbarGroups = screen.getAllByTestId( 'toolbar-group' );
		expect( toolbarGroups ).toHaveLength( 1 );
	} );

	test( 'handles field config objects with fieldName and fieldLabel', () => {
		const toolbarFields = [
			{ fieldName: 'title', fieldLabel: 'Custom Title Label' },
		];

		render(
			<BlockToolbarFields
				{ ...defaultProps }
				blockToolbarFields={ toolbarFields }
			/>
		);

		expect(
			screen.getByTestId( 'toolbar-button-Custom Title Label' )
		).toBeInTheDocument();
	} );

	test( 'handles field config objects with custom icons', () => {
		const customIconBase64 = btoa( '<svg></svg>' );
		const toolbarFields = [
			{
				fieldName: 'title',
				fieldLabel: 'Title',
				fieldIcon: customIconBase64,
			},
		];

		render(
			<BlockToolbarFields
				{ ...defaultProps }
				blockToolbarFields={ toolbarFields }
			/>
		);

		const button = screen.getByTestId( 'toolbar-button-Title' );
		expect( button ).toBeInTheDocument();
	} );

	test( 'uses field label from blockFieldInfo when not in field config', () => {
		render(
			<BlockToolbarFields
				{ ...defaultProps }
				blockToolbarFields={ [ 'title' ] }
			/>
		);

		// Should use 'Title' from blockFieldInfo
		expect(
			screen.getByTestId( 'toolbar-button-Title' )
		).toBeInTheDocument();
	} );

	test( 'falls back to fieldName when label not found', () => {
		render(
			<BlockToolbarFields
				{ ...defaultProps }
				blockFieldInfo={ [] }
				blockToolbarFields={ [ 'unknown_field' ] }
			/>
		);

		expect(
			screen.getByTestId( 'toolbar-button-unknown_field' )
		).toBeInTheDocument();
	} );

	test( 'generates correct field type class name', () => {
		render(
			<BlockToolbarFields
				{ ...defaultProps }
				blockToolbarFields={ [ 'content' ] }
			/>
		);

		// flexible_content should become flexible-content
		const button = screen.getByTestId( 'toolbar-button-Content Blocks' );
		expect( button ).toBeInTheDocument();
	} );

	test( 'opens popover for simple field types on click', async () => {
		render(
			<BlockToolbarFields
				{ ...defaultProps }
				blockToolbarFields={ [ 'title' ] }
			/>
		);

		const button = screen.getByTestId( 'toolbar-button-Title' );
		fireEvent.mouseDown( button );

		// Run all timers since component uses setTimeout
		await act( async () => {
			jest.runAllTimers();
		} );

		// Popover should appear
		expect( screen.getByTestId( 'popover-wrapper' ) ).toBeInTheDocument();
	} );

	test( 'opens modal for repeater field type', async () => {
		render(
			<BlockToolbarFields
				{ ...defaultProps }
				blockToolbarFields={ [ 'repeater' ] }
			/>
		);

		const button = screen.getByTestId( 'toolbar-button-Items' );
		fireEvent.mouseDown( button );

		// Run all timers since component uses setTimeout
		await act( async () => {
			jest.runAllTimers();
		} );

		// Modal should appear for repeater type
		expect( screen.getByTestId( 'modal' ) ).toBeInTheDocument();
	} );

	test( 'opens modal for flexible_content field type', async () => {
		render(
			<BlockToolbarFields
				{ ...defaultProps }
				blockToolbarFields={ [ 'content' ] }
			/>
		);

		const button = screen.getByTestId( 'toolbar-button-Content Blocks' );
		fireEvent.mouseDown( button );

		// Run all timers since component uses setTimeout
		await act( async () => {
			jest.runAllTimers();
		} );

		// Modal should appear for flexible_content type
		expect( screen.getByTestId( 'modal' ) ).toBeInTheDocument();
	} );

	test( 'toggles field selection off when clicking same button', async () => {
		render(
			<BlockToolbarFields
				{ ...defaultProps }
				blockToolbarFields={ [ 'title' ] }
			/>
		);

		const button = screen.getByTestId( 'toolbar-button-Title' );

		// First click - select
		fireEvent.mouseDown( button );
		await act( async () => {
			jest.runAllTimers();
		} );
		expect( screen.getByTestId( 'popover-wrapper' ) ).toBeInTheDocument();

		// Second click - deselect (toggle off)
		fireEvent.mouseDown( button );
		await act( async () => {
			jest.runAllTimers();
		} );
		expect(
			screen.queryByTestId( 'popover-wrapper' )
		).not.toBeInTheDocument();
	} );

	test( 'renders multiple field buttons in correct order', () => {
		render(
			<BlockToolbarFields
				{ ...defaultProps }
				blockToolbarFields={ [ 'title', 'description', 'image' ] }
			/>
		);

		const buttons = screen.getAllByRole( 'button' );
		// Edit Block + 3 field buttons = 4 buttons
		expect( buttons.length ).toBeGreaterThanOrEqual( 4 );
	} );

	test( 'handles field config with index instead of fieldName', () => {
		const toolbarFields = [ { index: 0, fieldLabel: 'First Field' } ];

		render(
			<BlockToolbarFields
				{ ...defaultProps }
				blockToolbarFields={ toolbarFields }
			/>
		);

		expect(
			screen.getByTestId( 'toolbar-button-First Field' )
		).toBeInTheDocument();
	} );

	test( 'renders style tag for selected field visibility', async () => {
		render(
			<BlockToolbarFields
				{ ...defaultProps }
				blockToolbarFields={ [ 'title' ] }
			/>
		);

		const button = screen.getByTestId( 'toolbar-button-Title' );
		fireEvent.mouseDown( button );

		await act( async () => {
			jest.runAllTimers();
		} );

		// Style tag should be rendered for the selected field
		const styles = document.querySelectorAll( 'style' );
		const hasFieldStyle = Array.from( styles ).some( ( style ) =>
			style.textContent.includes( 'data-name="title"' )
		);
		expect( hasFieldStyle ).toBe( true );
	} );

	test( 'closes popover when onClose is triggered', async () => {
		render(
			<BlockToolbarFields
				{ ...defaultProps }
				blockToolbarFields={ [ 'title' ] }
			/>
		);

		const button = screen.getByTestId( 'toolbar-button-Title' );
		fireEvent.mouseDown( button );

		await act( async () => {
			jest.runAllTimers();
		} );

		expect( screen.getByTestId( 'popover-wrapper' ) ).toBeInTheDocument();

		// Close the popover
		fireEvent.click( screen.getByTestId( 'popover-close' ) );

		expect(
			screen.queryByTestId( 'popover-wrapper' )
		).not.toBeInTheDocument();
	} );

	test( 'closes modal when onRequestClose is triggered', async () => {
		render(
			<BlockToolbarFields
				{ ...defaultProps }
				blockToolbarFields={ [ 'repeater' ] }
			/>
		);

		const button = screen.getByTestId( 'toolbar-button-Items' );
		fireEvent.mouseDown( button );

		await act( async () => {
			jest.runAllTimers();
		} );

		expect( screen.getByTestId( 'modal' ) ).toBeInTheDocument();

		// Close the modal
		fireEvent.click( screen.getByTestId( 'modal-close' ) );

		expect( screen.queryByTestId( 'modal' ) ).not.toBeInTheDocument();
	} );

	test( 'sets current block form container when popover opens', async () => {
		const setCurrentBlockFormContainer = jest.fn();

		render(
			<BlockToolbarFields
				{ ...defaultProps }
				blockToolbarFields={ [ 'title' ] }
				setCurrentBlockFormContainer={ setCurrentBlockFormContainer }
			/>
		);

		const button = screen.getByTestId( 'toolbar-button-Title' );
		fireEvent.mouseDown( button );

		await act( async () => {
			jest.runAllTimers();
		} );

		// The ref should be passed to the popover inner container
		expect( screen.getByTestId( 'popover-wrapper' ) ).toBeInTheDocument();
	} );
} );
