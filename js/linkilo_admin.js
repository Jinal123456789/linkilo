"use strict";

(function ($)
{

	var reloadGutenberg = false;

	/////////// preloading
	function getSuggestions(manualActivate = false){
		$('[data-linkilo-ajax-container]').each(function(k, el){
			var $el = $(el);
			var url = $el.attr('data-linkilo-ajax-container-url');
			var count = 0;
			var urlParams = parseURLParams(url);

			// don't load the suggestions automatically if the user has selected manual activation
			if($el.data('linkilo-manual-suggestions') == 1 && !manualActivate){
				return
			}

			$el.css({'display': 'block'});
			$('.linkilo-get-manual-suggestions-container').css({'display': 'none'});

			if(urlParams.type && 'outgoing_suggestions_ajax' === urlParams.type[0]){
				ajaxGetSuggestionsOutbound($el, url, count);
			}else if(urlParams.type && 'incoming_suggestions_page_container' === urlParams.type[0]){
				ajaxGetSuggestionsIncoming($el, url, count);
			}
		});
	}

	getSuggestions();

	$(document).on('click', '#linkilo-get-manual-suggestions', function(e){e.preventDefault(); getSuggestions(true)});

	function ajaxGetSuggestionsIncoming($el, url, count, lastPost = 0, processedPostCount = 0, key = null)
	{
		var urlParams = parseURLParams(url);
		var post_id = (urlParams.post_id) ? urlParams.post_id[0] : null;
		var term_id = (urlParams.term_id) ? urlParams.term_id[0] : null;
		var keywords = (urlParams.keywords) ? urlParams.keywords[0] : '';
		var sameCategory = (urlParams.same_category) ? urlParams.same_category[0] : '';
		var selectedCategory = (urlParams.selected_category) ? urlParams.selected_category[0] : '';
		var sameTag = (urlParams.same_tag) ? urlParams.same_tag[0] : '';
		var selectedTag = (urlParams.selected_tag) ? urlParams.selected_tag[0] : '';
        var nonce = (urlParams.nonce) ? urlParams.nonce[0]: '';

        if(!nonce){
            return;
        }

        // if there isn't a key set, make one
        if(!key){
            while(true){
                key = Math.round(Math.random() * 1000000000);
                if(key > 999999){break;}
            }
        }

		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'get_recommended_url',
                nonce: nonce,
				count: count,
				post_id: post_id,
                term_id: term_id,
				type: 'incoming_suggestions',
				keywords: keywords,
				same_category: sameCategory,
				selected_category: selectedCategory,
				same_tag: sameTag,
				selected_tag: selectedTag,
				last_post: lastPost,
                completed_processing_count: processedPostCount,
                key: key,
			},
			success: function(response){
                // if there was an error
                if(response.error){
                    // output the error message
                    linkilo_swal(response.error.title, response.error.text, 'error');
                    // and exit
                    return;
                }

				count = parseInt(count) + 1;
				var progress = Math.floor(response.completed_processing_count / (response.post_count + 0.1) * 100);
				if (progress > 100) {
					progress = 100;
				}

                // $('.progress_count').html(progress + '%');

                for (var i = 0; i <= progress; i++) {
                	$(".progress_count").css({'width': i + '%'});
                }
				if(!response.completed){
					ajaxGetSuggestionsIncoming($el, url, count, response.last_post, response.completed_processing_count, key);
				}else{
					return updateSuggestionDisplay(post_id, term_id, nonce, $el, 'incoming_suggestions', sameCategory, key, selectedCategory, sameTag, selectedTag);
				}
			}
		});
	}

	function ajaxGetSuggestionsOutbound($el, url, count, post_count = 0, key = null)
	{
        // if there isn't a key set, make one
        if(!key){
            while(true){
                key = Math.round(Math.random() * 1000000000);
                if(key > 999999){break;}
            }
        }

		var urlParams = parseURLParams(url);
		var post_id = (urlParams.post_id) ? urlParams.post_id[0] : null;
		var term_id = (urlParams.term_id) ? urlParams.term_id[0] : null;
		var sameCategory = (urlParams.same_category) ? urlParams.same_category[0] : '';
		var selectedCategory = (urlParams.selected_category) ? urlParams.selected_category[0] : '';
		var sameTag = (urlParams.same_tag) ? urlParams.same_tag[0] : '';
		var selectedTag = (urlParams.selected_tag) ? urlParams.selected_tag[0] : '';

		// var sameTitle = (urlParams.same_title) ? urlParams.same_title[0] : '';

        var nonce = (urlParams.nonce) ? urlParams.nonce[0]: '';


        if(!nonce){
            return;
        }

		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'get_recommended_url',
                nonce: nonce,
				count: count,
				post_count: (post_count) ? parseInt(post_count): 0,
				post_id: post_id,
                term_id: term_id,
				same_category: sameCategory,
				selected_category: selectedCategory,
				same_tag: sameTag,
				selected_tag: selectedTag,
				type: 'outgoing_suggestions',
                key: key,
			},
			success: function(response){
                // if there was an error
                if(response.error){
                    // output the error message
                    linkilo_swal(response.error.title, response.error.text, 'error');
                    // and exit
                    return;
                }

                // if there was a notice
                if(response.info){
                    // output the notice message
                    linkilo_swal(response.info.title, response.info.text, 'info');
                    // and exit
                    return;
                }

				// $el.find('.progress_count').html(response.message);

				if((count * response.batch_size) < response.post_count){
					ajaxGetSuggestionsOutbound($el, url, response.count, response.post_count, key);
				}else if( (sameCategory || sameTag) || (0 == linkilo_ajax.site_linking_enabled) ){
					// if we're doing same tag or cat matching, skip the external sites.
					return updateSuggestionDisplay(post_id, term_id, nonce, $el, 'outgoing_suggestions', sameCategory, key, selectedCategory, sameTag, selectedTag);
				}else{
					ajaxGetExternalSiteSuggestions($el, url, 0, 0, key);
				}
			},
            error: function(jqXHR, textStatus, errorThrown){
                console.log({jqXHR, textStatus, errorThrown});
            }
		});
	}


	function ajaxGetExternalSiteSuggestions($el, url, count, post_count = 0, key = null)
	{
        // if there isn't a key set, make one
        if(!key){
            while(true){
                key = Math.round(Math.random() * 1000000000);
                if(key > 999999){break;}
            }
        }

		var urlParams = parseURLParams(url);
		var post_id = (urlParams.post_id) ? urlParams.post_id[0] : null;
		var term_id = (urlParams.term_id) ? urlParams.term_id[0] : null;
		var sameCategory = (urlParams.same_category) ? urlParams.same_category[0] : '';
		var selectedCategory = (urlParams.selected_category) ? urlParams.selected_category[0] : '';
		var sameTag = (urlParams.same_tag) ? urlParams.same_tag[0] : '';
		var selectedTag = (urlParams.selected_tag) ? urlParams.selected_tag[0] : '';
        var nonce = (urlParams.nonce) ? urlParams.nonce[0]: '';

        if(!nonce){
            return;
        }

		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'linkilo_get_outer_site_recommendation',
                nonce: nonce,
				count: count,
				post_count: (post_count) ? parseInt(post_count): 0,
				post_id: post_id,
                term_id: term_id,
				same_category: sameCategory,
				selected_category: selectedCategory,
				same_tag: sameTag,
				selected_tag: selectedTag,
				type: 'outgoing_suggestions',
                key: key,
			},
			success: function(response){
				console.log(response);
				console.log([url, count, post_count, key]);
                // if there was an error
                if(response.error){
                    // output the error message
                    linkilo_swal(response.error.title, response.error.text, 'error');
                    // and exit
                    return;
                }

				// $el.find('.progress_count').html(response.message);

				if((count * response.batch_size) < response.post_count){
					ajaxGetExternalSiteSuggestions($el, url, response.count, response.post_count, key);
				}else{
					return updateSuggestionDisplay(post_id, term_id, nonce, $el, 'outgoing_suggestions', sameCategory, key, selectedCategory, sameTag, selectedTag);
				}
			},
            error: function(jqXHR, textStatus, errorThrown){
                console.log({jqXHR, textStatus, errorThrown});
            }
		});
	}

	function updateSuggestionDisplay(postId, termId, nonce, $el, type = 'outgoing_suggestions', sameCategory = '', key = null, selectedCategory, sameTag, selectedTag){
		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'update_recommendation_display',
                nonce: nonce,
				post_id: postId,
                term_id: termId,
                key: key,
				type: type,
				same_category: sameCategory,
				selected_category: selectedCategory,
				same_tag: sameTag,
				selected_tag: selectedTag,
			},
			success: function(response){
                // if there was an error
                if(response.error){
                    // output the error message
                    linkilo_swal(response.error.title, response.error.text, 'error');
                    // and exit
                    return;
                }

                // update the suggestion report
				$el.html(response);
				// style the sentences
				styleSentences();
			}
		});
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

	function linkiloImplodeEls(sep, els)
	{
		var res = [];
		$(els).each(function(k, el) {
			res.push(el.outerHTML);
		});

		return res.join(sep);
	}

	function linkiloImplodeText(sep, els)
	{
		var res = [];
		$(els).each(function(k, el) {
			var $el = $(el);
			res.push($el.text());
		});

		return res.join(sep);
	}

	function linkiloPushFix($ex)
	{
		var $div = $("<div/>");
		$div.append($ex);
		return $div.html();
	}

	$(document).on('click', '.linkilo_sentence a', function (e) {
		e.preventDefault();
	});

	var wordClicked = false;
	var wordClickedWait;
	var doubleClickWait;
	var clickedWordId = false;
	var clickedSentenceId = false;
	var notDirectlyStyled = true;
	$(document).on('click', '[class*=linkilo_word]', function (e) {
		e.preventDefault();

		var clickedWord = $(this);
		var sentence = clickedWord.closest('.linkilo_sentence');
		var incomingSelectedId = sentence.closest('.linkilo-incoming-sentence-data-container').data('container-id');

		if(wordClicked && false === clickedWordId){
			return;
		}else if(	clickedWordId === clickedWord.data('linkilo-word-id') &&
					clickedSentenceId === clickedWord.closest('tr').data('linkilo-sentence-id') &&
					notDirectlyStyled
		){
			processDoubleClick(clickedSentenceId, clickedWordId, incomingSelectedId);
			return;
		}else if(wordClicked){
			return;
		}

		wordClicked = true;
		notDirectlyStyled = true;

		// set up a timeout on the word clicked check so if processing fails the user doesn't have to reload the page to use the suggestion panel.
		clearTimeout(wordClickedWait);
		wordClickedWait = setTimeout(function(){ 
			wordClicked = false;
			notDirectlyStyled = true;
		}, 250);

		// set up a double click timeout to clear the double click watcher
		clearTimeout(doubleClickWait);
		doubleClickWait = setTimeout(function(){ 
			clickedWordId = false;
			clickedSentenceId = false;
		}, 200);


		// find the words in the current sentence
		var $words = sentence.find('.linkilo_word');

		// tag all the words in the sentence with ids
		var word_id = 0;
		var word_id_attr = 'linkilo-word-id';
		$words.each(function(i, el) {
			word_id++;
			var $el = $(el);
			$el.data(word_id_attr, word_id);
			$el.attr('data-' + word_id_attr, word_id);
		});

		// get the id of the clicked word and the current sentece
		clickedWordId = clickedWord.data('linkilo-word-id');
		clickedSentenceId = clickedWord.closest('tr').data('linkilo-sentence-id');

		if(clickedWordId > 1){
			var previousTag = sentence.find('[data-linkilo-word-id="' + (clickedWordId - 1) + '"]');
			var nextTag = sentence.find('[data-linkilo-word-id="' + (clickedWordId + 1) + '"]');
			if(nextTag.length && previousTag.hasClass('open-tag') && nextTag.hasClass('close-tag')){
				notDirectlyStyled = false;
			}
		}

		// find all the words in the anchor and get the start and end words of the anchor
		var anchorWords = sentence.find('a span');
		var start = anchorWords.first().data('linkilo-word-id');
		var end = anchorWords.last().data('linkilo-word-id');
		var middleWord = findMiddleWord(start, end, anchorWords);
		var clickedPos = clickedWord.data('linkilo-word-id');

		// get the link minus contents
		var anchorClone = sentence.clone().find('a').html('');

		// if the link start and end is undefinded, insert a link at the click location
		if(undefined === end && undefined === start){
			console.log('no link');

			// set the clicked word as the link's only word
			var linkWords = sentence.find('[data-linkilo-word-id="' + clickedPos + '"]');

			// clone the word
			var clonedWords = linkWords.clone();
			
			// create a new link if we didn't find one
			if(anchorClone.length < 1){
				anchorClone = $('<a href="%view_link%" target="_blank"></a>');
			}

			// insert the cloned words into the cloned anchor
			anchorClone = anchorClone.html(clonedWords);

			// replace the anchor with just the words
			sentence.find('a').replaceWith(sentence.find('a').html());

			// now remove all the new anchor words from the sentence
			sentence.find('[data-linkilo-word-id="' + clickedPos + '"]').remove();

			if((clickedPos - 1) > 0){
				// insert the anchor before the clicked word in the sentence
				anchorClone.insertAfter(sentence.find('[data-linkilo-word-id="' + (clickedPos - 1) + '"]'));
			}else{
				// insert the anchor at the start of the sentence
				sentence.prepend(anchorClone);
			}

			custom_sentence_refresh(sentence);

			if (sentence.closest('.wp-list-table').hasClass('incoming')) {
				sentence.closest('li').find('input[type="radio"]').click();
			}

			wordClicked = false;
			return;
		}

		// find out where the clicked word lands relative to the anchor
		if((clickedPos > end || clickedPos >= middleWord)){
			// if it's past the end of the anchor or middle of the sentence
			console.log('link end');

			// if the user clicked on the last word in the link,
			// reduce the clicked pos by 1 to remove the word from the link
			if(end == clickedPos && start < end){
				clickedPos -= 1;
			}

			var wordIds = numberRange(start, clickedPos);

			// find all the words that will be in the link
			var wordString = '[data-linkilo-word-id="' + wordIds.join('"], [data-linkilo-word-id="') + '"]';
			var linkWords = sentence.find(wordString);

			// clone the words
			var clonedWords = linkWords.clone();
			
			// insert the cloned words into the cloned anchor
			anchorClone = anchorClone.html(clonedWords);

			// replace the anchor with just the words
			sentence.find('a').replaceWith(sentence.find('a').html());

			// now remove all the new anchor words from the sentence
			sentence.find(wordString).remove();

			if((start - 1) > 0){
				// insert the anchor before the clicked word in the sentence
				anchorClone.insertAfter(sentence.find('[data-linkilo-word-id="' + (start - 1) + '"]'));
			}else{
				// insert the anchor at the start of the sentence
				sentence.prepend(anchorClone);
			}
		}else if(clickedPos < start || clickedPos < middleWord){
			console.log('link start');
			// if it's past the end of the anchor or middle of the sentence
			
			// if the user clicked on the last word in the link,
			// increase the clicked pos by 1 to remove the word from the link
			if(start == clickedPos && start < end){
				clickedPos += 1;
			}

			var wordIds = numberRange(clickedPos, end);

			// find all the words that will be in the link
			var wordString = '[data-linkilo-word-id="' + wordIds.join('"], [data-linkilo-word-id="') + '"]';
			var linkWords = sentence.find(wordString);

			// clone the words
			var clonedWords = linkWords.clone();

			// insert the cloned words into the cloned anchor
			anchorClone = anchorClone.html(clonedWords);

			// replace the anchor with just the words
			sentence.find('a').replaceWith(sentence.find('a').html());

			// now remove all the new anchor words from the sentence
			sentence.find(wordString).remove();

			if((clickedPos - 1) > 0){
				// insert the anchor before the clicked word in the sentence
				anchorClone.insertAfter(sentence.find('[data-linkilo-word-id="' + (clickedPos - 1) + '"]'));
			}else{
				// insert the anchor at the start of the sentence
				sentence.prepend(anchorClone);
			}
		}

		spaceSentenceWords(sentence);

		// check for html style tags inside the link
		var tags = $(anchorClone).find('.linkilo_suggestion_tag');

		// if there are some
		if(tags.length){
			// process the tags
			processSentenceTags(sentence, anchorClone);
		}

		styleSentenceWords(sentence);

		custom_sentence_refresh(sentence);

		if (sentence.closest('.wp-list-table').hasClass('incoming')) {
			sentence.closest('li').find('input[type="radio"]').click();
		}

		wordClicked = false;
	});

	function processDoubleClick(sentenceId, wordId, dataId = false){
		// if this is
		if(false !== dataId){
			// get the current sentence
			var sentence = $('tr[data-linkilo-sentence-id="' + sentenceId + '"] .linkilo-incoming-sentence-data-container[data-container-id="' + dataId + '"] [data-linkilo-word-id="' + wordId + '"]').closest('.linkilo_sentence');
		}else{
			// get the current sentence
			var sentence = $('tr[data-linkilo-sentence-id="' + sentenceId + '"] .top-level-sentence [data-linkilo-word-id="' + wordId + '"]').closest('.linkilo_sentence');
		}

		// get the link minus contents
		var anchorClone = sentence.clone().find('a').html('');

		// set the clicked word as the link's only word
		var linkWords = sentence.find('[data-linkilo-word-id="' + wordId + '"]');

		// clone the word
		var clonedWords = linkWords.clone();
		
		// create a new link if we didn't find one
		if(anchorClone.length < 1){
			anchorClone = $('<a href="%view_link%" target="_blank"></a>');
		}

		// insert the cloned words into the cloned anchor
		anchorClone = anchorClone.html(clonedWords);

		// replace the anchor with just the words
		sentence.find('a').replaceWith(sentence.find('a').html());

		// now remove all the new anchor words from the sentence
		sentence.find('[data-linkilo-word-id="' + wordId + '"]').remove();

		if((wordId - 1) > 0){
			// insert the anchor before the clicked word in the sentence
			anchorClone.insertAfter(sentence.find('[data-linkilo-word-id="' + (wordId - 1) + '"]'));
		}else{
			// insert the anchor at the start of the sentence
			sentence.prepend(anchorClone);
		}

		spaceSentenceWords(sentence);

		// check for html style tags inside the link
		var tags = $(anchorClone).find('.linkilo_suggestion_tag');

		// if there are some
		if(tags.length){
			// process the tags
			processSentenceTags(sentence, anchorClone);
		}

		styleSentenceWords(sentence);
		custom_sentence_refresh(sentence);

		if (sentence.closest('.wp-list-table').hasClass('incoming')) {
			sentence.closest('li').find('input[type="radio"]').click();
		}

		wordClicked = false;
		clickedWordId = false;
		clickedSentenceId = false;

		// clear any selections so the user doesn't wind up selecting the full sentence
		clearSelection();
	}

	function clearSelection(){
		if(window.getSelection){
			window.getSelection().removeAllRanges();
		}else if(document.selection){
			document.selection.empty();
		}
	}

	function findMiddleWord(start = 0, end = 0, words = []){
		start = parseInt(start);
		end = parseInt(end);

		if(start === end){
			return start;
		}

		var letterRange = [];
		var totalLetters = 0;
		words.each(function(index, word){
			word = $(word);
			if(!word.hasClass('linkilo_suggestion_tag')){
				var length = word.text().length;
				totalLetters += length;
				letterRange[word.data('linkilo-word-id')] = length;
			}
		});

		var middleLetter = Math.round(totalLetters/2);
		var currentCount = 0;
		var middle = 0;
		for(var i in letterRange){
			currentCount += letterRange[i];

			if(currentCount >= middleLetter){
				middle = i;
				break;
			}
		}

		return middle;
	}

	function numberRange(start = 0, end = 0){
		var result = [];
		for(var i = start; i <= end; i++){
			result.push(i);
		}

		return result;
	}

	/**
	 * Sets the correct word spacing on words in the clicked sentence.
	 * @param {object} sentence 
	 */
	function spaceSentenceWords(sentence){
		var words = sentence.find('.linkilo_word');

		// find all the existing spaces in the sentence
		var spaces = [];
		sentence.contents().filter(function(){
			if(this.nodeType === Node.TEXT_NODE){
				spaces.push(this);
			}
		});

		// and remove them
		$(spaces).remove();

		// now add new spaces to the sentence
		sentence.find('span').map(function(index, element) {
			var $el = $(this);
			var data = [];

			if(0 === index){
				if(undefined !== words[index + 1] && $(words[index + 1]).hasClass('no-space-left')){
					data = [this];
				}else if($el.hasClass('no-space-right')){
					data = [document.createTextNode(' '), this];
				}else{
					data = [this, document.createTextNode(' ')];
				}
			}else{
				if(undefined !== words[index + 1] && $(words[index + 1]).hasClass('no-space-left')){
					data = [this];
				}else if(undefined !== words[index + 1] && $(words[index + 1]).hasClass('no-space-right')){
					data = [document.createTextNode(' '), this];
				}else if($el.hasClass('no-space-right')){
					data = [this];
				}else{
					data = [this, document.createTextNode(' ')];
				}
			}

			$(data).insertAfter(element);
		});
	}

	/**
	 * Moves the html style tags based on the user's link selection so we don't get half a style tag in the link with the other half outside it.
	 * @param sentence 
	 * @param anchor 
	 */
	function processSentenceTags(sentence, anchor){
		var tagTypes = ['linkilo-bold', 'linkilo-ital', 'linkilo-under', 'linkilo-strong', 'linkilo-em'];

		// find all the tags in the anchor and add them to a list
		var anchorTagData = {};
		anchor.find('.linkilo_suggestion_tag').map(function(){
			var $el = $(this);
			for(var i in tagTypes){
				if($el.hasClass(tagTypes[i])){
					if(undefined === anchorTagData[tagTypes[i]]){
						anchorTagData[tagTypes[i]] = [];
					}

					anchorTagData[tagTypes[i]].push($el);
				}
			}
		});

		// look over all the found tags
		var keys = Object.keys(anchorTagData);
		$(keys).each(function(index, key){
			// if the anchor doesn't contain the opening and closing tags, 
			// move the tag to the correct location
			if(anchorTagData[key].length === 1){
				// if the tag is an opening one
				if(anchorTagData[key][0].hasClass('open-tag')){
					// move it right until it's outside the anchor
					var tag = sentence.find(anchorTagData[key][0]).detach();
					tag.insertAfter(sentence.find('a'));
				}else{
					// if the tag is an opening one, move it left until it's outside the anchor
					var tag = sentence.find(anchorTagData[key][0]).detach();
					tag.insertBefore(sentence.find('a'));
				}

			}else if(anchorTagData[key].length > 2){
				// todo handle cases where there's 3 of the same type of tag in the link...
			}
		});

		// now remove any tags that are right next to each other
		var words = sentence.find('span');
		words.map(function(index, element){
			var current = $(element);
			// if this is a style tag and the word after this one is a style tag
			if(current.hasClass('linkilo_suggestion_tag') && undefined !== words[index + 1] && $(words[index + 1]).hasClass('linkilo_suggestion_tag')){
				var next = $(words[index + 1]);

				// see if they're both the same kind of tag
				var tagType = '';
				for(var i in tagTypes){
					if(current.hasClass(tagTypes[i])){
						tagType = tagTypes[i];
						break;
					}
				}

				// if it does
				if(next.hasClass(tagType)){
					// remove both tags
					sentence.find(current).remove();
					sentence.find(next).remove();
				}
			}
		});
	}

	/**
	 * Styles the words in the sentence based on the HTML style tags found in the text.
	 * Mostly this is to give the user some idea of what we're doing with his style tags.
	 * @param sentence 
	 */
	function styleSentenceWords(sentence){
		var tagTypes = ['linkilo-bold', 'linkilo-ital', 'linkilo-under', 'linkilo-strong', 'linkilo-em'];
		var styleSettings = {'linkilo-bold': {'font-weight': 600}, 'linkilo-ital': {'font-style': 'italic'},  'linkilo-under': {'text-decoration': 'underline'}, 'linkilo-strong': {'font-weight': 600}, 'linkilo-em': {'font-style': 'italic'}};
		var styles = {};

		var words = sentence.find('span');
		words.map(function(index, element){
			var current = $(element);
			// if this is a style tag
			if(current.hasClass('linkilo_suggestion_tag')){
				// find out what kind it is
				var tagType = '';
				for(var i in tagTypes){
					if(current.hasClass(tagTypes[i])){
						tagType = tagTypes[i];
						break;
					}
				}

				// if it's an opening tag
				if(current.hasClass('open-tag')){
					// add the correct styles to the styling array to mimic the html tag effect
					for(var key in styleSettings[tagType]){
						styles[key] = styleSettings[tagType][key];
					}
				}else{
					// if it's a closing tag, remove the style
					for(var key in styleSettings[tagType]){
						delete styles[key];
					}
				}
			}else{
				current.removeAttr("style").css(styles);
			}
		});
	}

	/**
	 * Styles all of the page's sentences.
	 */
	function styleSentences(){
		$('#the-list .sentence .linkilo_sentence, #the-list .linkilo-content .linkilo_sentence').each(function(index, sentence){
			styleSentenceWords($(sentence));
		});
	}

	/*function increase_move_progress(i, class_name) {
	  if (i == 0) {
	    i = 1;
	    var elem = document.querySelector("."+class_name);
	    var width = 1;
	    var id = setInterval(frame, 10);
	    function frame() {
	      if (width >= 100) {
	        clearInterval(id);
	        i = 0;
	      } else {
	        width++;
	        elem.style.width = width + "%";
	        elem.innerHTML = width + "%";
	      }
	    }
	  }
	}*/

	$(document).on('change', '#field_same_title', function(e){

		if ($(this).prop('checked')) {
			var sameTitle = 'show';
		}else{
			var sameTitle = 'hide';
		}
		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'update_related_posts_same_title',
				same_title_option: sameTitle,
			},
			success: function(response){
				console.log(response.flag);
				if (response.flag == 'success') {
					location.reload();
				}else{
					console.log(response.flag + ' : ' + response.msg);
				}
			},
            error: function(jqXHR, textStatus, errorThrown){
                console.log({jqXHR, textStatus, errorThrown});
            }
		});
	});
	var same_category_loading = false;

	$(document).on('change', '#field_same_category, #field_same_tag, select[name="linkilo_selected_category"], select[name="linkilo_selected_tag"]', function(){
		if (!same_category_loading) {
			same_category_loading = true;
			var container = $(this).closest('[data-linkilo-ajax-container]');
			var url = container.attr('data-linkilo-ajax-container-url');
			var urlParams = parseURLParams(url);
			var sameCategory = container.find('#field_same_category').prop('checked');
			var sameTag = container.find('#field_same_tag').prop('checked');
			var category_checked = '';
			var tag_checked = '';
			var title_checked = '';
			var post_id = (urlParams.post_id) ? urlParams.post_id[0] : 0;

			// remove any active same category settings
			url = url.replace('&same_category=true', '');

			//category
			if (sameCategory) {
				url += "&same_category=true";
				category_checked = 'checked="checked"';
			}
			if (container.find('select[name="linkilo_selected_category"]').length) {
				url += "&selected_category=" + container.find('select[name="linkilo_selected_category"]').val();
			}

			//tag
			if (sameTag) {
				url += "&same_tag=true";
				tag_checked = 'checked="checked"';
			}
			if (container.find('select[name="linkilo_selected_tag"]').length) {
				url += "&selected_tag=" + container.find('select[name="linkilo_selected_tag"]').val();
			}
			// console.log("same title : " + sameTitle);

			if(urlParams.linkilo_no_preload && '1' === urlParams.linkilo_no_preload[0]){
				var checkAndButton = '<div style="margin-bottom: 15px;">' +
						'<input style="margin-bottom: -5px;" type="checkbox" name="same_category" id="field_same_category_page" ' + category_checked + '>' +
						'<label for="field_same_category_page">Only Show Link Suggestions in the Same Category as This Post</label> <br>' +
						'<input style="margin-bottom: -5px;" type="checkbox" name="same_tag" id="field_same_tag_page" ' + tag_checked + '>' +
						'<label for="field_same_category_page">Only Show Link Suggestions with the Same Tag as This Post</label> <br>' +
					'</div>' +
					'<button id="incoming_suggestions_button" class="sync_linking_keywords_list button-primary" data-id="' + post_id + '" data-type="incoming_suggestions_page_container" data-page="incoming">Custom links</button>';
				container.html(checkAndButton);
			}else{
				container.html('<div class="progress_panel loader"><div class="progress_count" style="width: 100%"></div></div><div class="progress_panel_center" > Loading </div>');
			}

			if(urlParams.type && 'outgoing_suggestions_ajax' === urlParams.type[0]){
				ajaxGetSuggestionsOutbound(container, url, 0);
			}else if(urlParams.type && 'incoming_suggestions_page_container' === urlParams.type[0]){
				ajaxGetSuggestionsIncoming(container, url, 0);
			}

			same_category_loading = false;
		}
	});

	$(document).on('change', '#field_same_category_page', function(){
		var url = document.URL;
		if ($(this).prop('checked')) {
			url += "&same_category=true";
		} else {
			url = url.replace('/&same_category=true/g', '');
		}

		location.href = url;
	});

	$(document).on('click', '.sync_linking_keywords_list', function (e) {
		e.preventDefault();
		// alert('cliked');
		// linkilo_swal('OKGreat Job!', 'Your Links Have Been Added', '',{buttons: ["Stop", "Do it!"]});
		// return false;

		var page = $(this).data('page');
		var links = [];
		var data = [];
		var button = $(this);
		$(this).closest('div:not(#linkilo-incoming-suggestions-head-controls)').find('[linkilo-link-new][type=checkbox]:checked').each(function() {
			if (page == 'incoming') {
				var item = {};
				item.id = $(this).closest('tr').find('.sentence').data('id');
				item.type = $(this).closest('tr').find('.sentence').data('type');
				item.links = [{
					'sentence': $(this).closest('tr').find('.sentence').find('[name="sentence"]').val(),
					'sentence_with_anchor': $(this).closest('tr').find('.linkilo_sentence_with_anchor').html(),
					'custom_sentence': $(this).closest('tr').find('input[name="custom_sentence"]').val()
				}];

				data.push(item);
			} else {
				if ($(this).closest('tr').find('input[type="radio"]:checked').length) {
					var id =  $(this).closest('tr').find('input[type="radio"]:checked').data('id');
					var type = $(this).closest('tr').find('input[type="radio"]:checked').data('type');
					var custom_link = $(this).closest('tr').find('input[type="radio"]:checked').data('custom');
					var post_origin = $(this).closest('tr').find('input[type="radio"]:checked').data('post-origin');
					var site_url = $(this).closest('tr').find('input[type="radio"]:checked').data('site-url');
				} else {
					var id =  $(this).closest('tr').find('.suggestion').data('id');
					var type =  $(this).closest('tr').find('.suggestion').data('type');
					var custom_link =  $(this).closest('tr').find('.suggestion').data('custom');
					var post_origin = $(this).closest('tr').find('.suggestion').data('post-origin');
					var site_url = $(this).closest('tr').find('.suggestion').data('site-url');
				}

				links.push({
					id: id,
					type: type,
					custom_link: custom_link,
					post_origin: post_origin,
					site_url: site_url,
					sentence: $(this).closest('div').find('[name="sentence"]').val(),
					sentence_with_anchor: $(this).closest('div').find('.linkilo_sentence_with_anchor').html(),
					custom_sentence: $(this).closest('.sentence').find('input[name="custom_sentence"]').val()
				});
			}
		});

		if (page == 'outgoing') {
			data.push({'links': links});
		}else{
			button.addClass('linkilo_button_is_active');
		}

		$('.linkilo_keywords_list, .tbl-link-reports .wp-list-table').addClass('ajax_loader');

		var data_post = {
			"id": $(this).data('id'),
			"type": $(this).data('type'),
			"page": $(this).data('page'),
			"action": 'linkilo_save_feed_url_references',
			'data': data,
			'gutenberg' : $('.block-editor-page').length ? true : false
    	};

		$.ajax({
			url: linkilo_ajax.ajax_url,
			dataType: 'json',
			data: data_post,
			method: 'post',
			error: function (jqXHR, textStatus, errorThrown) {
                var wrapper = document.createElement('div');
                $(wrapper).append('<strong>' + textStatus + '</strong><br>');
                $(wrapper).append(jqXHR.responseText);
                linkilo_swal({"title": "Error", "content": wrapper, "icon": "error"});

				$('.linkilo_keywords_list, .tbl-link-reports .wp-list-table').removeClass('ajax_loader');
			},
			success: function (data) {
				if (data.err_msg) {
					linkilo_swal('Error', data.err_msg, 'error');
				} else {
					if (page == 'outgoing') {
						if ($('.editor-post-save-draft').length) {
							$('.editor-post-save-draft').click();
						} else if ($('#save-post').length) {
							$('#save-post').click();
						} else if ($('.editor-post-publish-button').length) {
							$('.editor-post-publish-button').click();
						} else if ($('#publish').length) {
							$('#publish').click();
						} else if ($('.edit-tag-actions').length) {
							$('.edit-tag-actions input[type="submit"]').click();
						}

						// set the flag so we know that the editor needs to be reloaded
						reloadGutenberg = true;
					} else {
						location.reload();
					}
				}
			},
			complete: function(){
				button.removeClass('linkilo_button_is_active');
				$('.linkilo_keywords_list').removeClass('ajax_loader');
			}
		})
	});

	function stristr(haystack, needle, bool)
	{
		// http://jsphp.co/jsphp/fn/view/stristr
		// +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
		// +   bugfxied by: Onno Marsman
		// *     example 1: stristr('Kevin van Zonneveld', 'Van');
		// *     returns 1: 'van Zonneveld'
		// *     example 2: stristr('Kevin van Zonneveld', 'VAN', true);
		// *     returns 2: 'Kevin '
		var pos = 0;

		haystack += '';
		pos = haystack.toLowerCase().indexOf((needle + '').toLowerCase());

		if (pos == -1) {
			return false;
		} else {
			if (bool) {
				return haystack.substr(0, pos);
			} else {
				return haystack.slice(pos);
			}
		}
	}

	function linkilo_handle_errors(resp)
	{
		if (stristr(resp, "520") && stristr(resp, "unknown error") && stristr(resp, "Cloudflare")) {
			linkilo_swal('Error', "It seems you are using CloudFlare and CloudFlare is hiding some error message. Please temporary disable CloudFlare, open reporting page again, look if it has any new errors and send it to us", 'error')
				.then(linkilo_report_next_step);
			return true;
		}

		if (stristr(resp, "504") && stristr(resp, "gateway")) {
			linkilo_swal('Error', "504 error: Gateway timeout - please ask your hosting support about this error", 'error')
				.then(linkilo_report_next_step);
			return true;
		}

		return false;
	}

	function linkilo_report_next_step()
	{
		location.reload();
	}

    /**
     * Makes the call to reset the report data when the user clicks on the "Reset Data" button.
     **/
    function resetReportData(e){
        e.preventDefault();
        var form = $(this);
        var nonce = form.find('[name="reset_data_nonce"]').val();
       
        if(!nonce || form.attr('disabled')){
            return;
        }
        
        // disable the reset button
        form.attr('disabled', true);
        // add a color change to the button indicate it's disabled
        form.find('button.button-primary').addClass('linkilo_button_is_active');
        processReportReset(nonce, 0, true);
    }


    var timeList = [];    
    function processReportReset(nonce = null, loopCount = 0, clearData = false){
        if(!nonce){
            return;
        }

        jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'refresh_record_data',
                nonce: nonce,
                loop_count: loopCount,
                clear_data: clearData,
			},
            error: function (jqXHR, textStatus) {
				var resp = jqXHR.responseText;

				if (linkilo_handle_errors(resp)) {
					linkilo_report_next_step();
					return;
				}

				var wrapper = document.createElement('div');
				$(wrapper).append('<strong>' + textStatus + '</strong><br>');
				$(wrapper).append(jqXHR.responseText);
				linkilo_swal({"title": "Error", "content": wrapper, "icon": "error"}).then(linkilo_report_next_step());
			},
			success: function(response){
                // if there was an error
                if(response.error){
                    linkilo_swal(response.error.title, response.error.text, 'error');
                    return;
                }
                
                // if we've been around a couple times without processing links, there must have been an error
                if(!response.links_to_process_count && response.loop_count > 5){
                    linkilo_swal('Data Reset Error', 'Linkilo has tried a number of times to reset the report data, and it hasn\'t been able to complete the action.', 'error');
                    return;
                }

                // if the data has been successfully reset
                if(response.data_setup_complete){
                    // set the loading screen now that the data setup is complete
                    if(response.loading_screen){
                        $('#wpbody-content').html(response.loading_screen);
                    }
                    // set the time
                    timeList.push(response.time);
                    // and call the data processing function to handle the data
                    processReportData(response.nonce, 0, 0, 0);
                }else{
                    // if we're not done processing links, go around again
                    processReportReset(response.nonce, (response.loop_count + 1), true);
                }
			}
		});
    }

    // listen for clicks on the "Reset Data" button
    $('#linkilo_report_reset_data_form').on('submit', resetReportData);

    /**
     * Process runner that handles the report data generation process.
     * Loops around until all the site's links are inserted into the LW link table
     **/
    function processReportData(nonce = null, loopCount = 0, linkPostsToProcessCount = 0, linkPostsProcessed = 0, metaFilled = false, linksFilled = false){
        if(!nonce){
            return;
        }

        // initialize the stage clock. // The clock is useful for debugging
        if(loopCount < 1){
            if(timeList.length > 0){
                var lastTime = timeList.pop();
                timeList = [lastTime];
            }else{
                timeList = [];
            }
        }

        jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'process_record_data',
                nonce: nonce,
                loop_count: loopCount,
                link_posts_to_process_count: linkPostsToProcessCount,
                link_posts_processed: linkPostsProcessed,
                meta_filled: metaFilled,
                links_filled: linksFilled
			},
            error: function (jqXHR, textStatus, errorThrown) {
				var resp = jqXHR.responseText;

				if (linkilo_handle_errors(resp)) {
					linkilo_report_next_step();
					return;
				}

				var wrapper = document.createElement('div');
				$(wrapper).append('<strong>' + textStatus + '</strong><br>');
				$(wrapper).append(jqXHR.responseText);
				linkilo_swal({"title": "Error", "content": wrapper, "icon": "error"}).then(linkilo_report_next_step());

			},
			success: function(response){
                console.log(response);

                // if there was an error
                if(response.error){
                    // output the error message
                    linkilo_swal(response.error.title, response.error.text, 'error');
                    // and exit
                    return;
                }

                // log the time
                timeList.push(response.time);

                // if the meta has been successfully processed
				if(response.processing_complete){
					// if the processing is complete
					// console.log the time if available
					if(timeList > 1){
						console.log('The post processing took: ' + (timeList[(timeList.length - 1)] - timeList[0]) + ' seconds.');
					}


					// show the external site processing loading page
//					if(response.loading_screen){
//						$('#wpbody-content').html(response.loading_screen);
//					}

					// update the loading bar one more time
					animateTheReportLoadingBar(response);

					// if there are linked sites, show the external site processing loading page
					if(response.loading_screen){
						$('#wpbody-content').html(response.loading_screen);
					}

					// find out if there's external sites we need to get data from
					var externalSites = $('.linkilo_report_need_prepare.processing');

					// if there are no linked sites or the site linking is disabled
					if(externalSites.length < 1 || (0 == linkilo_ajax.site_linking_enabled)){
						// show the user the success message!
						linkilo_swal('Success!', 'Synchronization has been completed.', 'success').then(linkilo_report_next_step);
					}else{
						// call the site updator if there are sites to update
						processExternalSites();
					}

					// and exit since in either case we're done here
					return;
				} else if(response.link_processing_complete){
					// if we've finished loading links into the link table
					// show the post processing loading page
					if(response.loading_screen){
						$('#wpbody-content').html(response.loading_screen);
					}

					// console.log the time if available
					if(timeList > 1){
						console.log('The link processing took: ' + (timeList[(timeList.length - 1)] - timeList[0]) + ' seconds.');
					}

					// re-call the function for the final round of processing
					processReportData(  response.nonce,
						0,
						response.link_posts_to_process_count,
						0,
						response.meta_filled,
						response.links_filled);

				} else if(response.meta_filled){
					// show the link processing loading screen
					if(response.loading_screen){
						$('#wpbody-content').html(response.loading_screen);
					}
					// console.log the time if available
					if(timeList > 1){
						console.log('The meta processing took: ' + (timeList[(timeList.length - 1)] - timeList[0]) + ' seconds.');
					}

					// update the loading bar
					animateTheReportLoadingBar(response);

					// and recall the function to begin the link processing (loading the site's links into the link table)
					processReportData(  response.nonce,                         // nonce
						0,                                      // loop count
						response.link_posts_to_process_count,   // posts/cats to process count
						0,                                      // how many have been processed so far
						response.meta_filled,                   // if the meta processing is complete
						response.links_filled);                 // if the link processing is complete
				} else{
                    // if we're not done processing, go around again
                    processReportData(  response.nonce, 
                                        (response.loop_count + 1), 
                                        response.link_posts_to_process_count, 
                                        response.link_posts_processed,
                                        response.meta_filled,
                                        response.links_filled);
                    
                    // if the meta has been processed
                    if(response.meta_filled){
                        // update the loading bar
                        animateTheReportLoadingBar(response);
                    }
                }
			}
		});
    }

	/**
	 * Processes the external sites by ajax calls
	 **/
	function processExternalSites(){
		var externalSites = $('.linkilo_report_need_prepare.processing').first();

		if(externalSites.length < 1){
			// show the user the success message!
			linkilo_swal('Success!', 'Synchronization has been completed.', 'success').then(linkilo_report_next_step);
		}

		var site = $(externalSites),
			url = site.data('linked-url'), 
			page = site.data('page'), 
			saved = site.data('saved'), 
			total = site.data('total'), 
			nonce = site.data('nonce');


		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'linkilo_refresh_site_data',
				url: url,
				nonce: nonce,
				page: page,
				saved: saved,
				total: total
			},
			success: function(response){
				console.log(response);
				// if there was an error
				if(response.error){
					// remove the processing class
					site.removeClass('processing');
					// make the background of the loading bar red to indicate an error
					site.find('.progress_panel').css({'background': '#c7584d'});
					site.find('.progress_count').css({'background': '#fd2d2d'});
					// and add an error text
					site.find('.linkilo-loading-status').text('Processing Error');

					// and go on to the next site
					processExternalSites();

					return;
				}else if(response.success){
					// if the site is processed, update the display

					// remove the processing class
					site.removeClass('processing');
					// and go on to the next site
					processExternalSites();
					return;
				}else if(response){
					// if there's still posts to process in the current site

					// update the page relavent data
					site.data('page', response.page), 
					site.data('saved', response.saved), 
					site.data('total', response.total), 

					// update the display
					animateLinkedSiteLoadingBar(site, response);

					// and go around again
					processExternalSites();
					return;
				}
			}
		});
	}


    /**
     * Updates the loading bar length and the displayed completion status.
     * 
     * A possible improvement might be to progressively update the loading bar so its more interesting.
     * As it is now, the bar jumps every 60s, so it might be a bit dull and the user might wonder if it's working.
     **/
    function animateTheReportLoadingBar(response){
        // get the loading display
        var loadingDisplay = $('#wpbody-content .linkilo-loading-screen');
        // create some variable to update the display with
        var percentCompleted = Math.floor((response.link_posts_processed/response.link_posts_to_process_count) * 100);
        var displayedStatus = percentCompleted + '%' + ((response.links_filled) ? (', ' + response.link_posts_processed + '/' + response.link_posts_to_process_count) : '') + ' ' + linkilo_ajax.completed;
//        var oldPercent = parseInt(loadingDisplay.find('.progress_count').css('width'));

        // update the display with the new info
        //loadingDisplay.find('.linkilo-loading-status').text(displayedStatus);
        // loadingDisplay.find('.progress_count').css({'width': percentCompleted + '%'});
    }

    /**
     * Updates the loading bars for linked sites during the link scan.
     * Increases the length of the loading bars and the text content contained in the bar as the data is downloaded.
	 * 
     **/
    function animateLinkedSiteLoadingBar(site, response){
        // create some variables to update the display with
        var percentCompleted = Math.floor((response.saved/response.total) * 100);
        var displayedStatus = percentCompleted + '%' + ((response.saved) ? (', ' + response.saved + '/' + response.total) : '');

        // update the display with the new info
        // site.find('.linkilo-loading-status').text(displayedStatus);
        // site.find('.progress_count').css({'width': percentCompleted + '%'});
    }

	$(document).on('click', '.linkilo-collapsible', function (e) {
		if ($(this).hasClass('linkilo-no-action') ||
            $(e.target).hasClass('linkilo_word') || 
            $(e.target).hasClass('add-internal-links') ||
            $(e.target).hasClass('add_custom_link_button') ||
            $(e.target).hasClass('add_custom_link') || 
            $(e.target).parents('.add_custom_link').length || 
            $(this).find('.custom-link-wrapper').length > 0 || 
            $(this).find('.wp-editor-wrap').length > 0
        ) 
        {
			return;
		}

		// exit if the user clicked the "Add" button in the link report
		if($(e.srcElement).hasClass('add-internal-links')){
			return;
		}
		e.preventDefault();

		var $el = $(this);
		var $content = $el.closest('.linkilo-collapsible-wrapper').find('.linkilo-content');
		var cl_active = 'linkilo-active';
		var wrapper = $el.parents('.linkilo-collapsible-wrapper');

		if ($el.hasClass(cl_active)) {
			$el.removeClass(cl_active);
			wrapper.removeClass(cl_active);
			$content.hide();
		} else {
			// if this is the link report or focus keyword report or autolink table or the domains table
			if($('.tbl-link-reports').length || $('#linkilo_focus_keyword_table').length || $('#linkilo_keywords_table').length || $('#report_domains').length){
				// hide any open dropdowns in the same row
				$(this).closest('tr').find('td .linkilo-collapsible').removeClass('linkilo-active');
				$(this).closest('tr').find('td .linkilo-collapsible-wrapper').removeClass('linkilo-active');
				$(this).closest('tr').find('td .linkilo-collapsible-wrapper').find('.linkilo-content').hide();
			}
			$el.addClass(cl_active);
			wrapper.addClass(cl_active);
			$content.show();
		}
	});

	$(document).on('click', '#select_all', function () {
		if ($(this).prop('checked')) {
			if ($('.best_keywords').hasClass('outgoing')) {
				$(this).closest('table').find('.sentence:visible input[type="checkbox"].chk-keywords:visible').prop('checked', true);
			} else {
				$(this).closest('table').find('input[type="checkbox"].chk-keywords:visible').prop('checked', true);
			}

			$('.suggestion-select-all').prop('checked', true);
		} else {
			$(this).closest('table').find('input[type="checkbox"].chk-keywords').prop('checked', false);
			$('.suggestion-select-all').prop('checked', false);
		}
	});

	$(document).on('click', '.best_keywords.outgoing .linkilo-collapsible-wrapper input[type="radio"]', function () {
		var data = $(this).closest('li').find('.data').html();
		var id = $(this).data('id');
		var type = $(this).data('type');
		var suggestion = $(this).data('suggestion');
		var origin = $(this).data('post-origin');
		var siteUrl = $(this).data('site-url');

		$(this).closest('ul').find('input').prop('checked', false);

		$(this).prop('checked', true);
		$(this).closest('.linkilo-collapsible-wrapper').find('.linkilo-collapsible-static').html('<div data-id="' + id + '" data-type="' + type + '" data-post-origin="' + origin + '" data-site-url="' + siteUrl + '">' + data + '<span class="add_custom_link_button link-form-button"> | <a href="javascript:void(0)">Custom Link</a></span><span class="linkilo_add_feed_url_to_ignore link-form-button"> | <a href="javascript:void(0)">Ignore Link</a></span></div>');
		$(this).closest('tr').find('input[type="checkbox"]').prop('checked', false);
		$(this).closest('tr').find('input[type="checkbox"]').val(suggestion + ',' + id);

		if (!$(this).closest('tr').find('input[data-linkilo-custom-anchor]').length && $(this).closest('tr').find('.sentence[data-id="'+id+'"][data-type="'+type+'"]').length) {
			$(this).closest('tr').find('.sentences > div').hide();
			$(this).closest('tr').find('.sentence[data-id="'+id+'"][data-type="'+type+'"]').show();
		}
	});

	$(document).on('click', '.best_keywords.incoming .linkilo-collapsible-wrapper input[type="radio"]', function () {
		var id = $(this).data('id');
		var data = $(this).closest('li').find('.data').html();
		$(this).closest('ul').find('input').prop('checked', false);
		$(this).prop('checked', true);
		$(this).closest('.linkilo-collapsible-wrapper').find('.sentence').html(data + '<span class="linkilo_edit_sentence">| <a href="javascript:void(0)">Edit Sentence</a></span>');
		$(this).closest('tr').find('input[type="checkbox"]').prop('checked', false);
		$(this).closest('tr').find('.raw_html').hide();
		$(this).closest('tr').find('.raw_html[data-id="' + id + '"]').show();
	});

	$(document).on('click', '.best_keywords input[type="checkbox"]', function () {
		if ($(this).prop('checked')) {
			if ($('.best_keywords').hasClass('outgoing')) {
				var checked = $('.best_keywords .sentence:visible input[type="checkbox"].chk-keywords:checked');
			} else {
				var checked = $('.best_keywords input[type="checkbox"].chk-keywords:checked');
			}
			if (checked.length > 50) {
				checked = checked.slice(50);
				console.log(checked);
				checked.each(function(){
					$(this).prop('checked', false);
				});
				linkilo_swal('Warning', 'You can choose only 50 links', 'warning');
			}
		}

	});

	//ignore link in error reports
	/*	Commented unusable code ref:error_report_js
	$(document).on('click', '.column-url .row-actions .linkilo_ignore_link', function () {
		var el = $(this);
		var parent = el.parents('.column-url');
		var data = {
			url: el.data('url'),
			anchor: el.data('anchor'),
			post_id: el.data('post_id'),
			post_type: el.data('post_type'),
			link_id: typeof el.data('link_id') !== 'undefined' ? el.data('link_id') : ''
		};

		if (el.hasClass('linkilo_ignore_link')) {
			var rowParent = el.closest('tr');
		} else {
			var rowParent = el.closest('li');
		}

		parent.html('<div style="margin-left: calc(50% - 16px);" class="la-ball-clip-rotate la-md"><div></div></div>');

		$.post('admin.php?page=linkilo&type=ignore_link', data, function(){
			rowParent.fadeOut(300);
		});
	});*/

	//stop ignoring link in error reports
	/*	Commented unusable code ref:error_report_js
	$(document).on('click', '.column-url .row-actions .linkilo_stop_ignore_link', function () {
		var el = $(this);
		var parent = el.parents('.column-url');
		var data = {
			url: el.data('url'),
			anchor: el.data('anchor'),
			post_id: el.data('post_id'),
			post_type: el.data('post_type'),
			link_id: typeof el.data('link_id') !== 'undefined' ? el.data('link_id') : ''
		};

		if (el.hasClass('linkilo_stop_ignore_link')) {
			var rowParent = el.closest('tr');
		} else {
			var rowParent = el.closest('li');
		}

		parent.html('<div style="margin-left: calc(50% - 16px);" class="la-ball-clip-rotate la-md"><div></div></div>');

		$.post('admin.php?page=linkilo&type=stop_ignore_link', data, function(){
			rowParent.fadeOut(300);
		});
	});*/

	//delete link from post content
	$(document).on('click', '.linkilo_link_delete', function () {
		if (confirm("Are you sure you want to delete this link? This will delete the link from the page that it\'s on.")) {
			var el = $(this);
			var data = {
				url: el.data('url'),
				anchor: el.data('anchor'),
				post_id: el.data('post_id'),
				post_type: el.data('post_type'),
				link_id: typeof el.data('link_id') !== 'undefined' ? el.data('link_id') : ''
			};

			$.post('admin.php?page=linkilo&type=delete_link', data, function(){
				if (el.hasClass('broken_link')) {
					el.closest('tr').fadeOut(300);
				} else {
					el.closest('li').fadeOut(300);
				}
			});
		}
	});

	// ignore an orphaned post from the link report
	$(document).on('click', '.linkilo-ignore-orphaned-post', function (e) {
		e.preventDefault();
		var el = $(this);

		if (confirm("Are you sure you want to ignore this post on the Orphaned Posts view? It will still be visible on the General URLs Records and you can re-add the post to the Orphan URLs from the settings.")) {
			var el = $(this);
			var data = {
				action: 'linkilo_ignore_stray_feed',
				post_id: el.data('post-id'),
				type: el.data('type'),
				nonce: el.data('nonce')
			};
			jQuery.ajax({
				type: 'POST',
				url: ajaxurl,
				dataType: 'json',
				data: data,
				error: function (jqXHR, textStatus, errorThrown) {
					var wrapper = document.createElement('div');
					$(wrapper).append('<strong>' + textStatus + '</strong><br>');
					$(wrapper).append(jqXHR.responseText);
					linkilo_swal({"title": "Error", "content": wrapper, "icon": "error"});
				},
				success: function(response){
					if(response.success){
						if (el.hasClass('linkilo-ignore-orphaned-post')) {
							el.closest('tr').fadeOut(300);
						} else {
							el.closest('li').fadeOut(300);
						}
					}else if(response.error){
						linkilo_swal(response.error.title, response.error.text, 'error');
					}
				}
			});
		}
	});

	$(document).ready(function(){
		var saving = false;

		if (typeof wp.data != 'undefined' && typeof wp.data.select('core/editor') != 'undefined') {
			wp.data.subscribe(function () {
				if (document.body.classList.contains( 'block-editor-page' ) && !saving && reloadGutenberg) {
					saving = true;
					setTimeout(function(){
						$.post( ajaxurl, {action: 'linkilo_editor_reload', post_id: $('#post_ID').val()}, function(data) {
							if (data == 'reload') {
								location.reload();
							}

							saving = false;
							reloadGutenberg = false;
						});
					}, 3000);
				}
			});
		}

		if ($('#post_ID').length) {
			$.post( ajaxurl, {action: 'linkilo_is_outgoing_urls_added', id: $('#post_ID').val(), type: 'post'}, function(data) {
				if (data == 'success') {
					linkilo_swal('Success', 'Links have been added successfully', 'success');
				}
			});
		}

		if ($('#incoming_suggestions_page').length) {
			var id  = $('#incoming_suggestions_page').data('id');
			var type  = $('#incoming_suggestions_page').data('type');

			$.post( ajaxurl, {action: 'linkilo_is_incoming_urls_added', id: id, type: type}, function(data) {
				if (data == 'success') {
					linkilo_swal('Success', 'Links have been added successfully', 'success');
				}
			});
		}

		//show links chart in dashboard
		if ($('#linkilo_links_chart').length) {
			var internal = $('input[name="internal_links_count"]').val();
			var external = $('input[name="total_links_count"]').val() - $('input[name="internal_links_count"]').val();

			$('#linkilo_links_chart').jqChart({
				title: { text: '' },
				legend: {
					title: '',
					font: '15px sans-serif',
					location: 'top',
					border: {visible: false}
				},
				border: { visible: false },
				animation: { duration: 1 },
				shadows: {
					enabled: true
				},
				series: [
					{
						type: 'pie',
						fillStyles: ['#33c7fd', '#7646b0'],
						labels: {
							stringFormat: '%d',
							valueType: 'dataValue',
							font: 'bold 15px sans-serif',
							fillStyle: 'white',
							fontWeight: 'bold'
						},
						explodedRadius: 8,
						explodedSlices: [1],
						data: [['Inner Ratio', internal], ['Outer Ratio', external]],
						labelsPosition: 'inside', // inside, outside
						labelsAlign: 'circle', // circle, column
						labelsExtend: 20,
						leaderLineWidth: 1,
						leaderLineStrokeStyle: 'black'
					}
				]
			});
		}

		//show links click chart in detailed click report
		if ($('#link-click-detail-chart').length) {
			
			var clickData	= JSON.parse($('input#link-click-detail-data').val());
			var range		= JSON.parse($('input#link-click-detail-data-range').val());
			var clickCount = 0;
			var dateRange = getAllDays(range.start, range.end);
			var displayData = [];

			if(clickData !== ''){
				for(var i in dateRange){
					var date = dateRange[i];
					if(clickData[date] !== undefined){
						displayData.push([date, clickData[date]]);
						clickCount += clickData[date];
					}else{
						displayData.push([date, 0]);
					}
				}
			}

			$('#link-click-detail-chart').jqChart({
				title: { text: 'Clicks per day' },
				legend: {
					title: '',
					font: '15px sans-serif',
					location: 'top',
					border: {visible: false},
					visible: false
				},
				border: { visible: false },
				animation: { duration: 1 },
				shadows: {
					enabled: true
				},
				axes: [
					{
						type: 'linear',
						location: 'left',
						minimum: 0,
					},
					{
						location: 'bottom',
						labels: {
							resolveOverlappingMode: 'hide'
						},
						majorTickMarks: {
						},
						minorTickMarks: {
						}
					},
					{
						location: 'bottom',
						title: {
							text: 'Total Clicks for Selected Range: ' + clickCount,
							font: '16px sans-serif',
							fillStyle: '#282828',
						},
						strokeStyle: '#ffffff	',
						labels: {
							resolveOverlappingMode: 'hide'
						},
						majorTickMarks: {
						},
						minorTickMarks: {
						}
					},
				],
				series: [
					{
						type: 'area',
						title: '',
						shadows: {
							enabled: true
						},
//						fillStyles: ['#33c7fd', '#7646b0'],
						lineWidth : 2,
						fillStyle: '#2dc0fd',
						strokeStyle:'#6b3da7',
						markers: { 
							size: 8, 
							type: 'circle',
							strokeStyle: 'black', 
							fillStyle : '#6b3da7', 
							lineWidth: 1 
						},
						labels: {
							visible: false,
							stringFormat: '%d',
							valueType: 'dataValue',
							font: 'bold 15px sans-serif',
							fillStyle: 'transparent',
							fontWeight: 'bold'
						},
						data: displayData,
						leaderLineWidth: 1,
						leaderLineStrokeStyle: 'black'
					}
				]
			});
		}

		function getAllDays(start, end) {
			var s = new Date(start);
			var e = new Date(end);
			var a = [];
		
			while(s < e) {
				a.push(moment(s).format("MMMM DD, YYYY"));
				s = new Date(s.setDate(
					s.getDate() + 1
				))
			}
		
			// add an extra day because the date range counter cuts the last day off.
			a.push(moment(s).format("MMMM DD, YYYY"));

			return a;
		};

	});

	$(document).on('click', '.add_custom_link_button', function(e){
        $(this).closest('div').append('<div class="custom-link-wrapper">' + 
                '<div class="add_custom_link">' +
                    '<input type="text" placeholder="Paste URL or type to search">' +
                    '<div class="links_list"></div>' +
                    '<span class="button-primary cst-btn-clr">' +
                        '<i class="mce-ico mce-i-dashicon dashicons-editor-break"></i>' +
                    '</span>' +
                '</div>' +
                '<div class="cancel_custom_link">' +
                    '<span class="button-primary cst-btn-clr">' +
                        '<i class="mce-ico mce-i-dashicon dashicons-no"></i>' +
                    '</span>' +
                '</div>' +
            '</div>');
        $(this).closest('.suggestion').find('.link-form-button').hide();
        $(this).closest('.linkilo-collapsible-wrapper').find('.link-form-button').hide();
	});

	$(document).on('keyup', '.add_custom_link input[type="text"]', linkilo_link_autocomplete);
	$(document).on('click', '.add_custom_link .links_list .item', linkilo_link_choose);

	var linkilo_link_autocomplete_timeout = null;
	var linkilo_link_number = 0;
	function linkilo_link_autocomplete(e) {
		var list = $(this).closest('div').find('.links_list');

		//choose variant with keyboard
		if ((e.which == 38 || e.which == 40 || e.which == 13) && list.css('display') !== 'none') {
			switch (e.which) {
				case 38:
					linkilo_link_number--;
					if (linkilo_link_number > 0) {
						list.find('.item').removeClass('active');
						list.find('.item:nth-child(' + linkilo_link_number + ')').addClass('active')
					}
					break;
				case 40:
					linkilo_link_number++;
					if (linkilo_link_number <= list.find('.item').length) {
						list.find('.item').removeClass('active');
						list.find('.item:nth-child(' + linkilo_link_number + ')').addClass('active')
					}
					break;
				case 13:
					if (list.find('.item.active').length) {
						var url = list.find('.item.active').data('url');
						list.closest('.add_custom_link').find('input[type="text"]').val(url);
						list.html('').hide();
						linkilo_link_number = 0;
					}
					break;
			}
		} else {
			//search posts
			var search = $(this).val();
			if ($('#_ajax_linking_nonce').length && search.length) {
				var nonce = $('#_ajax_linking_nonce').val();
				clearTimeout(linkilo_link_autocomplete_timeout);
				linkilo_link_autocomplete_timeout = setTimeout(function(){
					$.post(ajaxurl, {
						page: 1,
						search: search,
						action: 'wp-link-ajax',
						_ajax_linking_nonce: nonce,
						'linkilo_custom_link_search': 1
					}, function (response) {
						list.html('');
						response = jQuery.parseJSON(response);
						for (var item of response) {
							list.append('<div class="item" data-url="' + item.permalink + '"><div class="title">' + item.title + '</div><div class="date">' + item.info + '</div></div>');
						}
						list.show();
						linkilo_link_number = 0;
					});
				}, 500);
			}
		}
	}

	function linkilo_link_choose() {
		var url = $(this).data('url');
		$(this).closest('.add_custom_link').find('input[type="text"]').val(url);
		$(this).closest('.links_list').html('').hide();
	}

	$(document).on('click', '.add_custom_link span', function(){
		var el = $(this);
		var link = el.parent().find('input').val();
		if (link) {
			$.post(ajaxurl, {link: link, action: 'linkilo_get_feed_url_title'}, function (response) {
				response = $.parseJSON(response);
				if (!el.parents('.linkilo-collapsible-wrapper').length) {
					var suggestion = el.closest('.suggestion');
					suggestion.html(response.title + '<br><a class="post-slug" target="_blank" href="'+link+'">'+response.link+'</a>' +
						'<span class="add_custom_link_button link-form-button"> | <a href="javascript:void(0)">Custom Link</a></span>');
					suggestion.data('id', response.id);
					suggestion.data('type', response.type);
					suggestion.data('custom', response.link);
				} else {
					var wrapper = el.closest('.linkilo-collapsible-wrapper');
					wrapper.find('input[type="radio"]').prop('checked', false);
					wrapper.find('.linkilo-content ul').prepend('<li>' +
						'<div>' +
						'<input type="radio" checked="" data-id="'+response.id+'" data-type="'+response.type+'" data-suggestion="-1" data-custom="'+link+'" data-post-origin="internal" data-site-url="">' +
						'<span class="data">' +
						'<span style="opacity:1">'+response.title+'</span><br>' +
						'<a class="post-slug" target="_blank" href="'+link+'">'+response.link+'</a>\n' +
						'</span>' +
						'</div>' +
						'</li>');
					wrapper.find('input[type="radio"]')[0].click();
					wrapper.find('.linkilo-collapsible').addClass('linkilo-active');
					wrapper.find('.linkilo-content').show();
				}
			});
		} else {
			alert("The link is empty!");
		}
	});

    // if the user cancels the custom link
    $(document).on('click', '.cancel_custom_link span', function(){
        $(this).closest('.suggestion').find('.link-form-button').show();
        $(this).closest('.linkilo-collapsible-wrapper').find('.link-form-button').show();
        $(this).closest('.custom-link-wrapper').remove();
    });

	//show edit sentence form
	$(document).on('click', '.linkilo_edit_sentence', function(){
		var block = $(this).closest('.sentence');
		var form = block.find('.linkilo_edit_sentence_form');
		var id = 'linkilo_editor' + block.data('id');
		var sentence = form.find('.linkilo_content').html();

		if (typeof incoming_internal_link !== 'undefined') {
			var link = incoming_internal_link;
		} else {
			var link = $(this).closest('tr').find('.post-slug:first').attr('href');
		}

		sentence = sentence.replace('%view_link%', link);
		form.find('.linkilo_content').attr('id', id).html(sentence).show();
		form.show();
		var textarea_height = form.find('.linkilo_content').height() + 100;
		form.find('.linkilo_content').height(textarea_height);
        if(undefined === wp.blockEditor){
            wp.editor.initialize(id, {
                tinymce: true,
                quicktags: true,
            });
        }else{
            wp.oldEditor.initialize(id, {
                tinymce: true,
                quicktags: true,
            }); 
        }

		block.find('input[type="checkbox"], .linkilo_sentence_with_anchor, .linkilo_edit_sentence').hide();
		setTimeout(function(){ block.find('.mce-tinymce').show(); }, 500);
		form.find('.linkilo_content').hide();
		form.show();
	});

	//Cancel button pressed
	$(document).on('click', '.linkilo_edit_sentence_form .button-secondary', function(){
		var block = $(this).closest('.sentence');
		linkilo_editor_remove(block);
	});

	//Save edited sentence
	$(document).on('click', '.linkilo_edit_sentence_form .button-primary', function(){
		var block = $(this).closest('.sentence');
		var id = 'linkilo_editor' + block.data('id');

		//get content from the editor
		var sentence;
		if ($('#' + id).css('display') == 'none') {
			var editor = tinyMCE.get(id);
			sentence = editor.getContent();
		} else {
			sentence = $('#' + id).val();
		}

		//remove multiple whitespaces and outer P tag
		if (sentence.substr(0,3) == '<p>') {
			sentence = sentence.substr(3);
		}
		if (sentence.substr(-4) == '</p>') {
			sentence = sentence.substr(0, sentence.length - 4);
		}
		var sentence_clear = sentence;

		//put each word to span
		var link = sentence.match(/<a[^>]+>/);
		if (link[0] != null) {
			sentence = sentence.replace(/<a[^>]+\s*>/, ' %link_start% ');
			sentence = sentence.replace(/\s*<\/a>/, ' %link_end% ');
		}

		// check for a second link
		var secondLink = sentence.match(/<a[^>]+>/);
		if (secondLink != null && secondLink[0] != null) {
			// if there are more links, remove them
			sentence = sentence.replace(/<a[^>]+\s*>/g, '');
			sentence = sentence.replace(/\s*<\/a>/g, '');
			// and update the clear sentence so the additional links aren't present
			sentence_clear = sentence.replace(/%link_start%/g, link[0]);
			sentence_clear = sentence_clear.replace(/%link_end%/g, '</a>');
		}

		sentence = sentence.replace(/\s+/g, ' ');
		sentence = sentence.replace(/ /g, '</span> <span class="linkilo_word">');
		sentence = '<span class="linkilo_word">' + sentence + '</span>';
		if (link[0] != null) {
			sentence = sentence.replace(/<span class="linkilo_word">%link_start%<\/span>/g, link[0]);
			sentence = sentence.replace(/<span class="linkilo_word">%link_end%<\/span>/g, '</a>');
		}

		block.find('.linkilo_sentence').html(sentence);
		block.find('input[name="custom_sentence"]').val(btoa(unescape(encodeURIComponent(sentence_clear))));

		if (block.closest('tr').find('.raw_html').length) {
			sentence_clear = sentence_clear.replace(/</g, '&lt;');
			sentence_clear = sentence_clear.replace(/>/g, '&gt;');
			block.closest('tr').find('.raw_html').hide();
			block.closest('tr').find('.raw_html.custom-text').html(sentence_clear).show();
		}

		linkilo_editor_remove(block)
	});

	//Remove WP Editor after sentence editing
	function linkilo_editor_remove(block) {
		var form = block.find('.linkilo_edit_sentence_form');
		var textarea_height = form.find('.linkilo_content').height() - 100;
		form.find('.linkilo_content').height(textarea_height);
		form.hide();
		form.find('.linkilo_content').attr('id', '').prependTo(form);
        if(undefined === wp.blockEditor){
            wp.editor.remove('linkilo_editor' + block.data('id'));
        }else{
            wp.oldEditor.remove('linkilo_editor' + block.data('id')); 
        }
		form.find('.wp-editor-wrap').remove();
		block.find('input[type="checkbox"], .linkilo_sentence_with_anchor, .linkilo_edit_sentence').show();
	}

	function custom_sentence_refresh(el) {
		var input = el.closest('.sentence').find('input[name="custom_sentence"]');
		var sentence = el.closest('.linkilo_sentence').html();
		sentence = sentence.replace(/<span[^>]+linkilo_suggestion_tag[^>]+>([a-zA-Z0-9=+]+)<\/span>/g, function (x) {
			x = x.replace(/<span[^>]+>/g, '');
			x = x.replace(/<\/span>/g, '');
			return atob(x);
		});
		sentence = sentence.replace(/<\/span> <\/a>/g, '<\/span><\/a> ');
		sentence = sentence.replace(/<span[^>]+>/g, '');
		sentence = sentence.replace(/<\/span>/g, '');
		el.closest('.sentence').find('.linkilo_content').html(sentence);

		if (input.val() !== '') {
			input.val(btoa(unescape(encodeURIComponent(sentence))));
		}
	}

	$(document).on('click', '.linkilo_add_feed_url_to_ignore', function(){
		if (confirm('You are about to add this link to your ignore list and it will never be suggested as a link in the future. However, you can reverse this decision on the settings page.')) {
			var block = $(this).closest('div');
			var id = block.data('id');
			var type = block.data('type');
			var postOrigin = block.data('post-origin');
			var siteUrl = block.data('site-url');

			$.post(ajaxurl, {
				id: id,
				type: type,
				post_origin: postOrigin,
				site_url: siteUrl,
				action: 'linkilo_add_feed_url_to_ignore'
			}, function (response) {
				response = $.parseJSON(response);
				if (response.error) {
					linkilo_swal('Error', response.error, 'error');
				} else {
					if (block.closest('.suggestion').length) {
						block.closest('tr').fadeOut(300, function(){
							$(this).remove();
						});
					} else {
						var id = block.data('id');
						var type = block.data('type');
						var wrapper = block.closest('.linkilo-collapsible-wrapper');

						wrapper.find('input[data-id="' +  id + '"][data-type="' +  type + '"]').closest('li').remove();
						wrapper.find('li:first input').prop('checked', true).click();
					}
					linkilo_swal('Success', 'Link was added to the ignored list successfully!', 'success');
				}
			});
		}
	});

	var mouseExit;
	$(document).on('mouseover', '.linkilo_help i, .linkilo_help div', function(){
		clearTimeout(mouseExit);
		$('.linkilo_help div').hide();
		$(this).parent().children('div').show();
	});

	$(document).on('mouseout', '.linkilo_help i, .linkilo_help div', function(){
		var element = this;
		mouseExit = setTimeout(function(){
			$(element).parent().children('div').hide();
		}, 250);
		
	});

	$(document).on('click', '.csv_button', function(){
		$(this).addClass('linkilo_button_is_active');
		var type = $(this).data('type');
		linkilo_csv_request(type, 1);
	});

	function linkilo_csv_request(type, count) {
		$.post(ajaxurl, {
			count: count,
			type: type,
			action: 'linkilo_csv_export'
		}, function (response) {
			if (response.error) {
				linkilo_swal('Error', response.error, 'error');
			} else {
				console.log(response);
				if (response.filename) {
					$('.csv_button').removeClass('linkilo_button_is_active');
					var link = document.createElement('a');
					link.href = response.filename;
					link.download = 'links_export.csv';
					document.body.appendChild(link);
					link.click();
					document.body.removeChild(link);
//					location.href = response.filename;
				} else {
					linkilo_csv_request(response.type, ++response.count);
				}
			}
		});
	}

	$(document).on('click', '.return_to_report', function(e){
		e.preventDefault();

		// if a link is specified
		if(undefined !== this.href){
			// parse the url
			var params = parseURLParams(this.href);
			// if the url is back to an edit page
			if(	undefined !== typeof params &&
				( (undefined !== params.action && undefined !== params.post && 'edit' === params.action[0]) || params.direct_return)
			){
				if(params.ret_url && params.ret_url[0]){
					var link = atob(params.ret_url[0]);
				}else{
					var link = this.href;
				}

				// redirect back to the page
				location.href = link;
				return;
			}
		}

		$.post(ajaxurl, {
			action: 'linkilo_back_to_record',
		}, function(){
			window.close();
		});
	});
	
	$(document).on('click', '.linkilo_gsc_switch_app', function(){
		if($(this).hasClass('enter-custom')){
			$('.linkilo_gsc_app_inputs').hide();
			$('.linkilo_gsc_custom_app_inputs').show();
		}else{
			$('.linkilo_gsc_app_inputs').show();
			$('.linkilo_gsc_custom_app_inputs').hide();
		}
	});

	$(document).on('click', '.linkilo-get-gsc-access-token', function(){
		$('.linkilo_gsc_get_authorize').show();
		$(this).hide();
	});

	$(document).on('click', '.linkilo_gsc_enter_app_creds', function(){
		$('#frmSaveSettings').trigger('submit');
	});

	$(document).on('click', '.linkilo_gsc_clear_app_creds', function(){
		$.post(ajaxurl, {
			action: 'linkilo_clear_gsc_app_credentials',
			nonce: $(this).data('nonce')
		}, function (response) {
			location.reload();
		});
	});

	$(document).on('click', '.linkilo-gsc-deactivate-app', function(){
		$.post(ajaxurl, {
			action: 'linkilo_gsc_deactivate_app',
			nonce: $(this).data('nonce')
		}, function (response) {
			location.reload();
		});
	});


    /** Sticky Header **/
	// Makes the thead sticky to the top of the screen when scrolled down far enough
	if($('.wp-list-table').length){
		var theadTop = $('.wp-list-table').offset().top;
		var adminBarHeight = parseInt(document.getElementById('wpadminbar').offsetHeight);
		var scrollLine = (theadTop - adminBarHeight);
		var sticky = false;

		// duplicate the footer and insert in the table head
		$('.wp-list-table tfoot tr').clone().addClass('linkilo-sticky-header').css({'display': 'none', 'top': adminBarHeight + 'px'}).appendTo('.wp-list-table thead');

		// resizes the header elements
		function sizeHeaderElements(){
			// adjust for any change in the admin bar
			adminBarHeight = parseInt(document.getElementById('wpadminbar').offsetHeight);
			$('.linkilo-sticky-header').css({'top': adminBarHeight + 'px'});

			// adjust the size of the header columns
			var elements = $('.linkilo-sticky-header').find('th');
			$('.wp-list-table thead tr').not('.linkilo-sticky-header').find('th').each(function(index, element){
				var width = getComputedStyle(element).width;

				$(elements[index]).css({'width': width});
			});
		}
		sizeHeaderElements();

		function resetScrollLinePositions(){
			theadTop = $('.wp-list-table').offset().top;
			adminBarHeight = parseInt(document.getElementById('wpadminbar').offsetHeight);
			scrollLine = (theadTop - adminBarHeight);
		}

		$(window).on('scroll', function(e){
			var scroll = parseInt(document.documentElement.scrollTop);

			// if we've passed the scroll line and the head is not sticky
			if(scroll > scrollLine && !sticky){
				// sticky the header
				$('.linkilo-sticky-header').css({'display': 'table-row'});
				sticky = true;
			}else if(scroll < scrollLine && sticky){
				// if we're above the scroll line and the header is sticky, unsticky it
				$('.linkilo-sticky-header').css({'display': 'none'});
				sticky = false;
			}
		});

		var wait;
		$(window).on('resize', function(){
			clearTimeout(wait);
			setTimeout(function(){ 
				sizeHeaderElements(); 
				resetScrollLinePositions();
			}, 150);
		});

		setTimeout(function(){ 
			resetScrollLinePositions();
		}, 1500);
	}
    /** /Sticky Header **/



})(jQuery);
