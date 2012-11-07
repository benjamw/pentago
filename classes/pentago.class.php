<?php
/*
+---------------------------------------------------------------------------
|
|   pentago.class.php (php 5.x)
|
|   by Benjam Welker
|   http://www.iohelix.net
|
+---------------------------------------------------------------------------
|
|   > Pentago Game module
|   > Date started: 2008-08-18
|
|   > Module Version Number: 0.8.0
|
+---------------------------------------------------------------------------
*/

// TODO: comments & organize better

class Pentago
{

	/**
	 *		PROPERTIES
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */


	/** static protected property COLORS
	 *		Holds the various color codes
	 *
	 * @var array (index starts at 1)
	 */
	static public $COLORS = array( 1 =>
			'X', // 1- white
			'O', // 2- black
			'S', // 3- red
			'Z', // 4- blue
		);


	/** protected property board
	 *		Holds the game board data
	 *		format:
	 *		array(
	 *			array( col1, col2, ... ) , // row 1
	 *			array( ... ) , // row 2
	 *			array( ... ) , // row 3
	 *			...
	 *		)
	 *		i.e. - $board[$y][$x] = $piece_color (or . )
	 *
	 * @var array
	 */
	protected $board;


	/** protected property move
	 *		Holds the latest move
	 *
	 * @var string
	 */
	protected $move;


	/** protected property players
	 *		Holds our player ID data in game order
	 *		array(
	 *			order_num => player_id,
	 *			order_num => player_id,
	 *		);
	 *
	 * @var array of player data
	 */
	protected $players;


	/** protected property current_player
	 *		The current player's position
	 *		(1 = 1st player, 2 = 2nd player, &c.)
	 *
	 * @var int
	 */
	protected $current_player;


	/** protected property center_rotates
	 *		Whether or not the center block can rotate
	 *		in a 4-player game (defaults to true)
	 *
	 * @var bool
	 */
	protected $center_rotates = true;


	/** protected property _color_player
	 *		A reverse look-up array with player's color code
	 *		as key and player's id as value
	 *
	 * @var array
	 */
	protected $_color_player;


	/** protected property _game_id
	 *		The database id for the current game
	 *
	 * @var int
	 */
	protected $_game_id;



	/**
	 *		METHODS
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/** public function __construct
	 *		Class constructor
	 *		Sets all outside data
	 *
	 * @param int optional game id
	 * @action instantiates object
	 * @return void
	 */
	public function __construct($game_id = 0)
	{
		$this->_game_id = (int) $game_id;
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

		switch ($property) {
			case 'players' :
				$this->set_players($value);
				return;
				break;

			case 'board' :
				$this->set_board($value);
				return;
				break;

			case 'center_rotates' :
				$value = (bool) $value;
				break;

			case 'current_player' :
				$value = (int) $value;

				if ($value <= 0) {
					throw new MyException(__METHOD__.': Trying to set '.$property.' to a non-positive value: '.$value);
				}
				elseif ($value > count($this->players)) {
					throw new MyException(__METHOD__.': Trying to set '.$property.': '.$value.', higher than the player count: '.count($this->players));
				}
				break;

			default :
				// do nothing
				break;
		}

		$this->$property = $value;
	}


	/** protected function get_board
	 *		Returns the board in it's current state
	 *		converts the board array to a string
	 *
	 * @param void
	 * @return string board
	 */
	public function get_board( )
	{
		$board = $this->board;

		foreach ($board as & $row) { // mind the reference
			$row = implode('', $row);
		}
		unset($row); // kill the reference

		$board = str_replace('.', '0', implode('', $board));

		return $board;
	}


	/** protected function get_move
	 *		Returns the latest move
	 *
	 * @param void
	 * @return string move
	 */
	public function get_move( )
	{
		return $this->move;
	}


	/** protected function set_players
	 *		Initializes the players array
	 *		with the given data
	 *
	 * @param array player data
	 * @action initializes player info
	 * @return void
	 */
	public function set_players($players)
	{
		if ( ! $players || ! is_array($players)) {
			throw new MyException(__METHOD__.': No player data given');
		}

		$this->players = array( );
		foreach ($players as $player) {
			if ( ! is_array($player)) {
				throw new MyException(__METHOD__.': Incorrect player data given');
			}

			if ( ! empty($player['order_num']) && ! empty($player['player_id'])) {
				$this->players[$player['order_num']] = $player['player_id'];
			}
			else {
				throw new MyException(__METHOD__.': Incorrect player data given');
			}
		}

		$this->_create_color_player( );

		if (empty($this->board)) {
			$this->_init_board( );
		}
	}


