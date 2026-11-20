/**
 * Settings page helpers.
 *
 * @since SCF 6.9.10
 */

/**
 * Returns the sections of a tab's data, or an empty array.
 *
 * @param {Object} tabData The tab data from the server.
 * @return {Array} The sections.
 */
export function getSections( tabData ) {
	return Array.isArray( tabData?.sections ) ? tabData.sections : [];
}

/**
 * Returns the initial value of every field, keyed by setting.
 *
 * @param {Array} sections The tab's sections.
 * @return {Object} Values keyed by setting.
 */
export function getInitialSettings( sections ) {
	const settings = {};
	sections.forEach( ( section ) =>
		section.fields.forEach( ( field ) => {
			settings[ field.key ] = field.value ?? null;
		} )
	);
	return settings;
}

/**
 * Reads a toggle value the way wp_validate_boolean() does, which is how the page reads toggle values on load.
 * A sanitize callback other than wp_validate_boolean() can return e.g. "0", which Boolean() would treat as true.
 *
 * @param {*} value The value from the server.
 * @return {boolean} The boolean value.
 */
export function toBoolean( value ) {
	if ( typeof value === 'string' ) {
		return value !== '' && value !== '0' && value.toLowerCase() !== 'false';
	}
	return Boolean( value );
}

/**
 * Whether two setting values differ.
 *
 * @param {*} a A value.
 * @param {*} b Another value.
 * @return {boolean} True if they differ.
 */
export function isDifferent( a, b ) {
	return JSON.stringify( a ) !== JSON.stringify( b );
}

/**
 * Whether a field can be changed in the UI.
 *
 * @param {Object} field The field.
 * @return {boolean} True if it can be edited.
 */
export function isEditable( field ) {
	return ! field.managed && ! field.disabled;
}

/**
 * Returns the editable settings that differ from their saved values.
 *
 * Disabled and managed fields are never sent, so their stored values can't be overwritten.
 *
 * @param {Array}  sections The tab's sections.
 * @param {Object} values   The current values.
 * @param {Object} saved    The saved values.
 * @return {Object} Changed values keyed by setting.
 */
export function getChangedSettings( sections, values, saved ) {
	const changed = {};
	sections.forEach( ( section ) =>
		section.fields.forEach( ( field ) => {
			if (
				isEditable( field ) &&
				isDifferent( values[ field.key ], saved[ field.key ] )
			) {
				changed[ field.key ] = values[ field.key ];
			}
		} )
	);
	return changed;
}

/**
 * Sends a request to admin-ajax.php and returns the parsed JSON payload, or null if it isn't JSON.
 *
 * @param {string} ajaxUrl The admin-ajax.php URL.
 * @param {Object} params  The request parameters.
 * @return {Promise<{response: Response, payload: Object|null}>} The response and payload.
 */
export async function postAjax( ajaxUrl, params ) {
	const response = await window.fetch( ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		body: new window.URLSearchParams( params ),
	} );
	const payload = await response.json().catch( () => null );
	return { response, payload };
}

/**
 * Saves settings with the settings page's AJAX action.
 *
 * Resolves with the server's response data: the saved values in 'settings', and any settings in 'skipped'
 * or 'failed'. Rejects with an Error carrying the server's message when the request fails.
 *
 * @param {Object} config   The settings page config.
 * @param {Object} settings The settings to save.
 * @return {Promise<Object>} The response data.
 */
export async function saveSettings( config, settings ) {
	const { strings = {} } = config;
	let result;

	try {
		result = await postAjax( config.ajaxUrl, {
			action: config.action,
			nonce: config.nonce,
			tab: config.activeTab,
			acfSettings: JSON.stringify( settings ),
		} );
	} catch {
		throw new Error( strings.saveErrorMessage );
	}

	const { response, payload } = result;

	if ( ! response.ok || ! payload?.success ) {
		throw new Error(
			typeof payload?.data === 'string' && payload.data
				? payload.data
				: strings.saveErrorMessage
		);
	}

	return payload.data && typeof payload.data === 'object' ? payload.data : {};
}

/**
 * Applies the values the server confirmed it saved.
 *
 * Only settings the server confirmed are saved. Anything skipped, or edited while the request was pending,
 * stays unsaved, and a newer edit isn't overwritten by the older saved value.
 *
 * @param {Array}  sections  The tab's sections.
 * @param {Object} current   The current values.
 * @param {Object} sent      The values that were sent.
 * @param {Object} confirmed The values the server sent back.
 * @return {{values: Object, saved: Object}} The confirmed values, and the current values with them applied.
 */
export function applyConfirmedSettings( sections, current, sent, confirmed ) {
	const types = {};
	sections.forEach( ( section ) =>
		section.fields.forEach( ( field ) => {
			types[ field.key ] = field.type;
		} )
	);

	const saved = {};
	Object.entries( confirmed || {} ).forEach( ( [ key, value ] ) => {
		saved[ key ] = types[ key ] === 'toggle' ? toBoolean( value ) : value;
	} );

	const values = { ...current };
	Object.entries( saved ).forEach( ( [ key, value ] ) => {
		if ( ! isDifferent( current[ key ], sent[ key ] ) ) {
			values[ key ] = value;
		}
	} );

	return { values, saved };
}
