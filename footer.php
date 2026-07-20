

</main>

<footer class="site-footer">
	<div class="wrapper">

		<div class="site-footer__top">
			<nav class="site-footer__nav" aria-label="Footer">
				<div class="site-footer__col">
					<h4>Find us</h4>
					<address class="site-footer__address">Level 2, 57 Willis Street + 70 Victoria Street, Te Whanganui-a-Tara</address>
					<p class="site-footer__note">Central lift access from both the Willis Street and Victoria Street entrances. The Victoria Street entrance is opposite Matapihi ki te Ao Nui.</p>
					<a class="site-footer__maplink" href="https://www.google.com/maps/search/?api=1&query=57+Willis+Street+Te+Whanganui-a-Tara" target="_blank" rel="noopener">View on map</a>
				</div>
				<div class="site-footer__col">
					<h4>Workspace</h4>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/base/' ) ); ?>">Base</a></li>
						<li><a href="<?php echo esc_url( home_url( '/hub/' ) ); ?>">Hub</a></li>
						<li><a href="<?php echo esc_url( home_url( '/desk/' ) ); ?>">Desk</a></li>
					</ul>
				</div>
				<div class="site-footer__col">
					<h4>Meet &amp; Host</h4>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/meetings/' ) ); ?>">Meetings</a></li>
						<li><a href="<?php echo esc_url( home_url( '/host-events/' ) ); ?>">Events</a></li>
					</ul>
				</div>
				<div class="site-footer__col">
					<h4>About</h4>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/korero/' ) ); ?>">Kōrero</a></li>
						<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a></li>
					</ul>
				</div>
			</nav>

			<div class="site-footer__signup">
				<h4>Pānui</h4>
				<p>Occasional notes from inside two/fiftyseven · what's on, what's changed, what we're thinking about.</p>
				<?php echo do_shortcode( '[mailpoet_form id="3"]' ); ?>
			</div>
		</div>

		<div class="site-footer__beliefs" aria-label="What we believe">
			<ul class="site-footer__beliefs-track">
				<li>diversity is resilience</li>
				<li>radical equality is good for all</li>
				<li>complete decolonisation is a necessary future</li>
				<li>climate justice is a human and environmental imperative</li>
				<li aria-hidden="true">diversity is resilience</li>
				<li aria-hidden="true">radical equality is good for all</li>
				<li aria-hidden="true">complete decolonisation is a necessary future</li>
				<li aria-hidden="true">climate justice is a human and environmental imperative</li>
			</ul>
		</div>

		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-footer__wordmark" aria-label="<?php bloginfo( 'name' ); ?>">
			<?php
			$logo = get_template_directory() . '/assets/images/logo-257.svg';
			if ( file_exists( $logo ) ) {
				echo file_get_contents( $logo );
			} else {
				bloginfo( 'name' );
			}
			?>
		</a>

		<div class="site-footer__bottom">
			<span>&copy; <?php echo esc_html( date( 'Y' ) ); ?> two/fiftyseven Limited</span>
			<span>Aotearoa / New Zealand</span>
		</div>

	</div>
</footer>
</div><!-- /#swup -->

<?php wp_footer(); ?>
</body>
</html>
