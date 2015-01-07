<?php
/*
+---------------------------------------------------------------------------
|
|   game.class.php (php 5.x)
|
|   by Benjam Welker
|   http://iohelix.net
|
+---------------------------------------------------------------------------
|
|	This module is built to facilitate the game Pentago, it doesn't really
|	care about how to play, or the deep goings on of the game, only about
|	database structure and how to allow players to interact with the game.
|
+---------------------------------------------------------------------------
|
|   > Pentago Game module
|   > Date started: 2008-02-28
|
|   > Module Version Number: 0.8.0
|
+---------------------------------------------------------------------------
*/

// TODO: comments & organize better

if (defined('INCLUDE_DIR')) {
	require_once INCLUDE_DIR.'func.array.php';
}

class Game
{

	/**
	 *		PROPERTIES
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/** const property GAME_TABLE
	 *		Holds the game table name
	 *
	 * @var string
	 */
	const GAME_TABLE = T_GAME;


	/** const property GAME_PLAYER_TABLE
	 *		Holds the game player table name
	 *
	 * @var string
	 */
	const GAME_PLAYER_TABLE = T_GAME_PLAYER;


	/** const property GAME_HISTORY_TABLE
	 *		Holds the game board table name
	 *
	 * @var string
	 */
	const GAME_HISTORY_TABLE = T_GAME_HISTORY;


	/** const property GAME_NUDGE_TABLE
	 *		Holds the game nudge table name
	 *
	 * @var string
	 */
	const GAME_NUDGE_TABLE = T_GAME_NUDGE;


	/** const property GAME_STATS_TABLE
	 *		Holds the game stats table name
	 *
	 * @var string
	 */
	const GAME_STATS_TABLE = T_STATS;


	/** static protected property _EXTRA_INFO_DEFAULTS
	 *		Holds the default extra info data
	 *
	 * @var array
	 */
	static protected $_EXTRA_INFO_DEFAULTS = array(
			'draw_offered' => false,
			'undo_requested' => false,
			'custom_rules' => '',
		);


	/** static protected property COLORS
	 *		Holds the various color codes
	 *		and their english translations
	 *
	 * @var array (index starts at 1)
	 */
	static public $COLORS = array(
			'X' => 'red',
			'O' => 'blue',
			'S' => 'yellow',
			'Z' => 'green',
		);


	/** public property id
	 *		Holds the game's id
	 *
	 * @var int
	 */
	public $id;


	/** public property state
	 *		Holds the game's current state
	 *		can be one of 'Waiting', 'Playing', 'Finished', 'Draw'
	 *
	 * @var string (enum)
	 */
	public $state;


	/** protected property name
	 *		Holds the match's name (csv of player names)
	 *
	 * @var string
	 */
	protected $name;


	/** protected property paused
	 *		Holds the game's current pause state
	 *
	 * @var bool
	 */
	protected $paused;


	/** protected property create_date
	 *		Holds the game's create date
	 *
	 * @var int (unix timestamp)
	 */
	protected $create_date;


	/** protected property modify_date
	 *		Holds the game's modified date
	 *
	 * @var int (unix timestamp)
	 */
	protected $modify_date;


	/** protected property last_move
	 *		Holds the game's last move date
	 *
	 * @var int (unix timestamp)
	 */
	protected $last_move;


	/** protected property capacity
	 *		Holds the game's player capacity
	 *
	 * @var int
	 */
	protected $capacity;


	/** protected property history
	 *		Holds the game move history
	 *
	 * @var array
	 */
	protected $history;


	/** protected property winner
	 *		Holds the game winner
	 *
	 * @var array
	 */
	protected $winner;


	/** protected property watch_mode
	 *		Visiting flag
	 *
	 * @var bool
	 */
	protected $watch_mode = false;


	/** protected property _extra_info
	 *		Holds the extra game info
	 *
	 * @var array
	 */
	protected $_extra_info;


	/** protected property _players
	 *		Holds the player's object references
	 *		along with other game data
	 *
	 * @var array
	 */
	protected $_players;


	/** protected property _match
	 *		Holds the match object reference
	 *
	 * @var Match object reference
	 */
	protected $_match;


	/** protected property _pentago
	 *		Holds the pentago object reference
	 *
	 * @var Pentago object reference
	 */
	protected $_pentago;


	/** protected property _mysql
	 *		Stores a reference to the Mysql class object
	 *
	 * @param Mysql object
	 */
	protected $_mysql;



	/**
	 *		METHODS
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/** public function __construct
	 *		Class constructor
	 *		Sets all outside data
	 *
	 * @param int optional game id
	 * @param Match optional referring object reference
	 * @param Mysql optional object reference
	 * @action instantiates object
	 * @return void
	 */
	public function __construct($id = 0, Match $Match = null, Mysql $Mysql = null)
	{
		call(__METHOD__);

		$this->id = (int) $id;
		call($this->id);

		$this->_pentago = new Pentago($this->id);

		if (is_null($Mysql)) {
			$Mysql = Mysql::get_instance( );
		}

		$this->_mysql = $Mysql;

		try {
			$this->_pull($Match);
		}
		catch (MyException $e) {
			throw $e;
		}
	}


	/** public function __get
	 *		Class getter
	 *		Returns the requested property if the
	 *		requested property is not _private
	 *
	 * @param string property name
	 * @return mixed property value
	 */
	public function __get($property)
	{
		if ( ! property_exists($this, $property)) {
			throw new MyException(__METHOD__.': Trying to access non-existent property ('.$property.')', 2);
		}

		if ('_' === $property[0]) {
			throw new MyException(__METHOD__.': Trying to access _private property ('.$property.')', 2);
		}

		return $this->$property;
	}


	/** public function __set
	 *		Class setter
	 *		Sets the requested property if the
	 *		requested property is not _private
	 *
	 * @param string property name
	 * @param mixed property value
	 * @action optional validation
	 * @return bool success
	 */
	public function __set($property, $value)
	{
		if ( ! property_exists($this, $property)) {
			throw new MyException(__METHOD__.': Trying to access non-existent property ('.$property.')', 3);
		}

		if ('_' === $property[0]) {
			throw new MyException(__METHOD__.': Trying to access _private property ('.$property.')', 3);
		}

		$this->$property = $value;
	}


