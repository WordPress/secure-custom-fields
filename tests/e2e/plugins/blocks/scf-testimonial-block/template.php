<?php
/**
 * Testimonial Block template.
 *
 * @package scf-test-plugins
 *
 * @param array  $block      The block settings and attributes.
 * @param string $content    The block inner HTML (empty).
 * @param bool   $is_preview True during backend preview render.
 * @param int    $post_id    The post ID the block is rendering content against.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load values and assign defaults.
$quote       = get_field( 'quote' );
$author      = get_field( 'author' );
$author_role = get_field( 'role' );
// Build quote attribution.
$quote_attribution = '';
if ( $author ) {
	$quote_attribution .= '<footer class="testimonial__attribution">';
	$quote_attribution .= '<cite class="testimonial__author">' . esc_html( $author ) . '</cite>';

	if ( $author_role ) {
		$quote_attribution .= '<span class="testimonial__role">' . esc_html( $author_role ) . '</span>';
	}

	$quote_attribution .= '</footer><!-- .testimonial__attribution -->';
}

// Add a unique block ID for editor identification.
$block_id = 'testimonial-' . ( isset( $block['id'] ) ? (string) $block['id'] : uniqid() );
?>

<div class="testimonial" id="<?php echo esc_attr( $block_id ); ?>">
	<div class="testimonial__col">
		<blockquote class="testimonial__blockquote">
			<?php echo esc_html( $quote ); ?>

			<?php if ( ! empty( $quote_attribution ) ) : ?>
				<?php echo wp_kses_post( $quote_attribution ); ?>
			<?php endif; ?>
		</blockquote>
	</div>

</div>