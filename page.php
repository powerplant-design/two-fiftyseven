<?php get_header(); ?>

<div class="container mx-auto px-4 py-8">
	<?php while ( have_posts() ) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<h1 class="text-4xl font-bold mb-6"><?php the_title(); ?></h1>
			<div class="entry-content prose max-w-none">
				<?php the_content(); ?>
			</div>
		</article>
	<?php endwhile; ?>
</div>

<?php get_footer(); ?>
