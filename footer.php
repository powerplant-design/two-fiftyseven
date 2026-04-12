


</main>

<footer class="site-footer">
	<div class="wrapper">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-footer__logo" aria-label="<?php bloginfo( 'name' ); ?>">
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
		<div class="site-footer__links repel">
			<p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
			<a href="/privacy-policy/" >Privacy Policy</a>
			<a href="https://powerplant.design" target="_blank" rel="noopener">Site 💚 Power Plant</a>
		</div>
	</div>

</footer>
</div><!-- /#swup -->

<?php wp_footer(); ?> 
</body>
</html>
