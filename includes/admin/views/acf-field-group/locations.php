<?php

// global
global $field_group;

?>
<div class="acf-field">
	<div class="acf-label">
		<label><?php esc_html_e( 'Rules', 'secure-custom-fields' ); ?></label>
		<i tabindex="0" class="acf-icon acf-icon-help acf-js-tooltip" title="<?php esc_attr_e( 'Create a set of rules to determine which edit screens will use these custom fields', 'secure-custom-fields' ); ?>">?</i>
	</div>
	<div class="acf-input">
		<div class="rule-groups">

			<?php
			foreach ( $field_group['location'] as $i => $group ) :

				// bail early if no group
				if ( empty( $group ) ) {
					return;
				}


				// view
				acf_get_view(
					'acf-field-group/location-group',
					array(
						'group'    => $group,
						'group_id' => "group_{$i}",
					)
				);
			endforeach;
			?>

			<h4><?php esc_html_e( 'or', 'secure-custom-fields' ); ?></h4>

			<a href="#" class="button add-location-group"><?php esc_html_e( 'Add rule group', 'secure-custom-fields' ); ?></a>

		</div>
	</div>
</div>
<script type="text/javascript">
if( typeof acf !== 'undefined' ) {

	acf.newPostbox({
		'id': 'acf-field-group-locations',
		'label': 'left'
	});

}
</script>
