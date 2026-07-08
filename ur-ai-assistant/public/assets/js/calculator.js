/**
 * 都更分回試算 前台腳本（單一計算模式 + 公式透明拆解）。
 * 純 vanilla JS，不使用任何瀏覽器儲存。
 */
(function () {
	'use strict';

	if (typeof window.UR_AI_CALC === 'undefined') {
		return;
	}

	var CFG = window.UR_AI_CALC;

	document.addEventListener('DOMContentLoaded', function () {
		var roots = document.querySelectorAll('.ur-ai-calc');
		Array.prototype.forEach.call(roots, function (root) {
			initCalc(root);
		});
	});

	function initCalc(root) {
		var resultBox = root.querySelector('[data-calc-result]');
		var errorBox = root.querySelector('[data-calc-error]');

		// 其他獎勵 custom 欄位顯隱。
		['other_bonus_1', 'other_bonus_2'].forEach(function (slot) {
			var sel = root.querySelector('[data-calc="' + slot + '"]');
			var custom = root.querySelector('[data-calc="' + slot + '_custom"]');
			if (!sel || !custom) {
				return;
			}
			sel.addEventListener('change', function () {
				var opt = sel.options[sel.selectedIndex];
				var isCustom = opt && opt.getAttribute('data-custom') === '1';
				custom.style.display = isCustom ? '' : 'none';
				if (!isCustom) {
					custom.value = '';
				}
			});
		});

		// 土地面積輸入方式切換（持分坪數 / 基地面積＋持分比例），立即試算／進階試算共用。
		var shareModeRadios = root.querySelectorAll('input[data-calc="share_mode"]');
		var ratioFields = root.querySelector('[data-calc-ratio-fields]');
		var pingsField = root.querySelector('[data-calc-pings-field]');
		function currentShareMode() {
			var checked = root.querySelector('input[data-calc="share_mode"]:checked');
			return checked ? checked.value : 'pings';
		}
		function applyShareModeVisibility() {
			var isRatio = (currentShareMode() === 'ratio');
			if (ratioFields) {
				ratioFields.hidden = !isRatio;
			}
			if (pingsField) {
				pingsField.hidden = isRatio;
			}
		}
		Array.prototype.forEach.call(shareModeRadios, function (radio) {
			radio.addEventListener('change', applyShareModeVisibility);
		});
		applyShareModeVisibility();

		var buttons = root.querySelectorAll('[data-calc-action="compute"]');
		Array.prototype.forEach.call(buttons, function (btn) {
			btn.addEventListener('click', function () {
				compute(btn.getAttribute('data-track') || 'single');
			});
		});

		var printBtn = root.querySelector('[data-calc-action="print"]');
		if (printBtn) {
			printBtn.addEventListener('click', function () {
				printResult(root);
			});
		}

		var lastTrack = 'single';

		bindShare('share-line', function (url) {
			window.open('https://social-plugins.line.me/lineit/share?url=' + encodeURIComponent(url), '_blank', 'noopener');
		});
		bindShare('share-fb', function (url) {
			window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url), '_blank', 'noopener');
		});
		bindShare('share-copy', function (url) {
			copyText(url, function (ok) {
				showShareHint(ok ? '✓ 連結已複製，可貼到 LINE／FB 社團分享。' : '複製失敗，請手動複製網址列。');
			});
		});

		function bindShare(action, fn) {
			var btn = root.querySelector('[data-calc-action="' + action + '"]');
			if (btn) {
				btn.addEventListener('click', function () {
					fn(buildShareUrl());
				});
			}
		}

		function buildShareUrl() {
			var loc = window.location;
			// 保留頁面原有查詢參數（例如 ?page_id=316），僅移除舊的 urc_* 再附上新的。
			var existing = parseQuery(loc.search);
			var parts = [];
			Object.keys(existing).forEach(function (k) {
				if (k.indexOf('urc') !== 0) {
					parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(existing[k]));
				}
			});
			parts.push('urc=1');
			parts.push('urc_t=' + encodeURIComponent(lastTrack));
			function add(key, v) {
				if (v !== '' && v != null) {
					parts.push(key + '=' + encodeURIComponent(v));
				}
			}
			add('urc_far', val('far'));
			add('urc_zone', val('zone-site'));

			var mode = currentShareMode();
			add('urc_sm', mode);
			if (mode === 'ratio') {
				add('urc_sta', val('site_total_area'));
				add('urc_sn', val('share_numerator'));
				add('urc_sd', val('share_denominator'));
				add('urc_cr', val('coverage_ratio'));
				add('urc_fh', val('floor_height'));
			} else {
				add('urc_area', val('site_area'));
			}

			if (lastTrack === 'advanced') {
				add('urc_fo', val('far_origin'));
				add('urc_bt', val('building_type'));
			} else {
				add('urc_gb', val('general_bonus'));
				add('urc_o1', val('other_bonus_1'));
				add('urc_o1c', val('other_bonus_1_custom'));
				add('urc_o2', val('other_bonus_2'));
				add('urc_o2c', val('other_bonus_2_custom'));
			}
			return loc.origin + loc.pathname + '?' + parts.join('&');
		}

		function showShareHint(msg) {
			var hint = root.querySelector('[data-calc-share-hint]');
			if (!hint) {
				return;
			}
			hint.textContent = msg;
			hint.hidden = false;
		}

		function setField(key, v) {
			var el = root.querySelector('[data-calc="' + key + '"]');
			if (el && v != null) {
				el.value = v;
				if (el.tagName === 'SELECT') {
					var ev = document.createEvent('HTMLEvents');
					ev.initEvent('change', true, false);
					el.dispatchEvent(ev);
				}
			}
		}

		function setShareMode(mode) {
			mode = (mode === 'ratio') ? 'ratio' : 'pings';
			Array.prototype.forEach.call(shareModeRadios, function (radio) {
				radio.checked = (radio.value === mode);
			});
			applyShareModeVisibility();
		}

		// 開啟分享連結時，自動帶入條件並試算。
		function applySharedParams() {
			var qs = window.location.search;
			if (!qs || qs.indexOf('urc=1') === -1) {
				return;
			}
			var params = parseQuery(qs);
			var track = params.urc_t === 'advanced' ? 'advanced' : 'single';

			setField('far', params.urc_far);
			setField('zone-site', params.urc_zone);

			var mode = (params.urc_sm === 'ratio') ? 'ratio' : 'pings';
			setShareMode(mode);

			if (mode === 'ratio') {
				setField('site_total_area', params.urc_sta);
				setField('share_numerator', params.urc_sn);
				setField('share_denominator', params.urc_sd);
				setField('coverage_ratio', params.urc_cr);
				setField('floor_height', params.urc_fh);
			} else {
				setField('site_area', params.urc_area);
			}

			if (track === 'advanced') {
				var adv = root.querySelector('.ur-ai-calc__advanced');
				if (adv) {
					adv.open = true;
				}
				setField('far_origin', params.urc_fo);
				setField('building_type', params.urc_bt || 'normal');
			} else {
				setField('general_bonus', params.urc_gb);
				setField('other_bonus_1', params.urc_o1);
				setField('other_bonus_1_custom', params.urc_o1c);
				setField('other_bonus_2', params.urc_o2);
				setField('other_bonus_2_custom', params.urc_o2c);
			}

			setTimeout(function () {
				compute(track);
			}, 150);
		}

		function val(key) {
			var el = root.querySelector('[data-calc="' + key + '"]');
			return el ? el.value : '';
		}

		function compute(track) {
			hideError();
			track = track || 'single';
			lastTrack = track;

			var data = {
				action: CFG.action,
				nonce: CFG.nonce,
				track: track,
				zone: val('zone-site'),
				far: val('far')
			};

			// 土地面積輸入（共用：立即試算／進階試算都用同一組值）。
			var shareMode = currentShareMode();
			data.share_mode = shareMode;

			if (shareMode === 'ratio') {
				data.site_total_area = val('site_total_area');
				data.share_numerator = val('share_numerator');
				data.share_denominator = val('share_denominator');
				data.coverage_ratio = val('coverage_ratio');
				data.floor_height = val('floor_height');

				if (!data.site_total_area) {
					showError('請輸入基地總面積。');
					return;
				}
				if (!data.share_numerator || !data.share_denominator) {
					showError('請輸入土地持分分子／分母。');
					return;
				}
			} else {
				data.site_area = val('site_area');
				if (!data.site_area) {
					showError('請輸入土地持分坪數。');
					return;
				}
			}

			if (!data.far) {
				showError('請輸入法定容積率（%）。');
				return;
			}

			if (track === 'advanced') {
				data.far_origin = val('far_origin');
				data.building_type = val('building_type');
				if (!data.far_origin) {
					showError('請輸入原建築容積率（%）。');
					return;
				}
			} else {
				data.general_bonus = val('general_bonus');
				data.other_bonus_1 = val('other_bonus_1');
				data.other_bonus_1_custom = val('other_bonus_1_custom');
				data.other_bonus_2 = val('other_bonus_2');
				data.other_bonus_2_custom = val('other_bonus_2_custom');
			}

			setBusy(true);

			post(CFG.ajax_url, data)
				.then(function (json) {
					setBusy(false);
					if (!json || !json.success) {
						showError((json && json.data && json.data.message) || CFG.i18n.error);
						return;
					}
					renderResult(json.data);
				})
				.catch(function () {
					setBusy(false);
					showError(CFG.i18n.error);
				});
		}

		function renderResult(data) {
			var r = data.result || {};
			var beforeEl = root.querySelector('[data-calc-before]');
			var afterEl = root.querySelector('[data-calc-after]');

			if (beforeEl) {
				beforeEl.textContent = fmt(r.has_individual ? r.own_share : r.site_area) + ' 坪';
			}
			if (afterEl) {
				if (r.has_individual) {
					afterEl.textContent = '約 ' + fmt(r.individual_low) + ' ~ ' + fmt(r.individual_high) + ' 坪';
				} else {
					afterEl.textContent = '約 ' + fmt(r.owner_total_low) + ' ~ ' + fmt(r.owner_total_high) + ' 坪';
				}
			}

			renderPaths(r);
			renderMassing(r);
			renderBreakdown(r);

			var dateEl = root.querySelector('[data-calc-date]');
			if (dateEl) {
				var d = new Date();
				dateEl.textContent = '試算日期：' + d.getFullYear() + '/' +
					('0' + (d.getMonth() + 1)).slice(-2) + '/' + ('0' + d.getDate()).slice(-2);
			}

			injectToken(data.token || '');

			if (resultBox) {
				resultBox.hidden = false;
				resultBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			}
		}

		// 三案擇優比較（僅進階）。
		function renderPaths(r) {
			var box = root.querySelector('[data-calc-paths]');
			if (!box) {
				return;
			}
			if (!r.best_key) {
				box.hidden = true;
				box.innerHTML = '';
				return;
			}
			var bPct = pct(r.b_legal_ratio != null ? r.b_legal_ratio : 0.3);
			var rows = [
				{ k: 'A', label: '法定容積 ×' + num(r.a_multiplier != null ? r.a_multiplier : 1.5), v: r.path_a },
				{ k: 'B', label: '原容積 ＋ 法容' + bPct, v: r.path_b },
				{ k: 'C', label: '原容積 ×' + num(r.c_multiplier_used), v: r.path_c }
			];
			var html = '<p class="ur-ai-calc__paths-title">獎勵方案比較・更新後容積樓地板（自動擇優）</p><ul class="ur-ai-calc__paths-list">';
			rows.forEach(function (row) {
				var win = (row.k === r.best_key);
				var capNote = (win && r.capped) ? '（超過上限，採計 ' + fmt(r.post_floor) + ' 坪）' : '';
				html += '<li class="' + (win ? 'is-best' : '') + '">' +
					'<span class="ur-ai-calc__paths-key">' + row.k + '.</span> ' +
					row.label + '　→　<strong>' + fmt(row.v) + ' 坪</strong>' + capNote +
					(win ? ' <span class="ur-ai-calc__paths-badge">★ 最有利</span>' : '') +
					'</li>';
			});
			html += '</ul>';
			html += '<p class="ur-ai-calc__paths-note">※ 上列為容積樓地板，非最終分回坪數；最終分回請見下方試算過程。</p>';
			box.innerHTML = html;
			box.hidden = false;
		}

		// 樓層／高度概估（基地面積＋持分比例模式且填了建蔽率時有值；立即試算／進階試算皆適用）。
		function renderMassing(r) {
			var box = root.querySelector('[data-calc-massing]');
			if (!box) {
				return;
			}
			var m = r.massing;
			if (!m) {
				box.hidden = true;
				box.innerHTML = '';
				return;
			}
			var html = '<p class="ur-ai-calc__massing-title">樓層／高度概估</p>';
			html += '<p class="ur-ai-calc__massing-lead">概估可蓋約 <strong>' + m.floors + ' 層</strong>、高度約 <strong>' + fmt(m.height) + ' 米</strong></p>';
			html += '<ul class="ur-ai-calc__massing-steps">';
			html += '<li>每層樓地板 ＝ 基地總面積 ' + fmt(m.site_area) + ' 坪 × 建蔽率 ' + pct(m.coverage_ratio) + ' ＝ ' + fmt(m.floor_plate) + ' 坪</li>';
			html += '<li>樓層數 ＝ 總樓地板 ' + fmt(m.total_floor) + ' 坪 ÷ 每層樓地板 ' + fmt(m.floor_plate) + ' 坪 ＝ ' + m.floors + ' 層（無條件進位）</li>';
			html += '<li>高度 ＝ ' + m.floors + ' 層 × 單層樓高 ' + num(m.floor_height) + ' 米 ＝ 約 ' + fmt(m.height) + ' 米</li>';
			html += '</ul>';
			html += '<p class="ur-ai-calc__massing-notes">僅供想像，實際以建築設計及都審為準；另須留意限高與建蔽率上限規定。</p>';
			box.innerHTML = html;
			box.hidden = false;
		}

		// 逐步公式拆解。
		function renderBreakdown(r) {
			var list = root.querySelector('[data-calc-breakdown]');
			if (!list) {
				return;
			}
			list.innerHTML = '';

			var steps = [];

			// 進階模式：採用擇優後的容積，往下算。
			if (r.best_key) {
				var siteLabel = r.has_individual ? '基地總面積' : '土地';
				steps.push('基準容積樓地板 ＝ ' + siteLabel + ' ' + fmt(r.site_area) + ' 坪 × 法定容積率 ' + pct(r.far_legal) + ' ＝ ' + fmt(r.base_floor) + ' 坪');
				steps.push('原容積樓地板 ＝ ' + siteLabel + ' ' + fmt(r.site_area) + ' 坪 × 原容積率 ' + pct(r.far_origin) + ' ＝ ' + fmt(r.origin_floor) + ' 坪');
				var pick = '三案擇優 → 採方案 ' + r.best_key + '，更新後容積 ' + fmt(r.post_floor) + ' 坪';
				if (r.capped) {
					pick += '（已達基準容積 ' + num(r.cap_multiplier) + ' 倍上限）';
				}
				steps.push(pick);
				steps.push('× 實設係數 ' + num(r.build_factor) + '（含陽台、公設、車位等免計容積）＝ ' + fmt(r.sellable_floor) + ' 坪（可銷售樓地板）');

				if (r.has_individual) {
					steps.push('× 地主分回比例 ' + pct(r.owner_ratio_low) + ' ~ ' + pct(r.owner_ratio_high) + ' ＝ 全體地主約 ' + fmt(r.owner_total_low) + ' ~ ' + fmt(r.owner_total_high) + ' 坪');
					var shareNote = r.share_ratio_capped ? '（持分比例超過 100%，已以 100% 計算）' : '';
					steps.push('× 個人持分比例（' + fmt(r.own_share) + ' ÷ ' + fmt(r.site_area) + ' ＝ ' + pct(r.share_ratio) + '）' + shareNote + ' ＝ 約 ' + fmt(r.individual_low) + ' ~ ' + fmt(r.individual_high) + ' 坪');
				} else {
					steps.push('× 地主分回比例 ' + pct(r.owner_ratio_low) + ' ~ ' + pct(r.owner_ratio_high) + ' ＝ 約 ' + fmt(r.owner_total_low) + ' ~ ' + fmt(r.owner_total_high) + ' 坪');
				}
				steps.forEach(function (text) { var li = document.createElement('li'); li.textContent = text; list.appendChild(li); });
				return;
			}

			var siteLabel = r.has_individual ? '基地總面積' : '土地';

			steps.push(
				'基準容積樓地板 ＝ ' + siteLabel + ' ' + fmt(r.site_area) + ' 坪 × 容積率 ' + pct(r.far) +
				' ＝ ' + fmt(r.base_floor) + ' 坪'
			);

			var bonusStep = '加計容積獎勵 ＋' + pct(r.effective_bonus) +
				' ＝ ' + fmt(r.bonus_floor) + ' 坪';
			if (r.bonus_capped) {
				bonusStep += '（總獎勵已達基準容積 2 倍上限）';
			}
			steps.push(bonusStep);

			steps.push(
				'× 實設係數 ' + num(r.build_factor) + '（含陽台、公設、車位等免計容積）＝ ' +
				fmt(r.sellable_floor) + ' 坪（可銷售樓地板）'
			);

			if (r.has_individual) {
				steps.push('× 地主分回比例 ' + pct(r.owner_ratio_low) + ' ~ ' + pct(r.owner_ratio_high) + ' ＝ 全體地主約 ' + fmt(r.owner_total_low) + ' ~ ' + fmt(r.owner_total_high) + ' 坪');
				var shareNote2 = r.share_ratio_capped ? '（持分比例超過 100%，已以 100% 計算）' : '';
				steps.push('× 個人持分比例（' + fmt(r.own_share) + ' ÷ ' + fmt(r.site_area) + ' ＝ ' + pct(r.share_ratio) + '）' + shareNote2 + ' ＝ 約 ' + fmt(r.individual_low) + ' ~ ' + fmt(r.individual_high) + ' 坪');
			} else {
				steps.push(
					'× 地主分回比例 ' + pct(r.owner_ratio_low) + ' ~ ' + pct(r.owner_ratio_high) +
					' ＝ 約 ' + fmt(r.owner_total_low) + ' ~ ' + fmt(r.owner_total_high) + ' 坪'
				);
			}

			steps.forEach(function (text) {
				var li = document.createElement('li');
				li.textContent = text;
				list.appendChild(li);
			});
		}

		function injectToken(token) {
			if (!token || !CFG.cf7_form_id) {
				return;
			}
			var form = findCf7Form();
			if (!form) {
				return;
			}
			var input = form.querySelector('input[name="ur_ai_calc_token"]');
			if (!input) {
				input = document.createElement('input');
				input.type = 'hidden';
				input.name = 'ur_ai_calc_token';
				form.appendChild(input);
			}
			input.value = token;
		}

		function findCf7Form() {
			var scope = resultBox || root;
			return scope.querySelector('form.wpcf7-form');
		}

		function setBusy(busy) {
			Array.prototype.forEach.call(buttons, function (btn) {
				btn.disabled = busy;
				if (busy) {
					btn.setAttribute('data-label', btn.textContent);
					btn.textContent = CFG.i18n.calculating;
				} else if (btn.getAttribute('data-label')) {
					btn.textContent = btn.getAttribute('data-label');
				}
			});
		}

		function showError(msg) {
			if (!errorBox) {
				return;
			}
			errorBox.textContent = msg || CFG.i18n.error;
			errorBox.hidden = false;
		}

		function hideError() {
			if (errorBox) {
				errorBox.hidden = true;
				errorBox.textContent = '';
			}
		}

		applySharedParams();
	}

	/* helpers */

	function copyText(text, cb) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function () { cb(true); }, function () { fallbackCopy(text, cb); });
		} else {
			fallbackCopy(text, cb);
		}
	}

	function fallbackCopy(text, cb) {
		try {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.left = '-9999px';
			document.body.appendChild(ta);
			ta.select();
			var ok = document.execCommand('copy');
			document.body.removeChild(ta);
			cb(!!ok);
		} catch (e) {
			cb(false);
		}
	}

	function parseQuery(qs) {
		var out = {};
		qs = qs.replace(/^\?/, '');
		if (!qs) {
			return out;
		}
		qs.split('&').forEach(function (pair) {
			var kv = pair.split('=');
			var k = decodeURIComponent(kv[0] || '');
			var v = decodeURIComponent((kv[1] || '').replace(/\+/g, ' '));
			if (k) {
				out[k] = v;
			}
		});
		return out;
	}

	function printResult(root) {
		var resultBox = root.querySelector('[data-calc-result]');
		if (!resultBox) {
			return;
		}

		var titleEl = root.querySelector('.ur-ai-calc__title');
		var title = titleEl ? titleEl.textContent : '都更分回試算';
		var dateEl = root.querySelector('[data-calc-date]');
		var dateTxt = dateEl ? dateEl.textContent : '';

		// 複製結果，移除留資料區與按鈕等不列印的部分。
		var clone = resultBox.cloneNode(true);
		['.ur-ai-calc__locked', '.ur-ai-calc__result-head', '.ur-ai-calc__print-btn'].forEach(function (sel) {
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

		// 等內容繪製完成再列印。
		if (iframe.contentWindow.document.readyState === 'complete') {
			setTimeout(fire, 250);
		} else {
			iframe.onload = function () { setTimeout(fire, 250); };
			setTimeout(fire, 600); // 後備
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
		'body{margin:0;font-family:-apple-system,\'PingFang TC\',\'Microsoft JhengHei\',sans-serif;color:#1f2937;font-size:11px;line-height:1.45;}' +
		'.pt-title{font-size:16px;font-weight:700;margin:0 0 2px;}' +
		'.pt-date{font-size:9px;color:#6b7280;margin:0 0 8px;}' +
		'.ur-ai-calc__compare{display:flex;gap:8px;align-items:stretch;margin-bottom:8px;}' +
		'.ur-ai-calc__compare-box{flex:1;padding:8px;border-radius:6px;text-align:center;}' +
		'.ur-ai-calc__compare-box--before{background:#f1f5f9;}' +
		'.ur-ai-calc__compare-box--after{background:#ecfdf5;border:1px solid #6ee7b7;}' +
		'.ur-ai-calc__compare-label{display:block;font-size:9px;color:#6b7280;margin-bottom:2px;}' +
		'.ur-ai-calc__compare-value{display:block;font-size:13px;font-weight:700;}' +
		'.ur-ai-calc__compare-box--after .ur-ai-calc__compare-value{color:#047857;}' +
		'.ur-ai-calc__compare-arrow{display:flex;align-items:center;color:#9ca3af;}' +
		'.ur-ai-calc__breakdown{padding:8px 10px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;margin-bottom:8px;}' +
		'.ur-ai-calc__breakdown-title{margin:0 0 4px;font-weight:700;color:#1e3a8a;}' +
		'.ur-ai-calc__breakdown-list{margin:0;padding-left:16px;color:#1e40af;}' +
		'.ur-ai-calc__breakdown-list li{margin-bottom:2px;}' +
		'.ur-ai-calc__paths{padding:8px 10px;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:6px;margin-bottom:8px;}' +
		'.ur-ai-calc__paths-title{margin:0 0 4px;font-weight:700;color:#5b21b6;}' +
		'.ur-ai-calc__paths-list{list-style:none;margin:0;padding:0;color:#4c1d95;line-height:1.55;}' +
		'.ur-ai-calc__paths-list li{padding:1px 4px;border-radius:4px;}' +
		'.ur-ai-calc__paths-list li.is-best{background:#ede9fe;font-weight:600;}' +
		'.ur-ai-calc__paths-badge{display:inline-block;margin-left:4px;padding:1px 5px;background:#7c3aed;color:#fff;font-size:9px;font-weight:700;border-radius:4px;}' +
		'.ur-ai-calc__paths-note{margin:5px 0 0;font-size:10px;line-height:1.5;color:#6b21a8;}' +
		'.ur-ai-calc__massing{padding:8px 10px;background:#ecfeff;border:1px solid #a5f3fc;border-radius:6px;margin-bottom:8px;}' +
		'.ur-ai-calc__massing-title{margin:0 0 4px;font-weight:700;color:#0e7490;}' +
		'.ur-ai-calc__massing-lead{margin:0 0 5px;font-size:11px;color:#155e75;}' +
		'.ur-ai-calc__massing-steps{margin:0 0 5px;padding-left:16px;color:#0e7490;}' +
		'.ur-ai-calc__massing-steps li{margin-bottom:2px;}' +
		'.ur-ai-calc__massing-notes{margin:0;font-size:10px;line-height:1.5;color:#64748b;}' +
		'.ur-ai-calc__public-notice{padding:7px 10px;background:#fff7ed;border:1px solid #fb923c;border-radius:6px;margin-bottom:8px;}' +
		'.ur-ai-calc__public-notice-badge{background:#ea580c;color:#fff;padding:1px 6px;border-radius:4px;font-size:9px;font-weight:700;margin-right:5px;}' +
		'.ur-ai-calc__public-notice-text{font-weight:600;color:#9a3412;}' +
		'.ur-ai-calc__factors{padding:7px 10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;margin-bottom:8px;}' +
		'.ur-ai-calc__factors-title{margin:0 0 3px;font-weight:700;}' +
		'.ur-ai-calc__factors-text{margin:0;font-size:10px;line-height:1.5;color:#475569;}' +
		'.ur-ai-calc__disclaimer-box{padding:7px 10px;background:#fef2f2;border:1px solid #f87171;border-radius:6px;}' +
		'.ur-ai-calc__disclaimer-badge{display:inline-block;background:#dc2626;color:#fff;padding:1px 6px;border-radius:4px;font-size:9px;font-weight:700;margin-bottom:3px;}' +
		'.ur-ai-calc__disclaimer-text{margin:0;font-size:10px;line-height:1.5;font-weight:600;color:#991b1b;}' +
		'.ur-ai-calc__print-footer{display:block;margin-top:10px;padding-top:6px;border-top:1px solid #cbd5e1;font-size:9px;color:#6b7280;text-align:center;}' +
		'.ur-ai-calc__locked,.ur-ai-calc__print-btn{display:none;}';
	}

	function fmt(n) {
		n = parseFloat(n);
		if (isNaN(n)) {
			return '0.00';
		}
		return n.toFixed(2);
	}

	function num(n) {
		n = parseFloat(n);
		if (isNaN(n)) {
			return '0';
		}
		return n.toFixed(2).replace(/\.?0+$/, '');
	}

	// 小數比例 → 百分比字串（0.225 → 22.5%，0.5 → 50%）。
	function pct(n) {
		n = parseFloat(n);
		if (isNaN(n)) {
			return '0%';
		}
		return (Math.round(n * 1000) / 10).toString().replace(/\.0$/, '') + '%';
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
