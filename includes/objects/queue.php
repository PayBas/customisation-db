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
* Class to abstract titania queue
* @package Titania
*/
class titania_queue extends titania_message_object
{
	/**
	 * SQL Table
	 *
	 * @var string
	 */
	protected $sql_table = TITANIA_QUEUE_TABLE;

	/**
	 * SQL identifier field
	 *
	 * @var string
	 */
	protected $sql_id_field = 'queue_id';

	/**
	 * Object type (for message tool)
	 *
	 * @var string
	 */
	protected $object_type = TITANIA_QUEUE;

	/** @var \phpbb\titania\controller\helper */
	protected $controller_helper;

	public function __construct()
	{
		// Configure object properties
		$this->object_config = array_merge($this->object_config, array(
			'queue_id'				=> array('default' => 0),
			'revision_id'			=> array('default' => 0),
			'contrib_id'			=> array('default' => 0),
			'submitter_user_id'		=> array('default' => (int) phpbb::$user->data['user_id']),
			'queue_topic_id'		=> array('default' => 0),
			'queue_allow_repack'	=> array('default' => 1),

			'queue_type'			=> array('default' => 0), // contrib type
			'queue_status'			=> array('default' => TITANIA_QUEUE_HIDE), // Uses either TITANIA_QUEUE_NEW or one of the tags for the queue status from the DB
			'queue_submit_time'		=> array('default' => titania::$time),
			'queue_close_time'		=> array('default' => 0),
			'queue_close_user'		=> array('default' => 0),
			'queue_progress'		=> array('default' => 0), // User_id of whoever marked this as in progress
			'queue_progress_time'	=> array('default' => 0),

			'queue_notes'			=> array('default' => '',	'message_field' => 'message'),
			'queue_notes_bitfield'	=> array('default' => '',	'message_field' => 'message_bitfield'),
			'queue_notes_uid'		=> array('default' => '',	'message_field' => 'message_uid'),
			'queue_notes_options'	=> array('default' => 7,	'message_field' => 'message_options'),

			'validation_notes'			=> array('default' => '',	'message_field' => 'message_validation'),
			'validation_notes_bitfield'	=> array('default' => '',	'message_field' => 'message_validation_bitfield'),
			'validation_notes_uid'		=> array('default' => '',	'message_field' => 'message_validation_uid'),
			'validation_notes_options'	=> array('default' => 7,	'message_field' => 'message_validation_options'),

			'mpv_results'			=> array('default' => ''),
			'mpv_results_bitfield'	=> array('default' => ''),
			'mpv_results_uid'		=> array('default' => ''),
			'automod_results'		=> array('default' => ''),
			
			'allow_author_repack'	=> array('default' => false),
			'queue_tested'			=> array('default' => false),
		));

		// Hooks
		titania::$hook->call_hook_ref(array(__CLASS__, __FUNCTION__), $this);

		$this->controller_helper = phpbb::$container->get('phpbb.titania.controller.helper');
	}

	public function submit($update_first_post = true)
	{
		if (!$this->queue_id)
		{
			titania::add_lang('manage');

			$sql = 'SELECT c.contrib_id, c.contrib_name_clean, c.contrib_name, c.contrib_type, r.revision_version
				FROM ' . TITANIA_CONTRIBS_TABLE . ' c, ' . TITANIA_REVISIONS_TABLE . ' r
				WHERE r.revision_id = ' . (int) $this->revision_id . '
					AND c.contrib_id = r.contrib_id';
			$result = phpbb::$db->sql_query($sql);
			$row = phpbb::$db->sql_fetchrow($result);
			phpbb::$db->sql_freeresult($result);

			if (!$row)
			{
				trigger_error('NO_CONTRIB');
			}

			$this->queue_type = $row['contrib_type'];

			// Submit here first to make sure we have a queue_id for the topic url
			parent::submit();

			// Is there a queue discussion topic?  If not we should create one
			$this->get_queue_discussion_topic();

			$this->update_first_queue_post(phpbb::$user->lang['VALIDATION'] . ' - ' . $row['contrib_name'] . ' - ' . $row['revision_version']);
		}
		else if ($update_first_post)
		{
			$this->update_first_queue_post();
		}

		parent::submit();

		// Hooks
		titania::$hook->call_hook_ref(array(__CLASS__, __FUNCTION__), $this);
	}

