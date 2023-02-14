"use strict";

(function ($)
{
    
    var hasChangedLanguage = false;



    $('.settings-carrot').on('click', openCloseSettings);
    function openCloseSettings(){
        var $setting = $(this),
            active = $setting.hasClass('active');
        if(active){
            $setting.removeClass('active');
            $(this).closest('tr').find('.setting-control').css({'height': '0px', 'overflow': 'hidden'});
        }else{
            $setting.addClass('active');
            $(this).closest('tr').find('.setting-control').css('height', 'initial');
        }
    }
    
    $('#linkilo-selected-language').on('change', updateDisplayedIgnoreWordList);
    function updateDisplayedIgnoreWordList(){

        var wordLists = $('#linkilo-available-language-word-lists').val(),
            selectedLang = $('#linkilo-selected-language').val();
        if(!wordLists){
            return;
        }

        if(!hasChangedLanguage){
            var str1 = $('#linkilo-currently-selected-language-confirm-text-1').val();
            var str2 = $('#linkilo-currently-selected-language-confirm-text-2').val();
            var text = (str1 + '\n\n' + str2);

            linkilo_swal({
                title: 'Notice:',
                text: (text) ? text: 'Changing Linkilo\'s language will replace the current Words to be Ignored with a new list of words. \n\n If you\'ve added any words to the Words to be Ignored area, this will erase them.',
                icon: 'info',
                buttons: {
                    cancel: true,
                    confirm: true,
                },
                }).then((replace) => {
                  if (replace) {
                        wordLists = JSON.parse(wordLists);
                        if(wordLists[selectedLang]){
                            $('#ignore_words').val(wordLists[selectedLang].join('\n'));
                            $('#linkilo-currently-selected-language').val(selectedLang);
                            hasChangedLanguage = true;
                        }
                  } else {
                    $('#linkilo-selected-language').val($('#linkilo-currently-selected-language').val());
                  }
                });
        }else{
            wordLists = JSON.parse(wordLists);
            if(wordLists[selectedLang]){
                $('#ignore_words').val(wordLists[selectedLang].join('\n'));
                $('#linkilo-currently-selected-language').val(selectedLang);
            }
        }
    }

    $(document).on('change', 'input[name="linkilo_show_all_links"]', function(){
        var checkbox = $(this);
        linkilo_swal({
            title: 'Notice:',
            text: 'After changing this setting, you are required to click "Perform Scan" reports on the links records page in order to see the correct link counts.',
            icon: 'info',
            buttons: ['Cancel', 'I Understand'],
        }).then((replace) => {
            if (!replace) {
                checkbox.prop('checked', !checkbox.prop('checked'));
            } else {
                $('#frmSaveSettings').submit();
            }
        });
    });

    $(document).on('change', 'input[name="linkilo_delete_all_data"]', function(){
        var checkbox = $(this);

        // don't show the warning message if the user is turning off the data delete
        if(!checkbox.is(':checked')){
            return;
        }

        var wrapper = document.createElement('div');
        var message = $('.linkilo-delete-all-data-message').val();
        $(wrapper).append(message);

        linkilo_swal({
            title: 'Notice:',
            content: wrapper,
            icon: 'info',
            buttons: ['Cancel', 'I Understand'],
        }).then((replace) => {
            if (!replace) {
                checkbox.prop('checked', !checkbox.prop('checked'));
            } else {
                $('#frmSaveSettings').submit();
            }
        });
    });

    $(document).on('change', 'input[name="linkilo_link_external_sites"]', toggleSiteLinkingDisplay);
    function toggleSiteLinkingDisplay(){
        var input = $('input[name="linkilo_link_external_sites"]');

        // if the site linking is toggled on
        if(input.is(':checked')){
            // show the setting inputs
            $('.linkilo-site-linking-setting-row').css({'display': 'table-row'});
        }else{
            // if it's toggled off, hide the inputs
            $('.linkilo-site-linking-setting-row').css({'display': 'none'});
        }
    }

    // if no sites have been linked yet, add the first "link site" input
    if($('.linkilo-linked-site-input').length < 2){
        addLinkedSiteInput();
    }
    
    $(document).on('click', '.linkilo-linked-site-add-button', function(e){ e.preventDefault(); addLinkedSiteInput(); });

    function addLinkedSiteInput(){
        var newInput = $('.linkilo-linked-site-input.template-input').clone().removeClass('template-input').removeClass('hidden');
        $(newInput).insertBefore('.linkilo-linked-site-add-button-container');
    }

    $(document).on('click', '.linkilo-register-site-button', registerSite);
    function registerSite(e){
        e.preventDefault();

        var button = $(this),
            parent = button.parent(),
            nonce = button.data('nonce'),
            url = button.parents('.linkilo-linked-site-input').find('[name="linkilo_linked_site_url[]"]').val(),
            urlRegex = /^(http|https):\/\/(([a-zA-Z0-9$\-_.+!*'(),;:&=]|%[0-9a-fA-F]{2})+@)?(((25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])(\.(25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])){3})|localhost|([a-zA-Z0-9\-\u00C0-\u017F]+\.)+([a-zA-Z]{2,}))(:[0-9]+)?(\/(([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*(\/([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*)*)?(\?([a-zA-Z0-9$\-_.+!*'(),;:@&=\/?]|%[0-9a-fA-F]{2})*)?(\#([a-zA-Z0-9$\-_.+!*'(),;:@&=\/?]|%[0-9a-fA-F]{2})*)?)?$/,
            code = $('[name="linkilo_link_external_sites_access_code"]:visible').val();

        // if there's no url given
        if(url.length < 1){
            // ask the user to add one
            linkilo_swal('No Site Url Given', 'The site url field is empty, please add the url of the site you want to link to.', 'error');
            return;
        }

        // if the site url isn't properly formatted
        if(!urlRegex.test(url)){
            // throw an error
            linkilo_swal('Format Error', 'The given url was not in the necessary format. Please enter the url as it appears in your browser\'s address bar including the protocol (https or http).', 'error');
            return;
        }

        linkilo_swal({
            title: 'Confirm Code',
            text: "Please confirm that the access code on \"" + url + "\" is: \n\n\n " + code + "\n\n\n If the codes don't match, please update them so they match.",
            icon: 'info',
            buttons: ['They Don\'t Match', 'They Match'],
        }).then((match) => {
            if (match) {
                jQuery.ajax({
                    type: 'POST',
                    url: ajaxurl,
                    data: {
                        action: 'linkilo_register_selected_site',
                        url: url,
                        nonce: nonce,
                    },
                    success: function(response){
                        console.log(response);
                        // if there was an error
                        if(response.error){
                            // output the error message
                            linkilo_swal(response.error.title, response.error.text, 'error');
                            // and exit
                            return;
                        }else if(response.info){
                            // output the success message
                            linkilo_swal(response.info.title, response.info.text, 'info');
                            // replace the link button with the unlink button
                            button.remove();
                            parent.append(response.info.link_button);
                            // and exit
                            return;
                        }
                    }
                });
            }
        });
    }

    $(document).on('click', '.linkilo-link-site-button', linkSite);
    function linkSite(e){
        e.preventDefault();

        var button = $(this),
            parent = button.parent(),
            nonce = button.data('nonce'),
            url = button.parents('.linkilo-linked-site-input').find('[name="linkilo_linked_site_url[]"]').val(),
            urlRegex = /^(http|https):\/\/(([a-zA-Z0-9$\-_.+!*'(),;:&=]|%[0-9a-fA-F]{2})+@)?(((25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])(\.(25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])){3})|localhost|([a-zA-Z0-9\-\u00C0-\u017F]+\.)+([a-zA-Z]{2,}))(:[0-9]+)?(\/(([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*(\/([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*)*)?(\?([a-zA-Z0-9$\-_.+!*'(),;:@&=\/?]|%[0-9a-fA-F]{2})*)?(\#([a-zA-Z0-9$\-_.+!*'(),;:@&=\/?]|%[0-9a-fA-F]{2})*)?)?$/;
            
        // if there's no url given
        if(url.length < 1){
            // ask the user to add one
            linkilo_swal('No Site Url Given', 'The site url field is empty, please add the url of the site you want to link to.', 'error');
            return;
        }

        // if the site url isn't properly formatted
        if(!urlRegex.test(url)){
            // throw an error
            linkilo_swal('Format Error', 'The given url was not in the necessary format. Please enter the url as it appears in your browser\'s address bar including the protocol (https or http).', 'error');
            return;
        }

        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'linkilo_link_selected_site',
                url: url,
                nonce: nonce,
            },
            success: function(response){
                console.log(response);
                // if there was an error
                if(response.error){
                    // output the error message
                    linkilo_swal(response.error.title, response.error.text, 'error');
                    // and exit
                    return;
                }else if(response.success){
                    // output the success message
                    linkilo_swal(response.success.title, response.success.text, 'success');
                    // replace the link button with the unlink button
                    parent.find('a').remove();
                    parent.append(response.success.import_button);
                    parent.append(response.success.suggestions_button);
                    parent.append(response.success.unlink_button);
                    // parent.append('<div class="progress_panel loader site-import-loader" style="display: none;"><div class="progress_count" style="width:100%">' + $('#linkilo-site-linking-initial-loading-message').val() + '</div></div>');
                    parent.append('<div class="progress_panel loader site-import-loader" style="display: none;"><div class="progress_count" style="width:100%"></div></div><div class="progress_panel_center" > Loading </div>');
                    // and exit
                    return;
                }else if(response.info){
                    // output the info message
                    linkilo_swal(response.info.title, response.info.text, 'info');
                    // and exit
                    return;
                }
            }
        });
    }

    $(document).on('click', '.linkilo-unlink-site-button', unlinkSite);
    function unlinkSite(e){
        e.preventDefault();

        var button = $(this),
            parent = button.parents('.linkilo-linked-site-input'),
            nonce = button.data('nonce'),
            url = button.parents('.linkilo-linked-site-input').find('[name="linkilo_linked_site_url[]"]').val(),
            urlRegex = /^(http|https):\/\/(([a-zA-Z0-9$\-_.+!*'(),;:&=]|%[0-9a-fA-F]{2})+@)?(((25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])(\.(25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])){3})|localhost|([a-zA-Z0-9\-\u00C0-\u017F]+\.)+([a-zA-Z]{2,}))(:[0-9]+)?(\/(([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*(\/([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*)*)?(\?([a-zA-Z0-9$\-_.+!*'(),;:@&=\/?]|%[0-9a-fA-F]{2})*)?(\#([a-zA-Z0-9$\-_.+!*'(),;:@&=\/?]|%[0-9a-fA-F]{2})*)?)?$/;
            
        // if there's no url given
        if(url.length < 1){
            // ask the user to add one
            linkilo_swal('No Site Url', 'The site url field is empty, please reload the page and try again.', 'error');
            return;
        }

        // if the site url isn't properly formatted
        if(!urlRegex.test(url)){
            // throw an error
            linkilo_swal('Format Error', 'The given url was not in the necessary format. Please reload the page and try again.', 'error');
            return;
        }

        // give the active class to the remove button
        button.addClass('linkilo_button_is_active_purple');

        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'linkilo_remove_linked_site',
                url: url,
                nonce: nonce,
            },
            success: function(response){
                console.log(response);
                // if there was an error
                if(response.error){
                    // output the error message
                    linkilo_swal(response.error.title, response.error.text, 'error');
                    // remove the active class
                    button.removeClass('linkilo_button_is_active_purple');
                    // and exit
                    return;
                }else if(response.success){
                    // output the success message
                    linkilo_swal(response.success.title, response.success.text, 'success');
                    // replace the link button with the unlink button
                    parent.fadeOut(300, function(){ parent.remove(); });
                    // and exit
                    return;
                }else if(response.info){
                    // output the success message
                    linkilo_swal(response.info.title, response.info.text, 'info');
                    // and exit
                    return;
                }
            }
        });
    }

    $(document).on('click', '.linkilo-unregister-site-button', unregisterSite);
    function unregisterSite(e){
        e.preventDefault();

        var button = $(this),
            parent = button.parents('.linkilo-linked-site-input'),
            nonce = button.data('nonce'),
            url = button.parents('.linkilo-linked-site-input').find('[name="linkilo_linked_site_url[]"]').val(),
            urlRegex = /^(http|https):\/\/(([a-zA-Z0-9$\-_.+!*'(),;:&=]|%[0-9a-fA-F]{2})+@)?(((25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])(\.(25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])){3})|localhost|([a-zA-Z0-9\-\u00C0-\u017F]+\.)+([a-zA-Z]{2,}))(:[0-9]+)?(\/(([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*(\/([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*)*)?(\?([a-zA-Z0-9$\-_.+!*'(),;:@&=\/?]|%[0-9a-fA-F]{2})*)?(\#([a-zA-Z0-9$\-_.+!*'(),;:@&=\/?]|%[0-9a-fA-F]{2})*)?)?$/;
            
        // if there's no url given
        if(url.length < 1){
            // ask the user to add one
            linkilo_swal('No Site Url', 'The site url field is empty, please reload the page and try again.', 'error');
            return;
        }

        // if the site url isn't properly formatted
        if(!urlRegex.test(url)){
            // throw an error
            linkilo_swal('Format Error', 'The given url was not in the necessary format. Please reload the page and try again.', 'error');
            return;
        }

        // give the active class to the remove button
        button.addClass('linkilo_button_is_active_purple');

        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'linkilo_remove_registered_site',
                url: url,
                nonce: nonce,
            },
            success: function(response){
                console.log(response);
                // if there was an error
                if(response.error){
                    // output the error message
                    linkilo_swal(response.error.title, response.error.text, 'error');
                    // remove the active class
                    button.removeClass('linkilo_button_is_active_purple');
                    // and exit
                    return;
                }else if(response.success){
                    // output the success message
                    linkilo_swal(response.success.title, response.success.text, 'success');
                    // replace the link button with the unlink button
                    parent.fadeOut(300, function(){ parent.remove(); });
                    // and exit
                    return;
                }else if(response.info){
                    // output the success message
                    linkilo_swal(response.info.title, response.info.text, 'info');
                    // and exit
                    return;
                }
            }
        });
    }

    $(document).on('click', '.linkilo-refresh-post-data', function(e){
        e.preventDefault(); 
        var button = $(this),
        url = button.parents('.linkilo-linked-site-input').find('[name="linkilo_linked_site_url[]"]').val(),
        urlRegex = /^(http|https):\/\/(([a-zA-Z0-9$\-_.+!*'(),;:&=]|%[0-9a-fA-F]{2})+@)?(((25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])(\.(25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])){3})|localhost|([a-zA-Z0-9\-\u00C0-\u017F]+\.)+([a-zA-Z]{2,}))(:[0-9]+)?(\/(([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*(\/([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*)*)?(\?([a-zA-Z0-9$\-_.+!*'(),;:@&=\/?]|%[0-9a-fA-F]{2})*)?(\#([a-zA-Z0-9$\-_.+!*'(),;:@&=\/?]|%[0-9a-fA-F]{2})*)?)?$/;

        // if there's no url given
        if(url.length < 1){
            // ask the user to add one
            linkilo_swal('No Site Url', 'The site url field is empty, please reload the page and try again.', 'error');
            return;
        }

        // if the site url isn't properly formatted
        if(!urlRegex.test(url)){
            // throw an error
            linkilo_swal('Format Error', 'The given url was not in the necessary format. Please reload the page and try again.', 'error');
            return;
        }

        refreshPostData(url, 0, 0, 0, button, 1); 
    });

    function refreshPostData(url, page, saved, total, button, reset = 0){

        var parent = button.parent(),
        loadingBar = parent.find('.site-import-loader'),
        nonce = button.data('nonce');

        // hide the current site's buttons
        parent.find('.site-linking-button').css({'display': 'none'});
        // show the loading bar
        loadingBar.css({'display': 'inline-block'});

        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'linkilo_refresh_site_data',
                url: url,
                nonce: nonce,
                page: page,
                saved: saved,
                total: total,
                reset: reset
            },
            success: function(response){
                console.log(response);
                // if there was an error
                if(response.error){
                    // output the error message
                    linkilo_swal(response.error.title, response.error.text, 'error');
                    // unhide the current site's buttons
                    parent.find('.site-linking-button').css({'display': 'inline-block'});
                    // hide the loading bar
                    loadingBar.css({'display': 'none'});
                    // and reset the status message
                    // loadingBar.find('.progress_count').html($('#linkilo-site-linking-initial-loading-message').val());
                    // and exit
                    return;
                }else if(response.success){
                    // output the success message
                    linkilo_swal(response.success.title, response.success.text, 'success');
                    // unhide the current site's buttons
                    parent.find('.site-linking-button').css({'display': 'inline-block'});
                    // hide the loading bar
                    loadingBar.css({'display': 'none'});
                    // and reset the status message
                    // loadingBar.find('.progress_count').html($('#linkilo-site-linking-initial-loading-message').val());
                    // and exit
                    return;
                }else if(response){
                    // update the loading bar with the status
                    // loadingBar.find('.progress_count').html(response.message);
                    // and go around again
                    refreshPostData(response.url, response.page, response.saved, response.total, button);
                    return;
                }
            },
        });
    }

    $(document).on('click', '.linkilo-external-site-suggestions-toggle', function(e){
        e.preventDefault(); 
        var suggestionsEnabled = parseInt($(this).attr('data-suggestions-enabled'));
        var button = $(this);
        button.addClass('linkilo_button_is_active');

        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'linkilo_outer_site_suggestion_toggle',
                url: button.data('site-url'),
                suggestions_enabled: suggestionsEnabled,
                nonce: $(this).data('nonce')
            },
            success: function(response){
                console.log(response);
                // if there was an error
                if(response.error){
                    // output the error message
                    linkilo_swal(response.error.title, response.error.text, 'error');
                }else if(response.success){
                    // output the success message
                    linkilo_swal(response.success.title, response.success.text, 'success');
                    // toggle the suggestion status
                    button.attr('data-suggestions-enabled', (suggestionsEnabled ? 0: 1));
                    // toggle the suggestion status text
                    button.html((!suggestionsEnabled ? $(button).data('disable-text'): $(button).data('enable-text')));
                }else if(response.info){
                    linkilo_swal(response.info.title, response.info.text, 'info');
                }
            },
            complete: function(){
                button.removeClass('linkilo_button_is_active');
            }
        });
    });

    $(document).on('click', '.linkilo-generate-id-code', generateIdCode);
    function generateIdCode(e){
        e.preventDefault();
        var idCodeNum = $(this).data('linkilo-id-code'),
            baseString = $(this).data('linkilo-base-id-string'),
            code = shuffle(baseString).slice(0, 120),
            message = "The site interlinking access code has been generated successfully! \n\n Please copy this code and paste it into \"Site Interlinking Access Code\" inputs for all sites that you want to link together. \n\n\n " + code;
            console.log(code);
            linkilo_swal('Access Code Generated!', message, 'info', {buttons: {'copy' : 'Copy Code'}}).then((value) => {
                if(value === 'copy'){
                    copyTextToClipboard(code);
                }
            });
    }

    function shuffle(string) {
        var a = string.split(""),
            n = a.length;
    
        for(var i = n - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var tmp = a[i];
            a[i] = a[j];
            a[j] = tmp;
        }
        return a.join("");
    }

    function fallbackCopyTextToClipboard(text) {
        var textArea = document.createElement("textarea");
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            var successful = document.execCommand('copy');
            var msg = successful ? 'successful' : 'unsuccessful';
            console.log('Fallback: Copying text command was ' + msg);
        } catch (err) {
            console.error('Fallback: Oops, unable to copy', err);
        }

        document.body.removeChild(textArea);
    }

    function copyTextToClipboard(text) {
        if (!navigator.clipboard) {
            fallbackCopyTextToClipboard(text);
            return;
        }
        navigator.clipboard.writeText(text).then(function() {
            console.log('Async: Copying to clipboard was successful!');
        }, function(err) {
            console.error('Async: Could not copy text: ', err);
        });
    }
    /** handle the GSC setting inputs **/
    $(document).on('click', '.linkilo-get-gsc-access-token', function(e){
        e.preventDefault();
        window.open(this.href, "", "width=800, height=600");
    });

    /** Show the settings for the current tab and hide the others **/
    $(document).on('click', '#settings_page .nav-tab-wrapper .nav-tab', function(e){
        e.preventDefault();

        // get the tab id
        var tabId = $(this).prop('id');

        // highlight the current tab
        $('#settings_page .nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // show the correct tab's settings
        $('.linkilo-setting-row').css({'display': 'none'});
        $('.' + tabId).css({'display': 'table-row'});

        // toggleLicenseSaving(tabId);  Commented unusable js ref:license

        // make sure that options that need to be toggled on to be seen stay hidden if not toggled
        if(!$('input[type="checkbox"][name="linkilo_link_external_sites"]').is(':checked')){
            $('.linkilo-site-linking-setting-row').css({'display': 'none'});
        }

        // 
        if(tabId === 'linkilo-advanced-settings'){
            showForcejSLinkOpening(); 
        }
    });
    
    /**
     * Enables license activating when the user is on the Licensing tab, 
     * and disables licensing activating when on the other settings tabs.
     * 
     * @param {string} tabId The id of the current setting tab.
     **/
    /*  Commented unusable js ref:license
    function toggleLicenseSaving(tabId){
        if(tabId === 'linkilo-licensing'){
            $('#linkilo_license_action_input').removeAttr('disabled');
            $('.linkilo-setting-button.save-settings').css({'display': 'none'});
            $('.linkilo-setting-button.activate-license').css({'display': 'block'});
        }else{
            $('#linkilo_license_action_input').prop('disabled', 'disabled');
            $('.linkilo-setting-button.save-settings').css({'display': 'block'});
            $('.linkilo-setting-button.activate-license').css({'display': 'none'});
        }
    }*/

    /*  Commented unusable js ref:license
    function showLicensingPageOnPageLoad(){
        var params = parseURLParams(window.location.href);

        if(params && params.licensing){
            $('#settings_page .nav-tab-wrapper #linkilo-licensing').trigger('click');
        }
    }
    showLicensingPageOnPageLoad();*/

    function showForcejSLinkOpening(){
        var int = $('[name=linkilo_open_all_internal_new_tab]').is(':checked');
        var ext = $('[name=linkilo_open_all_external_new_tab]').is(':checked');
        if(int || ext){
            $('.js-force-open-new-tabs').css({'display': 'table-row'});
        }else{
            $('.js-force-open-new-tabs').css({'display': 'none'});
        }
    }

    $(document).on('change', '[name=linkilo_open_all_internal_new_tab],[name=linkilo_open_all_external_new_tab]', showForcejSLinkOpening);

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
