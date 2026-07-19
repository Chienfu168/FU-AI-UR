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

		var printBtn = root.querySelector('[data-tax-action="print"]');
		if (printBtn) {
			printBtn.addEventListener('click', function () {
				printResult(root);
			});
		}
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
		var dateEl = resultBox.querySelector('[data-tax-date]');

		if (dateEl) {
			dateEl.textContent = formatDate(new Date());
		}

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

	function formatDate(d) {
		return d.getFullYear() + '/' + pad(d.getMonth() + 1) + '/' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
	}

	function pad(n) {
		return n < 10 ? '0' + n : String(n);
	}

	/*
	 * 友善列印：比照 calculator.js 的做法，用隱藏 iframe 印出結果區的
	 * 乾淨副本（去掉列印按鈕本身），而不是直接呼叫 window.print()——
	 * 避免把整個頁面（分頁按鈕、輸入表單、其他分頁的隱藏欄位）都印出來。
	 */
	function printResult(root) {
		var resultBox = root.querySelector('[data-tax-result]');
		if (!resultBox) {
			return;
		}

		var labelEl = resultBox.querySelector('[data-tax-result-label]');
		var title = labelEl && labelEl.textContent ? labelEl.textContent : '稅賦試算';
		var dateEl = resultBox.querySelector('[data-tax-date]');
		var dateTxt = dateEl ? dateEl.textContent : '';

		var clone = resultBox.cloneNode(true);
		['.ur-ai-tax-calc__result-head'].forEach(function (sel) {
			var el = clone.querySelector(sel);
			if (el && el.parentNode) {
				el.parentNode.removeChild(el);
			}
		});

		var css = printStyles();
		var doc = '<!DOCTYPE html><html lang="zh-Hant"><head><meta charset="utf-8">' +
			'<title>' + escapeHtml(title) + '</title><style>' + css + '</style></head><body>' +
			'<h1 class="pt-title">' + escapeHtml(title) + '</h1>' +
			(dateTxt ? '<p class="pt-date">' + escapeHtml(dateTxt) + '</p>' : '') +
			clone.innerHTML +
			'</body></html>';

		var iframe = document.createElement('iframe');
		iframe.setAttribute('aria-hidden', 'true');
		iframe.style.position = 'fixed';
		iframe.style.right = '0';
		iframe.style.bottom = '0';
		iframe.style.width = '0';
		iframe.style.height = '0';
		iframe.style.border = '0';
		document.body.appendChild(iframe);

		var idoc = iframe.contentWindow.document;
		idoc.open();
		idoc.write(doc);
		idoc.close();

		var done = false;
		function fire() {
			if (done) {
				return;
			}
			done = true;
			try {
				iframe.contentWindow.focus();
				iframe.contentWindow.print();
			} catch (e) {}
			setTimeout(function () {
				if (iframe.parentNode) {
					iframe.parentNode.removeChild(iframe);
				}
			}, 1000);
		}

		if (iframe.contentWindow.document.readyState === 'complete') {
			setTimeout(fire, 250);
		} else {
			iframe.onload = function () { setTimeout(fire, 250); };
			setTimeout(fire, 600);
		}
	}

	function escapeHtml(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function printStyles() {
		return '' +
		'@page{margin:1cm;}' +
		'*{box-sizing:border-box;}' +
		'body{margin:0;font-family:-apple-system,\'PingFang TC\',\'Microsoft JhengHei\',sans-serif;color:#1f2937;font-size:11px;line-height:1.5;}' +
		'.pt-title{font-size:16px;font-weight:700;margin:0 0 2px;}' +
		'.pt-date{font-size:9px;color:#6b7280;margin:0 0 8px;}' +
		'.ur-ai-tax-calc__result-final{display:flex;align-items:baseline;justify-content:space-between;padding:8px 10px;background:#eff6ff;border-radius:6px;margin-bottom:8px;}' +
		'.ur-ai-tax-calc__result-label{font-weight:600;}' +
		'.ur-ai-tax-calc__result-value{font-size:15px;font-weight:700;color:#1d4ed8;}' +
		'.ur-ai-tax-calc__reduction-note{padding:6px 9px;margin-bottom:8px;border-radius:6px;background:#ecfdf5;color:#065f46;}' +
		'.ur-ai-tax-calc__reduction-note.is-ineligible{background:#fef3c7;color:#92400e;}' +
		'.ur-ai-tax-calc__breakdown-title{margin:0 0 4px;font-weight:700;}' +
		'.ur-ai-tax-calc__breakdown-list{margin:0 0 8px;padding-left:16px;}' +
		'.ur-ai-tax-calc__breakdown-list li{margin-bottom:3px;}' +
		'.ur-ai-tax-calc__disclaimer-box{padding:7px 10px;background:#fef2f2;border:1px solid #f87171;border-radius:6px;}' +
		'.ur-ai-tax-calc__disclaimer-badge{display:inline-block;background:#dc2626;color:#fff;padding:1px 6px;border-radius:4px;font-size:9px;font-weight:700;margin-bottom:3px;}' +
		'.ur-ai-tax-calc__disclaimer-text{margin:0;font-size:10px;line-height:1.5;font-weight:600;color:#991b1b;}' +
		'.ur-ai-tax-calc__print-footer{display:block;margin-top:10px;padding-top:6px;border-top:1px solid #cbd5e1;font-size:9px;color:#6b7280;text-align:center;}';
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
