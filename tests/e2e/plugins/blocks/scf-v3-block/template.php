<?php
/**
 * V3 Block Template
 *
 * @package scf-test-plugins
 *
 * @var array $block      The block settings and attributes.
 * @var string $content   The block inner HTML (empty).
 * @var bool $is_preview  True during backend preview render.
 * @var int $post_id      The post ID the block is rendering content against.
 * @var array $context    The context provided to the block by the post or its parent block.
 */

// Get field values.
$block_title = get_field( 'title' );
$description = get_field( 'description' );
$show_badge  = get_field( 'show_badge' );

// Generate block ID for testing.
$block_id = 'v3-block-' . ( $block['id'] ?? uniqid() );
?>
<div id="<?php echo esc_attr( $block_id ); ?>" class="v3-block">
	<?php if ( $block_title ) : ?>
		<h3 class="v3-block__title"><?php echo esc_html( $block_title ); ?></h3>
	<?php else : ?>
		<h3 class="v3-block__title v3-block__title--placeholder">Enter a title...</h3>
	<?php endif; ?>

	<?php if ( $description ) : ?>
		<p class="v3-block__description"><?php echo esc_html( $description ); ?></p>
	<?php endif; ?>

	<?php if ( $show_badge ) : ?>
		<span class="v3-block__badge">Featured</span>
	<?php endif; ?>
</div>
