/**
 * 相關產業名錄 前台腳本：即時搜尋 + 分類 + 縣市篩選（純 vanilla JS）。
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var roots = document.querySelectorAll('.ur-ai-partners');
		Array.prototype.forEach.call(roots, function (root) {
			initPartners(root);
		});
	});

	function initPartners(root) {
		var searchEl = root.querySelector('[data-partners="search"]');
		var cityEl = root.querySelector('[data-partners="city"]');
		var catWrap = root.querySelector('[data-partners="cats"]');
		var grid = root.querySelector('[data-partners="grid"]');
		var noresult = root.querySelector('[data-partners="noresult"]');

		if (!grid) {
			return;
		}

		var cols = Array.prototype.slice.call(grid.querySelectorAll('.ur-ai-partners__col'));
		var state = { q: '', cat: '', city: '' };

		function apply() {
			var q = state.q.trim().toLowerCase();
			var shown = 0;

			cols.forEach(function (col) {
				var okCat = !state.cat || col.getAttribute('data-cat') === state.cat;
				var okCity = !state.city || col.getAttribute('data-city') === state.city;
				var okQ = !q || (col.getAttribute('data-search') || '').indexOf(q) !== -1;
				var visible = okCat && okCity && okQ;
				col.hidden = !visible;
				if (visible) {
					shown++;
				}
			});

			if (noresult) {
				noresult.hidden = shown !== 0;
			}
		}

		if (searchEl) {
			searchEl.addEventListener('input', function () {
				state.q = searchEl.value || '';
				apply();
			});
		}

		if (cityEl) {
			cityEl.addEventListener('change', function () {
				state.city = cityEl.value || '';
				apply();
			});
		}

		if (catWrap) {
			var btns = Array.prototype.slice.call(catWrap.querySelectorAll('.ur-ai-partners__cat-btn'));
			btns.forEach(function (btn) {
				btn.addEventListener('click', function () {
					state.cat = btn.getAttribute('data-cat') || '';
					btns.forEach(function (b) {
						b.classList.toggle('is-active', b === btn);
					});
					apply();
				});
			});
		}
	}
})();
