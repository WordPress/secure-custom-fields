( function ( $, undefined ) {
	const Field = acf.Field.extend( {
		type: 'icon_picker',

		wait: 'load',

		events: {
			showField: 'scrollToSelectedDashicon',
			'input .acf-icon_url': 'onUrlChange',
			'click .acf-icon-picker-list-icon': 'onDashiconClick',
			'focus .acf-icon-picker-list-icon-radio': 'onDashiconRadioFocus',
			'blur .acf-icon-picker-list-icon-radio': 'onDashiconRadioBlur',
			'keydown .acf-icon-picker-list-icon-radio': 'onDashiconKeyDown',
			'input .acf-icon-list-search-input': 'onDashiconSearch',
			'keydown .acf-icon-list-search-input': 'onDashiconSearchKeyDown',
			'click .acf-icon-picker-media-library-button':
				'onMediaLibraryButtonClick',
			'click .acf-icon-picker-media-library-preview':
				'onMediaLibraryButtonClick',
		},

		$typeInput() {
			return this.$(
				'input[type="hidden"][data-hidden-type="type"]:first'
			);
		},

		$valueInput() {
			return this.$(
				'input[type="hidden"][data-hidden-type="value"]:first'
			);
		},

		$tabButton() {
			return this.$( '.acf-tab-button' );
		},

		$selectedIcon() {
			return this.$( '.acf-icon-picker-list-icon.active' );
		},

		$selectedRadio() {
			return this.$( '.acf-icon-picker-list-icon.active input' );
		},

		$iconsList() {
			return this.$( '.acf-icon-list:visible' );
		},

		$mediaLibraryButton() {
			return this.$( '.acf-icon-picker-media-library-button' );
		},

		initialize() {
			// Set up actions hook callbacks.
			this.addActions();

			// Initialize the state of the icon picker.
			let typeAndValue = {
				type: this.$typeInput().val(),
				value: this.$valueInput().val()
			};

			// Store the type and value object.
			this.set( 'typeAndValue', typeAndValue );

			// Any time any acf tab is clicked, we will re-scroll to the selected dashicon.
			$( '.acf-tab-button' ).on( 'click', () => {
				this.initializeDashiconsTab( this.get( 'typeAndValue' ) );
			} );

			// Fire the action which lets people know the state has been updated.
			acf.doAction(
				this.get( 'name' ) + '/type_and_value_change',
				typeAndValue
			);

			this.initializeDashiconsTab( typeAndValue );
			this.alignMediaLibraryTabToCurrentValue( typeAndValue );
		},

		addActions() {
			// Set up an action listener for when the type and value changes.
			acf.addAction(
				this.get( 'name' ) + '/type_and_value_change',
				( newTypeAndValue ) => {
					// Align the visual state of each tab to the current value.
					this.alignDashiconsTabToCurrentValue( newTypeAndValue );
					this.alignMediaLibraryTabToCurrentValue( newTypeAndValue );
					this.alignUrlTabToCurrentValue( newTypeAndValue );
				}
			);
		},

		updateTypeAndValue( type, value ) {
			const typeAndValue = {
				type,
				value,
			};

			// Update the values in the hidden fields, which are what will actually be saved.
			acf.val( this.$typeInput(), type );
			acf.val( this.$valueInput(), value );

			// Fire an action to let each tab set itself according to the typeAndValue state.
			acf.doAction(
				this.get( 'name' ) + '/type_and_value_change',
				typeAndValue
			);

			// Set the state.
			this.set( 'typeAndValue', typeAndValue );
		},

		scrollToSelectedDashicon() {
			const innerElement = this.$selectedIcon();

			// If no icon is selected, do nothing.
			if ( innerElement.length === 0 ) {
				return;
			}

			const scrollingDiv = this.$iconsList();
			scrollingDiv.scrollTop( 0 );

			const distance = innerElement.position().top - 50;

			if ( distance === 0 ) {
				return;
			}

			scrollingDiv.scrollTop( distance );
		},

		initializeDashiconsTab( typeAndValue ) {
			const dashicons = this.getDashiconsList() || [];
			this.set( 'dashicons', dashicons );
			this.renderDashiconList();
			this.initializeSelectedDashicon( typeAndValue );
		},

		initializeSelectedDashicon( typeAndValue ) {
			if ( typeAndValue.type !== 'dashicons' ) {
				return;
			}
			// Select the correct dashicon.
			this.selectDashicon( typeAndValue.value, false ).then( () => {
				// Scroll to the selected dashicon.
				this.scrollToSelectedDashicon();
			} );
		},

		alignDashiconsTabToCurrentValue( typeAndValue ) {
			if ( typeAndValue.type !== 'dashicons' ) {
				this.unselectDashicon();
			}
		},

		renderDashiconHTML( dashicon ) {
			const id = `${ this.get( 'name' ) }-${ dashicon.key }`;
			return `<div class="dashicons ${ acf.strEscape(
				dashicon.key
			) } acf-icon-picker-list-icon" data-icon="${ acf.strEscape(
				dashicon.key
			) }">
				<label for="${ acf.strEscape( id ) }">${ acf.strEscape(
					dashicon.label
				) }</label>
				<input id="${ acf.strEscape(
					id
				) }" type="radio" class="acf-icon-picker-list-icon-radio" name="acf-icon-picker-list-icon-radio" value="${ acf.strEscape(
					dashicon.key
				) }">
			</div>`;
		},

		renderDashiconList() {
			const dashicons = this.get( 'dashicons' );

			this.$iconsList().empty();
			dashicons.forEach( ( dashicon ) => {
				this.$iconsList().append(
					this.renderDashiconHTML( dashicon )
				);
			} );
		},

		getDashiconsList() {
			const iconPickeri10n = acf.get( 'iconPickeri10n' ) || [];

			const dashicons = Object.entries( iconPickeri10n ).map(
				( [ key, value ] ) => {
					return {
						key,
						label: value,
					};
				}
			);

			return dashicons;
		},

		getDashiconsBySearch( searchTerm ) {
			const lowercaseSearchTerm = searchTerm.toLowerCase();
			const dashicons = this.getDashiconsList();

			const filteredDashicons = dashicons.filter( function ( icon ) {
				const lowercaseIconLabel = icon.label.toLowerCase();
				return lowercaseIconLabel.indexOf( lowercaseSearchTerm ) > -1;
			} );

			return filteredDashicons;
		},

		selectDashicon( dashicon, setFocus = true ) {
			this.set( 'selectedDashicon', dashicon );

			// Select the new one.
			const $newIcon = this.$iconsList().find(
				'.acf-icon-picker-list-icon[data-icon="' + dashicon + '"]'
			);
			$newIcon.addClass( 'active' );

			const $input = $newIcon.find( 'input' );
			const thePromise = $input.prop( 'checked', true ).promise();

			if ( setFocus ) {
				$input.trigger( 'focus' );
			}

			this.updateTypeAndValue( 'dashicons', dashicon );

			return thePromise;
		},

		unselectDashicon() {
			// Remove the currently active dashicon, if any.
			this.$iconsList()
				.find( '.acf-icon-picker-list-icon' )
				.removeClass( 'active' );
			this.set( 'selectedDashicon', false );
		},

		onDashiconRadioFocus( e ) {
			const dashicon = e.target.value;

			const $newIcon = this.$iconsList().find(
				'.acf-icon-picker-list-icon[data-icon="' + dashicon + '"]'
			);
			$newIcon.addClass( 'focus' );

			// If this is a different icon than previously selected, select it.
			if ( this.get( 'selectedDashicon' ) !== dashicon ) {
				this.unselectDashicon();
				this.selectDashicon( dashicon );
			}
		},

		onDashiconRadioBlur( e ) {
			const icon = this.$( e.target );
			const iconParent = icon.parent();

			iconParent.removeClass( 'focus' );
		},

		onDashiconClick( e ) {
			e.preventDefault();

			const icon = this.$( e.target );
			const dashicon = icon.find( 'input' ).val();

			const $newIcon = this.$iconsList().find(
				'.acf-icon-picker-list-icon[data-icon="' + dashicon + '"]'
			);

			// By forcing focus on the input, we fire onDashiconRadioFocus.
			$newIcon.find( 'input' ).prop( 'checked', true ).trigger( 'focus' );
		},

		onDashiconSearch( e ) {
			const searchTerm = e.target.value;
			const filteredDashicons = this.getDashiconsBySearch( searchTerm );

			if ( filteredDashicons.length > 0 || ! searchTerm ) {
				this.set( 'dashicons', filteredDashicons );
				this.$( '.acf-icon-list-empty' ).hide();
				this.$( '.acf-icon-list ' ).show();
				this.renderDashiconList();

				// Announce change of data to screen readers.
				wp.a11y.speak(
					acf.get( 'iconPickerA11yStrings' )
						.newResultsFoundForSearchTerm,
					'polite'
				);
			} else {
				// Truncate the search term if it's too long.
				const visualSearchTerm =
					searchTerm.length > 30
						? searchTerm.substring( 0, 30 ) + '&hellip;'
						: searchTerm;

				this.$( '.acf-icon-list ' ).hide();
				this.$( '.acf-icon-list-empty' )
					.find( '.acf-invalid-dashicon-search-term' )
					.text( visualSearchTerm );
				this.$( '.acf-icon-list-empty' ).css( 'display', 'flex' );
				this.$( '.acf-icon-list-empty' ).show();

				// Announce change of data to screen readers.
				wp.a11y.speak(
					acf.get( 'iconPickerA11yStrings' ).noResultsForSearchTerm,
					'polite'
				);
			}
		},

		onDashiconSearchKeyDown( e ) {
			// Check if the pressed key is Enter (key code 13)
			if ( e.which === 13 ) {
				// Prevent submitting the entire form if someone presses enter after searching.
				e.preventDefault();
			}
		},

		onDashiconKeyDown( e ) {
			if ( e.which === 13 ) {
				// If someone presses enter while an icon is focused, prevent the form from submitting.
				e.preventDefault();
			}
		},

		alignMediaLibraryTabToCurrentValue( typeAndValue ) {
			const type = typeAndValue.type;
			const value = typeAndValue.value;

			if ( type !== 'media_library' && type !== 'dashicons' ) {
				// Hide the preview container on the media library tab.
				this.$( '.acf-icon-picker-media-library-preview' ).hide();
			}

			if ( type === 'media_library' ) {
				const previewUrl = this.get( 'mediaLibraryPreviewUrl' );
				// Set the image file preview src.
				this.$( '.acf-icon-picker-media-library-preview-img img' ).attr(
					'src',
					previewUrl
				);

				// Hide the dashicon preview.
				this.$(
					'.acf-icon-picker-media-library-preview-dashicon'
				).hide();

				// Show the image file preview.
				this.$( '.acf-icon-picker-media-library-preview-img' ).show();

				// Show the preview container (it may have been hidden if nothing was ever selected yet).
				this.$( '.acf-icon-picker-media-library-preview' ).show();
			}

			if ( type === 'dashicons' ) {
				// Set the dashicon preview class.
				this.$(
					'.acf-icon-picker-media-library-preview-dashicon .dashicons'
				).attr( 'class', 'dashicons ' + value );

				// Hide the image file preview.
				this.$( '.acf-icon-picker-media-library-preview-img' ).hide();

				// Show the dashicon preview.
				this.$(
					'.acf-icon-picker-media-library-preview-dashicon'
				).show();

				// Show the preview container (it may have been hidden if nothing was ever selected yet).
				this.$( '.acf-icon-picker-media-library-preview' ).show();
			}
		},

		async onMediaLibraryButtonClick( e ) {
			e.preventDefault();

			await this.selectAndReturnAttachment().then( ( attachment ) => {
				// When an attachment is selected, update the preview and the hidden fields.
				this.set( 'mediaLibraryPreviewUrl', attachment.attributes.url );
				this.updateTypeAndValue( 'media_library', attachment.id );
			} );
		},

		selectAndReturnAttachment() {
			return new Promise( ( resolve ) => {
				acf.newMediaPopup( {
					mode: 'select',
					type: 'image',
					title: acf.__( 'Select Image' ),
					field: this.get( 'key' ),
					multiple: false,
					library: 'all',
					allowedTypes: 'image',
					select: resolve,
				} );
			} );
		},

		alignUrlTabToCurrentValue( typeAndValue ) {
			if ( typeAndValue.type !== 'url' ) {
				this.$( '.acf-icon_url' ).val( '' );
			}
		},

		onUrlChange( event ) {
			const currentValue = event.target.value;
			this.updateTypeAndValue( 'url', currentValue );
		},
	} );

	acf.registerFieldType( Field );
} )( jQuery );