	/** protected function set_board
	 *		Initializes the board
	 *		with the given data
	 *
	 * @param string | array board data
	 * @action initializes board
	 * @return void
	 */
	public function set_board($board)
	{
		call(__METHOD__);
		call($board);

		if (is_string($board)) {
			try {
				$FEN = $this->validate_FEN($board);
			}
			catch (MyException $E) {
				throw $e;
			}

			$length = strlen($FEN);

			$size = 6; // 4 square board
			if (81 == $length) {
				$size = 9; // 9 square board
			}

			$this->board = array( );
			for ($i = 0; $i < $length; ++$i) {
				$this->board[(int) floor($i / $size)][$i % $size] = (('0' === $FEN{$i}) ? '.' : $FEN{$i});
			}

			if (empty($this->current_player)) {
				$this->calc_current_player($FEN);
			}
		}
		else {
			// it's easier to validate a string, so convert to FEN and run again
			$FEN = '';
			foreach ($board as $row) {
				$FEN .= implode('', $row);
			}

			// anything not in $COLORS, replace with 0
			$FEN = preg_replace('%[^'.implode('', self::$COLORS).']%i', '0', $FEN);

			$this->set_board($FEN);
		}
	}


	/** protected function calc_current_player
	 *		Guesses the current player
	 *		based on the given board FEN
	 *
	 * @param string board FEN
	 * @action sets current player
	 * @return int current player
	 */
	public function calc_current_player($FEN)
	{
		try {
			$FEN = $this->validate_FEN($FEN);
		}
		catch (MyException $E) {
			throw $e;
		}

		$colors = self::$COLORS;
		if (36 == strlen($FEN)) {
			$colors = array_slice($colors, 0, 2, true);
		}

		$current_player = 1;

		$count = count_chars($FEN, 1);

		$prev_value = 0;
		foreach ($colors as $place => $color) {
			$ord = ord($color);

			if ( ! array_key_exists($ord, $count)) {
				continue;
			}

			$value = $count[$ord];

			if (empty($prev_value)) {
				$prev_value = $value;
			}

			// there are basically two types of players here...
			// those who have taken their turn this round, and those who haven't.
			// as soon as we reach the point where we are inside the group that hasn't,
			// it should be the next player's turn.
			// if we never get there, that means we are right on the edge of a new round
			// therefore, it's the first players turn, as set above.
			if ($value < $prev_value) {
				$current_player = $place;
				break;
			}
		}

		$this->current_player = (int) $current_player;
	}


	/** static protected function validate_FEN
	 *		Validates the given board FEN
	 *
	 * @param string board FEN
	 * @return string expanded clean board FEN
	 */
	static protected function validate_FEN($FEN)
	{
		$xFEN = expandFEN(strtoupper($FEN));

		$length = strlen($xFEN);

		if ( ! in_array($length, array(36, 81))) {
			throw new MyException(__METHOD__.': Incorrect board size given');
		}

		if (36 == $length && preg_match('/[^0XO]+/i', $xFEN)) {
			throw new MyException(__METHOD__.': Invalid board character found');
		}

		if (81 == $length && preg_match('/[^0XOSZ]+/i', $xFEN)) {
			throw new MyException(__METHOD__.': Invalid board character found');
		}

		return $xFEN;
	}


	/** public function do_move
	 *		Performs a full move
	 *
	 * @param string move code
	 * @return array winner ids (empty if none)
	 */
	public function do_move($move)
	{
		call(__METHOD__);

		if ( ! $this->current_player) {
			throw new MyException(__METHOD__.': Current player not set');
		}

		$move = strtoupper($move);

		// notation = <color><place_section><place_spot><rotate_section><rotate_direction>
		if ( ! preg_match('/([XOSZ])([A-J])([\\dA-J])([A-J])([WCLRFB])/', $move, $matches)) {
			throw new MyException(__METHOD__.': Incorrect move notation ('.$move.') encountered');
		}
		call($matches);

		if ($matches[1] !== self::$COLORS[$this->current_player]) {
			throw new MyException(__METHOD__.': Player trying to make a move ('.$move.') when it\'s not their turn ('.self::$COLORS[$this->current_player].')');
		}

		try {
			$this->place_piece($matches[2].$matches[3], $matches[1]);
			$this->rotate_section($matches[4], $matches[5]);

			$this->_next_player( );

			$outcome = $this->get_outcome( );
		}
		catch (MyException $e) {
			throw $e;
		}

		return $outcome;
	}


