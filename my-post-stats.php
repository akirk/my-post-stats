<?php
/**
 * Plugin Name: My Post Stats Dashboard Widget
 * Description: A dashboard widget that displays a simple bar chart of posts and some numeric stats. Lists posts grouped by year and month.
 * Version: 1.0
 * Author: Alex Kirk
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function my_post_stats_dashboard_widget() {
	?>
	<div id="myPostStatsWidget">
		<div id="postsChart"></div>
		<div id="hourlyDistribution"></div>
		<div id="buttonHolder">
			<select id="toggleAuthors">
				<option value="<?php echo esc_attr( get_current_user_id() ); ?>">Just Me</option>
				<option value="all">All Authors</option>
			</select>
			<select id="includePostFormats">
				<option value="all">Include all post formats</option>
				<option value="standard">Just standard posts</option>
			</select>
		</div>
		<details id="myPostStatsSummary">
			<summary>Stats</summary>
			<table>
				<thead><th colspan="2">Posts per</th><th colspan="2">Most Active</th></thead>
				<tr>
					<td>Day:</td><td><span id="posts_per_day">0</span></td>
					<td>Day:</td><td><span id="most_active_day">N/A</span></td>
				</tr>
				<tr>
					<td>Week:</td><td><span id="posts_per_week">0</span></td>
					<td>Hour:</td><td><span id="most_active_hour">N/A</span></td>
				</tr>
				<tr>
					<td>Month:</td><td><span id="posts_per_month">0</span></td>
					<td>Year:</td><td><span id="most_active_year">N/A</span></td>
				</tr>
			</table>
		</details>
		<details id="postsList">
			<summary>Posts by Year and Month</summary>
			<div id="postsByMonth"></div>
		</details>
	</div>
	<script>
		document.addEventListener('click', function(e) {
			const target = e.target;
			if (target.dataset.targetId) {
				location.href = '#postsList';
				let details = document.getElementById(target.dataset.targetId);
				if (details) {
					details.scrollIntoView({ behavior: 'smooth' });
					details.open = !details.open;
					while (details.parentElement) {
						details = details.parentElement;
						if (details.tagName === 'DETAILS') {
							details.open = true;
						}
					}

				}
			}
		});

		function fetchPostsData() {
			jQuery.post(ajaxurl, {
				action: 'get_post_data',
				author: document.getElementById('toggleAuthors').value,
				post_formats: document.getElementById('includePostFormats').value,
			}, function(data) {
				if (data.success) {
					const counts = data.data.counts;
					const stats = data.data.stats;

					const postsChart = document.getElementById('postsChart');
					postsChart.innerHTML = '';

					const maxCount = Math.max(...Object.values(counts), 1) / 2;
					let = c = 0;
					for (const dateString in counts) {
						const count = counts[dateString];
						const date = new Date(dateString + '-01');

						const label = date.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });

						const bar = document.createElement('div');
						bar.className = 'post-bar';
						const barWidth = Math.min(430, (count / maxCount * 400)) + 'px';
						if ( ++c < 14 ) {
							bar.style.width = 0;
							setTimeout(function() {
								bar.style.width = barWidth;
							}, 1 );
						} else {
							bar.style.width = barWidth;
						}
						bar.title = `${label}: ${count} posts`;
						bar.dataset.targetId = 'post-stats-' + dateString;

						const monthLabel = document.createElement('span');
						monthLabel.textContent = label;
						monthLabel.className = 'month-label';
						monthLabel.dataset.targetId = 'post-stats-' + dateString;

						bar.appendChild(monthLabel);
						postsChart.appendChild(bar);
					}

					document.getElementById('posts_per_day').innerText = stats.daily;
					document.getElementById('posts_per_week').innerText = stats.weekly;
					document.getElementById('posts_per_month').innerText = stats.monthly;
					document.getElementById('most_active_day').innerText = stats.most_active_day;
					document.getElementById('most_active_hour').innerText = stats.most_active_hour;
					document.getElementById('most_active_year').innerText = stats.most_active_year;

					renderPostsByMonth(data.data.postsByMonth);
					renderHourlyDistribution(data.data.hourlyCounts);
				}
			});
		}

		function renderPostsByMonth(postsByMonth) {
			const postsByMonthDiv = document.getElementById('postsByMonth');
			postsByMonthDiv.innerHTML = '';

			const sortedYears = Object.keys(postsByMonth).sort((a, b) => b - a);

			sortedYears.forEach(year => {
				const yearDiv = document.createElement('details');
				const yearHeader = document.createElement('summary');
				yearHeader.textContent = year;
				yearDiv.appendChild(yearHeader);

				const sortedMonths = Object.keys(postsByMonth[year]).sort((a, b) => new Date(Date.parse(a + " 1, 2018")) - new Date(Date.parse(b + " 1, 2018")));
				sortedMonths.forEach(month => {
					const details = document.createElement('details');
					const summary = document.createElement('summary');
					summary.textContent = month;

					details.id = `post-stats-${year}-` + month.split(' ')[0];

					details.appendChild(summary);

					const ul = document.createElement('ul');
					postsByMonth[year][month].forEach(post => {
						const li = document.createElement('li');
						li.innerHTML = `${post.prefix} <a href="${post.link}" target="_blank">${post.title}</a>`;
						ul.appendChild(li);
					});
					details.appendChild(ul);
					yearDiv.appendChild(details);
				});

				postsByMonthDiv.appendChild(yearDiv);
			});
		}

		function renderHourlyDistribution(hourlyCounts) {
			const hourlyGraph = document.getElementById('hourlyDistribution');
			hourlyGraph.innerHTML = '';

			const maxHourlyCount = Math.max(...Object.values(hourlyCounts));

			for (let hour = 0; hour < 24; hour++) {
				const count = hourlyCounts[hour] || 0;

				const bar = document.createElement('div');
				bar.style.height = 15 + (count / maxHourlyCount * 100) + 'px'; // Scale the bar height
				bar.className = 'hour-bar';
				if ( ! count ) bar.classList.add('empty');
				bar.title = `${hour}:00: ${count} posts`;
				bar.textContent = hour;

				hourlyGraph.appendChild(bar);
			}
		}

		// Event listener for the selects
		document.getElementById('toggleAuthors').addEventListener('change', fetchPostsData);
		document.getElementById('includePostFormats').addEventListener('change', fetchPostsData);

		fetchPostsData();
	</script>
	<style>
		#myPostStatsWidget #toggleAuthors {
			margin: 20px 0;
		}

		#myPostStatsWidget #buttonHolder {
			display: flex;
			align-items: center;
			justify-content: space-between;
		}
		#myPostStatsWidget #postsChart {
			max-height: 275px;
			overflow: auto;
			margin-top: 20px;
		}

		#myPostStatsWidget #postsChart .post-bar {
			height: 20px;
			background-color: rgba(75, 192, 192, 0.7);
			margin: 2px 0;
			position: relative;
			cursor: pointer;
		}
		/* only transition the first 12 items */
		#myPostStatsWidget #postsChart .post-bar:nth-child(-n+14) {
			transition: width 1s ease;
		}

		#myPostStatsWidget #postsChart .post-bar .month-label {
			position: absolute;
			left: 5px;
			color: #000;
			font-size: 12px;
			white-space: nowrap;
		}

		#myPostStatsWidget #hourlyDistribution {
			display: flex;
			flex-direction: row;
			align-items: flex-end;
			margin-top: 20px;
		}

		#myPostStatsWidget #hourlyDistribution .hour-bar {
			background-color: rgba(75, 192, 192, 0.7);
			margin: 2px;
			text-align: center;
			flex-grow: 1;
		}
		#myPostStatsWidget #hourlyDistribution .hour-bar.empty {
			background: none;
			height: 10px;
		}

		#myPostStatsWidget #postsByMonth summary {
			cursor: pointer;
			text-decoration: none;
			color: #000;
		}

		#myPostStatsWidget #myPostStatsSummary p {
			margin: 0;
			margin-left: 10px;
		}
		#myPostStatsWidget > details > summary {
			text-decoration: none;
			color: #000;
		}
		#myPostStatsWidget details details {
			margin-left: 20px;
		}
		#myPostStatsWidget details ul {
			margin: 0;
		}
		#myPostStatsWidget details ul li {
			margin: 0;
			margin-left: 20px;
			margin-bottom: 5px;
		}
		#myPostStatsWidget th {
			text-align: left;
			width: 50%;
		}

	</style>
	<?php
}

function my_post_stats_add_dashboard_widgets() {
	wp_add_dashboard_widget( 'myPostStatsWidget', 'My Post Stats', 'my_post_stats_dashboard_widget' );
}
add_action( 'wp_dashboard_setup', 'my_post_stats_add_dashboard_widgets' );

function my_post_stats_get_post_data() {
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

add_action( 'wp_ajax_get_post_data', 'my_post_stats_get_post_data' );
