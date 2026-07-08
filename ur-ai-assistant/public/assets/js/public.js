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
        feedbackSubmit: '.ur-ai-feedback-submit',
        printButton: '.ur-ai-print-button',
        kbBrowse: '.ur-ai-kb-browse',
        kbSearchForm: '.ur-ai-kb-search-form',
        kbSearchInput: '.ur-ai-kb-search-input',
        kbCategorySelect: '.ur-ai-kb-category-select',
        kbResults: '.ur-ai-kb-results',
        kbPagination: '.ur-ai-kb-pagination',
        kbItem: '.ur-ai-kb-item',
        kbItemQuestion: '.ur-ai-kb-item-question',
        kbPageLink: '.ur-ai-kb-page-link'
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

    function formatInlineMarkdown(text) {
        let html = escapeHtml(text || '');

        html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/__([^_]+)__/g, '<strong>$1</strong>');

        return html;
    }

    function formatMarkdownText(text) {
        const normalized = String(text || '')
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n')
            .replace(/<br\s*\/?>/gi, '\n');

        const lines = normalized.split('\n');
        let html = '';
        let inList = false;

        function closeList() {
            if (inList) {
                html += '</ul>';
                inList = false;
            }
        }

        lines.forEach(function (rawLine) {
            const line = $.trim(rawLine || '');

            if (!line) {
                closeList();
                return;
            }

            const headingMatch = line.match(/^#{1,6}\s*(.+)$/);
            if (headingMatch) {
                closeList();
                html += '<h3 class="ur-ai-answer-heading">' + formatInlineMarkdown(headingMatch[1]) + '</h3>';
                return;
            }

            const bulletMatch = line.match(/^[-*•]\s+(.+)$/);
            if (bulletMatch) {
                if (!inList) {
                    html += '<ul class="ur-ai-answer-list">';
                    inList = true;
                }

                html += '<li>' + formatInlineMarkdown(bulletMatch[1]) + '</li>';
                return;
            }

            const numberMatch = line.match(/^\d+[.、]\s+(.+)$/);
            if (numberMatch) {
                if (!inList) {
                    html += '<ul class="ur-ai-answer-list">';
                    inList = true;
                }

                html += '<li>' + formatInlineMarkdown(numberMatch[1]) + '</li>';
                return;
            }

            closeList();
            html += '<p>' + formatInlineMarkdown(line) + '</p>';
        });

        closeList();

        return html;
    }

    function formatExistingAnswerHtml(html) {
        let value = String(html || '');

        if (!value) {
            return '';
        }

        value = value.replace(/&nbsp;/gi, ' ');

        const valueWithoutBreaks = value.replace(/<br\s*\/?>/gi, '');
        const hasHtmlTags = /<[^>]+>/.test(valueWithoutBreaks);

        if (!hasHtmlTags) {
            return formatMarkdownText(value);
        }

        /*
        * 處理後端已包成 HTML 的 Markdown 標題。
        * 支援：
        * <p>### 標題</p>
        * <p class="...">### 標題</p>
        * <p dir="auto">### 標題</p>
        * <div>### 標題</div>
        * <div class="...">### 標題</div>
        */
        value = value.replace(
            /<p\b[^>]*>\s*#{1,6}\s*([^<\n\r]+?)\s*<\/p>/gi,
            function (match, title) {
                return '<h3 class="ur-ai-answer-heading">' + formatInlineMarkdown(title) + '</h3>';
            }
        );

        value = value.replace(
            /<div\b[^>]*>\s*#{1,6}\s*([^<\n\r]+?)\s*<\/div>/gi,
            function (match, title) {
                return '<h3 class="ur-ai-answer-heading">' + formatInlineMarkdown(title) + '</h3>';
            }
        );

        /*
        * 處理同一個段落內有標題加換行的情況：
        * <p>### 標題<br>內文...</p>
        */
        value = value.replace(
            /<p\b[^>]*>\s*#{1,6}\s*([^<\n\r]+?)\s*<br\s*\/?>\s*([\s\S]*?)<\/p>/gi,
            function (match, title, content) {
                return '<h3 class="ur-ai-answer-heading">' + formatInlineMarkdown(title) + '</h3><p>' + content + '</p>';
            }
        );

        value = value.replace(
            /<div\b[^>]*>\s*#{1,6}\s*([^<\n\r]+?)\s*<br\s*\/?>\s*([\s\S]*?)<\/div>/gi,
            function (match, title, content) {
                return '<h3 class="ur-ai-answer-heading">' + formatInlineMarkdown(title) + '</h3><p>' + content + '</p>';
            }
        );

        /*
        * 最後再處理純文字混在 HTML 中、或換行後出現的 Markdown 標題。
        */
        value = value.replace(
            /(^|<br\s*\/?>|\n)\s*#{1,6}\s*([^<\n\r]+)/gi,
            function (match, prefix, title) {
                return prefix + '<h3 class="ur-ai-answer-heading">' + formatInlineMarkdown(title) + '</h3>';
            }
        );

        /*
        * 處理粗體符號。
        */
        value = value
            .replace(/\*\*([^*<]+)\*\*/g, '<strong>$1</strong>')
            .replace(/__([^_<]+)__/g, '<strong>$1</strong>');

        return value;
    }

    function getAnswerMainLabel(answerSource, answerSourceLabel) {
        const source = String(answerSource || '').toLowerCase();

        if (source === 'faq') {
            return answerSourceLabel || getI18n('knowledge_answer', '知識庫回答');
        }

        if (source === 'ai') {
            return answerSourceLabel || getI18n('assistant_answer', 'AI 助理回答');
        }

        if (answerSourceLabel) {
            return answerSourceLabel;
        }

        return getI18n('assistant_answer', 'AI 助理回答');
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

        const rawAnswer = data.answer_html || data.html || data.answer || '';
        const answer = (data.answer_html || data.html)
            ? formatExistingAnswerHtml(rawAnswer)
            : formatMarkdownText(rawAnswer);
        const answerSource = data.answer_source || data.source || '';
        const answerSourceLabel = data.answer_source_label || data.source_label || answerSource;
        const mainAnswerLabel = getAnswerMainLabel(answerSource, answerSourceLabel);
        const logId = data.log_id || 0;
        const relatedPages = data.related_pages || [];

        let html = '';

        html += '<div class="ur-ai-message ur-ai-user-message">';
        html += '<span class="ur-ai-message-label">' + escapeHtml(getI18n('your_question', '你的問題')) + '</span>';
        html += '<div class="ur-ai-message-content">' + escapeHtml(question) + '</div>';
        html += '</div>';

        html += '<div class="ur-ai-message ur-ai-answer-message">';
        html += '<span class="ur-ai-message-label">' + escapeHtml(mainAnswerLabel) + '</span>';
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

        html += '<button type="button" class="ur-ai-print-button"';
        html += ' data-print-question="' + escapeHtml(encodeURIComponent(question)) + '"';
        html += ' data-print-answer="' + escapeHtml(encodeURIComponent(answer)) + '"';
        html += ' aria-label="' + escapeHtml(getI18n('print_button_aria', '列印這則問答')) + '">';
        html += escapeHtml(getI18n('print_button', '列印'));
        html += '</button>';

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

    function getKbPerPage($kbSection) {
        const value = parseInt($kbSection.data('kb-per-page'), 10);

        return Number.isNaN(value) || value <= 0 ? 10 : value;
    }

    function renderKbItem(item) {
        const question = item.question || '';
        const answerHtml = item.answer_html || '';
        const category = item.category || '';

        let html = '';

        html += '<div class="ur-ai-kb-item">';
        html += '<button type="button" class="ur-ai-kb-item-question">';

        if (category) {
            html += '<span class="ur-ai-kb-item-category">' + escapeHtml(category) + '</span>';
        }

        html += '<span class="ur-ai-kb-item-question-text">' + escapeHtml(question) + '</span>';
        html += '</button>';
        html += '<div class="ur-ai-kb-item-answer">' + answerHtml + '</div>';
        html += '</div>';

        return html;
    }

    function renderKbPagination($kbSection, data) {
        const paged = parseInt(data.paged, 10) || 1;
        const totalPages = parseInt(data.total_pages, 10) || 0;
        const $pagination = $kbSection.find(selectors.kbPagination);

        if (totalPages <= 1) {
            $pagination.empty();
            return;
        }

        let html = '';

        html += '<button type="button" class="ur-ai-kb-page-link" data-page="' + (paged - 1) + '"' + (paged <= 1 ? ' disabled' : '') + '>';
        html += escapeHtml(getI18n('kb_prev', '上一頁'));
        html += '</button>';

        const pageInfoTemplate = getI18n('kb_page_info', '第 %1$s／%2$s 頁（共 %3$s 筆）');
        const pageInfo = pageInfoTemplate
            .replace('%1$s', paged)
            .replace('%2$s', totalPages)
            .replace('%3$s', data.total || 0);

        html += '<span class="ur-ai-kb-page-info">' + escapeHtml(pageInfo) + '</span>';

        html += '<button type="button" class="ur-ai-kb-page-link" data-page="' + (paged + 1) + '"' + (paged >= totalPages ? ' disabled' : '') + '>';
        html += escapeHtml(getI18n('kb_next', '下一頁'));
        html += '</button>';

        $pagination.html(html);
    }

    function fetchKbList($kbSection, page) {
        const $results = $kbSection.find(selectors.kbResults);
        const search = $kbSection.find(selectors.kbSearchInput).val() || '';
        const category = $kbSection.find(selectors.kbCategorySelect).val() || '';

        $kbSection.data('kb-page', page);
        $results.html('<p class="ur-ai-kb-loading">' + escapeHtml(getI18n('kb_loading', '載入中…')) + '</p>');

        $.ajax({
            url: getAjaxUrl(),
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'ur_ai_faq_browse',
                nonce: getNonce(),
                search: search,
                category: category,
                paged: page,
                per_page: getKbPerPage($kbSection)
            }
        })
            .done(function (response) {
                if (!response || !response.success || !response.data) {
                    const message = response && response.data && response.data.message
                        ? response.data.message
                        : getI18n('kb_error', '知識庫載入失敗，請稍後再試。');

                    $results.html('<p class="ur-ai-kb-error">' + escapeHtml(message) + '</p>');
                    $kbSection.find(selectors.kbPagination).empty();
                    return;
                }

                const data = response.data;
                const items = Array.isArray(data.items) ? data.items : [];

                if (items.length === 0) {
                    $results.html('<p class="ur-ai-kb-empty">' + escapeHtml(getI18n('kb_no_results', '找不到符合的常見問題，可以直接在下方向 AI 助理提問。')) + '</p>');
                    $kbSection.find(selectors.kbPagination).empty();
                    return;
                }

                let html = '';
                items.forEach(function (item) {
                    html += renderKbItem(item);
                });

                $results.html(html);
                renderKbPagination($kbSection, data);
            })
            .fail(function () {
                $results.html('<p class="ur-ai-kb-error">' + escapeHtml(getI18n('network_error', '連線失敗，請稍後再試。')) + '</p>');
                $kbSection.find(selectors.kbPagination).empty();
            });
    }

    function handleKbSearchSubmit(event) {
        event.preventDefault();

        const $kbSection = $(this).closest(selectors.kbBrowse);

        fetchKbList($kbSection, 1);
    }

    function handleKbItemToggle(event) {
        event.preventDefault();

        $(this).closest(selectors.kbItem).toggleClass('is-open');
    }

    function handleKbCategoryChange(event) {
        const $kbSection = $(this).closest(selectors.kbBrowse);

        fetchKbList($kbSection, 1);
    }

    function handleKbPageLinkClick(event) {
        event.preventDefault();

        const $link = $(this);

        if ($link.is('[disabled]')) {
            return;
        }

        const $kbSection = $link.closest(selectors.kbBrowse);
        const page = parseInt($link.data('page'), 10) || 1;

        fetchKbList($kbSection, page);
    }

    function initKbBrowse() {
        $(selectors.kbBrowse).each(function () {
            fetchKbList($(this), 1);
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

    function decodeDataAttr(value) {
        if (!value) {
            return '';
        }

        try {
            return decodeURIComponent(value);
        } catch (e) {
            return value;
        }
    }

    function buildPrintDocument(questionText, answerHtml) {
        const config = getConfig();
        const siteName = config.site_name || '';
        const siteUrl = config.site_url || '';
        const disclaimer = config.disclaimer || '';

        const now = new Date();
        const pad = function (n) {
            return (n < 10 ? '0' : '') + n;
        };
        const dateStr = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) +
            ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes());

        const docTitle = getI18n('print_document_title', '都更 AI 助理問答');
        const questionLabel = getI18n('print_question_label', '問題');
        const answerLabel = getI18n('print_answer_label', '回答');
        const dateLabel = getI18n('print_date_label', '列印日期');
        const disclaimerLabel = getI18n('print_disclaimer_label', '免責聲明');

        const styles =
            '@page { margin: 18mm 16mm; }' +
            'body { font-family: "Microsoft JhengHei", "PingFang TC", "Helvetica Neue", Arial, sans-serif; color: #1a1a1a; line-height: 1.7; font-size: 13px; margin: 0; padding: 0; }' +
            '.ur-print-header { border-bottom: 2px solid #1a1a1a; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: baseline; }' +
            '.ur-print-brand { font-size: 16px; font-weight: 700; }' +
            '.ur-print-url { font-size: 12px; color: #555; }' +
            '.ur-print-date { font-size: 11px; color: #666; margin-bottom: 18px; }' +
            '.ur-print-block { margin-bottom: 18px; }' +
            '.ur-print-label { font-size: 12px; font-weight: 700; color: #444; border-left: 3px solid #888; padding-left: 8px; margin-bottom: 8px; }' +
            '.ur-print-question { font-size: 14px; font-weight: 600; padding-left: 11px; }' +
            '.ur-print-answer { padding-left: 11px; }' +
            '.ur-print-answer p { margin: 0 0 10px; }' +
            '.ur-print-answer ul, .ur-print-answer ol { margin: 0 0 10px; padding-left: 22px; }' +
            '.ur-print-answer h1, .ur-print-answer h2, .ur-print-answer h3, .ur-print-answer h4 { font-size: 14px; margin: 12px 0 6px; }' +
            '.ur-print-disclaimer { margin-top: 28px; padding-top: 12px; border-top: 1px solid #ccc; font-size: 11px; color: #666; }' +
            '.ur-print-disclaimer-label { font-weight: 700; margin-bottom: 4px; }';

        let html = '<!DOCTYPE html><html lang="zh-Hant"><head><meta charset="utf-8">';
        html += '<title>' + escapeHtml(docTitle) + '</title>';
        html += '<style>' + styles + '</style></head><body>';

        html += '<div class="ur-print-header">';
        html += '<span class="ur-print-brand">' + escapeHtml(siteName) + '</span>';
        if (siteUrl) {
            html += '<span class="ur-print-url">' + escapeHtml(siteUrl) + '</span>';
        }
        html += '</div>';

        html += '<div class="ur-print-date">' + escapeHtml(dateLabel) + '：' + escapeHtml(dateStr) + '</div>';

        html += '<div class="ur-print-block">';
        html += '<div class="ur-print-label">' + escapeHtml(questionLabel) + '</div>';
        html += '<div class="ur-print-question">' + escapeHtml(questionText) + '</div>';
        html += '</div>';

        html += '<div class="ur-print-block">';
        html += '<div class="ur-print-label">' + escapeHtml(answerLabel) + '</div>';
        html += '<div class="ur-print-answer">' + answerHtml + '</div>';
        html += '</div>';

        if (disclaimer) {
            html += '<div class="ur-print-disclaimer">';
            html += '<div class="ur-print-disclaimer-label">' + escapeHtml(disclaimerLabel) + '</div>';
            html += '<div>' + escapeHtml(disclaimer) + '</div>';
            html += '</div>';
        }

        html += '</body></html>';

        return html;
    }

    function handlePrintClick(event) {
        event.preventDefault();

        const $button = $(event.currentTarget);
        const questionText = decodeDataAttr($button.attr('data-print-question'));
        const answerHtml = decodeDataAttr($button.attr('data-print-answer'));

        const docHtml = buildPrintDocument(questionText, answerHtml);

        const iframe = document.createElement('iframe');
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';

        document.body.appendChild(iframe);

        const doc = iframe.contentWindow ? iframe.contentWindow.document : iframe.contentDocument;

        if (!doc) {
            document.body.removeChild(iframe);
            return;
        }

        doc.open();
        doc.write(docHtml);
        doc.close();

        const triggerPrint = function () {
            try {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            } catch (e) {
                // 靜默失敗，避免影響前台其他操作。
            }

            setTimeout(function () {
                if (iframe.parentNode) {
                    iframe.parentNode.removeChild(iframe);
                }
            }, 1000);
        };

        // 等內容繪製完成再列印。
        setTimeout(triggerPrint, 250);
    }

    function bindEvents() {
        $(document).on('submit', selectors.form, handleFormSubmit);
        $(document).on('click', selectors.clear, handleClearClick);
        $(document).on('click', selectors.popularButton, handlePopularClick);
        $(document).on('click', selectors.relatedLink, handleRelatedClick);
        $(document).on('click', selectors.feedbackButton, handleFeedbackButtonClick);
        $(document).on('click', selectors.feedbackSubmit, handleFeedbackSubmit);
        $(document).on('click', selectors.printButton, handlePrintClick);
        $(document).on('submit', selectors.kbSearchForm, handleKbSearchSubmit);
        $(document).on('click', selectors.kbItemQuestion, handleKbItemToggle);
        $(document).on('click', selectors.kbPageLink, handleKbPageLinkClick);
        $(document).on('change', selectors.kbCategorySelect, handleKbCategoryChange);

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
        initKbBrowse();
    }

    $(document).ready(init);

})(jQuery);