	/**
	* Rebuild (or create) the first post in the queue topic
	*/
	public function update_first_queue_post($post_subject = false)
	{
		titania::add_lang('manage');

		if (!$this->queue_topic_id)
		{
			$sql = 'SELECT contrib_type FROM ' . TITANIA_CONTRIBS_TABLE . '
				WHERE contrib_id = ' . $this->contrib_id;
			phpbb::$db->sql_query($sql);
			$contrib_type = phpbb::$db->sql_fetchfield('contrib_type');
			phpbb::$db->sql_freeresult();

			// Create the topic
			$post = new titania_post(TITANIA_QUEUE);
			$post->post_access = TITANIA_ACCESS_TEAMS;
			$post->topic->parent_id = $this->queue_id;
			$post->topic->topic_category = $contrib_type;
			$post->topic->topic_url = serialize(array('id' => $this->queue_id));
		}
		else
		{
			// Load the first post
			$topic = new titania_topic;
			$topic->topic_id = $this->queue_topic_id;
			$topic->load();

			$post = new titania_post($topic->topic_type, $topic, $topic->topic_first_post_id);
			$post->load();
		}

		if ($post_subject)
		{
			$post->post_subject = $post_subject;
		}

		$post->post_user_id = $this->submitter_user_id;
		$post->post_time = $this->queue_submit_time;
		$revision = $this->get_revision();

		// Reset the post text
		$post->post_text = '';

		// Queue Discussion Link
		$queue_topic = $this->get_queue_discussion_topic();
		$post->post_text .= '[url=' . $queue_topic->get_url() . ']' . phpbb::$user->lang['QUEUE_DISCUSSION_TOPIC'] . "[/url]\n\n";

		if ($revision->revision_status == TITANIA_REVISION_ON_HOLD)
		{
			$post->post_text .= '<strong>' . phpbb::$user->lang['REVISION_FOR_NEXT_PHPBB'] . "</strong>\n\n";
		}

		// Put text saying whether repacking is allowed or not
		$post->post_text .= phpbb::$user->lang[(($this->queue_allow_repack) ? 'QUEUE_REPACK_ALLOWED' : 'QUEUE_REPACK_NOT_ALLOWED')] . "\n\n";

		// Add the queue notes
		if ($this->queue_notes)
		{
			$queue_notes = $this->queue_notes;
			titania_decode_message($queue_notes, $this->queue_notes_uid);
			$post->post_text .= '[quote=&quot;' . users_overlord::get_user($this->submitter_user_id, 'username', true) . '&quot;]' . $queue_notes . "[/quote]\n";
		}

		// Add the MPV results
		if ($this->mpv_results)
		{
			$mpv_results = $this->mpv_results;
			titania_decode_message($mpv_results, $this->mpv_results_uid);
			$post->post_text .= '[quote=&quot;' . phpbb::$user->lang['VALIDATION_PV'] . '&quot;]' . $mpv_results . "[/quote]\n";
		}

		// Add the Automod results
		if ($this->automod_results)
		{
			$post->post_text .= '[quote=&quot;' . phpbb::$user->lang['VALIDATION_AUTOMOD'] . '&quot;]' . $this->automod_results . "[/quote]\n";
		}

		// Prevent errors from different configurations
		phpbb::$config['min_post_chars'] = 1;
		phpbb::$config['max_post_chars'] = 0;

		// Hooks
		titania::$hook->call_hook_ref(array(__CLASS__, __FUNCTION__), $post, $this);

		// Store the post
		$post->generate_text_for_storage(true, true, true);
		$post->submit();

		$this->queue_topic_id = $post->topic_id;
	}

