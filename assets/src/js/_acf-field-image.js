( function ( $ ) {
	const Field = acf.Field.extend( {
		type: 'image',

		$control: function () {
			return this.$( '.acf-image-uploader' );
		},

		$input: function () {
			return this.$( 'input[type="hidden"]:first' );
		},

		events: {
			'click a[data-name="add"]': 'onClickAdd',
			'click a[data-name="edit"]': 'onClickEdit',
			'click a[data-name="remove"]': 'onClickRemove',
			'change input[type="file"]': 'onChange',
			'keydown .image-wrap': 'onImageWrapKeydown',
		},

		initialize: function () {
			// add attribute to form
			if ( this.get( 'uploader' ) === 'basic' ) {
				this.$el
					.closest( 'form' )
					.attr( 'enctype', 'multipart/form-data' );
			}
		},

		validateAttachment: function ( attachment ) {
			// Use WP attachment attributes when available.
			if ( attachment && attachment.attributes ) {
				attachment = attachment.attributes;
			}

			// Apply defaults.
			attachment = acf.parseArgs( attachment, {
				id: 0,
				url: '',
				alt: '',
				title: '',
				caption: '',
				description: '',
				width: 0,
				height: 0,
			} );

			// Override with "preview size".
			const size = acf.isget(
				attachment,
				'sizes',
				this.get( 'preview_size' )
			);
			if ( size ) {
				attachment.url = size.url;
				attachment.width = size.width;
				attachment.height = size.height;
			}

			// Return.
			return attachment;
		},

		render: function ( attachment ) {
			attachment = this.validateAttachment( attachment );

			// Update DOM.
			this.$( 'img' ).attr( {
				src: attachment.url,
				alt: attachment.alt,
			} );
			if ( attachment.id ) {
				this.val( attachment.id );
				this.$control().addClass( 'has-value' );
				const imageWrap = this.$( '.image-wrap' );
				if ( imageWrap.length ) {
					imageWrap.trigger( 'focus' );
				}
			} else {
				this.val( '' );
				this.$control().removeClass( 'has-value' );
			}
		},

		// create a new repeater row and render value
		append: function ( attachment, parent ) {
			// create function to find next available field within parent
			const getNext = function ( field, parent ) {
				// find existing file fields within parent
				const fields = acf.getFields( {
					key: field.get( 'key' ),
					parent: parent.$el,
				} );

				// find the first field with no value
				for ( let i = 0; i < fields.length; i++ ) {
					if ( ! fields[ i ].val() ) {
						return fields[ i ];
					}
				}

				// return
				return false;
			};

			// find existing file fields within parent
			let field = getNext( this, parent );

			// add new row if no available field
			if ( ! field ) {
				parent.$( '.acf-button:last' ).trigger( 'click' );
				field = getNext( this, parent );
			}

			// render
			if ( field ) {
				field.render( attachment );
			}
		},

		selectAttachment: function () {
			// vars
			const parent = this.parent();
			const multiple = parent && parent.get( 'type' ) === 'repeater';

			// new frame
			const frame = acf.newMediaPopup( {
				mode: 'select',
				type: 'image',
				title: acf.__( 'Select Image' ),
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

		editAttachment: function ( attachment ) {
			// vars
			const val = this.val();

			if ( val ) {
				// popup
				var frame = acf.newMediaPopup( {
					mode: 'edit',
					title: acf.__( 'Edit Image' ),
					button: acf.__( 'Update Image' ),
					attachment: val,
					field: this.get( 'key' ),
					select: $.proxy( function ( attachment ) {
						this.render( attachment );
					}, this ),
					close: $.proxy( function () {
						if ( 'edit-button' === attachment ) {
							const edit = this.$el.find( 'a[data-name="edit"]' );
							if ( edit.length ) {
								edit.trigger( 'focus' );
							}
						} else {
							const imageWrap = this.$( '.image-wrap' );
							if ( imageWrap.length ) {
								imageWrap.trigger( 'focus' );
							}
						}
					}, this ),
				} );
			}
		},

		removeAttachment: function () {
			this.render( false );
		},

		onClickAdd: function ( e, $el ) {
			this.selectAttachment();
		},

		onClickEdit: function ( e, $el ) {
			this.editAttachment( 'edit-button' );
		},

		onClickRemove: function ( e, $el ) {
			this.removeAttachment();
		},

		onChange: function ( e, $el ) {
			const $hiddenInput = this.$input();

			if ( ! $el.val() ) {
				$hiddenInput.val( '' );
			}

			acf.getFileInputData( $el, function ( data ) {
				$hiddenInput.val( $.param( data ) );
			} );
		},
		onImageWrapKeydown: function ( event, imageWrapElement ) {
			// Check if Enter key was pressed (keycode 13)
			if ( event.which === 13 ) {
				// Check if the event target is the imageWrapElement itself
				if ( event.target === imageWrapElement[ 0 ] ) {
					// Prevent the default Enter key behavior
					event.preventDefault();

					// Check if the field has a value
					if ( this.val() ) {
						// Check if the uploader is NOT the basic uploader
						if ( this.get( 'uploader' ) !== 'basic' ) {
							// Open the edit attachment dialog
							this.editAttachment();
						}
					}
				}
			}
		},
	} );

	acf.registerFieldType( Field );
} )( jQuery );
