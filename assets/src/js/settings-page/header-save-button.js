/**
 * Connects the Save button in SCF's PHP-rendered header bar to the settings app.
 *
 * @since SCF 6.9.10
 */

import { useEffect, useRef } from '@wordpress/element';

export const SAVE_BUTTON_ID = 'acf-settings-save';

/**
 * Keeps the header Save button's visibility, disabled and busy state in sync, and calls onClick when pressed.
 *
 * @param {Object}   options          Options.
 * @param {boolean}  options.visible  Whether the button is shown.
 * @param {boolean}  options.disabled Whether the button is disabled.
 * @param {boolean}  options.busy     Whether a save is in progress.
 * @param {string}   options.label    The button label.
 * @param {Function} options.onClick  Called when the button is pressed.
 */
export function useHeaderSaveButton( {
	visible,
	disabled,
	busy,
	label,
	onClick,
} ) {
	const onClickRef = useRef( onClick );
	onClickRef.current = onClick;

	useEffect( () => {
		const button = document.getElementById( SAVE_BUTTON_ID );
		if ( ! button ) {
			return;
		}

		const handleClick = ( event ) => {
			event.preventDefault();
			onClickRef.current?.();
		};

		button.addEventListener( 'click', handleClick );
		return () => button.removeEventListener( 'click', handleClick );
	}, [] );

	useEffect( () => {
		const button = document.getElementById( SAVE_BUTTON_ID );
		if ( ! button ) {
			return;
		}

		button.hidden = ! visible;
		button.disabled = !! disabled;
		button.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		if ( label ) {
			button.textContent = label;
		}
	}, [ visible, disabled, busy, label ] );
}
