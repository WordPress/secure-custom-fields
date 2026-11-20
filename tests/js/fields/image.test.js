/**
 * Unit tests for the image field type's attachment validation.
 */

describe( 'Image Field', () => {
	let fieldDefinition;
	let mockField;

	beforeEach( () => {
		fieldDefinition = null;

		global.acf = {
			Field: {
				extend: jest.fn( ( definition ) => {
					fieldDefinition = definition;
					return definition;
				} ),
			},
			registerFieldType: jest.fn(),
			parseArgs: ( args, defaults ) => ( { ...defaults, ...args } ),
			isget: ( obj, ...keys ) => {
				for ( const key of keys ) {
					if ( ! obj || ! obj.hasOwnProperty( key ) ) {
						return null;
					}
					obj = obj[ key ];
				}
				return obj;
			},
		};

		jest.isolateModules( () => {
			require( '../../../assets/src/js/_acf-field-image.js' );
		} );

		mockField = {
			...fieldDefinition,
			get: jest.fn( ( name ) =>
				name === 'preview_size' ? 'medium' : undefined
			),
		};
	} );

	afterEach( () => {
		delete global.acf;
		jest.resetModules();
	} );

	describe( 'validateAttachment()', () => {
		it( 'should use the preview size for images', () => {
			const attachment = mockField.validateAttachment( {
				id: 1,
				type: 'image',
				url: 'full.jpg',
				icon: 'icon.png',
				sizes: {
					medium: { url: 'medium.jpg', width: 300, height: 200 },
				},
			} );

			expect( attachment.url ).toBe( 'medium.jpg' );
			expect( attachment.width ).toBe( 300 );
			expect( attachment.height ).toBe( 200 );
		} );

		it( 'should keep the full URL for images without the preview size', () => {
			const attachment = mockField.validateAttachment( {
				id: 1,
				type: 'image',
				url: 'full.jpg',
				icon: 'icon.png',
			} );

			expect( attachment.url ).toBe( 'full.jpg' );
		} );

		it( 'should use the icon for attachments that are not images', () => {
			const attachment = mockField.validateAttachment( {
				id: 2,
				type: 'application',
				url: 'file.pdf',
				title: 'My file',
				icon: 'document.png',
			} );

			expect( attachment.url ).toBe( 'document.png' );
			expect( attachment.alt ).toBe( 'My file' );
		} );

		it( 'should keep the alt text of attachments that are not images', () => {
			const attachment = mockField.validateAttachment( {
				id: 2,
				type: 'application',
				url: 'file.pdf',
				title: 'My file',
				alt: 'Existing alt',
				icon: 'document.png',
			} );

			expect( attachment.alt ).toBe( 'Existing alt' );
		} );

		it( 'should keep the URL when there is no icon', () => {
			const attachment = mockField.validateAttachment( {
				id: 2,
				type: 'application',
				url: 'file.pdf',
			} );

			expect( attachment.url ).toBe( 'file.pdf' );
		} );

		it( 'should read WP attachment model attributes', () => {
			const attachment = mockField.validateAttachment( {
				attributes: {
					id: 3,
					type: 'video',
					url: 'movie.mp4',
					title: 'Movie',
					icon: 'video.png',
				},
			} );

			expect( attachment.id ).toBe( 3 );
			expect( attachment.url ).toBe( 'video.png' );
		} );
	} );
} );
