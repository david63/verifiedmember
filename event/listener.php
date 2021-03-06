<?php
/**
 *
 * @package Verified Member Extension
 * @copyright (c) 2021 david63
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace david63\verifiedmember\event;

/**
 * @ignore
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use phpbb\request\request;
use phpbb\db\driver\driver_interface;
use phpbb\language\language;
use phpbb\template\template;
use phpbb\group\helper;
use david63\verifiedmember\core\functions;

/**
 * Event listener
 */
class listener implements EventSubscriberInterface
{
	/** @var request */
	protected $request;

	/** @var driver_interface */
	protected $db;

	/** @var language */
	protected $language;

	/** @var template */
	protected $template;

	/** @var helper */
	protected $group_helper;

	/** @var functions */
	protected $functions;

	/** @var array phpBB tables */
	protected $tables;

	/** @var string */
	protected $images_path;

	/** @var string phpBB root path */
	protected $root_path;

	/**
	 * Constructor for listener
	 *
	 * @param request           $request        Request object
	 * @param driver_interface  $db             Db object
	 * @param language          $language       Language object
	 * @param template          $template       Template object
	 * @param helper            $group_helper   Group helper object
	 * @param functions         $functions      Functions for the extension
	 * @param array             $tables         phpBB db tables
	 * @param string            $images_path	Path to this extension's images
	 * @param string			$root_path		phpBB root path
	 *
	 * @access public
	 */
	public function __construct(request $request, driver_interface $db, language $language, template $template, helper $group_helper, functions $functions, array $tables, string $images_path, string $root_path)
	{
		$this->request      = $request;
		$this->db           = $db;
		$this->language     = $language;
		$this->template     = $template;
		$this->group_helper	= $group_helper;
		$this->functions    = $functions;
		$this->tables       = $tables;
		$this->images_path  = $images_path;
		$this->root_path	= $root_path;
	}

