"use strict";

(function ($) {
    $(document).on('click', '#linkilo_url_changer_table .delete', linkilo_url_changer_delete);
    $(document).on('click', '#linkilo_url_changer_reset_button', linkilo_url_changer_reset);

    if (is_linkilo_url_changer_reset) {
        linkilo_url_changer_reset_process(2, 1);
    }

    function linkilo_url_changer_delete() {
        if (confirm("Are you sure you want to delete this URL?")) {
            var el = $(this);
            var id = el.data('id');

            $.post(ajaxurl, {
                action: 'linkilo_url_changer_delete',
                id: id
            }, function(){
                el.closest('tr').fadeOut(300);
                linkilo_swal('Success!', 'URLs have been replaced with the old ones.', 'success');
            });
        }
    }

    function linkilo_url_changer_reset() {
        $('#linkilo_url_changer_table .table').hide();
        $('#linkilo_url_changer_table .progress').show();
        linkilo_url_changer_reset_process(1, 1);
    }

    function linkilo_url_changer_reset_process(count, total) {
        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
                action: 'linkilo_url_changer_reset',
                nonce: linkilo_url_changer_nonce,
                count: count,
                total: total,
            },
            error: function (jqXHR, textStatus, errorThrown) {
                var wrapper = document.createElement('div');
                $(wrapper).append('<strong>' + textStatus + '</strong><br>');
                $(wrapper).append(jqXHR.responseText);
                linkilo_swal({"title": "Error", "content": wrapper, "icon": "error"}).then(function(){
                    linkilo_url_changer_reset_process(1, 1)
                });
            },
            success: function(response){
                if (response.error) {
                    linkilo_swal(response.error.title, response.error.text, 'error');
                    return;
                }

                // var progress = Math.floor((response.ready / response.total) * 100);
                // $('#linkilo_url_changer_table .progress .progress_count').text(progress + '%' + ' ' + response.ready + '/' + response.total);
                if (response.finish) {
                    location.reload();
                } else {
                    linkilo_url_changer_reset_process(response.count, response.total)
                }
            }
        });
    }
})(jQuery);