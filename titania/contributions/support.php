<?php
/**
 *
 * @package titania
 * @version $Id$
 * @copyright (c) 2008 phpBB Customisation Database Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

/**
* @ignore
*/
if (!defined('IN_TITANIA'))
{
	exit;
}

$post_id = request_var('p', 0);
$topic_id = request_var('t', 0);

// Load the topic and contrib items
if ($post_id)
{
	$topic_id = topics_overlord::load_topic_from_post($post_id);

	// Load the topic into a topic object
	$topic = topics_overlord::get_topic_object($topic_id);
	if ($topic === false)
	{
		trigger_error('NO_TOPIC');
	}

	// Load the contrib item
	load_contrib($topic->contrib_id);
	$topic->contrib = titania::$contrib;

	titania::generate_breadcrumbs(array(
		censor_text($topic->topic_subject)	=> $topic->get_url(),
	));
}
else if ($topic_id)
{
	topics_overlord::load_topic($topic_id);

	// Load the topic into a topic object
	$topic = topics_overlord::get_topic_object($topic_id);
	if ($topic === false)
	{
		trigger_error('NO_TOPIC');
	}

	// Load the contrib item
	load_contrib($topic->contrib_id);

	$topic->contrib = titania::$contrib;

	titania::generate_breadcrumbs(array(
		censor_text($topic->topic_subject)	=> $topic->get_url(),
	));
}
else
{
	// Load the contrib item
	load_contrib();
}

// Output the simple info on the contrib
titania::$contrib->assign_details(true);

$action = request_var('action', '');

