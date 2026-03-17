<?php
/**
 * Template Part: Post Sidebar External Links
 *
 * @param array $args['post_links'] ACF repeater rows, each with a 'link' sub-field (array).
 */

$post_links = $args['post_links'] ?? [];
if ( ! $post_links ) {
	return;
}
?>

<div class="post-layout__links-group">
	<span class="post-layout__meta-label text-monospace text-s">Links</span>
	<ul class="post-layout__links list-unstyled">
		<?php foreach ( $post_links as $row ) :
			$link   = $row['link'] ?? [];
			if ( empty( $link['url'] ) ) {
				continue;
			}
			$target = ! empty( $link['target'] ) ? $link['target'] : '';
		?>
			<li>
				<a href="<?php echo esc_url( $link['url'] ); ?>"
					<?php if ( $target ) : ?>target="<?php echo esc_attr( $target ); ?>" rel="noopener noreferrer"<?php endif; ?>>
					<?php echo esc_html( $link['title'] ?: $link['url'] ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
