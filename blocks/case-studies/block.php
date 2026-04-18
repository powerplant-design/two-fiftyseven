<?php
/**
 * 257 Case Studies — ACF block render template.
 *
 * Renders three selected Organisation posts as cards with:
 * - SVG logo (inline, currentColor aware)
 * - Post title
 * - Post subheading (ACF post_subheading)
 * - Excerpt
 *
 * Also renders an editable heading and a secondary CTA button to the
 * Organisations archive.
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

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
?>

<section class="case-studies | block">
	<div class="case-studies__inner | stack">
		<?php if ( $heading ) : ?>
			<h2 class="case-studies__heading | text-2xl" data-scroll data-scroll-repeat><?php echo esc_html( $heading ); ?></h2>
		<?php endif; ?>

		<?php if ( $items ) : ?>
			<ul class="case-studies__cards | grid cards" data-grid-layout="thirds">
				<?php foreach ( $items as $index => $item ) :
					$item_id       = (int) $item->ID;
					$item_title    = get_the_title( $item_id );
					$item_link     = get_permalink( $item_id );
					$item_excerpt  = get_the_excerpt( $item_id );
					$brand_logo_id = function_exists( 'get_field' ) ? (int) get_field( 'brand_logo', $item_id ) : 0;
					$brand_logo    = $brand_logo_id ? two_fiftyseven_get_inline_svg( $brand_logo_id ) : '';
					$delay_ms      = $index * 300; // 0ms, 200ms, 400ms

					$use_type   = function_exists( 'get_field' ) ? ( get_field( 'organisation_use_type', $item_id ) ?: '' ) : '';
					$badge_term = '';
					$terms      = get_the_terms( $item_id, 'organisation_category' );
					if ( $terms && ! is_wp_error( $terms ) ) {
						foreach ( $terms as $t ) {
							if ( $t->slug !== 'uncategorized' ) {
								$badge_term = $t->name;
								break;
							}
						}
					}
				?>
					<li class="case-studies__card | card" data-scroll data-scroll-repeat style="--delay: <?php echo $delay_ms; ?>ms">
						<a class="case-studies__card-link" href="<?php echo esc_url( $item_link ); ?>">
							<?php if ( $use_type || $badge_term ) : ?>
								<div class="cluster badge-cluster">
									<?php if ( $badge_term ) : ?>
										<span class="badge"><?php echo esc_html( $badge_term ); ?></span>
									<?php endif; ?>
									<?php if ( $use_type ) : ?>
										<span class="badge"><?php echo esc_html( strtoupper( $use_type ) ); ?></span>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<?php if ( $brand_logo ) : ?>
								<div class="case-studies__logo" aria-hidden="true">
									<?php echo $brand_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by two_fiftyseven_get_inline_svg() ?>
								</div>
							<?php endif; ?>

							<?php if ( $item_title ) : ?>
								<h3 class="case-studies__card-title | card-title"><?php echo esc_html( $item_title ); ?></h3>
							<?php endif; ?>

							<?php if ( $item_excerpt ) : ?>
								<p class="case-studies__card-excerpt card-desc | text-m line-clamp-3"><?php echo esc_html( $item_excerpt ); ?></p>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php elseif ( $is_preview ) : ?>
			<p class="case-studies__preview-hint">Select up to 6 Organisation posts in the block settings &rarr;</p>
		<?php endif; ?>

		<?php if ( $archive_url ) : ?>
			<div class="case-studies__cta" data-scroll data-scroll-repeat>
				<a
					class="btn"
					data-type="secondary"
					href="<?php echo esc_url( $archive_url ); ?>"
					<?php if ( $archive_target ) : ?>target="<?php echo esc_attr( $archive_target ); ?>" rel="noopener noreferrer"<?php endif; ?>
				>
					<?php echo esc_html( $archive_title ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
</section>