	/** public function do_moves
	 *		Performs a series of full moves
	 *
	 * @param array of string move codes
	 * @return array winner ids (empty if none)
	 */
	public function do_moves($moves)
	{
		call(__METHOD__);
		call($moves);

		if ( ! $this->board) {
			$this->_init_board( );
		}

		$outcome = array( );

		try {
			foreach ($moves as $move) {
				$this->move = $move;

				$outcome = $this->do_move($move);

				if (count($outcome)) {
					break;
				}
			}
		}
		catch (MyException $e) {
			throw $e;
		}

		return $outcome;
	}


	/** public function place_piece
	 *		Place a piece on the board
	 *
	 * @param string location code
	 * @param string optional piece code
	 * @param bool optional replace existing piece (if any)
	 * @return void
	 */
	public function place_piece($location, $piece = null, $replace = false)
	{
		$location = $this->_location_hum2comp($location);
		$piece = (is_null($piece)) ? self::$COLORS[$this->players[$this->current_player]['order_num']] : $piece;

		if (empty($this->board[$location['y']][$location['x']])) {
			throw new MyException(__METHOD__.': Trying to place a piece somewhere outside the board');
		}
		elseif ( ! $replace && ($this->board[$location['y']][$location['x']] != '.')) {
			throw new MyException(__METHOD__.': Trying to place a piece on an occupied space');
		}

		$this->board[$location['y']][$location['x']] = $piece;
	}


	/** public function rotate_section
	 *		Rotates a section of the board
	 *
	 * @param string section code
	 * @param string optional direction code (if empty, direction MAY be part of the section code)
	 * @return void
	 */
	public function rotate_section($section, $direction = null)
	{
		$size = count($this->board);

		if ((9 == $size) && ! $this->center_rotates && ('D' == strtoupper($section[0]))) {
			throw new MyException(__METHOD__.': Cannot rotate center block in 4 player game');
		}
		elseif ((4 == $size) && ! in_array(strtoupper($section[0]), array('A','B','C','D'))) {
			throw new MyException(__METHOD__.': Trying to rotate a block that does not exist');
		}

		if (is_null($direction)) {
			$direction = $section[1];
		}

		if ( ! isset($direction)) {
			$direction = 'R';
		}

		$add = $this->_location_hum2comp($section[0]);

		$moving_section = array( );
		for ($y = 0; $y < 3; ++$y) {
			for ($x = 0; $x < 3; ++$x) {
				$moving_section[$y][$x] = $this->board[$y + $add['y']][$x + $add['x']];
			}
		}

		/*
			rotate the section
			when rotating clockwise, the new x value is the old y value
			and the new y value is a flipped old x value (0 => 2; 1 => 1, 2 => 0)
			i.e.- new y = abs(2 - old x)  (abs is just to make sure it's positive)
			when rotating the opposite direction, the opposite happens
			the new y is the old x, and the new x is a flipped old y
			crazy matrices...

			examples :

				| 0-0  1-0  2-0 |         | 0-2  0-1  0-0 |
				|               |         |               |
				| 0-1  1-1  2-1 |   CW->  | 1-2  1-1  1-0 |
				|               |  <-CCW  |               |
				| 0-2  1-2  2-2 |         | 2-2  2-1  2-0 |
		*/

		$moved_section = array( );
		if (0 < $this->_direction_hum2comp($direction)) { // (W, F, R, 1)
			for ($y = 0; $y < 3; ++$y) {
				for ($x = 0; $x < 3; ++$x) {
					$moved_section[$y][$x] = $moving_section[abs(2 - $x)][$y];
				}
			}
		}
		else { // 0 > direction (C, B, L, -1)
			for ($y = 0; $y < 3; ++$y) {
				for ($x = 0; $x < 3; ++$x) {
					$moved_section[$y][$x] = $moving_section[$x][abs(2 - $y)];
				}
			}
		}

		// save the section back into the board
		for ($y = 0; $y < 3; ++$y) {
			for ($x = 0; $x < 3; ++$x) {
				$this->board[$y + $add['y']][$x + $add['x']] = $moved_section[$y][$x];
			}
		}
	}


	/** protected function _next_player
	 *		Sets the next player
	 *
	 * @param void
	 * @action sets next player's turn
	 * @return void
	 */
	protected function _next_player( )
	{
		if ($this->current_player === count($this->players)) {
			$this->current_player = 1;
		}
		else {
			$this->current_player += 1;
		}
	}