	/**
	* Reply to the queue topic with a message
	*
	* @param string $message
	* @param bool $teams_only true to set to access level of teams
	* @return \titania_post Returns post object.
	*/
	public function topic_reply($message, $teams_only = true)
	{
		titania::add_lang('manage');

		$message = (isset(phpbb::$user->lang[$message])) ? phpbb::$user->lang[$message] : $message;

		$post = new titania_post(TITANIA_QUEUE, $this->queue_topic_id);
		$post->__set_array(array(
			'post_subject'		=> 'Re: ' . $post->topic->topic_subject,
			'post_text'			=> $message,
		));

		if ($teams_only)
		{
			$post->post_access = TITANIA_ACCESS_TEAMS;
		}

		$post->parent_contrib_type = $this->queue_type;

		$post->generate_text_for_storage(true, true, true);
		$post->submit();

		return $post;
	}

	/**
	* Reply to the discussion topic with a message
	*
	* @param string $message
	* @param bool $teams_only true to set to access level of teams
	*/
	public function discussion_reply($message, $teams_only = false)
	{
		titania::add_lang('manage');

		$message = (isset(phpbb::$user->lang[$message])) ? phpbb::$user->lang[$message] : $message;

		$topic = $this->get_queue_discussion_topic();

		$post = new titania_post(TITANIA_QUEUE_DISCUSSION, $topic);
		$post->__set_array(array(
			'post_subject'		=> 'Re: ' . $post->topic->topic_subject,
			'post_text'			=> $message,
		));

		if ($teams_only)
		{
			$post->post_access = TITANIA_ACCESS_TEAMS;
		}

		$post->parent_contrib_type = $this->queue_type;

		$post->generate_text_for_storage(true, true, true);
		$post->submit();
	}

	public function delete()
	{
		// Hooks
		titania::$hook->call_hook_ref(array(__CLASS__, __FUNCTION__), $this);

		$post = new titania_post;

		// Remove posts and topic
		$sql = 'SELECT post_id FROM ' . TITANIA_POSTS_TABLE . '
			WHERE topic_id = ' . (int) $this->queue_topic_id;
		$result = phpbb::$db->sql_query($sql);
		while ($row = phpbb::$db->sql_fetchrow($result))
		{
			$post->post_id = $row['post_id'];
			$post->hard_delete();
		}
		phpbb::$db->sql_freeresult($result);

		// Clear the revision queue id from the revisions table
		$sql = 'UPDATE ' . TITANIA_REVISIONS_TABLE . '
			SET revision_queue_id = 0
			WHERE revision_id = ' . $this->revision_id;
		phpbb::$db->sql_query($sql);

		// Assplode
		parent::delete();
	}

	public function move($new_status)
	{
		titania::add_lang('manage');

		$from = titania_tags::get_tag_name($this->queue_status);
		$to = titania_tags::get_tag_name($new_status);

		$this->topic_reply(sprintf(phpbb::$user->lang['QUEUE_REPLY_MOVE'], $from, $to));

		$this->queue_status = (int) $new_status;
		$this->queue_progress = 0;
		$this->queue_progress_time = 0;
		$this->submit(false);

		// Send notifications
		$contrib = contribs_overlord::get_contrib_object($this->contrib_id, true);
		$topic = new titania_topic();
		$topic->load($this->queue_topic_id);
		$path_helper = phpbb::$container->get('path_helper');
		$u_view_queue = $topic->get_url(false, array('tag' => $new_status));

		$vars = array(
			'CONTRIB_NAME'	=> $contrib->contrib_name,
			'CATEGORY_NAME'	=> $to,
			'U_VIEW_QUEUE'	=> $path_helper->strip_url_params($u_view_queue, 'sid'),
		);
		titania_subscriptions::send_notifications(
			TITANIA_QUEUE_TAG,
			$new_status,
			'new_contrib_queue_cat.txt',
			$vars,
			phpbb::$user->data['user_id']
		);

		// Hooks
		titania::$hook->call_hook_ref(array(__CLASS__, __FUNCTION__), $this);
	}

