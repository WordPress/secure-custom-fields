<?php

/**
 * Functions for registering and managing ACF block patterns.
 *
 * @package SecureCustomFields
 */
function scf_register_block_pattern( $pattern_name, $pattern_properties, $extra_text = '' ) {
	register_block_pattern( $pattern_name, $pattern_properties );
}
