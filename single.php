<?php
/**
 * Single Post Template
 *
 * Renders a single post with:
 *   - A hero zone: post title (h1) + optional featured image (toggle)
 *   - A two-column post layout:
 *       Left  (2/5) — sticky sidebar: site logo, posted date, author, external links
 *       Right (3/5) — scrolling rich post content
 *
 * ACF fields (group_two57_post_options):
 *   show_featured_image — true_false; shows/hides the featured image in the hero
 *   post_links          — repeater of link fields ({ url, title, target })
 */

get_header();

while ( have_posts() ) :
	the_post();

	// ACF fields.
	// show_featured_image defaults to true (1) in ACF; treat null (never saved) as true too.
	$show_featured_image = get_field( 'show_featured_image' ) !== false;
	$image_orientation   = get_field( 'image_orientation' ) ?: 'landscape';
	$post_links          = get_field( 'post_links' ) ?: [];

	// Site logo — inline SVG so fill inherits currentColor from colour-space token.
	$logo_path = get_template_directory() . '/assets/images/logo-257.svg';
	$logo_svg  = file_exists( $logo_path ) ? file_get_contents( $logo_path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents — static local file

	$has_thumb   = $show_featured_image && has_post_thumbnail();
	$title_class = mb_strlen( get_the_title() ) < 28 ? 'post-hero__title' : 'post-hero__title text-3xl';
?>

<div class="page-layout">

	<?php /* ── Hero ─────────────────────────────────────────────────── */ ?>
	<section class="post-hero<?php echo $has_thumb ? ' post-hero--with-image' : ''; ?>">
		<h1 class="<?php echo esc_attr( $title_class ); ?>"><?php the_title(); ?></h1>
		<?php if ( $has_thumb ) : ?>
			<div class="post-hero__image post-hero__image--<?php echo esc_attr( $image_orientation ); ?>">
				<?php the_post_thumbnail( 'large' ); ?>
			</div>
		<?php endif; ?>
	</section>

	<?php /* ── Post Layout ──────────────────────────────────────────── */ ?>
	<div class="post-layout">

		<aside class="post-layout__sidebar">

			<!-- <?php if ( $logo_svg ) : ?>
				<span class="post-layout__logo" aria-hidden="true">
					<?php echo $logo_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — static theme file ?>
				</span>
			<?php endif; ?> -->

			<div class="post-layout__meta | stack">
				<p>
					<span class="post-layout__meta-label text-monospace text-s">Posted</span><br>
					<time datetime="<?php echo esc_attr( get_the_date( 'Y-m-d' ) ); ?>">
						<?php echo esc_html( get_the_date() ); ?>
					</time>
				</p>
				<?php if ( get_the_modified_date( 'U' ) > get_the_date( 'U' ) ) : ?>
				<p>
					<span class="post-layout__meta-label text-monospace text-s">Updated</span><br>
					<time datetime="<?php echo esc_attr( get_the_modified_date( 'Y-m-d' ) ); ?>">
						<?php echo esc_html( get_the_modified_date() ); ?>
					</time>
				</p>
				<?php endif; ?>
				<p>
					<span class="post-layout__meta-label text-monospace text-s">Author</span><br>
					<?php echo esc_html( get_the_author() ); ?>
				</p>
			</div>

			<?php if ( $post_links ) : ?>
				<ul class="post-layout__links list-unstyled">
					<?php foreach ( $post_links as $row ) :
						$link = $row['link'] ?? [];
						if ( empty( $link['url'] ) ) continue;
					?>
						<li>
							<a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $link['title'] ?: $link['url'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<div class="post-layout__back">
				<p>
					<a class="btn" data-type="text" href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ); ?>">
					&larr;  Kōrero
					</a>
				</p>
			</div>

		</aside>

		<div class="post-layout__content | prose stack">
			<?php the_content(); ?>

			<?php
			$prev_post = get_previous_post();
			$next_post = get_next_post();
			if ( $prev_post || $next_post ) : ?>
				<nav class="post-layout__adjacent | repel" aria-label="<?php esc_attr_e( 'Post navigation', 'two-fiftyseven' ); ?>">
					<div class="post-layout__adjacent-item post-layout__adjacent-item--next">
						<?php if ( $next_post ) : ?>
							<!-- <span class="post-layout__meta-label text-monospace text-s">Next</span><br> -->
							<a class="btn" data-type="text" href="<?php echo esc_url( get_permalink( $next_post ) ); ?>">
								&larr; <?php echo esc_html( get_the_title( $next_post ) ); ?>
							</a>
							<?php endif; ?>
						</div>
						<div class="post-layout__adjacent-item">
							<?php if ( $prev_post ) : ?>
								<!-- <span class="post-layout__meta-label text-monospace text-s"> Previous</span><br> -->
								<a class="btn" data-type="text" href="<?php echo esc_url( get_permalink( $prev_post ) ); ?>">
									<?php echo esc_html( get_the_title( $prev_post ) ); ?> &rarr;
								</a>
							<?php endif; ?>
						</div>
				</nav>
			<?php endif; ?>
		</div>

	</div><!-- /.post-layout -->

</div><!-- /.page-layout -->

<?php endwhile; ?>
<?php get_footer();
