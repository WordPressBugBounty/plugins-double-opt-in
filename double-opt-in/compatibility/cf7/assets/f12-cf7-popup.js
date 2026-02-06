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
        $(document).find('.forge12-plugin-popup').remove();
    }

    function showPopup(content) {
        removePopup();
        $('body').append('<div class="forge12-plugin-popup"><div class="forge12-plugin-popup-close">X</div><div class="forge12-plugin-popup-content">' + content + '</div></div>');
    }

    $(document).on('click', '.forge12-plugin-popup-close', function () {
        removePopup();
    });
});