-- phpMyAdmin SQL Dump
-- version 4.0.0-dev
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Nov 03, 2012 at 11:23 PM
-- Server version: 5.5.28
-- PHP Version: 5.4.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `games_dev`
--

-- --------------------------------------------------------

--
-- Table structure for table `pa_chat`
--

CREATE TABLE IF NOT EXISTS `pa_chat` (
  `chat_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `message` text COLLATE latin1_general_ci NOT NULL,
  `from_id` int(10) unsigned NOT NULL DEFAULT '0',
  `game_id` int(10) unsigned NOT NULL DEFAULT '0',
  `private` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`chat_id`),
  KEY `game_id` (`game_id`),
  KEY `private` (`private`),
  KEY `from_id` (`from_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `pa_game`
--

CREATE TABLE IF NOT EXISTS `pa_game` (
  `game_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `match_id` int(11) unsigned NOT NULL,
  `winner` varchar(50) COLLATE latin1_general_ci DEFAULT NULL,
  `extra_info` text COLLATE latin1_general_ci,
  `paused` tinyint(1) NOT NULL DEFAULT '0',
  `create_date` datetime DEFAULT '0000-00-00 00:00:00',
  `modify_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`game_id`),
  KEY `match_id` (`match_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `pa_game_history`
--

CREATE TABLE IF NOT EXISTS `pa_game_history` (
  `game_id` int(11) unsigned NOT NULL,
  `move` char(6) COLLATE latin1_general_ci DEFAULT NULL,
  `board` varchar(81) COLLATE latin1_general_ci NOT NULL,
  `move_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `game_id_2` (`game_id`,`move_date`),
  KEY `game_id` (`game_id`),
  KEY `move_date` (`move_date`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pa_game_nudge`
--

CREATE TABLE IF NOT EXISTS `pa_game_nudge` (
  `game_id` int(10) unsigned NOT NULL DEFAULT '0',
  `player_id` int(10) unsigned NOT NULL DEFAULT '0',
  `nudged` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `game_player` (`game_id`,`player_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pa_game_player`
--

CREATE TABLE IF NOT EXISTS `pa_game_player` (
  `game_id` int(11) unsigned NOT NULL,
  `player_id` int(11) unsigned NOT NULL,
  `order_num` tinyint(1) NOT NULL,
  UNIQUE KEY `game_player` (`game_id`,`player_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pa_match`
--

CREATE TABLE IF NOT EXISTS `pa_match` (
  `match_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `password` varchar(255) COLLATE latin1_general_ci DEFAULT NULL,
  `capacity` tinyint(1) unsigned NOT NULL DEFAULT '2',
  `paused` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`match_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `pa_match_player`
--

CREATE TABLE IF NOT EXISTS `pa_match_player` (
  `match_id` int(11) unsigned NOT NULL,
  `player_id` int(11) unsigned DEFAULT NULL,
  `host` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `score` decimal(4,2) DEFAULT NULL,
  UNIQUE KEY `match_player` (`match_id`,`player_id`),
  KEY `host` (`host`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pa_message`
--

CREATE TABLE IF NOT EXISTS `pa_message` (
  `message_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) COLLATE latin1_general_ci NOT NULL DEFAULT '',
  `message` text COLLATE latin1_general_ci NOT NULL,
  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `pa_message_glue`
--

CREATE TABLE IF NOT EXISTS `pa_message_glue` (
  `message_glue_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` int(10) unsigned NOT NULL DEFAULT '0',
  `from_id` int(10) unsigned NOT NULL DEFAULT '0',
  `to_id` int(10) unsigned NOT NULL DEFAULT '0',
  `send_date` datetime DEFAULT NULL,
  `expire_date` datetime DEFAULT NULL,
  `view_date` datetime DEFAULT NULL,
  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`message_glue_id`),
  KEY `outbox` (`from_id`,`message_id`),
  KEY `inbox` (`to_id`,`message_id`),
  KEY `created` (`create_date`),
  KEY `expire_date` (`expire_date`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `pa_pa_player`
--

CREATE TABLE IF NOT EXISTS `pa_pa_player` (
  `player_id` int(11) unsigned NOT NULL DEFAULT '0',
  `is_admin` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `allow_email` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `max_games` tinyint(2) unsigned NOT NULL DEFAULT '0',
  `color` varchar(25) COLLATE latin1_general_ci DEFAULT NULL,
  `wins` smallint(5) unsigned NOT NULL DEFAULT '0',
  `draws` smallint(5) unsigned NOT NULL DEFAULT '0',
  `losses` smallint(5) unsigned NOT NULL DEFAULT '0',
  `last_online` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `id` (`player_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pa_settings`
--

CREATE TABLE IF NOT EXISTS `pa_settings` (
  `setting` varchar(255) COLLATE latin1_general_ci NOT NULL DEFAULT '',
  `value` text COLLATE latin1_general_ci NOT NULL,
  `notes` text COLLATE latin1_general_ci,
  `sort` smallint(5) unsigned NOT NULL DEFAULT '0',
  UNIQUE KEY `setting` (`setting`),
  KEY `sort` (`sort`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

--
-- Dumping data for table `pa_settings`
--

INSERT INTO `pa_settings` (`setting`, `value`, `notes`, `sort`) VALUES
('site_name', 'Your Site Name', 'The name of your site', 10),
('default_color', 'c_blue_black.css', 'The default theme color for the script pages', 20),
('nav_links', '<a href="/">Home</a>', 'HTML code for your site''s navigation links to display on the script pages', 30),
('from_email', 'your.mail@yoursite.com', 'The email address used to send game emails', 40),
('to_email', 'you@yoursite.com', 'The email address to send admin notices to (comma separated)', 50),
('new_users', '1', '(1/0) Allow new users to register (0 = off)', 60),
('approve_users', '0', '(1/0) Require admin approval for new users (0 = off)', 70),
('confirm_email', '0', '(1/0) Require email confirmation for new users (0 = off)', 80),
('max_users', '0', 'Max users allowed to register (0 = off)', 90),
('default_pass', 'change!me', 'The password to use when resetting a user''s password', 100),
('expire_users', '45', 'Number of days until untouched user accounts are deleted (0 = off)', 110),
('save_games', '1', '(1/0) Save games in the ''games'' directory on the server (0 = off)', 120),
('expire_games', '30', 'Number of days until untouched games are deleted (0 = off)', 130),
('expire_finished_games', '7', 'Number of days until finished games are deleted (0 = off)', 140),
('nudge_flood_control', '24', 'Number of hours between nudges. (-1 = no nudging, 0 = no flood control)', 150),
('timezone', 'UTC', 'The timezone to use for dates (<a href="http://www.php.net/manual/en/timezones.php">List of Timezones</a>)', 160),
('long_date', 'M j, Y g:i a', 'The long format for dates (<a href="http://www.php.net/manual/en/function.date.php">Date Format Codes</a>)', 170),
('short_date', 'Y.m.d H:i', 'The short format for dates (<a href="http://www.php.net/manual/en/function.date.php">Date Format Codes</a>)', 180),
('debug_pass', '', 'The DEBUG password to use to set temporary DEBUG status for the script', 190),
('DB_error_log', '1', '(1/0) Log database errors to the ''logs'' directory on the server (0 = off)', 200),
('DB_error_email', '1', '(1/0) Email database errors to the admin email addresses given (0 = off)', 210);

-- --------------------------------------------------------

--
-- Table structure for table `pa_stats`
--

CREATE TABLE IF NOT EXISTS `pa_stats` (
  `player_id` int(10) unsigned NOT NULL,
  `game_id` int(10) unsigned NOT NULL,
  `setup_id` int(10) unsigned NOT NULL,
  `color` enum('white','black') COLLATE latin1_general_ci NOT NULL,
  `win` tinyint(1) NOT NULL DEFAULT '0',
  `move_count` int(10) unsigned NOT NULL DEFAULT '0',
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `hour_count` float(8,3) NOT NULL DEFAULT '0.000',
  UNIQUE KEY `player_id` (`player_id`,`game_id`,`setup_id`),
  KEY `move_count` (`move_count`),
  KEY `hour_count` (`hour_count`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player`
--

CREATE TABLE IF NOT EXISTS `player` (
  `player_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(20) COLLATE latin1_general_ci NOT NULL DEFAULT '',
  `first_name` varchar(20) COLLATE latin1_general_ci DEFAULT NULL,
  `last_name` varchar(20) COLLATE latin1_general_ci DEFAULT NULL,
  `email` varchar(100) COLLATE latin1_general_ci NOT NULL DEFAULT '',
  `timezone` varchar(255) COLLATE latin1_general_ci NOT NULL DEFAULT '',
  `is_admin` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `password` varchar(32) COLLATE latin1_general_ci NOT NULL DEFAULT '',
  `alt_pass` varchar(32) COLLATE latin1_general_ci NOT NULL DEFAULT '',
  `ident` varchar(32) COLLATE latin1_general_ci DEFAULT NULL,
  `token` varchar(32) COLLATE latin1_general_ci DEFAULT NULL,
  `create_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_approved` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`player_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci AUTO_INCREMENT=1 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
