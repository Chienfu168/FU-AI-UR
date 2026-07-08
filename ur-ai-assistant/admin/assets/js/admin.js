(function ($) {
    'use strict';

    const selectors = {
        checkAll: '.ur-ai-check-all',
        itemCheckbox: '.ur-ai-item-checkbox',
        bulkForm: '.ur-ai-bulk-form',
        bulkAction: '.ur-ai-bulk-action',
        deleteButton: '.ur-ai-delete-button',
        convertFaqButton: '.ur-ai-convert-faq-button',
        importButton: '.ur-ai-import-button',
        copyButton: '.ur-ai-copy-button',
        toggleButton: '.ur-ai-toggle-button',
        toggleTarget: '.ur-ai-toggle-target'
    };

    function getConfig() {
        return window.UR_AI_ADMIN || {};
    }

    function getI18n(key, fallback) {
        const config = getConfig();

        if (config.i18n && config.i18n[key]) {
            return config.i18n[key];
        }

        return fallback;
    }

    function hasCheckedItems($form) {
        return $form.find(selectors.itemCheckbox + ':checked').length > 0;
    }

    function getBulkAction($form) {
        const $action = $form.find(selectors.bulkAction).first();

        if (!$action.length) {
            return '';
        }

        return $action.val() || '';
    }

    function confirmByAction(action) {
        if (action === 'delete') {
            return window.confirm(
                getI18n('confirm_bulk_delete', '確定要批次刪除所選資料嗎？此操作無法復原。')
            );
        }

        if (action === 'convert_to_faq') {
            return window.confirm(
                getI18n('confirm_convert_faq', '確定要轉成 FAQ 草稿嗎？轉換後仍需人工檢查後再啟用。')
            );
        }

        if (action === 'import') {
            return window.confirm(
                getI18n('confirm_import', '確定要匯入所選資料嗎？匯入後預設停用，請檢查後再啟用。')
            );
        }

        return true;
    }

    function handleCheckAll() {
        const $checkAll = $(this);
        const checked = $checkAll.prop('checked');
        const $scope = $checkAll.closest('table, .ur-ai-card, .ur-ai-admin-page');

        if ($scope.length) {
            $scope.find(selectors.itemCheckbox).prop('checked', checked);
            return;
        }

        $(selectors.itemCheckbox).prop('checked', checked);
    }

    function syncCheckAllState() {
        const $checkbox = $(this);
        const $scope = $checkbox.closest('table, .ur-ai-card, .ur-ai-admin-page');
        const $checkAll = $scope.find(selectors.checkAll).first();

        if (!$checkAll.length) {
            return;
        }

        const total = $scope.find(selectors.itemCheckbox).length;
        const checked = $scope.find(selectors.itemCheckbox + ':checked').length;

        $checkAll.prop('checked', total > 0 && total === checked);
    }

    function handleBulkSubmit(event) {
        const $form = $(this);
        const action = getBulkAction($form);

        if (!action) {
            return true;
        }

        if (!hasCheckedItems($form)) {
            event.preventDefault();
            window.alert(getI18n('select_items', '請先選擇要操作的項目。'));
            return false;
        }

        if (!confirmByAction(action)) {
            event.preventDefault();
            return false;
        }

        return true;
    }

    function handleDeleteClick(event) {
        const confirmed = window.confirm(
            getI18n('confirm_delete', '確定要刪除這筆資料嗎？此操作無法復原。')
        );

        if (!confirmed) {
            event.preventDefault();
            return false;
        }

        return true;
    }

    function handleConvertFaqClick(event) {
        const confirmed = window.confirm(
            getI18n('confirm_convert_faq', '確定要轉成 FAQ 草稿嗎？轉換後仍需人工檢查後再啟用。')
        );

        if (!confirmed) {
            event.preventDefault();
            return false;
        }

        return true;
    }

    function handleImportClick(event) {
        const confirmed = window.confirm(
            getI18n('confirm_import', '確定要匯入所選資料嗎？匯入後預設停用，請檢查後再啟用。')
        );

        if (!confirmed) {
            event.preventDefault();
            return false;
        }

        return true;
    }

    function copyText(text, $button) {
        if (!text) {
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text)
                .then(function () {
                    showCopySuccess($button);
                })
                .catch(function () {
                    fallbackCopyText(text, $button);
                });

            return;
        }

        fallbackCopyText(text, $button);
    }

    function fallbackCopyText(text, $button) {
        const $temp = $('<textarea />');

        $('body').append($temp);
        $temp.val(text).select();

        try {
            document.execCommand('copy');
            showCopySuccess($button);
        } catch (error) {
            window.alert(getI18n('copy_failed', '複製失敗，請手動選取文字。'));
        }

        $temp.remove();
    }

    function showCopySuccess($button) {
        if (!$button || !$button.length) {
            return;
        }

        const original = $button.text();

        $button
            .text(getI18n('copy_success', '已複製。'))
            .prop('disabled', true);

        window.setTimeout(function () {
            $button
                .text(original)
                .prop('disabled', false);
        }, 1200);
    }

    function handleCopyClick(event) {
        event.preventDefault();

        const $button = $(this);
        let text = $button.data('copy-text') || '';

        if (!text) {
            const target = $button.data('copy-target');

            if (target && $(target).length) {
                text = $(target).text() || $(target).val() || '';
            }
        }

        copyText(text, $button);
    }

    function handleToggleClick(event) {
        event.preventDefault();

        const $button = $(this);
        const target = $button.data('target');
        const $target = target ? $(target) : $button.closest('.ur-ai-toggle-wrap').find(selectors.toggleTarget).first();

        if (!$target.length) {
            return;
        }

        const expanded = $button.attr('aria-expanded') === 'true';

        if (expanded) {
            $target.slideUp(160);
            $button.attr('aria-expanded', 'false');
        } else {
            $target.slideDown(160);
            $button.attr('aria-expanded', 'true');
        }
    }

    function initTabs() {
        $(document).on('click', '.ur-ai-admin-tab', function (event) {
            event.preventDefault();

            const $tab = $(this);
            const target = $tab.data('target');

            if (!target) {
                return;
            }

            $('.ur-ai-admin-tab').removeClass('is-active').attr('aria-selected', 'false');
            $tab.addClass('is-active').attr('aria-selected', 'true');

            $('.ur-ai-admin-tab-panel').hide().removeClass('is-active');
            $(target).show().addClass('is-active');
        });
    }

    function initCharacterCounters() {
        $(document).on('input', '[data-ur-ai-counter-target]', function () {
            const $input = $(this);
            const target = $input.data('ur-ai-counter-target');
            const $target = $(target);

            if (!$target.length) {
                return;
            }

            const value = $input.val() || '';
            $target.text(value.length);
        });

        $('[data-ur-ai-counter-target]').trigger('input');
    }

    function initFormDirtyWarning() {
        let isDirty = false;

        $(document).on('change input', '.ur-ai-admin-form input, .ur-ai-admin-form textarea, .ur-ai-admin-form select', function () {
            isDirty = true;
        });

        $(document).on('submit', '.ur-ai-admin-form', function () {
            isDirty = false;
        });

        window.addEventListener('beforeunload', function (event) {
            if (!isDirty) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        });
    }

    function bindEvents() {
        $(document).on('change', selectors.checkAll, handleCheckAll);
        $(document).on('change', selectors.itemCheckbox, syncCheckAllState);

        $(document).on('submit', selectors.bulkForm, handleBulkSubmit);

        $(document).on('click', selectors.deleteButton, handleDeleteClick);
        $(document).on('click', selectors.convertFaqButton, handleConvertFaqClick);
        $(document).on('click', selectors.importButton, handleImportClick);
        $(document).on('click', selectors.copyButton, handleCopyClick);
        $(document).on('click', selectors.toggleButton, handleToggleClick);
    }

    function init() {
        bindEvents();
        initTabs();
        initCharacterCounters();
        initFormDirtyWarning();
    }

    $(document).ready(init);

})(jQuery);