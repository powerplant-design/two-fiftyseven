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
$options_heading = function_exists( 'get_field' ) ? get_field( 'posts_archive_heading', 'option' ) : '';
$page_title      = $options_heading ?: ( get_queried_object()?->post_title ?: __( 'Posts', 'two-fiftyseven' ) );
?>

<div class="page-layout">

    <?php /* ── Page title ─────────────────────────────────────────── */ ?>
	<header class="post-index-header text-center">
        <h1 class="post-index-header__title"><?php echo esc_html( $page_title ); ?></h1>
	</header>
    
    <hr>

	<?php /* ── Post grid ───────────────────────────────────────── */ ?>
	<?php get_template_part( 'template-parts/archive-loop' ); ?>

</div>

<?php get_footer(); ?>
