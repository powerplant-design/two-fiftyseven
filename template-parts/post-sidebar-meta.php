<?php
/**
 * Template Part: Post Sidebar Meta
 *
 * Shows posted date, updated date (only when modified > published), and author.
 * All meta is hidden for the 'person' and 'organisation' post types.
 */
?>

<?php if ( ! in_array( get_post_type(), [ 'person', 'organisation' ], true ) ) : ?>
<div class="post-layout__meta | stack">
	<p>
		<span class="post-layout__meta-label text-monospace text-s">Posted</span><br>
		<time datetime="<?php echo esc_attr( get_the_date( 'Y-m-d' ) ); ?>">
			<?php echo esc_html( get_the_date() ); ?>
		</time>
	</p>
	<?php if ( get_the_modified_date( 'U' ) > get_the_date( 'U' ) ) : ?>
	<p>
		<span class="post-layout__meta-label text-monospace text-s">Updated</span><br>
		<time datetime="<?php echo esc_attr( get_the_modified_date( 'Y-m-d' ) ); ?>">
			<?php echo esc_html( get_the_modified_date() ); ?>
		</time>
	</p>
	<?php endif; ?>
	<p>
		<span class="post-layout__meta-label text-monospace text-s">Author</span><br>
		<?php echo esc_html( get_the_author() ); ?>
	</p>
</div>
<?php endif; ?>
