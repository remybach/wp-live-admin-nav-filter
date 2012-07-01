// lnf = live nav filter : use this in an attempt to avoid clashing with other plugins' class names.
(function($) {
	jQuery(function($) {
		// Add our filter field.
		var filter_field = '<input type="search" placeholder="filter" id="lnf" class="lnf-filter-'+
							(LNF_POSITION ? LNF_POSITION : 'bottom')+'"'+
							(LNF_HIDDEN ? 'style="display:none;"' : '')+' />';

		// Position this as specified in our settings
		if (LNF_POSITION && LNF_POSITION === 'top') {
			$('#adminmenu').before(filter_field);
		} else {
			$('#adminmenu').after(filter_field);
		}
		$('#adminmenu li > a').each(function(i, val) {
			var searchable = $(this).html()
				.replace(/^<.*?\/?>/, '')	// Remove any html tags at the beginning of the element.
				.match(/([\w\s-&;]+)/);		// Lastly, search for the first words (\w\s) in the <a> (sometimes there's
											// 	additional HTML for update counts, etc.) Let's also include dashes,
											// 	and allow html entities (-&;)

			// Don't go any further if this doesn't match
			if ( !searchable || (searchable && searchable.length < 2) ) { return false; }

			// Append this as a data attribute for searching later (we do this so that we can still search while the
			// 	element has our additional highlighting markup in it).
			$.data(this, 'lnf-search', searchable[1]);
		});

		var timer = false;
		$('#lnf').keyup(function(e) {
			var lnf_input = this;

			// Add some latency so the menu doesn't flash when typing faster than ~60wpm...
			// 60wpm / 5 (approximate average of characters per word) / 60 seconds = 0.2seconds (or 200ms)
			clearTimeout(timer);
			timer = setTimeout(function() {
				filter.call(lnf_input, e);
			}, 200);
		}).change(filter);

		// If the user hits '/', focus our filter field.
		$(document).keyup(function(e) {
			// If the user isn't trying to legitimately type & the key that was pressed matches
			if ( e.keyCode === 191 && $('input:focus, textarea:focus').length === 0 ) { // 191 == /
				if ( $('#lnf:hidden').length > 0 ) {
					$('#lnf:hidden').slideDown(100, function() {
						$(this).focus();
					});
				} else {
					$('#lnf').focus();
				}
				
				return false;
			}
		});

		function filter(e) {
			if ( e.keyCode === 13 ) { // If the user hits enter, let's go to the selected link.
				location.href = $('.lnf-focused-item').attr('href');
			} else if ( e.keyCode === 40 ) { // If the user has pressed down
				focus(1);
				return false;
			} else if ( e.keyCode === 38 ) { // If the user has pressed up
				focus(-1);
				return false;
			} else if ( this.value && this.value.length >= 3 ) { // Make sure there's something to search for
				var search = new RegExp(this.value, 'i');

				// Make sure there is even something matching at all here
				if ( search.test( $('#adminmenu').html() ) ) {
					$('#adminmenu li > a').each(function(i, val) {
						// If the text in this link matches, work our magic.
						if ( !search.test( $(this).data('lnf-search') ) ) {
							$(this).parent('li').fadeTo('fast', 0.15).addClass('lnf-hidden');
						} else {
							reset(this);
							unhighlight($(this).find('.lnf-highlight'));
							$(this).html(val.innerHTML.replace(search, '<mark class="lnf-highlight">'+val.innerHTML.match(search)[0]+'</mark>'));

							// If this is a sub menu, show it
							if ( $(this).parents('.wp-submenu').length > 0 ) {
								$(this).parents('.wp-submenu').addClass('lnf-sub-open');
								$(this).parents('.lnf-hidden').fadeTo('fast', 1).removeClass('lnf-hidden');
							}
						}
					});

					// Focus the first result
					focus(0);

					$('#lnf').removeClass('lnf-error');
				} else {
					reset('.lnf-hidden');
					unhighlight( $('.lnf-highlight') );
					$('#lnf').addClass('lnf-error');
				}
			} else {
				if (e.keyCode === 27 && LNF_HIDDEN) { // 27 == Escape
					$('#lnf').slideUp(100).blur();
				}

				reset('.lnf-hidden');
				unhighlight( $('.lnf-highlight') );
				$('#lnf').removeClass('lnf-error');
				$('.lnf-sub-open').removeClass('lnf-sub-open');
				$('.lnf-focused-item').removeClass('lnf-focused-item');
			}
		}

		/**
		 * Reset the given element back to it's original state
		 * @param  DOM Element what The element to reset.
		 */
		function reset(what) {
			$(what).fadeTo('fast', 1).removeClass('lnf-hidden').parents('.lnf-focus').removeClass('lnf-focus');
			$(what).parents('.lnf-focused-item').removeClass('lnf-focused-item');

			$('#adminmenu .wp-submenu:visible').each(function(i, val) {
				if ( $(this).hasClass('lnf-sub-open') && $(this).parent('li').find('.lnf-highlight').length === 0 ) {
					$(this).removeClass('lnf-sub-open');
				}
			});
		}

		/**
		 * Remove the highlight wrapper from the given element.
		 * @param  DOM Element what The element to remove all highlighting from.
		 */
		function unhighlight(what) {
			// Remove the highlighting
			$(what).each(function(i, val) {
				$(this).replaceWith(this.innerHTML);
			});
		}

		/**
		 * Focus the next/previous element. Has scope for selecting the first item too if none are selected yet.
		 * @param  Number which_way Either -1 or 1 depending on whether to go to the previous or next item respectively.
		 */
		function focus(which_way) {
			var i = 0,
				highlighted = $('.lnf-highlight')
				where_to = 0;

			// If there isn't a focused item, make it the first one.
			if ( $('.lnf-focused-item').length === 0 ) {
				which_way = 0;
			} else {
				// Get the index of the currently focused item.
				i = $.inArray( $('.lnf-focused-item .lnf-highlight')[0], highlighted );
			}

			// Make the selection wrap around to the beginning when hitting the end and vice versa
			if ( i+which_way < 0 ) { // when pressing up on the first item.
				where_to = highlighted.length-1;
			} else if ( i+which_way > highlighted.length-1 ) { // when pressing down on the last item.
				where_to = 0;
			} else { // otherwise it's safe to go to the next/previous item.
				where_to = i+which_way;
			}

			// Remove the currently focused item.
			$('.lnf-focus, .lnf-focused-item').removeClass('lnf-focus').removeClass('lnf-focused-item');

			// Add the highlight class to the li that's highest up in the dom.
			var parent_lis = $( $('.lnf-highlight').get(where_to) ).parents('li');
			$(parent_lis[parent_lis.length-1]).addClass('lnf-focus');
			// Also indicate which is the currently selected link (so the user can see where hitting the Enter key takes them.)
			$( $('.lnf-highlight').get(where_to) ).parents('a').addClass('lnf-focused-item');
		}

		/*===== Give the user predefined options for colours on the options page. =====*/
		if ( $('#border_colour').length > 0 ) {
			var colours = {
				blue:'80C8F0',
				green:'6EAF51',
				orange:'EC6D1E',
				pink:'EA6C9C',
				purple:'A05DA2',
				yellow:'FFED2B'
			};

			// Add our suggested colour swatches before the input field.
			$('#border_colour, #highlight_colour').before('<div class="lnf-colour-options"/>');
			$.each(colours, function(name, hex) {
				$('.lnf-colour-options').each(function() {
					var selected = false;
					if ( $(this).next('input').val().match(hex) ) {
						selected = true;
					}
					$(this).append(''+
						'<span '+
							'class="lnf-colour lnf-'+name+(selected ? ' selected' : '')+'" '+
							'data-hex="'+hex+'" '+
							'style="background:#'+hex+';'+(selected ? '' : 'border:1px solid #'+hex)+'" '+
						'/>');
				});
			});

			// Give the colour swatches their functionality here.
			$('.lnf-colour').click(function(e) {
				var current = $(this).parents('.lnf-colour-options').find('.selected');
				
				// Update our input field.
				$(this).parents('.lnf-colour-options').next('input').val( '#'+$(this).data('hex') );
				// Clear the currently selected swatch
				current.removeClass('selected').css('border', '1px solid #'+$(current).data('hex'));
				// Indicate that this is the currently selected swatch.
				$(this).addClass('selected');

				e.preventDefault();
			});
		}
	});
})(jQuery);