	public function in_progress()
	{
		$this->topic_reply('QUEUE_REPLY_IN_PROGRESS');

		$this->queue_progress = phpbb::$user->data['user_id'];
		$this->queue_progress_time = titania::$time;
		$this->submit(false);

		// Hooks
		titania::$hook->call_hook_ref(array(__CLASS__, __FUNCTION__), $this);
	}

	public function no_progress()
	{
		$this->topic_reply('QUEUE_REPLY_NO_PROGRESS');

		$this->queue_progress = 0;
		$this->queue_progress_time = 0;
		$this->submit(false);

		// Hooks
		titania::$hook->call_hook_ref(array(__CLASS__, __FUNCTION__), $this);
	}

	public function change_tested_mark($mark)
	{
		$this->queue_tested = (bool) $mark;
		$this->submit(false);

		// Hooks
		titania::$hook->call_hook_ref(array(__CLASS__, __FUNCTION__), $this, $mark);
	}

	/**
	* Approve this revision
	*
	* @param mixed $public_notes
	*/
	public function approve($public_notes)
	{
		titania::add_lang(array('manage', 'contributions'));
		$revision = $this->get_revision();
		$contrib = new titania_contribution;
		if (!$contrib->load($this->contrib_id) || !$contrib->is_visible())
		{
			return false;
		}
		$revision->contrib = $contrib;
		$revision->load_phpbb_versions();
		$branch = (int) $revision->phpbb_versions[0]['phpbb_version_branch'];

		$contrib_release_topic_id = $contrib->get_release_topic_id($branch);

		$notes = $this->validation_notes;
		titania_decode_message($notes, $this->validation_notes_uid);
		$message = sprintf(phpbb::$user->lang['QUEUE_REPLY_APPROVED'], $revision->revision_version, $notes);

		// Replace empty quotes if there are no notes
		if (!$notes)
		{
			$message = str_replace('[quote][/quote]', '', $message);
		}

		$this->topic_reply($message, false);
		$this->discussion_reply($message);

		// Add support for version that has just been released
		if ($revision->revision_status == TITANIA_REVISION_ON_HOLD)
		{
			$revision->phpbb_versions = array();
			$revision->phpbb_versions[] = array('phpbb_version_branch' => $branch, 'phpbb_version_revision' => titania::$config->prerelease_phpbb_version[$branch]);
		}

		// Update the revisions
		$revision->change_status(TITANIA_REVISION_APPROVED);
		$revision->submit();

		// Reply to the release topic
		if ($contrib_release_topic_id && $contrib->type->update_public)
		{
			// Replying to an already existing topic, use the update message
			$public_notes = sprintf(phpbb::$user->lang[$contrib->type->update_public], $revision->revision_version) . (($public_notes) ? sprintf(phpbb::$user->lang[$contrib->type->update_public . '_NOTES'], $public_notes) : '');
			$contrib->reply_release_topic($branch, $public_notes);
		}
		elseif (!$contrib_release_topic_id && $contrib->type->reply_public)
		{
			// Replying to a topic that was just made, use the reply message
			$public_notes = phpbb::$user->lang[$contrib->type->reply_public] . (($public_notes) ? sprintf(phpbb::$user->lang[$contrib->type->reply_public . '_NOTES'], $public_notes) : '');
			$contrib->reply_release_topic($branch, $public_notes);
		}

		// Self-updating
		$this->queue_status = TITANIA_QUEUE_APPROVED;
		$this->queue_close_time = titania::$time;
		$this->queue_close_user = phpbb::$user->data['user_id'];
		$this->submit(false);

		// Send notification message
		$this->send_approve_deny_notification(true);

		// Subscriptions
		$email_vars = array(
			'NAME'		=> $contrib->contrib_name,
			'U_VIEW'	=> $contrib->get_url(),
		);
		titania_subscriptions::send_notifications(TITANIA_CONTRIB, $this->contrib_id, 'subscribe_notify.txt', $email_vars);

		// Hooks
		titania::$hook->call_hook_ref(array(__CLASS__, __FUNCTION__), $this);
	}

