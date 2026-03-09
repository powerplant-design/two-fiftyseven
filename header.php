<!DOCTYPE html>
<html <?php language_attributes(); ?> data-color-space="<?php echo esc_attr( two_fiftyseven_get_colour_space() ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<script>/* no-FOUC: set data-theme before CSS paints */(function(){var e=document.documentElement,s=e.getAttribute('data-color-space')||'neutral',stored=localStorage.getItem('color-mode'),d=stored?stored==='dark':window.matchMedia('(prefers-color-scheme:dark)').matches;e.setAttribute('data-theme',s+(d?'-dark':'-light'));})();</script>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
	<div class="wrapper repel">
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
		<button
			class="color-mode-toggle"
			data-js="color-mode-toggle"
			aria-label="<?php esc_attr_e( 'Toggle light/dark mode', 'two-fiftyseven' ); ?>"
			aria-pressed="false"
			type="button"
		>
			<span data-mode-label></span>
		</button>
	</div>
</header>

<main class="site-main">
	<div id="swup">
