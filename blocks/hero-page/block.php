<?php
/**
 * Hero Page — ACF block render template.
 *
 * Renders a page-level hero in one of two layout modes:
 *   full-bleed  Full-bleed background image with white text (default)
 *   split       Two-column: text content left, right-column photo, on plain background
 *
 * Both layouts support an optional eyebrow label, headline, subtitle, CTA, and marquee.
 *
 * ACF fields:
 *   page_hero_layout                — "full-bleed" or "split"
 *   page_hero_eyebrow               — optional short label above the headline
 *   page_hero_headline              — display heading (textarea, supports <br>)
 *   page_hero_subtitle              — subtitle paragraph (textarea)
 *   page_hero_primary_button        — optional CTA button (link array)
 *   page_hero_secondary_button       — optional secondary CTA button (link array)
 *   page_hero_background_image      — background image (array, full-bleed only)
 *   page_hero_column_image          — right-column photo (array, split only)
 *   page_hero_marquee_enabled       — show/hide toggle (true_false, default 1)
 *   page_hero_marquee_label         — eyebrow text above marquee (e.g. "As used by")
 *   page_hero_marquee_mode          — "default" (auto by page slug) or "custom" (hand-picked)
 *   page_hero_marquee_logos         — relationship: post IDs from organisation/person/event CPTs (custom mode only)
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused — no inner blocks).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$layout         = get_field( 'page_hero_layout' ) ?: 'full-bleed';
$layout         = in_array( $layout, [ 'full-bleed', 'split' ], true ) ? $layout : 'full-bleed';
$eyebrow        = get_field( 'page_hero_eyebrow' );
$headline       = get_field( 'page_hero_headline' );
$subtitle       = get_field( 'page_hero_subtitle' );
$primary_button   = get_field( 'page_hero_primary_button' );
$secondary_button = get_field( 'page_hero_secondary_button' );
$bg_image         = get_field( 'page_hero_background_image' );
$column_image   = 'split' === $layout ? get_field( 'page_hero_column_image' ) : null;
// Treat null (field never saved) as enabled — matches the default_value of 1.
$marquee_enabled_raw = get_field( 'page_hero_marquee_enabled' );
$marquee_enabled     = null === $marquee_enabled_raw ? true : (bool) $marquee_enabled_raw;
$marquee_label       = get_field( 'page_hero_marquee_label' );
$marquee_mode        = get_field( 'page_hero_marquee_mode' ) ?: 'default';

// Background image inline CSS custom property (full-bleed layout only).
$bg_style = '';
if ( 'full-bleed' === $layout && ! empty( $bg_image['url'] ) ) {
	$bg_style = ' style="--hero-bg: url(\'' . esc_url( $bg_image['url'] ) . '\')"';
}

$section_class = 'hero-page' . ( 'split' === $layout ? ' hero-page--split' : '' );
?>

<section class="<?php echo esc_attr( $section_class ); ?>" data-block="full"<?php echo $bg_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>

	<?php if ( 'full-bleed' === $layout ) : ?>
	<div class="hero-page__backdrop | overlay" aria-hidden="true"></div>
	<?php endif; ?>

	<div class="hero-page__inner | wrapper<?php echo 'full-bleed' === $layout ? ' stack' : ''; ?>">

		<?php if ( 'split' === $layout ) : ?><div class="hero-page__content | stack"><?php endif; ?>

		<?php if ( $eyebrow ) : ?>
			<p class="hero-page__eyebrow | text-monospace text-s"><?php echo esc_html( $eyebrow ); ?></p>
		<?php endif; ?>

		<?php if ( $headline ) :
			// Short headlines (< 25 chars) get one step up on the type scale.
			$headline_size = mb_strlen( wp_strip_all_tags( $headline ) ) < 25 ? 'text-4xl' : 'text-3xl';
		?>
			<h1 class="hero-page__headline | line-clamp-3 line-height-slim <?php echo esc_attr( $headline_size ); ?>"><?php echo wp_kses( $headline, [ 'br' => [] ] ); ?></h1>
		<?php elseif ( $is_preview ) : ?>
			<p style="<?php echo 'full-bleed' === $layout ? 'color:white;' : ''; ?>opacity:0.5;text-align:center;">Add a headline in the block settings →</p>
		<?php endif; ?>

		<?php if ( $subtitle ) : ?>
			<h2 class="hero-page__subtitle | line-clamp-4 text-m-l"><?php echo wp_kses( $subtitle, [ 'br' => [], 'strong' => [], 'em' => [] ] ); ?></h2>
		<?php endif; ?>

		<?php if ( ! empty( $primary_button['url'] ) || ! empty( $secondary_button['url'] ) ) : ?>
		<div class="hero-page__actions | cluster">
			<?php if ( ! empty( $primary_button['url'] ) ) : ?>
				<a
					class="btn"
					data-type="primary"
					<?php if ( 'full-bleed' === $layout ) : ?>data-invert<?php endif; ?>
					href="<?php echo esc_url( $primary_button['url'] ); ?>"
					<?php if ( ! empty( $primary_button['target'] ) ) : ?>target="<?php echo esc_attr( $primary_button['target'] ); ?>" rel="noopener noreferrer"<?php endif; ?>
				>
					<?php echo esc_html( $primary_button['title'] ?: $primary_button['url'] ); ?>
				</a>
			<?php endif; ?>
			<?php if ( ! empty( $secondary_button['url'] ) ) : ?>
				<a
					class="btn"
					data-type="secondary"
					<?php if ( 'full-bleed' === $layout ) : ?>data-invert<?php endif; ?>
					href="<?php echo esc_url( $secondary_button['url'] ); ?>"
					<?php if ( ! empty( $secondary_button['target'] ) ) : ?>target="<?php echo esc_attr( $secondary_button['target'] ); ?>" rel="noopener noreferrer"<?php endif; ?>
				>
					<?php echo esc_html( $secondary_button['title'] ?: $secondary_button['url'] ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php if ( 'split' === $layout ) : ?>
		</div>
		<?php if ( ! empty( $column_image['url'] ) ) : ?>
		<div class="hero-page__image-col">
			<img
				class="hero-page__column-img"
				src="<?php echo esc_url( $column_image['url'] ); ?>"
				alt="<?php echo esc_attr( $column_image['alt'] ?? '' ); ?>"
				<?php if ( ! empty( $column_image['width'] ) ) : ?>width="<?php echo (int) $column_image['width']; ?>"<?php endif; ?>
				<?php if ( ! empty( $column_image['height'] ) ) : ?>height="<?php echo (int) $column_image['height']; ?>"<?php endif; ?>
				loading="eager"
			>
		</div>
		<?php endif; ?>
		<?php endif; ?>

	</div>

	<?php if ( $marquee_enabled ) :
		// Build a flat array of SVG attachment IDs from CPTs or manual selection.
		$attachment_ids = [];

		if ( 'custom' === $marquee_mode ) :
			$selected_post_ids = get_field( 'page_hero_marquee_logos' ) ?: [];
		else :
			// Map page slug → use-type filter for organisation logos.
			$page_slug    = get_post_field( 'post_name', $post_id );
			$use_type_map = [
				'workspace'   => [ 'base', 'hub', 'desk' ],
				'meetings'    => [ 'meet' ],
				'host-events' => [ 'events' ],
			];

			$query_args = [
				'post_type'      => 'organisation',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			];

			// Filter by use type when on a specific page; homepage shows all.
			if ( isset( $use_type_map[ $page_slug ] ) ) {
				$query_args['meta_query'] = [
					[
						'key'     => 'organisation_use_type',
						'value'   => $use_type_map[ $page_slug ],
						'compare' => 'IN',
					],
				];
			}

			$logo_query        = new WP_Query( $query_args );
			$selected_post_ids = $logo_query->posts;

			// host-events: also include all event CPT posts with logos.
			if ( 'host-events' === $page_slug ) {
				$event_query = new WP_Query( [
					'post_type'      => 'event',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				] );
				$selected_post_ids = array_merge( $selected_post_ids, $event_query->posts );
			}
		endif;

		foreach ( $selected_post_ids as $logo_post_id ) :
			$logo_attachment_id = (int) get_field( 'brand_logo', (int) $logo_post_id );
			if ( $logo_attachment_id ) :
				$attachment_ids[] = $logo_attachment_id;
			endif;
		endforeach;

		$attachment_ids = array_values( array_unique( $attachment_ids ) );

		if ( $attachment_ids ) :
			get_template_part( 'template-parts/logo-marquee', null, [
				'attachment_ids' => $attachment_ids,
				'label'          => $marquee_label,
			] );
		elseif ( $is_preview ) :
	?>
		<p style="color:white;opacity:0.5;text-align:center;padding:1rem;">No logos found &mdash; add a Brand Logo to Organisations, People, or Events &rarr;</p>
	<?php
		endif;
	endif; ?>

</section>
