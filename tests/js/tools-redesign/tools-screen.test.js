/**
 * Unit tests for the ToolsScreen component.
 * Verifies the card layout, item selection state, and the disabled
 * import button before a file is chosen.
 *
 * The @wordpress/components mock renders plain host elements so the
 * tests can assert on the prototype's markup structure.
 */

import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock( '@wordpress/components', () => {
	const mockReact = require( 'react' );
	const passthrough =
		( tag ) =>
		( { children, ...props } ) =>
			mockReact.createElement( tag, props, children );

	return {
		BaseControl: ( { children } ) => children,
		Button: ( { children, onClick, disabled, variant, ...rest } ) =>
			mockReact.createElement(
				'button',
				{ onClick, disabled, 'data-variant': variant, ...rest },
				children
			),
		Card: passthrough( 'div' ),
		CardBody: passthrough( 'div' ),
		CardHeader: passthrough( 'div' ),
		CheckboxControl: ( { label, checked, onChange } ) =>
			mockReact.createElement( 'input', {
				type: 'checkbox',
				checked,
				onChange: ( event ) => onChange( event.target.checked ),
				'aria-label': label,
			} ),
		Flex: ( { children, ...props } ) =>
			mockReact.createElement( 'div', props, children ),
		FlexItem: ( { children } ) => children,
		FormFileUpload: ( { accept, onChange } ) =>
			mockReact.createElement( 'input', {
				type: 'file',
				accept,
				onChange,
				'aria-label': 'Select JSON File',
			} ),
		TextareaControl: ( { value, readOnly, rows, ...rest } ) =>
			mockReact.createElement( 'textarea', {
				value,
				readOnly,
				rows,
				...rest,
			} ),
	};
} );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

import { ToolsScreen } from '../../../assets/src/js/tools-redesign/components/tools-screen';
import { ExportCard } from '../../../assets/src/js/tools-redesign/components/export-card';
import { PhpResult } from '../../../assets/src/js/tools-redesign/components/php-result';
import { ImportCard } from '../../../assets/src/js/tools-redesign/components/import-card';

const config = {
	nonces: { import: 'import-nonce', export: 'export-nonce' },
	items: {
		'acf-field-group': [ { key: 'group_1', title: 'Group One' } ],
		'acf-post-type': [ { key: 'post_type_1', title: 'Book' } ],
	},
	cptui: {},
};

describe( 'ToolsScreen', () => {
	test( 'renders import and export cards', () => {
		render( <ToolsScreen config={ config } /> );

		expect( screen.getByTestId( 'import-card' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'export-card' ) ).toBeInTheDocument();
	} );

	test( 'shows the PHP result screen when export code is provided', () => {
		render(
			<ToolsScreen
				config={ { ...config, phpExport: { code: '<?php // code' } } }
			/>
		);

		expect( screen.queryByTestId( 'import-card' ) ).not.toBeInTheDocument();
		expect( screen.getByTestId( 'php-export-textarea' ).value ).toContain(
			'code'
		);
	} );
} );

describe( 'ImportCard', () => {
	test( 'disables the import button until a file is chosen', () => {
		render( <ImportCard config={ config } /> );

		expect( screen.getByTestId( 'import-json-button' ) ).toBeDisabled();
	} );

	test( 'hides the CPTUI section when the plugin data is absent', () => {
		render( <ImportCard config={ config } /> );

		expect(
			screen.queryByTestId( 'import-cptui-button' )
		).not.toBeInTheDocument();
	} );

	test( 'shows CPTUI choices when provided', () => {
		render(
			<ImportCard
				config={ {
					...config,
					cptui: { choices: { post_types: 'Post Types' } },
				} }
			/>
		);

		expect(
			screen.getByRole( 'checkbox', { name: 'Post Types' } )
		).toBeInTheDocument();
	} );
} );

describe( 'ExportCard', () => {
	test( 'renders selection groups with item checkboxes', () => {
		render( <ExportCard config={ config } /> );

		expect(
			screen.getByRole( 'checkbox', { name: 'Group One' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'checkbox', { name: 'Book' } )
		).toBeInTheDocument();
	} );

	test( 'selecting an item checks it', () => {
		render( <ExportCard config={ config } /> );

		const checkbox = screen.getByRole( 'checkbox', {
			name: 'Group One',
		} );
		expect( checkbox ).not.toBeChecked();

		fireEvent.click( checkbox );
		expect( checkbox ).toBeChecked();
	} );
} );

describe( 'PhpResult', () => {
	test( 'renders the generated code read only', () => {
		render( <PhpResult code="<?php echo 'hi';" /> );

		const textarea = screen.getByTestId( 'php-export-textarea' );
		expect( textarea ).toHaveValue( "<?php echo 'hi';" );
		expect( textarea ).toHaveAttribute( 'readonly' );
	} );
} );
