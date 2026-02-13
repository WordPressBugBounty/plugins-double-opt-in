jQuery(document).ready(function ($) {
    $('.f12-cf7-details').on('click', function () {
        var hash = $(this).attr('data-hash');

        if (hash) {
            $.ajax({
                url: doi.ajax_url,
                type: 'post',
                data: {
                    action: 'f12_doi_details',
                    nonce: doi.nonce,
                    hash: hash
                },
                success(data) {
                    var data = JSON.parse(data);
                    showPopup(data.content);
                }
            })
        }
    });

    function removePopup() {
        $(document).find('.forge12-plugin-popup, .forge12-plugin-popup-overlay').remove();
    }

    function showPopup(content) {
        removePopup();
        $('body').append(
            '<div class="forge12-plugin-popup-overlay"></div>' +
            '<div class="forge12-plugin-popup">' +
                '<button type="button" class="forge12-plugin-popup-close">' +
                    '<span class="dashicons dashicons-no-alt"></span>' +
                '</button>' +
                '<div class="forge12-plugin-popup-content">' + content + '</div>' +
            '</div>'
        );
    }

    $(document).on('click', '.forge12-plugin-popup-close', function () {
        removePopup();
    });

    $(document).on('click', '.forge12-plugin-popup-overlay', function () {
        removePopup();
    });

    $(document).on('mouseenter', '.forge12-plugin-popup .doi-tooltip', function () {
        var $tip = $(this).find('.doi-tooltip-text');
        var rect = this.getBoundingClientRect();
        var tipW = 260;
        var left = rect.left + rect.width / 2 - tipW / 2;
        if (left < 8) left = 8;
        if (left + tipW > window.innerWidth - 8) left = window.innerWidth - tipW - 8;
        $tip.css({ left: left + 'px', bottom: (window.innerHeight - rect.top + 8) + 'px' });
    });
});