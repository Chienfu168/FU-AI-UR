/**
 * 稅賦試算（土地增值稅／契稅） 前台腳本。
 * 純 vanilla JS，比照 calculator.js／market-price.js 的寫法，不依賴 jQuery。
 */
(function () {
	'use strict';

	if (typeof window.UR_AI_TAX_CALC === 'undefined') {
		return;
	}

	var CFG = window.UR_AI_TAX_CALC;

	document.addEventListener('DOMContentLoaded', function () {
		var roots = document.querySelectorAll('.ur-ai-tax-calc');
		Array.prototype.forEach.call(roots, function (root) {
			initTaxCalc(root);
		});
	});

	function initTaxCalc(root) {
		var tabs = root.querySelectorAll('[data-tax-tab]');
		var panels = root.querySelectorAll('[data-tax-panel]');
		var buttons = root.querySelectorAll('[data-tax-action="compute"]');
		var resultBox = root.querySelector('[data-tax-result]');
		var errorBox = root.querySelector('[data-tax-error]');

		Array.prototype.forEach.call(tabs, function (tab) {
			tab.addEventListener('click', function () {
				var target = tab.getAttribute('data-tax-tab');

				Array.prototype.forEach.call(tabs, function (t) {
					t.classList.toggle('is-active', t === tab);
				});
				Array.prototype.forEach.call(panels, function (p) {
					p.hidden = p.getAttribute('data-tax-panel') !== target;
				});

				hide(resultBox);
				hide(errorBox);
			});
		});

		Array.prototype.forEach.call(buttons, function (btn) {
			btn.addEventListener('click', function () {
				var calcType = btn.getAttribute('data-calc-type');
				compute(root, calcType, btn, resultBox, errorBox);
			});
		});
	}

	function compute(root, calcType, btn, resultBox, errorBox) {
		hide(resultBox);
		hide(errorBox);

		var data = { action: CFG.action, nonce: CFG.nonce, calc_type: calcType };

		if ('deed_tax' === calcType) {
			data.declared_value = readValue(root, 'declared_value');
			data.transfer_type = readValue(root, 'transfer_type');
			data.reduction_scenario = readValue(root, 'reduction_scenario_deed');
		} else {
			data.land_type = getRadioValue(root, 'land_type');
			data.self_use = readChecked(root, 'self_use') ? 1 : 0;
			data.area = readValue(root, 'area');
			data.share_numerator = readValue(root, 'share_numerator');
			data.share_denominator = readValue(root, 'share_denominator');
			data.current_value = readValue(root, 'current_value');
			data.original_value = readValue(root, 'original_value');
			data.cpi_percent = readValue(root, 'cpi_percent');
			data.holding_years = readValue(root, 'holding_years');
			data.land_value_tax_credit = readValue(root, 'land_value_tax_credit');
			data.reduction_scenario = readValue(root, 'reduction_scenario');
		}

		var originalText = btn.textContent;
		btn.disabled = true;
		btn.textContent = CFG.i18n.calculating;

		post(CFG.ajax_url, data)
			.then(function (res) {
				btn.disabled = false;
				btn.textContent = originalText;

				if (!res || !res.success) {
					showError(errorBox, (res && res.data && res.data.message) || CFG.i18n.error);
					return;
				}

				renderResult(resultBox, res.data);
			})
			.catch(function () {
				btn.disabled = false;
				btn.textContent = originalText;
				showError(errorBox, CFG.i18n.error);
			});
	}

	function renderResult(resultBox, data) {
		if (!resultBox) {
			return;
		}

		var labelEl = resultBox.querySelector('[data-tax-result-label]');
		var valueEl = resultBox.querySelector('[data-tax-result-value]');
		var noteEl = resultBox.querySelector('[data-tax-reduction-note]');
		var listEl = resultBox.querySelector('[data-tax-breakdown]');

		if (labelEl) {
			labelEl.textContent = 'deed_tax' === data.calc_type ? '契稅應納稅額' : '土地增值稅應納稅額';
		}
		if (valueEl) {
			valueEl.textContent = formatMoney(data.final_tax) + ' 元';
		}

		if (noteEl) {
			var reduction = data.reduction || {};
			if (reduction.scenario && 'none' !== reduction.scenario) {
				noteEl.hidden = false;
				noteEl.textContent = reduction.note || '';
				noteEl.classList.toggle('is-ineligible', !reduction.eligible);
			} else {
				hide(noteEl);
			}
		}

		if (listEl) {
			listEl.innerHTML = '';
			(data.notes || []).forEach(function (note) {
				var li = document.createElement('li');
				li.textContent = note;
				listEl.appendChild(li);
			});

			if (data.reduction && data.reduction.eligible) {
				var li2 = document.createElement('li');
				if (data.reduction.exempt) {
					li2.textContent = '套用「' + data.reduction.label + '」：免徵，應納稅額為 0 元。';
				} else {
					li2.textContent = '套用「' + data.reduction.label + '」：一般稅額 ' + formatMoney(data.base_tax) + ' 元 × (1 － ' + Math.round(data.reduction.rate * 100) + '%) = ' + formatMoney(data.final_tax) + ' 元。';
				}
				listEl.appendChild(li2);
			}
		}

		show(resultBox);
	}

	function readValue(root, key) {
		var el = root.querySelector('[data-tax="' + key + '"]');
		return el ? el.value : '';
	}

	function readChecked(root, key) {
		var el = root.querySelector('[data-tax="' + key + '"]');
		return !!(el && el.checked);
	}

	function getRadioValue(root, key) {
		var el = root.querySelector('[data-tax="' + key + '"]:checked');
		return el ? el.value : '';
	}

	function formatMoney(n) {
		var num = Math.round(Number(n) || 0);
		return num.toLocaleString('zh-Hant-TW');
	}

	function showError(box, message) {
		if (!box) {
			return;
		}
		box.textContent = message;
		show(box);
	}

	function show(el) {
		if (el) {
			el.hidden = false;
		}
	}

	function hide(el) {
		if (el) {
			el.hidden = true;
		}
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
