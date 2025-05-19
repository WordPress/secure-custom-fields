<?php
/**
 * Admin Beta Features View
 *
 * @package    Secure Custom Fields
 * @since      SCF 6.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$beta_features = acf()->admin_beta_features->get_beta_features();
?>
<div class="wrap" id="scf-admin-beta-features">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Beta Features', 'secure-custom-fields' ); ?></h1>
	<hr class="wp-header-end">

	<div class="scf-beta-features-list">
		<div class="scf-beta-features-header">
			<p><?php esc_html_e( 'Enable or disable beta features. These features are in development and may change in future releases.', 'secure-custom-fields' ); ?></p>
		</div>
		
		<?php if ( empty( $beta_features ) ) : ?>
			<div class="scf-no-beta-features">
				<p><?php esc_html_e( 'No beta features are currently available.', 'secure-custom-fields' ); ?></p>
			</div>
		<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'edit.php?post_type=acf-field-group&page=scf-beta-features' ) ); ?>">
			<?php wp_nonce_field( 'scf_beta_features_update', 'scf_beta_features_nonce' ); ?>
			<table class="widefat scf-beta-features-table">
				<thead>
					<tr>
						<th class="scf-beta-feature-status"><?php esc_html_e( 'Enabled', 'secure-custom-fields' ); ?></th>
						<th class="scf-beta-feature-info"><?php esc_html_e( 'Beta Feature', 'secure-custom-fields' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $beta_features as $beta_feature ) : ?>
						<tr>
							<td class="scf-beta-feature-status">
								<input type="checkbox" 
									id="scf_beta_feature_<?php echo esc_attr( $beta_feature->name ); ?>" 
									name="scf_beta_features[<?php echo esc_attr( $beta_feature->name ); ?>]" 
									value="1"
									<?php checked( $beta_feature->is_enabled() ); ?>
								/>
							</td>
							<td class="scf-beta-feature-info">
								<label for="scf_beta_feature_<?php echo esc_attr( $beta_feature->name ); ?>">
									<strong><?php echo esc_html( $beta_feature->title ); ?></strong>
								</label>
								<p class="description"><?php echo esc_html( $beta_feature->description ); ?></p>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="submit">
				<input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 'secure-custom-fields' ); ?>" />
			</p>
		</form>
		<?php endif; ?>
	</div>
</div>

<style>
.scf-beta-features-list {
	max-width: 800px;
	margin-top: 20px;
}
.scf-beta-features-header {
	margin-bottom: 20px;
}
.scf-beta-features-table {
	border-spacing: 0;
	width: 100%;
	clear: both;
	margin: 0;
}
.scf-beta-features-table th {
	padding: 8px 10px;
}
.scf-beta-features-table td {
	padding: 15px 10px;
	vertical-align: top;
}
.scf-beta-feature-status {
	width: 60px;
	text-align: center;
}
.scf-beta-feature-info label {
	font-size: 14px;
	line-height: 1.3;
}
.scf-beta-feature-info .description {
	margin: 4px 0 0;
	color: #646970;
}
.scf-no-beta-features {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	margin-top: 20px;
	text-align: center;
}
</style> 