	/** static public function create_game
	 *		Creates a new Pentago game
	 *
	 * @param int match id
	 * @action creates a new game into the database
	 * @return int new game id
	 */
	static public function create($match_id)
	{
		call(__METHOD__);

		$match_id = (int) $match_id;

		$Mysql = Mysql::get_instance( );

		$query = "
			SELECT *
			FROM `".Match::MATCH_TABLE."`
			WHERE `match_id` = '{$match_id}'
		";
		$match = $Mysql->fetch_assoc($query);;

		if (empty($match)) {
			throw new MyException(__METHOD__.': Trying to start a game with invalid match id');
		}

		// create the game in the database
		$data = array( );
		$data['match_id'] = $match_id;
		$data['create_date '] = 'NOW( )'; // note the trailing space in the field name, this is not a typo
		$data['modify_date'] = null;

		$game_id = (int) $Mysql->insert(self::GAME_TABLE, $data);

		// see if there were any previous games from this match
		$query = "
			SELECT G.*
			FROM ".self::GAME_TABLE." AS G
			WHERE G.game_id = (
					SELECT MAX(game_id)
					FROM ".self::GAME_TABLE."
					WHERE match_id = '{$match_id}'
						AND game_id <> '{$game_id}'
					GROUP BY match_id
				)
		";
		$game_data = $Mysql->fetch_assoc($query);
		call($game_data);

		if ($game_data) {
			// pull the players in the new player order
			$query = "
				SELECT GP.player_id
				FROM ".Match::MATCH_PLAYER_TABLE." AS MP
					INNER JOIN ".Match::MATCH_TABLE." AS M
						USING (match_id)
					LEFT JOIN ".self::GAME_PLAYER_TABLE." AS GP
						USING (player_id)
				WHERE MP.match_id = '{$match_id}'
					AND GP.game_id = '{$game_data['game_id']}'
				ORDER BY GP.order_num = M.capacity DESC
					, GP.order_num ASC
			";
			$players = $Mysql->fetch_value_array($query);
		}
		else {
			// pull the player data
			$query = "
				SELECT MP.player_id
				FROM ".Match::MATCH_PLAYER_TABLE." AS MP
				WHERE MP.match_id = '{$match_id}'
				ORDER BY RAND( )
			";
			$players = $Mysql->fetch_value_array($query);
		}
		call($players);

		$order = 0;
		$data = array( );
		$data['game_id'] = $game_id;
		foreach ($players as $player) {
			$data['player_id'] = $player;
			$data['order_num'] = ++$order;

			$Mysql->insert(self::GAME_PLAYER_TABLE, $data);
		}

		// create an entry in the history table
		$board = str_repeat('.', ((2 < count($players)) ? '81' : '36'));

		$data = array( );
		$data['game_id'] = $game_id;
		$data['board'] = packFEN($board, ((36 < strlen($board)) ? 9 : 6));

		$Mysql->insert(self::GAME_HISTORY_TABLE, $data);

		$that = new Game($game_id);

		// send an email to the first player
		Email::send('turn', reset($players), array('game_id' => $game_id, 'opponent' => $that->get_opponents( )));

		return $game_id;
	}


	/** public function do_move
	 *		Do the given move and send out emails
	 *
	 * @param string move code
	 * @action performs the move
	 * @return array indexes hit
	 */
	public function do_move($move)
	{
		call(__METHOD__);

		$current_player = $this->_pentago->get_current_player( );
		call($current_player);

		try {
			$outcome = $this->_pentago->do_move($move);
			call($outcome);
		}
		catch (MyException $e) {
			throw $e;
		}

		$next_player = $this->_pentago->get_current_player( );
		call($next_player);

		if ($outcome) {
			$winners = array_keys($outcome);
			$this->winner = implode(', ', $winners);
			$this->_extra_info['winners'] = $outcome;

			foreach ($winners as $winner) {
				if (empty($this->_players[$winner]['match']['score'])) {
					$this->_players[$winner]['match']['score'] = 0;
				}

				$this->_players[$winner]['match']['score'] += (1 / count($outcome));
			}

			foreach ($this->_players as $key => $player) {
				if ( ! is_numeric($key)) {
					continue;
				}

				if (in_array($player['player_id'], $winners)) {
					if (1 === count($winners)) {
						$player['object']->add_win( );

						if ((int) $player['player_id'] !== $current_player) {
							Email::send('won', $player['player_id'], array('opponent' => $this->get_opponents( )));
						}
					}
					else {
						$player['object']->add_draw( );

						if ((int) $player['player_id'] !== $current_player) {
							Email::send('draw', $player['player_id'], array('opponent' => $this->get_opponents( )));
						}
					}
				}
				else {
					$player['object']->add_loss( );

					if ((int) $player['player_id'] !== $current_player) {
						Email::send('defeated', $player['player_id'], array('opponent' => $this->_players[$current_player]['object']->username));
					}
				}
			}
		}
		else {
			// send the email
			Email::send('turn', $next_player, array('opponent' => $this->get_opponents( ), 'game_id' => $this->id));
		}

		$this->save( );

		if ($outcome) {
			$this->_match->next_game( );
		}

		return $outcome;
	}


