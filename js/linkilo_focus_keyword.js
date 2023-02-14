"use strict";

(function ($) {
    $(document).on('click', '#linkilo_focus_keyword_reset_button', linkilo_focus_keyword_reset);

    var is_linkilo_focus_keyword_reset = (undefined !== is_linkilo_focus_keyword_reset) ? is_linkilo_focus_keyword_reset : false;

    if (is_linkilo_focus_keyword_reset) {
        linkilo_focus_keyword_reset_process(2, 1);
    }

    function linkilo_focus_keyword_reset() {
        var text = $('#linkilo_focus_keyword_reset_notice').val();
        var auth = $('#linkilo_focus_keyword_gsc_authenticated').val();
        var authText = ($('#linkilo_focus_keyword_gsc_not_authtext_a').val() + '\n\n' + $('#linkilo_focus_keyword_gsc_not_authtext_b').val());

        if(auth < 1 && false){ // todo make into separate button or remove
            linkilo_swal({
                title: 'Linkilo Not Authorized',
                text: (authText) ? authText: 'Linkilo can not connect to Google Search Console because it has not been authorized yet. \n\n Please go to the Linkilo Settings and authorize access.',
                icon: 'info',
            });
            return;
        }


        linkilo_swal({
            title: 'Please Confirm',
            text: (text) ? text: 'Please confirm refreshing the focus keywords. If you\'ve authenticated the connection to Google Search Console, this will refresh the keyword data.',
            icon: 'info',
            buttons: {
                cancel: true,
                confirm: true,
            },
            }).then((reset) => {
              if(reset){
                $('#linkilo_focus_keyword_table .table').hide();
                $('#linkilo_focus_keyword_table .progress').show();
                linkilo_focus_keyword_reset_process(1, 1, true);
              }
            });
    }

    function linkilo_focus_keyword_reset_process(count, total, reset = false) {
        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
                action: 'linkilo_focus_keyword_reset',
                nonce: linkilo_focus_keyword_nonce,
                count: count,
                total: total,
                reset: reset,
            },
            error: function (jqXHR, textStatus, errorThrown) {
                var wrapper = document.createElement('div');
                $(wrapper).append('<strong>' + textStatus + '</strong><br>');
                $(wrapper).append(jqXHR.responseText);
                linkilo_swal({"title": "Error", "content": wrapper, "icon": "error"}).then(function(){
                    location.reload();
                });
            },
            success: function(response){
                console.log(response);
                if (response.error) {
                    linkilo_swal(response.error.title, response.error.text, 'error');
                    return;
                }

                var state = false;
                switch (response.state) {
                    case 'gsc_query':
                        state = 'Getting Google Search Console Data. ' + (5000 * response.gsc_row) + ' Items Queried';
                        break;
                    case 'gsc_process':
                        state = 'Processing Google Search Console Data';
                        break;
                    case 'yoast_process':
                        state = 'Processing Yoast Keyword Data';
                        break;
                    case 'rank_math_process':
                        state = 'Processing Rank Math Keyword Data';
                        break;                    
                    case 'aioseo_process':
                        state = 'Processing All in One SEO Keyword Data';
                        break;
                    case 'custom_process':
                        state = 'Processing Custom Keywords';
                        break;
                }

                if(state){
                    //$('.progress_count').html(state);
                }

                if (response.finish) {
                    location.reload();
                } else {
                    linkilo_focus_keyword_reset_process(response.count, response.total)
                }
            }
        });
    }

    $(document).on('change', '#linkilo_focus-keywords input[type="checkbox"]', function(){
        var keyId = $(this).val();
        var checked = $(this).is(':checked');

        $('.keyword-' + keyId).prop('checked', checked);
    });

    $(document).on('click', '.linkilo-update-selected-keywords', updateSelectedKeywords);
    function updateSelectedKeywords(e){
        e.preventDefault();
        var button = $(this),
            data = {};

        // if we're update keywords from the post/term edit pages
        if($('#linkilo_focus-keywords').length){
            $('#linkilo_focus-keywords').find('input').each(function(index, element){
                var el = $(element);
                if(undefined === el.data('keyword-id')){
                    return;
                }
                console.log(el);
                data[el.data('keyword-id')] = el.is(':checked');
            });
        }else{
            $(this).parents('.linkilo-collapsible-wrapper').find('input').each(function(index, element){
                var el = $(element);
                console.log(el);
                data[el.data('keyword-id')] = el.is(':checked');
            });
        }

        if(!data){
            linkilo_swal('Error', 'There were no keywords found in the dropdown!', 'error');
            return;
        }

        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
                action: 'linkilo_update_selected_focus_keyword',
                nonce: $(this).data('nonce'),
                post_id: $(this).data('post-id'),
                selected: data
            },
            error: function (jqXHR, textStatus, errorThrown) {
                var wrapper = document.createElement('div');
                $(wrapper).append('<strong>' + textStatus + '</strong><br>');
                $(wrapper).append(jqXHR.responseText);
                linkilo_swal({"title": "Error", "content": wrapper, "icon": "error"}).then(function(){
                    location.reload();
                });
            },
            success: function(response){
                if (response.error) {
                    linkilo_swal(response.error.title, response.error.text, 'error');
                    return;
                }else if(response.success){
                    if($('.linkilo-admin_page_linkilo_focus_keywords').length > 0){
                        var noItems = true;
                        for(var i in data){
                            // if the keyword is active, show the keyword
                            if(data[i]){
                                noItems = false;
                                $('#active-keyword-' + i).css({'display': 'inline-block'});
                            }else{
                                $('#active-keyword-' + i).css({'display': 'none'});
                            }

                        }

                        if(noItems){
                            button.parents('tr').find('.no-active-keywords-notice').css({'display': 'inline-block'});
                        }else{
                            button.parents('tr').find('.no-active-keywords-notice').css({'display': 'none'});
                        }
                    }

                    linkilo_swal(response.success.title, response.success.text, 'success');
                }
            }
        });
    }

    var addingKeywords = false;
    $(document).on('click', '.linkilo-create-focus-keywords', addCustomTargetKeywords);
    function addCustomTargetKeywords(e){
        e.preventDefault();

        if(addingKeywords){
            return;
        }

        addingKeywords = true;

        var button = $(this);
        var parent = $(this).parents('.create-post-keywords');
        var keywords = [];
        $(parent).find('.create-custom-focus-keyword-input').each(function(index, element){
            var keyword = $(element).val();
            if(keyword){
                keywords.push(keyword);
            }
        });
        var wrapper = button.parents('.linkilo-collapsible-wrapper');

        if(!keywords){
            linkilo_swal('Keyword empty', 'Please enter a keyword in the New Custom Keyword field.', 'info');
            addingKeywords = false;
            return;
        }

        // flip the array so the keywords are inserted from top to bottom
        keywords.reverse();

        button.addClass('linkilo_button_is_active');

        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
                action: 'linkilo_add_custom_focus_keyword',
                nonce: button.data('nonce'),
                post_id: button.data('post-id'),
                post_type: button.data('post-type'),
                keywords: keywords
            },
            error: function (jqXHR, textStatus, errorThrown) {
                linkilo_swal('Error', textStatus + "\n\n" + jqXHR.responseText, 'error').then(function(){
                    location.reload();
                });
            },
            success: function(response){
                console.log(response);
                if (response.error) {
                    linkilo_swal(response.error.title, response.error.text, 'error');
                    return;
                }else if(response.success){
                    if($('.linkilo-admin_page_linkilo_focus_keywords').length > 0){
                        linkilo_swal(response.success.title, response.success.text, 'success').then(function(){
                            var activeKeywordsCloud = button.parents('tr').find('.column-word_cloud ul');
                            var activeKeyword = '';

                            $(response.success.data).each(function(index, dat){
                                button.parents('.linkilo-collapsible-wrapper').find('.report_links').append(dat.reportRow);
                                activeKeyword += '<li id="active-keyword-' + dat.keywordId + '" class="linkilo-focus-keyword-active-kywrd">' + dat.keyword + '</li>';
                            });

                            $(activeKeywordsCloud).find('.no-active-keywords-notice').css({'display': 'none'});
                            $(activeKeywordsCloud).append(activeKeyword);

                            // update the custom keyword count
                            var count = wrapper.find('.report_links li:visible').length;
                            wrapper.find('.linkilo-collapsible').text(count);
                        });
                    }else{
                        linkilo_swal(response.success.title, response.success.text, 'success').then(function(){
                            console.log(button.parents('#linkilo-keyword-select-metabox').find('#keywordchecklist'));

                            $(response.success.data).each(function(index, dat){
                                button.parents('#linkilo-keyword-select-metabox').find('#keywordchecklist').append(dat.suggestionRow);
                                button.parents('#linkilo-keyword-select-metabox').find('#keywordchecklist-custom').append(dat.suggestionRow);
                            });
                        });
                    }

                    parent.find('.input-clone').remove();
                    parent.find('.create-custom-focus-keyword-input').val('');
                }
            },
            complete: function(){
                button.removeClass('linkilo_button_is_active');
                addingKeywords = false;
            }
        });
    }

    $(document).on('click', '.linkilo-add-focus-keyword-row', addTargetKeywordRow);
    function addTargetKeywordRow(e){
        e.preventDefault();
        var parent = $(this).parents('.create-post-keywords');
        $(parent).find('.linkilo-create-focus-keywords-row-container').append('<input type="text" style="width: 100%; margin-top:5px;" class="create-custom-focus-keyword-input input-clone" placeholder="New Custom Keyword">');
    }

    var deletingKeywords = false;
    $(document).on('click', '.linkilo_focus_keyword_delete', deleteCustomTargetKeywords);
    function deleteCustomTargetKeywords(e){
        e.preventDefault();

        if(deletingKeywords){
            return;
        }

        deletingKeywords = true;
        var keywordId = $(this).data('keyword-id');
        var nonce = $(this).data('nonce');
        var button = $(this);
        var wrapper = $(this).parents('.linkilo-collapsible-wrapper');

        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
                action: 'linkilo_remove_custom_focus_keyword',
                nonce: nonce,
                keyword_id: keywordId,
            },
            error: function (jqXHR, textStatus, errorThrown) {
                var wrapper = document.createElement('div');
                $(wrapper).append('<strong>' + textStatus + '</strong><br>');
                $(wrapper).append(jqXHR.responseText);
                linkilo_swal({"title": "Error", "content": wrapper, "icon": "error"}).then(function(){
                    location.reload();
                });
            },
            success: function(response){
                console.log(response);
                if (response.error) {
                    linkilo_swal(response.error.title, response.error.text, 'error');
                    return;
                }else if(response.success){
                    if($('.linkilo-admin_page_linkilo_focus_keywords').length > 0){
                        linkilo_swal(response.success.title, response.success.text, 'success').then(function(){
                            $('#focus-keyword-' + keywordId).fadeOut(300, function(){
                                var count = wrapper.find('.report_links li:visible').length;
                                wrapper.find('.linkilo-collapsible').text(count);
                            });

                            // hide the active keyword button
                            $('#active-keyword-' + keywordId).fadeOut(300, function(){
                                var activeKeywordsCount = button.parents('tr').find('.column-word_cloud li:visible').length;
                                if(activeKeywordsCount < 1){
                                    button.parents('tr').find('.column-word_cloud li.no-active-keywords-notice').css({'display': 'inline-block'});
                                }
                            });
                            
                        });
                    }else{
                        linkilo_swal(response.success.title, response.success.text, 'success').then(function(){
                            $('#keyword-all-' + keywordId + ', #keyword-custom-' + keywordId).fadeOut(300);
                        });
                    }
                }
            },
            complete: function(){
                deletingKeywords = false;
            }
        });
    }

    $(document).on('click', '.linkilo-incoming-focus-keyword-edit-button .button-primary', toggleIncomingTargetKeywordForm);
    function toggleIncomingTargetKeywordForm(){
        $('.linkilo-incoming-focus-keyword-edit-form').toggle();
        saveIncomingTargetKeywordVisibility(this);
    }

    function saveIncomingTargetKeywordVisibility(button){
        var visible = $('.linkilo-incoming-focus-keyword-edit-form').is(':visible');
        var nonce = $(button).data('nonce');

        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
                action: 'linkilo_save_incoming_focus_keyword_visibility',
                nonce: nonce,
                visible: (visible) ? 1: 0,
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log(textStatus);
            },
            success: function(response){
                console.log(response);

            },
        });
    }

    // switch tabs in the keyword metabox
    /*Temporary blocked js for tab switch*/
    $('#linkilo-keyword-select-metabox #keyword-tabs a').on('click', switchTab);
    function switchTab(e){
        e.preventDefault();
        $('.keyword-tab').removeClass('tabs');
        $(this).parents('li').addClass('tabs'),
        $('#linkilo-keyword-select-metabox .tabs-panel').css({'display': 'none'});
        var tab = $(this).data('keyword-tab')
        $('#' + tab).css({'display': 'block'});

        var visibleInputs = $('#linkilo-keyword-select-metabox .tabs-panel input:visible');

        if(tab !== 'keywords-custom' && visibleInputs.length > 0){
            $('.linkilo-update-selected-keywords').css({'display': 'inline-block'});
        }else{
            $('.linkilo-update-selected-keywords').css({'display': 'none'});
        }
    }

    $(document).on('click', '#linkilo_links_table_filter .button-primary', linkilo_report_filter_submit);

    function linkilo_report_filter_submit() {
        var block = $(this).closest('div');
        var post_type = block.find('select[name="keyword_post_type"]').val();
        var filterNonce = block.find('.post-filter-nonce').val();
        var url = admin_url + 'admin.php?page=linkilo_focus_keywords';

        if(post_type){
            url += '&keyword_post_type=' + post_type;
        }

        // save the updated filter settings
        updateFilterSettings(post_type, '', filterNonce, url);
    }

    function updateFilterSettings(postType = '', category = '', filterNonce, url){
        var data = {
            action: 'linkilo_save_user_filter_settings',
            post_type: postType,
            category: category,
            setting_type: 'focus_keywords',
            nonce: filterNonce
        };

        $.ajax({
            url: ajaxurl,
            dataType: 'json',
            data: data,
            method: 'post',
            error: function (jqXHR, textStatus, errorThrown) {
                linkilo_swal('Error', msg, 'error');
            },
            success: function (response) {
                location.href = url;
            }
        });
    }

})(jQuery);