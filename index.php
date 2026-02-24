<?php get_header(); ?>

<div class="container mx-auto px-4 py-8">
	<?php if ( have_posts() ) : ?>

		<?php while ( have_posts() ) : the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'mb-12' ); ?>>
				<h2 class="text-2xl font-bold mb-2">
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</h2>
				<div class="entry-meta text-sm text-gray-500 mb-4">
					<time datetime="<?php the_date( 'c' ); ?>"><?php the_date(); ?></time>
				</div>
				<div class="entry-content prose">
					<?php the_excerpt(); ?>
				</div>
				<a href="<?php the_permalink(); ?>" class="read-more mt-4 inline-block">
					<?php esc_html_e( 'Read more', 'two-fiftyseven' ); ?>
				</a>
			</article>
		<?php endwhile; ?>

		<?php the_posts_navigation(); ?>

	<?php else : ?>
		<p><?php esc_html_e( 'No posts found.', 'two-fiftyseven' ); ?></p>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
