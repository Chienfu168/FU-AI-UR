/**
 * 後台「AI 對話」腳本。
 *
 * 對話紀錄只存在本機記憶體（重新整理頁面會清空），每次傳送訊息／
 * 產生總結草稿都會把目前累積的整段對話送給伺服器，伺服器本身不保存
 * 對話狀態（OpenAI Chat Completions API 本身也是無狀態的）。
 */
(function ($) {
    'use strict';

    if (typeof window.UR_AI_ADMIN === 'undefined' || typeof window.UR_AI_ADMIN_CHAT === 'undefined') {
        return;
    }

    var CFG = window.UR_AI_ADMIN;
    var CHAT_CFG = window.UR_AI_ADMIN_CHAT;

    // 對話紀錄：[{role: 'user'|'assistant', content: string}]
    var messages = [];

    var $messages;
    var $input;
    var $sendButton;
    var $summarizeButton;
    var $draftsCard;
    var $draftsList;

    function i18n(key, fallback) {
        return (CHAT_CFG.i18n && CHAT_CFG.i18n[key]) || fallback || '';
    }

    function escapeHtml(text) {
        return $('<div/>').text(String(text == null ? '' : text)).html();
    }

    function renderMessages() {
        if (0 === messages.length) {
            $messages.html('<p class="ur-ai-muted ur-ai-chat-empty">' + escapeHtml(i18n('no_conversation', '目前還沒有對話內容。')) + '</p>');
            return;
        }

        var html = '';

        messages.forEach(function (message) {
            var roleClass = 'assistant' === message.role ? 'ur-ai-chat-bubble-assistant' : 'ur-ai-chat-bubble-user';
            html += '<div class="ur-ai-chat-bubble ' + roleClass + '">' + escapeHtml(message.content).replace(/\n/g, '<br>') + '</div>';
        });

        $messages.html(html);
        $messages.scrollTop($messages.prop('scrollHeight'));
    }

    function appendMessage(role, content) {
        messages.push({ role: role, content: content });
        renderMessages();
    }

    function showChatError(text) {
        var $error = $('<div class="ur-ai-chat-bubble ur-ai-chat-bubble-error"></div>').text(text);
        $messages.append($error);
        $messages.scrollTop($messages.prop('scrollHeight'));
    }

    function ajaxPost(action, data, onSuccess, onError) {
        var payload = $.extend({ action: action, nonce: CFG.nonce }, data);

        $.post(CFG.ajax_url, payload)
            .done(function (response) {
                if (response && response.success) {
                    onSuccess(response.data || {});
                } else {
                    onError((response && response.data && response.data.message) || '');
                }
            })
            .fail(function () {
                onError('');
            });
    }

    function handleSend() {
        var text = $.trim($input.val());

        if ('' === text) {
            window.alert(i18n('empty_message', '請先輸入訊息內容。'));
            return;
        }

        appendMessage('user', text);
        $input.val('');

        $sendButton.prop('disabled', true).text(i18n('sending', '傳送中…'));

        ajaxPost(
            CHAT_CFG.action_send,
            { messages: JSON.stringify(messages) },
            function (data) {
                appendMessage('assistant', data.answer || '');
                $sendButton.prop('disabled', false).text(i18n('send_button', '傳送'));
            },
            function (message) {
                showChatError(message || i18n('send_error', 'AI 回覆失敗，請稍後再試。'));
                $sendButton.prop('disabled', false).text(i18n('send_button', '傳送'));
            }
        );
    }

    function handleSummarize() {
        if (0 === messages.length) {
            window.alert(i18n('no_conversation', '請先與 AI 對話幾輪，再產生總結草稿。'));
            return;
        }

        $summarizeButton.prop('disabled', true).text(i18n('summarizing', '整理中…'));

        ajaxPost(
            CHAT_CFG.action_summarize,
            { messages: JSON.stringify(messages) },
            function (data) {
                renderDrafts(data.drafts || []);
                $summarizeButton.prop('disabled', false).text(i18n('summarize_button', '產生總結草稿'));
            },
            function (message) {
                window.alert(message || i18n('summarize_error', '整理草稿失敗，請稍後再試。'));
                $summarizeButton.prop('disabled', false).text(i18n('summarize_button', '產生總結草稿'));
            }
        );
    }

    function renderDrafts(drafts) {
        $draftsList.empty();

        drafts.forEach(function (draft, index) {
            var $card = buildDraftCard(draft, index);
            $draftsList.append($card);
        });

        $draftsCard.prop('hidden', 0 === drafts.length);

        if (drafts.length > 0) {
            $draftsCard.get(0).scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function buildDraftCard(draft, index) {
        var uid = 'ur-ai-chat-draft-' + index;

        var $card = $('<div class="ur-ai-chat-draft-card"></div>');

        $card.append(
            $('<div class="ur-ai-form-row"></div>').append(
                $('<label></label>').attr('for', uid + '-question').text(i18n('question_label', '標準問題')),
                $('<textarea rows="2"></textarea>').attr('id', uid + '-question').addClass('ur-ai-chat-draft-question').val(draft.question || '')
            )
        );

        $card.append(
            $('<div class="ur-ai-form-row"></div>').append(
                $('<label></label>').attr('for', uid + '-answer').text(i18n('answer_label', '固定回答')),
                $('<textarea rows="4"></textarea>').attr('id', uid + '-answer').addClass('ur-ai-chat-draft-answer').val(draft.answer || '')
            )
        );

        var $metaRow = $('<div class="ur-ai-grid ur-ai-grid-2"></div>');

        $metaRow.append(
            $('<div class="ur-ai-form-row"></div>').append(
                $('<label></label>').attr('for', uid + '-category').text(i18n('category_label', '分類')),
                $('<input type="text">').attr('id', uid + '-category').addClass('ur-ai-chat-draft-category').val(draft.category || '')
            )
        );

        $metaRow.append(
            $('<div class="ur-ai-form-row"></div>').append(
                $('<label></label>').attr('for', uid + '-keywords').text(i18n('keywords_label', '關鍵字')),
                $('<input type="text">').attr('id', uid + '-keywords').addClass('ur-ai-chat-draft-keywords').val(draft.keywords || '')
            )
        );

        $card.append($metaRow);

        var $status = $('<span class="ur-ai-muted ur-ai-chat-draft-status"></span>');

        var $saveButton = $('<button type="button" class="button button-primary"></button>')
            .text(i18n('save_draft_button', '加入知識庫（存成草稿）'))
            .on('click', function () {
                handleSaveDraft($card, $(this), $status);
            });

        $card.append($('<div class="ur-ai-chat-draft-actions"></div>').append($saveButton, $status));

        return $card;
    }

    function handleSaveDraft($card, $button, $status) {
        if (!window.confirm(i18n('confirm_save_draft', '確定要把這則內容加入知識庫嗎？'))) {
            return;
        }

        var question = $.trim($card.find('.ur-ai-chat-draft-question').val());
        var answer   = $.trim($card.find('.ur-ai-chat-draft-answer').val());
        var category = $.trim($card.find('.ur-ai-chat-draft-category').val());
        var keywords = $.trim($card.find('.ur-ai-chat-draft-keywords').val());

        $button.prop('disabled', true).text(i18n('saving_draft', '儲存中…'));
        $status.text('');

        ajaxPost(
            CHAT_CFG.action_save_draft,
            { question: question, answer: answer, category: category, keywords: keywords },
            function () {
                $status.text(i18n('draft_saved', '已儲存為草稿'));
                $button.text(i18n('draft_saved', '已儲存為草稿'));
                $card.find('textarea, input').prop('disabled', true);
            },
            function (message) {
                window.alert(message || i18n('save_draft_error', '儲存失敗，請稍後再試。'));
                $button.prop('disabled', false).text(i18n('save_draft_button', '加入知識庫（存成草稿）'));
            }
        );
    }

    function init() {
        $messages        = $('#ur-ai-chat-messages');
        $input           = $('#ur-ai-chat-input');
        $sendButton      = $('#ur-ai-chat-send');
        $summarizeButton = $('#ur-ai-chat-summarize');
        $draftsCard      = $('#ur-ai-chat-drafts-card');
        $draftsList      = $('#ur-ai-chat-drafts-list');

        if (!$messages.length) {
            return;
        }

        $sendButton.on('click', handleSend);
        $summarizeButton.on('click', handleSummarize);

        $input.on('keydown', function (event) {
            // Ctrl/Cmd + Enter 送出，一般 Enter 保留換行給多行輸入使用。
            if ((event.ctrlKey || event.metaKey) && 13 === event.which) {
                event.preventDefault();
                handleSend();
            }
        });
    }

    $(document).ready(init);

})(jQuery);
