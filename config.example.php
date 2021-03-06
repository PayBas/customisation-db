<?php
/**
*
* This file is part of the phpBB Customisation Database package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

/**
 * @ignore
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

$config = array(
	/**
	* phpBB versions array
	*
	* @param array(
	*	(release branch) => array(
	*		'latest_revision' => (revision number)
	* 		'allow_uploads' => (allow submission of revisions for this version of phpBB?),
	*	),
	* ),
	*/
	'phpbb_versions' => array(
		'20'	=> array('latest_revision' => '23', 'name' => 'phpBB 2.0.x', 'allow_uploads' => false),
		'30'	=> array('latest_revision' => '12', 'name' => 'phpBB 3.0.x', 'allow_uploads' => true),
		'31'	=> array('latest_revision' => '0-RC1', 'name' => 'phpBB 3.1.x', 'allow_uploads' => true),
	),

	/**
	* Relative path from the server root (generate_board_url(true))
	*
	* @param	string	Path to the phpBB folder
	*/
	'phpbb_script_path' => 'phpBB/',

	/**
	* Relative path from the server root (generate_board_url(true))
	*
	* @param	string	Path to the titania folder
	*/
	'titania_script_path' => 'customisation/',

	/**
	* Prefix of the sql tables.  Not the prefix for the phpBB tables, prefix for the Titania tables only.
	* This MUST NOT be the same as the phpBB prefix!
	*
	* @param	string	$titania_table_prefix	Table prefix
	*/
	'table_prefix' => 'cdb_',

	/**
	* Path to the style demo board you would like styles to be installed on upon validation
	* (there is a checkbox option for styles to be installed on the demo board when approving)
	*
	* @param bool|string false to not use a style demo board, path to the board root
	*/
	'demo_style_path' => array(
		'30'	=> false,
		'31'	=> false,
	),

	/**
	* Full URL to the demo style.  We will perform sprintf(demo_style_full, $style_id), so please write the url properly
	* Example (from phpbb.com) http://www.phpbb.com/styles/demo/3.0/?style_id=%s
	*
	* @param bool|string false to not use a style demo board
	*/
	'demo_style_url' => array(
		'30'	=> false,
		'31'	=> false,
	),

	/**
	* Style Path (titania/style/ *path* /)
	*/
	'style' => 'default',

	/**
	* Team groups (members will get TITANIA_TEAMS_ACCESS)
	*/
	'team_groups' => array(5),

	/**
	* IDs of database forum
	*/
	'forum_mod_database'		=> 0,
	'forum_style_database'		=> 0,
	'forum_converter_database'	=> 0,
	'forum_bbcode_database'		=> 0,
	'forum_extension_database'	=> 0,

	/**
	* IDs of account used for topic/post release in database forum
	*/
	'forum_mod_robot'		=> 0,
	'forum_style_robot'		=> 0,
	'forum_converter_robot' => 0,
	'forum_bbcode_robot'	=> 0,
	'forum_extension_robot'	=> 0,

	/**
	* Show the support/discussion panel in each contribution to the public?
	*/
	'support_in_titania' => true,

	/**
	* If the type of post made is in this array we will increment their postcount as posts are made within titania
	*/
	'increment_postcount'	=> array(TITANIA_SUPPORT),

	/**
	* Note: There are still more configuration settings!
	*
	* This example file does not contain all the configuration settings because there are quite a few more trivial settings most probably will not worry about.
	*
	* To see the additiona settings available, please see includes/core/config.php
	*/
);
