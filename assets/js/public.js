(function ($) {

	var TKEmojiReaction = {
		addReaction: function( container, emoji ) {
			var emojiImage = emojione.shortnameToImage( emoji );

			var clone = container.find( '.reaction.template' ).clone();
			clone.attr( 'data-emoji', emoji );
			clone.find('button').prepend( emojiImage );
			TKEmojiReaction.updateCount( clone );
			clone.removeClass( 'template' ).addClass('tker_active');
			clone.css('display', 'inline-block');
			container.append( clone );
		},
		updateCount: function( node, add = true ) {
			var count = node.find( '.count' ).text();
			if ( add ) {
				count++;
				node.addClass('tker_active');
			} else {
				count--;
				node.removeClass('tker_active');
			}
			node.find('.count').text( count );
			if ( count === 0 ) {
				node.remove();
			}
		},
		saveReactionToDB: function( container, comment_id, emoji ) {
			if ( ! TKER.logged_in ) {
				$(document).trigger('tker_ask_for_login');
				return;
			}

			$.ajax({
				method: "POST",
				url: TKER.ajaxurl,
				data: {
					action: 'tk_emoji_reaction_save',
					nonce: TKER.nonce,
					comment_id: comment_id,
					emoji: emoji,
				},
				success: function(res) {
					if ( res.success ) {
						var existingDiv = container.find( '[data-emoji="' + emoji + '"]' );
						if ( existingDiv.length ) {
							TKEmojiReaction.updateCount( existingDiv, res.data );
						} else {
							TKEmojiReaction.addReaction( container, emoji );
						}
					}
				},
			});
		}
	}

	$( function() {

		var picker;

		$( "#real-emojionearea" ).emojioneArea( {
	    standalone: true,
	    autocomplete: false,
	    emojiPlaceholder: "",
	    pickerPosition: 'right',
	    filters: {
	    	recent: false
	    },
	    saveEmojisAs: 'unicode',
	    events: {
	    	ready: function() {
  	      picker = {
  					api: this,
  	        wrapper: this.editor.parent().hide()
  	      };
  	    },
	    	emojibtn_click: function ( btn ) {
	    		var emojiName = btn.data( 'name' );
	    		var container = btn.closest( '.comment-reactions' );
	    		var comment_id = container.attr('data-comment-id');

    			TKEmojiReaction.saveReactionToDB( container, comment_id, emojiName );
	    	}
	    }
		});

		$(document).on('click', '.tker-reaction-picker', function() {
			picker.element = $(this);
		  // picker.editor = picker.element.children(".emojionearea-editor");
		  picker.element.before(picker.wrapper.show());
		  // picker.api.editor.html(picker.editor.html()).attr("class", picker.editor.attr("class"));
		  picker.api.showPicker();
		});

		$(document).on('click', '.reaction button', function( e ) {
			e.preventDefault();
			var container = $(this).closest( '.comment-reactions' );
			var comment_id = container.attr('data-comment-id');
			var emojiName = $(this).parent().data( 'emoji' );
			TKEmojiReaction.saveReactionToDB( container, comment_id, emojiName );
		});
	});
})(jQuery);
