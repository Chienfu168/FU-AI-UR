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

			var html = '<div class="ur-ai-market-price-compare">';
			html += renderCard(oldTitle, data.old);
			html += renderCard(newTitle, data.new);
			html += '</div>';

			html += '<p class="ur-ai-market-price-meta">';
			html += escapeHtml('資料來源：內政部不動產交易實價查詢服務');
			if (data.last_imported_at) {
				html += '　' + escapeHtml('資料最後更新：' + String(data.last_imported_at).slice(0, 10));
			}
			html += '</p>';

			if (data.disclaimer) {
				html += '<p class="ur-ai-market-price-disclaimer">' + escapeHtml(data.disclaimer) + '</p>';
			}

			resultBox.innerHTML = html;
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
			html += escapeHtml(getI18n('range_label', '區間')) + '：' + escapeHtml(formatWan(stats.min)) + ' ~ ' + escapeHtml(formatWan(stats.max));
			html += '</p>';
			html += '<p class="ur-ai-market-price-detail">';
			html += escapeHtml(getI18n('sample_count', '樣本 %s 筆').replace('%s', stats.count));
			html += '，' + escapeHtml(getI18n('avg_age', '平均屋齡 %s 年').replace('%s', stats.avg_age));
			html += '</p>';
			html += '</div>';

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
