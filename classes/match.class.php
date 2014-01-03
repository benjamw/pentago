<?php
/*
+---------------------------------------------------------------------------
|
|   match.class.php (php 5.x)
|
|   by Benjam Welker
|   http://iohelix.net
|
+---------------------------------------------------------------------------
|
|   > Pentago Match module
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

class Match
{

	/**
	 *		PROPERTIES
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/** const property MATCH_TABLE
	 *		Holds the match table name
	 *
	 * @var string
	 */
	const MATCH_TABLE = T_MATCH;


	/** const property MATCH_PLAYER_TABLE
	 *		Holds the match player glue table name
	 *
	 * @var string
	 */
	const MATCH_PLAYER_TABLE = T_MATCH_PLAYER;


	/** protected property id
	 *		Holds the match's id
	 *
	 * @var int
	 */
	protected $id;


	/** protected property name
	 *		Holds the match's name (csv of player names)
	 *
	 * @var string
	 */
	protected $name;


	/** protected property paused
	 *		Holds the match's current pause state
	 *
	 * @var bool
	 */
	protected $paused;


	/** protected property state
	 *		Holds the match's current game state
	 *
	 * @var string
	 */
	protected $state;


	/** protected property create_date
	 *		Holds the match's create date
	 *
	 * @var int (unix timestamp)
	 */
	protected $create_date;


	/** protected property capacity
	 *		Holds the match's player capacity
	 *
	 * @var int
	 */
	protected $capacity;


	/** protected property password_protected
	 *		Lets us know if the match is password protected or not
	 *
	 * @var bool
	 */
	protected $password_protected;


	/** protected property _players
	 *		Holds our player's object references
	 *		along with other match data
	 *
	 * @var array
	 */
	protected $_players;


	/** protected property _games
	 *		Holds the game object references
	 *
	 * @var array
	 */
	protected $_games;


	/** protected property _mysql
	 *		Stores a reference to the Mysql class object
	 *
	 * @param Mysql object
	 */
	protected $_mysql;


	/** protected property _DEBUG
	 *		Holds the DEBUG state for the class
	 *
	 * @var bool
	 */
	protected $_DEBUG = false;



	/**
	 *		METHODS
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/** public function __construct
	 *		Class constructor
	 *		Sets all outside data
	 *
	 * @param int optional match id
	 * @param Game optional referring Game object reference
	 * @param Mysql optional object reference
	 * @action instantiates object
	 * @return void
	 */
	public function __construct($id = 0, Game $Game = null, Mysql $Mysql = null)
	{
		call(__METHOD__);

		$this->id = (int) $id;
		call($this->id);

		if (is_null($Mysql)) {
			$Mysql = Mysql::get_instance( );
		}

		$this->_mysql = $Mysql;

		if (defined('DEBUG')) {
			$this->_DEBUG = DEBUG;
		}

		try {
			$this->_pull($Game);
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
			throw new MyException(__METHOD__.': Trying to access non-existant property ('.$property.')', 2);
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
			throw new MyException(__METHOD__.': Trying to access non-existant property ('.$property.')', 3);
		}

		if ('_' === $property[0]) {
			throw new MyException(__METHOD__.': Trying to access _private property ('.$property.')', 3);
		}

		$this->$property = $value;
	}


	/** static public function create
	 *		Creates a new Pentago match
	 *
	 * @param void
	 * @action inserts a new match into the database
	 * @return insert id
	 */
	static public function create( )
	{
		call(__METHOD__);
		call($_POST);

		$Mysql = Mysql::get_instance( );

		// create the match
		$required = array( );

		$key_list = array_merge($required, array(
			'password' ,
		));

		try {
			$_DATA = array_clean($_POST, $key_list, $required);
		}
		catch (MyException $e) {
			throw $e;
		}

		// get our capcity based on the number of players invited
		$capacity = 1;
		foreach ($_POST['opponent'] as $opp) {
			if ('C' !== (string) $opp) {
				$capacity += 1;
			}
		}

		$_DATA['capacity'] = $capacity;
		$_DATA['create_date '] = 'NOW( )'; // note the trailing space in the field name, this is not a typo

		$_DATA['large_board'] = $_POST['large_board'];

		if (2 < $capacity) {
			$_DATA['large_board'] = 1;
		}

		if ( ! empty($_POST['password'])) {
			$_DATA['password'] = $this->_hash_pass($_POST['password']);
		}
		else {
			$_DATA['password'] = null;
		}

		$insert_id = $Mysql->insert(self::MATCH_TABLE, $_DATA);

		if (empty($insert_id)) {
			throw new MyException(__METHOD__.': Match could not be created');
		}

		$host_name = Player::get_username($_SESSION['player_id']);

		// now add the host player to the match
		$MP = array(
			'match_id' => $insert_id,
			'player_id' => (int) $_SESSION['player_id'],
			'host' => 1,
			'score' => 0,
		);
		$Mysql->insert(self::MATCH_PLAYER_TABLE, $MP);

		// add any additional players to the match
		foreach ($_POST['opponent'] as $opp) {
			if ('C' == $opp) {
				continue;
			}

			$opp = (int) $opp;
			if ('0' == $opp) {
				$opp = null;
			}

			$MP = array(
				'match_id' => $insert_id,
				'player_id' => $opp,
				'score' => null, // this is how we test for readiness
			);
			$Mysql->insert(self::MATCH_PLAYER_TABLE, $MP);

			// send the invite to the player
			Email::send('invite', $opp, array('opponent' => $host_name));
		}

		return $insert_id;
	}


	/** public function is_player
	 *		Tests if the given player is already in this match or not
	 *
	 * @param int player id
	 * @return bool is player in match
	 */
	public function is_player($player_id)
	{
		call(__METHOD__);

		$player_id = (int) $player_id;

		if (isset($this->_players[$player_id])) {
			return true;
		}

		return false;
	}


	/** static public function resend_invite
	 *		Resends the invite email (if allowed)
	 *
	 * @param int game id
	 * @action resends an invite email
	 * @return bool invite email sent
	 */
	static public function resend_invite($match_id)
	{
		call(__METHOD__);

		$match_id = (int) $match_id;
		$host_id = (int) $_SESSION['player_id'];

		$Mysql = Mysql::get_instance( );

		// grab the invite from the database
		$query = "
			SELECT `M`.*
				, `MPH`.`host` AS `host_id`
				, `MP`.*
				, DATE_ADD(NOW( ), INTERVAL -1 DAY) AS `resend_limit`
			FROM `".self::MATCH_TABLE."` AS `M`
				LEFT JOIN `".self::MATCH_PLAYER_TABLE."` AS `MPH`
					ON (`MPH`.`match_id` = `M`.`match_id`
						AND `MPH`.`host` <> '0')
				LEFT JOIN `".self::MATCH_PLAYER_TABLE."` AS `MP`
					ON (`MP`.`match_id` = `M`.`match_id`)
				LEFT JOIN `".Game::GAME_TABLE."` AS `G`
					ON (`G`.`match_id` = `M`.`match_id`)
			WHERE `M`.`match_id` = '{$match_id}'
				AND `G`.`game_id` IS NULL
		";
		$invites = $Mysql->fetch_array($query);

		if ( ! $invites) {
			throw new MyException(__METHOD__.': Player (#'.$host_id.') trying to resend a non-existant invite (#'.$match_id.')');
		}

		if ((int) $invites[0]['host_id'] !== (int) $host_id) {
			throw new MyException(__METHOD__.': Player (#'.$host_id.') trying to resend an invite (#'.$match_id.') that is not theirs');
		}

		if (strtotime($invites[0]['modify_date']) >= strtotime($invites[0]['resend_limit'])) {
			throw new MyException(__METHOD__.': Player (#'.$host_id.') trying to resend an invite (#'.$match_id.') that is too new');
		}

		// if we get here, all is good...
		$sent = false;
		foreach ($invites as $invite) {
			if ( ! is_null($invite['score'])) {
				continue;
			}

			$sent = $sent || Email::send('invite', $invite['player_id'], array('opponent' => $GLOBALS['_PLAYERS'][$invite['host_id']], 'page' => 'invite.php'));
		}

		if ($sent) {
			// update the modify_date to prevent invite resend flooding
			$_DATA['modify_date '] = 'NOW( )'; // note the trailing space in the field name, this is not a typo
			$Mysql->insert(self::MATCH_TABLE, $_DATA, " WHERE match_id = '{$match_id}' ");
		}

		return $sent;
	}


	/** static public function accept_invite
	 *		Creates the match from invite data
	 *		if all the players are ready to go
	 *		and creates the first game
	 *
	 * @param int match id
	 * @action creates a match
	 * @return int match id
	 */
	static public function accept_invite($match_id, $player_id)
	{
		call(__METHOD__);

		$match_id = (int) $match_id;
		$player_id = (int) $player_id;

		$Mysql = Mysql::get_instance( );

		$query = "
			SELECT M.*
			FROM ".self::MATCH_TABLE." AS M
			WHERE M.match_id = '{$match_id}'
		";
		$match = $Mysql->fetch_assoc($query);

		// grab all the players invited to this match
		$query = "
			SELECT MP.*
			FROM ".self::MATCH_PLAYER_TABLE." AS MP
			WHERE MP.match_id = '{$match_id}'
		";
		$players = $Mysql->fetch_array($query);

		$in_match = $has_open = false;
		foreach ($players as $player) {
			if ($player['player_id'] == $player_id) {
				$in_match = true;
			}

			if (is_null($player['player_id'])) {
				$has_open = true;
			}
		}

		if ($in_match) {
			// set this players score to '0'
			// which is our flag that this
			// player has accepted the invite
			$Mysql->insert(self::MATCH_PLAYER_TABLE, array('score' => '0'), "
				WHERE match_id = '{$match_id}'
					AND player_id = '{$player_id}'
			");
		}
		elseif ($has_open) {
			$data = array(
				'match_id' => $match_id,
				'player_id' => $player_id,
				'score' => 0,
			);

			$WHERE = "";
			if (count($players) == $match['capacity']) {
				$WHERE = "
					WHERE match_id = '{$match_id}'
						AND player_id IS NULL
					LIMIT 1
				";
			}

			$Mysql->insert(self::MATCH_PLAYER_TABLE, $data, $WHERE);
		}
		else {
			throw new MyException(__METHOD__.': Player (#'.$player_id.') trying to join a match (#'.$match_id.') they are not invited to');
		}

		// grab the ready player count
		$query = "
			SELECT MP.player_id
			FROM ".self::MATCH_PLAYER_TABLE." AS MP
			WHERE MP.match_id = '{$match_id}'
				AND MP.player_id IS NOT NULL
				AND score IS NOT NULL
		";
		$players = $Mysql->fetch_value_array($query);

		if (count($players) !== (int) $match['capacity']) {
			call('All players not ready yet');
			return -1;
		}

		// woohoo... start the match

		$game_id = Game::create($match_id);

		// send the emails
		foreach ($players as $player) {
			$opp = array( );

			foreach ($players as $p_id) {
				if ($player == $p_id) {
					continue;
				}

				$opp[] = $GLOBALS['_PLAYERS'][$p_id];
			}
			$opps = implode(', ', $opp);
			$opps = strrev($opps);
			$opps = preg_replace('/ ,/', ' dna ,', $opps, 1);
			$opps = strrev($opps);

			Email::send('start', $player, array('opponent' => $opps));
		}

		return $game_id;
	}


	/** static public function delete_invite
	 *		Deletes the given invite
	 *
	 * @param int game id
	 * @action deletes the invite
	 * @return void
	 */
	static public function delete_invite($match_id, $player_id)
	{
		call(__METHOD__);

		$match_id = (int) $match_id;
		$player_id = (int) $player_id;

		$Mysql = Mysql::get_instance( );

		// check if we are the host, and if we are, just delete the whole thing
		$query = "
			SELECT COUNT(MP.match_id)
			FROM ".self::MATCH_PLAYER_TABLE." AS MP
			WHERE MP.match_id = '{$match_id}'
				AND MP.host = '1'
				AND MP.player_id = '{$player_id}'
		";
		$is_host = (bool) $Mysql->fetch_value($query);

		if ($is_host) {
			return self::delete($match_id);
		}

		// check the capacity and if we are the last one, then delete
		$query = "
			SELECT COUNT(MP.match_id)
			FROM ".self::MATCH_PLAYER_TABLE." AS MP
			WHERE MP.match_id = '{$match_id}'
				AND MP.host <> '1'
				AND MP.player_id <> '{$player_id}'
				AND MP.player_id IS NOT NULL
		";
		$has_players = (bool) $Mysql->fetch_value($query);

		if ( ! $has_players) {
			return self::delete($match_id);
		}

		// we're not the last one, so delete this player's
		// entry in the match and replace it with an 'Open' player

		// make sure there isn't already an 'Open' player
		$query = "
			SELECT COUNT(MP.match_id)
			FROM ".self::MATCH_PLAYER_TABLE." AS MP
			WHERE MP.match_id = '{$match_id}'
				AND MP.player_id IS NULL
		";
		$has_open = (bool) $Mysql->fetch_value($query);

		if ( ! $has_open) {
			$Mysql->insert(self::MATCH_PLAYER_TABLE, array('player_id' => null), "
				WHERE match_id = '{$match_id}'
					AND player_id = '{$player_id}'
			");
		}
		else {
			$Mysql->delete(self::MATCH_PLAYER_TABLE, "
				WHERE match_id = '{$match_id}'
					AND player_id = '{$player_id}'
			");
		}

		return true;
	}


	/** static public function has_invite
	 *		Tests if the given player has the given invite
	 *
	 * @param int game id
	 * @param int player id
	 * @param bool optional player can accept invite
	 * @return bool player has invite
	 */
	static public function has_invite($match_id, $player_id, $accept = false)
	{
		call(__METHOD__);

		$match_id = (int) $match_id;
		$player_id = (int) $player_id;
		$accept = (bool) $accept;

		$Mysql = Mysql::get_instance( );

		$open = "";
		if ($accept) {
			$open = " OR MP.player_id IS NULL ";
		}

		$query = "
			SELECT COUNT(M.match_id)
			FROM ".self::MATCH_TABLE." AS M
				LEFT JOIN ".Game::GAME_TABLE." AS G
					USING (match_id)
				LEFT JOIN ".self::MATCH_PLAYER_TABLE." AS MP
					USING (match_id)
			WHERE G.game_id IS NULL
				AND M.match_id = '{$match_id}'
				AND (MP.player_id = '{$player_id}'
					{$open})
		";
		$has_invite = (bool) $Mysql->fetch_value($query);

		return $has_invite;
	}


	/** public function get_current_game
	 *		Returns the game object for the most recent game
	 *
	 * @param void
	 * @return Game object reference
	 */
	public function get_current_game( )
	{
		call(__METHOD__);

		// grab the most recent game (largest id)
		$game_ids = array_keys($this->_games);

		if ( ! $game_ids) {
			return false;
		}

		rsort($game_ids);
		$game_id = reset($game_ids);

		return $this->_games[$game_id]['object'];
	}


	/** protected function _pull
	 *		Pulls all match data from the database
	 *
	 * @param Game optional referring game object reference
	 * @action pulls the match data
	 * @return void
	 */
	protected function _pull(Game $Game = null)
	{
		call(__METHOD__);

		$query = "
			SELECT M.*
			FROM ".self::MATCH_TABLE." AS M
			WHERE M.match_id = '{$this->id}'
		";
		$result = $this->_mysql->fetch_assoc($query);

		if ((0 != $this->id) && ( ! $result)) {
			throw new MyException(__METHOD__.': Match data not found for match #'.$this->id);
		}

		if ($result) {
			$this->capacity = (int) $result['capacity'];
			$this->create_date = strtotime($result['create_date']);
			$this->password_protected = ( ! is_null($result['password']));
			$this->paused = (bool) $result['paused'];

			try {
				$this->_pull_games($Game);
				$this->_pull_players( );
			}
			catch (MyException $e) {
				throw $e;
			}
		}
	}


	/** protected function _pull_games
	 *		Pulls all game data from the database
	 *
	 * @param Game optional referring game object reference
	 * @action pulls the game data
	 * @return void
	 */
	protected function _pull_games(Game $Game = null)
	{
		call(__METHOD__);

		$this->_games = array( );

		$query = "
			SELECT *
			FROM ".Game::GAME_TABLE."
			WHERE match_id = '{$this->id}'
			ORDER BY create_date
		";
		$games = $this->_mysql->fetch_array($query);
		call($games);

		if ($games) {
			foreach ($games as $game) {
				$this->_games[$game['game_id']] = $game;
				$this->_games[$game['game_id']]['object'] = (
					( ! empty($Game) && ((int) $Game->id === (int) $game['game_id']))
						? $Game
						: new Game($game['game_id'], $this)
				);
			}

			// grab the last one and get the game state
			$last = end($this->_games);
			if ( ! is_null($last['winner'])) {
				$this->state = 'Finished';
			}
			else {
				$this->state = 'Playing';
			}
		}
		else {
			$this->state = 'Waiting';
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
			SELECT player_id
			FROM ".self::MATCH_PLAYER_TABLE."
			WHERE match_id = '{$this->id}'
		";
		$result = $this->_mysql->fetch_array($query);

		if ((0 != $this->id) && ( ! $result)) {
			throw new MyException(__METHOD__.': Player data not found for match #'.$this->id);
		}

		if ($result) {
			$names = array( );
			foreach ($result as $player) {
				$player['object'] = new GamePlayer($player['player_id']);
				$this->_players[$player['player_id']] = $player;
				$names[] = $player['object']->username;
			}

			$this->name = implode(', ', $names);
		}
	}


	/** protected function _hash_pass
	 *		Hashs the match password
	 *
	 * @param string password
	 * @return string salted password hash
	 */
	static protected function _hash_pass($password)
	{
		return md5($password);
	}


	/**
	 *		STATIC METHODS
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */


	/** static public function get_games
	 *		Returns an array of game IDs for the match IDs given
	 *
	 * @param array | int match IDs
	 * @return array game IDs
	 */
	static public function get_games($match_ids)
	{
		call(__METHOD__);

		$Mysql = Mysql::get_instance( );

		array_trim($match_ids, 'int');

		$match_ids[] = 0; // don't break the IN clause

		$query = "
			SELECT game_id
			FROM ".Game::GAME_TABLE."
			WHERE match_id IN (".implode(',', $match_ids).")
		";
		$game_ids = $Mysql->fetch_value_array($query);

		return $game_ids;
	}

	/** static public function get_open_list
	 *		Returns a list array of all open matches in the database
	 *
	 * @param void
	 * @return array open matches
	 */
	static public function get_open_list( )
	{
		$Mysql = Mysql::get_instance( );

		$query = "
			SELECT M.*
				, COUNT(MP.match_id) AS num_players
			FROM ".self::MATCH_TABLE." AS M
				LEFT JOIN ".self::MATCH_PLAYER_TABLE." AS MP
					USING (match_id)
				LEFT JOIN ".Game::GAME_TABLE." AS G
					USING (match_id)
			WHERE G.game_id IS NULL
			GROUP BY MP.match_id
		";
		$list = $Mysql->fetch_array($query);

		// run though the list and add some more data
		if ($list) {
			foreach ($list as $key => $match) {
				// grab all the players in the match
				$query = "
					SELECT P.*
					FROM ".self::MATCH_PLAYER_TABLE." AS MP
						LEFT JOIN ".Player::PLAYER_TABLE." AS P
							USING (player_id)
					WHERE MP.match_id = '{$match['match_id']}'
				";
				$players = $Mysql->fetch_array($query);

				$match['players'] = array( );
				if ($players) {
					foreach ($players as $player) {
						$match['players'][$player['player_id']] = $player['username'];
					}
				}

				$match['name'] = implode(', ', $match['players']);

				$list[$key] = $match;
			}
		}

		return $list;
	}


	/** static public function get_count
	 *		Returns a count of all matches in the database
	 *		as well as the highest match id (the total number of matches played)
	 *
	 * @param void
	 * @return array (int current match count, int total match count)
	 */
	static public function get_count( )
	{
		$Mysql = Mysql::get_instance( );

		$query = "
			SELECT COUNT(*)
			FROM ".self::MATCH_TABLE."
		";
		$count = $Mysql->fetch_value($query);

		$query = "
			SELECT MAX(match_id)
			FROM ".self::MATCH_TABLE."
		";
		$next = $Mysql->fetch_value($query);

		return array($count, $next);
	}


	/** static public function get_invites
	 *		Returns a list array of all the invites in the database
	 *		for the given player
	 *
	 * @param int player's id
	 * @return 2D array invite list
	 */
	static public function get_invites($player_id)
	{
		call(__METHOD__);
		call($player_id);

		$Mysql = Mysql::get_instance( );

		$player_id = (int) $player_id;

		$query = "
			SELECT M.*
				, MP.*
				, DATE_ADD(NOW( ), INTERVAL -1 DAY) AS resend_limit
				, H.player_id AS invitor_id
				, H.username AS invitor
				, P.username
			FROM ".self::MATCH_TABLE." AS M
				LEFT JOIN ".Game::GAME_TABLE." AS G
					USING (match_id)
				LEFT JOIN ".self::MATCH_PLAYER_TABLE." AS MP
					USING (match_id)
				LEFT JOIN ".Player::PLAYER_TABLE." AS P
					ON (P.player_id = MP.player_id)
				LEFT JOIN ".self::MATCH_PLAYER_TABLE." AS HP
					ON (HP.match_id = M.match_id
						AND HP.host = 1)
				LEFT JOIN ".Player::PLAYER_TABLE." AS H
					ON (H.player_id = HP.player_id)
			WHERE G.game_id IS NULL
				AND M.match_id IN (
					SELECT SM.match_id
					FROM ".self::MATCH_TABLE." AS SM
						LEFT JOIN ".self::MATCH_PLAYER_TABLE." AS SMP
							USING (match_id)
					WHERE SMP.player_id = '{$player_id}'
						OR SMP.player_id IS NULL
				)
			ORDER BY M.match_id DESC
		";
		$list = $Mysql->fetch_array($query);

		$in_vites = $out_vites = $open_vites = array( );
		$match_id = 0;
		foreach ($list as $item) {
			if ($item['match_id'] != $match_id) {
				if ( ! empty($new_item)) {
					$opp = array( );
					$accepted = false;
					foreach ($new_item['players'] as $p_id => $score) {
						if ($p_id === (int) $new_item['invitor_id']) {
							continue;
						}

						if (empty($p_id)) {
							$opp[] = '-- Open --';
							continue;
						}

						if (is_null($score)) {
							$opp[] = $GLOBALS['_PLAYERS'][$p_id];
						}
						else {
							$opp[] = '<span class="highlight">'.$GLOBALS['_PLAYERS'][$p_id].'</span>';

							if ($p_id === $player_id) {
								$accepted = true;
							}
						}
					}

					$new_item['opponents'] = implode(', ', $opp);
					$new_item['accepted'] = $accepted;

					${$type}[] = $new_item;
					$new_item = array( );
				}

				$new_item = $item;

				$type = 'open_vites';
				$match_id = $item['match_id'];
			}

			if ($item['host'] && ($item['player_id'] == $player_id)) {
				$type = 'out_vites';
			}

			if ( ! $item['host'] && ($item['player_id'] == $player_id)) {
				$type = 'in_vites';
			}

			$new_item['players'][(int) $item['player_id']] = $item['score'];
		}

		// don't forget the last one
		if ( ! empty($new_item)) {
			$opp = array( );
			$accepted = false;
			foreach ($new_item['players'] as $p_id => $score) {
				if ($p_id === (int) $new_item['invitor_id']) {
					continue;
				}

				if (empty($p_id)) {
					$opp[] = '-- Open --';
					continue;
				}

				if (is_null($score)) {
					$opp[] = $GLOBALS['_PLAYERS'][$p_id];
				}
				else {
					$opp[] = '<span class="highlight">'.$GLOBALS['_PLAYERS'][$p_id].'</span>';

					if ($p_id === $player_id) {
						$accepted = true;
					}
				}
			}

			$new_item['opponents'] = implode(', ', $opp);
			$new_item['accepted'] = $accepted;

			${$type}[] = $new_item;
		}

		return array($in_vites, $out_vites, $open_vites);
	}


	/** static public function get_invite_count
	 *		Returns a count array of all the invites in the database
	 *		for the given player
	 *
	 * @param int player's id
	 * @return 2D array invite count
	 */
	static public function get_invite_count($player_id)
	{
		call(__METHOD__);

		$player_id = (int) $player_id;

		$results = self::get_invites($player_id);

		// only count un-accepted in_vites
		foreach ($results[0] as $idx => $in_vite) {
			if ($in_vite['accepted']) {
				unset($results[0][$idx]);
			}
		}

		return array(count($results[0]), count($results[1]), count($results[2]));
	}


	/** static public function player_deleted
	 *		Deletes the games the given players are in
	 *
	 * @param mixed array or csv of player ids
	 * @action deletes the players games
	 * @return void
	 */
	static public function player_deleted($ids)
	{
		$Mysql = Mysql::get_instance( );

		array_trim($ids, 'int');

		if (empty($ids)) {
			throw new MyException(__METHOD__.': No player ids given');
		}

		$query = "
			SELECT DISTINCT(match_id)
			FROM ".self::MATCH_PLAYER_TABLE."
			WHERE player_id IN (".implode(',', $ids).")
		";
		$game_ids = $Mysql->fetch_value_array($query);

		if ($game_ids) {
			self::delete($game_ids);
		}
	}


	/** static public function pause
	 *		Pauses the given matches
	 *
	 * @param mixed array or csv of match IDs
	 * @param bool optional pause match (false = unpause)
	 * @action pauses the matches
	 * @return void
	 */
	static public function pause($ids, $pause = true)
	{
		$Mysql = Mysql::get_instance( );

		array_trim($ids, 'int');

		$pause = (int) (bool) $pause;

		$ids[] = 0; // don't break the IN clause

		$game_ids = self::get_games($ids);
		Game::pause($game_ids, $pause);

		$Mysql->insert(self::MATCH_TABLE, array('paused' => $pause), " WHERE match_id IN (".implode(',', $ids).") ");
	}


	/** static public function delete
	 *		Deletes the given match and all related data
	 *
	 * @param mixed array or csv of match IDs
	 * @action deletes the match and all related data from the database
	 * @return void
	 */
	static public function delete($ids)
	{
		$Mysql = Mysql::get_instance( );

		array_trim($ids, 'int');

		$ids[] = 0; // don't break the IN clause

		// delete the associated games
		// do these through their own class because it does
		// other clean-up that goes along with deleting a game
		$query = "
			SELECT game_id
			FROM ".Game::GAME_TABLE."
			WHERE match_id IN (".implode(',', $ids).")
		";
		$game_ids = $Mysql->fetch_value_array($query);

		Game::delete($game_ids);

		// delete all the match data
		$tables = array(
			self::MATCH_PLAYER_TABLE ,
			self::MATCH_TABLE ,
		);

		$return = $Mysql->multi_delete($tables, " WHERE match_id IN (".implode(',', $ids).") ");

		$query = "
			OPTIMIZE TABLE ".self::MATCH_TABLE."
				, ".self::MATCH_PLAYER_TABLE."
		";
		$Mysql->query($query);

		return $return;
	}


	/** static public function delete_inactive
	 *		Deletes the inactive matches from the database
	 *
	 * @param int age in days
	 * @return void
	 */
	static public function delete_inactive($age)
	{
		call(__METHOD__);
		call($age);

		$Mysql = Mysql::get_instance( );

		if (0 == $age) {
			return false;
		}

		$age = (int) abs($age);

		// grab all the matches that have moves older
		// than the maximum age
		$query = "
			SELECT M.match_id
				, MAX(GH.move_date) AS last_move
			FROM ".Game::GAME_HISTORY_TABLE." AS GH
				LEFT JOIN ".Game::GAME_TABLE." AS G
					USING (game_id)
				LEFT JOIN ".self::MATCH_TABLE." AS M
					USING (match_id)
			WHERE M.create_date < DATE_SUB(NOW( ), INTERVAL {$age} DAY)
			GROUP BY M.match_id
			ORDER BY last_move
		";
		$match_ids = $Mysql->fetch_value_array($query);
		call($match_ids);

		if ($match_ids) {
			return self::delete($match_ids);
		}

		return true;
	}


	/** static public function delete_finished
	 *		Deletes the finished matches from the database
	 *
	 * @param int age in days
	 * @return void
	 */
	static public function delete_finished($age)
	{
		call(__METHOD__);
		call($age);

		$Mysql = Mysql::get_instance( );

		if (0 == $age) {
			return false;
		}

		$age = (int) abs($age);

// TODO: figure out how to pull only finished matches
// until then...
return false;

		// grab all the matches that are finished
		// and are older than the maximum age
		$query = "
			SELECT M.match_id
				, MAX(MH.move_date) AS last_move
			FROM ".self::MATCH_HISTORY_TABLE." AS MH
				LEFT JOIN ".Game::GAME_TABLE." AS G
					USING (game_id)
				LEFT JOIN ".self::MATCH_TABLE." AS M
					USING (match_id)
			WHERE M.create_date < DATE_SUB(NOW( ), INTERVAL {$age} DAY)
			GROUP BY M.match_id
			ORDER BY last_move
		";
		$match_ids = $Mysql->fetch_value_array($query);
		call($match_ids);

		if ($match_ids) {
			return self::delete($match_ids);
		}

		return true;
	}


} // end of Match class


/*		schemas
// ===================================

--
-- Table structure for table `match`
--

DROP TABLE IF EXISTS `match`;
CREATE TABLE IF NOT EXISTS `match` (
  `match_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `password` varchar(255) COLLATE latin1_general_ci DEFAULT NULL,
  `capacity` tinyint(1) unsigned NOT NULL DEFAULT '2',
  `large_board` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `paused` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `create_date` datetime NOT NULL,
  `modify_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`match_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `match_player`
--

DROP TABLE IF EXISTS `match_player`;
CREATE TABLE IF NOT EXISTS `match_player` (
  `match_id` int(11) unsigned NOT NULL,
  `player_id` int(11) unsigned NULL DEFAULT NULL,
  `host` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `score` decimal(4,2) NULL DEFAULT NULL,
  UNIQUE KEY `match_player` (`match_id`,`player_id`),
  KEY `host` (`host`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

*/

