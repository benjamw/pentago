<?php

require_once 'includes/inc.global.php';

// remove any previous game sessions
unset($_SESSION['game_id']);

// grab the message and game counts
$message_count = (int) Message::check_new($_SESSION['player_id']);
$turn_count = (int) Game::check_turns($_SESSION['player_id']);
$turn_msg_count = $message_count + $turn_count;

$meta['title'] = 'Game List';
$meta['head_data'] = '
	<script type="text/javascript" src="scripts/jquery.jplayer.min.js"></script>
	<script type="text/javascript" src="scripts/index.js"></script>
	<script type="text/javascript">//<![CDATA[
		var turn_msg_count = '.$turn_msg_count.';
	//]]></script>
';
$meta['foot_data'] = '
	<div id="sounds"></div>
';


// grab the list of games
$list = Game::get_list($_SESSION['player_id']);

$contents = '';

$table_meta = array(
	'sortable' => true ,
	'no_data' => '<p>There are no games to show</p>' ,
	'caption' => 'Current Games' ,
);
$table_format = array(
	array('SPECIAL_HTML', 'true', 'id="g[[[game_id]]]"') ,
	array('SPECIAL_CLASS', '(1 == \'[[[my_turn]]]\')', 'highlight') ,
	array('SPECIAL_CLASS', '( ! my_empty(\'[[[winner]]]\'))', 'lowlight') ,

	array('Match', 'match_id') ,
	array('Game', 'game_id') ,
	array('Turn', '###((\'draw\' == \'[[[turn]]]\') ? \'Draw- [[[winner]]]\' : \'[[[turn]]]\')') ,
	array('Players', 'players') ,
	array('Lg. Board', '###((\'[[[large_board]]]\') ? \'Yes\' : \'No\')') ,
	array('Moves', 'moves') ,
	array('Last Move', '###date(Settings::read(\'long_date\'), strtotime(\'[[[last_move]]]\'))', null, ' class="date"') ,
);
$contents .= '
	<div class="tableholder">
		'.get_table($table_format, $list, $table_meta).'
	</div>';

// create the lobby
$Chat = new Chat($_SESSION['player_id'], 0);
$chat_data = $Chat->get_box_list( );

// temp storage for gravatar imgs
$gravatars = array( );

$lobby = '
	<div id="lobby">
		<div class="caption">Lobby</div>
		<div id="chatbox">
			<form action="'.$_SERVER['REQUEST_URI'].'" method="post"><div>
				<input type="hidden" name="lobby" value="1" />
				<input id="chat" type="text" name="chat" />
			</div></form>';

	if (is_array($chat_data)) {
		$lobby .= '
			<dl id="chats">';

		foreach ($chat_data as $chat) {
			// preserve spaces in the chat text
			$chat['message'] = str_replace("\t", '    ', $chat['message']);
			$chat['message'] = str_replace('  ', ' &nbsp;', $chat['message']);

			if ( ! isset($gravatars[$chat['email']])) {
				$gravatars[$chat['email']] = Gravatar::src($chat['email']);
			}

			$grav_img = '<img src="'.$gravatars[$chat['email']].'" alt="" /> ';

			if ('' == $chat['username']) {
				$chat['username'] = '[deleted]';
			}

			$lobby .= '
				<dt>'.$grav_img.'<span>'.$chat['create_date'].'</span> '.$chat['username'].'</dt>
				<dd>'.htmlentities($chat['message'], ENT_QUOTES, 'ISO-8859-1', false).'</dd>';
		}

		$lobby .= '
			</dl> <!-- #chats -->';
	}

	$lobby .= '
		</div> <!-- #chatbox -->
	</div> <!-- #lobby -->';

$contents .= $lobby;

$hints = array(
	'Select a game from the list and resume play by clicking anywhere on the row.' ,
	'Invite another player to a game by clicking on the Invitations menu item.' ,
	'<span class="highlight">Colored entries</span> indicate that it is your turn.' ,
	'<span class="warning">WARNING!</span><br />Games will be deleted after '.Settings::read('expire_games').' days of inactivity.' ,
	'Finished games will be deleted after '.Settings::read('expire_finished_games').' days.' ,
);

echo get_header($meta);
echo get_item($contents, $hints, $meta['title']);
call($GLOBALS);
echo get_footer($meta);

