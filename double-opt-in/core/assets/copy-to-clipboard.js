/**
 * Just copy content to the clipboard
 * using the .copy-to-clipboard class
 *
 * @version 1.0
 */
(function($){
    /*
     * Add the icon which indicates the copy to clipboard
     */
    $(document).ready(function() {
        $(document).find('.copy-to-clipboard').each(function () {
            $(this).wrap('<div class="copy-to-clipboard-wrapper"></div>');
            $(this).parent().append('<div class="copy-icon dashicons dashicons-admin-page"></div>');
        });
    });

    /*
     * Add the copy to clipboard function
     */
    $(document).on('click','.copy-to-clipboard-wrapper .copy-icon', function(){
        var copyText = $(this).parent().find('.copy-to-clipboard').html();
        copyText = copyText.trim();
        navigator.clipboard.writeText(copyText);

        $(this).removeClass('dashicons-admin-page').addClass('dashicons-yes');

        var c = $(this);
        setTimeout(function(){
            c.removeClass('dashicons-yes').addClass('dashicons-admin-page');
        }, 2500);
    });
})(jQuery)