/**
 * WordPress post locking utilities for ACF blocks
 * Handles locking/unlocking post saving during block operations
 */

/**
 * Locks post saving in the WordPress editor
 * Used when block operations are in progress (like fetching data)
 *
 * @param {string} clientId - The block's client ID
 */
export const lockPostSaving = (clientId) => {
	if (wp.data.dispatch('core/editor')) {
		wp.data.dispatch('core/editor').lockPostSaving('acf/block/' + clientId);
	}
};

/**
 * Unlocks post saving in the WordPress editor
 * Called when block operations are complete
 *
 * @param {string} clientId - The block's client ID
 */
export const unlockPostSaving = (clientId) => {
	if (wp.data.dispatch('core/editor')) {
		wp.data
			.dispatch('core/editor')
			.unlockPostSaving('acf/block/' + clientId);
	}
};

/**
 * Sorts an object's keys alphabetically
 * Used for consistent object serialization and comparison
 *
 * @param {Object} obj - Object to sort
 * @returns {Object} - New object with sorted keys
 */
export const sortObjectKeys = (obj) =>
	Object.keys(obj)
		.sort()
		.reduce((result, key) => {
			result[key] = obj[key];
			return result;
		}, {});