	public function close($revision_status)
	{
		// Update the revision
		$revision = $this->get_revision();
		$revision->change_status($revision_status);

		// Self-updating
		$this->queue_status = TITANIA_QUEUE_CLOSED;
		$this->queue_close_time = titania::$time;
		$this->queue_close_user = phpbb::$user->data['user_id'];
		$this->submit(false);

		// Hooks
		titania::$hook->call_hook_ref(array(__CLASS__, __FUNCTION__), $this, $revision_status);
	}

	public function deny()
	{
		// Reply to the queue topic and discussion with the message
		titania::add_lang('manage');
		$revision = $this->get_revision();

		$notes = $this->validation_notes;
		titania_decode_message($notes, $this->validation_notes_uid);
		$message = sprintf(phpbb::$user->lang['QUEUE_REPLY_DENIED'], $revision->revision_version, $notes);

		// Replace empty quotes if there are no notes
		if (!$notes)
		{
			$message = str_replace('[quote][/quote]', '', $message);
		}

		$this->topic_reply($message, false);
		$this->discussion_reply($message);

		// Update the revision
		$revision->change_status(TITANIA_REVISION_DENIED);

		// Self-updating
		$this->queue_status = TITANIA_QUEUE_DENIED;
		$this->queue_close_time = titania::$time;
		$this->queue_close_user = phpbb::$user->data['user_id'];
		$this->submit(false);

		// Send notification message
		$this->send_approve_deny_notification(false);

		// Hooks
		titania::$hook->call_hook_ref(array(__CLASS__, __FUNCTION__), $this);
	}

	/**
	* Send the approve/deny notification
	*/
	private function send_approve_deny_notification($approve = true)
	{
		titania::add_lang('manage');
		phpbb::_include('functions_privmsgs', 'submit_pm');

		// Generate the authors list to send it to
		$authors = array($this->submitter_user_id => 'to');
		$sql = 'SELECT user_id FROM ' . TITANIA_CONTRIB_COAUTHORS_TABLE . '
			WHERE contrib_id = ' . (int) $this->contrib_id . '
				AND active = 1';
		$result = phpbb::$db->sql_query($sql);
		while ($row = phpbb::$db->sql_fetchrow($result))
		{
			$authors[$row['user_id']] = 'to';
		}
		phpbb::$db->sql_freeresult($result);

		// Need some stuff
		$contrib = new titania_contribution();
		$contrib->load((int) $this->contrib_id);
		$revision = new titania_revision($contrib, $this->revision_id);
		$revision->load();

		// Subject
		$subject = sprintf(phpbb::$user->lang[$contrib->type->validation_subject], $contrib->contrib_name, $revision->revision_version);

		// Message
		$notes = $this->validation_notes;
		titania_decode_message($notes, $this->validation_notes_uid);
		if ($approve)
		{
			$message = $contrib->type->validation_message_approve;
		}
		else
		{
			$message = $contrib->type->validation_message_deny;
		}
		$message = sprintf(phpbb::$user->lang[$message], $notes);

		// Replace empty quotes if there are no notes
		if (!$notes)
		{
			$message = str_replace('[quote][/quote]', phpbb::$user->lang['NO_NOTES'], $message);
		}

		// Parse the message
		$message_uid = $message_bitfield = $message_options = false;
		generate_text_for_storage($message, $message_uid, $message_bitfield, $message_options, true, true, true);

		$data = array(
			'address_list'		=> array('u' => $authors),
			'from_user_id'		=> phpbb::$user->data['user_id'],
			'from_username'		=> phpbb::$user->data['username'],
			'icon_id'			=> 0,
			'from_user_ip'		=> phpbb::$user->ip,
			'enable_bbcode'		=> true,
			'enable_smilies'	=> true,
			'enable_urls'		=> true,
			'enable_sig'		=> true,
			'message'			=> $message,
			'bbcode_bitfield'	=> $message_bitfield,
			'bbcode_uid'		=> $message_uid,
		);

		// Hooks
		titania::$hook->call_hook_ref(array(__CLASS__, __FUNCTION__), $data, $this);

		// Submit Plz
		submit_pm('post', $subject, $data, true);
	}

