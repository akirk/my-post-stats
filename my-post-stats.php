<?php
/**
 * Plugin Name: My Post Stats
 * Description: A WordPress dashboard widget to display your own post stats.
 * Version: 1.0.0
 * Author: Alex Kirk
 * Requires PHP: 7.0
 *
 * License: GPL2
 * Text Domain: my-post-stats
 *
 * @package My_Post_Stats
 */

namespace My_Post_Stats;

use DateTime;
use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MY_POST_STATS_VERSION', '1.0.0' );

class Dashboard_Widget {
	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
		add_action( 'wp_ajax_get_my_post_stats', array( $this, 'get_my_post_stats' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'my-post-stats-css', plugin_dir_url( __FILE__ ) . 'assets/style.css', array(), MY_POST_STATS_VERSION );
		wp_enqueue_script( 'my-post-stats-js', plugin_dir_url( __FILE__ ) . 'assets/script.js', array(), MY_POST_STATS_VERSION, true );

		global $_wp_admin_css_colors;
		$admin_colors = $_wp_admin_css_colors[get_user_option( 'admin_color' )]->colors;
		array_pop( $admin_colors );
		$background_color = array_pop( $admin_colors );

		// If the color is rather dark, use a light text color:
		$foreground_color = ( 0.2126 * hexdec( substr( $background_color, 1, 2 ) ) + 0.7152 * hexdec( substr( $background_color, 3, 2 ) ) + 0.0722 * hexdec( substr( $background_color, 5, 2 ) ) ) > 128 ? '#000' : '#fff';

		wp_localize_script(
			'my-post-stats-js',
			'myPostStats',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'my_post_stats_nonce' ),
				'background_color' => $background_color,
				'foreground_color' => $foreground_color,
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
						<th colspan="2"><?php esc_html_e( 'All time', 'my-post-stats' ); ?></th>
					</thead>
					<tr>
						<td><?php esc_html_e( 'Day' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></td>
						<td><span id="my_posts_posts_per_day">0</span></td>
						<td><?php esc_html_e( 'Day' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></td>
						<td><span id="my_posts_most_active_day"><?php esc_html_e( 'N/A', 'my-post-stats' ); ?></span></td>
						<td><?php esc_html_e( 'First Post', 'my-post-stats' ); ?></td>
						<td><span id="my_posts_first_post"><?php esc_html_e( 'N/A', 'my-post-stats' ); ?></span></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Week' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></td>
						<td><span id="my_posts_posts_per_week">0</span></td>
						<td><?php esc_html_e( 'Hour' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></td>
						<td><span id="my_posts_most_active_hour"><?php esc_html_e( 'N/A', 'my-post-stats' ); ?></span></td>
						<td><?php esc_html_e( 'Total', 'my-post-stats' ); ?></td>
						<td><span id="my_posts_total_posts"><?php esc_html_e( 'N/A', 'my-post-stats' ); ?></span></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Month' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></td>
						<td><span id="my_posts_posts_per_month">0</span></td>
						<td><?php esc_html_e( 'Year' ); /* phpcs:ignore WordPress.WP.I18n.MissingArgDomain */ ?></td>
						<td><span id="my_posts_most_active_year"><?php esc_html_e( 'N/A', 'my-post-stats' ); ?></span></td>
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

		$author_id = isset( $_POST['author'] ) && 'all' === $_POST['author'] ? '' : intval( $_POST['author'] );
		$post_formats = isset( $_POST['post_formats'] ) ? sanitize_text_field( wp_unslash( $_POST['post_formats'] ) ) : 'all';
		$timezone = isset( $_POST['timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['timezone'] ) ) : 'UTC';

		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);

		if ( 'standard' === $post_formats ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'post_format',
					'operator' => 'NOT EXISTS',
				),
			);
		} elseif ( $post_formats && in_array( $post_formats, get_post_format_slugs() ) ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
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

		$posts = get_posts( $args );

		$counts = array();
		$hourly_counts = array_fill( 0, 24, 0 );
		$posts_by_month = array();
		$weekday_counts = array_fill( 1, 7, 0 );

		$last_month = false;
		foreach ( $posts as $post ) {
			$post_date = false;
			try {
				if ( substr( $post->post_date_gmt, 0, 4 ) > 1970 ) {
					$post_date = new DateTime( $post->post_date_gmt, new DateTimeZone( 'UTC' ) );
					$post_date->setTimezone( new DateTimeZone( $timezone ) );
				} elseif ( substr( $post->post_date, 0, 4 ) > 1970 ) {
					$post_date = new DateTime( $post->post_date, new DateTimeZone( $timezone ) );
				}
			} catch ( Exception $e ) {
				continue;
			}

			if ( ! $post_date ) {
				continue;
			}
			if ( $last_month ) {
				while ( $last_month > $post_date ) {
					$last_month = $last_month->modify( '-1 month' );
					$counts[ $last_month->format( 'Y-m' ) ] = 0;
				}
			}
			$last_month = $post_date;
			$post_month = $post_date->format( 'Y-m' );
			$day_of_week = $post_date->format( 'w' );
			$hour_of_day = $post_date->format( 'G' );

			if ( ! isset( $counts[ $post_month ] ) ) {
				$counts[ $post_month ] = 0;
			}
			++$counts[ $post_month ];

			++$hourly_counts[ $hour_of_day ];

			++$weekday_counts[ $day_of_week ];

			$year = $post_date->format( 'Y' );
			$month = $post_date->format( 'm - F' );
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
				'prefix' => $post_date->format( 'jS H:i ' ),
			);
		}

		$most_active_day = array_keys( $weekday_counts, max( $weekday_counts ) )[0];
		global $wp_locale;
		$most_active_day_display = $wp_locale->get_weekday( $most_active_day ) . ' (' . max( $weekday_counts ) . ')';
		$most_active_hour = array_keys( $hourly_counts, max( $hourly_counts ) )[0];
		$most_active_hour = $most_active_hour . ':00 - ' . ( $most_active_hour + 1 ) . ':00';
		$posts_per_year = array();
		foreach ( $posts_by_month as $year => $months ) {
			$posts_per_year[ $year ] = array_sum( array_map( 'count', $months ) );
		}
		$most_active_year = array_keys( $posts_per_year, max( $posts_per_year ) )[0] . ' (' . max( $posts_per_year ) . ')';

		$total_posts = array_sum( $counts );
		$days = $post_date->diff( new DateTime( 'now', new DateTimeZone( $timezone ) ) )->days;
		$weeks = ceil( $days / 7 );
		$months = ceil( $days / 30 );

		$stats = array(
			'daily'            => $days ? round( $total_posts / $days, 2 ) : 0,
			'weekly'           => $weeks ? round( $total_posts / $weeks, 2 ) : 0,
			'monthly'          => $months ? round( $total_posts / $months, 2 ) : 0,
			'most_active_day'  => $most_active_day_display,
			'most_active_hour' => $most_active_hour,
			'most_active_year' => $most_active_year,
			'first_post'       => $post_date->format( 'M j, Y' ),
			'total_posts'      => $total_posts,
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


new Dashboard_Widget();
