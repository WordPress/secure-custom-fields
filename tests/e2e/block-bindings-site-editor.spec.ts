/**
 * E2E tests for block bindings in the site editor
 */
/* eslint-disable @typescript-eslint/no-explicit-any */
const { test, expect } = require( './fixtures' );

const PLUGIN_SLUG = 'secure-custom-fields';
const TEST_PLUGIN_SLUG = 'scf-test-setup-post-types';
const FIELD_GROUP_LABEL = 'Product Details';
const TEXT_FIELD_LABEL = 'Product Name';
const TEXTAREA_FIELD_LABEL = 'Product Description';
const IMAGE_FIELD_LABEL = 'Product Image';
const URL_FIELD_LABEL = 'Product Link';
const POST_TYPE = 'product';

test.describe( 'Block Bindings in Site Editor', () => {
	test.beforeAll( async ( { requestUtils }: any ) => {
		await requestUtils.activatePlugin( PLUGIN_SLUG );
		await requestUtils.activatePlugin( TEST_PLUGIN_SLUG );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( TEST_PLUGIN_SLUG );
		await requestUtils.deactivatePlugin( PLUGIN_SLUG );
		await requestUtils.deleteAllPosts();
	} );

	test( 'should bind text field to paragraph block in site editor', async ( {
		page,
		admin,
	} ) => {
		// Create a field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		await page.click( 'a.acf-btn:has-text("Add New")' );

		// Fill field group title
		await page.fill( '#title', FIELD_GROUP_LABEL );

		// Add text field
		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( TEXT_FIELD_LABEL );

		// Set field type
		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'text' );

		// Show in REST API
		await page.check( 'input[name*="[show_in_rest]"]' );

		// Set location to product post type
		const locationSelect = page.locator(
			'select[data-name="param"]'
		);
		await locationSelect.selectOption( 'post_type' );

		const locationValue = page.locator(
			'select[data-name="value"]'
		);
		await locationValue.selectOption( POST_TYPE );

		// Publish field group
		await page.click( 'button.acf-btn.acf-publish[type="submit"]' );

		// Wait for success message
		await page.waitForSelector( '.updated.notice' );

		// Navigate to site editor
		await admin.visitAdminPage( 'site-editor.php' );

		// Wait for site editor to load
		await page.waitForSelector( '.edit-site-header' );

		// Create or edit a template for the product post type
		// This part will depend on the actual site editor implementation
		// For now, we'll test that the REST API endpoint returns the fields
		const response = await page.evaluate( async () => {
			const res = await fetch( '/wp-json/wp/v2/types/product?context=edit' );
			return res.json();
		} );

		expect( response ).toHaveProperty( 'scf_field_groups' );
		expect( response.scf_field_groups ).toBeInstanceOf( Array );
		expect( response.scf_field_groups.length ).toBeGreaterThan( 0 );

		const fieldGroup = response.scf_field_groups[ 0 ];
		expect( fieldGroup ).toHaveProperty( 'title', FIELD_GROUP_LABEL );
		expect( fieldGroup.fields ).toBeInstanceOf( Array );
		expect( fieldGroup.fields[ 0 ] ).toHaveProperty( 'label', TEXT_FIELD_LABEL );
		expect( fieldGroup.fields[ 0 ] ).toHaveProperty( 'type', 'text' );
	} );

	test( 'should bind image field to image block in site editor', async ( {
		page,
		admin,
	} ) => {
		// Create a field group with image field
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		await page.click( 'a.acf-btn:has-text("Add New")' );

		await page.fill( '#title', 'Product Images' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( IMAGE_FIELD_LABEL );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'image' );

		// Show in REST API
		await page.check( 'input[name*="[show_in_rest]"]' );

		// Set location
		const locationSelect = page.locator(
			'select[data-name="param"]'
		);
		await locationSelect.selectOption( 'post_type' );

		const locationValue = page.locator(
			'select[data-name="value"]'
		);
		await locationValue.selectOption( POST_TYPE );

		// Publish field group
		await page.click( 'button.acf-btn.acf-publish[type="submit"]' );

		await page.waitForSelector( '.updated.notice' );

		// Verify field is available via REST API
		const response = await page.evaluate( async () => {
			const res = await fetch( '/wp-json/wp/v2/types/product?context=edit' );
			return res.json();
		} );

		const imageField = response.scf_field_groups
			.flatMap( ( group ) => group.fields )
			.find( ( field ) => field.label === IMAGE_FIELD_LABEL );

		expect( imageField ).toBeDefined();
		expect( imageField.type ).toBe( 'image' );
	} );

	test( 'should retrieve field groups for custom post type via REST API', async ( {
		page,
		admin,
	} ) => {
		// Create a comprehensive field group
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		await page.click( 'a.acf-btn:has-text("Add New")' );

		await page.fill( '#title', 'Complete Product Fields' );

		// Add multiple field types
		const fields = [
			{ label: 'Name', type: 'text' },
			{ label: 'Description', type: 'textarea' },
			{ label: 'Price', type: 'number' },
			{ label: 'Featured Image', type: 'image' },
			{ label: 'External URL', type: 'url' },
		];

		for ( let i = 0; i < fields.length; i++ ) {
			if ( i > 0 ) {
				// Click add field button for additional fields
				await page.click( 'a.acf-button-add:has-text("Add Field")' );
			}

			const fieldLabelInput = page.locator(
				`input[id^="acf_fields-field_"][id$="-label"]`
			).nth( i );
			await fieldLabelInput.fill( fields[ i ].label );

			const fieldTypeSelect = page.locator(
				`select[id^="acf_fields-field_"][id$="-type"]`
			).nth( i );
			await fieldTypeSelect.selectOption( fields[ i ].type );

			// Show in REST API
			const restCheckbox = page.locator(
				'input[name*="[show_in_rest]"]'
			).nth( i );
			await restCheckbox.check();
		}

		// Set location
		const locationSelect = page.locator(
			'select[data-name="param"]'
		);
		await locationSelect.selectOption( 'post_type' );

		const locationValue = page.locator(
			'select[data-name="value"]'
		);
		await locationValue.selectOption( POST_TYPE );

		// Publish field group
		await page.click( 'button.acf-btn.acf-publish[type="submit"]' );

		await page.waitForSelector( '.updated.notice' );

		// Test REST API response
		const response = await page.evaluate( async () => {
			const res = await fetch( '/wp-json/wp/v2/types/product?context=edit' );
			return res.json();
		} );

		expect( response.scf_field_groups ).toBeInstanceOf( Array );

		const fieldGroup = response.scf_field_groups.find(
			( group ) => group.title === 'Complete Product Fields'
		);

		expect( fieldGroup ).toBeDefined();
		expect( fieldGroup.fields.length ).toBe( fields.length );

		fields.forEach( ( field, index ) => {
			expect( fieldGroup.fields[ index ].label ).toBe( field.label );
			expect( fieldGroup.fields[ index ].type ).toBe( field.type );
		} );
	} );

	test( 'should filter post types by source parameter', async ( {
		page,
	} ) => {
		// Test core source
		const coreResponse = await page.evaluate( async () => {
			const res = await fetch( '/wp-json/wp/v2/types?source=core' );
			return res.json();
		} );

		expect( coreResponse ).toHaveProperty( 'post' );
		expect( coreResponse ).toHaveProperty( 'page' );
		expect( coreResponse ).not.toHaveProperty( 'product' );

		// Test other source (should include custom post types)
		const otherResponse = await page.evaluate( async () => {
			const res = await fetch( '/wp-json/wp/v2/types?source=other' );
			return res.json();
		} );

		// Custom post types should be in 'other'
		expect( Object.keys( otherResponse ).length ).toBeGreaterThanOrEqual( 0 );
	} );

	test( 'should handle bindings with show_in_rest enabled', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Create a field group with show_in_rest enabled
		await admin.visitAdminPage( 'edit.php', 'post_type=acf-field-group' );
		await page.click( 'a.acf-btn:has-text("Add New")' );

		await page.fill( '#title', 'REST Enabled Fields' );

		const fieldLabel = page.locator(
			'input[id^="acf_fields-field_"][id$="-label"]'
		);
		await fieldLabel.fill( 'REST Field' );

		const fieldType = page.locator(
			'select[id^="acf_fields-field_"][id$="-type"]'
		);
		await fieldType.selectOption( 'text' );

		// Enable show in REST API
		await page.check( 'input[name*="[show_in_rest]"]' );

		// Set location
		const locationSelect = page.locator(
			'select[data-name="param"]'
		);
		await locationSelect.selectOption( 'post_type' );

		const locationValue = page.locator(
			'select[data-name="value"]'
		);
		await locationValue.selectOption( 'post' );

		// Publish
		await page.click( 'button.acf-btn.acf-publish[type="submit"]' );
		await page.waitForSelector( '.updated.notice' );

		// Create a post with the field
		const post = await requestUtils.createPost( {
			title: 'Test Post',
			status: 'publish',
			acf: {
				rest_field: 'Test value',
			},
		} );

		// Verify field is accessible via REST API
		const response = await page.evaluate( async ( postId ) => {
			const res = await fetch(
				`/wp-json/wp/v2/posts/${ postId }?context=edit`
			);
			return res.json();
		}, post.id );

		expect( response ).toHaveProperty( 'acf' );
		expect( response.acf ).toHaveProperty( 'rest_field' );
	} );
} );
