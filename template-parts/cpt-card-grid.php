<?php
/**
 * Template Part: CPT Card Grid
 *
 * Renders the card grid and pagination for CPT/post archives.
 * Used server-side on initial archive load AND returned via AJAX for
 * category tab switches and pagination.
 *
 * @param WP_Query $args['query']        The WP_Query object.
 * @param string   $args['post_type']    The post type slug.
 * @param int      $args['current_page'] Current pagination page number.
 * @param int      $args['total_pages']  Total number of pagination pages.
 */

$query        = $args['query'] ?? null;
$post_type    = $args['post_type'] ?? '';
$current_page = (int) ( $args['current_page'] ?? 1 );
$total_pages  = (int) ( $args['total_pages'] ?? 1 );

if ( ! $query instanceof WP_Query || ! $query->have_posts() ) : ?>
	<p class="post-archive__empty text-monospace"><?php esc_html_e( 'Nothing found.', 'two-fiftyseven' ); ?></p>
<?php return; endif;

$grid_layout = in_array( $post_type, [ 'person', 'organisation', 'media_item' ], true ) ? 'halves' : 'thirds';

// Map post types to their category taxonomy (mirrors archive-loop.php).
$taxonomy_map = [
	'post'         => 'category',
	'person'       => 'person_category',
	'organisation' => 'organisation_category',
	'media_item'   => 'media_item_category',
];
?>

<div class="post-index | grid" data-grid-layout="<?php echo esc_attr( $grid_layout ); ?>">
	<?php
	while ( $query->have_posts() ) :
		$query->the_post();

		$item_post_type  = get_post_type();
		$card_space      = function_exists( 'get_field' ) ? ( get_field( 'colour_space', get_the_ID() ) ?: 'neutral' ) : 'neutral';
		$card_modifier   = match ( $item_post_type ) {
			'person'       => ' post-index__item--person',
			'organisation' => ' post-index__item--organisation',
			'media_item'   => ' post-index__item--media-item',
			default        => '',
		};

		$has_logo       = in_array( $item_post_type, [ 'organisation', 'media_item' ], true );
		$brand_logo_id  = ( $has_logo && function_exists( 'get_field' ) ) ? get_field( 'brand_logo' ) : null;
		$brand_logo_svg = $brand_logo_id ? two_fiftyseven_get_inline_svg( $brand_logo_id ) : '';

		$badge_taxonomy = $taxonomy_map[ $item_post_type ] ?? '';
		$badge_term     = '';
		if ( $badge_taxonomy ) {
			$terms = get_the_terms( get_the_ID(), $badge_taxonomy );
			if ( $terms && ! is_wp_error( $terms ) ) {
				// Skip 'uncategorized' — uncategorised posts show no category badge.
				foreach ( $terms as $t ) {
					if ( $t->slug !== 'uncategorized' ) {
						$badge_term = $t->name;
						break;
					}
				}
			}
		}

		$use_type = '';
		if ( $item_post_type === 'organisation' && function_exists( 'get_field' ) ) {
			$use_type = get_field( 'organisation_use_type', get_the_ID() ) ?: '';
		}
	?>
		<article class="post-index__item<?php echo $card_modifier; ?>" data-color-space="<?php echo esc_attr( $card_space ); ?>">

			<?php if ( $brand_logo_svg ) : ?>
				<div class="post-index__image post-index__image--logo | frame" aria-hidden="true">
					<?php echo $brand_logo_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — sanitized by two_fiftyseven_get_inline_svg() ?>
				</div>
			<?php elseif ( has_post_thumbnail() && $item_post_type !== 'organisation' ) : ?>
				<div class="post-index__image | frame">
					<?php the_post_thumbnail( 'medium_large' ); ?>
				</div>
			<?php endif; ?>

			<div class="post-index__body | stack">
				<?php if ( $item_post_type === 'post' || $badge_term || $use_type ) : ?>
				<div class="cluster badge-cluster">
					<?php if ( $item_post_type === 'post' ) : ?>
						<span class="badge">
							<?php echo esc_html( get_the_date( 'j M Y' ) ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $badge_term ) : ?>
						<span class="badge">
							<?php echo esc_html( $badge_term ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $use_type ) : ?>
						<span class="badge">
							<?php echo esc_html( strtoupper( $use_type ) ); ?>
						</span>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<h2 class="post-index__title | text-xl line-clamp-3">
					<a class="post-index__link" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</h2>
				<p class="post-index__excerpt | line-clamp-3"><?php echo esc_html( get_the_excerpt() ); ?></p>
			</div>

		</article>

	<?php
	endwhile;
	wp_reset_postdata();
	?>
</div>

<?php if ( $total_pages > 1 ) : ?>
<nav class="post-archive__pagination | repel" aria-label="<?php esc_attr_e( 'Archive pagination', 'two-fiftyseven' ); ?>">

	<?php if ( $current_page > 1 ) : ?>
		<button class="btn" data-type="secondary" data-js="cpt-pager" data-page="<?php echo esc_attr( $current_page - 1 ); ?>">
			&larr; <?php esc_html_e( 'Previous', 'two-fiftyseven' ); ?>
		</button>
	<?php else : ?>
		<span></span>
	<?php endif; ?>

	<span class="post-archive__page-count text-monospace text-s">
		<?php printf(
			/* translators: 1: current page, 2: total pages */
			esc_html__( 'Page %1$d of %2$d', 'two-fiftyseven' ),
			$current_page,
			$total_pages
		); ?>
	</span>

	<?php if ( $current_page < $total_pages ) : ?>
		<button class="btn" data-type="secondary" data-js="cpt-pager" data-page="<?php echo esc_attr( $current_page + 1 ); ?>">
			<?php esc_html_e( 'Next', 'two-fiftyseven' ); ?> &rarr;
		</button>
	<?php else : ?>
		<span></span>
	<?php endif; ?>

</nav>
<?php endif;
