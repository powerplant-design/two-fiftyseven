<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
	<div class="container mx-auto px-4">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-logo font-bold text-xl">
			<?php bloginfo( 'name' ); ?>
		</a>
		<nav class="site-nav">
			<?php
			wp_nav_menu( [
				'theme_location' => 'primary',
				'menu_class'     => 'nav-menu flex gap-4',
				'fallback_cb'    => false,
			] );
			?>
		</nav>
	</div>
</header>

<main class="site-main">
