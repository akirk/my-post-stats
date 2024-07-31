<?php
/**
 * Plugin Name: My Post Stats Dashboard Widget
 * Description: A dashboard widget that displays a simple bar chart of posts and some numeric stats. Lists posts grouped by year and month.
 * Version: 1.0.0
 * Author: Alex Kirk
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MY_POST_STATS_VERSION', '1.0.0' );

class MyPostStatsDashboardWidget {
	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
		add_action( 'wp_ajax_get_my_post_stats', array( $this, 'get_my_post_stats' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'my-post-stats-css', plugin_dir_url( __FILE__ ) . 'assets/style.css', array(), MY_POST_STATS_VERSION );
		wp_enqueue_script( 'my-post-stats-js', plugin_dir_url( __FILE__ ) . 'assets/script.js', array(), MY_POST_STATS_VERSION, true );

		// Localize script with nonce
		wp_localize_script(
			'my-post-stats-js',
			'myPostStats',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'my_post_stats_nonce' ), // Create nonce
			)
		);
	}

	public function add_dashboard_widget() {
		wp_add_dashboard_widget( 'myPostStatsWidget', 'My Post Stats', array( $this, 'dashboard_widget_html' ) );
	}

	public function dashboard_widget_html() {
		?>
		<div id="myPostStatsWidget">
			<div id="postsChart"></div>
			<div id="hourlyDistribution"></div>
			<div id="buttonHolder">
				<select id="toggleAuthors">
					<option value="<?php echo esc_attr( get_current_user_id() ); ?>"><?php esc_html_e( 'Just Me', 'my-post-stats' ); ?></option>
					<option value="all"><?php esc_html_e( 'All Authors', 'my-post-stats' ); ?></option>
				</select>
				<select id="includePostFormats">
					<option value="all"><?php esc_html_e( 'Include all post formats', 'my-post-stats' ); ?></option>
					<option value="standard"><?php esc_html_e( 'Just standard posts', 'my-post-stats' ); ?></option>
				</select>
			</div>
			<details id="myPostStatsSummary">
				<summary><?php esc_html_e( 'Stats', 'my-post-stats' ); ?></summary>
				<table>
					<thead>
						<th colspan="2"><?php esc_html_e( 'Posts per', 'my-post-stats' ); ?></th>
						<th colspan="2"><?php esc_html_e( 'Most Active', 'my-post-stats' ); ?></th>
					</thead>
					<tr>
						<td><?php esc_html_e( 'Day:', 'my-post-stats' ); ?></td>
						<td><span id="posts_per_day">0</span></td>
						<td><?php esc_html_e( 'Day:', 'my-post-stats' ); ?></td>
						<td><span id="most_active_day"><?php esc_html_e( 'N/A', 'my-post-stats' ); ?></span></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Week:', 'my-post-stats' ); ?></td>
						<td><span id="posts_per_week">0</span></td>
						<td><?php esc_html_e( 'Hour:', 'my-post-stats' ); ?></td>
						<td><span id="most_active_hour"><?php esc_html_e( 'N/A', 'my-post-stats' ); ?></span></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Month:', 'my-post-stats' ); ?></td>
						<td><span id="posts_per_month">0</span></td>
						<td><?php esc_html_e( 'Year:', 'my-post-stats' ); ?></td>
						<td><span id="most_active_year"><?php esc_html_e( 'N/A', 'my-post-stats' ); ?></span></td>
					</tr>
				</table>
			</details>
			<details id="postsList">
				<summary><?php esc_html_e( 'Posts by Year and Month', 'my-post-stats' ); ?></summary>
				<div id="postsByMonth"></div>
			</details>
		</div>
		<?php
	}

	public function get_my_post_stats() {
		check_ajax_referer( 'my_post_stats_nonce', 'nonce' );
		// Gather input data
		$author_id = isset( $_POST['author'] ) && $_POST['author'] === 'all' ? '' : intval( $_POST['author'] );
		$post_formats = ! isset( $_POST['post_formats'] ) || $_POST['post_formats'] === 'all' ? false : $_POST['post_formats'];

		// Prepare arguments for WP_Query
		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1, // Get all posts
		);

		if ( 'standard' === $post_formats ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'post_format',
					'operator' => 'NOT EXISTS',
				),
			);
		} elseif ( $post_formats ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'post_format',
					'field'    => 'slug',
					'terms'    => array( $post_formats ),
				),
			);
		}

		if ( $author_id ) {
			$args['author'] = $author_id;
		}
		$args['orderby'] = 'post_date';
		$args['order'] = 'DESC';

		// Fetch posts
		$posts = get_posts( $args );

		// Initialize statistics containers
		$counts = array();
		$hourly_counts = array_fill( 0, 24, 0 ); // Initialize hourly counts with zero
		$posts_by_month = array();
		$weekday_counts = array_fill( 1, 7, 0 );

		$last_month = false;
		foreach ( $posts as $post ) {
			$post_date = strtotime( $post->post_date );
			if ( $last_month ) {
				while ( $last_month > $post_date ) {
					$last_month = strtotime( '-1 month', $last_month );
					$counts[ date( 'Y-m', $last_month ) ] = 0;
				}
			}
			$last_month = $post_date;
			$post_month = date( 'Y-m', $post_date );
			$day_of_week = date( 'w', $post_date );
			$hour_of_day = date( 'G', $post_date );

			// Count the posts per month
			if ( ! isset( $counts[ $post_month ] ) ) {
				$counts[ $post_month ] = 0;
			}
			++$counts[ $post_month ];

			// Update hourly counts
			++$hourly_counts[ $hour_of_day ];

			// Update weekday counts
			++$weekday_counts[ $day_of_week ];

			// Group posts by year and month
			$year = date( 'Y', $post_date );
			$month = date( 'm - F', $post_date );
			if ( ! isset( $posts_by_month[ $year ] ) ) {
				$posts_by_month[ $year ] = array();
			}
			if ( ! isset( $posts_by_month[ $year ][ $month ] ) ) {
				$posts_by_month[ $year ][ $month ] = array();
			}
			$title = $post->post_title;
			if ( empty( $title ) ) {
				$title = substr( get_the_excerpt( $post ), 0, 40 ) . '...';
			}
			$posts_by_month[ $year ][ $month ][] = array(
				'link'   => get_permalink( $post->ID ),
				'title'  => $title,
				'prefix' => date( 'jS H:i ', $post_date ),
			);
		}

		// Calculate statistics
		$most_active_day = array_keys( $weekday_counts, max( $weekday_counts ) )[0];
		$days_of_week = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
		$most_active_day_display = isset( $days_of_week[ $most_active_day ] ) ? $days_of_week[ $most_active_day ] : 'N/A';
		$most_active_hour = array_keys( $hourly_counts, max( $hourly_counts ) )[0];
		$most_active_hour = $most_active_hour . ':00 - ' . ( $most_active_hour + 1 ) . ':00';
		$posts_per_year = array();
		foreach ( $posts_by_month as $year => $months ) {
			$posts_per_year[ $year ] = array_sum( array_map( 'count', $months ) );
		}
		$most_active_year = array_keys( $posts_per_year, max( $posts_per_year ) )[0] . ' (' . max( $posts_per_year ) . ' posts)';

		$total_posts = array_sum( $counts );
		$days = count( $counts );
		$weeks = ceil( $days / 7 );
		$months = ceil( $days / 30 );

		$stats = array(
			'daily'            => $days ? round( $total_posts / $days, 2 ) : 0,
			'weekly'           => $weeks ? round( $total_posts / $weeks, 2 ) : 0,
			'monthly'          => $months ? round( $total_posts / $months, 2 ) : 0,
			'total_posts'      => $total_posts,
			'most_active_day'  => $most_active_day_display,
			'most_active_hour' => $most_active_hour,
			'most_active_year' => $most_active_year,
		);

		wp_send_json_success(
			array(
				'counts'       => $counts,
				'stats'        => $stats,
				'postsByMonth' => $posts_by_month,
				'hourlyCounts' => $hourly_counts,
			)
		);
	}
}

// Instantiate the class
new MyPostStatsDashboardWidget();
