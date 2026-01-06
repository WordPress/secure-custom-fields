( function ( $, undefined ) {
	const Field = acf.models.ImageField.extend( {
		type: 'file',

		$control: function () {
			return this.$( '.acf-file-uploader' );
		},

		$input: function () {
			return this.$( 'input[type="hidden"]:first' );
		},
		events: {
			'click a[data-name="add"]': 'onClickAdd',
			'click a[data-name="edit"]': 'onClickEdit',
			'click a[data-name="remove"]': 'onClickRemove',
			'change input[type="file"]': 'onChange',
			'keydown .file-wrap': 'onImageWrapKeydown',
		},
		validateAttachment: function ( attachment ) {
			// defaults
			attachment = attachment || {};

			// WP attachment
			if ( attachment.id !== undefined ) {
				attachment = attachment.attributes;
			}

			// args
			attachment = acf.parseArgs( attachment, {
				url: '',
				alt: '',
				title: '',
				filename: '',
				filesizeHumanReadable: '',
				icon: '/wp-includes/images/media/default.png',
			} );

			// return
			return attachment;
		},

		render: function ( attachment ) {
			// vars
			attachment = this.validateAttachment( attachment );

			// update image
			this.$( 'img' ).attr( {
				src: attachment.icon,
				alt: attachment.alt,
				title: attachment.title,
			} );

			// update elements
			this.$( '[data-name="title"]' ).text( attachment.title );
			this.$( '[data-name="filename"]' )
				.text( attachment.filename )
				.attr( 'href', attachment.url );
			this.$( '[data-name="filesize"]' ).text(
				attachment.filesizeHumanReadable
			);

			// vars
			const val = attachment.id || '';

			// update val
			acf.val( this.$input(), val );
			if ( val ) {
				// update class
				this.$control().addClass( 'has-value' );
				const fileWrap = this.$( '.file-wrap' );
				if ( fileWrap.length ) {
					fileWrap.trigger( 'focus' );
				}
			} else {
				this.$control().removeClass( 'has-value' );
			}
		},

		selectAttachment: function () {
			// vars
			const parent = this.parent();
			const multiple = parent && parent.get( 'type' ) === 'repeater';

			// new frame
			const frame = acf.newMediaPopup( {
				mode: 'select',
				title: acf.__( 'Select File' ),
				field: this.get( 'key' ),
				multiple: multiple,
				library: this.get( 'library' ),
				allowedTypes: this.get( 'mime_types' ),
				select: $.proxy( function ( attachment, i ) {
					if ( i > 0 ) {
						this.append( attachment, parent );
					} else {
						this.render( attachment );
					}
				}, this ),
			} );
		},

		editAttachment: function ( button ) {
			// vars
			const val = this.val();

			// bail early if no val
			if ( ! val ) {
				return false;
			}

			// popup
			var frame = acf.newMediaPopup( {
				mode: 'edit',
				title: acf.__( 'Edit File' ),
				button: acf.__( 'Update File' ),
				attachment: val,
				field: this.get( 'key' ),
				select: $.proxy( function ( attachment ) {
					this.render( attachment );
				}, this ),
				close: $.proxy( function () {
					if ( 'edit-button' === button ) {
						const edit = this.$el.find( 'a[data-name="edit"]' );
						if ( edit.length ) {
							edit.trigger( 'focus' );
						}
					} else {
						const imageWrap = this.$el.find( '.image-wrap' );
						if ( imageWrap.length ) {
							imageWrap.trigger( 'focus' );
						}
					}
				}, this ),
			} );
		},
	} );

	acf.registerFieldType( Field );
} )( jQuery );