	/** public function get_outcome
	 *		Searches the board for winners
	 *
	 * @param void
	 * @return array winner ids (empty if none)
	 */
	public function get_outcome( )
	{
		$winners = array( );

		$board_size = (int) count($this->board);
		$test_start = (int) $board_size - 4;

		$used = array('.', '0');

		$moves_avail = false;

		// run through the board looking for five in a row of any color
		// make sure to run through the whole board though, we are still looking
		// for valid moves while checking for winners, do not optimize these loops
		for ($y = 0; $y < $board_size; ++$y) {
			for ($x = 0; $x < $board_size; ++$x) {
				// get the color of the piece we are on
				$piece = $this->board[$y][$x];

				// while we're here, look for available moves
				// (will set to true when one is found)
				$moves_avail = ('.' == $piece) || $moves_avail;

				// skip it if it's not a 'real' piece, or we found this winner already
				if (in_array($piece, $used)) {
					continue;
				}

				// test everything to the right
				if ($x < $test_start) {
					$count = 1; // we already counted the piece we started on
					for ($xx = ($x + 1); $xx < $board_size; ++$xx) {
						if ($piece != $this->board[$y][$xx]) {
							break;
						}

						++$count;

						if ((5 == $count) && ! in_array($piece, $winners)) {
							$winners[] = $this->_color_player[$piece];
							$used[] = $piece;
							break;
						}
					}
				}

				// test everything to the down & right
				if (($x < $test_start) && ($y < $test_start)) {
					$count = 1;
					$xx = ($x + 1);
					$yy = ($y + 1);
					while (($xx < $board_size) && ($yy < $board_size)) {
						if ($piece != $this->board[$yy][$xx]) {
							break;
						}

						++$count;

						if ((5 == $count) && ! in_array($piece, $winners)) {
							$winners[] = $this->_color_player[$piece];
							$used[] = $piece;
							break;
						}

						++$xx;
						++$yy;
					}
				}

				// test everything to the down
				if ($y < $test_start) {
					$count = 1;
					for ($yy = ($y + 1); $yy < $board_size; ++$yy) {
						if ($piece != $this->board[$yy][$x]) {
							break;
						}

						++$count;

						if ((5 == $count) && ! in_array($piece, $winners)) {
							$winners[] = $this->_color_player[$piece];
							$used[] = $piece;
							break;
						}
					}
				}

				// test everything to the down & left
				if (($x >= ($board_size - $test_start)) && ($y < $test_start)) {
					$count = 1;
					$xx = ($x - 1);
					$yy = ($y + 1);
					while (($xx < $board_size) && ($yy < $board_size)) {
						if ($piece != $this->board[$yy][$xx]) {
							break;
						}

						++$count;

						if ((5 == $count) && ! in_array($piece, $winners)) {
							$winners[] = $this->_color_player[$piece];
							$used[] = $piece;
							break;
						}

						--$xx;
						++$yy;
					}
				}
			}
		}

		$winners = array_unique($winners);

		// if the game is over due to completion, with no winners yet...
		// set the winners to all players currently playing (TIE)
		if ( ! $moves_avail && ! $winners) {
			foreach ($used as $piece) {
				if ('.' == $piece) {
					continue;
				}

				$winners[] = $this->_color_player[$piece];
			}
		}

		return $winners;
	}


	/** protected function _init_board
	 *		Initializes an empty board of the proper size
	 *		based on number of players
	 *
	 * @param void
	 * @action initializes the board
	 * @return void
	 */
	protected function _init_board( )
	{
		if ( ! is_array($this->players)) {
			throw new MyException(__METHOD__.': Trying to initialize board with no player data');
		}

		$board_size = (4 == count($this->players)) ? 81 : 36;

		// create an expanded FEN, then run it through the board setter
		// to clean it up and parse it
		$this->set_board(str_repeat('0', $board_size));

		// set the current player
		$this->current_player = 1;
	}


	/** protected function _create_color_player
	 *		Creates a reverse lookup array for piece->player_id
	 *
	 * @param array optional player ids
	 * @action initializes the color player lookup array
	 * @return void
	 */
	protected function _create_color_player($players = null)
	{
		if (empty($players)) {
			$players = $this->players;
		}

		if ( ! $players || ! is_array($players)) {
			throw new MyException(__METHOD__.': No player data given');
		}

		$color_player = array( );
		foreach ($players as $order_num => $player_id) {
			$color_player[self::$COLORS[$order_num]] = $player_id;
		}

		$this->_color_player = $color_player;
	}


