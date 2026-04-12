<?php
/**
 * Blog page template (page slug: blog).
 *
 * @package Velune
 */

$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

$posts_query = new WP_Query(
	array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => (int) get_option( 'posts_per_page', 10 ),
		'paged'               => $paged,
		'ignore_sticky_posts' => true,
	)
);

$temp_query          = $GLOBALS['wp_query'];
$GLOBALS['wp_query'] = $posts_query;

get_header();

$subscription_url = velune_get_subscription_url();
$category_filters = get_categories(
	array(
		'taxonomy'   => 'category',
		'hide_empty' => true,
		'number'     => 4,
	)
);
?>
<main>
	<?php
	get_template_part(
		'template-parts/common/page-hero',
		null,
		array(
			'breadcrumbs' => array(
				array(
					'label' => __( 'Home', 'velune' ),
					'url'   => home_url( '/' ),
				),
				array(
					'label' => __( 'Journal', 'velune' ),
				),
			),
			'eyebrow'     => __( 'Journal', 'velune' ),
			'title'       => __( 'Editorial skincare, not content noise.', 'velune' ),
			'description' => __( 'Articles designed to reinforce trust and product understanding without sounding salesy.', 'velune' ),
		)
	);
	?>

	<section class="page-section">
		<?php
		get_template_part(
			'template-parts/blog/archive-layout',
			null,
			array(
				'posts_query'      => $posts_query,
				'category_filters' => $category_filters,
				'subscription_url' => $subscription_url,
			)
		);
		?>
	</section>
</main>
<?php
wp_reset_postdata();
$GLOBALS['wp_query'] = $temp_query;

get_footer();
