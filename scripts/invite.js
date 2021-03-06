
var reload = true; // do not change this

function check_fieldset_box( ) {
	$('input.fieldset_box').each( function( ) {
		var $this = $(this);
		var id = $this.attr('id').slice(0,-4);

		if ($this.prop('checked')) {
			$('div#'+id).show( );
		}
		else {
			$('div#'+id).hide( );
		}
	});
}

$(document).ready( function( ) {

	check_fieldset_box( );

	// force large board for more than 2 players
	$('#opponent2, #opponent3').on('change', function( ) {
		var $all = $('#opponent2, #opponent3');

		var closed = true;
		$all.each( function( ) {
			closed = closed && ('C' == $(this).val( ));
		});
	});

	// make sure we don't have any duplicate names when we submit the form
	$('#send').on('submit', function(evt) {
		var used = [];

		$('select', this).each( function(idx, elem) {
			var val = $(elem).val( );

			if (('C' == val.toUpperCase( )) || (0 == val)) {
				return; // don't break the loop, just continue
			}

			if (-1 != used.indexOf(val)) {
				alert('You have duplicate players selected.\n\nPlease fix and try again.');
				evt.preventDefault( );
				return false; // this breaks the .each loop
			}

			used.push(val);
		});

		return true;
	});

	// hide the collapsible fieldsets
	$('input.fieldset_box').on('change', function( ) {
		check_fieldset_box( );
	});

	// this runs all the ...vites
	$('div#invites input').click( function( ) {
		var $this = $(this);
		var id = $this.attr('id').split('-');

		if ('accept' == id[0]) { // invites and openvites
			// accept the invite
			if (debug) {
				window.location = 'ajax_helper.php'+debug_query+'&'+'invite=accept&match_id='+id[1];
				return;
			}

			$.ajax({
				type: 'POST',
				url: 'ajax_helper.php',
				data: 'invite=accept&match_id='+id[1],
				success: function (msg) {
					msg = msg.toString( );

					if ('-1' === msg) {
						alert('Invite accepted.\n\nWaiting for other players to accept.');
						if (reload) { window.location.reload( ); }
					}
					else if ('ERROR' == msg.slice(0, 5)) {
						alert(msg);
						if (reload) { window.location.reload( ); }
					}
					else if (/^\d+$/.test(msg)) {
						window.location = 'game.php?id='+msg+debug_query_;
					}
					else {
						alert('UNKNOWN ERROR');
						if (reload) { window.location.reload( ); }
					}
				}
			});
		}
		else if ('resend' == id[0]) { // resends outvites
			// resend the invite
			if (debug) {
				window.location = 'ajax_helper.php'+debug_query+'&'+'invite=resend&match_id='+id[1];
				return;
			}

			$.ajax({
				type: 'POST',
				url: 'ajax_helper.php',
				data: 'invite=resend&match_id='+id[1],
				success: function(msg) {
					alert(msg);
					if ('ERROR' == msg.slice(0, 5)) {
						if (reload) { window.location.reload( ); }
					}
					else {
						// remove the resend button
						$this.remove( );
					}
				}
			});
		}
		else if ('start' == id[0]) { // start incomplete games
			// delete the invite
			if (debug) {
				window.location = 'ajax_helper.php'+debug_query+'&'+'invite=start&match_id='+id[1];
				return;
			}

			$.ajax({
				type: 'POST',
				url: 'ajax_helper.php',
				data: 'invite=start&match_id='+id[1],
				success: function(msg) {
					msg = msg.toString( );

					if ('-1' === msg) {
						alert('You are not the game host.');
						if (reload) { window.location.reload( ); }
					}
					else if ('ERROR' == msg.slice(0, 5)) {
						alert(msg);
						if (reload) { window.location.reload( ); }
					}
					else if (/^\d+$/.test(msg)) {
						window.location = 'game.php?id='+msg+debug_query_;
					}
					else {
						alert('UNKNOWN ERROR');
						if (reload) { window.location.reload( ); }
					}
				}
			});
		}
		else { // invites decline and outvites withdraw
			// delete the invite
			if (debug) {
				window.location = 'ajax_helper.php'+debug_query+'&'+'invite=delete&match_id='+id[1];
				return;
			}

			$.ajax({
				type: 'POST',
				url: 'ajax_helper.php',
				data: 'invite=delete&match_id='+id[1],
				success: function(msg) {
					alert(msg);
					if ('ERROR' == msg.slice(0, 5)) {
						if (reload) { window.location.reload( ); }
					}
					else {
						// remove the parent TR
						$this.parent( ).parent( ).remove( );
					}
				}
			});
		}
	});
});

