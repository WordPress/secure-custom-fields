/**
 * WordPress post locking utilities for ACF blocks
 * Handles locking/unlocking post saving during block operations
 */

/**
 * Locks post saving in the WordPress editor for a specific block
 * Used when block operations are in progress for a specific block instance
 *
 * @param {string} clientId - The block's client ID
 */
export const lockPostSaving = ( clientId ) => {
	const dispatch = wp.data.dispatch( 'core/editor' );
	if ( dispatch ) {
		dispatch.lockPostSaving( 'acf/block/' + clientId );
	}
};

/**
 * Unlocks post saving in the WordPress editor for a specific block
 * Called when block operations are complete for a specific block instance
 *
 * @param {string} clientId - The block's client ID
 */
export const unlockPostSaving = ( clientId ) => {
	const dispatch = wp.data.dispatch( 'core/editor' );
	if ( dispatch ) {
		dispatch.unlockPostSaving( 'acf/block/' + clientId );
	}
};

/**
 * Checks if post saving is currently locked for a specific block
 *
 * @param {string} clientId - The block's client ID
 * @returns {boolean} - True if post saving is locked for this block
 */
export const isPostSavingLocked = ( clientId ) => {
	const dispatch = wp.data.dispatch( 'core/editor' );
	if ( ! dispatch ) {
		return false;
	}
	return wp.data.select( 'core/editor' ).isPostSavingLocked( `acf/block/${ clientId }` );
};

/**
 * Locks post saving with a custom lock name
 * Used for global operations that aren't tied to a specific block
 *
 * @param {string} lockName - The name of the lock
 */
export const lockPostSavingByName = ( lockName ) => {
	const dispatch = wp.data.dispatch( 'core/editor' );
	if ( dispatch ) {
		dispatch.lockPostSaving( 'acf/block/' + lockName );
	}
};

/**
 * Unlocks post saving with a custom lock name
 * Used for global operations that aren't tied to a specific block
 *
 * @param {string} lockName - The name of the lock
 */
export const unlockPostSavingByName = ( lockName ) => {
	const dispatch = wp.data.dispatch( 'core/editor' );
	if ( dispatch ) {
		dispatch.unlockPostSaving( 'acf/block/' + lockName );
	}
};

/**
 * Sorts an object's keys alphabetically and returns a new object
 * Used for consistent object serialization and comparison
 * Ensures that objects with same properties in different order produce same hash
 *
 * @param {Object} obj - Object to sort
 * @returns {Object} - New object with sorted keys in alphabetical order
 */
export const sortObjectKeys = ( obj ) => {
	return Object.keys( obj )
		.sort()
		.reduce( ( sortedObj, key ) => {
			sortedObj[ key ] = obj[ key ];
			return sortedObj;
		}, {} );
};
