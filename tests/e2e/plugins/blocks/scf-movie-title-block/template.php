<?php
/**
 * Template for the SCF Movie Title Block.
 *
 * @package scf-test-plugins
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$movie_title = get_field( 'movie_title' );

?>
<p id="scf-test-block-movie-title">Movie title: <?php echo esc_html( $movie_title ); ?></p>