switch ($action)
{
	case 'post' :
	case 'reply' :
	case 'edit' :
		titania::add_lang('posting');
		phpbb::$user->add_lang('posting');

		if ($action != 'edit' && (($action == 'post' && !phpbb::$auth->acl_get('u_titania_topic')) || ($action == 'reply' && (!$topic_id || !phpbb::$auth->acl_get('u_titania_post')))))
		{
			trigger_error('NO_AUTH');
		}

		if ($action == 'post')
		{
			$topic = new titania_topic(TITANIA_SUPPORT, titania::$contrib);
			$post = new titania_post(TITANIA_SUPPORT, $topic);
			$post->topic->contrib_id = titania::$contrib->contrib_id;
		}
		else if ($action == 'reply')
		{
			$post = new titania_post(TITANIA_SUPPORT, $topic);
		}
		else
		{
			$post = new titania_post(TITANIA_SUPPORT, $topic, $post_id);
			if ($post->load() === false)
			{
				trigger_error('NO_POST');
			}
		}

		// Load the message object
		$message = new titania_message($post);
		$message->set_auth(array(
			'bbcode'		=> phpbb::$auth->acl_get('u_titania_bbcode'),
			'smilies'		=> phpbb::$auth->acl_get('u_titania_smilies'),
			'lock'			=> ($action == 'edit' && $post->post_user_id != phpbb::$user->data['user_id'] && phpbb::$auth->acl_get('m_titania_post_mod')) ? true : false,
			'sticky_topic'	=> (($action == 'post' || ($action == 'edit' && $post_id == $post->topic->topic_first_post_id)) && (phpbb::$auth->acl_get('m_titania_post_mod') || titania::$contrib->is_author || titania::$contrib->is_active_coauthor)) ? true : false,
			'lock_topic'	=> (phpbb::$auth->acl_get('m_titania_post_mod') || (phpbb::$auth->acl_get('u_titania_post_mod_own') && $post->topic->topic_first_post_user_id == phpbb::$user->data['user_id'])) ? true : false,
			'attachments'	=> phpbb::$auth->acl_get('u_titania_post_attach'),
		));
		$message->set_settings(array(
			'display_captcha'			=> (!phpbb::$user->data['is_registered']) ? true : false,
			'subject_default_override'	=> ($action == 'reply') ? 'Re: ' . $topic->topic_subject : false,
			'attachments_group'			=> TITANIA_ATTACH_EXT_SUPPORT,
		));

		// Submit check...handles running $post->post_data() if required
		$submit = $message->submit_check();

		if ($submit)
		{
			$error = $post->validate();

			if (($validate_form_key = $message->validate_form_key()) !== false)
			{
				$error[] = $validate_form_key;
			}

			// @todo use permissions for captcha
			if (!phpbb::$user->data['is_registered'] && ($validate_captcha = $message->validate_captcha()) !== false)
			{
				$error[] = $validate_captcha;
			}

			if (sizeof($error))
			{
				phpbb::$template->assign_var('ERROR', implode('<br />', $error));
			}
			else
			{
				$post->submit();

				$message->submit($post->post_access);

				redirect($post->get_url());
			}
		}

		$message->display();

		switch ($action)
		{
			case 'post' :
				phpbb::$template->assign_vars(array(
					'S_POST_ACTION'		=> titania_url::append_url(titania::$contrib->get_url('support'), array('action' => $action)),
					'L_POST_A'			=> phpbb::$user->lang['POST_TOPIC'],
				));
				titania::page_header('NEW_TOPIC');
			break;
			case 'reply' :
				phpbb::$template->assign_vars(array(
					'S_POST_ACTION'		=> $topic->get_url('reply'),
					'L_POST_A'			=> phpbb::$user->lang['POST_REPLY'],
				));
				titania::page_header('POST_REPLY');
			break;
			case 'edit' :
				phpbb::$template->assign_vars(array(
					'S_POST_ACTION'		=> $post->get_url('edit', false),
					'L_POST_A'			=> phpbb::$user->lang['EDIT_POST'],
				));
				titania::page_header('EDIT_POST');
			break;
		}

		titania::page_footer(true, 'contributions/contribution_support_post.html');
	break;

	case 'delete' :
	case 'undelete' :
		phpbb::$user->add_lang('posting');

		$post = new titania_post(TITANIA_SUPPORT, $topic, $post_id);
		if ($post->load() === false)
		{
			trigger_error('NO_POST');
		}

		if (titania::confirm_box(true))
		{
			if ($action == 'delete')
			{
				$redirect_post_id = posts_overlord::next_prev_post_id($post->topic_id, $posts->post_id);

				// Delete the post (let's not allow hard deleting for now)
				$post->soft_delete();

				// try a nice redirect, back to the position where the post was deleted from
				if ($redirect_post_id)
				{
					redirect(titania_url::append_url($topic->get_url(), array('p' => $redirect_post_id, '#p' => $redirect_post_id)));
				}

				redirect($topic->get_url());
			}
			else
			{
				$post->undelete();

				redirect($post->get_url());
			}
		}
		else
		{
			titania::confirm_box(false, (($action == 'delete') ? 'DELETE_POST' : 'UNDELETE_POST'), $post->get_url($action));
		}
		redirect($post->get_url());
	break;

	default :
		phpbb::$user->add_lang('viewforum');

		if ($topic_id)
		{
			posts_overlord::display_topic_complete($topic);

			titania::page_header(phpbb::$user->lang['CONTRIB_SUPPORT'] . ' - ' . censor_text($topic->topic_subject));

			if (phpbb::$auth->acl_get('u_titania_post'))
			{
				phpbb::$template->assign_var('U_POST_REPLY', titania_url::append_url($topic->get_url(), array('action' => 'reply')));
			}
		}
		else
		{
			topics_overlord::display_forums_complete('support', titania::$contrib);

			titania::page_header('CONTRIB_SUPPORT');

			if (phpbb::$auth->acl_get('u_titania_topic'))
			{
				phpbb::$template->assign_var('U_POST_TOPIC', titania_url::append_url(titania::$contrib->get_url('support'), array('action' => 'post')));
			}
		}

		titania::page_footer(true, 'contributions/contribution_support.html');
	break;
}