	/** protected function _location_hum2comp
	 *		Converts a location code to computer readable data
	 *
	 * @param string 2-char location code (or 1-char section code)
	 * @return array location x and y values (or x and y add values if only section code given)
	 */
	protected function _location_hum2comp($location)
	{
		$first = $location[0];
		$second = (isset($location[1])) ? $location[1] : '';

		/*  Section Map

			 A | B | G
			---+---+---
			 C | D | H
			---+---+---
			 E | F | I/J

			I know it's a bit odd, but I didn't want to
			have to switch the A-D section values based on
			the board size

			To make it even more confusing...
			the spots inside the sections have a more
			intuitive distribution:

			 A | B | C
			---+---+---
			 D | E | F
			---+---+---
			 G | H | I/J

		*/

		switch (strtoupper($first)) {
			case 1 :
			case 'A' :
				$x_add = 0;
				$y_add = 0;
				break;

			case 2 :
			case 'B' :
				$x_add = 3;
				$y_add = 0;
				break;

			case 3 :
			case 'C' :
				$x_add = 0;
				$y_add = 3;
				break;

			case 4 :
			case 'D' :
				$x_add = 3;
				$y_add = 3;
				break;

			case 5 :
			case 'E' :
				$x_add = 6;
				$y_add = 0;
				break;

			case 6 :
			case 'F' :
				$x_add = 6;
				$y_add = 3;
				break;

			case 7 :
			case 'G' :
				$x_add = 0;
				$y_add = 6;
				break;

			case 8 :
			case 'H' :
				$x_add = 3;
				$y_add = 6;
				break;

			case 9 :
			case 'I' :
			case 'J' : // it may skip I, so make J the same
				$x_add = 6;
				$y_add = 6;
				break;

			default :
				throw new MyException(__METHOD__.': Incorrect location code given');
				break;
		}

		// if only one character was given, return the add values
		if ('' == $second) {
			$add = array( );
			$add['x'] = $x_add;
			$add['y'] = $y_add;

			return $add;
		}

		// if the second character is a letter, convert it to a number
		if ( ! preg_match('/\\d/', $second)) {
			$letter = array (
				'A' => 1 , 'B' => 2 , 'C' => 3 ,
				'D' => 4 , 'E' => 5 , 'F' => 6 ,
				'G' => 7 , 'H' => 8 , 'I' => 9 , 'J' => 9 // it may skip I, so make J the same
			);

			$second = (int) $letter[strtoupper($second)];
		}

		$location = array( );
		$location['x'] = (int) ($x_add + (($second - 1) % 3));
		$location['y'] = (int) ($y_add + floor(($second - 1) / 3));

		return $location;
	}


	/** protected function _direction_hum2comp
	 *		Converts a direction code to computer readable data
	 *
	 * @param mixed direction code
	 * @return int 1 for CW, -1 for CCW
	 */
	protected function _direction_hum2comp($direction)
	{
		switch (strtoupper($direction))
		{
			case 'W' : // clockWise
			case 'F' : // Forward
			case 'R' : // Right
			case 1 :
				$value = 1;
				break;

			case 'C' : // Counter-clockwise
			case 'B' : // Backward
			case 'L' : // Left
			case -1 :
			default :
				$value = -1;
				break;
		}

		return $value;
	}


	/** public function print_board
	 *		For debugging output of the board in it's current state
	 *
	 * @param void
	 * @action outputs an ascii board to the browser
	 * @return void
	 */
	public function print_board( )
	{
		$board_size = count($this->board);

		$h_divider = (9 == $board_size) ? "\n----------+---------+----------\n" : "\n----------+----------\n";
		$v_divider = (9 == $board_size) ? "\n          |         |          \n" : "\n          |          \n";

		echo '<pre>';
		for ($y = 0; $y < $board_size; ++$y) {
			if ((3 == $y) || ((9 == $board_size) && (6 == $y))) {
				echo $h_divider;
			}
			else {
				echo $v_divider;
			}

			for ($x = 0; $x < $board_size; ++$x) {
				if ((3 == $x) || ((9 == $board_size) && (6 == $x))) {
					echo ' | ';
				}
				else {
					echo '  ';
				}

				echo $this->board[$y][$x];
			}
		}
		echo $v_divider;
		echo '</pre>';
	}

} // end of Pentago class

