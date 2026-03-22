<?php
/**
 * Template Part: Archive Post Loop
 *
 * Shared card grid used by the posts index (home.php) and all CPT archive
 * templates. Renders the loop, grid wrapper, and pagination as a unit.
 */

if ( have_posts() ) : ?>

	<?php
		$post_type = get_post_type();
		$grid_layout = in_array( $post_type, [ 'person', 'organisation', 'media_item' ], true ) ? 'halves' : 'thirds';
	?>

	<div class="post-index | grid" data-grid-layout="<?php echo esc_attr( $grid_layout ); ?>">
		<?php while ( have_posts() ) : the_post();
			$card_space    = function_exists( 'get_field' ) ? ( get_field( 'colour_space', get_the_ID() ) ?: 'neutral' ) : 'neutral';
			$post_type     = get_post_type();
			$card_modifier = match ( $post_type ) {
				'person'       => ' post-index__item--person',
				'organisation' => ' post-index__item--organisation',
				'media_item'   => ' post-index__item--media-item',
				default        => '',
			};
			$has_logo       = in_array( $post_type, [ 'organisation', 'media_item' ], true );
			$brand_logo_id  = ( $has_logo && function_exists( 'get_field' ) ) ? get_field( 'brand_logo' ) : null;
			$brand_logo_svg = $brand_logo_id ? two_fiftyseven_get_inline_svg( $brand_logo_id ) : '';
		?>

			<article class="post-index__item<?php echo $card_modifier; ?>" data-color-space="<?php echo esc_attr( $card_space ); ?>">

				<?php if ( has_post_thumbnail() ) : ?>
					<div class="post-index__image | frame">
						<?php the_post_thumbnail( 'medium_large' ); ?>
					</div>
				<?php elseif ( $brand_logo_svg ) : ?>
					<div class="post-index__image post-index__image--logo | frame" aria-hidden="true">
						<?php echo $brand_logo_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — sanitized by two_fiftyseven_get_inline_svg() ?>
					</div>
				<?php endif; ?>

				<div class="post-index__body | stack">
					<time class="post-index__date text-monospace text-s" datetime="<?php echo esc_attr( get_the_date( 'Y-m-d' ) ); ?>">
						<?php echo esc_html( get_the_date() ); ?>
					</time>
					<h2 class="post-index__title | text-xl line-clamp-3">
						<a class="post-index__link" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					</h2>
					<p class="post-index__excerpt | line-clamp-3"><?php echo esc_html( get_the_excerpt() ); ?></p>
				</div>

			</article>

		<?php endwhile; ?>
	</div>

	<?php the_posts_pagination( [ 'mid_size' => 2 ] ); ?>

<?php else : ?>
	<p class="post-index__empty"><?php esc_html_e( 'No posts yet.', 'two-fiftyseven' ); ?></p>
<?php endif; ?>
