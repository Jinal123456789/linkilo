"use strict";

(function ($) {
    $(document).on('click', 'a', linkilo_url_clicked);
    $(document).on('auxclick', 'a', linkilo_url_clicked);

    function linkilo_url_clicked(e){
//        e.preventDefault();
        var link = $(this);
        var linkAnchor = '';
        var hasImage = false;
        var imageTitle = '';
        var imageTags = ['img', 'svg'];

        if('1' === linkiloFrontend.disableClicks){
            return;
        }

        if(this.href === undefined || link.attr('href') === '#'){
            return;
        }

        function findLinkText(link){
            if(link.children().length > 0){
                $(link).contents().filter(function(){
                    var childThis = $(this);
    
                    if(childThis.children().length > 0 && linkAnchor === ''){
                        findLinkText(childThis);
                    }
            
                    if(this.nodeType === 1 && -1 !== imageTags.indexOf(this.nodeName.toLowerCase()) && imageTitle === ''){
                        hasImage = true;
                        var title = $(this).attr('title');
    
                        if(undefined !== title){
                            imageTitle = title.trim();
                        }
                    }
    
                    if(this.nodeType === 3 && linkAnchor === ''){
                        var text = $(this).text().trim();
                        if(text.length > 0){
                            linkAnchor = text;
                        }
                    }
            
                    linkAnchor = linkAnchor.trim();
                    imageTitle = (imageTitle !== undefined) ? imageTitle.trim(): '';
                });
    
            }else{
                linkAnchor = link.text().trim();
            }
        }

        findLinkText(link);

        if(linkAnchor === '' && hasImage){
            if(imageTitle !== ''){
                linkAnchor = linkiloFrontend.clicksI18n.imageText + imageTitle;
            }else{
                linkAnchor = linkiloFrontend.clicksI18n.imageNoText;
            }
        }else if(linkAnchor === '' && !hasImage){
            linkAnchor = linkiloFrontend.clicksI18n.noText;
        }

        // if the click wasn't a primary or middle click, or there's no link, exit
        if(!(e.which == 1 || e.button == 0 ) && !(e.which == 2 || e.button == 4 ) || link.length < 1){
            return;
        }

        // ignore non-content links
        if($(link).parents('header, footer, nav, [id~=header], [id~=menu], [id~=footer], [id~=widget], [id~=comment], [class~=header], [class~=menu], [class~=footer], [class~=widget], [class~=comment], #wpadminbar').length){
            return;
        }

        jQuery.ajax({
			type: 'POST',
			url: linkiloFrontend.ajaxUrl,
			data: {
				action: 'linkilo_url_clicked',
				post_id: linkiloFrontend.postId,
                post_type: linkiloFrontend.postType,
                link_url: link.prop('href'),
                link_anchor: linkAnchor,
			},
			success: function(response){

			}
		});
    }
    $(window).on('load', openLinksInNewTab);

    /**
     * Sets non nav links on a page to open in a new tab based on the settings.
     **/
    function openLinksInNewTab(){
        // exit if non of the links are supposed to open in a new tab via JS
        if(linkiloFrontend.openLinksWithJS = 0 || (linkiloFrontend.openExternalInNewTab == 0 && linkiloFrontend.openInternalInNewTab == 0) ){
            return;
        }
        var links = $('body').find('a');

        $(links).each(function(index, element){
            // if the link is not a nav link
            if($(element).parents('header, footer, nav, [id~=header], [id~=menu], [id~=footer], [id~=widget], [id~=comment], [class~=header], [class~=menu], [class~=footer], [class~=widget], [class~=comment], #wpadminbar').length < 1){
                // if there is a url in the link, there isn't a target and the link isn't a jump link
                if(element.href && !element.target && element.href.indexOf(window.location.href) === -1){
                    var url = new URL(element.href);
                    var internal = (window.location.hostname === url.hostname) ? true: false;
                    // if the settings allow it
                    if( (internal && parseInt(linkiloFrontend.openInternalInNewTab)) || (!internal && parseInt(linkiloFrontend.openExternalInNewTab)) ){
                        // set the link to open in a new page
                        $(element).attr('target', '_blank');
                    }
                }
            }
            
        });
    }

})(jQuery);