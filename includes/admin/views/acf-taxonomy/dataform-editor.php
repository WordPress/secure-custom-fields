<?php
/**
 * DataForm editor for taxonomies.
 *
 * @package Secure Custom Fields
 */

/*
 * The classic Basic Settings markup stays in the page so taxonomy key
 * slugification, the Post Types selector and third party fields keep working.
 * Only the fields rendered by DataForm are hidden, and only once the editor
 * script has mounted, so a script failure leaves the original fields usable.
 */

global $acf_taxonomy;

?>
<div id="scf-taxonomy-editor"></div>

<div id="scf-taxonomy-editor-fields">
	<style>
		#scf-taxonomy-editor-fields.scf-taxonomy-editor-active .acf-field[data-name="name"],
		#scf-taxonomy-editor-fields.scf-taxonomy-editor-active .acf-field[data-name="singular_name"],
		#scf-taxonomy-editor-fields.scf-taxonomy-editor-active .acf-field[data-name="taxonomy"],
		#scf-taxonomy-editor-fields.scf-taxonomy-editor-active .acf-field[data-name="public"],
		#scf-taxonomy-editor-fields.scf-taxonomy-editor-active .acf-field[data-name="hierarchical"] {
			display: none;
		}
	</style>
	<?php acf_get_view( 'acf-taxonomy/basic-settings' ); ?>
</div>
