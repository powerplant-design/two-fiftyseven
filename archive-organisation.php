<?php
/**
 * Organisation Archive Template
 *
 * Colour space is set via ACF Options → Archive Settings → Organisation Archive
 * Colour Space, read automatically by two_fiftyseven_get_colour_space().
 */

get_header();
?>

<div class="page-layout">

	<header class="post-index-header text-center">
		<h1 class="post-index-header__title"><?php post_type_archive_title(); ?></h1>
	</header>

	<hr>

	<?php get_template_part( 'template-parts/archive-loop' ); ?>

</div>

<?php get_footer(); ?>
