"use strict";

(function ($) {
    if($('#linkilo-click-detail-daterange').length > 0){
        $('#linkilo-click-detail-daterange').daterangepicker({
            autoUpdateInput: false,
            linkedCalendars: false,
            locale: {
                cancelLabel: 'Clear'
            }
        });
    }

    $('#linkilo-click-detail-daterange').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
        var start = picker.startDate.format();
        var end = picker.endDate.format();
        var url = window.location.href;

        // update the url with the info from the date picker
        url = updateURLParameter(url, 'start_date', start);
        url = updateURLParameter(url, 'end_date', end);

        // reload the page with the date range settings
        window.location.href = url;
    });

    function updateURLParameter(url, param, paramVal)
    {
        var TheAnchor = null;
        var newAdditionalURL = "";
        var tempArray = url.split("?");
        var baseURL = tempArray[0];
        var additionalURL = tempArray[1];
        var temp = "";
    
        if (additionalURL) 
        {
            var tmpAnchor = additionalURL.split("#");
            var TheParams = tmpAnchor[0];
                TheAnchor = tmpAnchor[1];
            if(TheAnchor)
                additionalURL = TheParams;
    
            tempArray = additionalURL.split("&");
    
            for (var i=0; i<tempArray.length; i++)
            {
                if(tempArray[i].split('=')[0] != param)
                {
                    newAdditionalURL += temp + tempArray[i];
                    temp = "&";
                }
            }        
        }
        else
        {
            var tmpAnchor = baseURL.split("#");
            var TheParams = tmpAnchor[0];
                TheAnchor  = tmpAnchor[1];
    
            if(TheParams)
                baseURL = TheParams;
        }
    
        if(TheAnchor)
            paramVal += "#" + TheAnchor;
    
        var rows_txt = temp + "" + param + "=" + paramVal;
        return baseURL + "?" + newAdditionalURL + rows_txt;
    }

    $('#linkilo_clear_clicks_data_form').on('submit', clearClickData);

    /**
     * Makes the call to clear the click data when the user clicks the button.
     **/
     function clearClickData(e){
        e.preventDefault();
        var form = $(this);
        var nonce = form.find('[name="nonce"]').val();
       
        if(!nonce || form.attr('disabled')){
            return;
        }
        
        // disable the reset button
        form.attr('disabled', true);
        // add a color change to the button indicate it's disabled
        form.find('button.button-primary').addClass('linkilo_button_is_active');
        processClearClicks(nonce, form);
    }

    /**
     * Makes the ajax call to clear the click data from the site
     **/
    function processClearClicks(nonce = null, form){
        if(!nonce){
            return;
        }

        jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'linkilo_clear_url_click_data',
                clear_data: 1,
                nonce: nonce,
			},
            error: function (jqXHR, textStatus) {
				var wrapper = document.createElement('div');
				$(wrapper).append('<strong>' + textStatus + '</strong><br>');
				$(wrapper).append(jqXHR.responseText);
				linkilo_swal({"title": "Error", "content": wrapper, "icon": "error"}).then(function(){
                    form.attr('disabled', false);
                    form.find('button.button-primary').removeClass('linkilo_button_is_active');
                });
			},
			success: function(response){
                // if there was an error
                if(response.error){
                    linkilo_swal(response.error.title, response.error.text, 'error').then(function(){
                        form.attr('disabled', false);
                        form.find('button.button-primary').removeClass('linkilo_button_is_active');
                    });
                    return;
                }
                
                // if the clicks have been successfully cleared
                if(response.success){
                    linkilo_swal(response.success.title, response.success.text, 'success').then(function(){
                        form.attr('disabled', false);
                        form.find('button.button-primary').removeClass('linkilo_button_is_active');
                        location.reload();
                    });
                    return;
                }
			}
		});
    }

    $(document).on('click', '.linkilo_delete_url_click_data', deleteClickData);

    /**
     * Deletes the click data for a specific post or URL via ajax.
     * Requests the user to confirm that he wants to delete the data
     **/
    function deleteClickData(){
        var button = $(this);
        var text = $('input[name="click_delete_confirm_text"]').val();

        linkilo_swal({
            title: 'Please Confirm',
            text: (text) ? text: 'Do you really want to delete all the click data in the row?',
            icon: 'info',
            buttons: {
                cancel: true,
                confirm: true,
            },
            }).then((delete_data) => {
                if(delete_data){
                    jQuery.ajax({
                        type: 'POST',
                        url: ajaxurl,
                        data: {
                            action: 'linkilo_delete_url_click_data',
                            click_id: button.data('click_id'),
                            post_id: button.data('post_id'),
                            post_type: button.data('post_type'),
                            anchor: button.data('anchor'),
                            url: button.data('url'),
                            nonce: button.data('nonce')
                        },
                        error: function (jqXHR, textStatus) {
                            var wrapper = document.createElement('div');
                            $(wrapper).append('<strong>' + textStatus + '</strong><br>');
                            $(wrapper).append(jqXHR.responseText);
                            linkilo_swal({"title": "Error", "content": wrapper, "icon": "error"}).then(function(){
                                form.attr('disabled', false);
                                form.find('button.button-primary').removeClass('linkilo_button_is_active');
                            });
                        },
                        success: function(response){
                            // if there was an error
                            if(response.error){
                                linkilo_swal(response.error.title, response.error.text, 'error');
                                return;
                            }
                            
                            // if the clicks have been successfully cleared
                            if(response.success){
                                linkilo_swal(response.success.title, response.success.text, 'success').then(function(){
                                    window.location.reload();
                                });
                                return;
                            }
                        }
                    });
                }
        });
    }

    $(document).on('change', '#linkilo_url_clicks_table_filter select', linkilo_report_filter);
    $(document).on('click', '#linkilo_url_clicks_table_filter .button-primary', linkilo_report_filter_submit);
    function linkilo_report_filter() {
        var block = $('#linkilo_links_table_filter');

        var post_type = block.find('select[name="post_type"]').val();
        if (post_type != 0 && post_type !== 'post') {
            block.find('select[name="category"]').val(0).prop('disabled', true);
        } else {
            block.find('select[name="category"]').prop('disabled', false);
        }

        var category = block.find('select[name="category"]').val();
        if (category != 0) {
            block.find('select[name="post_type"]').val('post');
        }

        var filterType = block.find('select[name="filter_type"]').val();

        switch (filterType) {
            case '2':
                block.find('.filter-by-type, .filter-by-count').css({'display': 'inline-block'});
            break;
            case '1':
                block.find('.filter-by-type').css({'display': 'none'});
                block.find('.filter-by-count').css({'display': 'inline-block'});
            break;
            case '0':
                block.find('.filter-by-type').css({'display': 'inline-block'});
                block.find('.filter-by-count').css({'display': 'none'});
            default:
            break;
        }

        if (post_type != 0 && post_type !== 'post') {
            block.find('select[name="category"]').val(0).prop('disabled', true);
        } else {
            block.find('select[name="category"]').prop('disabled', false);
        }
    }
    linkilo_report_filter();

    function linkilo_report_filter_submit() {
        var block = $(this).closest('div');
        var filterType = 0; //block.find('select[name="filter_type"]').val();
        var linkType = block.find('select[name="link_type"]').val();
        var linkCountMin = block.find('input[name="link_min_count"]').val();
        var linkCountMax = block.find('input[name="link_max_count"]').val();
        var post_type = block.find('select[name="click_post_type"]').val();
        var category = block.find('select[name="category"]').val();
        var filterNonce = block.find('.post-filter-nonce').val();
        var url = admin_url + 'admin.php?page=linkilo&type=clicks&filter_type=' + filterType;

        switch (filterType) {
            case '2':
                url += '&post_type=' + post_type + '&category=' + category + '&link_type=' + linkType + '&link_min_count=' + linkCountMin;
                if(linkCountMax !== ''){
                    url += '&link_max_count=' + linkCountMax;
                }
            break;
            case '1':
                url += '&link_type=' + linkType + '&link_min_count=' + linkCountMin;
                if(linkCountMax !== ''){
                    url += '&link_max_count=' + linkCountMax;
                }
            break; 
            case '0':
            default:
                url += '&post_type=' + post_type;
            break;
        }
        console.log(url);
        // save the updated filter settings
        //updateFilterSettings(post_type, category, filterNonce, url);

        location.href = url;
    }

    function updateFilterSettings(postType = '', category = '', filterNonce, url){
        var data = {
            action: 'linkilo_save_user_filter_settings',
            post_type: postType,
            category: category,
            setting_type: 'report',
            nonce: filterNonce
        };

        $.ajax({
            url: ajaxurl,
            dataType: 'json',
            data: data,
            method: 'post',
            error: function (jqXHR, textStatus, errorThrown) {
                linkilo_swal('Error', errorThrown, 'error');
            },
            success: function (response) {
                location.href = url;
            }
        });
    }



})(jQuery);