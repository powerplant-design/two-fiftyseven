<?php get_header(); ?>

<div class="wrapper">
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(''); ?>>
			<h1 class="text-4xl font-bold mb-2"><?php the_title(); ?></h1>
			<div class="entry-meta text-sm text-gray-500 mb-8">
				<time datetime="<?php the_date( 'c' ); ?>"><?php the_date(); ?></time>
				<?php if ( get_the_author() ) : ?>
					&middot; <?php the_author(); ?>
				<?php endif; ?>
			</div>
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="featured-image mb-8">
					<?php the_post_thumbnail( 'large', [ 'class' => 'w-full h-auto rounded' ] ); ?>
				</div>
			<?php endif; ?>
			<div class="entry-content prose max-w-none">
				<?php the_content(); ?>
			</div>
		</article>

		<?php the_post_navigation(); ?>

		<?php if ( comments_open() || get_comments_number() ) : ?>
			<?php comments_template(); ?>
		<?php endif; ?>

	<?php endwhile; ?>
</div>

<?php get_footer(); ?>
