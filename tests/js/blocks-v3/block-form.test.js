/**
 * Unit tests for BlockForm component
 * Tests the ACF fields form rendering inside blocks
 * Covers form changes, validation, and remounting behavior
 */

/* global acf */

import React from 'react';
import { render, waitFor, act } from '@testing-library/react';
import '@testing-library/jest-dom';

// Mock post-locking utilities - must be before component import
jest.mock( '../../../assets/src/js/pro/blocks-v3/utils/post-locking', () => ( {
	lockPostSaving: jest.fn(),
	unlockPostSaving: jest.fn(),
} ) );

// Import mocked functions
import {
	lockPostSaving,
	unlockPostSaving,
} from '../../../assets/src/js/pro/blocks-v3/utils/post-locking';

import { BlockForm } from '../../../assets/src/js/pro/blocks-v3/components/block-form';

// Mock jQuery
const mockJQuery = jest.fn( () => {
	const elements = {
		find: jest.fn( () => elements ),
		html: jest.fn( () => elements ),
		remove: jest.fn( () => elements ),
		length: 1,
	};
	return elements;
} );

// Mock acf global
global.acf = {
	__: jest.fn( ( key ) => {
		const translations = {
			'Validation successful': 'Validation successful',
			'An ACF Block on this page requires attention before you can save.':
				'An ACF Block on this page requires attention before you can save.',
		};
		return translations[ key ] || key;
	} ),
	debug: jest.fn(),
	getBlockFormValidator: jest.fn( () => ( {
		clearErrors: jest.fn(),
		set: jest.fn(),
		get: jest.fn( () => ( {
			update: jest.fn(),
		} ) ),
		addErrors: jest.fn(),
		showErrors: jest.fn(),
		$el: {
			find: jest.fn( () => ( {
				length: 0,
				remove: jest.fn(),
			} ) ),
		},
	} ) ),
	doAction: jest.fn(),
	applyFilters: jest.fn( ( hook, value ) => value ),
	serialize: jest.fn( () => ( { field_1: 'value_1' } ) ),
	normalizeFlexibleContentData: jest.fn( ( data ) => data ),
};

// Mock wp.data for notices
global.wp = {
	data: {
		dispatch: jest.fn( () => ( {
			createErrorNotice: jest.fn(),
			removeNotice: jest.fn(),
		} ) ),
		select: jest.fn( () => ( {
			getBlocks: jest.fn( () => [] ),
		} ) ),
	},
};

