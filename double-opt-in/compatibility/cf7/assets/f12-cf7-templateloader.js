jQuery(document).ready(function ($) {
    var active_xhr_request = null;

    $('.f12-cf7-templateloader').on('change', function(){
        let value = $(this).val();

        if (active_xhr_request != null) {
            active_xhr_request.abort();
        }

        // add overlay to textarea while loading the new template
        if($('#templateloader-overlay').length <= 0) {
            $('textarea[name="_fusion[doubleoptin][body]"]').parent().css('position', 'relative');
            $('textarea[name="_fusion[doubleoptin][body]"]').parent().append('<div id="templateloader-overlay" style="border-radius:5px; position:absolute; background-color:rgba(255,255,255,0.9); left:0; top:0; right:0; bottom:0;"><div style="position:absolute; top:45%; width:100%; text-align:center;">' + templateloader.label_placeholder + '</div></div>');
        }

        if (value) {
            $(this).parent().parent().parent().find('.active').removeClass('active');
            $(this).parent().addClass('active');

            active_xhr_request = $.ajax({
                url: templateloader.ajax_url,
                type: 'post',
                data: {
                    action: 'f12_doi_templateloader',
                    nonce: templateloader.nonce,
                    template: value
                },
                success(data) {
                    var data = JSON.parse(data);
                    if (data.status == 200) {
                        if ($('textarea[name="_fusion[doubleoptin][body]"]').length > 0) {
                            $('textarea[name="_fusion[doubleoptin][body]"]').val(data.content);

                            // set a little delay to have a smooth gradient
                            setTimeout(function () {
                                $('#templateloader-overlay').fadeOut().remove();
                            }, 1000);

                            active_xhr_request = null;
                        }
                    }
                }
            })
        }
    });


    $('.f12-cf7-templateloader-preview').on('click', function () {
        let value = $(this).attr('data-template');

        if (active_xhr_request != null) {
            active_xhr_request.abort();
        }

        // add overlay to textarea while loading the new template
        if($('#templateloader-overlay').length <= 0) {
            $('#doubleoptin-body').parent().css('position', 'relative');
            $('#doubleoptin-body').parent().append('<div id="templateloader-overlay" style="border-radius:5px; position:absolute; background-color:rgba(255,255,255,0.9); left:0; top:0; right:0; bottom:0;"><div style="position:absolute; top:45%; width:100%; text-align:center;">' + templateloader.label_placeholder + '</div></div>');
        }

        if (value) {
            $(this).parent().parent().parent().find('.active').removeClass('active');
            $(this).parent().addClass('active');

            active_xhr_request = $.ajax({
                url: templateloader.ajax_url,
                type: 'post',
                data: {
                    action: 'f12_doi_templateloader',
                    nonce: templateloader.nonce,
                    template: value
                },
                success(data) {
                    var data = JSON.parse(data);
                    if (data.status == 200) {
                        if ($('#doubleoptin-body').length > 0) {
                            $('#doubleoptin-body').val(data.content);
                            $('.f12-cf7-templateloader').val(value);
                            $('.f12-cf7-templateloader').change();

                            // set a little delay to have a smooth gradient
                            setTimeout(function(){
                                $('#templateloader-overlay').fadeOut().remove();
                            },1000);

                            active_xhr_request = null;
                        }
                    }
                }
            })
        }
    });
});