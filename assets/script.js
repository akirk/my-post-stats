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
		action: 'get_my_post_stats',
		author: document.getElementById('toggleAuthors').value,
		post_formats: document.getElementById('includePostFormats').value,
		 nonce: myPostStats.nonce // Include nonce here
	 }, function(data) {
		if (data.success) {
			const counts = data.data.counts;
			const stats = data.data.stats;

			const postsChart = document.getElementById('postsChart');
			postsChart.innerHTML = '';

			const maxCount = Math.max(...Object.values(counts), 1) / 2;
			let c = 0;
			for (const dateString in counts) {
				const count = counts[dateString];
				const date = new Date(dateString + '-01');

				const label = date.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });

				const bar = document.createElement('div');
				bar.className = 'post-bar';
				const barWidth = Math.min(430, (count / maxCount * 400)) + 'px';
				if (++c < 14) {
					bar.style.width = 0;
					setTimeout(function() {
						bar.style.width = barWidth;
					}, 1);
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
		if (!count) bar.classList.add('empty');
		bar.title = `${hour}:00: ${count} posts`;
		bar.textContent = hour;

		hourlyGraph.appendChild(bar);
	}
}

// Event listener for the selects
document.getElementById('toggleAuthors').addEventListener('change', fetchPostsData);
document.getElementById('includePostFormats').addEventListener('change', fetchPostsData);

fetchPostsData();
