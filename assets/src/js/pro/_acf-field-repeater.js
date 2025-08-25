( function ( $ ) {
	var Field = acf.Field.extend( {
		type: 'repeater',
		wait: '',
		page: 1,
		nextRowNum: 0,

		events: {
			'click a[data-event="add-row"]': 'onClickAdd',
			'click a[data-event="duplicate-row"]': 'onClickDuplicate',
			'click a[data-event="remove-row"]': 'onClickRemove',
			'click a[data-event="collapse-row"]': 'onClickCollapse',
			'click a[data-event="first-page"]:not(.disabled)' : 'onClickFirstPage',
			'click a[data-event="last-page"]:not(.disabled)' : 'onClickLastPage',
			'click a[data-event="prev-page"]:not(.disabled)': 'onClickPrevPage',
			'click a[data-event="next-page"]:not(.disabled)': 'onClickNextPage',
			'change .current-page': 'onChangeCurrentPage',
			'click .acf-order-input-wrap': 'onClickRowOrder',
			'blur .acf-order-input': 'onBlurRowOrder',
			'change .acf-order-input': 'onChangeRowOrder',
			'changed:total_rows': 'onChangeTotalRows',
			showField: 'onShow',
			unloadField: 'onUnload',
			mouseover: 'onHover',
			change: 'onChangeField',
		},

		$control: function () {
			return this.$( '.acf-repeater:first' );
		},

		$table: function () {
			return this.$( 'table:first' );
		},

		$tbody: function () {
			return this.$( 'tbody:first' );
		},

		$rows: function () {
			return this.$( 'tbody:first > tr' ).not( '.acf-clone, .acf-deleted' );
		},

		$row: function ( index ) {
			return this.$( 'tbody:first > tr:eq(' + index + ')' );
		},

		$clone: function () {
			return this.$( 'tbody:first > tr.acf-clone' );
		},

		$actions: function () {
			return this.$( '.acf-actions:last' );
		},

		$button: function () {
			return this.$( '.acf-actions:last .button' );
		},

		$firstPageButton: function() {
			return this.$( '.acf-tablenav:last .first-page' );
		},

		$prevPageButton: function() {
			return this.$( '.acf-tablenav:last .prev-page' );
		},

		$nextPageButton: function() {
			return this.$( '.acf-tablenav:last .next-page' );
		},

		$lastPageButton: function() {
			return this.$( '.acf-tablenav:last .last-page' );
		},

		$pageInput: function() {
			return this.$( '.current-page:last' );
		},

		totalPages: function() {
			const totalPages = this.$( '.acf-total-pages:last' ).text();
			return parseInt( totalPages );
		},

		getValue: function () {
			return this.$rows().length;
		},

		allowRemove: function () {
			let numRows = this.val();
			let minRows = parseInt( this.get( 'min' ) );

			if ( this.get( 'pagination' ) ) {
				numRows = this.get( 'total_rows' );
			}

			return ! minRows || minRows < numRows;
		},

		allowAdd: function () {
			let numRows = this.val();
			let maxRows = parseInt( this.get( 'max' ) );

			if ( this.get( 'pagination' ) ) {
				numRows = this.get( 'total_rows' );
			}

			return ! maxRows || maxRows > numRows;
		},

		addSortable: function ( self ) {
			// bail early if max 1 row
			if ( this.get( 'max' ) == 1 ) {
				return;
			}

			// Bail early if using pagination.
			if ( this.get( 'pagination') ) {
				return;
			}

			// add sortable
			this.$tbody().sortable( {
				items: '> tr',
				handle: '> td.order',
				zIndex: 9999,
				forceHelperSize: true,
				forcePlaceholderSize: true,
				scroll: true,
				stop: function ( event, ui ) {
					self.render();
				},
				update: function ( event, ui ) {
					self.$input().trigger( 'change' );
				},
			} );
		},

		addCollapsed: function () {
			// vars
			var indexes = preference.load( this.get( 'key' ) );

			// bail early if no collapsed
			if ( ! indexes ) {
				return false;
			}

			// loop
			this.$rows().each( function ( i ) {
				if ( indexes.indexOf( i ) > -1 ) {
					if ( $( this ).find( '.-collapsed-target' ).length ) {
						$( this ).addClass( '-collapsed' );
					}
				}
			} );
		},

		addUnscopedEvents: function ( self ) {
			// invalidField
			this.on( 'invalidField', '.acf-row', function ( e ) {
				var $row = $( this );
				if ( self.isCollapsed( $row ) ) {
					self.expand( $row );
				}
			} );

			// Listen for changes to fields, so we can persist them in the DOM.
			if ( this.get( 'pagination' ) ) {
				this.on( 'change', 'input, select, textarea', function ( e ) {
					const $changed = $( e.currentTarget );
					if ( ! $changed.hasClass( 'acf-order-input' ) && ! $changed.hasClass( 'acf-row-status' ) ) {
						self.onChangeField( e, $( this ) );
					}
				} );
			}

			this.listenForSavedMetaBoxes();
		},

		initialize: function () {
			// add unscoped events
			this.addUnscopedEvents( this );

			// add collapsed
			this.addCollapsed();

			// disable clone
			acf.disable( this.$clone(), this.cid );

			// Set up the next row number.
			if ( this.get( 'pagination' ) ) {
				this.nextRowNum = this.get( 'total_rows' );
			}

			// render
			this.render();
		},

		render: function ( update_order_numbers = true ) {
			// Update order number.
			if ( update_order_numbers ) {
				this.$rows().each( function ( i ) {
					$( this )
						.find( '> .order > span' )
						.html( i + 1 );
				} );
			}

			// Extract vars.
			var $control = this.$control();
			var $button = this.$button();

			// empty
			if ( this.val() == 0 ) {
				$control.addClass( '-empty' );
			} else {
				$control.removeClass( '-empty' );
			}

			// Reached max rows.
			if ( ! this.allowAdd() ) {
				$control.addClass( '-max' );
				$button.addClass( 'disabled' );
			} else {
				$control.removeClass( '-max' );
				$button.removeClass( 'disabled' );
			}

			if ( this.get( 'pagination' ) ) {
				this.maybeDisablePagination();
			}

			// Reached min rows (not used).
			//if( !this.allowRemove() ) {
			//	$control.addClass('-min');
			//} else {
			//	$control.removeClass('-min');
			//}
		},

		listenForSavedMetaBoxes: function() {
			if ( ! acf.isGutenbergPostEditor() || ! this.get( 'pagination' ) ) {
				return;
			}

			let checkedMetaBoxes = true;
			wp.data.subscribe( () => {
				if ( wp.data.select( 'core/edit-post' ).isSavingMetaBoxes() ) {
					checkedMetaBoxes = false;
				} else {
					if ( ! checkedMetaBoxes ) {
						checkedMetaBoxes = true;
						this.set( 'total_rows', 0, true );
						this.ajaxLoadPage( true );
					}
				}
			} );
		},

		incrementTotalRows: function() {
			let totalRows = this.get( 'total_rows' );
			this.set( 'total_rows', ++totalRows, true );
		},

		decrementTotalRows: function() {
			let totalRows = this.get( 'total_rows' );
			this.set( 'total_rows', --totalRows, true );
		},

		validateAdd: function () {
			// return true if allowed
			if ( this.allowAdd() ) {
				return true;
			}

			// vars
			var max = this.get( 'max' );
			var text = acf.__( 'Maximum rows reached ({max} rows)' );

			// replace
			text = text.replace( '{max}', max );

			// add notice
			this.showNotice( {
				text: text,
				type: 'warning',
			} );

			// return
			return false;
		},

		onClickAdd: function ( e, $el ) {
			// validate
			if ( ! this.validateAdd() ) {
				return false;
			}

			// add above row
			if ( $el.hasClass( 'acf-icon' ) ) {
				this.add( {
					before: $el.closest( '.acf-row' ),
				} );

				// default
			} else {
				this.add();
			}
		},

		add: function ( args ) {
			// validate
			if ( ! this.allowAdd() ) {
				return false;
			}

			// defaults
			args = acf.parseArgs( args, {
				before: false,
			} );

			// add row
			var $el = acf.duplicate( {
				target: this.$clone(),
				append: this.proxy( function ( $el, $el2 ) {
					// append
					if ( args.before ) {
						args.before.before( $el2 );
					} else {
						$el.before( $el2 );
					}

					// remove clone class
					$el2.removeClass( 'acf-clone' );

					// enable
					acf.enable( $el2, this.cid );
				} ),
			} );

			if ( this.get( 'pagination' ) ) {
				this.incrementTotalRows();

				if ( false !== args.before ) {
					// If the row was inserted above an existing row, try to keep that order.
					const prevRowNum = parseInt( args.before.find( '.acf-row-number' ).first().text() ) || 0;
					let newRowNum = prevRowNum;

					if ( newRowNum && ! args.before.hasClass( 'acf-inserted' ) && ! args.before.hasClass( 'acf-added' ) ) {
						--newRowNum;
					}

					if ( args.before.hasClass( 'acf-divider' ) ) {
						args.before.removeClass( 'acf-divider' );
						$el.addClass( 'acf-divider' );
					}

					this.updateRowStatus( $el, 'inserted' );
					this.updateRowStatus( $el, 'reordered', newRowNum );

					// Hide the row numbers to avoid confusion with existing rows.
					$el.find( '.acf-row-number' ).first().hide().text( newRowNum );
					if ( ! $el.find( '.acf-order-input-wrap' ).hasClass( 'disabled' ) ) {
						let message =  acf.__( 'Order will be assigned upon save' );
						$el.find( '.acf-order-input-wrap' ).addClass( 'disabled' );
						$el.find( '.acf-row-number' ).first().after( '<span title="' + message + '">-</span>' );
					}
					$el.find( '.acf-order-input' ).first().hide();
					$el.attr( 'data-inserted', newRowNum );
				} else {
					this.nextRowNum++;

					$el.find( '.acf-order-input' ).first().val( this.nextRowNum );
					$el.find( '.acf-row-number' ).first().text( this.nextRowNum );
					this.updateRowStatus( $el, 'added' );

					if ( ! this.$tbody().find( '.acf-divider' ).length ) {
						$el.addClass( 'acf-divider' );
					}
				}

				$el.find( '.acf-input:first' )
					.find( 'input:not([type=hidden]), select, textarea' )
					.first()
					.trigger( 'focus' );
			}

			// Render and trigger change for validation errors.
			this.render();
			this.$input().trigger( 'change' );

			return $el;
		},

		onClickDuplicate: function ( e, $el ) {
			// Validate with warning.
			if ( ! this.validateAdd() ) {
				return false;
			}

			// get layout and duplicate it.
			var $row = $el.closest( '.acf-row' );
			this.duplicateRow( $row );
		},

		duplicateRow: function ( $row ) {
			// Validate without warning.
			if ( ! this.allowAdd() ) {
				return false;
			}

			// Vars.
			var fieldKey = this.get( 'key' );

			// Duplicate row.
			var $el = acf.duplicate( {
				target: $row,

				// Provide a custom renaming callback to avoid renaming parent row attributes.
				rename: function ( name, value, search, replace ) {
					// Rename id attributes from "field_1-search" to "field_1-replace".
					if ( name === 'id' || name === 'for' ) {
						return value.replace(
							fieldKey + '-' + search,
							fieldKey + '-' + replace
						);

						// Rename name and for attributes from "[field_1][search]" to "[field_1][replace]".
					} else {
						return value.replace(
							fieldKey + '][' + search,
							fieldKey + '][' + replace
						);
					}
				},
				before: function ( $el ) {
					acf.doAction( 'unmount', $el );
				},
				after: function ( $el, $el2 ) {
					acf.doAction( 'remount', $el );
				},
			} );

			if ( this.get( 'pagination' ) ) {
				this.incrementTotalRows();

				// If the row was inserted above an existing row, try to keep that order.
				const prevRowNum = parseInt( $row.find( '.acf-row-number' ).first().text() ) || 0;

				this.updateRowStatus( $el, 'inserted' );
				this.updateRowStatus( $el, 'reordered', prevRowNum );

				// Hide the row numbers to avoid confusion with existing rows.
				$el.find( '.acf-row-number' ).first().hide();
				if ( ! $el.find( '.acf-order-input-wrap' ).hasClass( 'disabled' ) ) {
					let message =  acf.__( 'Order will be assigned upon save' );
					$el.find( '.acf-order-input-wrap' ).addClass( 'disabled' );
					$el.find( '.acf-row-number' ).first().after( '<span title="' + message + '">-</span>' );
				}
				$el.find( '.acf-order-input' ).first().hide();
				$el.attr( 'data-inserted', prevRowNum );
				$el.removeClass( 'acf-divider' );
			}

			// trigger change for validation errors
			this.$input().trigger( 'change' );

			// Update order numbers.
			this.render();

			// Focus on new row.
			acf.focusAttention( $el );

			// Return new layout.
			return $el;
		},

		validateRemove: function () {
			// return true if allowed
			if ( this.allowRemove() ) {
				return true;
			}

			// vars
			var min = this.get( 'min' );
			var text = acf.__( 'Minimum rows not reached ({min} rows)' );

			// replace
			text = text.replace( '{min}', min );

			// add notice
			this.showNotice( {
				text: text,
				type: 'warning',
			} );

			// return
			return false;
		},

		onClickRemove: function ( e, $el ) {
			var $row = $el.closest( '.acf-row' );

			// Bypass confirmation when holding down "shift" key.
			if ( e.shiftKey ) {
				return this.remove( $row );
			}

			// add class
			$row.addClass( '-hover' );

			// add tooltip
			var tooltip = acf.newTooltip( {
				confirmRemove: true,
				target: $el,
				context: this,
				confirm: function () {
					this.remove( $row );
				},
				cancel: function () {
					$row.removeClass( '-hover' );
				},
			} );
		},

		onClickRowOrder: function( e, $el ) {
			if ( ! this.get( 'pagination' ) ) {
				return;
			}

			if ( $el.hasClass( 'disabled' ) ) {
				return;
			}

			$el.find( '.acf-row-number' ).hide();
			$el.find( '.acf-order-input' ).show().trigger( 'select' );
		},

		onBlurRowOrder: function( e, $el ) {
			this.onChangeRowOrder( e, $el, false );
		},

		onChangeRowOrder: function( e, $el, update = true ) {
			if ( ! this.get( 'pagination' ) ) {
				return;
			}

			const $row = $el.closest( '.acf-row' );
			const $orderSpan = $row.find( '.acf-row-number' ).first();
			let hrOrder = $el.val();

			$row.find( '.acf-order-input' ).first().hide();

			if ( ! acf.isNumeric( hrOrder ) || parseFloat( hrOrder ) < 0 ) {
				$orderSpan.show();
				return;
			}

			hrOrder = Math.round( hrOrder );

			const newOrder = hrOrder - 1;

			$el.val( hrOrder );
			$orderSpan.text( hrOrder ).show();

			if ( update ) {
				this.updateRowStatus( $row, 'reordered', newOrder );
			}
		},

		onChangeTotalRows: function() {
			const perPage = parseInt( this.get( 'per_page' ) ) || 20;
			const totalRows = parseInt( this.get( 'total_rows' ) ) || 0;
			const totalPages = Math.ceil( totalRows / perPage );

			// Update the total pages in pagination.
			this.$( '.acf-total-pages:last' ).text( totalPages );
			this.nextRowNum = totalRows;

			// If the current page no longer exists, load the last page.
			if ( this.page > totalPages ) {
				this.page = totalPages;
				this.ajaxLoadPage();
			}
		},

		remove: function ( $row ) {
			const self = this;

			if ( this.get( 'pagination' ) ) {
				this.decrementTotalRows();

				// If using pagination and the row had already been saved, just hide the row instead of deleting it.
				if ( $row.data( 'id' ).includes( 'row-' ) ){
					this.updateRowStatus( $row, 'deleted' );

					$row.hide();
					self.$input().trigger( 'change' );
					self.render( false );
					return;
				} else if ( $row.hasClass( 'acf-divider' ) ) {
					$row.next( '.acf-added' ).addClass( 'acf-divider' );
				}
			}

			// If not using pagination, delete the actual row.
			acf.remove( {
				target: $row,
				endHeight: 0,
				complete: function () {
					// trigger change to allow attachment save
					self.$input().trigger( 'change' );

					// render
					self.render();

					// sync collapsed order
					//self.sync();
				},
			} );
		},

		isCollapsed: function ( $row ) {
			return $row.hasClass( '-collapsed' );
		},

		collapse: function ( $row ) {
			$row.addClass( '-collapsed' );
			acf.doAction( 'hide', $row, 'collapse' );
		},

		expand: function ( $row ) {
			$row.removeClass( '-collapsed' );
			acf.doAction( 'show', $row, 'collapse' );
		},

		onClickCollapse: function ( e, $el ) {
			// vars
			var $row = $el.closest( '.acf-row' );
			var isCollpased = this.isCollapsed( $row );

			// shift
			if ( e.shiftKey ) {
				$row = this.$rows();
			}

			// toggle
			if ( isCollpased ) {
				this.expand( $row );
			} else {
				this.collapse( $row );
			}
		},

		onShow: function ( e, $el, context ) {
			// get sub fields
			var fields = acf.getFields( {
				is: ':visible',
				parent: this.$el,
			} );

			// trigger action
			// - ignore context, no need to pass through 'conditional_logic'
			// - this is just for fields like google_map to render itself
			acf.doAction( 'show_fields', fields );
		},

		onUnload: function () {
			// vars
			var indexes = [];

			// loop
			this.$rows().each( function ( i ) {
				if ( $( this ).hasClass( '-collapsed' ) ) {
					indexes.push( i );
				}
			} );

			// allow null
			indexes = indexes.length ? indexes : null;

			// set
			preference.save( this.get( 'key' ), indexes );
		},

		onHover: function () {
			// add sortable
			this.addSortable( this );

			// remove event
			this.off( 'mouseover' );
		},

		onChangeField: function( e, $el ) {
			const $target = $( e.delegateTarget );
			let $row = $el.closest( '.acf-row' );

			if ( $row.closest( '.acf-field-repeater' ).data( 'key' ) !== $target.data( 'key' ) ) {
				$row = $row.parent().closest( '.acf-row' );
			}

			this.updateRowStatus( $row, 'changed' );
		},

		updateRowStatus: function( $row, status, data = true ) {
			if ( ! this.get( 'pagination' ) ) {
				return;
			}

			const parent_key = $row.parents( '.acf-field-repeater' ).data( 'key' );

			if ( this.parent() && parent_key !== this.get( 'key' ) ) {
				return;
			}

			const row_id = $row.data( 'id' );
			const input_name = this.$el.find( '.acf-repeater-hidden-input:first' ).attr( 'name' );
			const status_name = `${input_name}[${row_id}][acf_${status}]`;
			const status_input = `<input type="hidden" class="acf-row-status" name="${status_name}" value="${data}" />`;

			if ( ! $row.hasClass( 'acf-' + status ) ) {
				$row.addClass( 'acf-' + status );
			}

			// TODO: Update so that this doesn't get messed up with repeater subfields.
			const $existing_status = $row.find( `input[name='${status_name}']` );
			if ( ! $existing_status.length ) {
				$row.find( 'td' ).first().append( status_input );
			} else {
				$existing_status.val( data );
			}
		},

		onClickFirstPage: function() {
			this.validatePage( 1 );
		},

		onClickPrevPage: function() {
			this.validatePage( this.page - 1 );
		},

		onClickNextPage: function( e ) {
			this.validatePage( this.page + 1 );
		},

		onClickLastPage: function() {
			this.validatePage( this.totalPages() );
		},

		onChangeCurrentPage: function() {
			this.validatePage( this.$pageInput().val() );
		},

		maybeDisablePagination: function() {
			this.$actions().find( '.acf-nav' ).removeClass( 'disabled' );

			if ( this.page <= 1 ) {
				this.$firstPageButton().addClass( 'disabled' );
				this.$prevPageButton().addClass( 'disabled' );
			}

			if ( this.page >= this.totalPages() ) {
				this.$nextPageButton().addClass( 'disabled' );
				this.$lastPageButton().addClass( 'disabled' );
			}
		},

		validatePage: function( nextPage ) {
			const self = this;

			// Validate the current page.
			acf.validateForm( {
				form: this.$control(),
				event: '',
				reset: true,
				success: function( $form ) {
					self.page = nextPage;

					// Set up some sane defaults.
					if ( self.page <= 1 ) {
						self.page = 1;
					}
					if ( self.page >= self.totalPages() ) {
						self.page = self.totalPages();
					}

					self.ajaxLoadPage();
				},
				failure: function( $form ) {
					self.$pageInput().val( self.page );
					return false;
				},
			} );
		},

		ajaxLoadPage: function( clearChanged = false ) {
			const ajaxData = acf.prepareForAjax( {
				action: 'acf/ajax/query_repeater',
				paged: this.page,
				field_key: this.get( 'key' ),
				field_name: this.get( 'orig_name' ),
				rows_per_page: parseInt( this.get( 'per_page' ) ),
				refresh: clearChanged,
				nonce: this.get( 'nonce' )
			} );

			$.ajax(
				{
					url: ajaxurl,
					method: 'POST',
					dataType: 'json',
					data: ajaxData,
					context: this,
				}
			).done(
				function( response ) {
					const { rows } = response.data;
					const $existingRows = this.$tbody().find( '> tr' );

					$existingRows.not( '.acf-clone' ).hide();

					if ( clearChanged ) {
						// Remove any existing rows since we are refreshing from the server.
						$existingRows.not( '.acf-clone' ).remove();

						// Trigger a change in total rows, so we can update pagination.
						this.set( 'total_rows', response.data.total_rows, false );
					} else {
						$existingRows.not( '.acf-changed, .acf-deleted, .acf-reordered, .acf-added, .acf-inserted, .acf-clone' ).remove();
					}

					Object.keys( rows ).forEach( index => {
						let $row         = false;
						let $unsavedRow  = this.$tbody().find( '> *[data-id=row-' + index + ']' );
						let $insertedRow = this.$tbody().find( '> *[data-inserted=' + index + ']' );

						// Unsaved new rows that are inserted into this specific position.
						if ( $insertedRow.length ) {
							$insertedRow.appendTo( this.$tbody() ).show();
							acf.doAction( 'remount', $insertedRow );
						}

						// Skip unsaved deleted rows; we don't want to show them again.
						if ( $unsavedRow.hasClass( 'acf-deleted' ) ) {
							return;
						}

						// Unsaved edited rows should be moved to correct position.
						if ( $unsavedRow.length ) {
							acf.doAction( 'unmount', $unsavedRow );
							$unsavedRow.appendTo( this.$tbody() ).show();
							acf.doAction( 'remount', $unsavedRow );
							return;
						}

						// Rows from the server (that haven't been changed or deleted) should be appended and shown.
						$row = $( rows[ index ] );
						this.$tbody().append( $row ).show();
						acf.doAction( 'remount', $row );

						// Move clone field back to the right spot.
						this.$clone().appendTo( this.$tbody() );
					} );

					const $addedRows = this.$tbody().find( '.acf-added:hidden' );

					// If there are any new rows that are still hidden, append them to the bottom.
					if ( $addedRows.length ) {
						const self = this;

						$addedRows.each( function() {
							const $addedRow = $( this );
							$addedRow.insertBefore( self.$clone() ).show();
							acf.doAction( 'remount', $addedRow );
						} );
					}

					// Update the page input.
					this.$pageInput().val( this.page );
					this.maybeDisablePagination();
				}
			).fail(
				function( jqXHR, textStatus, errorThrown ) {
					const error = acf.getXhrError( jqXHR );
					let message = acf.__( 'Error loading page' );

					if ( '' !== error ) {
						message = `${message}: ${error}`;
					}

					this.showNotice( {
						text: message,
						type: 'warning',
					} );
				}
			);
		},

	} );

	acf.registerFieldType( Field );

	// register existing conditions
	acf.registerConditionForFieldType( 'hasValue', 'repeater' );
	acf.registerConditionForFieldType( 'hasNoValue', 'repeater' );
	acf.registerConditionForFieldType( 'lessThan', 'repeater' );
	acf.registerConditionForFieldType( 'greaterThan', 'repeater' );

	// state
	var preference = new acf.Model( {
		name: 'this.collapsedRows',

		key: function ( key, context ) {
			// vars
			var count = this.get( key + context ) || 0;

			// update
			count++;
			this.set( key + context, count, true );

			// modify fieldKey
			if ( count > 1 ) {
				key += '-' + count;
			}

			// return
			return key;
		},

		load: function ( key ) {
			// vars
			var key = this.key( key, 'load' );
			var data = acf.getPreference( this.name );

			// return
			if ( data && data[ key ] ) {
				return data[ key ];
			} else {
				return false;
			}
		},

		save: function ( key, value ) {
			// vars
			var key = this.key( key, 'save' );
			var data = acf.getPreference( this.name ) || {};

			// delete
			if ( value === null ) {
				delete data[ key ];

				// append
			} else {
				data[ key ] = value;
			}

			// allow null
			if ( $.isEmptyObject( data ) ) {
				data = null;
			}

			// save
			acf.setPreference( this.name, data );
		},
	} );
} )( jQuery );