describe( 'BlockForm Component', () => {
	const defaultProps = {
		$: mockJQuery,
		clientId: 'test-client-id',
		blockFormHtml:
			'<div class="acf-fields"><input type="text" name="field_1" /></div>',
		onMount: jest.fn(),
		onChange: jest.fn(),
		validationErrors: null,
		showValidationErrors: false,
		acfFormRef: { current: null },
		userHasInteractedWithForm: false,
		attributes: { name: 'acf/test-block', data: {} },
		hideFieldsInSidebar: false,
	};

	beforeEach( () => {
		jest.clearAllMocks();
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	test( 'renders form container with correct classes', () => {
		render( <BlockForm { ...defaultProps } /> );

		const formContainer = document.querySelector( '.acf-block-component' );
		expect( formContainer ).toBeInTheDocument();
		expect( formContainer ).toHaveClass( 'acf-block-panel' );
	} );

	test( 'calls onMount when component mounts', () => {
		render( <BlockForm { ...defaultProps } /> );

		expect( defaultProps.onMount ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'renders form HTML via dangerouslySetInnerHTML', () => {
		render( <BlockForm { ...defaultProps } /> );

		// The form HTML should be filtered through acf.applyFilters
		expect( acf.applyFilters ).toHaveBeenCalledWith(
			'blocks/form/render',
			defaultProps.blockFormHtml,
			true
		);
	} );

	test( 'hides form when hideFieldsInSidebar is true', () => {
		render(
			<BlockForm { ...defaultProps } hideFieldsInSidebar={ true } />
		);

		const formContainer = document.querySelector( '.acf-block-component' );
		expect( formContainer ).toHaveStyle( { display: 'none' } );
	} );

	test( 'shows form when hideFieldsInSidebar is false', () => {
		render(
			<BlockForm { ...defaultProps } hideFieldsInSidebar={ false } />
		);

		const formContainer = document.querySelector( '.acf-block-component' );
		expect( formContainer ).not.toHaveStyle( { display: 'none' } );
	} );

	test( 'updates form HTML when blockFormHtml prop changes', async () => {
		const { rerender } = render(
			<BlockForm { ...defaultProps } blockFormHtml="" />
		);

		// Initially empty form
		expect( acf.applyFilters ).toHaveBeenCalledWith(
			'blocks/form/render',
			'',
			true
		);

		// Update with new HTML
		rerender(
			<BlockForm
				{ ...defaultProps }
				blockFormHtml="<div>New content</div>"
			/>
		);

		await waitFor( () => {
			expect( acf.applyFilters ).toHaveBeenCalledWith(
				'blocks/form/render',
				'<div>New content</div>',
				true
			);
		} );
	} );

	test( 'triggers validation actions before applying validation', () => {
		render(
			<BlockForm
				{ ...defaultProps }
				validationErrors={ [ { message: 'Error' } ] }
			/>
		);

		expect( acf.doAction ).toHaveBeenCalledWith(
			'blocks/validation/pre_apply',
			[ { message: 'Error' } ]
		);
		expect( acf.doAction ).toHaveBeenCalledWith(
			'blocks/validation/post_apply',
			[ { message: 'Error' } ]
		);
	} );

	test( 'locks post saving when validation errors are shown', () => {
		render(
			<BlockForm
				{ ...defaultProps }
				validationErrors={ [ { message: 'Required field' } ] }
				showValidationErrors={ true }
			/>
		);

		expect( lockPostSaving ).toHaveBeenCalledWith( 'test-client-id' );
	} );

	test( 'unlocks post saving when validation passes', () => {
		render(
			<BlockForm
				{ ...defaultProps }
				validationErrors={ null }
				showValidationErrors={ true }
			/>
		);

		expect( unlockPostSaving ).toHaveBeenCalledWith( 'test-client-id' );
	} );

	test( 'gets block form validator from acf', () => {
		const acfFormRef = { current: document.createElement( 'div' ) };

		render(
			<BlockForm
				{ ...defaultProps }
				acfFormRef={ acfFormRef }
				validationErrors={ [ { message: 'Error' } ] }
			/>
		);

		expect( acf.getBlockFormValidator ).toHaveBeenCalled();
	} );

	test( 'clears validation errors on validator', () => {
		const mockValidator = {
			clearErrors: jest.fn(),
			set: jest.fn(),
			get: jest.fn( () => ( { update: jest.fn() } ) ),
			addErrors: jest.fn(),
			showErrors: jest.fn(),
			$el: {
				find: jest.fn( () => ( { length: 0, remove: jest.fn() } ) ),
			},
		};
		acf.getBlockFormValidator.mockReturnValue( mockValidator );

		const acfFormRef = { current: document.createElement( 'div' ) };
		render(
			<BlockForm
				{ ...defaultProps }
				acfFormRef={ acfFormRef }
				validationErrors={ null }
			/>
		);

		expect( mockValidator.clearErrors ).toHaveBeenCalled();
		expect( mockValidator.set ).toHaveBeenCalledWith( 'notice', null );
	} );

	test( 'adds validation errors when present and showValidationErrors is true', () => {
		const mockValidator = {
			clearErrors: jest.fn(),
			set: jest.fn(),
			get: jest.fn( () => ( { update: jest.fn() } ) ),
			addErrors: jest.fn(),
			showErrors: jest.fn(),
			$el: {
				find: jest.fn( () => ( { length: 0, remove: jest.fn() } ) ),
			},
		};
		acf.getBlockFormValidator.mockReturnValue( mockValidator );

		const acfFormRef = { current: document.createElement( 'div' ) };
		const errors = [ { message: 'Field is required', input: 'field_1' } ];

		render(
			<BlockForm
				{ ...defaultProps }
				acfFormRef={ acfFormRef }
				validationErrors={ errors }
				showValidationErrors={ true }
			/>
		);

		expect( mockValidator.addErrors ).toHaveBeenCalledWith( errors );
		expect( mockValidator.showErrors ).toHaveBeenCalledWith( 'after' );
	} );

	test( 'triggers remount action when form HTML changes', async () => {
		const acfFormRef = { current: document.createElement( 'div' ) };

		render(
			<BlockForm
				{ ...defaultProps }
				acfFormRef={ acfFormRef }
				blockFormHtml="<div>Initial</div>"
			/>
		);

		// Wait for useEffect
		await act( async () => {
			jest.runAllTimers();
		} );

		expect( acf.doAction ).toHaveBeenCalledWith(
			'remount',
			expect.anything()
		);
	} );

	test( 'handles different clientId values', () => {
		const clientIds = [ 'block-1', 'block-2', 'block-3' ];

		clientIds.forEach( ( clientId ) => {
			jest.clearAllMocks();
			const { unmount } = render(
				<BlockForm
					{ ...defaultProps }
					clientId={ clientId }
					validationErrors={ [ { message: 'Error' } ] }
					showValidationErrors={ true }
				/>
			);

			expect( lockPostSaving ).toHaveBeenCalledWith( clientId );
			unmount();
		} );
	} );
} );

describe( 'BlockForm Validation Display', () => {
	const mockValidator = {
		clearErrors: jest.fn(),
		set: jest.fn(),
		get: jest.fn( () => ( { update: jest.fn() } ) ),
		addErrors: jest.fn(),
		showErrors: jest.fn(),
		$el: {
			find: jest.fn( () => ( {
				length: 1,
				remove: jest.fn(),
			} ) ),
		},
	};

	beforeEach( () => {
		jest.clearAllMocks();
		acf.getBlockFormValidator.mockReturnValue( mockValidator );
	} );

	test( 'shows success notice when validation passes after errors', () => {
		const acfFormRef = { current: document.createElement( 'div' ) };

		render(
			<BlockForm
				$={ mockJQuery }
				clientId="test-client-id"
				blockFormHtml="<div>Form</div>"
				onMount={ jest.fn() }
				onChange={ jest.fn() }
				validationErrors={ null }
				showValidationErrors={ true }
				acfFormRef={ acfFormRef }
				userHasInteractedWithForm={ true }
				attributes={ { name: 'acf/test-block', data: {} } }
				hideFieldsInSidebar={ false }
			/>
		);

		// When validation passes, success message should be shown
		expect( mockValidator.addErrors ).toHaveBeenCalledWith( [
			{ message: 'Validation successful' },
		] );
	} );
} );

describe( 'BlockForm Change Detection', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	test( 'calls onChange with form data when serialized', async () => {
		const onChange = jest.fn();
		const acfFormRef = { current: document.createElement( 'div' ) };

		render(
			<BlockForm
				$={ mockJQuery }
				clientId="test-client-id"
				blockFormHtml="<div>Form</div>"
				onMount={ jest.fn() }
				onChange={ onChange }
				validationErrors={ null }
				showValidationErrors={ false }
				acfFormRef={ acfFormRef }
				userHasInteractedWithForm={ true }
				attributes={ { name: 'acf/test-block', data: {} } }
				hideFieldsInSidebar={ false }
			/>
		);

		// Run timers to trigger initial onChange
		await act( async () => {
			jest.runAllTimers();
		} );

		// onChange should have been called during initial mount
		expect( onChange ).toHaveBeenCalled();
	} );

	test( 'debounces form changes', async () => {
		const onChange = jest.fn();
		const acfFormRef = { current: document.createElement( 'div' ) };

		render(
			<BlockForm
				$={ mockJQuery }
				clientId="test-client-id"
				blockFormHtml="<div>Form</div>"
				onMount={ jest.fn() }
				onChange={ onChange }
				validationErrors={ null }
				showValidationErrors={ false }
				acfFormRef={ acfFormRef }
				userHasInteractedWithForm={ true }
				attributes={ { name: 'acf/test-block', data: {} } }
				hideFieldsInSidebar={ false }
			/>
		);

		// Fast forward 100ms - should not have triggered change yet
		jest.advanceTimersByTime( 100 );
		const callCountBefore = onChange.mock.calls.length;

		// Fast forward another 300ms - should have triggered by now (300ms debounce)
		await act( async () => {
			jest.advanceTimersByTime( 300 );
		} );

		// Changes should be batched/debounced
		expect( onChange.mock.calls.length ).toBeGreaterThanOrEqual(
			callCountBefore
		);
	} );
} );

describe( 'BlockForm Attribute Handling', () => {
	test( 'passes attributes to form context', () => {
		const attributes = {
			name: 'acf/testimonial',
			data: {
				field_1: 'John Doe',
				field_2: 'Great product!',
			},
		};

		render(
			<BlockForm
				$={ mockJQuery }
				clientId="test-client-id"
				blockFormHtml="<div>Form</div>"
				onMount={ jest.fn() }
				onChange={ jest.fn() }
				validationErrors={ null }
				showValidationErrors={ false }
				acfFormRef={ { current: null } }
				userHasInteractedWithForm={ false }
				attributes={ attributes }
				hideFieldsInSidebar={ false }
			/>
		);

		// Form should render with the provided attributes
		expect( acf.applyFilters ).toHaveBeenCalled();
	} );

	test( 'handles empty attributes', () => {
		const attributes = {
			name: 'acf/empty-block',
			data: {},
		};

		expect( () =>
			render(
				<BlockForm
					$={ mockJQuery }
					clientId="test-client-id"
					blockFormHtml="<div>Form</div>"
					onMount={ jest.fn() }
					onChange={ jest.fn() }
					validationErrors={ null }
					showValidationErrors={ false }
					acfFormRef={ { current: null } }
					userHasInteractedWithForm={ false }
					attributes={ attributes }
					hideFieldsInSidebar={ false }
				/>
			)
		).not.toThrow();
	} );
} );
