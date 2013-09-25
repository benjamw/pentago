

if ('undefined' === typeof GAME) {
	var GAME = { };
}


// power the auto refreshing game page

;GAME.RELOAD = true;
GAME.REFRESH = (function(GAME, $, window) {

	'use strict';

	var jqXHR = false,
		timer = false,
		timeout = 2001, // ms


		stop = function( ) {
console.log('REFRESH TIMER - STOPPING');
			clearTimeout(timer);
			timer = false;
		},


		start = function( ) {
console.log('REFRESH TIMER - STARTING');
			timer = setTimeout(refresh, timeout);
		},


		refresh = function( ) {
console.log('REFRESH TIMER - REFRESHING');
			// no debug redirect, just do it

			// only run this if the previous ajax call has completed
			if (false == jqXHR) {
				jqXHR = $.ajax({
					type: 'POST',
					url: 'ajax_helper.php',
					data: 'refresh=1',
					success: function(msg) {
						if ((parseInt(msg, 10) !== parseInt(GAME.last_move, 10)) && GAME.RELOAD) {
							// don't just reload( ), it tries to submit the POST again
							window.location = window.location.href;
						}
					}
				})
				.always( function( ) {
					jqXHR = false;
				});
			}

			// successively increase the timeout time in case someone
			// leaves their window open, don't poll the server every
			// two seconds for the rest of time
			if (0 == (timeout % 5)) {
				timeout += Math.floor(timeout * 0.001) * 1000;
			}

			++timeout;
console.log('REFRESH TIMER - '+ timeout);

			start( );
		};


	// run the ajax refresher
	if ( ! GAME.my_turn && ('finished' !== GAME.state)) {
		refresh( );

		// set some things that will halt the timer
		$('#chatbox form input').focus(stop);

		$('#chatbox form input').blur( function( ) {
			if ('' !== $(this).val( )) {
				start( );
			}
		});
	}

	return {
		start: start,
		stop: stop
	};

}(GAME, jQuery, window));


