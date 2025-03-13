<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package wordpress/secure-custom-fields
 */

// Load Composer dependencies.
require_once dirname( dirname( __DIR__ ) ) . '/vendor/autoload.php';

// Initialize WordBless.
\WorDBless\Load::load();

// Load our plugin.
require dirname( dirname( __DIR__ ) ) . '/secure-custom-fields.php';

// Load the ACF_Form_Post class.
require dirname( dirname( __DIR__ ) ) . '/includes/forms/form-post.php';