	/**
	* Get the revision object for this queue
	*/
	public function get_revision()
	{
		$sql = 'SELECT * FROM ' . TITANIA_REVISIONS_TABLE . '
			WHERE contrib_id = ' . $this->contrib_id . '
				AND revision_id = ' . $this->revision_id;
		$result = phpbb::$db->sql_query($sql);
		$row = phpbb::$db->sql_fetchrow($result);
		phpbb::$db->sql_freeresult($result);

		if ($row)
		{
			$revision = new titania_revision(contribs_overlord::get_contrib_object($this->contrib_id, true), $this->revision_id);
			$revision->__set_array($row);
			return $revision;
		}

		return false;
	}

	/**
	* Get the queue discussion topic or create one if needed
	*
	* @param bool $check_only Return false if topic does not exist instead of creating it
	*
	* @return titania_topic object
	*/
	public function get_queue_discussion_topic($check_only = false)
	{
		$sql = 'SELECT * FROM ' . TITANIA_TOPICS_TABLE . '
			WHERE parent_id = ' . $this->contrib_id . '
				AND topic_type = ' . TITANIA_QUEUE_DISCUSSION;
		$result = phpbb::$db->sql_query($sql);
		$row = phpbb::$db->sql_fetchrow($result);
		phpbb::$db->sql_freeresult($result);

		if ($row)
		{
			$topic = new titania_topic;
			$topic->__set_array($row);
			$this->queue_discussion_topic_id = $topic->topic_id;

			return $topic;
		}
		else if ($check_only)
		{
			return false;
		}

		// No queue discussion topic...so we must create one
		titania::add_lang('posting');

		$contrib = contribs_overlord::get_contrib_object($this->contrib_id, true);

		$post = new titania_post(TITANIA_QUEUE_DISCUSSION);
		$post->topic->__set_array(array(
			'parent_id'			=> $this->contrib_id,
			'topic_category'	=> $contrib->contrib_type,
			'topic_url'			=> serialize(array(
				'contrib_type'	=> $contrib->type->url,
				'contrib'		=> $contrib->contrib_name_clean,
			)),
			'topic_sticky'		=> true,
		));
		$post->__set_array(array(
			'post_access'		=> TITANIA_ACCESS_AUTHORS,
			'post_subject'		=> sprintf(phpbb::$user->lang['QUEUE_DISCUSSION_TOPIC_TITLE'], $contrib->contrib_name),
			'post_text'			=> phpbb::$user->lang['QUEUE_DISCUSSION_TOPIC_MESSAGE'],
		));
		$post->generate_text_for_storage(true, true, true);
		$post->submit();
		$this->queue_discussion_topic_id = $post->topic->topic_id;

		return $post->topic;
	}

	/**
	* Get queue item URL.
	*
	* @param bool|string $action	Optional action to link to.
	* @param array $params			Optional parameters to add to URL.
	*
	* @return string Returns generated URL.
	*/
	public function get_url($action = false, $params = array())
	{
		$controller = 'phpbb.titania.queue.item';
		$params += array(
			'id'	=> $this->queue_id,
		);

		if ($action)
		{
			$controller .= '.action';
			$params['action'] = $action;
		}

		return $this->controller_helper->route($controller, $params);
	}

	/**
	* Get URL to queue tool.
	*
	* @param string $tool		Tool.
	* @param int $revision_id	Revision id.
	* @param array $params		Additional parameters to append to the URL.
	*
	* @return string
	*/
	public function get_tool_url($tool, $revision_id, array $params = array())
	{
		$params += array(
			'tool'	=> $tool,
			'id'	=> $revision_id,
		);
		return $this->controller_helper->route('phpbb.titania.queue.tools', $params);
	}
}