// power everything else
;(function(GAME, $, window) {

	'use strict';


// --- VARIABLES ---


	var time = [ ];


// --- PROTOTYPES ---


	if ('function' !== typeof Array.prototype.reduce) {
		Array.prototype.reduce = function(callback, opt_initialValue) {

			'use strict';

			if (null === this || 'undefined' === typeof this) {
				// At the moment all modern browsers, that support strict mode, have
				// native implementation of Array.prototype.reduce. For instance, IE8
				// does not support strict mode, so this check is actually useless.
				throw new TypeError('Array.prototype.reduce called on null or undefined');
			}

			if ('function' !== typeof callback) {
				throw new TypeError(callback + ' is not a function');
			}

			var index, value,
				length = this.length >>> 0,
				isValueSet = false;

			if (1 < arguments.length) {
				value = opt_initialValue;
				isValueSet = true;
			}

			for (index = 0; length > index; ++index) {
				if (this.hasOwnProperty(index)) {
					if (isValueSet) {
						value = callback(value, this[index], index, this);
					}
					else {
						value = this[index];
						isValueSet = true;
					}
				}
			}

			if ( ! isValueSet) {
				throw new TypeError('Reduce of empty array with no initial value');
			}

			return value;
		};
	}


	$.fn.animateRotate = function(angle, duration, easing, complete) {
		var args = $.speed(duration, easing, complete);
		var step = args.step;
		return this.each(function(i, e) {
			args.step = function(now) {
				$.style(e, 'transform', 'rotate(' + now + 'deg)');
				if (step) return step.apply(this, arguments);
			};

			$({deg: 0}).animate({deg: angle}, args);
		});
	};


// --- FUNCTIONS ---


	function set_square(event) {
		// don't allow the event to bubble up the DOM tree
		event.stopPropagation( );

		// set the time of the click
		time.push(new Date( ).getTime( ));

		var $this = $(this),
			$move = $('#move');

		if ('s_' === $this.attr('id').slice(0, 2)) {
			// update the form and place the piece
			$('#move').val(GAME.code + $this.attr('id').slice(2));
			$this.addClass(GAME.code.toLowerCase( ));

			// show the "remove piece" link
			$('#remove_piece').show( ).on('click', function( ) {
				$move.val('');
				$this.removeClass(GAME.code.toLowerCase( ));
				time = [ ];
			});

			// show the rotate arrows
			$('.rotate').show( );
		}
		else {
			// check the times and show a confirmation if the clicks were too fast
			if (1000 > (time[1] - time[0])) {
				if ( ! confirm('You clicked that rotate button awfully fast...  ('+ (time[1] - time[0]) +' ms)\nWas that what you meant to do?  (Rotating '+ $this.attr('id').slice(3) +')')) {
					time = time.splice(1, 1);
					return;
				}
			}

			$move.val($move.val( ) + $this.attr('id').slice(2));

			// rotate the block
			$('#blk_'+ $this.attr('id').slice(2, 3)).css('z-index', 10).animateRotate((('R' === $this.attr('id').slice(3)) ? 90 : -90), 500, 'swing');

			if (debug) {
				window.location = 'ajax_helper.php'+debug_query+'&'+$('form#game').serialize( )+'&turn=1';
				return false;
			}

			// ajax the form
			$.ajax({
				type: 'POST',
				url: 'ajax_helper.php',
				data: $('form#game').serialize( )+'&turn=1',
				success: function(msg) {
					// if something happened, just reload
					if ('{' != msg[0]) {
						alert('ERROR: AJAX Failed');
						if (GAME.RELOAD) { window.location.reload( ); }
						return;
					}

					var reply = JSON.parse(msg);

					if (reply.error) {
						alert(reply.error);
						if (GAME.RELOAD) { window.location.reload( ); }
						return;
					}

					if (GAME.RELOAD) { window.location.reload( ); }
				}
			});
		}
	}


	/**
	 *	Returns the index of a square board element
	 *	that looks something like a sudoku board
	 *
	 * @param int the n-th block in the board
	 * @param int the n-th element in the block
	 * @param int optional the number of blocks per side in the board (default: 3)
	 * @param int optional the number of elems per side in a block (default: 3)
	 * @return int the index of the element in an expanded FEN type string
	 */
	function get_index(i, j, blocks, elems) {
		blocks = parseInt(blocks || 3, 10);
		elems = parseInt(elems || 3, 10);

		var bits = [
				(j % elems), // across within block (across elems)
				(parseInt(Math.floor(j / elems), 10) * blocks * elems), // down within block (down elems)
				((i % blocks) * elems), // across blocks
				(parseInt(Math.floor(i / blocks), 10) * blocks * elems * elems) // down blocks
			];

		// array sum
		bits = bits.reduce(function(a, b) {
			return a + b;
		});

		return bits;
	}


	function create_board(xFEN) {
console.log(xFEN);
		if ( ! xFEN) {
			return false;
		}

		var i,
			j,
			len,
			idx,
			quads = [['A', 'B', 'C', 'D'], ['A','B','E','C','D','F','G','H','I']],
			quad = quads[((2 === GAME.divisor) ? 0 : 1)],
			piece,
			klass = '',
			html = '';

		for (i = 0, len = Math.pow(GAME.divisor, 2); i < len; ++i) {
			html += '<div id="blk_'+ quad[i] +'" class="block">';

			for (j = 0; j < 9; ++j) {
				idx = get_index(i, j, GAME.divisor);
				piece = xFEN.charAt(idx);

				klass = '';
				if ('0' !== piece) {
					klass = ' class="'+ piece.toLowerCase( ) +'"';
				}

				html += '<div'+ klass +' id="s_'+ quad[i] + String.fromCharCode(65 + j) +'"></div>';
			}

			html += '<div class="rotate rotate_right" id="r_'+ quad[i] +'R" style="display:none;cursor:pointer;"></div>';
			html += '<div class="rotate rotate_left" id="r_'+ quad[i] +'L" style="display:none;cursor:pointer;"></div>';

			html += '</div>';
		}

		return html;
	}


	function enable_moves( ) {
		GAME.move_index = parseInt(GAME.move_index, 10) || (GAME.move_count - 1);

		if ( ! GAME.my_turn || GAME.draw_offered || GAME.undo_requested || ('finished' == GAME.state) || ('draw' == GAME.state) || ((GAME.move_count - 1) !== GAME.move_index)) {
			return;
		}

		// make all our pieces clickable
		$('div#board .block div').not('.x, .o, .s, .z')
			.on({
				click: set_square,
				mouseenter: function( ) {
					$(this).addClass('h');
				},
				mouseleave: function( ) {
					$(this).removeClass('h');
				}
			})
			.css('cursor', 'pointer');
	}


	function show_board( ) {
		GAME.move_index = parseInt(GAME.move_index, 10) || (GAME.move_count - 1);

		$('div#board').empty( ).append(create_board(GAME.game_history[GAME.move_index][0]));

		enable_moves( );

		return true;
	}


	function update_history( ) {
		// update our active move history item
		$('#history table td.active').removeClass('active');
		$('td#mv_'+ GAME.move_index).addClass('active');

		// update our disabled review buttons as needed
		$('#history div .disabled').removeClass('disabled');

		if (1 >= GAME.move_index) {
			$('#prev, #prev5, #first').addClass('disabled');
		}

		if (GAME.move_index >= (GAME.move_count - 1)) {
			$('#next, #next5, #last').addClass('disabled');
		}
	}


	function review( ) {
		var type = $(this).attr('id');

		switch (type) {
			case 'first' : GAME.move_index = 1; break;
			case 'prev5' : GAME.move_index -= 5; break;
			case 'prev' : GAME.move_index -= 1; break;
			case 'next' : GAME.move_index += 1; break;
			case 'next5' : GAME.move_index += 5; break;
			case 'last' : GAME.move_index = (GAME.move_count - 1); break;
		}

		if (GAME.move_index < 1) {
			GAME.move_index = 1;
		}
		else if (GAME.move_index > (GAME.move_count - 1)) {
			GAME.move_index = (GAME.move_count - 1);
		}

		update_history( );

		show_board( );

		enable_moves( );

		return true;
	}


// --- EVENTS ---


	// chat box functions
	$('#chatbox form').submit( function( ) {
		if ('' == $.trim($('#chatbox input#chat').val( ))) {
			return false;
		}

		if (debug) {
			window.location = 'ajax_helper.php'+debug_query+'&'+$('#chatbox form').serialize( );
			return false;
		}

		$.ajax({
			type: 'POST',
			url: 'ajax_helper.php',
			data: $('#chatbox form').serialize( ),
			success: function(msg) {
				// if something happened, just reload
				if ('{' != msg[0]) {
					alert('ERROR: AJAX failed');
					if (reload) { window.location.reload( ); }
				}

				var reply = JSON.parse(msg);

				if (reply.error) {
					alert(reply.error);
				}
				else {
					var entry = '<dt><span>'+reply.create_date+'</span> '+reply.username+'</dt>'+
						'<dd'+(('1' == reply.private) ? ' class="private"' : '')+'>'+reply.message+'</dd>';

					$('#chats').prepend(entry);
					$('#chatbox input#chat').val('');
				}
			}
		});

		return false;
	});


	// move history clicks
	$('#history table td[id^=mv_]').click( function( ) {
		GAME.move_index = parseInt($(this).attr('id').slice(3));

		update_history( );

		show_board( );
	}).css('cursor', 'pointer');


	// review button clicks
	$('#history div span:not(.disabled)').on('click', review);


	// ajax form on input button clicks
	$('form input[type=button]').click( function( ) {
		var $this = $(this);
		var confirmed = true;

		switch ($this.prop('name')) {
			case 'nudge' :
				confirmed = confirm('Are you sure you wish to nudge this person?');
				break;

			case 'resign' :
				confirmed = confirm('Are you sure you wish to resign?');
				break;
		}

		if (confirmed) {
			if (debug) {
				window.location = 'ajax_helper.php'+debug_query+'&'+$this.parents('form').serialize( )+'&'+$this.prop('name')+'='+$this.prop('value');
				return false;
			}

			$.ajax({
				type: 'POST',
				url: 'ajax_helper.php',
				data: $this.parents('form').serialize( )+'&'+$this.prop('name')+'='+$this.prop('value'),
				success: function(msg) {
					if ('OK' != msg) {
						alert('ERROR: AJAX failed');
					}

					if (reload) { window.location.reload( ); }
				}
			});
		}
	});


	// tha fancybox stuff
	$('a.fancybox').fancybox({
		autoDimensions : true,
		onStart : function(link) {
			$($(link).attr('href')).show( );
		},
		onCleanup : function( ) {
			$(this.href).hide( );
		}
	});




	// run draw offer alert
	if (GAME.draw_offered && ('watching' !== GAME.state)) {
		alert('Your opponent has offered you a draw.\n\nMake your decision with the draw\nbuttons below the game board.');
	}


	// run undo request alert
	if (GAME.undo_requested && ('watching' !== GAME.state)) {
		alert('Your opponent has requested an undo.\n\nMake your decision with the undo\nbuttons below the game board.');
	}


	// now that it's all set up, do the things
	update_history( );
	show_board( );

}(GAME, jQuery, window));

