"use strict";

(function ($) {
    $(document).on('click', '.linkilo-domains-report-url-edit-confirm', linkilo_domains_link_update);
    $(document).on('click', '.report_links .linkilo_edit_link, .linkilo-domains-report-url-edit-cancel', toggleReportLinkEditor);

    // edit link in domains report
    function linkilo_domains_link_update() {
        var urlRow = $(this).parents('.linkilo-domains-report-url-edit-wrapper');
        var el = $(this);
        var data = {
            action: 'record_edit_url',
            url: el.data('url'),
            new_url: urlRow.find('.linkilo-domains-report-url-edit').val(),
            anchor: el.data('anchor'),
            post_id: el.data('post_id'),
            post_type: el.data('post_type'),
            link_id: typeof el.data('link_id') !== 'undefined' ? el.data('link_id') : '',
            status: 'domains',
            nonce: el.data('nonce')
        };

        // make the call to update the link
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: data,
            success: function(response){
                console.log(response);
                // if there was an error
                if(response.error){
                    // output the error message
                    linkilo_swal(response.error.title, response.error.text, 'error');
                }else if(response.success){
                    // if it was successful, output the succcess message
                    linkilo_swal(response.success.title, response.success.text, 'success').then(function(){
                        // and remove the link from the table when the user closes the popup
                        el.closest('li').fadeOut(300);
                    });
                }
            }
        });
    }

    // toggle display of the link editor
    function toggleReportLinkEditor(e){
        e.preventDefault();
        var urlRow = $(this).parents('li');

        if(urlRow.hasClass('editing-active')){
            urlRow.removeClass('editing-active');
            urlRow.find('.linkilo-domains-report-url').css({'display': 'block'});
            urlRow.find('.linkilo-domains-report-url-edit-wrapper').css({'display': 'none'});
            urlRow.find('.row-actions').css({'display': 'block'});
            urlRow.find('.linkilo_edit_link').css({'display': 'block'});
        }else{
            urlRow.addClass('editing-active');
            urlRow.find('.linkilo-domains-report-url').css({'display': 'none'});
            urlRow.find('.linkilo-domains-report-url-edit-wrapper').css({'display': 'inline-block'});
            urlRow.find('.row-actions').css({'display': 'none'});
            urlRow.find('.linkilo_edit_link').css({'display': 'none'});
        }
    }
})(jQuery);
