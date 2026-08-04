<?php
/**
 * 257 Case Studies — ACF block render template.
 *
 * Renders Organisation posts as a horizontal Swiper slider.
 * Three cards fill the central wrapper width; additional cards overflow right.
 *
 * ACF fields:
 *   case_studies_eyebrow        — small label above heading (text)
 *   case_studies_heading        — section heading (text)
 *   case_studies_items          — selected Organisation post IDs (relationship)
 *   case_studies_archive_link   — optional CTA button (link)
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$eyebrow          = get_field( 'case_studies_eyebrow' );
$heading          = get_field( 'case_studies_heading' ) ?: __( 'Case Studies', 'two-fiftyseven' );
$selected_ids_raw = get_field( 'case_studies_items' ) ?: [];
$archive_link     = get_field( 'case_studies_archive_link' ) ?: [];

$selected_ids = array_values( array_filter( array_map( 'intval', (array) $selected_ids_raw ) ) );
$items        = [];

if ( $selected_ids ) {
	$items = get_posts( [
		'post_type'      => 'organisation',
		'post_status'    => 'publish',
		'post__in'       => $selected_ids,
		'orderby'        => 'post__in',
		'posts_per_page' => 6,
	] );
}

$archive_url    = '';
$archive_title  = '';
$archive_target = '';

if ( ! empty( $archive_link['url'] ) ) {
	$archive_url    = $archive_link['url'];
	$archive_title  = ! empty( $archive_link['title'] ) ? $archive_link['title'] : __( 'Explore organisations', 'two-fiftyseven' );
	$archive_target = ! empty( $archive_link['target'] ) ? $archive_link['target'] : '';
} else {
	$archive_url   = get_post_type_archive_link( 'organisation' );
	$archive_title = __( 'Explore organisations', 'two-fiftyseven' );
}

$slide_count = count( $items );
?>

<section class="case-studies-wrapper | block" data-block="full">
<div class="case-studies | wrapper">
	<div class="case-studies__inner | stack">
		<?php if ( $eyebrow || $heading ) : ?>
			<div class="case-studies__header | stack" data-scroll data-scroll-repeat>
				<?php if ( $eyebrow ) : ?>
					<p class="case-studies__eyebrow | text-monospace text-s"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<?php if ( $heading ) : ?>
					<h2 class="case-studies__heading | text-3xl text-wrap-balance"><?php echo esc_html( $heading ); ?></h2>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $items ) : ?>
			<div class="swiper case-studies__swiper" data-slides="<?php echo esc_attr( $slide_count ); ?>">
				<div class="swiper-wrapper">
				<?php foreach ( $items as $item ) :
					$item_id       = (int) $item->ID;
					$item_title    = get_the_title( $item_id );
					$item_link     = get_permalink( $item_id );
					$item_excerpt  = get_the_excerpt( $item_id );
					$brand_logo_id = function_exists( 'get_field' ) ? (int) get_field( 'brand_logo', $item_id ) : 0;
					$brand_logo    = $brand_logo_id ? two_fiftyseven_get_inline_svg( $brand_logo_id ) : '';
					$use_type      = function_exists( 'get_field' ) ? get_field( 'organisation_use_type', $item_id ) : '';
					$categories    = get_the_terms( $item_id, 'organisation_category' );
				?>
				<div class="swiper-slide">
					<a class="case-studies__card-link" href="<?php echo esc_url( $item_link ); ?>">
						<div class="case-studies__card | card">
							<?php if ( $use_type || ( $categories && ! is_wp_error( $categories ) ) ) : ?>
								<div class="cluster badge-cluster case-studies__badges">
									<?php if ( $use_type ) : ?>
										<span class="badge" data-color="purple"><?php echo esc_html( strtoupper( $use_type ) ); ?></span>
									<?php endif; ?>
									<?php if ( $categories && ! is_wp_error( $categories ) ) :
										foreach ( $categories as $cat ) :
											if ( 'uncategorized' === $cat->slug ) { continue; }
										?>
											<span class="badge"><?php echo esc_html( $cat->name ); ?></span>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
							<?php endif; ?>
							<div class="case-studies__logo" aria-hidden="true">
								<?php if ( $brand_logo ) : ?>
									<?php echo $brand_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by two_fiftyseven_get_inline_svg() ?>
								<?php endif; ?>
							</div>
							<div class="case-studies__card-copy | stack">
								<?php if ( $item_title ) : ?>
									<h3 class="case-studies__card-title | card-title text-xl font-bold"><?php echo esc_html( $item_title ); ?></h3>
								<?php endif; ?>
								<?php if ( $item_excerpt ) : ?>
									<p class="case-studies__card-excerpt card-desc | text-s-m line-clamp-3"><?php echo esc_html( $item_excerpt ); ?></p>
								<?php endif; ?>
							</div>
						</div>
					</a>
				</div>
					<?php endforeach; ?>
				</div>

			<?php if ( $slide_count > 1 || $archive_url ) : ?>
				<div class="case-studies__controls">
					<?php if ( $archive_url ) : ?>
						<a
							class="btn"
							data-type="secondary"
							href="<?php echo esc_url( $archive_url ); ?>"
							<?php if ( $archive_target ) : ?>target="<?php echo esc_attr( $archive_target ); ?>" rel="noopener noreferrer"<?php endif; ?>
						>
							<?php echo esc_html( $archive_title ); ?>
						</a>
					<?php endif; ?>
					<div class="case-studies__nav">
						<?php if ( $slide_count > 1 ) : ?>
							<button class="swiper-button-prev" aria-label="<?php esc_attr_e( 'Previous case studies', 'two-fiftyseven' ); ?>"></button>
							<button class="swiper-button-next" aria-label="<?php esc_attr_e( 'Next case studies', 'two-fiftyseven' ); ?>"></button>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
			</div>

		<?php elseif ( $is_preview ) : ?>
			<p class="case-studies__preview-hint">Select up to 6 Organisation posts in the block settings &rarr;</p>
		<?php endif; ?>
	</div>
</div>
</section>
