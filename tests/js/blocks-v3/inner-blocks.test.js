/* global acf */
/**
 * Unit tests for the InnerBlocks default_block / direct_insert helpers.
 */

import {
	applyInnerBlocksDefaults,
	getInnerBlocksSettings,
} from '../../../assets/src/js/pro/blocks-v3/utils/inner-blocks';
import { parseJSX } from '../../../assets/src/js/pro/blocks-v3/components/jsx-parser';

describe( 'InnerBlocks helpers', () => {
	const originalGet = acf.get.getMockImplementation();
	let wpVersion;

	beforeEach( () => {
		wpVersion = '6.8';
		acf.get.mockImplementation( ( key ) =>
			key === 'wp_version' ? wpVersion : originalGet( key )
		);
	} );

	afterEach( () => {
		acf.get.mockImplementation( originalGet );
	} );

	describe( 'applyInnerBlocksDefaults', () => {
		const blockType = {
			default_block: { name: 'core/heading', attributes: { level: 3 } },
			direct_insert: true,
		};

		test( 'applies the block type defaults', () => {
			expect( applyInnerBlocksDefaults( {}, blockType ) ).toEqual( {
				defaultBlock: blockType.default_block,
				directInsert: true,
			} );
		} );

		test( 'keeps values set in the template', () => {
			const props = {
				defaultBlock: { name: 'core/paragraph' },
				directInsert: false,
			};
			expect( applyInnerBlocksDefaults( props, blockType ) ).toEqual( {
				defaultBlock: { name: 'core/paragraph' },
				directInsert: false,
			} );
		} );

		test( 'ignores empty or unset block type values', () => {
			expect(
				applyInnerBlocksDefaults(
					{},
					{ default_block: [], direct_insert: false }
				)
			).toEqual( {} );
			expect( applyInnerBlocksDefaults( {}, false ) ).toEqual( {} );
		} );
	} );

	describe( 'getInnerBlocksSettings', () => {
		const props = {
			defaultBlock: { name: 'core/heading' },
			directInsert: true,
		};

		test( 'returns the props unchanged on WordPress 6.3+', () => {
			expect( getInnerBlocksSettings( props ) ).toBe( props );
		} );

		test( 'adds the experimental props on WordPress 6.2 and earlier', () => {
			wpVersion = '6.2.1';
			expect( getInnerBlocksSettings( props ) ).toEqual( {
				...props,
				__experimentalDefaultBlock: props.defaultBlock,
				__experimentalDirectInsert: true,
			} );
		} );

		test( 'returns the props unchanged when the version is unknown', () => {
			wpVersion = undefined;
			expect( getInnerBlocksSettings( props ) ).toBe( props );
		} );
	} );

	describe( 'v3 parseJSX', () => {
		test( 'applies the block type defaults to InnerBlocks', () => {
			const result = parseJSX(
				'<div><InnerBlocks /></div>',
				undefined,
				null,
				null,
				null,
				null,
				{
					name: 'acf/container',
					default_block: { name: 'core/heading' },
					direct_insert: true,
				}
			);

			const innerBlocks = result.props.children;
			expect( innerBlocks.props.defaultBlock ).toEqual( {
				name: 'core/heading',
			} );
			expect( innerBlocks.props.directInsert ).toBe( true );
		} );
	} );
} );
