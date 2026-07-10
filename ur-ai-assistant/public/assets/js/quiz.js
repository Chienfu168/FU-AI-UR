/**
 * 知識大考驗 前台腳本。
 * 純 vanilla JS，比照 market-price.js 的寫法，不依賴 jQuery。
 *
 * 重要：正確答案永遠不會出現在這支檔案能取得的任何資料裡。
 * 開始挑戰時，伺服器只回傳題目與選項文字；每一題選了什麼，會先存在
 * 本機記憶體，等使用者按下「送出成績」，才一次送給伺服器計分。
 * 也因此，作答過程中不會即時顯示「答對／答錯」，會在最後結算才揭曉，
 * 這與真實考試的體驗一致，也避免任何提前洩漏正確答案的管道。
 */
(function () {
	'use strict';

	if (typeof window.UR_AI_QUIZ === 'undefined') {
		return;
	}

	var CFG = window.UR_AI_QUIZ;

	document.addEventListener('DOMContentLoaded', function () {
		var roots = document.querySelectorAll('.ur-ai-quiz');
		Array.prototype.forEach.call(roots, function (root) {
			initQuiz(root);
		});
	});

	function initQuiz(root) {
		var startButton = root.querySelector('.ur-ai-quiz-start-button');

		if (!startButton) {
			return;
		}

		var state = {
			token: '',
			questions: [],
			currentIndex: 0,
			answers: {},
			startedAt: 0
		};

		var els = {
			intro: root.querySelector('[data-state="intro"]'),
			play: root.querySelector('[data-state="play"]'),
			nickname: root.querySelector('[data-state="nickname"]'),
			result: root.querySelector('[data-state="result"]'),
			loading: root.querySelector('.ur-ai-quiz-loading'),
			error: root.querySelector('.ur-ai-quiz-error'),
			progressFill: root.querySelector('.ur-ai-quiz-progress-fill'),
			progressLabel: root.querySelector('.ur-ai-quiz-progress-label'),
			questionText: root.querySelector('.ur-ai-quiz-question-text'),
			options: root.querySelector('.ur-ai-quiz-options'),
			nextButton: root.querySelector('.ur-ai-quiz-next-button'),
			nicknameInput: root.querySelector('.ur-ai-quiz-nickname-input'),
			submitButton: root.querySelector('.ur-ai-quiz-submit-button'),
			resultScore: root.querySelector('.ur-ai-quiz-result-score'),
			resultDetail: root.querySelector('.ur-ai-quiz-result-detail'),
			resultStatus: root.querySelector('.ur-ai-quiz-result-status'),
			retryButton: root.querySelector('.ur-ai-quiz-retry-button')
		};

		startButton.addEventListener('click', handleStart);

		if (els.nextButton) {
			els.nextButton.addEventListener('click', handleNext);
		}

		if (els.submitButton) {
			els.submitButton.addEventListener('click', handleSubmit);
		}

		if (els.retryButton) {
			els.retryButton.addEventListener('click', handleRetry);
		}

		function handleStart() {
			showLoading(CFG.i18n.loading);
			hideError();

			post(CFG.ajax_url, {
				action: CFG.action_start,
				nonce: CFG.nonce
			}).then(function (res) {
				hideLoading();

				if (!res || !res.success) {
					showError(res && res.data && res.data.message ? res.data.message : CFG.i18n.error);
					return;
				}

				state.token = res.data.token;
				state.questions = res.data.questions || [];
				state.currentIndex = 0;
				state.answers = {};
				state.startedAt = Date.now();

				if (!state.questions.length) {
					showError(CFG.i18n.error);
					return;
				}

				setState('play');
				renderQuestion();
			}).catch(function () {
				hideLoading();
				showError(CFG.i18n.error);
			});
		}

		function renderQuestion() {
			var question = state.questions[state.currentIndex];

			if (!question) {
				return;
			}

			var total = state.questions.length;
			var current = state.currentIndex + 1;

			els.progressFill.style.width = Math.round((state.currentIndex / total) * 100) + '%';
			els.progressLabel.textContent = format(CFG.i18n.question_progress, current, total);
			els.questionText.textContent = question.question;
			els.options.innerHTML = '';
			els.nextButton.disabled = true;
			els.nextButton.textContent = current === total ? CFG.i18n.submit_button : CFG.i18n.next_question;

			var letters = ['a', 'b', 'c', 'd'];

			letters.forEach(function (letter) {
				if (typeof question.options[letter] === 'undefined') {
					return;
				}

				var button = document.createElement('button');
				button.type = 'button';
				button.className = 'ur-ai-quiz-option';
				button.setAttribute('role', 'radio');
				button.setAttribute('aria-checked', 'false');
				button.dataset.letter = letter;

				var letterBadge = document.createElement('span');
				letterBadge.className = 'ur-ai-quiz-option-letter';
				letterBadge.textContent = letter.toUpperCase();

				var text = document.createElement('span');
				text.textContent = question.options[letter];

				button.appendChild(letterBadge);
				button.appendChild(text);

				button.addEventListener('click', function () {
					selectOption(question.uid, letter, button);
				});

				els.options.appendChild(button);
			});

			// 若使用者上一次已經選過這一題（例如上一頁又切回來的情境），恢復選取狀態。
			var previousAnswer = state.answers[question.uid];
			if (previousAnswer) {
				var previousButton = els.options.querySelector('[data-letter="' + previousAnswer + '"]');
				if (previousButton) {
					markSelected(previousButton);
					els.nextButton.disabled = false;
				}
			}
		}

		function selectOption(questionUid, letter, button) {
			state.answers[questionUid] = letter;
			markSelected(button);
			els.nextButton.disabled = false;
		}

		function markSelected(selectedButton) {
			var buttons = els.options.querySelectorAll('.ur-ai-quiz-option');
			Array.prototype.forEach.call(buttons, function (btn) {
				btn.classList.remove('is-selected');
				btn.setAttribute('aria-checked', 'false');
			});
			selectedButton.classList.add('is-selected');
			selectedButton.setAttribute('aria-checked', 'true');
		}

		function handleNext() {
			var question = state.questions[state.currentIndex];

			if (!question || !state.answers[question.uid]) {
				showError(CFG.i18n.please_answer);
				return;
			}

			hideError();

			if (state.currentIndex >= state.questions.length - 1) {
				setState('nickname');
				return;
			}

			state.currentIndex++;
			renderQuestion();
		}

		function handleSubmit() {
			hideError();
			showLoading(CFG.i18n.loading);

			var duration = Math.round((Date.now() - state.startedAt) / 1000);
			var nickname = els.nicknameInput ? els.nicknameInput.value : '';

			post(CFG.ajax_url, {
				action: CFG.action_submit,
				nonce: CFG.nonce,
				token: state.token,
				answers: JSON.stringify(state.answers),
				nickname: nickname,
				duration: duration
			}).then(function (res) {
				hideLoading();

				if (!res || !res.success) {
					showError(res && res.data && res.data.message ? res.data.message : CFG.i18n.error);
					return;
				}

				renderResult(res.data);
			}).catch(function () {
				hideLoading();
				showError(CFG.i18n.error);
			});
		}

		function renderResult(data) {
			els.resultScore.textContent = data.score;
			els.resultDetail.textContent = format(CFG.i18n.result_detail, data.correct_count, data.total_questions);
			els.resultStatus.textContent = data.is_new_best ? CFG.i18n.new_best : CFG.i18n.not_best;

			setState('result');
		}

		function handleRetry() {
			if (els.nicknameInput) {
				els.nicknameInput.value = '';
			}

			hideError();
			setState('intro');
		}

		function setState(name) {
			['intro', 'play', 'nickname', 'result'].forEach(function (key) {
				if (els[key]) {
					els[key].hidden = key !== name;
				}
			});
		}

		function showLoading(message) {
			if (els.loading) {
				els.loading.textContent = message || '';
				els.loading.hidden = false;
			}
		}

		function hideLoading() {
			if (els.loading) {
				els.loading.hidden = true;
			}
		}

		function showError(message) {
			if (els.error) {
				els.error.textContent = message || CFG.i18n.error;
				els.error.hidden = false;
			}
		}

		function hideError() {
			if (els.error) {
				els.error.hidden = true;
			}
		}
	}

	/**
	 * 簡易 sprintf 風格格式化。
	 * 支援 PHP 慣用的位置型佔位符（%1$s、%2$d…），對應 wp_localize_script
	 * 傳過來、原本設計給 PHP sprintf() 用的 i18n 字串，讓翻譯字串可以照語言
	 * 習慣調整詞序，不受參數傳入順序限制。
	 */
	function format(template) {
		var args = Array.prototype.slice.call(arguments, 1);

		return String(template).replace(/%(\d+)\$[ds]/g, function (match, position) {
			var value = args[parseInt(position, 10) - 1];
			return typeof value === 'undefined' ? '' : value;
		});
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
