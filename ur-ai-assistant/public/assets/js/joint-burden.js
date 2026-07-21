/**
 * 都市更新共同負擔提列估算 前台腳本（新北市，第一階段）。
 * 純 vanilla JS，比照 tax-calculator.js 的寫法，不依賴 jQuery。
 */
(function () {
	'use strict';

	if (typeof window.UR_AI_JOINT_BURDEN === 'undefined') {
		return;
	}

	var CFG = window.UR_AI_JOINT_BURDEN;

	var FIELDS = [
		'structure', 'floors_above', 'floors_below', 'total_floor_area_ping', 'household_count',
		'demolition_structure', 'demolition_area', 'surcharge_rate', 'price_index_base', 'price_index_current',
		'unit_area_sqm', 'rights_holders', 'main_building_parcels_before', 'land_parcels',
		'main_building_parcels_after', 'boundary_survey_parcels', 'drilling_holes',
		'land_current_value_total', 'base_site_area_sqm', 'door_count', 'owner_count',
		'own_capital_ratio', 'postal_rate', 'bank_rate',
		'design_fee', 'construction_mgmt_fee', 'public_facility_fee', 'condo_fund',
		'demolition_compensation', 'relocation_fee', 'other_c_fee', 'planning_extra_wan',
		'trust_fee', 'trust_fee_type', 'b_cost', 'g_cost', 'h_cost',
		'post_renewal_total_value', 'allocated_value', 'business_tax_method',
		'house_assessed_value', 'land_announced_value_for_tax', 'public_facility_land_burden'
	];

	document.addEventListener('DOMContentLoaded', function () {
		var roots = document.querySelectorAll('.ur-ai-jb');
		Array.prototype.forEach.call(roots, function (root) {
			initJb(root);
		});
	});

	function initJb(root) {
		var computeBtn = root.querySelector('[data-jb-action="compute"]');
		var resultBox = root.querySelector('[data-jb-result]');
		var errorBox = root.querySelector('[data-jb-error]');

		if (computeBtn) {
			computeBtn.addEventListener('click', function () {
				compute(root, computeBtn, resultBox, errorBox);
			});
		}

		var printBtn = root.querySelector('[data-jb-action="print"]');
		if (printBtn) {
			printBtn.addEventListener('click', function () {
				printResult(root);
			});
		}
	}

	function compute(root, btn, resultBox, errorBox) {
		hide(resultBox);
		hide(errorBox);

		var data = { action: CFG.action, nonce: CFG.nonce };

		FIELDS.forEach(function (key) {
			data[key] = readValue(root, key);
		});
		data.top_down_construction = readChecked(root, 'top_down_construction') ? 1 : 0;

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

		var dateEl = resultBox.querySelector('[data-jb-date]');
		if (dateEl) {
			dateEl.textContent = formatDate(new Date());
		}

		var labelEl = resultBox.querySelector('[data-jb-subtotal-label]');
		if (labelEl) {
			labelEl.textContent = data.has_total_value ? '共同負擔總額' : '共同負擔（不含營業稅）';
		}

		var subtotalEl = resultBox.querySelector('[data-jb-subtotal]');
		if (subtotalEl) {
			subtotalEl.textContent = formatMoney(data.subtotal) + ' 元';
		}

		var ratioEl = resultBox.querySelector('[data-jb-ratio]');
		if (ratioEl) {
			if (data.has_total_value && data.burden_ratio != null) {
				ratioEl.hidden = false;
				ratioEl.textContent = '共同負擔比率 ' + (data.burden_ratio * 100).toFixed(2) + '%（÷ 更新後總權利價值 ' + formatMoney(data.post_renewal_total_value) + ' 元）';
			} else {
				ratioEl.hidden = true;
			}
		}

		var groupsEl = resultBox.querySelector('[data-jb-groups]');
		if (groupsEl) {
			groupsEl.innerHTML = '';
			groupsEl.appendChild(buildGroup('工程費用 A', data.a_items, data.a_total));
			groupsEl.appendChild(buildGroup('權利變換費用 C', data.c_items, data.c_total));
			groupsEl.appendChild(buildLoanGroup('貸款利息 D', data.d_detail, data.d_total));
			if (data.e_items) {
				groupsEl.appendChild(buildGroup('稅捐 E（印花稅／營業稅）', data.e_items, data.e_total));
			}
			groupsEl.appendChild(buildGroup('管理費用 F（F1／F2／F3／F4／F5）', data.f_items, data.f_total));

			var ghItems = (data.gh_items || []).filter(function (it) { return Number(it.amount) > 0; });
			if (ghItems.length) {
				groupsEl.appendChild(buildGroup('其他費用 B／G／H', ghItems, data.b_cost + data.g_cost + data.h_cost));
			}
		}

		var notesEl = resultBox.querySelector('[data-jb-notes]');
		if (notesEl) {
			notesEl.innerHTML = '';
			(data.notes || []).forEach(function (note) {
				var p = document.createElement('p');
				p.className = 'ur-ai-jb__note-line';
				p.textContent = '※ ' + note;
				notesEl.appendChild(p);
			});
		}

		show(resultBox);
	}

	function buildGroup(title, items, total) {
		var wrap = document.createElement('div');
		wrap.className = 'ur-ai-jb__group';

		var head = document.createElement('div');
		head.className = 'ur-ai-jb__group-head';
		head.innerHTML = '<span class="ur-ai-jb__group-title">' + escapeHtml(title) + '</span>' +
			'<span class="ur-ai-jb__group-total">' + formatMoney(total) + ' 元</span>';
		wrap.appendChild(head);

		var list = document.createElement('ul');
		list.className = 'ur-ai-jb__item-list';

		(items || []).forEach(function (item) {
			var amount = Number(item.amount) || 0;
			// 個案選填項目金額為 0 時不顯示，避免雜訊。
			if (!item.auto && amount === 0) {
				return;
			}
			var li = document.createElement('li');
			li.className = 'ur-ai-jb__item' + (item.auto ? '' : ' is-manual');

			var row = document.createElement('div');
			row.className = 'ur-ai-jb__item-row';
			row.innerHTML = '<span class="ur-ai-jb__item-label">' + escapeHtml(item.label) + '</span>' +
				'<span class="ur-ai-jb__item-amount">' + formatMoney(amount) + ' 元</span>';
			li.appendChild(row);

			if (item.note) {
				var note = document.createElement('div');
				note.className = 'ur-ai-jb__item-note';
				note.textContent = item.note;
				li.appendChild(note);
			}
			list.appendChild(li);
		});

		wrap.appendChild(list);
		return wrap;
	}

	function buildLoanGroup(title, detail, total) {
		var wrap = document.createElement('div');
		wrap.className = 'ur-ai-jb__group';

		var head = document.createElement('div');
		head.className = 'ur-ai-jb__group-head';
		head.innerHTML = '<span class="ur-ai-jb__group-title">' + escapeHtml(title) + '</span>' +
			'<span class="ur-ai-jb__group-total">' + formatMoney(total) + ' 元</span>';
		wrap.appendChild(head);

		if (detail && detail.notes) {
			var list = document.createElement('ul');
			list.className = 'ur-ai-jb__item-list';
			detail.notes.forEach(function (n) {
				var li = document.createElement('li');
				li.className = 'ur-ai-jb__item';
				var note = document.createElement('div');
				note.className = 'ur-ai-jb__item-note';
				note.textContent = n;
				li.appendChild(note);
				list.appendChild(li);
			});
			wrap.appendChild(list);
		}
		return wrap;
	}

	function readValue(root, key) {
		var el = root.querySelector('[data-jb="' + key + '"]');
		return el ? el.value : '';
	}

	function readChecked(root, key) {
		var el = root.querySelector('[data-jb="' + key + '"]');
		return !!(el && el.checked);
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
	 * 友善列印：比照 tax-calculator.js，用隱藏 iframe 印出結果區的乾淨副本
	 * （去掉列印按鈕），避免把整個輸入表單一起印出來。
	 */
	function printResult(root) {
		var resultBox = root.querySelector('[data-jb-result]');
		if (!resultBox) {
			return;
		}

		var title = '都市更新共同負擔提列估算（新北市）';
		var dateEl = resultBox.querySelector('[data-jb-date]');
		var dateTxt = dateEl ? dateEl.textContent : '';

		var clone = resultBox.cloneNode(true);
		['.ur-ai-jb__result-head'].forEach(function (sel) {
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
		'.ur-ai-jb__result-final{display:flex;align-items:baseline;justify-content:space-between;padding:8px 10px;background:#eff6ff;border-radius:6px;margin-bottom:10px;}' +
		'.ur-ai-jb__result-label{font-weight:600;}' +
		'.ur-ai-jb__result-value{font-size:15px;font-weight:700;color:#1d4ed8;}' +
		'.ur-ai-jb__ratio{padding:5px 9px;margin-bottom:8px;border-radius:6px;background:#ecfdf5;color:#065f46;font-weight:700;}' +
		'.ur-ai-jb__result-caution{padding:5px 9px;margin:0 0 8px;border-left:3px solid #dc2626;background:#fef2f2;color:#991b1b;font-weight:600;font-size:10px;line-height:1.5;}' +
		'.ur-ai-jb__group{margin-bottom:10px;page-break-inside:avoid;}' +
		'.ur-ai-jb__group-head{display:flex;justify-content:space-between;font-weight:700;border-bottom:1.5px solid #cbd5e1;padding-bottom:2px;margin-bottom:4px;}' +
		'.ur-ai-jb__group-total{color:#1d4ed8;}' +
		'.ur-ai-jb__item-list{list-style:none;margin:0;padding:0;}' +
		'.ur-ai-jb__item{margin-bottom:5px;}' +
		'.ur-ai-jb__item-row{display:flex;justify-content:space-between;font-weight:600;}' +
		'.ur-ai-jb__item-note{font-size:9px;color:#6b7280;margin-top:1px;}' +
		'.ur-ai-jb__note-line{font-size:9px;color:#6b7280;margin:2px 0;}' +
		'.ur-ai-jb__disclaimer-box{padding:7px 10px;margin-top:8px;background:#fef2f2;border:1px solid #f87171;border-radius:6px;}' +
		'.ur-ai-jb__disclaimer-badge{display:inline-block;background:#dc2626;color:#fff;padding:1px 6px;border-radius:4px;font-size:9px;font-weight:700;margin-bottom:3px;}' +
		'.ur-ai-jb__disclaimer-text{margin:0;font-size:10px;line-height:1.5;font-weight:600;color:#991b1b;}' +
		'.ur-ai-jb__print-footer{display:block;margin-top:10px;padding-top:6px;border-top:1px solid #cbd5e1;font-size:9px;color:#6b7280;text-align:center;}';
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
