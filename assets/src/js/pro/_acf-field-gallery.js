( function ( $ ) {
	var Field = acf.Field.extend( {
		type: 'gallery',

		events: {
			'click .acf-gallery-add': 'onClickAdd',
			'click .acf-gallery-edit': 'onClickEdit',
			'click .acf-gallery-remove': 'onClickRemove',
			'click .acf-gallery-attachment': 'onClickSelect',
			'click .acf-gallery-close': 'onClickClose',
			'change .acf-gallery-sort': 'onChangeSort',
			'click .acf-gallery-update': 'onUpdate',
			mouseover: 'onHover',
			showField: 'render',
		},

		actions: {
			validation_begin: 'onValidationBegin',
			validation_failure: 'onValidationFailure',
			resize: 'onResize',
		},

		onValidationBegin: function () {
			acf.disable( this.$sideData(), this.cid );
		},

		onValidationFailure: function () {
			acf.enable( this.$sideData(), this.cid );
		},

		$control: function () {
			return this.$( '.acf-gallery' );
		},

		$collection: function () {
			return this.$( '.acf-gallery-attachments' );
		},

		$attachments: function () {
			return this.$( '.acf-gallery-attachment' );
		},

		$attachment: function ( id ) {
			return this.$( '.acf-gallery-attachment[data-id="' + id + '"]' );
		},

		$active: function () {
			return this.$( '.acf-gallery-attachment.active' );
		},

		$main: function () {
			return this.$( '.acf-gallery-main' );
		},

		$side: function () {
			return this.$( '.acf-gallery-side' );
		},

		$sideData: function () {
			return this.$( '.acf-gallery-side-data' );
		},

		isFull: function () {
			var max = parseInt( this.get( 'max' ) );
			var count = this.$attachments().length;
			return max && count >= max;
		},

		getValue: function () {
			// vars
			var val = [];

			// loop
			this.$attachments().each( function () {
				val.push( $( this ).data( 'id' ) );
			} );

			// return
			return val.length ? val : false;
		},

		addUnscopedEvents: function ( self ) {
			// invalidField
			this.on( 'change', '.acf-gallery-side', function ( e ) {
				self.onUpdate( e, $( this ) );
			} );
		},

		addSortable: function ( self ) {
			// add sortable
			this.$collection().sortable( {
				items: '.acf-gallery-attachment',
				forceHelperSize: true,
				zIndex: 9999,
				forcePlaceholderSize: true,
				scroll: true,
				start: function ( event, ui ) {
					ui.placeholder.html( ui.item.html() );
					ui.placeholder.removeAttr( 'style' );
				},
				update: function ( event, ui ) {
					self.$input().trigger( 'change' );
				},
			} );

			// resizable
			this.$control().resizable( {
				handles: 's',
				minHeight: 200,
				stop: function ( event, ui ) {
					acf.update_user_setting( 'gallery_height', ui.size.height );
				},
			} );
		},

		initialize: function () {
			// add unscoped events
			this.addUnscopedEvents( this );

			// render
			this.render();
		},

		render: function () {
			// vars
			var $sort = this.$( '.acf-gallery-sort' );
			var $add = this.$( '.acf-gallery-add' );
			var count = this.$attachments().length;

			// disable add
			if ( this.isFull() ) {
				$add.addClass( 'disabled' );
			} else {
				$add.removeClass( 'disabled' );
			}

			// disable select
			if ( ! count ) {
				$sort.addClass( 'disabled' );
			} else {
				$sort.removeClass( 'disabled' );
			}

			// resize
			this.resize();
		},

		resize: function () {
			// vars
			var width = this.$control().width();
			var target = 150;
			var columns = Math.round( width / target );

			// max columns = 8
			columns = Math.min( columns, 8 );

			// update data
			this.$control().attr( 'data-columns', columns );
		},

		onResize: function () {
			this.resize();
		},

		openSidebar: function () {
			// add class
			this.$control().addClass( '-open' );

			// hide bulk actions
			// should be done with CSS
			//this.$main().find('.acf-gallery-sort').hide();

			// vars
			var width = this.$control().width() / 3;
			width = parseInt( width );
			width = Math.max( width, 350 );

			// animate
			this.$( '.acf-gallery-side-inner' ).css( { width: width - 1 } );
			this.$side().animate( { width: width - 1 }, 250 );
			this.$main().animate( { right: width }, 250 );
		},

		closeSidebar: function () {
			// remove class
			this.$control().removeClass( '-open' );

			// clear selection
			this.$active().removeClass( 'active' );

			// disable sidebar
			acf.disable( this.$side() );

			// animate
			var $sideData = this.$( '.acf-gallery-side-data' );
			this.$main().animate( { right: 0 }, 250 );
			this.$side().animate( { width: 0 }, 250, function () {
				$sideData.html( '' );
			} );
		},

		onClickAdd: function ( e, $el ) {
			// validate
			if ( this.isFull() ) {
				this.showNotice( {
					text: acf.__( 'Maximum selection reached' ),
					type: 'warning',
				} );
				return;
			}

			// new frame
			var frame = acf.newMediaPopup( {
				mode: 'select',
				title: acf.__( 'Add Image to Gallery' ),
				field: this.get( 'key' ),
				multiple: 'add',
				library: this.get( 'library' ),
				allowedTypes: this.get( 'mime_types' ),
				selected: this.val(),
				select: $.proxy( function ( attachment, i ) {
					this.appendAttachment( attachment, i );
				}, this ),
			} );
		},

		appendAttachment: function ( attachment, i ) {
			// vars
			attachment = this.validateAttachment( attachment );

			// bail early if is full
			if ( this.isFull() ) {
				return;
			}

			// bail early if already exists
			if ( this.$attachment( attachment.id ).length ) {
				return;
			}

			// html
			var html = [
				'<div class="acf-gallery-attachment" data-id="' +
					attachment.id +
					'">',
				'<input type="hidden" value="' +
					attachment.id +
					'" name="' +
					this.getInputName() +
					'[]">',
				'<div class="margin" title="">',
				'<div class="thumbnail">',
				'<img src="" alt="">',
				'</div>',
				'<div class="filename"></div>',
				'</div>',
				'<div class="actions">',
				'<a href="#" class="acf-icon -cancel dark acf-gallery-remove" data-id="' +
					attachment.id +
					'"></a>',
				'</div>',
				'</div>',
			].join( '' );
			var $html = $( html );

			// append
			this.$collection().append( $html );

			// move to beginning
			if ( this.get( 'insert' ) === 'prepend' ) {
				var $before = this.$attachments().eq( i );
				if ( $before.length ) {
					$before.before( $html );
				}
			}

			// render attachment
			this.renderAttachment( attachment );

			// render
			this.render();

			// trigger change
			this.$input().trigger( 'change' );
		},

		validateAttachment: function ( attachment ) {
			// defaults
			attachment = acf.parseArgs( attachment, {
				id: '',
				url: '',
				alt: '',
				title: '',
				filename: '',
				type: 'image',
			} );

			// WP attachment
			if ( attachment.attributes ) {
				attachment = attachment.attributes;

				// preview size
				var url = acf.isget(
					attachment,
					'sizes',
					this.get( 'preview_size' ),
					'url'
				);
				if ( url !== null ) {
					attachment.url = url;
				}
			}

			// return
			return attachment;
		},

		renderAttachment: function ( attachment ) {
			// vars
			attachment = this.validateAttachment( attachment );

			// vars
			var $el = this.$attachment( attachment.id );

			// Image type.
			if ( attachment.type == 'image' ) {
				// Remove filename.
				$el.find( '.filename' ).remove();

				// Other file type.
			} else {
				// Check for attachment featured image.
				var image = acf.isget( attachment, 'image', 'src' );
				if ( image !== null ) {
					attachment.url = image;
				}

				// Update filename text.
				$el.find( '.filename' ).text( attachment.filename );
			}

			// Default to mimetype icon.
			if ( ! attachment.url ) {
				attachment.url = acf.get( 'mimeTypeIcon' );
				$el.addClass( '-icon' );
			}

			// update els
			$el.find( 'img' ).attr( {
				src: attachment.url,
				alt: attachment.alt,
				title: attachment.title,
			} );

			// update val
			acf.val( $el.find( 'input' ), attachment.id );
		},

		editAttachment: function ( id ) {
			// new frame
			var frame = acf.newMediaPopup( {
				mode: 'edit',
				title: acf.__( 'Edit Image' ),
				button: acf.__( 'Update Image' ),
				attachment: id,
				field: this.get( 'key' ),
				select: $.proxy( function ( attachment, i ) {
					this.renderAttachment( attachment );
					// todo - render sidebar
				}, this ),
			} );
		},

		onClickEdit: function ( e, $el ) {
			var id = $el.data( 'id' );
			if ( id ) {
				this.editAttachment( id );
			}
		},

		removeAttachment: function ( id ) {
			// close sidebar (if open)
			this.closeSidebar();

			// remove attachment
			this.$attachment( id ).remove();

			// render
			this.render();

			// trigger change
			this.$input().trigger( 'change' );
		},

		onClickRemove: function ( e, $el ) {
			// prevent event from triggering click on attachment
			e.preventDefault();
			e.stopPropagation();

			//remove
			var id = $el.data( 'id' );
			if ( id ) {
				this.removeAttachment( id );
			}
		},

		selectAttachment: function ( id ) {
			// vars
			var $el = this.$attachment( id );

			// bail early if already active
			if ( $el.hasClass( 'active' ) ) {
				return;
			}

			// step 1
			var step1 = this.proxy( function () {
				// save any changes in sidebar
				this.$side().find( ':focus' ).trigger( 'blur' );

				// clear selection
				this.$active().removeClass( 'active' );

				// add selection
				$el.addClass( 'active' );

				// open sidebar
				this.openSidebar();

				// call step 2
				step2();
			} );

			// step 2
			var step2 = this.proxy( function () {
				const ajaxData = {
					action: 'acf/fields/gallery/get_attachment',
					nonce: this.get( 'nonce' ),
					field_key: this.get( 'key' ),
					id: id,
				};

				// abort prev ajax call
				if ( this.has( 'xhr' ) ) {
					this.get( 'xhr' ).abort();
				}

				// loading
				acf.showLoading( this.$sideData() );

				// get HTML
				var xhr = $.ajax( {
					url: acf.get( 'ajaxurl' ),
					data: acf.prepareForAjax( ajaxData ),
					type: 'post',
					dataType: 'html',
					cache: false,
					success: step3,
				} );

				// update
				this.set( 'xhr', xhr );
			} );

			// step 3
			var step3 = this.proxy( function ( html ) {
				// bail early if no html
				if ( ! html ) {
					return;
				}

				// vars
				var $side = this.$sideData();

				// render
				$side.html( html );

				// remove acf form data
				$side.find( '.compat-field-acf-form-data' ).remove();

				// merge tables
				$side
					.find( '> table.form-table > tbody' )
					.append(
						$side.find( '> .compat-attachment-fields > tbody > tr' )
					);

				// setup fields
				acf.doAction( 'append', $side );
			} );

			// run step 1
			step1();
		},

		onClickSelect: function ( e, $el ) {
			var id = $el.data( 'id' );
			if ( id ) {
				this.selectAttachment( id );
			}
		},

		onClickClose: function ( e, $el ) {
			this.closeSidebar();
		},

		onChangeSort: function ( e, $el ) {
			// Bail early if is disabled.
			if ( $el.hasClass( 'disabled' ) ) {
				return;
			}

			// Get sort val.
			var val = $el.val();
			if ( ! val ) {
				return;
			}

			// find ids
			var ids = [];
			this.$attachments().each( function () {
				ids.push( $( this ).data( 'id' ) );
			} );

			// step 1
			var step1 = this.proxy( function () {
				const ajaxData = {
					action: 'acf/fields/gallery/get_sort_order',
					nonce: this.get( 'nonce' ),
					field_key: this.get( 'key' ),
					ids: ids,
					sort: val,
				};

				// get results
				var xhr = $.ajax( {
					url: acf.get( 'ajaxurl' ),
					dataType: 'json',
					type: 'post',
					cache: false,
					data: acf.prepareForAjax( ajaxData ),
					success: step2,
				} );
			} );

			// step 2
			var step2 = this.proxy( function ( json ) {
				// validate
				if ( ! acf.isAjaxSuccess( json ) ) {
					return;
				}

				// reverse order
				json.data.reverse();

				// loop
				json.data.map( function ( id ) {
					this.$collection().prepend( this.$attachment( id ) );
				}, this );
			} );

			// call step 1
			step1();
		},

		onUpdate: function ( e, $el ) {
			// vars
			var $submit = this.$( '.acf-gallery-update' );

			// validate
			if ( $submit.hasClass( 'disabled' ) ) {
				return;
			}

			// serialize data
			const ajaxData = acf.serialize( this.$sideData() );

			// loading
			$submit.addClass( 'disabled' );
			$submit.before( '<i class="acf-loading"></i> ' );

			// Append AJAX action and nonce.
			ajaxData.action = 'acf/fields/gallery/update_attachment';
			ajaxData.nonce = this.get( 'nonce' );
			ajaxData.field_key = this.get( 'key' );

			// ajax
			$.ajax( {
				url: acf.get( 'ajaxurl' ),
				data: acf.prepareForAjax( ajaxData ),
				type: 'post',
				dataType: 'json',
				complete: function () {
					$submit.removeClass( 'disabled' );
					$submit.prev( '.acf-loading' ).remove();
				},
			} );
		},

		onHover: function () {
			// add sortable
			this.addSortable( this );

			// remove event
			this.off( 'mouseover' );
		},
	} );

	acf.registerFieldType( Field );

	// register existing conditions
	acf.registerConditionForFieldType( 'hasValue', 'gallery' );
	acf.registerConditionForFieldType( 'hasNoValue', 'gallery' );
	acf.registerConditionForFieldType( 'selectionLessThan', 'gallery' );
	acf.registerConditionForFieldType( 'selectionGreaterThan', 'gallery' );
} )( jQuery );
