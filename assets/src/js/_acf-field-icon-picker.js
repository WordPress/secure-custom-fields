( function ( $, undefined ) {
	const Field = acf.Field.extend( {
		type: 'icon_picker',

		wait: 'load',

		events: {
			showField: 'scrollToSelectedIcon',
			'input .acf-icon_url': 'onUrlChange',
			'click .acf-icon-picker-list-icon': 'onIconClick',
			'focus .acf-icon-picker-list-icon-radio': 'onIconRadioFocus',
			'blur .acf-icon-picker-list-icon-radio': 'onIconRadioBlur',
			'keydown .acf-icon-picker-list-icon-radio': 'onIconKeyDown',
			'input .acf-icon-list-search-input': 'onIconSearch',
			'keydown .acf-icon-list-search-input': 'onIconSearchKeyDown',
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

			// Any time any acf tab is clicked, we will re-scroll to the selected icon.
			$( '.acf-tab-button' ).on( 'click', () => {
				this.initializeIconLists( this.get( 'typeAndValue' ) );
			} );

			// Fire the action which lets people know the state has been updated.
			acf.doAction(
				this.get( 'name' ) + '/type_and_value_change',
				typeAndValue
			);

			this.initializeIconLists( typeAndValue );
			this.alignMediaLibraryTabToCurrentValue( typeAndValue );
		},

		addActions() {
			// Set up an action listener for when the type and value changes.
			acf.addAction(
				this.get( 'name' ) + '/type_and_value_change',
				( newTypeAndValue ) => {
					// Align the visual state of each tab to the current value.
					this.alignIconListTabsToCurrentValue( newTypeAndValue );
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

		scrollToSelectedIcon() {
			const innerElement = this.$selectedIcon();

			// If no icon is selected, do nothing.
			if ( innerElement.length === 0 ) {
				return;
			}

			const scrollingDiv = innerElement.closest( '.acf-icon-list' );
			scrollingDiv.scrollTop( 0 );

			const distance = innerElement.position().top - 50;

			if ( distance === 0 ) {
				return;
			}

			scrollingDiv.scrollTop( distance );
		},

		initializeIconLists( typeAndValue ) {
			const self = this;

			this.$( '.acf-icon-list' ).each( function( i ) {
				const tabName = $( this ).data( 'parent-tab' );
				const icons = self.getIconsList( tabName ) || [];
				self.set( tabName, icons );
				self.renderIconList( $( this ) );

				if ( typeAndValue.type === tabName ) {
					// Select the correct icon.
					self.selectIcon( $( this ), typeAndValue.value, false ).then( () => {
						// Scroll to the selected icon.
						self.scrollToSelectedIcon();
					} );
				}
			} );
		},

		alignIconListTabsToCurrentValue( typeAndValue ) {
			const icons = this.$( '.acf-icon-list' ).filter(
				function () {
					return (
						$( this ).data( 'parent-tab' ) !== typeAndValue.type
					);
				}
			);
			const self = this;
			icons.each( function () {
				self.unselectIcon( $( this ) );
			} );
		},

		renderIconHTML( tabName, icon ) {
			const id = `${ this.get( 'name' ) }-${ icon.key }`;

			let style = '';
			if ( 'dashicons' !== tabName ) {
				style = `background: center / contain url( ${ acf.strEscape(
					icon.url
				) } ) no-repeat;`;
			}

			return `<div class="${ tabName } ${ acf.strEscape(
				icon.key
			) } acf-icon-picker-list-icon" role="radio" data-icon="${ acf.strEscape(
				icon.key
			) }" style="${ style }" title="${ acf.strEscape(
				icon.label
			) }">
				<label for="${ acf.strEscape( id ) }">${ acf.strEscape(
					icon.label
				) }</label>
				<input id="${ acf.strEscape(
					id
				) }" type="radio" class="acf-icon-picker-list-icon-radio" name="acf-icon-picker-list-icon-radio" value="${ acf.strEscape(
					icon.key
				) }">
			</div>`;
		},

		renderIconList( $el ) {
			const tabName = $el.data( 'parent-tab' );
			const icons = this.get( tabName );

			$el.empty();
			if ( icons ) {
				icons.forEach( ( icon ) => {
					const iconHTML = this.renderIconHTML( tabName, icon );
					$el.append( iconHTML );
				} );
			}
		},

		getIconsList( tabName ) {
			if ( 'dashicons' === tabName ) {
				const iconPickeri10n = acf.get( 'iconPickeri10n' ) || [];

				return Object.entries( iconPickeri10n ).map(
					( [ key, value ] ) => {
						return {
							key,
							label: value,
						};
					}
				);
			}

			return acf.get( `iconPickerIcons_${ tabName }` );
		},

		getIconsBySearch( searchTerm, tabName ) {
			const lowercaseSearchTerm = searchTerm.toLowerCase();
			const icons = this.getIconsList( tabName);

			const filteredIcons = icons.filter( function ( icon ) {
				const lowercaseIconLabel = icon.label.toLowerCase();
				return lowercaseIconLabel.indexOf( lowercaseSearchTerm ) > -1;
			} );

			return filteredIcons;
		},

		selectIcon( $el, icon, setFocus = true ) {
			this.set( 'selectedIcon', icon );

			// Select the new one.
			const $newIcon = $el.find(
				'.acf-icon-picker-list-icon[data-icon="' + icon + '"]'
			);
			$newIcon.addClass( 'active' );

			const $input = $newIcon.find( 'input' );
			const thePromise = $input.prop( 'checked', true ).promise();

			if ( setFocus ) {
				$input.trigger( 'focus' );
			}

			this.updateTypeAndValue( $el.data( 'parent-tab' ), icon );

			return thePromise;
		},

		unselectIcon( $el ) {
			// Remove the currently active dashicon, if any.
			$el
				.find( '.acf-icon-picker-list-icon' )
				.removeClass( 'active' );
			this.set( 'selectedIcon', false );
		},

		onIconRadioFocus( e ) {
			const icon = e.target.value;
			const $tabs = this.$( e.target ).closest(
				'.acf-icon-picker-tabs'
			);
			const $iconsList = $tabs.find( '.acf-icon-list' );

			const $newIcon = $iconsList.find(
				'.acf-icon-picker-list-icon[data-icon="' + icon + '"]'
			);
			$newIcon.addClass( 'focus' );

			// If this is a different icon than previously selected, select it.
			if ( this.get( 'selectedIcon' ) !== icon ) {
				this.unselectIcon( $iconsList );
				this.selectIcon( $iconsList, icon );
			}
		},

		onIconRadioBlur( e ) {
			const icon = this.$( e.target );
			const iconParent = icon.parent();

			iconParent.removeClass( 'focus' );
		},

		onIconClick( e ) {
			e.preventDefault();
			const $iconList = this.$( e.target ).closest(
				'.acf-icon-list'
			);
			const $iconElement = this.$( e.target );
			const icon = $iconElement.find( 'input' ).val();

			const $newIconElement = $iconList.find(
				'.acf-icon-picker-list-icon[data-icon="' + icon + '"]'
			);

			// By forcing focus on the input, we fire onIconRadioFocus.
			$newIconElement.find( 'input' ).prop( 'checked', true ).trigger( 'focus' );
		},

		onIconSearch( e ) {
			const $tabs = this.$( e.target ).closest(
				'.acf-icon-picker-tabs'
			);
			const $iconList = $tabs.find( '.acf-icon-list' );
			const tabName = $tabs.data( 'tab' );
			const searchTerm = e.target.value;
			const filteredIcons = this.getIconsBySearch( searchTerm, tabName );

			if ( filteredIcons.length > 0 || ! searchTerm ) {
				this.set( tabName, filteredIcons );
				$tabs.find( '.acf-icon-list-empty' ).hide();
				$tabs.find( '.acf-icon-list ' ).show();
				this.renderIconList( $iconList );

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

				$tabs.find( '.acf-icon-list ' ).hide();
				$tabs.find( '.acf-icon-list-empty' )
					.find( '.acf-invalid-icon-list-search-term' )
					.text( visualSearchTerm );
				$tabs.find( '.acf-icon-list-empty' ).css( 'display', 'flex' );
				$tabs.find( '.acf-icon-list-empty' ).show();

				// Announce change of data to screen readers.
				wp.a11y.speak(
					acf.get( 'iconPickerA11yStrings' ).noResultsForSearchTerm,
					'polite'
				);
			}
		},

		onIconSearchKeyDown( e ) {
			// Check if the pressed key is Enter (key code 13)
			if ( e.which === 13 ) {
				// Prevent submitting the entire form if someone presses enter after searching.
				e.preventDefault();
			}
		},

		onIconKeyDown( e ) {
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
