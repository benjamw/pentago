<?php

require_once 'includes/inc.global.php';

// this has nothing to do with creating a game
// but I'm running it here to prevent long load
// times on other pages where it would be run more often
GamePlayer::delete_inactive(Settings::read('expire_users'));
Match::delete_inactive(Settings::read('expire_games'));
Match::delete_finished(Settings::read('expire_finished_games'));

if (isset($_POST['invite'])) {
	call($_POST);

	// make sure this user is not full
	if ($GLOBALS['Player']->max_games && ($GLOBALS['Player']->max_games <= $GLOBALS['Player']->current_games)) {
		Flash::store('You have reached your maximum allowed games !', false);
	}

	test_token( );

	try {
		Match::create( );
		Flash::store('Invitation Sent Successfully', true);
	}
	catch (MyException $e) {
		Flash::store('Invitation FAILED !', false);
	}
}

// grab the full list of players
$players_full = GamePlayer::get_list(true);
$invite_players = array_shrink($players_full, 'player_id');
$invite_players = ife($invite_players, array( ), false);

// grab the players who's max game count has been reached
$players_maxed = GamePlayer::get_maxed( );
$players_maxed[] = $_SESSION['player_id'];

// remove the maxed players from the invite list
$players = array_diff($invite_players, $players_maxed);

$opponent_selection = '';
$opponent_selection .= '<option value="0">-- Open --</option>';
foreach ($players_full as $player) {
	if ($_SESSION['player_id'] == $player['player_id']) {
		continue;
	}

	if (in_array($player['player_id'], $players)) {
		$opponent_selection .= '
			<option value="'.$player['player_id'].'">'.$player['username'].'</option>';
	}
}

$meta['title'] = 'Send Game Invitation';
$meta['foot_data'] = '
	<script type="text/javascript" src="scripts/invite.js"></script>
';

$hints = array(
	'Create a match by selecting your desired opponents.' ,
	'Matches are played until one player gets 3 points or more.' ,
	'2 player matches can be played on either the small board or the large board.' ,
	'Matches with more than 2 players must be played on the large board.' ,
	'<span class="highlight">Highlighted</span> opponents have accepted the invitation.' ,
	'<span class="warning">WARNING!</span><br />Matches will be deleted after '.Settings::read('expire_games').' days of inactivity.' ,
);

// make sure this user is not full
$submit_button = '<div><input type="submit" name="invite" value="Send Invitation" /></div>';
$warning = '';
if ($GLOBALS['Player']->max_games && ($GLOBALS['Player']->max_games <= $GLOBALS['Player']->current_games)) {
	$submit_button = $warning = '<p class="warning">You have reached your maximum allowed games, you can not create this game !</p>';
}

$contents = <<< EOF
	<form method="post" action="{$_SERVER['REQUEST_URI']}" id="send"><div class="formdiv">

		<input type="hidden" name="token" value="{$_SESSION['token']}" />
		<input type="hidden" name="player_id" value="{$_SESSION['player_id']}" />
		<input type="hidden" name="large_board" value="0" />

		{$warning}

		<div><label for="opponent1">Opponent 1</label><select id="opponent1" name="opponent[]">{$opponent_selection}</select></div>
		<div><label for="opponent2">Opponent 2</label><select id="opponent2" name="opponent[]"><option value="C">-- Closed --</option>{$opponent_selection}</select></div>
		<div><label for="opponent3">Opponent 3</label><select id="opponent3" name="opponent[]"><option value="C">-- Closed --</option>{$opponent_selection}</select></div>

		<div><label>Use Large Board? <input type="checkbox" name="large_board" id="large_board" value="1" /></label></div>

		{$submit_button}

		<div class="clr"></div>
	</div></form>

EOF;

// create our invitation tables
list($in_vites, $out_vites, $open_vites) = Match::get_invites($_SESSION['player_id']);

$contents .= <<< EOT
	<form method="post" action="{$_SERVER['REQUEST_URI']}"><div class="formdiv" id="invites">
EOT;

$table_meta = array(
	'sortable' => true ,
	'no_data' => '<p>There are no received invites to show</p>' ,
	'caption' => 'Invitations Received' ,
);
$table_format = array(
	array('Invitor', 'invitor') ,
	array('Other Opponents', 'opponents') ,
	array('Capacity', 'capacity') ,
	array('Large Board', '###(((bool) \'[[[large_board]]]\') ? \'Yes\' : \'No\')') ,
	array('Date Sent', '###date(Settings::read(\'long_date\'), strtotime(\'[[[create_date]]]\'))', null, ' class="date"') ,
	array('Action', '###(((bool) \'[[[accepted]]]\') ? \'\' : \'<input type="button" id="accept-[[[match_id]]]" value="Accept" />\').\'<input type="button" id="decline-[[[match_id]]]" value="Decline" />\'', false) ,
	array('ID', 'match_id') ,
);
$contents .= get_table($table_format, $in_vites, $table_meta);

$table_meta = array(
	'sortable' => true ,
	'no_data' => '<p>There are no sent invites to show</p>' ,
	'caption' => 'Invitations Sent' ,
);
$table_format = array(
	array('Other Opponents', 'opponents') ,
	array('Capacity', 'capacity') ,
	array('Large Board', '###(((bool) \'[[[large_board]]]\') ? \'Yes\' : \'No\')') ,
	array('Date Sent', '###date(Settings::read(\'long_date\'), strtotime(\'[[[create_date]]]\'))', null, ' class="date"') ,
	array('Action', '###\'<input type="button" id="withdraw-[[[match_id]]]" value="Withdraw" />\'.((strtotime(\'[[[create_date]]]\') >= strtotime(\'[[[resend_limit]]]\')) ? \'\' : \'<input type="button" id="resend-[[[match_id]]]" value="Resend" />\')', false) ,
	array('ID', 'match_id') ,
);
$contents .= get_table($table_format, $out_vites, $table_meta);

$table_meta = array(
	'sortable' => true ,
	'no_data' => '<p>There are no open invites to show</p>' ,
	'caption' => 'Open Invitations' ,
);
$table_format = array(
	array('Invitor', 'invitor') ,
	array('Other Opponents', 'opponents') ,
	array('Capacity', 'capacity') ,
	array('Large Board', '###(((bool) \'[[[large_board]]]\') ? \'Yes\' : \'No\')') ,
	array('Date Sent', '###date(Settings::read(\'long_date\'), strtotime(\'[[[create_date]]]\'))', null, ' class="date"') ,
	array('Action', '<input type="button" id="accept-[[[match_id]]]" value="Accept" />', false) ,
	array('ID', 'match_id') ,
);
$contents .= get_table($table_format, $open_vites, $table_meta);

$contents .= <<< EOT
	</div></form>
EOT;

echo get_header($meta);
echo get_item($contents, $hints, $meta['title']);
call($GLOBALS);
echo get_footer($meta);

