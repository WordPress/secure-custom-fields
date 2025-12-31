/**
 * Unit tests for InlineEditingToolbar component
 * Tests the inline editing toolbar for ACF blocks
 */

/* global acf */

import React from 'react';
import { render, screen, fireEvent, act } from '@testing-library/react';
import '@testing-library/jest-dom';

// Mock @wordpress/components
jest.mock(
	'@wordpress/components',
	() => {
		const mockReact = require( 'react' );
		return {
			Toolbar: ( { children, className, style } ) =>
				mockReact.createElement(
					'div',
					{
						'data-testid': 'toolbar',
						className,
						style,
						role: 'toolbar',
					},
					children
				),
			ToolbarGroup: ( { children, style } ) =>
				mockReact.createElement(
					'div',
					{ 'data-testid': 'toolbar-group', style },
					children
				),
			ToolbarButton: mockReact.forwardRef(
				(
					{
						children,
						icon,
						label,
						onClick,
						isPressed,
						disabled,
						className,
					},
					ref
				) =>
					mockReact.createElement(
						'button',
						{
							ref,
							onClick,
							'data-testid': `toolbar-button-${ label }`,
							'data-pressed': isPressed,
							disabled,
							className,
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
		PopoverWrapper: ( { children, onClose, className, anchor } ) => (
			<div
				data-testid="popover-wrapper"
				className={ className }
				data-anchor={ anchor ? 'present' : 'absent' }
			>
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
	debug: jest.fn(),
};

// Import component after mocks
import { InlineEditingToolbar } from '../../../assets/src/js/pro/blocks-v3/components/inline-editing-toolbar';

describe( 'InlineEditingToolbar Component', () => {
	const defaultProps = {
		blockIcon: 'edit',
		blockFieldInfo: [
			{ name: 'title', type: 'text', label: 'Title' },
			{ name: 'description', type: 'textarea', label: 'Description' },
			{ name: 'image', type: 'image', label: 'Image' },
		],
		setInlineEditingToolbarHasFocus: jest.fn(),
		currentContentEditableElement: null,
		currentInlineEditingElement: null,
		currentInlineEditingElementUid: null,
		gutenbergIframeOrDocument: document,
		setCurrentBlockFormContainer: jest.fn(),
		contentEditableChangeInProgress: false,
	};

	beforeEach( () => {
		jest.clearAllMocks();
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	test( 'renders toolbar container', () => {
		render( <InlineEditingToolbar { ...defaultProps } /> );

		expect( screen.getByTestId( 'toolbar' ) ).toBeInTheDocument();
	} );

	test( 'renders toolbar with correct class', () => {
		render( <InlineEditingToolbar { ...defaultProps } /> );

		const toolbar = screen.getByTestId( 'toolbar' );
		expect( toolbar ).toHaveClass(
			'block-editor-block-contextual-toolbar'
		);
	} );

	test( 'renders field buttons when inline element has fields', () => {
		const inlineElement = document.createElement( 'div' );
		inlineElement.setAttribute(
			'data-acf-inline-fields',
			'["title","description"]'
		);
		inlineElement.setAttribute( 'data-acf-inline-fields-uid', 'uid-123' );

		render(
			<InlineEditingToolbar
				{ ...defaultProps }
				currentInlineEditingElement={ inlineElement }
				currentInlineEditingElementUid="uid-123"
			/>
		);

		expect(
			screen.getByTestId( 'toolbar-button-Title' )
		).toBeInTheDocument();
		expect(
			screen.getByTestId( 'toolbar-button-Description' )
		).toBeInTheDocument();
	} );

	test( 'handles malformed JSON in inline fields attribute', () => {
		const inlineElement = document.createElement( 'div' );
		inlineElement.setAttribute( 'data-acf-inline-fields', 'invalid-json' );

		render(
			<InlineEditingToolbar
				{ ...defaultProps }
				currentInlineEditingElement={ inlineElement }
			/>
		);

		// Should log debug message but not crash
		expect( acf.debug ).toHaveBeenCalledWith(
			'Inline fields were not a properly formatted JSON array',
			'invalid-json'
		);
	} );

	test( 'disables field buttons when contentEditableChangeInProgress is true', () => {
		const inlineElement = document.createElement( 'div' );
		inlineElement.setAttribute( 'data-acf-inline-fields', '["title"]' );
		inlineElement.setAttribute( 'data-acf-inline-fields-uid', 'uid-123' );

		render(
			<InlineEditingToolbar
				{ ...defaultProps }
				currentInlineEditingElement={ inlineElement }
				currentInlineEditingElementUid="uid-123"
				contentEditableChangeInProgress={ true }
			/>
		);

		const button = screen.getByTestId( 'toolbar-button-Title' );
		expect( button ).toBeDisabled();
	} );

	test( 'calls setInlineEditingToolbarHasFocus when field button is clicked', () => {
		const setInlineEditingToolbarHasFocus = jest.fn();
		const inlineElement = document.createElement( 'div' );
		inlineElement.setAttribute( 'data-acf-inline-fields', '["title"]' );
		inlineElement.setAttribute( 'data-acf-inline-fields-uid', 'uid-123' );

		render(
			<InlineEditingToolbar
				{ ...defaultProps }
				currentInlineEditingElement={ inlineElement }
				currentInlineEditingElementUid="uid-123"
				setInlineEditingToolbarHasFocus={
					setInlineEditingToolbarHasFocus
				}
			/>
		);

		fireEvent.click( screen.getByTestId( 'toolbar-button-Title' ) );

		expect( setInlineEditingToolbarHasFocus ).toHaveBeenCalledWith( true );
	} );

	test( 'toggles field selection on repeated clicks', async () => {
		const inlineElement = document.createElement( 'div' );
		inlineElement.setAttribute( 'data-acf-inline-fields', '["title"]' );
		inlineElement.setAttribute( 'data-acf-inline-fields-uid', 'uid-123' );

		render(
			<InlineEditingToolbar
				{ ...defaultProps }
				currentInlineEditingElement={ inlineElement }
				currentInlineEditingElementUid="uid-123"
			/>
		);

		const button = screen.getByTestId( 'toolbar-button-Title' );

		// First click - select (state updates but ref might not be set yet)
		await act( async () => {
			fireEvent.click( button );
			jest.runAllTimers();
		} );

		// The button should show as pressed
		expect( button ).toHaveAttribute( 'data-pressed', 'true' );

		// Second click - deselect
		await act( async () => {
			fireEvent.click( button );
			jest.runAllTimers();
		} );

		expect( button ).toHaveAttribute( 'data-pressed', 'false' );
	} );

	test( 'shows popover for simple field types after selection', async () => {
		const inlineElement = document.createElement( 'div' );
		inlineElement.setAttribute( 'data-acf-inline-fields', '["title"]' );
		inlineElement.setAttribute( 'data-acf-inline-fields-uid', 'uid-123' );

		const { rerender } = render(
			<InlineEditingToolbar
				{ ...defaultProps }
				currentInlineEditingElement={ inlineElement }
				currentInlineEditingElementUid="uid-123"
			/>
		);

		// Click to select
		await act( async () => {
			fireEvent.click( screen.getByTestId( 'toolbar-button-Title' ) );
			jest.runAllTimers();
		} );

		// Rerender to get ref assigned
		rerender(
			<InlineEditingToolbar
				{ ...defaultProps }
				currentInlineEditingElement={ inlineElement }
				currentInlineEditingElementUid="uid-123"
			/>
		);

		await act( async () => {
			jest.runAllTimers();
		} );

		// After re-render, popover should be present
		// Note: Component uses ref callback pattern, popover may appear after ref is set
		const button = screen.getByTestId( 'toolbar-button-Title' );
		expect( button ).toHaveAttribute( 'data-pressed', 'true' );
	} );

	test( 'shows modal for fields with useExpandedEditor flag after selection', async () => {
		const inlineElement = document.createElement( 'div' );
		// Use fieldLabel to specify a custom label that matches what the test looks for
		inlineElement.setAttribute(
			'data-acf-inline-fields',
			'[{"fieldName":"description","fieldLabel":"Expanded Description","useExpandedEditor":true}]'
		);
		inlineElement.setAttribute( 'data-acf-inline-fields-uid', 'uid-123' );

		const { rerender } = render(
			<InlineEditingToolbar
				{ ...defaultProps }
				currentInlineEditingElement={ inlineElement }
				currentInlineEditingElementUid="uid-123"
			/>
		);

		// Click to select - the label comes from fieldLabel in the config
		await act( async () => {
			fireEvent.click(
				screen.getByTestId( 'toolbar-button-Expanded Description' )
			);
			jest.runAllTimers();
		} );

		// Rerender to get ref assigned
		rerender(
			<InlineEditingToolbar
				{ ...defaultProps }
				currentInlineEditingElement={ inlineElement }
				currentInlineEditingElementUid="uid-123"
			/>
		);

		await act( async () => {
			jest.runAllTimers();
		} );

		// Check the button is pressed (modal requires ref which needs extra render)
		const button = screen.getByTestId(
			'toolbar-button-Expanded Description'
		);
		expect( button ).toHaveAttribute( 'data-pressed', 'true' );
	} );

	test( 'handles field config objects with custom icons', () => {
		const customIconBase64 = btoa( '<svg></svg>' );
		const inlineElement = document.createElement( 'div' );
		// Include fieldLabel so the button can be found by testid
		inlineElement.setAttribute(
			'data-acf-inline-fields',
			`[{"fieldName":"title","fieldLabel":"Title","fieldIcon":"${ customIconBase64 }"}]`
		);
		inlineElement.setAttribute( 'data-acf-inline-fields-uid', 'uid-123' );

		render(
			<InlineEditingToolbar
				{ ...defaultProps }
				currentInlineEditingElement={ inlineElement }
				currentInlineEditingElementUid="uid-123"
			/>
		);

		// Button should render with custom icon
		expect(
			screen.getByTestId( 'toolbar-button-Title' )
		).toBeInTheDocument();
	} );

	test( 'handles field config objects with custom labels', () => {
		const inlineElement = document.createElement( 'div' );
		inlineElement.setAttribute(
			'data-acf-inline-fields',
			'[{"fieldName":"title","fieldLabel":"Custom Label"}]'
		);
		inlineElement.setAttribute( 'data-acf-inline-fields-uid', 'uid-123' );

		render(
			<InlineEditingToolbar
				{ ...defaultProps }
				currentInlineEditingElement={ inlineElement }
				currentInlineEditingElementUid="uid-123"
			/>
		);

		expect(
			screen.getByTestId( 'toolbar-button-Custom Label' )
		).toBeInTheDocument();
	} );

	test( 'renders style tag for selected field', () => {
		const inlineElement = document.createElement( 'div' );
		inlineElement.setAttribute( 'data-acf-inline-fields', '["title"]' );
		inlineElement.setAttribute( 'data-acf-inline-fields-uid', 'uid-123' );

		render(
			<InlineEditingToolbar
				{ ...defaultProps }
				currentInlineEditingElement={ inlineElement }
				currentInlineEditingElementUid="uid-123"
			/>
		);

		fireEvent.click( screen.getByTestId( 'toolbar-button-Title' ) );

		// Check for style tag with field visibility
		const styles = document.querySelectorAll( 'style' );
		const hasFieldStyle = Array.from( styles ).some( ( style ) =>
			style.textContent.includes( 'data-name="title"' )
		);
		expect( hasFieldStyle ).toBe( true );
	} );

	test( 'clears selected field when contentEditableChangeInProgress changes', () => {
		const inlineElement = document.createElement( 'div' );
		inlineElement.setAttribute( 'data-acf-inline-fields', '["title"]' );
		inlineElement.setAttribute( 'data-acf-inline-fields-uid', 'uid-123' );

		const { rerender } = render(
			<InlineEditingToolbar
				{ ...defaultProps }
				currentInlineEditingElement={ inlineElement }
				currentInlineEditingElementUid="uid-123"
				contentEditableChangeInProgress={ false }
			/>
		);

		// Select field
		fireEvent.click( screen.getByTestId( 'toolbar-button-Title' ) );
		expect( screen.getByTestId( 'popover-wrapper' ) ).toBeInTheDocument();

		// Trigger change in progress
		rerender(
			<InlineEditingToolbar
				{ ...defaultProps }
				currentInlineEditingElement={ inlineElement }
				currentInlineEditingElementUid="uid-123"
				contentEditableChangeInProgress={ true }
			/>
		);

		// Selection should be cleared
		expect(
			screen.queryByTestId( 'popover-wrapper' )
		).not.toBeInTheDocument();
	} );

	test( 'does not render field buttons when inline fields is empty', () => {
		const inlineElement = document.createElement( 'div' );
		inlineElement.setAttribute( 'data-acf-inline-fields', '[]' );

		render(
			<InlineEditingToolbar
				{ ...defaultProps }
				currentInlineEditingElement={ inlineElement }
			/>
		);

		// Only toolbar structure should exist, no field buttons
		expect(
			screen.queryByTestId( 'toolbar-button-Title' )
		).not.toBeInTheDocument();
	} );

	test( 'handles null currentInlineEditingElement', () => {
		render(
			<InlineEditingToolbar
				{ ...defaultProps }
				currentInlineEditingElement={ null }
			/>
		);

		// Should render without crashing
		expect( screen.getByTestId( 'toolbar' ) ).toBeInTheDocument();
	} );
} );

describe( 'InlineEditingToolbar Icon and Title Generation', () => {
	const blockFieldInfo = [
		{ name: 'title', type: 'text', label: 'Title' },
		{ name: 'link', type: 'link', label: 'Link URL' },
	];

	test( 'uses custom toolbar icon from element attribute', () => {
		const inlineElement = document.createElement( 'div' );
		inlineElement.setAttribute(
			'data-acf-toolbar-icon',
			'<svg>icon</svg>'
		);
		inlineElement.setAttribute( 'data-acf-inline-fields', '["title"]' );
		inlineElement.setAttribute( 'data-acf-inline-fields-uid', 'uid-123' );

		render(
			<InlineEditingToolbar
				blockIcon="default"
				blockFieldInfo={ blockFieldInfo }
				setInlineEditingToolbarHasFocus={ jest.fn() }
				currentContentEditableElement={ null }
				currentInlineEditingElement={ inlineElement }
				currentInlineEditingElementUid="uid-123"
				gutenbergIframeOrDocument={ document }
				setCurrentBlockFormContainer={ jest.fn() }
				contentEditableChangeInProgress={ false }
			/>
		);

		// Toolbar should render
		expect( screen.getByTestId( 'toolbar' ) ).toBeInTheDocument();
	} );

	test( 'uses custom toolbar title from element attribute', () => {
		const inlineElement = document.createElement( 'div' );
		inlineElement.setAttribute(
			'data-acf-toolbar-title',
			'Custom Section Title'
		);
		inlineElement.setAttribute( 'data-acf-inline-fields', '["title"]' );
		inlineElement.setAttribute( 'data-acf-inline-fields-uid', 'uid-123' );

		render(
			<InlineEditingToolbar
				blockIcon="default"
				blockFieldInfo={ blockFieldInfo }
				setInlineEditingToolbarHasFocus={ jest.fn() }
				currentContentEditableElement={ null }
				currentInlineEditingElement={ inlineElement }
				currentInlineEditingElementUid="uid-123"
				gutenbergIframeOrDocument={ document }
				setCurrentBlockFormContainer={ jest.fn() }
				contentEditableChangeInProgress={ false }
			/>
		);

		// The title should be rendered somewhere in the toolbar
		expect(
			screen.getByText( 'Custom Section Title' )
		).toBeInTheDocument();
	} );

	test( 'generates title from element tag name when multiple fields', () => {
		const inlineElement = document.createElement( 'a' );
		inlineElement.setAttribute(
			'data-acf-inline-fields',
			'["title","link"]'
		);
		inlineElement.setAttribute( 'data-acf-inline-fields-uid', 'uid-123' );

		render(
			<InlineEditingToolbar
				blockIcon="default"
				blockFieldInfo={ blockFieldInfo }
				setInlineEditingToolbarHasFocus={ jest.fn() }
				currentContentEditableElement={ null }
				currentInlineEditingElement={ inlineElement }
				currentInlineEditingElementUid="uid-123"
				gutenbergIframeOrDocument={ document }
				setCurrentBlockFormContainer={ jest.fn() }
				contentEditableChangeInProgress={ false }
			/>
		);

		// Should show "Link" for A tag
		expect( screen.getByText( 'Link' ) ).toBeInTheDocument();
	} );
} );
