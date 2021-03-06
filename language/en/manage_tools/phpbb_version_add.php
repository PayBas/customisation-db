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
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine
//
// Some characters you may want to copy&paste:
// ’ » “ ” …
//

$lang = array_merge($lang, array(
	'CATEGORY_EXPLAIN'				=> 'Limit the new version support to only the selected categories.',

	'NEW_PHPBB_VERSION'				=> 'New phpBB Version',
	'NEW_PHPBB_VERSION_EXPLAIN'		=> 'New phpBB version to list support on the revision for.',
	'NO_REVISIONS_UPDATED'			=> 'No revisions were updated from the given limitations.',
	'NO_VERSION_SELECTED'			=> 'You must give a proper phpBB version.  Ex: 3.0.7 or 3.0.7-PL1.',

	'PHPBB_VERSION_ADD'				=> 'Add phpBB Version support to revisions',

	'REVISIONS_UPDATED'				=> '%d revisions have been updated.',

	'VERSION_RESTRICTION'			=> 'Version Restriction',
	'VERSION_RESTRICTION_EXPLAIN'	=> 'Limit the new version support to only the selected versions.',
));
