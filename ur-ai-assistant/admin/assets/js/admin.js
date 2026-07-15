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
        applyIndustryButton: '.ur-ai-apply-industry-button',
        toggleButton: '.ur-ai-toggle-button',
        toggleTarget: '.ur-ai-toggle-target',
        selectAllBanner: '.ur-ai-select-all-banner',
        selectAllConfirm: '.ur-ai-select-all-confirm',
        selectAllCancel: '.ur-ai-select-all-cancel',
        selectAllFlag: '.ur-ai-select-all-flag'
    };

    /**
     * 簡易 sprintf 風格格式化，支援 PHP 慣用的位置型佔位符（%1$s、%2$d…），
     * 對應 wp_localize_script 傳過來、原本設計給 PHP sprintf() 用的 i18n
     * 字串。
     */
    function format(template) {
        const args = Array.prototype.slice.call(arguments, 1);

        return String(template).replace(/%(\d+)\$[ds]/g, function (match, position) {
            const value = args[parseInt(position, 10) - 1];
            return typeof value === 'undefined' ? '' : value;
        });
    }

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

    /**
     * 取得歸屬於某個批次表單的項目勾選框。
     *
     * 部分頁面的勾選框是透過 HTML5 form="..." 屬性歸屬表單（避免每列的
     * 單筆審核／刪除小表單巢狀包在批次表單裡面造成瀏覽器解析錯誤），
     * 不一定是表單在 DOM 上的子節點，因此不能用 $form.find() 找。改用
     * 表單原生的 .elements（會正確包含透過 form="..." 歸屬的欄位，也
     * 相容於仍是 DOM 子節點的舊頁面寫法）。
     */
    function getItemCheckboxes($form) {
        const formEl = $form.get(0);

        if (!formEl || !formEl.elements) {
            return $form.find(selectors.itemCheckbox);
        }

        return $(formEl.elements).filter(selectors.itemCheckbox);
    }

    function hasCheckedItems($form) {
        if (isSelectAllMatchingActive($form)) {
            return true;
        }

        return getItemCheckboxes($form).filter(':checked').length > 0;
    }

    function isSelectAllMatchingActive($form) {
        return $form.find(selectors.selectAllFlag).val() === '1';
    }

    /**
     * 「全選」目前只會勾選畫面上這一頁看得到的項目，不會自動涵蓋其他頁的
     * 資料（分頁／篩選後可能還有更多筆符合條件）。若符合條件的總筆數比
     * 這一頁多，勾選全選時提示是否要改為套用到「全部符合條件」的資料，
     * 而不只是這一頁看到的。實際筆數與頁面筆數由頁面模板寫在
     * data-total-matching／data-page-count 屬性上。
     */
    function maybeShowSelectAllBanner($form) {
        const $banner = $form.find(selectors.selectAllBanner);

        if (!$banner.length) {
            return;
        }

        const totalMatching = parseInt($form.data('total-matching'), 10) || 0;
        const pageCount = parseInt($form.data('page-count'), 10) || 0;

        if (totalMatching <= pageCount) {
            return;
        }

        $banner
            .find('.ur-ai-select-all-banner-text')
            .text(format(getI18n('select_all_prompt', '已選取本頁 %1$s 筆，是否改為選取符合目前篩選條件的全部 %2$s 筆？'), pageCount, totalMatching));

        $banner.find(selectors.selectAllConfirm).show().prop('disabled', false);
        $banner.removeAttr('hidden');
    }

    function hideSelectAllBanner($form) {
        $form.find(selectors.selectAllFlag).val('0');
        $form.find(selectors.selectAllBanner).attr('hidden', 'hidden');
    }

    function confirmSelectAllMatching($form) {
        const totalMatching = parseInt($form.data('total-matching'), 10) || 0;

        $form.find(selectors.selectAllFlag).val('1');

        $form
            .find(selectors.selectAllBanner)
            .find('.ur-ai-select-all-banner-text')
            .text(format(getI18n('select_all_confirmed', '已選取全部 %1$s 筆，套用批次操作時會套用到全部符合條件的資料。'), totalMatching));

        $form.find(selectors.selectAllConfirm).hide();
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

    /**
     * 取得某個勾選框實際歸屬的批次表單。
     *
     * 優先使用瀏覽器原生的 .form（同時支援 DOM 巢狀與 form="..." 屬性
     * 歸屬兩種寫法），找不到時才退回舊的 .closest() 寫法相容尚未套用
     * form="..." 屬性的頁面。
     */
    function getOwningBulkForm($el) {
        const nativeEl = $el.get(0);

        if (nativeEl && nativeEl.form) {
            return $(nativeEl.form);
        }

        return $el.closest(selectors.bulkForm);
    }

    function handleCheckAll() {
        const $checkAll = $(this);
        const checked = $checkAll.prop('checked');
        const $scope = $checkAll.closest('table, .ur-ai-card, .ur-ai-admin-page');

        if ($scope.length) {
            $scope.find(selectors.itemCheckbox).prop('checked', checked);
        } else {
            $(selectors.itemCheckbox).prop('checked', checked);
        }

        const $form = getOwningBulkForm($checkAll);

        if (!$form.length) {
            return;
        }

        if (checked) {
            maybeShowSelectAllBanner($form);
        } else {
            hideSelectAllBanner($form);
        }
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

        // 手動取消勾選單一項目，代表使用者不再是「全部符合條件」的意圖，
        // 跨頁全選狀態應一併取消，避免批次操作套用到超出畫面所見的資料。
        const $form = getOwningBulkForm($checkbox);

        if ($form.length && isSelectAllMatchingActive($form) && checked < total) {
            hideSelectAllBanner($form);
        }
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

    function handleApplyIndustryClick(event) {
        event.preventDefault();

        const $button = $(this);
        const config = getConfig();
        const profiles = config.industry_profiles || {};

        const $industrySelect = $button.closest('.ur-ai-form-row').find('#industry');
        const industryKey = $industrySelect.length ? $industrySelect.val() : '';
        const profile = profiles[industryKey];

        if (!profile) {
            return;
        }

        const confirmMessage = getI18n('confirm_apply_industry', '確定要套用所選產業別的預設文案嗎？');

        if (!window.confirm(confirmMessage)) {
            return;
        }

        const $prompt = $($button.data('target-prompt'));
        const $title = $($button.data('target-title'));
        const $subtitle = $($button.data('target-subtitle'));

        if ($prompt.length && profile.system_prompt) {
            $prompt.val(profile.system_prompt).trigger('input');
        }

        if ($title.length && profile.frontend_title) {
            $title.val(profile.frontend_title).trigger('input');
        }

        if ($subtitle.length && profile.frontend_subtitle) {
            $subtitle.val(profile.frontend_subtitle).trigger('input');
        }
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
        $(document).on('click', selectors.applyIndustryButton, handleApplyIndustryClick);
        $(document).on('click', selectors.toggleButton, handleToggleClick);

        $(document).on('click', selectors.selectAllConfirm, function (event) {
            event.preventDefault();
            confirmSelectAllMatching($(this).closest(selectors.bulkForm));
        });

        $(document).on('click', selectors.selectAllCancel, function (event) {
            event.preventDefault();
            hideSelectAllBanner($(this).closest(selectors.bulkForm));
        });
    }

    function init() {
        bindEvents();
        initTabs();
        initCharacterCounters();
        initFormDirtyWarning();
    }

    $(document).ready(init);

})(jQuery);