<?php

function iiq_related_by_primary_sc( $atts = [] ){
	if ( ! is_singular() ) return '';

	$atts = shortcode_atts([
		'count' => 3,
		'thumbs'=> 1,
	], $atts, 'iiq_related_by_primary');

	$pid = get_the_ID();
	$pt = get_post_type($pid);
	if ( ! $pt || ! is_object_in_taxonomy($pt, 'category') ) return '';

	// Determine target category:
	// 1) use current post's primary if set
	// 2) else, if it has only one category, use it
	// 3) else, fall back to first-assigned category (helper)
	$primary_current = iiq_get_primary_meta_cat_id( $pid );
	$terms_current = get_the_terms( $pid, 'category' );
	$single_only = ( ! empty($terms_current) && ! is_wp_error($terms_current) && count($terms_current) === 1 );

	if ( $primary_current ) {
		$target_cat = (int) $primary_current;
	} elseif ( $single_only ) {
		$target_cat = (int) $terms_current[0]->term_id;
	} else {
		$target_cat = (int) iiq_primary_cat_id( $pid );
	}
	if ( ! $target_cat ) return '';

	$count = (int) $atts['count'];

	$base_args = [
		'post_type' => $pt,
		'post__not_in' => [ $pid ],
		'ignore_sticky_posts' => true,
		'no_found_rows' => true,
		'orderby' => 'date',
		'order' => 'DESC',
	];

	$primary_q = new WP_Query( array_merge( $base_args, [
		'posts_per_page' => $count,
		'meta_query' => [
			'relation' => 'OR',
			[
				'key' => '_yoast_wpseo_primary_category',
				'value' => (string) $target_cat,
				'compare' => '='
			],
			[
				'key' => 'rank_math_primary_category',
				'value' => (string) $target_cat,
				'compare' => '='
			],
		],
	] ) );

	$posts = [];
	$found_ids = [];

	if ( $primary_q->have_posts() ) {
		foreach ( $primary_q->posts as $p ) {
			$posts[] = $p;
			$found_ids[] = $p->ID;
		}
	}

	$need = $count - count( $posts );
	if ( $need > 0 ) {
		$fill_q = new WP_Query( array_merge( $base_args, [
			'posts_per_page' => $need * 3, // oversample to allow filtering
			'post__not_in' => array_merge( $base_args['post__not_in'], $found_ids ),
			'tax_query' => [[
				'taxonomy' => 'category',
				'field' => 'term_id',
				'terms' => [ $target_cat ],
			]],
		] ) );

		if ( $fill_q->have_posts() ) {
			foreach ( $fill_q->posts as $p ) {
				if ( in_array( $p->ID, $found_ids, true ) ) continue;

				$pm = iiq_get_primary_meta_cat_id( $p->ID );
				if ( $pm !== 0 ) continue;

				$its_terms = get_the_terms( $p->ID, 'category' );
				if ( ! empty( $its_terms ) && ! is_wp_error( $its_terms ) && count( $its_terms ) === 1 && (int) $its_terms[0]->term_id === $target_cat ) {
					$posts[] = $p;
					$found_ids[] = $p->ID;
					if ( count( $posts ) >= $count ) break;
				}
			}
		}
	}

	if ( empty( $posts ) ) return '';

	global $post;
	ob_start(); ?>
	<aside class="related-posts related-<?= esc_attr($pt); ?>">
		<ul class="related-list">
		<?php foreach ( $posts as $post ) : setup_postdata( $post ); ?>
			<li class="related-item">
				<a class="related-link" href="<?php the_permalink(); ?>">
					<div class="related-thumb-container">
						<?php if ( $atts['thumbs'] && has_post_thumbnail() ) {
							the_post_thumbnail('full', ['loading'=>'lazy', 'class'=>'related-thumb']);
						} ?>
						<?php
						list( $cat_name, $cat_slug ) = rs_get_primary_category( get_the_ID(), 'category' );
						if ( $cat_name ) : ?>
							<a class="primary-category" href="<?php echo esc_url( get_term_link( $cat_slug, 'category' ) ); ?>">
								<?php echo esc_html( $cat_name ); ?>
							</a>
						<?php endif; ?>
					</div>
					<div class="related-item-body">
						<h3 class="related-title"><?php the_title(); ?></h3>
						<?php
						$excerpt = get_the_excerpt();
						if ( ! empty( $excerpt ) ) {
							echo '<p class="related-excerpt">' . $excerpt . '</p>';
						}
						?>
						<a href="<?php the_permalink(); ?>" class="cta-button related-post-button">
							Read More
							<img src="<!-- Add link to little 'arrow' image url here, or just remove the img tag if not needed -->" alt="Image inside of button - click here to visit post" class="button-arrow" />
						</a>
					</div>
				</a>
			</li>
		<?php endforeach; wp_reset_postdata(); ?>
		</ul>
	</aside>
	<?php
	return trim( ob_get_clean() );
}
add_shortcode('iiq_related_by_primary','iiq_related_by_primary_sc');


?>