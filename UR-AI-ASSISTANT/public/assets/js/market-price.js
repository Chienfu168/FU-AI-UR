/**
 * 行情參考 前台腳本。
 * 純 vanilla JS，比照 calculator.js 的寫法，不依賴 jQuery。
 */
(function () {
	'use strict';

	if (typeof window.UR_AI_MARKET_PRICE === 'undefined') {
		return;
	}

	var CFG = window.UR_AI_MARKET_PRICE;

	document.addEventListener('DOMContentLoaded', function () {
		var roots = document.querySelectorAll('.ur-ai-market-price');
		Array.prototype.forEach.call(roots, function (root) {
			initMarketPrice(root);
		});
	});

	function initMarketPrice(root) {
		var citySelect = root.querySelector('.ur-ai-market-price-city-select');
		var districtSelect = root.querySelector('.ur-ai-market-price-district-select');
		var form = root.querySelector('.ur-ai-market-price-form');
		var loadingBox = root.querySelector('.ur-ai-market-price-loading');
		var resultBox = root.querySelector('.ur-ai-market-price-result');

		if (!citySelect || !districtSelect || !form) {
			return;
		}

		function filterDistricts() {
			var city = citySelect.value;
			var options = districtSelect.querySelectorAll('option[data-city]');
			var hasSelected = false;

			Array.prototype.forEach.call(options, function (opt) {
				var match = opt.getAttribute('data-city') === city;
				opt.hidden = !match;
				opt.disabled = !match;
				if (match && opt.selected) {
					hasSelected = true;
				}
			});

			if (!hasSelected) {
				districtSelect.value = '';
			}
		}

		citySelect.addEventListener('change', filterDistricts);
		filterDistricts();

		form.addEventListener('submit', function (event) {
			event.preventDefault();

			var city = citySelect.value;
			var district = districtSelect.value;

			if (!district) {
				showError(getI18n('need_district', '請選擇行政區。'));
				return;
			}

			setLoading(true);

			post(CFG.ajax_url, {
				action: CFG.action,
				nonce: CFG.nonce,
				city: city,
				district: district
			})
				.then(function (response) {
					setLoading(false);

					if (!response || !response.success) {
						var message = response && response.data && response.data.message
							? response.data.message
							: getI18n('error', '查詢失敗，請稍後再試。');
						showError(message);
						return;
					}

					renderResult(response.data);
				})
				.catch(function () {
					setLoading(false);
					showError(getI18n('error', '查詢失敗，請稍後再試。'));
				});
		});

		function setLoading(isLoading) {
			if (loadingBox) {
				loadingBox.textContent = isLoading ? getI18n('loading', '查詢中…') : '';
			}
			if (isLoading && resultBox) {
				resultBox.innerHTML = '';
			}
		}

		function showError(message) {
			if (resultBox) {
				resultBox.innerHTML = '<p class="ur-ai-market-price-error">' + escapeHtml(message) + '</p>';
			}
		}

		function renderResult(data) {
			if (!resultBox) {
				return;
			}

			var oldTitle = getI18n('old_title', '老屋現況行情（%s 年以上）').replace('%s', data.old_age_threshold);
			var newTitle = getI18n('new_title', '新成屋行情（%s 年內）').replace('%s', data.new_age_threshold);

			var html = '';

			if (data.uplift_percent !== null && data.uplift_percent !== undefined) {
				html += renderUplift(data.uplift_percent);
			}

			html += '<div class="ur-ai-market-price-compare">';
			html += renderCard(oldTitle, data.old);
			html += renderCard(newTitle, data.new);
			html += '</div>';

			html += '<p class="ur-ai-market-price-meta">';
			html += escapeHtml('資料來源：內政部不動產交易實價查詢服務');
			if (data.total_records) {
				html += '　' + escapeHtml(getI18n('total_records_label', '資料庫累計 %s 筆歷史成交紀錄').replace('%s', formatNumber(data.total_records)));
			}
			if (data.last_imported_at) {
				html += '　' + escapeHtml('資料最後更新：' + String(data.last_imported_at).slice(0, 10));
			}
			html += '</p>';

			if (data.disclaimer) {
				html += '<p class="ur-ai-market-price-disclaimer">' + escapeHtml(data.disclaimer) + '</p>';
			}

			resultBox.innerHTML = html;
		}

		function renderUplift(upliftPercent) {
			var isPositive = upliftPercent >= 0;
			var sign = isPositive ? '+' : '';
			var cls = isPositive ? 'ur-ai-market-price-uplift-up' : 'ur-ai-market-price-uplift-down';
			var text = getI18n('uplift_label', '都更後行情變化約 %s').replace('%s', sign + upliftPercent + '%');

			return '<p class="ur-ai-market-price-uplift ' + cls + '">' + escapeHtml(text) + '</p>';
		}

		function renderCard(title, stats) {
			var html = '<div class="ur-ai-market-price-card">';
			html += '<h3 class="ur-ai-market-price-card-title">' + escapeHtml(title) + '</h3>';

			if (!stats || !stats.sufficient) {
				var count = stats ? stats.count : 0;
				html += '<p class="ur-ai-market-price-insufficient">';
				html += escapeHtml(getI18n('insufficient', '本區樣本數不足，暫不提供統計，建議放寬篩選條件。'));
				html += '（' + escapeHtml(getI18n('sample_count', '樣本 %s 筆').replace('%s', count)) + '）';
				html += '</p>';
				html += '</div>';
				return html;
			}

			html += '<p class="ur-ai-market-price-median">' + escapeHtml(formatWan(stats.median)) + ' ' + escapeHtml(getI18n('per_ping', '每坪')) + '</p>';
			html += '<p class="ur-ai-market-price-detail">';
			html += escapeHtml(getI18n('range_label', '常見區間')) + '：' + escapeHtml(formatWan(stats.range_low)) + ' ~ ' + escapeHtml(formatWan(stats.range_high));
			html += '</p>';
			html += '<p class="ur-ai-market-price-range-note">' + escapeHtml(getI18n('range_note', '（反映同區域內不同樓層、屋況、地點的價格落差，已排除少數極端案例）')) + '</p>';
			html += '<p class="ur-ai-market-price-detail">';
			html += escapeHtml(getI18n('sample_count', '樣本 %s 筆').replace('%s', stats.count));
			html += '，' + escapeHtml(getI18n('avg_age', '平均屋齡 %s 年').replace('%s', stats.avg_age));
			html += '</p>';
			html += renderRecent(stats.recent);
			html += renderExamples(stats.examples);
			html += '</div>';

			return html;
		}

		function renderRecent(recent) {
			if (!recent) {
				return '';
			}

			var html = '<div class="ur-ai-market-price-recent">';
			html += '<p class="ur-ai-market-price-recent-label">' + escapeHtml(getI18n('recent_label', '近一年行情')) + '</p>';
			html += '<p class="ur-ai-market-price-recent-median">' + escapeHtml(formatWan(recent.median)) + ' ' + escapeHtml(getI18n('per_ping', '每坪'));

			if (recent.change_percent !== null && recent.change_percent !== undefined) {
				var isPositive = recent.change_percent >= 0;
				var sign = isPositive ? '+' : '';
				var cls = isPositive ? 'ur-ai-market-price-trend-up' : 'ur-ai-market-price-trend-down';
				html += ' <span class="' + cls + '">（' + escapeHtml(getI18n('trend_label', '近一年成長 %s').replace('%s', sign + recent.change_percent + '%')) + '）</span>';
			}

			html += '</p>';
			html += '<p class="ur-ai-market-price-detail">';
			html += escapeHtml(getI18n('range_label', '常見區間')) + '：' + escapeHtml(formatWan(recent.range_low)) + ' ~ ' + escapeHtml(formatWan(recent.range_high));
			html += '　' + escapeHtml(getI18n('sample_count', '樣本 %s 筆').replace('%s', recent.count));
			html += '</p>';
			html += '</div>';

			return html;
		}

		function renderExamples(examples) {
			if (!examples || !examples.length) {
				return '';
			}

			var html = '<p class="ur-ai-market-price-examples-label">' + escapeHtml(getI18n('examples_label', '參考案例（依單價由低到高）')) + '</p>';
			html += '<ul class="ur-ai-market-price-examples">';

			for (var i = 0; i < examples.length; i++) {
				var ex = examples[i];
				var parts = [];

				var place = ex.district || '';
				if (ex.road_section) {
					place += ex.road_section;
				}
				if (place) {
					parts.push(place);
				}

				var feature = getI18n('example_feature', '屋齡 %1$s 年、%2$s 坪、%3$s')
					.replace('%1$s', ex.building_age_years)
					.replace('%2$s', ex.ping)
					.replace('%3$s', ex.building_type || '');
				parts.push(feature);

				parts.push(getI18n('example_price', '單價約 %s/坪').replace('%s', formatWan(ex.unit_price_per_ping)));

				html += '<li>' + escapeHtml(parts.join('｜')) + '</li>';
			}

			html += '</ul>';

			return html;
		}
	}

	function formatWan(value) {
		var n = parseFloat(value);
		if (isNaN(n)) {
			return '0 萬';
		}
		return (n / 10000).toFixed(1) + ' 萬';
	}

	function formatNumber(value) {
		var n = parseInt(value, 10);
		if (isNaN(n)) {
			return '0';
		}
		return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
	}

	function getI18n(key, fallback) {
		if (CFG.i18n && CFG.i18n[key]) {
			return CFG.i18n[key];
		}
		return fallback;
	}

	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text === null || text === undefined ? '' : String(text);
		return div.innerHTML;
	}

	function post(url, data) {
		var body = Object.keys(data)
			.map(function (k) {
				return encodeURIComponent(k) + '=' + encodeURIComponent(data[k] == null ? '' : data[k]);
			})
			.join('&');

		return fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body
		}).then(function (r) {
			return r.json();
		});
	}
})();
