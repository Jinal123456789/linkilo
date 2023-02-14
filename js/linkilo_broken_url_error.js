"use strict";

(function ($) {
    $(document).on('change', '#linkilo_check_all_errors', linkilo_check_all_errors);
    $(document).on('change', '#report_error table input[type="checkbox"]', linkilo_error_delete_button_update);
    $(document).on('click', '#linkilo_error_delete_selected', linkilo_remove_error_links);
    $(document).on('click', '#linkilo_error_filter', linkilo_error_codes_update);
    $(document).on('click', '#error_table_code_filter .item:first-of-type', linkilo_error_codes_toggle);
    $(document).on('click', '.linkilo-error-report-url-edit-confirm', linkilo_error_link_update);
    $(document).on('click', '.column-url .row-actions .linkilo_edit_link, .linkilo-error-report-url-edit-cancel', toggleReportLinkEditor);
    $(document).on('submit', '#linkilo_reset_broken_url_error_data_form', linkilo_reset_broken_url_error_data);

    $(document).click(function(e){
        if (!$(e.target).hasClass('.codes') && !$(e.target).parents('.codes').length) {
            $('#error_table_code_filter .codes').height(30);
            $(this).find('.dashicons-arrow-up').hide();
            $(this).find('.dashicons-arrow-down').show();
        }
    });

    function linkilo_check_all_errors() {
        var checked = false;

        if ($(this).prop('checked')) {
            checked = true;
        }

        $('#report_error table input[type="checkbox"]').each(function(){
            $(this).prop('checked', checked);
        });
    }

    function linkilo_error_delete_button_update() {
        if ($('#report_error table input[type="checkbox"]:checked').length) {
            $('#linkilo_error_delete_selected').removeClass('button-disabled');
        } else {
            $('#linkilo_error_delete_selected').addClass('button-disabled');
        }
    }

    function linkilo_remove_error_links() {
        if (confirm("Are you sure you want to delete this link?")) {
            var links = [];

            $('#report_error table input[type="checkbox"]:checked').each(function () {
                if (parseInt($(this).data('id')) > 0) {
                    links.push($(this).data('id'));
                }
            });

            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: {links: links, action: 'linkilo_remove_error_links'},
                success: function (response) {
                    if (response.error) {
                        linkilo_swal(response.error.title, response.error.text, 'error');
                    } else if (response.success) {
                        location.reload();
                    }
                }
            });
        }
    }

    function linkilo_error_codes_update() {
        var codes = [];
        $('#error_table_code_filter input[type="checkbox"]').each(function(){
            if ($(this).prop('checked')) {
                codes.push($(this).data('code'));
            }
        });

        var post = '';
        var currentPost = parseInt($('#error_table_code_filter input[type="hidden"].current-post').val());
        if(currentPost){
            post = '&post_id=' + currentPost;
        }

        document.location.href = 'admin.php?page=linkilo&type=error&codes='+codes.join(',')+post;
    }

    function linkilo_error_codes_toggle() {
        var block = $('#error_table_code_filter .codes');
        if ($(this).hasClass('closed')) {
            $(this).find('.dashicons-arrow-down').hide();
            $(this).find('.dashicons-arrow-up').css('display', 'inline-block');
            block.css('height', 'auto');
            $(this).removeClass('closed');
            $(this).addClass('open');
        } else {
            $(this).find('.dashicons-arrow-up').hide();
            $(this).find('.dashicons-arrow-down').show();
            block.css('height', 30);
            $(this).removeClass('open');
            $(this).addClass('closed');
        }
    }

    // edit link in error report
    /*  Commented unusable code ref:error_report_js
    function linkilo_error_link_update() {
        var urlRow = $(this).parents('.column-url');
        var el = $(urlRow).find('.linkilo_edit_link');
        var data = {
            action: 'record_edit_url',
            url: el.data('url'),
            new_url: urlRow.find('.linkilo-error-report-url-edit').val(),
            anchor: el.data('anchor'),
            post_id: el.data('post_id'),
            post_type: el.data('post_type'),
            link_id: typeof el.data('link_id') !== 'undefined' ? el.data('link_id') : '',
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
                        if (el.hasClass('linkilo_edit_link')) {
                            el.closest('tr').fadeOut(300);
                        } else {
                            el.closest('li').fadeOut(300);
                        }
                    });
                }
            }
        });
    }*/

    // toggle display of the link editor
    function toggleReportLinkEditor(){
        var urlRow = $(this).parents('.column-url');

        if(urlRow.hasClass('editing-active')){
            urlRow.removeClass('editing-active');
            urlRow.find('.linkilo-error-report-url').css({'display': 'block'});
            urlRow.find('.linkilo-error-report-url-edit-wrapper').css({'display': 'none'});
            urlRow.find('.row-actions').css({'display': 'block'});
        }else{
            urlRow.addClass('editing-active');
            urlRow.find('.linkilo-error-report-url').css({'display': 'none'});
            urlRow.find('.linkilo-error-report-url-edit-wrapper').css({'display': 'inline-block'});
            urlRow.find('.row-actions').css({'display': 'none'});
        }
    }

    //send request to proceed broken links search
    function linkilo_broken_url_error_process()
    {
        $.post(ajaxurl, {
            action: 'linkilo_broken_url_error_process',
        }, function(response){
            // $('.progress_count:first').css('width', response.percents + '%');
            $('.linkilo-loading-status:first').text(response.status);

            if (response.finish) {
                linkilo_swal('Success!', 'Synchronization has been completed.', 'success').then(function(){
                    location.reload();
                });
            } else {
                linkilo_broken_url_error_process();
            }
        });
    }

    //send request to reset data about broken links
    function linkilo_reset_broken_url_error_data(e){
        e.preventDefault();
        var nonce = $(this).find('input[name="nonce"]').val();

        $(this).attr('disabled', true);
        $(this).find('button.button-primary').addClass('linkilo_button_is_active');

        $.post(ajaxurl, {
            action: 'linkilo_reset_broken_url_error_data',
            nonce: nonce
        }, function(response){
            if (typeof response.error != 'undefined') {
                linkilo_swal(response.error.title, response.error.text, 'error');
                return;
            } else if (typeof response.template != 'undefined') {
                $('#wpbody-content').html(response.template);
                linkilo_broken_url_error_process();
            }
        }, 'json');
    }

    //show progress bar and send search request if user interrupted the search
    if (typeof error_reset_run != 'undefined' && error_reset_run) {
        $.post(ajaxurl, {
            action: 'linkilo_broken_url_error_process',
            get_status: 1
        }, function(response){
            // $('.progress_count:first').css('width', response.percents + '%');
            $('.linkilo-loading-status:first').text(response.status);
            linkilo_broken_url_error_process();
        });
    }

    $(document).on('change', '#linkilo_error_table_post_filter select', linkilo_report_filter);
    $(document).on('click', '#linkilo_error_table_post_filter .button-primary', linkilo_report_filter_submit);

    function linkilo_report_filter() {
        var block = $('#linkilo_error_table_post_filter');

        var post_type = block.find('select[name="post_type"]').val();

        $('.linkilo_filter_post_type:not(.' + post_type + ')').css({'display': 'none'});
        $('.linkilo_filter_post_type.' + post_type).css({'display': 'block'});

        if($(this).attr('name') === 'post_type'){
            block.find('select[name="category"]').val(0);
        }
    }
    linkilo_report_filter();

    function linkilo_report_filter_submit() {
        var block = $(this).closest('div');
        var post_type = block.find('select[name="post_type"]').val();
        var category = block.find('select[name="category"]').val();
        var urlParams = parseURLParams(location.href);
        var codes = (urlParams.codes) ? 'codes=' + encodeURIComponent(urlParams.codes[0]) : '';
        var url = admin_url + 'admin.php?page=linkilo&type=error&' + codes + '&post_type=' + post_type + '&category=' + category;

        location.href = url;
    }

    /**
     * Helper function that parses urls to get their query vars.
     **/
	function parseURLParams(url) {
		var queryStart = url.indexOf("?") + 1,
			queryEnd   = url.indexOf("#") + 1 || url.length + 1,
			query = url.slice(queryStart, queryEnd - 1),
			pairs = query.replace(/\+/g, " ").split("&"),
			parms = {}, i, n, v, nv;
	
		if (query === url || query === "") return;
	
		for (i = 0; i < pairs.length; i++) {
			nv = pairs[i].split("=", 2);
			n = decodeURIComponent(nv[0]);
			v = decodeURIComponent(nv[1]);
	
			if (!parms.hasOwnProperty(n)) parms[n] = [];
			parms[n].push(nv.length === 2 ? v : null);
		}
		return parms;
	}

})(jQuery);