	/**
	 * Assign functions defined in this class to event listeners in the core
	 *
	 * @return array
	 * @static
	 * @access public
	 */
	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup' 							=> 'load_language_on_setup',
			'core.acp_users_overview_before' 			=> 'acp_verified_member',
			'core.acp_manage_group_request_data' 		=> 'add_group',
			'core.acp_manage_group_initialise_data'		=> 'manage_group_initialise_data',
			'core.acp_manage_group_display_form' 		=> 'manage_group_display_form',
			'core.memberlist_prepare_profile_data' 		=> 'profile_template',
			'core.ucp_pm_view_messsage'					=> 'pm_view_message',
			'core.message_history_modify_template_vars'	=> 'pm_quote_modify',
			'core.viewtopic_cache_user_data' 			=> 'modify_user_cache',
			'core.topic_review_modify_row'				=> 'modify_topic_review',
			'core.viewtopic_modify_post_row'			=> ['modify_post_row', -10], // Run after other extensions
			'core.modify_username_string'	 			=> ['modify_username', -10], // Make compatible with other extensions using this event
		];
	}

	/**
	 * Load common language file during user setup
	 *
	 * @param object $event The event object
	 * @return null
	 * @access public
	 */
	public function load_language_on_setup($event)
	{
		$lang_set_ext   = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name' => $this->functions->get_ext_namespace(),
			'lang_set' => 'vm_common',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	 * Show whether a member is verified on ACP overview
	 *
	 * @param object $event The event object
	 *
	 * @return $template
	 * @access public
	 */
	public function acp_verified_member($event)
	{
		$this->language->add_lang('acp_users', $this->functions->get_ext_namespace());

		$user_row     = $event['user_row'];
		$verify_image = $this->get_group_image($user_row['user_id']);

		$this->template->assign_vars([
			'GROUP_NAME' 		=> ($verify_image) ? $this->group_helper->get_name($verify_image['group_name']) : '',

			'SHOW_VERIFY_IMAGE'	=> ($verify_image) ? true : false,

			'VERIFIED_MEMBER' 	=> ($verify_image) ? $this->language->lang('YES') : $this->language->lang('NO'),
			'VERIFY_IMAGE' 		=> ($verify_image) ? $this->root_path . $this->images_path . $verify_image['group_verified_member'] : '',
		]);
	}

	/**
	 * Add a group
	 *
	 * @param object $event The event object
	 *
	 * @return  $event
	 * @access  public
	 */
	public function add_group($event)
	{
		$submit_ary                    	= $event['submit_ary'];
		$submit_ary['verified_member']	= $this->request->variable('group_verified_member', '');
		$submit_ary['verified_title']	= $this->request->variable('group_verified_title', '');
		$event['submit_ary']           	= $submit_ary;
	}

	/**
	 * Initialise the group data
	 *
	 * @param object $event The event object
	 *
	 * @return  $event
	 * @access  public
	 */
	public function manage_group_initialise_data($event)
	{
		$group_row      = $event['group_row'];
		$test_variables = $event['test_variables'];
		$submit_ary     = $event['submit_ary'];

		$group_row['verified_member']      	= $this->request->variable('group_verified_member', '');
		$group_row['verified_title']      	= $this->request->variable('group_verified_title', '');
		$test_variables['verified_member'] 	= 'string';
		$test_variables['verified_title'] 	= 'string';
		$submit_ary['verified_member']     	= $this->request->variable('group_verified_member', '');
		$submit_ary['verified_title']     	= $this->request->variable('group_verified_title', '');

		$event['group_row']      = $group_row;
		$event['test_variables'] = $test_variables;
		$event['submit_ary']     = $submit_ary;
	}

	/**
	 * Display the group data
	 *
	 * @param object $event The event object
	 *
	 * @return  $template
	 * @access  public
	 */
	public function manage_group_display_form($event)
	{
		$this->language->add_lang(['acp_groups', 'vm_common'], $this->functions->get_ext_namespace());

		$group_row                          = $event['group_row'];
		$group_row['group_verified_member'] = (!empty($group_row)) ? $group_row['group_verified_member'] : '';
		$group_row['group_verified_title']	= (!empty($group_row)) ? $group_row['group_verified_title'] : '';

		// Create the images select list from the images folder
		$image_files = '';
		$files       = array_slice(scandir($this->root_path . $this->images_path), 2);
		$selected    = ($group_row['group_verified_member'] == '') ? ' selected="selected"' : '';
		$image_files .= '<option value="' . '' . '"' . $selected . '>' . $this->language->lang('SELECT_IMAGE') . '</option>';

		foreach ($files as $image)
		{
			$selected 		= ($group_row['group_verified_member'] == $image) ? ' selected="selected"' : '';
			$image_files	.= '<option value="' . $image . '"' . $selected . '>' . $image . '</option>';
		}
		$image_select = '<select name="group_verified_member" id="group_verified_member">' . $image_files . '</select>';

		// Create the titles select list
		$title_options	= '';
		$selected		= ($group_row['group_verified_title'] == '') ? ' selected="selected"' : '';
		$title_options	.= '<option value="' . '' . '"' . $selected . '>' . $this->language->lang('SELECT_TITLE') . '</option>';

		foreach ($this->language->lang_raw('VERIFIED') as $key => $title)
		{
			$selected		= ($group_row['group_verified_title'] == $key) ? ' selected="selected"' : '';
			$title_options	.= '<option value="' . $key . '"' . $selected . '>' . $title . '</option>';
		}
		$title_select = '<select name="group_verified_title" id="group_verified_title">' . $title_options . '</select>';

		$this->template->assign_vars([
			'SHOW_VERIFY_IMAGE'	=> ($group_row['group_verified_member']) ? true : false,

			'VERIFIED_MEMBER' 	=> $image_select,
			'VERIFIED_TITLE' 	=> $title_select,
			'VERIFY_IMAGE' 		=> $this->root_path . $this->images_path . $group_row['group_verified_member'],
		]);
	}

	/**
	 * Modify the profile template data
	 * This is needed to prevent problems with Memberlist popup
	 *
	 * @param object $event The event object
	 *
	 * @return  $event
	 * @access  public
	 */
	public function profile_template($event)
	{
		$template_data = $event['template_data'];
		// Use this to check if the user has a group image
		$group_member = $this->get_group_image($event['data']['user_id']);
		if ($group_member)
		{
			$template_data['A_USERNAME'] 	= $this->strip_verify_image($template_data['A_USERNAME']);
			$template_data['USERNAME'] 		= $this->strip_verify_image($template_data['USERNAME']);
			$event['template_data']      	= $template_data;
		}
	}

	/**
	 * Modify PM data
	 *
	 * @param object $event The event object
	 *
	 * @return  $event
	 * @access  public
	 */
	public function pm_view_message($event)
	{
		$msg_data					= $event['msg_data'];
		$msg_data['CONTACT_USER']	= $this->strip_verify_image($msg_data['CONTACT_USER']);
		$event['msg_data']        	= $msg_data;
	}

	/**
	 * Modify PM quote data
	 *
	 * @param object $event The event object
	 *
	 * @return  $event
	 * @access  public
	 */
	public function pm_quote_modify($event)
	{
		$template_vars 							= $event['template_vars'];
		$template_vars['MESSAGE_AUTHOR']		= $this->strip_verify_image($template_vars['MESSAGE_AUTHOR']);
		$template_vars['MESSAGE_AUTHOR_QUOTE']	= $this->strip_verify_image($template_vars['MESSAGE_AUTHOR_QUOTE']);
		$event['template_vars']        			= $template_vars;
	}

	/**
	 * Modify the user_data_cache
	 * This is needed to prevent problems with Contact popup
	 *
	 * @param object $event The event object
	 *
	 * @return  $event
	 * @access  public
	 */
	public function modify_user_cache($event)
	{
		$user_cache_data                 = $event['user_cache_data'];
		$user_cache_data['contact_user'] = $this->strip_verify_image($user_cache_data['contact_user']);
		$event['user_cache_data']        = $user_cache_data;
	}

	/**
	 * Modify the topic review
	 *
	 * @param object $event The event object
	 *
	 * @return  $event
	 * @access  public
	 */
	public function modify_topic_review($event)
	{
		$post_row 					= $event['post_row'];
		$post_row['POSTER_QUOTE']	= $this->strip_verify_image($post_row['POSTER_QUOTE']);
		$post_row['POST_AUTHOR'] 	= $this->strip_verify_image($post_row['POST_AUTHOR']);
		$event['post_row']        	= $post_row;
	}

	/**
	 * Modify the post row data
	 * Used to manipulate other extension's data
	 *
	 * @param object $event The event object
	 *
	 * @return  $event
	 * @access  public
	 */
	public function modify_post_row($event)
	{
		$post_row = $event['post_row'];

		// Thanks for posts extension
		if (array_key_exists('THANK_IMG', $post_row))
		{
			$post_row['POST_AUTHOR']	= $this->strip_verify_image($post_row['POST_AUTHOR']);
			$event['post_row'] 			= $post_row;
		}
	}

	/**
	 * Modify the username string
	 *
	 * @param object $event The event object
	 *
	 * @return  $event
	 * @access  public
	 */
	public function modify_username($event)
	{
		// We do not want to run this function on the portal extension
		if (!strpos($this->request->server('PHP_SELF'), 'portal'))
		{
			$username_string	= $event['username_string'];
			$mode            	= $event['mode'];
			$verify_image		= $this->get_group_image($event['user_id']);

			if ($verify_image && ($mode == 'full' || $mode == 'username'))
			{
				$username_string = $username_string . '&nbsp;<img src="' . generate_board_url() . $this->images_path . $verify_image['group_verified_member'] . '" title="' . $this->language->lang(['VERIFIED', $verify_image['group_verified_title']]) . '" />';

				$event['username_string'] = $username_string;
			}
		}
	}

	/**
	 * Strip the verify image from the username
	 *
	 * @return  username
	 * @access  public
	 */
	public function strip_verify_image($data)
	{
		return substr($data, 0, strpos($data, '<'));
	}

	/**
	 * Get the group image for the user
	 *
	 * @return  $image_data
	 * @access  public
	 */
	public function get_group_image($user_id)
	{
		$sql = $this->db->sql_build_query('SELECT', [
			'SELECT' => 'g.group_verified_member, g.group_verified_title, g.group_name',
			'FROM' => [
				$this->tables['groups'] => 'g',
			],
			'LEFT_JOIN' => [
				[
					'FROM' => [$this->tables['user_group'] => ' ug', ],
					'ON' => 'ug.group_id = g.group_id',
				],
			],
			'WHERE' => 'ug.user_id = ' . (int) $user_id,
		]);

		$result = $this->db->sql_query($sql);

		$image_data = '';

		while ($row = $this->db->sql_fetchrow($result))
		{
			if ($row['group_verified_member'])
			{
				$image_data = $row;
			}
		}

		$this->db->sql_freeresult($result);

		return $image_data;
	}
}
