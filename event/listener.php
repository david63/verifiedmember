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

	/** @var functions */
	protected $functions;

	/** @var array phpBB tables */
	protected $tables;

	/** @var string */
	protected $images_path;

	/**
	 * Constructor for listener
	 *
	 * @param request           $request		Request object
	 * @param driver_interface  $db				Db object
	 * @param language          $language		Language object
	 * @param template          $template		Template object
	 * @param functions         $functions		Functions for the extension
	 * @param array             $tables			phpBB db tables
	 * @param string            $images_path    Path to this extension's images
	 *
	 * @access public
	 */
	public function __construct(request $request, driver_interface $db, language $language, template $template, functions $functions, array $tables, string $images_path)
	{
		$this->request		= $request;
		$this->db			= $db;
		$this->language		= $language;
		$this->template		= $template;
		$this->functions	= $functions;
		$this->tables		= $tables;
		$this->images_path	= $images_path;
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
			'core.acp_manage_group_request_data'		=> 'add_group',
			'core.acp_manage_group_initialise_data'		=> 'manage_group_initialise_data',
			'core.acp_manage_group_display_form' 		=> 'manage_group_display_form',
			'core.viewtopic_modify_post_row' 			=> 'modify_post_row',
			'core.memberlist_prepare_profile_data'		=> 'profile_template',
		];
	}

	/**
	 * Add a group
	 *
	 * @return  $event
	 * @access  public
	 */
	public function add_group($event)
	{
		$submit_ary = $event['submit_ary'];
		$submit_ary['verified_member'] = $this->request->variable('group_verified_member', '');
		$event['submit_ary'] = $submit_ary;
	}

	/**
	 * Initialise the group data
	 *
	 * @return  $event
	 * @access  public
	 */
	public function manage_group_initialise_data($event)
	{
		$group_row      = $event['group_row'];
		$test_variables = $event['test_variables'];
		$submit_ary     = $event['submit_ary'];

		$group_row['verified_member']      = $this->request->variable('group_verified_member', '');
		$test_variables['verified_member'] = 'string';
		$submit_ary['verified_member']     = $this->request->variable('group_verified_member', '');

		$event['group_row']      = $group_row;
		$event['test_variables'] = $test_variables;
		$event['submit_ary']     = $submit_ary;
	}

	/**
	 * Display the group data
	 *
	 * @return  $event
	 * @access  public
	 */
	public function manage_group_display_form($event)
	{
		$this->language->add_lang('acp_groups', $this->functions->get_ext_namespace());

		$group_row = $event['group_row'];
		$group_row['group_verified_member'] = (!empty($group_row)) ? $group_row['group_verified_member'] : '';

		$files = array_slice(scandir($this->images_path), 2);

		$image_files	= '';
		$selected    	= ($group_row['group_verified_member'] == '') ? ' selected="selected"' : '';
		$image_files 	.= '<option value="' . '' . '"' . $selected . '>' . $this->language->lang('SELECT_IMAGE') . '</option>';
		foreach ($files as $image)
		{
			$selected 		= ($group_row['group_verified_member'] == $image) ? ' selected="selected"' : '';
			$image_files	.= '<option value="' . $image . '"' . $selected . '>' . $image . '</option>';
		}
		$image_select = '<select name="group_verified_member" id="group_verified_member">' . $image_files . '</select>';

		$this->template->assign_vars([
			'VERIFIED_MEMBER' 	=> $image_select,
			'VERIFY_IMAGE' 		=> $this->images_path . '/' . $group_row['group_verified_member'],
			'SHOW_VERIFY_IMAGE'	=> ($group_row['group_verified_member']) ? true : false,
		]);
	}

	/**
	 * Modify the post row
	 *
	 * @return  $event
	 * @access  public
	 */
	public function modify_post_row($event)
	{
		$post_row 						= $event['post_row'];
		$verify_image 					= $this->get_group_image($post_row['POSTER_ID']);
		$post_row['VERIFY_IMAGE'] 		= ($verify_image) ? $this->images_path . '/' . $verify_image['group_verified_member'] : '';
		$post_row['SHOW_VERIFY_IMAGE']	= ($verify_image) ? true : false;
		$event['post_row'] 				= $post_row;
	}

	/**
	 * Modify the profile template data
	 *
	 * @return  $event
	 * @access  public
	 */
	public function profile_template($event)
	{
		$template_data 						= $event['template_data'];
		$data 								= $event['data'];
		$verify_image 						= $this->get_group_image($data['user_id']);
		$template_data['VERIFY_IMAGE'] 		= ($verify_image) ? $this->images_path . '/' . $verify_image['group_verified_member'] : '';
		$template_data['SHOW_VERIFY_IMAGE']	= ($verify_image) ? true : false;
		$event['template_data'] 			= $template_data;
	}

	/**
	 * Get the group image for the user
	 *
	 * @return  $event
	 * @access  public
	 */
	public function get_group_image($user_id)
	{
		$sql = $this->db->sql_build_query('SELECT', [
			'SELECT' => 'g.group_verified_member',
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
