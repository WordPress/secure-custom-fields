( function () {
	acf.unload = new acf.Model( {
		wait: 'load',
		active: true,
		changed: false,

		actions: {
			validation_failure: 'startListening',
			validation_success: 'stopListening',
		},

		events: {
			'change form .acf-field': 'startListening',
			'submit form': 'stopListening',
		},

		enable: function () {
			this.active = true;
		},

		disable: function () {
			this.active = false;
		},

		reset: function () {
			this.stopListening();
		},

		startListening: function () {
			// bail early if already changed, not active
			if ( this.changed || ! this.active ) {
				return;
			}

			// update
			this.changed = true;

			// add event
			window.addEventListener( 'beforeunload', this.onUnload );
		},

		stopListening: function () {
			// update
			this.changed = false;

			// remove event
			window.removeEventListener( 'beforeunload', this.onUnload );
		},

		onUnload: function ( e ) {
			var message = acf.__(
				'The changes you made will be lost if you navigate away from this page'
			);

			// Native beforeunload listeners must prevent default and set
			// returnValue for the browser to show the confirmation dialog.
			if ( e && typeof e.preventDefault === 'function' ) {
				e.preventDefault();
				e.returnValue = message;
			}

			return message;
		},
	} );
} )();
