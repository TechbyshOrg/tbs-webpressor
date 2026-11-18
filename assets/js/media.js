/* global jQuery, tbswMedia, wp */
(function ($) {
    'use strict';

    if (typeof tbswMedia === 'undefined') {
        return;
    }

    function speak(message) {
        if (wp && wp.a11y && typeof wp.a11y.speak === 'function') {
            wp.a11y.speak(message);
        }
    }

    $(document).on('click', '.tbswebpressor-media-action', function (event) {
        event.preventDefault();

        var $button = $(this);
        var $panel = $button.closest('.tbswebpressor-media-status');
        var operation = $button.data('operation');
        var attachmentId = $button.data('attachment-id');

        if (operation === 'remove' && !window.confirm(tbswMedia.i18n.confirmRemove)) {
            return;
        }
        if (operation === 'exclude' && tbswMedia.i18n.confirmExclude && !window.confirm(tbswMedia.i18n.confirmExclude)) {
            return;
        }

        var $feedback = $panel.find('.tbswebpressor-media-feedback');
        $panel.find('.tbswebpressor-media-action').prop('disabled', true);
        $panel.attr('aria-busy', 'true');
        $feedback.removeClass('is-error').text(tbswMedia.i18n.working);

        $.post(tbswMedia.ajax_url, {
            action: 'tbswebpressor_media_action',
            nonce: tbswMedia.nonce,
            attachment_id: attachmentId,
            operation: operation
        }).done(function (response) {
            if (response && response.success) {
                var $updated = $(response.data.html);
                $panel.replaceWith($updated);
                $updated.find('.tbswebpressor-media-feedback').text(response.data.message);
                // Keep keyboard focus inside the panel that was just re-rendered.
                $updated.find('.tbswebpressor-media-action').first().trigger('focus');
                speak(response.data.message);
                return;
            }
            var message = response && response.data && response.data.message ? response.data.message : tbswMedia.i18n.error;
            $feedback.addClass('is-error').text(message);
            speak(message);
            $panel.find('.tbswebpressor-media-action').prop('disabled', false);
        }).fail(function () {
            $feedback.addClass('is-error').text(tbswMedia.i18n.error);
            speak(tbswMedia.i18n.error);
            $panel.find('.tbswebpressor-media-action').prop('disabled', false);
        }).always(function () {
            $panel.removeAttr('aria-busy');
        });
    });
})(jQuery);
