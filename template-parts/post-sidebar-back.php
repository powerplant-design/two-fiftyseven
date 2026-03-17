<?php
/**
 * Template Part: Post Sidebar Back Link
 *
 * @param string $args['back_href']  URL of the archive or index page.
 * @param string $args['back_label'] Visible link text.
 */

$back_href  = $args['back_href'] ?? '';
$back_label = $args['back_label'] ?? __( 'Back', 'two-fiftyseven' );

if ( ! $back_href ) {
	return;
}
?>

<div class="post-layout__back">
	<p>
		<a class="btn" data-type="text" href="<?php echo esc_url( $back_href ); ?>">
			&larr; <?php echo esc_html( $back_label ); ?>
		</a>
	</p>
</div>