	/** public function resign
	 *		Resigns the given player from the game
	 *
	 * @param int player id
	 * @return void
	 */
	public function resign($player_id)
	{
		call(__METHOD__);
// TODO: make this work
return false;

		if ($this->paused) {
			throw new MyException(__METHOD__.': Trying to perform an action on a paused game');
		}

		$player_id = (int) $player_id;

		if (empty($player_id)) {
			throw new MyException(__METHOD__.': Missing required argument');
		}

		if ( ! $this->is_player($player_id)) {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to resign from a game (#'.$this->id.') they are not playing in');
		}

		if (empty($this->_players['player']['player_id']) || ((int) $this->_players['player']['player_id'] !== (int) $player_id)) {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to resign opponent from a game (#'.$this->id.')');
		}

		$this->_players['opponent']['object']->add_win( );
		$this->_players['player']['object']->add_loss( );
		$this->state = 'Finished';
		$this->_pharaoh->winner = 'opponent';

		Email::send('resigned', $this->_players['opponent']['player_id'], array('opponent' => $this->_players['player']['object']->username, 'game_id' => $this->id));

		$this->save( );
	}


	/** public function offer_draw
	 *		Offers a draw to the given player's apponent
	 *
	 * @param int player id
	 * @return void
	 */
	public function offer_draw($player_id)
	{
		call(__METHOD__);

		if ($this->paused) {
			throw new MyException(__METHOD__.': Trying to perform an action on a paused game');
		}

		$player_id = (int) $player_id;

		if (empty($player_id)) {
			throw new MyException(__METHOD__.': Missing required argument');
		}

		if ( ! $this->is_player($player_id)) {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to offer draw in a game (#'.$this->id.') they are not playing in');
		}

		if ($this->_players['player']['player_id'] != $player_id) {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to offer draw for an opponent in game (#'.$this->id.')');
		}

		$this->_extra_info['draw_offered'] = $player_id;

		Email::send('draw_offered', $this->_players['opponent']['player_id'], array('opponent' => $this->_players['player']['object']->username, 'game_id' => $this->id));

		$this->save( );
	}


	/** public function draw_offered
	 *		Returns the state of the game draw for the given player
	 *		or in general if no player given
	 *
	 * @param int [optional] player id
	 * @return bool draw state
	 */
	public function draw_offered($player_id = false)
	{
		call(__METHOD__);

		$player_id = (int) $player_id;

		// if the draw was offered AND player is blank or player is not the one who offered the draw
		if (($this->_extra_info['draw_offered']) && ( ! $player_id || ($player_id != $this->_extra_info['draw_offered']))) {
			return true;
		}

		return false;
	}


	/** public function accept_draw
	 *		Accepts a draw offered to the given player
	 *
	 * @param int player id
	 * @return void
	 */
	public function accept_draw($player_id)
	{
		call(__METHOD__);

		if ($this->paused) {
			throw new MyException(__METHOD__.': Trying to perform an action on a paused game');
		}

		$player_id = (int) $player_id;

		if (empty($player_id)) {
			throw new MyException(__METHOD__.': Missing required argument');
		}

		if ( ! $this->is_player($player_id)) {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to accept draw in a game (#'.$this->id.') they are not playing in');
		}

		if (($this->_players['player']['player_id'] != $player_id) || ($this->_extra_info['draw_offered'] == $player_id)) {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to accept draw for an opponent in game (#'.$this->id.')');
		}

		$this->_players['opponent']['object']->add_draw( );
		$this->_players['player']['object']->add_draw( );
		$this->state = 'Draw';
		$this->_extra_info['draw_offered'] = false;

		Email::send('draw', $this->_players['opponent']['player_id'], array('opponent' => $this->_players['player']['object']->username, 'game_id' => $this->id));

		$this->save( );
	}


	/** public function reject_draw
	 *		Rejects a draw offered to the given player
	 *
	 * @param int player id
	 * @return void
	 */
	public function reject_draw($player_id)
	{
		call(__METHOD__);

		if ($this->paused) {
			throw new MyException(__METHOD__.': Trying to perform an action on a paused game');
		}

		$player_id = (int) $player_id;

		if (empty($player_id)) {
			throw new MyException(__METHOD__.': Missing required argument');
		}

		if ( ! $this->is_player($player_id)) {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to reject draw in a game (#'.$this->id.') they are not playing in');
		}

		if (($this->_players['player']['player_id'] != $player_id) || ($this->_extra_info['draw_offered'] == $player_id)) {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to reject draw for an opponent in game (#'.$this->id.')');
		}

		$this->_extra_info['draw_offered'] = false;

		$this->save( );
	}


	/** public function request_undo
	 *		Requests an undo from the given player's apponent
	 *
	 * @param int player id
	 * @return void
	 */
	public function request_undo($player_id)
	{
		call(__METHOD__);

		if ($this->paused) {
			throw new MyException(__METHOD__.': Trying to perform an action on a paused game');
		}

		$player_id = (int) $player_id;

		if (empty($player_id)) {
			throw new MyException(__METHOD__.': Missing required argument');
		}

		if ( ! $this->is_player($player_id)) {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to request undo in a game (#'.$this->id.') they are not playing in');
		}

		if ($this->_players['player']['player_id'] != $player_id) {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to request undo from an opponent in game (#'.$this->id.')');
		}

		$this->_extra_info['undo_requested'] = $player_id;

		Email::send('undo_requested', $this->_players['opponent']['player_id'], array('opponent' => $this->_players['player']['object']->username, 'game_id' => $this->id));

		$this->save( );
	}


	/** public function undo_requested
	 *		Returns the state of the game undo for the given player
	 *		or in general if no player given
	 *
	 * @param int [optional] player id
	 * @return bool draw state
	 */
	public function undo_requested($player_id = false)
	{
		call(__METHOD__);

		$player_id = (int) $player_id;

		// if the undo was requested AND player is blank or player is not the one who offered the draw
		if (($this->_extra_info['undo_requested']) && ( ! $player_id || ($player_id != $this->_extra_info['undo_requested']))) {
			return true;
		}

		return false;
	}


	/** public function accept_undo
	 *		Accepts an undo requested to the given player
	 *
	 * @param int player id
	 * @return void
	 */
	public function accept_undo($player_id)
	{
		call(__METHOD__);

		if ($this->paused) {
			throw new MyException(__METHOD__.': Trying to perform an action on a paused game');
		}

		$player_id = (int) $player_id;

		if (empty($player_id)) {
			throw new MyException(__METHOD__.': Missing required argument');
		}

		if ( ! $this->is_player($player_id)) {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to accept undo in a game (#'.$this->id.') they are not playing in');
		}

		if (($this->_players['player']['player_id'] != $player_id) || ($this->_extra_info['draw_offered'] == $player_id)) {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to accept undo for an opponent in game (#'.$this->id.')');
		}

		// we need to adjust the database here
		// it's not really possible via the save function
		$this->_mysql->delete(self::GAME_HISTORY_TABLE, "
			WHERE `game_id` = '{$this->id}'
			ORDER BY `move_date` DESC
			LIMIT 1
		");

		// and fix up the game data
		$this->_pull( );
		$this->_extra_info['undo_requested'] = false;

		Email::send('undo_accepted', $this->_players['opponent']['player_id'], array('opponent' => $this->_players['player']['object']->username, 'game_id' => $this->id));

		$this->save( );
	}


	/** public function reject_undo
	 *		Rejects an undo requested to the given player
	 *
	 * @param int player id
	 * @return void
	 */
	public function reject_undo($player_id)
	{
		call(__METHOD__);

		if ($this->paused) {
			throw new MyException(__METHOD__.': Trying to perform an action on a paused game');
		}

		$player_id = (int) $player_id;

		if (empty($player_id)) {
			throw new MyException(__METHOD__.': Missing required argument');
		}

		if ( ! $this->is_player($player_id)) {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to reject undo in a game (#'.$this->id.') they are not playing in');
		}

		if (($this->_players['player']['player_id'] != $player_id) || ($this->_extra_info['draw_offered'] == $player_id)) {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to reject undo for an opponent in game (#'.$this->id.')');
		}

		$this->_extra_info['undo_requested'] = false;

		$this->save( );
	}


	/** public function has_winner
	 *		Tests if the game is over or not
	 *
	 * @param void
	 * @return bool game has winner
	 */
	public function has_winner( )
	{
		call(__METHOD__);

		return ! empty($this->winner);
	}


	/** public function is_player
	 *		Tests if the given player is a player in the game
	 *
	 * @param int player id
	 * @return bool player is in game
	 */
	public function is_player($player_id)
	{
		call(__METHOD__);

		return isset($this->_players[(int) $player_id]);
	}


	/** public function is_turn
	 *		Returns the requested player's turn
	 *
	 * @param bool current player is requested player
	 * @return bool is the requested player's turn
	 */
	public function is_turn($player = true)
	{
		if ('Playing' != $this->state) {
			return false;
		}

		if ($this->_extra_info['draw_offered']) {
			return false;
		}

		$request = (((bool) $player) ? 'player' : 'opponent');
		return ((isset($this->_players[$request]['turn'])) ? (bool) $this->_players[$request]['turn'] : false);
	}


	/** public function nudge
	 *		Nudges the given player to take their turn
	 *
	 * @param void
	 * @return bool success
	 */
	public function nudge( )
	{
		call(__METHOD__);

		if ($this->paused) {
			throw new MyException(__METHOD__.': Trying to perform an action on a paused game');
		}

		if ( ! $this->test_nudge( )) {
			throw new MyException(__METHOD__.': Trying to nudge a person who is not nudgable');
		}

		$sent = Email::send('nudge', $this->_players['opponent']['player_id'], array('opponent' => $this->_players['player']['object']->username, 'game_id' => $this->id));

		if ( ! $sent) {
			throw new MyException(__METHOD__.': Failed to send email');
		}

		$this->_mysql->delete(self::GAME_NUDGE_TABLE, " WHERE game_id = '{$this->id}' ");
		$this->_mysql->insert(self::GAME_NUDGE_TABLE, array('game_id' => $this->id, 'player_id' => $this->_players['opponent']['player_id']));
	}


	/** public function test_nudge
	 *		Tests if the current player can nudge or not
	 *
	 * @param void
	 * @return bool player can be nudged
	 */
	public function test_nudge( )
	{
		call(__METHOD__);

		$player_id = (int) $this->_pentago->get_current_player( );

		if ( ! $this->is_player($player_id) || $this->is_turn( ) || ('Playing' != $this->state) || $this->paused) {
			return false;
		}

		if ( ! $this->_players[$player_id]['object']->allow_email || ('' == $this->_players[$player_id]['object']->email)) {
			return false;
		}

		try {
			$nudge_time = Settings::read('nudge_flood_control');
		}
		catch (MyException $e) {
			return false;
		}

		if (-1 == $nudge_time) {
			return false;
		}
		elseif (0 == $nudge_time) {
			return true;
		}

		// check the nudge status for this game/player
		// 'now' is taken from the DB because it may
		// have a different time from the PHP server
		$query = "
			SELECT NOW( ) AS now
				, G.modify_date AS move_date
				, GN.nudged
				, COUNT(GH.game_id) AS history
			FROM ".self::GAME_TABLE." AS G
				LEFT JOIN ".self::GAME_NUDGE_TABLE." AS GN
					ON (GN.game_id = G.game_id
						AND GN.player_id = '{$player_id}')
				LEFT JOIN ".self::GAME_HISTORY_TABLE." AS GH
					ON (GH.game_id = G.game_id)
			WHERE G.game_id = '{$this->id}'
			GROUP BY G.game_id
			HAVING 0 <> history
		";
		$dates = $this->_mysql->fetch_assoc($query);

		if ( ! $dates) {
			return false;
		}

		// check the dates
		// if the move date is far enough in the past
		//  AND the player has not been nudged
		//   OR the nudge date is far enough in the past
		if ((strtotime($dates['move_date']) <= strtotime('-'.$nudge_time.' hour', strtotime($dates['now'])))
			&& ((empty($dates['nudged']))
				|| (strtotime($dates['nudged']) <= strtotime('-'.$nudge_time.' hour', strtotime($dates['now'])))))
		{
			return true;
		}

		return false;
	}


	/** public function set_player
	 *		Sets who the current player is
	 *
	 * @param int player id
	 * @return void
	 */
	public function set_player($player_id)
	{
		call(__METHOD__);

		$player_id = (int) $player_id;

		if ( ! empty($this->_players[$player_id])) {
			$this->_players['player'] =& $this->_players[$player_id];
		}
		call($this->_players);
	}


	/** public function get_players
	 *		Returns the game players
	 *
	 * @param void
	 * @return array player data
	 */
	public function get_players( )
	{
		call(__METHOD__);

		$players = $this->_players;
		unset($players['player']);

		return $players;
	}


	/** public function get_opponents
	 *		Returns a comma separated list of player names
	 *
	 * @param void
	 * @return string opponent names
	 */
	public function get_opponents( )
	{
		call(__METHOD__);

		$player_id = 0;
		if ( ! empty($this->_players['player']['player_id'])) {
			$player_id = (int) $this->_players['player']['player_id'];
		}

		$opponents = array( );
		foreach ($this->_players as $p_id => $player) {
			if ($player_id === (int) $player['player_id']) {
				continue;
			}

			$opponents[] = $player['object']->username;
		}

		switch (count($opponents)) {
			case 0 :
				return '--ERROR 1--';

			case 1 :
				return $opponents[0];

			case 2 :
				return $opponents[0].' and '.$opponents[1];

			default :
				$last = array_pop($opponents);
				return implode(', ', $opponents).', and '.$last;
		}

		return '--ERROR 2--';
	}


	/** public function get_players_turn
	 *		Tests if it's the given player's turn
	 *
	 * @param int player id
	 * @return bool is player's turn
	 */
	public function get_players_turn($player_id)
	{
		call(__METHOD__);

		$player_id = (int) $player_id;

		return ( ! empty($this->_players[$player_id]['order_num']) && ((int) $this->_players[$player_id]['order_num'] === (int) ((count($this->history) - 1) % $this->capacity) + 1));
	}


	/** public function get_turn
	 *		Returns the name of the player who's turn it is
	 *
	 * @param void
	 * @return string current player's name
	 */
	public function get_turn( )
	{
		$turn = $this->_pentago->players[$this->_pentago->current_player];
		return (( ! empty($turn) && isset($this->_players[$turn]['object'])) ? $this->_players[$turn]['object']->username : false);
	}


	/** public function get_board
	 *		Returns the current board
	 *
	 * @param int optional history index
	 * @param bool optional return expanded FEN
	 * @return string board FEN (or xFEN)
	 */
	public function get_board($index = null, $expanded = false)
	{
		call(__METHOD__);

		if (is_null($index)) {
			$index = count($this->history) - 1;
		}

		$index = (int) $index;
		$expanded = (bool) $expanded;

		if (isset($this->history[$index])) {
			$board = $this->history[$index]['board'];
		}
		else {
			return false;
		}

		if ($expanded) {
			return expandFEN($board);
		}

		return $board;
	}


	/** public function get_history
	 *		Returns the game history
	 *
	 * @param bool optional return as JSON string
	 * @return array or string game history
	 */
	public function get_history($json = false)
	{
		call(__METHOD__);
		call($json);

		$json = (bool) $json;

		if ( ! $json) {
			return $this->history;
		}

		$history = array( );
		foreach (array_reverse($this->history) as $i => $node) {
			$history[] = array(
				expandFEN($node['board']),
				$node['move'],
			);
		}

		return json_encode($history);
	}


	/** public function get_move_history
	 *		Returns the game move history
	 *
	 * @param void
	 * @return array game history
	 */
	public function get_move_history( )
	{
		call(__METHOD__);

		$history = array_reverse($this->history);
		array_shift($history); // remove the empty first move

		$return = array( );
		foreach ($history as $i => $ply) {
			$return[floor($i / $this->capacity)][$i % $this->capacity] = $ply['move'];
		}

		while (isset($i) && (($this->capacity - 1) !== ($i % $this->capacity))) {
			++$i;
			$return[floor($i / $this->capacity)][$i % $this->capacity] = '';
		}

		return $return;
	}


	/** public function get_outcome
	 *		Returns the outcome string and outcome
	 *
	 * @param int id of observing player
	 * @return array (outcome text, outcome string)
	 */
	public function get_outcome($player_id)
	{
		call(__METHOD__);

		$player_id = (int) $player_id;

		if ( ! in_array($this->state, array('Finished', 'Draw'))) {
			return false;
		}

		if ( ! empty($this->winner)) {
			if (1 === count($this->winner)) {
				list($winner,) = each($this->winner);
				if ($player_id === (int) $winner) {
					return array('You Won !!', 'won');
				}
				else {
					return array($this->_players[$winner]['object']->username.' Won', 'lost');
				}
			}
			else {
				$won = false;
				$winners = array_keys($this->winner);
				foreach ($winners as & $winner) { // mind the reference
					if ($player_id === (int) $winner) {
						$won = true;
					}

					$winner = $this->_players[$winner]['object']->username;
				}
				unset($winner); // kill the reference

				return array('Winners: '.implode(', ', $winners), ($won ? 'won' : 'lost'));
			}
		}
	}


	/** protected function _pull
	 *		Pulls all game data from the database
	 *
	 * @param Match optional referring object reference
	 * @action pulls the game data
	 * @return void
	 */
	protected function _pull(Match $Match = null)
	{
		call(__METHOD__);

		if (empty($this->id)) {
			return false;
		}

		$query = "
			SELECT G.*
			FROM ".self::GAME_TABLE." AS G
			WHERE G.game_id = '{$this->id}'
		";
		$result = $this->_mysql->fetch_assoc($query);

		if ((0 != $this->id) && ( ! $result)) {
			throw new MyException(__METHOD__.': Game data not found for game #'.$this->id);
		}

		$this->create_date = strtotime($result['create_date']);
		$this->modify_date = strtotime($result['modify_date']);
		$this->paused = (bool) $result['paused'];
		$this->winner = array_trim($result['winner'], 'int');

		$this->_extra_info = array_merge_plus(self::$_EXTRA_INFO_DEFAULTS, unserialize($result['extra_info']));

		$this->_match = ( ! empty($Match) ? $Match : new Match($result['match_id'], $this));

		try {
			$this->_pull_history( );
			$this->_pull_players( );
		}
		catch (MyException $e) {
			throw $e;
		}

		if (empty($this->winner)) {
			if (empty($this->paused)) {
				if ( ! empty($this->history)) {
					$this->state = 'Playing';
				}
				else {
					$this->state = 'Waiting';
				}
			}
			else {
				$this->state = 'Paused';
			}
		}
		else {
			$this->state = 'Finished';
		}

		$this->_update_pentago( );
	}


	/** protected function _pull_history
	 *		Pulls all move data from the database
	 *
	 * @param void
	 * @action pulls the move data
	 * @return void
	 */
	protected function _pull_history( )
	{
		call(__METHOD__);

		$this->history = array( );
		$this->last_move = $this->create_date;

		$query = "
			SELECT *
			FROM ".self::GAME_HISTORY_TABLE."
			WHERE game_id = '{$this->id}'
			ORDER BY move_date DESC
		";
		$result = $this->_mysql->fetch_array($query);

		if ($result) {
			$this->history = $result;
			if ($this->history[0]) {
				$this->last_move = strtotime($this->history[0]['move_date']);
			}
		}
	}


	/** protected function _pull_players
	 *		Pulls all player data from the database
	 *
	 * @param void
	 * @action pulls the player data
	 * @return void
	 */
	protected function _pull_players( )
	{
		call(__METHOD__);

		$this->_players = array( );

		$query = "
			SELECT *
			FROM ".self::GAME_PLAYER_TABLE."
			WHERE game_id = '{$this->id}'
			ORDER BY order_num ASC
		";
		$result = $this->_mysql->fetch_array($query);

		if ((0 != $this->id) && ( ! $result)) {
			throw new MyException(__METHOD__.': Player data not found for game #'.$this->id);
		}

		$names = array( );
		foreach ($result as $key => $player) {
			$player['code'] = Pentago::$COLORS[$player['order_num']];
			$player['color'] = self::$COLORS[Pentago::$COLORS[$player['order_num']]];
			$player['match'] = $this->_match->get_player($player['player_id']);
			$player['object'] = new GamePlayer($player['player_id']);
			$this->_players[$player['player_id']] = $player;
			$names[] = $player['object']->username;
		}
		call($this->_players);

		foreach ($this->winner as & $winner) { // mind the reference
			if (empty($winner)) {
				continue;
			}

			$winner = $this->_players[$winner]['object']->username;
		}
		unset($winner); // kill the reference

		$this->capacity = count($this->_players);

		$this->name = implode(', ', $names);
		call($this->name);
	}


	/** protected function _update_pentago
	 *		Updates the Pentago object with the current game data
	 *
	 * @param void
	 * @action updates the Pentago object
	 * @return void
	 */
	protected function _update_pentago( )
	{
		call(__METHOD__);

		if (0 == $this->id) {
			// no exception, just quit
			return false;
		}

		// pull the pentago player data
		$this->_pentago->players = $this->_players;

		// grab the current player id
		$this->_pentago->current_player = 1;
		if ($this->history) {
			$this->_pentago->current_player = ((count($this->history) - 1) % $this->capacity) + 1;
		}

		call($this->history);

		$this->_pentago->set_board($this->history[0]['board']);

		$this->winner = $this->_pentago->get_outcome( );
	}


	/** public function save
	 *		Saves all changed data to the database
	 *
	 * @param void
	 * @action saves the game data
	 * @return void
	 */
	public function save( )
	{
		call(__METHOD__);

		// grab the base game data
		$query = "
			SELECT winner
				, modify_date
				, extra_info
			FROM ".self::GAME_TABLE."
			WHERE game_id = '{$this->id}'
		";
		$game = $this->_mysql->fetch_assoc($query);
		call($game);

		$update_modified = false;

		if ( ! $game) {
			throw new MyException(__METHOD__.': Game data not found for game #'.$this->id);
		}

		$this->_log('DATA SAVE: #'.$this->id.' @ '.time( )."\n".' - '.$this->modify_date."\n".' - '.strtotime($game['modify_date']));

		// test the modified date and make sure we still have valid data
		call($this->modify_date);
		call(strtotime($game['modify_date']));
		if ($this->modify_date != strtotime($game['modify_date'])) {
			$this->_log('== FAILED ==');
			throw new MyException(__METHOD__.': Trying to save game (#'.$this->id.') with out of sync data');
		}

		$update_game = false;
		call($game['winner']);
		call($this->winner);
		if ($game['winner'] != $this->winner) {
			$update_game['winner'] = $this->winner;

			try {
				$this->_add_stats( );
			}
			catch (MyException $e) {
				// do nothing, it gets logged
			}
		}

		$diff = array_compare($this->_extra_info, self::$_EXTRA_INFO_DEFAULTS);
		$update_game['extra_info'] = $diff[0];
		ksort($update_game['extra_info']);

		$update_game['extra_info'] = serialize($update_game['extra_info']);

		if ('a:0:{}' == $update_game['extra_info']) {
			$update_game['extra_info'] = null;
		}

		call($game['extra_info']);
		call($update_game['extra_info']);
		if (0 === strcmp($game['extra_info'], $update_game['extra_info'])) {
			unset($update_game['extra_info']);
		}

		if ($update_game) {
			call('UPDATED GAME');
			call($update_game);
			$update_modified = true;
			$this->_mysql->insert(self::GAME_TABLE, $update_game, " WHERE game_id = '{$this->id}' ");
		}

		// grab the current board from the database
		$query = "
			SELECT *
			FROM ".self::GAME_HISTORY_TABLE."
			WHERE game_id = '{$this->id}'
			ORDER BY move_date DESC
			LIMIT 1
		";
		$move = $this->_mysql->fetch_assoc($query);
		$board = $move['board'];
		call($board);

		$new_board = packFEN($this->_pentago->get_board( ), ((2 < count($this->_players)) ? 9 : 6));
		call($new_board);
		$new_move = $this->_pentago->get_move( );
		call($new_move);

		if ($new_board != $board) {
			call('UPDATED BOARD');
			call($new_board);
			call($new_move);
			$update_modified = true;

			$this->_mysql->insert(self::GAME_HISTORY_TABLE, array('board' => $new_board, 'move' => $new_move, 'game_id' => $this->id));
		}

		// grab the current match scores from the database
		$query = "
			SELECT player_id, score
			FROM ".Match::MATCH_PLAYER_TABLE."
			WHERE match_id = '{$this->_match->id}'
		";
		$scores = $this->_mysql->fetch_array($query);
		call($scores);

		foreach ($scores as $score) {
			foreach ($this->_players as $key => $player) {
				if ( ! is_numeric($key)) {
					continue;
				}

				if ((int) $score['player_id'] === (int) $player['player_id']) {
					if ((float) $score['score'] !== (float) $player['match']['score']) {
						$data = array(
							'score' => $player['match']['score'],
						);
						call($data);

						$where = "
							WHERE `match_id` = '{$this->_match->id}'
								AND `player_id` = '{$player['player_id']}'
						";
						call($where);

						$this->_mysql->insert(Match::MATCH_PLAYER_TABLE, $data, $where);

						$update_modified = true;
					}
				}
			}
		}

		// update the game modified date
		if ($update_modified) {
			$this->_mysql->insert(self::GAME_TABLE, array('modify_date' => NULL), " WHERE game_id = '{$this->id}' ");
		}
	}


	/** protected function _add_stats
	 *		Adds data to the stats table
	 *
	 * @param void
	 * @action adds stats to stats table
	 * @return void
	 */
	protected function _add_stats( )
	{
		call(__METHOD__);
		call($this);

// TODO: get the stats table built
// add things like number of wins by red, blue, green, and yellow
// number of draws, losses, etc. for each
return;

		if (is_null($this->winner)) {
			throw new MyException (__METHOD__.': Game (#'.$this->id.') is not finished ('.$this->state.')');
		}

		$count = count($this->history) - 1;

		$start = $end = $this->history[0]['move_date'];
		if ($count) {
			$start = $this->history[1]['move_date'];
			$end = $this->history[$count]['move_date'];
		}

		$start_unix = strtotime($start);
		$end_unix = strtotime($end);
		$hours = round(($end_unix - $start_unix) / (60 * 60), 3);

		$stat = array(
			'game_id' => $this->id,
			'setup_id' => $this->_setup['id'],
			'move_count' => $count,
			'start_date' => $start,
			'end_date' => $end,
			'hour_count' => $hours,
		);

		$white_outcome = $black_outcome = 0;
		if ('Finished' == $this->state) {
			$white_outcome = 1;
			$black_outcome = -1;

			if ('red' == $this->_pharaoh->winner) {
				$white_outcome = -1;
				$black_outcome = 1;
			}
		}

		$white = array_merge($stat, array(
			'player_id' => $this->_players['white']['player_id'],
			'color' => 'white',
			'win' => $white_outcome,
		));
		call($white);
		$this->_mysql->insert(self::GAME_STATS_TABLE, $white);

		$black = array_merge($stat, array(
			'player_id' => $this->_players['black']['player_id'],
			'color' => 'black',
			'win' => $black_outcome,
		));
		call($black);
		$this->_mysql->insert(self::GAME_STATS_TABLE, $black);
	}


	/** protected function _log
	 *		Report messages to a file
	 *
	 * @param string message
	 * @action log messages to file
	 * @return void
	 */
	protected function _log($msg)
	{
		// log the error
		if (false && class_exists('Log')) {
			Log::write($msg, __CLASS__);
		}
	}


	/**
	 *		STATIC METHODS
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/** static public function get_match_id
	 *		Finds the match id for the given game id
	 *
	 * @param int game id
	 * @return int match id
	 */
	static public function get_match_id($game_id)
	{
		$Mysql = Mysql::get_instance( );

		$game_id = (int) $game_id;

		$query = "
			SELECT match_id
			FROM ".Game::GAME_TABLE."
			WHERE game_id = '{$game_id}'
		";
		$match_id = (int) $Mysql->fetch_value($query);

		return $match_id;
	}


	/** static public function get_list
	 *		Returns a list array of all the games in the database
	 *		with games which need the users attention highlighted
	 *
	 *		NOTE: $player_id is required when not pulling all games
	 *		(when $all is false)
	 *
	 * @param int optional player's id
	 * @param bool optional pull all games (vs only given player's games)
	 * @return array game list (or bool false on failure)
	 */
	static public function get_list($player_id = 0, $all = true)
	{
		call(__METHOD__);
		call(func_get_args( ));

		$Mysql = Mysql::get_instance( );

		$player_id = (int) $player_id;
		$all = (bool) $all;

		if ( ! $all && ! $player_id) {
			throw new MyException(__METHOD__.': Player ID required when not pulling all games');
		}

		// check the session for any stored lists
		if ( ! empty($GLOBALS['CACHE']['game_list'][$player_id][(int) $all])) {
			return $GLOBALS['CACHE']['game_list'][$player_id][(int) $all];
		}

		$WHERE = "";
		if ( ! $all) {
			$query = "
				SELECT DISTINCT GP.game_id
				FROM ".self::GAME_PLAYER_TABLE." AS GP
				WHERE GP.player_id = '{$player_id}'
			";
			$game_ids = $Mysql->fetch_value_array($query);

			$game_ids[] = 0; // don't break the IN clause
			$WHERE = " WHERE G.game_id IN (".implode(',', $game_ids).") ";
		}

		$query = "
			SELECT G.*
				, G.winner IS NOT NULL AS finished
				, 0 AS in_game
				, 0 AS my_turn
				, IF((0 = MAX(GH.move_date)) OR MAX(GH.move_date) IS NULL, G.create_date, MAX(GH.move_date)) AS last_move
				, (COUNT(GH.game_id) - 1) AS moves
			FROM ".self::GAME_TABLE." AS G
				LEFT JOIN ".self::GAME_HISTORY_TABLE." AS GH
					USING (game_id)
				LEFT JOIN ".Match::MATCH_TABLE." AS M
					USING (match_id)
			{$WHERE}
			GROUP BY G.game_id
			ORDER BY finished ASC
				, last_move DESC
		";
		$list = $Mysql->fetch_array($query);

		if ($list) {
			foreach ($list as & $game) { // mind the reference
				// grab all the players in the game
				$query = "
					SELECT P.*
						, MP.*
						, P.player_id AS player_id
					FROM ".self::GAME_PLAYER_TABLE." AS GP
						LEFT JOIN ".Player::PLAYER_TABLE." AS P
							ON (P.player_id = GP.player_id)
						LEFT JOIN ".self::GAME_TABLE." AS G
							ON (G.game_id = GP.game_id)
						LEFT JOIN ".Match::MATCH_PLAYER_TABLE." AS MP
							ON (MP.match_id = G.match_id
								AND MP.player_id = GP.player_id)
					WHERE GP.game_id = '{$game['game_id']}'
					GROUP BY GP.order_num
				";
				$players = $Mysql->fetch_array($query);

				$game_players = array( );
				$keyed_players = array( );
				if ($players) {
					foreach ($players as $player) {
						$keyed_players[$player['player_id']] = $player;

						$player_disp = htmlentities($player['username'].'('.number_format($player['score'], 1).')', ENT_QUOTES, 'ISO-8859-1', false);

						if ($player_id && ($player_id === (int) $player['player_id'])) {
							$game_players[$player['player_id']] = '<span class="highlight">'.$player_disp.'</span>';
							$game['in_game'] = 1;
						}
						else {
							$game_players[$player['player_id']] = $player_disp;
						}
					}
				}

				$game['players'] = implode(', ', $game_players);
				$game['name'] = strip_tags($game['players']);

				// calculate the current player based on how many moves are in the history table
				// and how many players are in the game, unless there is a winner
				if ($game['winner']) {
					$winners = $game['winner'];
					array_trim($winners, 'int');

					$game['turn'] = array( );
					foreach ($winners as $winner) {
						$game['turn'][] = $keyed_players[$winner]['username'];
					}

					$game['turn'] = implode(', ', $game['turn']);
				}
				else {
					$turn_idx = ($game['moves'] % count($players));
					$game['turn'] = $players[$turn_idx]['username'];

					if ($player_id === (int) $players[$turn_idx]['player_id']) {
						$game['my_turn'] = 1;
					}
				}

				$game['state'] = 'Playing';
				if ( ! empty($game['winners'])) {
					$game['state'] = 'Finished';
				}
				elseif ($game['paused']) {
					$game['state'] = 'Paused';
				}
			}
			unset($game); // kill the reference
		}
		call($list);

		// store this in session in case we need to use it later
		$GLOBALS['CACHE']['game_list'][$player_id][(int) $all] = $list;

		return $list;
	}


	/** static public function get_count
	 *		Returns a count of all games in the database,
	 *		as well as the highest game id (the total number of games played)
	 *
	 * @param void
	 * @return array (int current game count, int total game count)
	 */
	static public function get_count( )
	{
		$Mysql = Mysql::get_instance( );

		// games in play
		$query = "
			SELECT COUNT(*)
			FROM ".self::GAME_TABLE."
			WHERE winner IS NULL
		";
		$count = (int) $Mysql->fetch_value($query);

		// total games
		$query = "
			SELECT MAX(game_id)
			FROM ".self::GAME_TABLE."
		";
		$next = (int) $Mysql->fetch_value($query);

		return array($count, $next);
	}


	/** static public function check_turns
	 *		Returns a count of all games
	 *		in which it is the user's turn
	 *
	 * @param int player id
	 * @return int number of games with player action
	 */
	static public function check_turns($player_id)
	{
		$list = self::get_list($player_id, false);
		$turn_count = array_sum_field($list, 'my_turn');
		return $turn_count;
	}


	/** static public function get_my_count
	 *		Returns a count of all given player's games in the database,
	 *		as well as the games in which it is the player's turn
	 *
	 * @param int player id
	 * @return array (int player game count, int turn game count)
	 */
	static public function get_my_count($player_id)
	{
		$Mysql = Mysql::get_instance( );

		$player_id = (int) $player_id;

		$list = self::get_list($player_id, false);

		$mine = $turn = 0;
		foreach ($list as $game) {
			if ($game['in_game']) {
				++$mine;
			}

			if ($game['my_turn']) {
				++$turn;
			}
		}

		return array($mine, $turn);
	}


	/** static public function delete
	 *		Deletes the given game and all related data
	 *
	 * @param mixed array or csv of game IDs
	 * @action deletes the game and all related data from the database
	 * @return void
	 */
	static public function delete($ids)
	{
		$Mysql = Mysql::get_instance( );

		array_trim($ids, 'int');

		$ids[] = 0; // don't break the IN clause

		foreach ($ids as $id) {
			self::write_game_file($id);
		}

		$tables = array(
			self::GAME_PLAYER_TABLE ,
			self::GAME_HISTORY_TABLE ,
			self::GAME_NUDGE_TABLE ,
			self::GAME_TABLE ,
		);

		$Mysql->multi_delete($tables, " WHERE game_id IN (".implode(',', $ids).") ");

		$query = "
			OPTIMIZE TABLE ".self::GAME_TABLE."
				, ".self::GAME_NUDGE_TABLE."
				, ".self::GAME_HISTORY_TABLE."
		";
		$Mysql->query($query);
	}


	/** static public function pause
	 *		Pauses the given games
	 *
	 * @param mixed array or csv of game IDs
	 * @param bool optional pause game (false = unpause)
	 * @action pauses the games
	 * @return void
	 */
	static public function pause($ids, $pause = true)
	{
		$Mysql = Mysql::get_instance( );

		array_trim($ids, 'int');

		$pause = (int) (bool) $pause;

		$ids[] = 0; // don't break the IN clause

		$Mysql->insert(self::GAME_TABLE, array('paused' => $pause), " WHERE game_id IN (".implode(',', $ids).") ");
	}


	/** static public function write_game_file
	 *		Writes the game logs to a file for storage
	 *
	 * @param int game id
	 * @action writes the game data to a file
	 * @return bool success
	 */
	static public function write_game_file($game_id)
	{
// until I get it built
return false;

		$game_id = (int) $game_id;

		if ( ! Settings::read('save_games')) {
			return false;
		}

		if (0 == $game_id) {
			return false;
		}

		try {
			$Game = new Game($game_id);
		}
		catch (MyException $e) {
			return false;
		}

		$players = $Game->get_players( );
		call($players);

		if (empty($players)) {
			return false;
		}

		$logs = $Game->get_history(false);
		call($logs);

		if (empty($logs)) {
			return false;
		}

		// open the file for writing
		$filename = $GLOBALS['GAMES_DIR'].'Pentago_'.$game_id.'_'.date('Ymd', strtotime($game['create_date'])).'.dat';
		$file = fopen($filename, 'wb');

		if (false === $file) {
			return false;
		}

		fwrite($file, "{$game['game_id']} - {$game['name']}\n");
		fwrite($file, date('Y-m-d', strtotime($game['create_date']))."\n");
		fwrite($file, $GLOBALS['_ROOT_URI']."\n");
		fwrite($file, "=================================\n");

		foreach ($players as $player) {
			fwrite($file, "{$players['player_id']} - {$players['username']}\n");
		}

		fwrite($file, "=================================\n");

		$logs = array_reverse($logs);

		foreach ($logs as $log) {
			fwrite($file, $log['data']."\n");
		}

		fwrite($file, "=================================");

		return fclose($file);
	}


} // end of Game class


/*		schemas
// ===================================

--
-- Table structure for table `game`
--

DROP TABLE IF EXISTS `game`;
CREATE TABLE IF NOT EXISTS `game` (
  `game_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `match_id` int(11) unsigned NOT NULL,
  `winner` varchar(50) DEFAULT NULL,
  `extra_info` text COLLATE latin1_general_ci NULL DEFAULT NULL,
  `paused` tinyint(1) NOT NULL DEFAULT '0',
  `create_date` datetime DEFAULT '0000-00-00 00:00:00',
  `modify_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`game_id`),
  KEY `match_id` (`match_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `game_history`
--

DROP TABLE IF EXISTS `game_history`;
CREATE TABLE IF NOT EXISTS `game_history` (
  `game_id` int(11) unsigned NOT NULL,
  `move` char(6) COLLATE latin1_general_ci NULL DEFAULT NULL,
  `board` varchar(81) COLLATE latin1_general_ci NOT NULL,
  `move_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `id` (`game_id`,`move_date`),
  KEY `game_id` (`game_id`),
  KEY `move_date` (`move_date`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `game_player`
--

DROP TABLE IF EXISTS `game_player`;
CREATE TABLE IF NOT EXISTS `game_player` (
  `game_id` int(11) unsigned NOT NULL,
  `player_id` int(11) unsigned NOT NULL,
  `order_num` tinyint(1) NOT NULL,
  UNIQUE KEY `game_player` (`game_id`,`player_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

*/

