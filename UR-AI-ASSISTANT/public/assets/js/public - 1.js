(function ($) {
    'use strict';

    const selectors = {
        wrapper: '.ur-ai-assistant',
        form: '.ur-ai-form',
        input: '.ur-ai-question-input',
        submit: '.ur-ai-submit',
        clear: '.ur-ai-clear',
        counter: '.ur-ai-counter-current',
        result: '.ur-ai-result',
        loading: '.ur-ai-loading',
        error: '.ur-ai-error',
        popularButton: '.ur-ai-popular-button',
        relatedLink: '.ur-ai-related-link',
        feedbackButton: '.ur-ai-feedback-button',
        feedbackReasonWrap: '.ur-ai-feedback-reason',
        feedbackReason: '.ur-ai-feedback-reason-select',
        feedbackComment: '.ur-ai-feedback-comment',
        feedbackSubmit: '.ur-ai-feedback-submit'
    };

    function getConfig() {
        return window.UR_AI_PUBLIC || {};
    }

    function getAjaxUrl() {
        const config = getConfig();
        return config.ajax_url || '';
    }

    function getNonce() {
        const config = getConfig();
        return config.nonce || '';
    }

    function getI18n(key, fallback) {
        const config = getConfig();

        if (config.i18n && config.i18n[key]) {
            return config.i18n[key];
        }

        return fallback;
    }

    function getMaxQuestionLength($wrapper) {
        const value = parseInt($wrapper.data('max-question-length'), 10);

        if (Number.isNaN(value) || value <= 0) {
            const config = getConfig();
            const configValue = parseInt(config.max_question_length, 10);

            return Number.isNaN(configValue) || configValue <= 0 ? 500 : configValue;
        }

        return value;
    }

    function setLoading($wrapper, isLoading) {
        const $loading = $wrapper.find(selectors.loading);
        const $submit = $wrapper.find(selectors.submit);
        const $input = $wrapper.find(selectors.input);

        if (isLoading) {
            $loading.addClass('is-active');
            $submit.prop('disabled', true).text(getI18n('processing', '思考中...'));
            $input.prop('disabled', true);
            return;
        }

        $loading.removeClass('is-active');
        $submit.prop('disabled', false).text(getI18n('submit', '送出提問'));
        $input.prop('disabled', false);
    }

    function clearError($wrapper) {
        $wrapper.find(selectors.error).remove();
    }

    function showError($wrapper, message) {
        clearError($wrapper);

        const text = message || getI18n('error', '發生錯誤，請稍後再試。');

        const $error = $('<div />', {
            class: 'ur-ai-error',
            text: text
        });

        $wrapper.find(selectors.form).after($error);
    }

    function scrollToResult($wrapper) {
        const $result = $wrapper.find(selectors.result);

        if (!$result.length || !$result.html().trim()) {
            return;
        }

        const offset = $result.offset();

        if (!offset) {
            return;
        }

        $('html, body').animate(
            {
                scrollTop: Math.max(0, offset.top - 90)
            },
            260
        );
    }

    function updateCounter($wrapper) {
        const $input = $wrapper.find(selectors.input);
        const $counter = $wrapper.find(selectors.counter);

        if (!$input.length || !$counter.length) {
            return;
        }

        const value = $input.val() || '';
        $counter.text(value.length);
    }

    function validateQuestion($wrapper, question) {
        const maxLength = getMaxQuestionLength($wrapper);

        if (!question || !question.trim()) {
            return {
                valid: false,
                message: getI18n('empty_question', '請先輸入想詢問的問題。')
            };
        }

        if (question.length > maxLength) {
            return {
                valid: false,
                message: getI18n('question_too_long', '問題字數過長，請縮短後再送出。')
            };
        }

        return {
            valid: true,
            message: ''
        };
    }

    function escapeHtml(text) {
        return $('<div />').text(text || '').html();
    }

    function renderRelatedPages(relatedPages) {
        if (!Array.isArray(relatedPages) || relatedPages.length === 0) {
            return '';
        }

        let html = '';

        html += '<div class="ur-ai-related">';
        html += '<h3 class="ur-ai-related-title">' + escapeHtml(getI18n('related_title', '你也許想知道')) + '</h3>';
        html += '<ul class="ur-ai-related-list">';

        relatedPages.forEach(function (page) {
            const id = page.id || 0;
            const title = page.title || '';
            const url = page.url || '#';
            const description = page.description || '';
            const category = page.category || '';

            html += '<li class="ur-ai-related-item">';
            html += '<a class="ur-ai-related-link" href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer" data-related-page-id="' + escapeHtml(id) + '">';

            if (category) {
                html += '<span class="ur-ai-related-category">' + escapeHtml(category) + '</span>';
            }

            html += '<h4 class="ur-ai-related-heading">' + escapeHtml(title) + '</h4>';

            if (description) {
                html += '<p class="ur-ai-related-description">' + escapeHtml(description) + '</p>';
            }

            html += '</a>';
            html += '</li>';
        });

        html += '</ul>';
        html += '</div>';

        return html;
    }

    function renderFeedback(logId) {
        if (!logId) {
            return '';
        }

        let html = '';

        html += '<div class="ur-ai-feedback" data-log-id="' + escapeHtml(logId) + '">';
        html += '<p class="ur-ai-feedback-title">' + escapeHtml(getI18n('feedback_title', '這個回答對你有幫助嗎？')) + '</p>';

        html += '<div class="ur-ai-feedback-actions">';
        html += '<button type="button" class="ur-ai-feedback-button" data-feedback="helpful">';
        html += escapeHtml(getI18n('feedback_helpful', '有幫助'));
        html += '</button>';

        html += '<button type="button" class="ur-ai-feedback-button" data-feedback="not_helpful">';
        html += escapeHtml(getI18n('feedback_not_helpful', '沒幫助'));
        html += '</button>';
        html += '</div>';

        html += '<div class="ur-ai-feedback-reason">';
        html += '<select class="ur-ai-feedback-reason-select">';
        html += '<option value="">' + escapeHtml(getI18n('feedback_reason_placeholder', '請選擇原因')) + '</option>';
        html += '<option value="回答不夠清楚">' + escapeHtml(getI18n('reason_unclear', '回答不夠清楚')) + '</option>';
        html += '<option value="沒有回答到問題">' + escapeHtml(getI18n('reason_not_answered', '沒有回答到問題')) + '</option>';
        html += '<option value="內容太籠統">' + escapeHtml(getI18n('reason_too_general', '內容太籠統')) + '</option>';
        html += '<option value="需要更多實務說明">' + escapeHtml(getI18n('reason_need_examples', '需要更多實務說明')) + '</option>';
        html += '<option value="其他">' + escapeHtml(getI18n('reason_other', '其他')) + '</option>';
        html += '</select>';

        html += '<textarea class="ur-ai-feedback-comment" placeholder="' + escapeHtml(getI18n('feedback_comment_placeholder', '可補充說明，讓我們知道如何改善。')) + '"></textarea>';

        html += '<button type="button" class="ur-ai-submit ur-ai-feedback-submit">';
        html += escapeHtml(getI18n('feedback_submit', '送出回饋'));
        html += '</button>';
        html += '</div>';

        html += '</div>';

        return html;
    }

    function renderAnswer($wrapper, question, response) {
        const data = response && response.data ? response.data : response;

        const answer = data.answer_html || data.html || data.answer || '';
        const answerSource = data.answer_source || data.source || '';
        const answerSourceLabel = data.answer_source_label || data.source_label || answerSource;
        const logId = data.log_id || 0;
        const relatedPages = data.related_pages || [];

        let html = '';

        html += '<div class="ur-ai-message ur-ai-user-message">';
        html += '<span class="ur-ai-message-label">' + escapeHtml(getI18n('your_question', '你的問題')) + '</span>';
        html += '<div class="ur-ai-message-content">' + escapeHtml(question) + '</div>';
        html += '</div>';

        html += '<div class="ur-ai-message ur-ai-answer-message">';
        html += '<span class="ur-ai-message-label">' + escapeHtml(getI18n('assistant_answer', 'AI 助理回答')) + '</span>';
        html += '<div class="ur-ai-message-content">' + answer + '</div>';

        html += '<div class="ur-ai-answer-meta">';

        if (answerSourceLabel) {
            html += '<span class="ur-ai-badge ur-ai-badge-' + escapeHtml(answerSource || 'default') + '">';
            html += escapeHtml(answerSourceLabel);
            html += '</span>';
        }

        if (data.faq_id) {
            html += '<span class="ur-ai-badge ur-ai-badge-faq">';
            html += 'FAQ #' + escapeHtml(data.faq_id);
            html += '</span>';
        }

        html += '</div>';

        html += renderRelatedPages(relatedPages);
        html += renderFeedback(logId);

        html += '</div>';

        $wrapper.find(selectors.result).html(html);
        scrollToResult($wrapper);
    }

    function submitQuestion($wrapper, question) {
        const validation = validateQuestion($wrapper, question);

        if (!validation.valid) {
            showError($wrapper, validation.message);
            return;
        }

        clearError($wrapper);
        setLoading($wrapper, true);

        $.ajax({
            url: getAjaxUrl(),
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'ur_ai_ask',
                nonce: getNonce(),
                question: question
            }
        })
            .done(function (response) {
                if (!response || !response.success) {
                    const message = response && response.data && response.data.message
                        ? response.data.message
                        : getI18n('error', '發生錯誤，請稍後再試。');

                    showError($wrapper, message);
                    return;
                }

                renderAnswer($wrapper, question, response);
            })
            .fail(function () {
                showError($wrapper, getI18n('network_error', '連線失敗，請稍後再試。'));
            })
            .always(function () {
                setLoading($wrapper, false);
            });
    }

    function handleFormSubmit(event) {
        event.preventDefault();

        const $form = $(this);
        const $wrapper = $form.closest(selectors.wrapper);
        const question = $wrapper.find(selectors.input).val() || '';

        submitQuestion($wrapper, question.trim());
    }

    function handleClearClick(event) {
        event.preventDefault();

        const $button = $(this);
        const $wrapper = $button.closest(selectors.wrapper);

        $wrapper.find(selectors.input).val('').trigger('input').focus();
        $wrapper.find(selectors.result).empty();
        clearError($wrapper);
    }

    function handlePopularClick(event) {
        event.preventDefault();

        const $button = $(this);
        const $wrapper = $button.closest(selectors.wrapper);
        const question = $button.data('submit-question') || $button.data('question') || $button.text();
        const questionId = parseInt($button.data('question-id'), 10) || 0;

        if (questionId > 0) {
            trackPopularQuestionClick(questionId);
        }

        $wrapper.find(selectors.input).val(question).trigger('input');
        submitQuestion($wrapper, String(question).trim());
    }

    function trackPopularQuestionClick(questionId) {
        if (!questionId) {
            return;
        }

        $.ajax({
            url: getAjaxUrl(),
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'ur_ai_popular_question_click',
                nonce: getNonce(),
                question_id: questionId
            }
        });
    }

    function handleRelatedClick() {
        const $link = $(this);
        const pageId = parseInt($link.data('related-page-id'), 10) || 0;

        if (!pageId) {
            return true;
        }

        $.ajax({
            url: getAjaxUrl(),
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'ur_ai_related_page_click',
                nonce: getNonce(),
                page_id: pageId
            }
        });

        return true;
    }

    function handleFeedbackButtonClick(event) {
        event.preventDefault();

        const $button = $(this);
        const $feedback = $button.closest('.ur-ai-feedback');
        const feedbackType = $button.data('feedback') || '';

        $feedback.find(selectors.feedbackButton).removeClass('is-selected');
        $button.addClass('is-selected');

        $feedback.data('feedback', feedbackType);

        if (feedbackType === 'helpful') {
            submitFeedback($feedback, feedbackType, '', '');
            return;
        }

        $feedback.find(selectors.feedbackReasonWrap).addClass('is-active');
    }

    function handleFeedbackSubmit(event) {
        event.preventDefault();

        const $button = $(this);
        const $feedback = $button.closest('.ur-ai-feedback');
        const feedbackType = $feedback.data('feedback') || 'not_helpful';
        const reason = $feedback.find(selectors.feedbackReason).val() || '';
        const comment = $feedback.find(selectors.feedbackComment).val() || '';

        submitFeedback($feedback, feedbackType, reason, comment, $button);
    }

    function submitFeedback($feedback, feedbackType, reason, comment, $button) {
        const logId = parseInt($feedback.data('log-id'), 10) || 0;

        if (!logId) {
            return;
        }

        if ($button && $button.length) {
            $button.prop('disabled', true).text(getI18n('processing', '處理中...'));
        }

        $.ajax({
            url: getAjaxUrl(),
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'ur_ai_feedback',
                nonce: getNonce(),
                log_id: logId,
                feedback: feedbackType,
                reason: reason,
                comment: comment
            }
        })
            .done(function (response) {
                if (!response || !response.success) {
                    const message = response && response.data && response.data.message
                        ? response.data.message
                        : getI18n('feedback_failed', '回饋送出失敗，請稍後再試。');

                    showInlineFeedbackMessage($feedback, message, false);
                    return;
                }

                const message = response.data && response.data.message
                    ? response.data.message
                    : getI18n('feedback_success', '感謝您的回饋。');

                showInlineFeedbackMessage($feedback, message, true);
                $feedback.find('button, select, textarea').prop('disabled', true);
            })
            .fail(function () {
                showInlineFeedbackMessage($feedback, getI18n('network_error', '連線失敗，請稍後再試。'), false);
            })
            .always(function () {
                if ($button && $button.length) {
                    $button.prop('disabled', false).text(getI18n('feedback_submit', '送出回饋'));
                }
            });
    }

    function showInlineFeedbackMessage($feedback, message, success) {
        $feedback.find('.ur-ai-feedback-message').remove();

        const $message = $('<div />', {
            class: success ? 'ur-ai-success ur-ai-feedback-message' : 'ur-ai-error ur-ai-feedback-message',
            text: message
        });

        $feedback.append($message);
    }

    function bindEvents() {
        $(document).on('submit', selectors.form, handleFormSubmit);
        $(document).on('click', selectors.clear, handleClearClick);
        $(document).on('click', selectors.popularButton, handlePopularClick);
        $(document).on('click', selectors.relatedLink, handleRelatedClick);
        $(document).on('click', selectors.feedbackButton, handleFeedbackButtonClick);
        $(document).on('click', selectors.feedbackSubmit, handleFeedbackSubmit);

        $(document).on('input', selectors.input, function () {
            const $wrapper = $(this).closest(selectors.wrapper);
            updateCounter($wrapper);
        });
    }

    function initCounters() {
        $(selectors.wrapper).each(function () {
            updateCounter($(this));
        });
    }

    function init() {
        bindEvents();
        initCounters();
    }

    $(document).ready(init);

})(jQuery);