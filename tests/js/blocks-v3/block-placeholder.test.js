/**
 * Unit tests for BlockPlaceholder component
 * Tests the placeholder UI shown when a block has no preview HTML
 */

import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';

import { BlockPlaceholder } from '../../../assets/src/js/pro/blocks-v3/components/block-placeholder';

// Mock the acf global object
global.acf = {
	__: jest.fn( ( key ) => {
		const translations = {
			'Edit Block': 'Edit Block',
		};
		return translations[ key ] || key;
	} ),
	// Decodes HTML entities like acf.decode() in _acf.js.
	decode: jest.fn( ( string ) => {
		const textarea = document.createElement( 'textarea' );
		textarea.innerHTML = string;
		return textarea.value;
	} ),
};

describe( 'BlockPlaceholder Component', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders with block label', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<BlockPlaceholder
				blockLabel="Test Block"
				setBlockFormModalOpen={ mockSetModalOpen }
			/>
		);

		expect( screen.getByTestId( 'placeholder' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'placeholder-label' ) ).toHaveTextContent(
			'Test Block'
		);
	} );

	test( 'renders with instructions when provided', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<BlockPlaceholder
				blockLabel="Test Block"
				setBlockFormModalOpen={ mockSetModalOpen }
				instructions="Please fill in the block fields"
			/>
		);

		expect(
			screen.getByTestId( 'placeholder-instructions' )
		).toHaveTextContent( 'Please fill in the block fields' );
	} );

	test( 'does not render instructions when not provided', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<BlockPlaceholder
				blockLabel="Test Block"
				setBlockFormModalOpen={ mockSetModalOpen }
			/>
		);

		expect(
			screen.queryByTestId( 'placeholder-instructions' )
		).not.toBeInTheDocument();
	} );

	test( 'renders Edit Block button', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<BlockPlaceholder
				blockLabel="Test Block"
				setBlockFormModalOpen={ mockSetModalOpen }
			/>
		);

		const button = screen.getByRole( 'button' );
		expect( button ).toHaveTextContent( 'Edit Block' );
	} );

	test( 'calls setBlockFormModalOpen with true when Edit Block button is clicked', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<BlockPlaceholder
				blockLabel="Test Block"
				setBlockFormModalOpen={ mockSetModalOpen }
			/>
		);

		const button = screen.getByRole( 'button' );
		fireEvent.click( button );

		expect( mockSetModalOpen ).toHaveBeenCalledTimes( 1 );
		expect( mockSetModalOpen ).toHaveBeenCalledWith( true );
	} );

	test( 'renders icon in placeholder', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<BlockPlaceholder
				blockLabel="Test Block"
				setBlockFormModalOpen={ mockSetModalOpen }
			/>
		);

		expect( screen.getByTestId( 'icon' ) ).toBeInTheDocument();
	} );

	test( 'renders button with primary variant', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<BlockPlaceholder
				blockLabel="Test Block"
				setBlockFormModalOpen={ mockSetModalOpen }
			/>
		);

		const button = screen.getByRole( 'button' );
		expect( button ).toHaveAttribute( 'data-variant', 'primary' );
	} );

	test( 'uses acf.__ for translations', () => {
		const mockSetModalOpen = jest.fn();

		render(
			<BlockPlaceholder
				blockLabel="Test Block"
				setBlockFormModalOpen={ mockSetModalOpen }
			/>
		);

		expect( global.acf.__ ).toHaveBeenCalledWith( 'Edit Block' );
	} );

	test( 'renders with different block labels', () => {
		const mockSetModalOpen = jest.fn();
		const testCases = [
			'Testimonial Block',
			'Image Gallery',
			'Contact Form',
			'Hero Section',
		];

		testCases.forEach( ( label ) => {
			const { unmount } = render(
				<BlockPlaceholder
					blockLabel={ label }
					setBlockFormModalOpen={ mockSetModalOpen }
				/>
			);

			expect(
				screen.getByTestId( 'placeholder-label' )
			).toHaveTextContent( label );
			unmount();
		} );
	} );
	describe( 'placeholder button text', () => {
		test( 'renders custom button text decoded as plain text', () => {
			render(
				<BlockPlaceholder
					blockLabel="Test Block"
					setBlockFormModalOpen={ jest.fn() }
					buttonText={ 'Edit &amp; Configure &lt;b&gt;' }
				/>
			);

			const button = screen.getByRole( 'button' );
			expect( button ).toHaveTextContent( 'Edit & Configure <b>' );
			expect( button.querySelector( 'b' ) ).toBeNull();
			expect( global.acf.decode ).toHaveBeenCalledWith(
				'Edit &amp; Configure &lt;b&gt;'
			);
			expect( global.acf.__ ).not.toHaveBeenCalledWith( 'Edit Block' );
		} );

		test( 'falls back to Edit Block for empty button text', () => {
			render(
				<BlockPlaceholder
					blockLabel="Test Block"
					setBlockFormModalOpen={ jest.fn() }
					buttonText=""
				/>
			);

			expect( screen.getByRole( 'button' ) ).toHaveTextContent(
				'Edit Block'
			);
		} );
	} );

	describe( 'placeholder block icon', () => {
		const customIcon = <svg data-testid="custom-icon" />;

		test( 'renders an element icon', () => {
			render(
				<BlockPlaceholder
					blockLabel="Test Block"
					setBlockFormModalOpen={ jest.fn() }
					blockIcon={ customIcon }
				/>
			);

			expect( screen.getByTestId( 'custom-icon' ) ).toBeInTheDocument();
		} );

		test( 'renders an icon wrapped in an object with a src key', () => {
			render(
				<BlockPlaceholder
					blockLabel="Test Block"
					setBlockFormModalOpen={ jest.fn() }
					blockIcon={ { src: customIcon } }
				/>
			);

			expect( screen.getByTestId( 'custom-icon' ) ).toBeInTheDocument();
		} );

		test( 'falls back to the default icon for string icons', () => {
			render(
				<BlockPlaceholder
					blockLabel="Test Block"
					setBlockFormModalOpen={ jest.fn() }
					blockIcon="admin-comments"
				/>
			);

			const icon = screen.getByTestId( 'icon' );
			expect( icon ).not.toHaveTextContent( 'admin-comments' );
			expect( icon.querySelector( 'svg' ) ).not.toBeNull();
		} );
	} );
} );
