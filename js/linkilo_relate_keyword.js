"use strict";

(function ($) {
    $(document).on('click', '#linkilo_keywords_table .delete', linkilo_remove_relate_url_keyword);
    $(document).on('click', '#linkilo_keywords_settings i', linkilo_keyword_settings_show);
    $(document).on('click', '.linkilo-admin_page_linkilo_keywords .column-keyword .dashicons', linkilo_keyword_local_settings_show);
    $(document).on('click', '#linkilo_keywords_settings input[type="submit"]', linkilo_keyword_clear_fields);
    $(document).on('click', '#add_keyword_form a', linkilo_add_relate_url_keyword);
    $(document).on('click', '.linkilo_keyword_local_settings_save', linkilo_keyword_local_settings_save);
    $(document).on('click', '#linkilo_keywords_reset_button', linkilo_reset_relate_url_keyword);
    $(document).on('click', '.linkilo-insert-selected-keywords', linkilo_insert_selected_keywords);

    if (is_linkilo_reset_relate_url_keyword) {
        linkilo_reset_relate_url_keyword_process(2, 1);
    }

    function linkilo_remove_relate_url_keyword() {
        if (confirm("Are you sure you want to delete this keyword?")) {
            var el = $(this);
            var id = el.data('id');

            $.post(ajaxurl, {
                action: 'linkilo_remove_relate_url_keyword',
                id: id
            }, function(){
                el.closest('tr').fadeOut(300);
            });
        }
    }

    function linkilo_keyword_settings_show() {
        $('#linkilo_keywords_settings .block').toggle();
    }

    function linkilo_keyword_local_settings_show() {
        $(this).closest('td').find('.block').toggle();
    }

    $(document).on('change', '.linkilo_keywords_set_priority_checkbox', linkiloShowSetPriorityInput);
    function linkiloShowSetPriorityInput(){
        var button = $(this);
        button.parent().find('.linkilo_keywords_priority_setting_container').toggle();
    }

    $(document).on('change', '.linkilo_keywords_restrict_date_checkbox', linkiloShowRestrictDateInput);
    function linkiloShowRestrictDateInput(){
        var button = $(this);
        button.parent().find('.linkilo_keywords_restricted_date-container').toggle();
    }

    $(document).on('click', '.linkilo-keywords-restrict-cats-show', linkiloShowRestrictCategoryList);
    function linkiloShowRestrictCategoryList(){
        var button = $(this);
        button.parents('.block').find('.linkilo-keywords-restrict-cats').toggle();
        button.toggleClass('open');
    }

    function linkilo_keyword_clear_fields() {
        $('input[name="keyword"]').val('');
        $('input[name="link"]').val('');
    }
    /*Autocomplete check*/
    function split( val ) {
        return val.split( /,\s*/ );
    }
    function extractLast( term ) {
        return split( term ).pop();
    }   

    /*For post type list*/
    $( "#linkilo_keywords_whitelist_of_post_types" ).on("focus keydown", function( event ) {
        if (event.type == "focus") {
            $(this).autocomplete("search", "");
        }
        if (event.type == "keydown") {
            if ( event.keyCode === $.ui.keyCode.TAB &&
                $( this ).autocomplete( "instance" ).menu.active ) 
            {
                event.preventDefault();
            }
        }
    }).autocomplete({
        minLength: 1,
        autoFocus: true,
        source: function( request, response ) {
            // delegate back to autocomplete, but extract the last term
            var data = [
                {
                    "label" : "Post",
                    "value" : "post"
                }, {
                    "label" : "Page",
                    "value" : "page",
                }
            ];

            response(data);
        },
        focus: function() {
            // prevent value inserted on focus
            return false;
        },
        select: function( event, ui ) {
            var terms = split( this.value );
            // remove the current input
            terms.pop();
            // add the selected item

            if(!($.inArray(ui.item.value,terms) > -1))
            terms.push( ui.item.value );

            // add placeholder to get the comma-and-space at the end
            terms.push( "" );
            this.value = terms.join( "," );
            return false;
        }
    });
    /*For post type list ends*/

    /*For post id list*/
    $( "#linkilo_keywords_blacklist_of_posts" ).on( "focus keydown", function( event ) {
        if (event.type == "focus") {
            $(this).autocomplete("search", "");
        }
        if (event.type == "keydown") {
            if ( event.keyCode === $.ui.keyCode.TAB &&
                $( this ).autocomplete( "instance" ).menu.active ) 
            {
                event.preventDefault();
            }
        }
    }).autocomplete({
        minLength: 3,
        source: function( request, response ) {
            // delegate back to autocomplete, but extract the last term
            var req_data = {
                action: 'linkilo_keyword_search_post_id',
                term: extractLast( request.term ),
                term_length : request.term.length,
            }
            $.ajax({
                type: "POST",
                url: ajaxurl,
                data: req_data,
                error: function (jqXHR, textStatus, errorThrown) {
                    console.log(jqXHR + "\n" + textStatus + "\n" + errorThrown);
                },
                success: function(resp_data){
                    var autoData = JSON.parse(resp_data);
                    response(autoData);
                }
            });
        },
        focus: function() {
            // prevent value inserted on focus
            return false;
        },
        select: function( event, ui ) { 
            var terms = split( this.value );
            // remove the current input
            terms.pop();

            // add the selected item
            if(!($.inArray(ui.item.value,terms) > -1))
            // terms.push( ui.item.value );
            terms.push( parseInt(ui.item.value) );
            // add placeholder to get the comma-and-space at the end
            terms.push( "" );
            this.value = terms.join( "," );
            return false;
        }
    });
    /*For post id list ends*/

    /*For html tags list*/
    $( "#linkilo_keywords_excluded_html_elements" ).on("focus keydown", function( event ) {
        if (event.type == "focus") {
            $(this).autocomplete("search", "");
        }
        if (event.type == "keydown") {
            if ( event.keyCode === $.ui.keyCode.TAB &&
                $( this ).autocomplete( "instance" ).menu.active ) 
            {
                event.preventDefault();
            }
        }
    }).autocomplete({
        minLength: 0,
        autoFocus: true,
        source: function( request, response ) {
            // delegate back to autocomplete, but extract the last term
            var data = [
                {
                    "label" : "<H1>",
                    "value" : "h1"
                },{
                    "label" : "<H2>",
                    "value" : "h2"
                },{
                    "label" : "<H3>",
                    "value" : "h3"
                },{
                    "label" : "<H4>",
                    "value" : "h4"
                },{
                    "label" : "<H5>",
                    "value" : "h5"
                },{
                    "label" : "<H6>",
                    "value" : "h6"
                },{
                    "label" : "<p>",
                    "value" : "p"
                },{
                    "label" : "<span>",
                    "value" : "span",
                },{
                    "label" : "<div>",
                    "value" : "div"
                },{
                    "label" : "<b>",
                    "value" : "b"
                }
            ];

            response(data);
        },
        focus: function() {
            // prevent value inserted on focus
            return false;
        },
        select: function( event, ui ) {
            var terms = split( this.value );
            // remove the current input
            terms.pop();
            // add the selected item

            if(!($.inArray(ui.item.value,terms) > -1))
            terms.push( ui.item.value );

            // add placeholder to get the comma-and-space at the end
            terms.push( "" );
            this.value = terms.join( "," );
            return false;
        }
    });
    /*For html tags list ends*/

    /*Autocomplete check*/
    function linkilo_add_relate_url_keyword() {
        var form = $('#add_keyword_form');
        var keyword = form.find('input[name="keyword"]').val();
        var link = form.find('input[name="link"]').val();

        if(keyword.length === 0 || link.length === 0){
            linkilo_swal({"title": "Auto-Link Field Empty", "text": "Please make sure there's a Keyword and a Link in the Auto-Link creation fields before attempting to creating an Auto-Link.", "icon": "info"});
            return;
        }
        var restrictedToDate = $('#linkilo_keywords_restrict_date').prop('checked') ? 1 : 0;
        var restrictedToCat = $('#linkilo_keywords_restrict_to_cats').prop('checked') ? 1 : 0;
        var setPriority = $('#linkilo_keywords_set_priority').prop('checked') ? 1 : 0;

        form.find('input[type="text"]').hide();
        form.find('.progress_panel').show();
        form.find('.progress_panel_center').show();

        // return false;
        var params = {
            keyword: keyword,
            link: link,
            linkilo_keywords_add_same_link: $('#linkilo_keywords_add_same_link').prop('checked') ? 1 : 0,
            linkilo_keywords_link_once: $('#linkilo_keywords_link_once').prop('checked') ? 1 : 0,
            linkilo_keywords_select_links: $('#linkilo_keywords_select_links').prop('checked') ? 1 : 0,
            linkilo_keywords_set_priority: setPriority,
            linkilo_keywords_restrict_date: restrictedToDate,
            linkilo_keywords_restrict_to_cats: restrictedToCat,
        };

        if(setPriority){
            var priority = $('#linkilo_keywords_priority_setting').val();
            if(!priority){
                priority = null;
            }
            params['linkilo_keywords_priority_setting'] = priority; 
        }

        if(restrictedToDate){
            var date = $('#linkilo_keywords_restricted_date').val();
            if(!date){
                date = null;
            }
            params['linkilo_keywords_restricted_date'] = date; 
        }

        if(restrictedToCat){
            var selectedCats = [];
            $('#linkilo_keywords_settings .linkilo-restrict-keywords-input:checked').each(function(index, element){
                selectedCats.push($(element).data('term-id'));
            });

            params['restricted_cats'] = selectedCats; 
        }

        /*Add new settings*/
        
        params['linkilo_keywords_exact_phrase_match'] =  $('#linkilo_keywords_exact_phrase_match').prop('checked') ? 1 : 0;

        params['linkilo_keywords_add_dofollow'] = $('#linkilo_keywords_add_dofollow').prop('checked') ? 1 : 0;

        params['linkilo_keywords_open_in_same_or_new_window'] = $('#linkilo_keywords_open_in_same_or_new_window').prop('checked') ? 1 : 0;
        

        // create array
        var whitelist_post_types = $("#linkilo_keywords_whitelist_of_post_types").val();
        var whitelist_post_types = whitelist_post_types.split(',');
        var whitelist_post_types = whitelist_post_types.filter(Boolean);
        params['linkilo_keywords_whitelist_of_post_types'] = whitelist_post_types;
        // console.log(arr);


        // create array
        var blacklist_posts = $("#linkilo_keywords_blacklist_of_posts").val();
        var blacklist_posts = blacklist_posts.split(',');
        var blacklist_posts = blacklist_posts.filter(Boolean);
        params['linkilo_keywords_blacklist_of_posts'] = blacklist_posts;


        params['linkilo_keywords_max_rel_links_per_post'] = parseInt($('#linkilo_keywords_max_rel_links_per_post').val());

        params['linkilo_keywords_post_linking_maximum_frequency'] = parseInt($('#linkilo_keywords_post_linking_maximum_frequency').val());


        // create array
        var html_elem_exclude = $("#linkilo_keywords_excluded_html_elements").val();
        var html_elem_exclude = html_elem_exclude.split(',');
        var html_elem_exclude = html_elem_exclude.filter(Boolean);
        params['linkilo_keywords_excluded_html_elements'] = html_elem_exclude;
        /*Add new settings ends*/

        linkilo_keyword_process(null, 0, form, params);
    }

    function linkilo_keyword_local_settings_save() {
        var keyword_id = $(this).data('id');
        var form = $(this).closest('.local_settings');
        // form.find('.block').hide();
        // form.find('.progress_panel').show();
        var setPriority = form.find('input[type="checkbox"][name="linkilo_keywords_set_priority"]').prop('checked') ? 1 : 0;
        var restrictedToDate = form.find('input[type="checkbox"][name="linkilo_keywords_restrict_date"]').prop('checked') ? 1 : 0;
        var restrictedToCats = form.find('input[type="checkbox"][name="linkilo_keywords_restrict_to_cats"]').prop('checked') ? 1 : 0;
        var params = {
            linkilo_keywords_add_same_link: form.find('input[type="checkbox"][name="linkilo_keywords_add_same_link"]').prop('checked') ? 1 : 0,
            linkilo_keywords_link_once: form.find('input[type="checkbox"][name="linkilo_keywords_link_once"]').prop('checked') ? 1 : 0,
            linkilo_keywords_select_links: $('input[type="checkbox"][name="linkilo_keywords_select_links"]').prop('checked') ? 1 : 0,
            linkilo_keywords_restrict_date: restrictedToDate,
            linkilo_keywords_restrict_to_cats: restrictedToCats,
            linkilo_keywords_set_priority: setPriority
        };

        if(setPriority){
            var priority = form.find('input[name="linkilo_keywords_priority_setting"]').val();
            if(!priority){
                priority = 0;
            }
            params['linkilo_keywords_priority_setting'] = parseInt(priority); 
        }

        if(restrictedToDate){
            var date = form.find('input[name="linkilo_keywords_restricted_date"]').val();
            if(!date){
                date = null;
            }
            params['linkilo_keywords_restricted_date'] = date; 
        }

        if(restrictedToCats){
            var selectedCats = [];
            form.find('input.linkilo-restrict-keywords-input[type="checkbox"]:checked').each(function(index, element){
                selectedCats.push($(element).data('term-id'));
            });

            params['restricted_cats'] = selectedCats; 
        }

        linkilo_keyword_process(keyword_id, 0, form, params);
    }

    function linkilo_keyword_process(keyword_id, total, form, params = {}) {
        var data = {
            action: 'linkilo_add_relate_url_keyword',
            nonce: linkilo_keyword_nonce,
            keyword_id: keyword_id,
            total: total
        }

        for (var key in params) {
            data[key] = params[key];
        }

        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: data,
            error: function (jqXHR, textStatus, errorThrown) {
                var wrapper = document.createElement('div');
                $(wrapper).append('<strong>' + textStatus + '</strong><br>');
                $(wrapper).append(jqXHR.responseText);
                linkilo_swal({"title": "Error", "content": wrapper, "icon": "error"}).then(linkilo_keyword_process(keyword_id, keyword, link));
            },
            success: function(response){
                if (response.error) {
                    linkilo_swal(response.error.title, response.error.text, 'error');
                    return;
                }

                // form.find('.progress_count').text(parseInt(response.progress) + '%');
                if (response.finish) {
                    location.reload();
                } else {
                    if (response.keyword_id && response.total) {
                        linkilo_keyword_process(response.keyword_id, response.total, form);
                    }
                }
            }
        });
    }

    function linkilo_reset_relate_url_keyword() {
        $('#linkilo_keywords_table .table').hide();
        $('#linkilo_keywords_table .progress').show();
        linkilo_reset_relate_url_keyword_process(1, 1);
    }

    function linkilo_reset_relate_url_keyword_process(count, total) {
        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
                action: 'linkilo_reset_relate_url_keyword',
                nonce: linkilo_keyword_nonce,
                count: count,
                total: total,
            },
            error: function (jqXHR, textStatus, errorThrown) {
                var wrapper = document.createElement('div');
                $(wrapper).append('<strong>' + textStatus + '</strong><br>');
                $(wrapper).append(jqXHR.responseText);
                linkilo_swal({"title": "Error", "content": wrapper, "icon": "error"}).then(linkilo_reset_relate_url_keyword_process(1, 1));
            },
            success: function(response){
                if (response.error) {
                    linkilo_swal(response.error.title, response.error.text, 'error');
                    return;
                }

                // var progress = Math.floor((response.ready / response.total) * 100);
                // $('#linkilo_keywords_table .progress .progress_count').text(progress + '%' + ' ' + response.ready + '/' + response.total);
                if (response.finish) {
                    location.reload();
                } else {
                    linkilo_reset_relate_url_keyword_process(response.count, response.total)
                }
            }
        });
    }

    function linkilo_insert_selected_keywords(e){
        e.preventDefault();

        var parentCell = $(this).closest('.linkilo-dropdown-column');
        var checkedLinks = $(this).closest('td.column-select_links').find('[name=linkilo_keyword_select_link]:checked');
        var linkIds = [];

        $(checkedLinks).each(function(index, element){
            var id = $(element).data('select-keyword-id');
            if(id){
                linkIds.push(id);
            }
        });

        if(linkIds.length < 1){
            return;
        }

        // hide the dropdown and show the loading bar
        parentCell.find('.linkilo-collapsible-wrapper').css({'display': 'none'});
        parentCell.find('.progress_panel.loader').css({'display': 'block'});

        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
                action: 'linkilo_insert_selected_keyword_links',
                link_ids: linkIds,
                nonce: linkilo_keyword_nonce,
            },
            error: function (jqXHR, textStatus, errorThrown) {
                var wrapper = document.createElement('div');
                $(wrapper).append('<strong>' + textStatus + '</strong><br>');
                $(wrapper).append(jqXHR.responseText);
                linkilo_swal({"title": "Error", "content": wrapper, "icon": "error"});
                // hide the loading bar and show the dropdown
                parentCell.find('.progress_panel.loader').css({'display': 'none'});
                parentCell.find('.linkilo-collapsible-wrapper').css({'display': 'block'});
            },
            success: function(response){
                if (response.error) {
                    linkilo_swal(response.error.title, response.error.text, 'error');

                    // hide the loading bar and show the dropdown
                    parentCell.find('.progress_panel.loader').css({'display': 'none'});
                    parentCell.find('.linkilo-collapsible-wrapper').css({'display': 'block'});
                    return;
                }

                if (response.success) {
                    linkilo_swal({"title": response.success.title, "text": response.success.text, "icon": "success"}).then(function(){
                        location.reload();
                    });
                } else {
                    location.reload();
                }
            }
        });
    }
})(jQuery);