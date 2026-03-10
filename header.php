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

<?php
// Resolve the current post ID robustly: queried object for singular/front-page,
// falling back to the static front page option if is_front_page() without a query object.
$current_post_id = get_queried_object_id();
if ( ! $current_post_id && is_front_page() ) {
	$current_post_id = (int) get_option( 'page_on_front' );
}
$has_hero = $current_post_id && has_block( 'acf/hero-home', $current_post_id );
?>

<header class="site-header<?php echo $has_hero ? '' : ' site-header--no-hero'; ?>">
	<div class="wrapper repel | w-full">
		<nav class="site-nav">
			<?php
			wp_nav_menu( [
				'theme_location' => 'primary',
				'menu_class'     => 'nav-menu cluster',
				'fallback_cb'    => false,
			] );
			?>
		</nav>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-logo" aria-label="<?php bloginfo( 'name' ); ?>">
			<?php
			$logo = get_template_directory() . '/assets/images/logo-257.svg';
			if ( file_exists( $logo ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo file_get_contents( $logo );
			} else {
				bloginfo( 'name' );
			}
			?>
		</a>
		<button
			class="btn color-mode-toggle"
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
