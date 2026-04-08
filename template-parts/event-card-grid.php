<?php
/**
 * Template Part: Event Card Grid
 *
 * Renders the event cards grid and pagination.
 * Used server-side on initial archive load AND returned via AJAX for tab/page switches.
 *
 * @param WP_Query $args['query']        The events WP_Query object.
 * @param int      $args['current_page'] Current pagination page number.
 * @param int      $args['total_pages']  Total number of pagination pages.
 */

$query        = $args['query'] ?? null;
$current_page = (int) ( $args['current_page'] ?? 1 );
$total_pages  = (int) ( $args['total_pages'] ?? 1 );

if ( ! $query instanceof WP_Query || ! $query->have_posts() ) : ?>
	<p class="post-archive__empty text-monospace"><?php esc_html_e( 'No events found.', 'two-fiftyseven' ); ?></p>
<?php return; endif; ?>

<ul class="event-cards | grid" data-grid-layout="halves" role="list">
	<?php
	$index = 0;
	while ( $query->have_posts() ) :
		$query->the_post();
		get_template_part( 'template-parts/event-card', null, [
			'post_id'    => get_the_ID(),
			'card_index' => $index,
		] );
		$index++;
	endwhile;
	wp_reset_postdata();
	?>
</ul>

<?php if ( $total_pages > 1 ) : ?>
<nav class="post-archive__pagination | repel" aria-label="<?php esc_attr_e( 'Events pagination', 'two-fiftyseven' ); ?>">

	<?php if ( $current_page > 1 ) : ?>
		<button class="btn" data-type="secondary" data-js="events-pager" data-page="<?php echo esc_attr( $current_page - 1 ); ?>">
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
		<button class="btn" data-type="secondary" data-js="events-pager" data-page="<?php echo esc_attr( $current_page + 1 ); ?>">
			<?php esc_html_e( 'Next', 'two-fiftyseven' ); ?> &rarr;
		</button>
	<?php else : ?>
		<span></span>
	<?php endif; ?>

</nav>
<?php endif;
