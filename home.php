<?php
/**
 * Post Index Template (home.php)
 *
 * WordPress uses this template when a static Posts Page is assigned in
 * Settings → Reading. The assigned page provides the title and URL slug.
 *
 * To activate:
 *   1. Create a Page titled "Kōrero" in WP Admin (slug: korero or kōrero).
 *   2. Go to Settings → Reading → set "Posts page" to that page.
 *   3. WordPress will use this template at that page's URL.
 */

get_header();

// The title comes from the Page assigned as the posts index — not from a post.
$page_title = get_queried_object()?->post_title ?: __( 'Posts', 'two-fiftyseven' );
?>

<div class="page-layout">

    <?php /* ── Page title ─────────────────────────────────────────── */ ?>
	<header class="post-index-header text-center">
        <h1 class="post-index-header__title"><?php echo esc_html( $page_title ); ?></h1>
	</header>
    
    <hr>

	<?php if ( have_posts() ) : ?>

		<?php /* ── Post grid ───────────────────────────────────────── */ ?>
		<div class="post-index | grid" data-grid-layout="halves">
			<?php while ( have_posts() ) : the_post();
				$card_space = function_exists( 'get_field' ) ? ( get_field( 'colour_space', get_the_ID() ) ?: 'neutral' ) : 'neutral';
			?>

				<article class="post-index__item" data-color-space="<?php echo esc_attr( $card_space ); ?>">

					<?php if ( has_post_thumbnail() ) : ?>
						<div class="post-index__image | frame">
							<?php the_post_thumbnail( 'medium_large' ); ?>
						</div>
					<?php endif; ?>

					<div class="post-index__body | stack">
						<time class="post-index__date text-monospace text-s" datetime="<?php echo esc_attr( get_the_date( 'Y-m-d' ) ); ?>">
							<?php echo esc_html( get_the_date() ); ?>
						</time>
						<h2 class="post-index__title | text-2xl line-clamp-3">
							<a class="post-index__link" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h2>
						<p class="post-index__excerpt | line-clamp-3"><?php echo esc_html( get_the_excerpt() ); ?></p>
					</div>

				</article>

			<?php endwhile; ?>
		</div>

		<?php /* ── Pagination ──────────────────────────────────────── */ ?>
		<?php the_posts_pagination( [ 'mid_size' => 2 ] ); ?>

	<?php else : ?>
		<p class="post-index__empty"><?php esc_html_e( 'No posts yet.', 'two-fiftyseven' ); ?></p>
	<?php endif; ?>

</div>

<?php get_footer(); ?>
