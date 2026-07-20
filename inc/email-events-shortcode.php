<table style="width:100%;border-collapse:collapse;" role="presentation">
	<?php while ( $query->have_posts() ) : $query->the_post(); ?>
		<?php
		$badge          = function_exists( 'two57_format_event_badge' ) ? two57_format_event_badge( get_the_ID() ) : '';
		$location_badge = function_exists( 'two57_get_event_location_badge' ) ? two57_get_event_location_badge( get_the_ID() ) : '';
		$excerpt        = has_excerpt() ? get_the_excerpt() : '';
		?>
		<tr>
			<td style="padding:16px 0;border-bottom:1px solid #e5e5e5;">
				<h3 style="margin:0 0 4px;font-size:18px;">
					<?php echo esc_html( get_the_title() ); ?>
				</h3>
				<?php if ( $badge || $location_badge ) : ?>
					<p style="margin:0 0 8px;font-size:13px;color:#6b7280;">
						<?php if ( $badge ) : ?><?php echo esc_html( $badge ); ?><?php endif; ?>
						<?php if ( $badge && $location_badge ) : ?><br><?php endif; ?>
						<?php if ( $location_badge ) : ?><?php echo esc_html( $location_badge ); ?><?php endif; ?>
					</p>
				<?php endif; ?>
				<?php if ( $excerpt ) : ?>
					<p style="margin:0 0 8px;font-size:15px;line-height:1.5;">
						<?php echo esc_html( $excerpt ); ?>
					</p>
				<?php endif; ?>
				<a href="<?php echo esc_url( get_permalink() ); ?>"
				   style="font-size:14px;font-weight:600;color:#1a1a1a;text-decoration:underline;">
					Read more →
				</a>
			</td>
		</tr>
	<?php endwhile; ?>
</table>
