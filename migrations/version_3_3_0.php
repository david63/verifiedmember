<?php
/**
 *
 * @package Verified Member Extension
 * @copyright (c) 2021 david63
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace david63\verifiedmember\migrations;

class version_3_3_0 extends \phpbb\db\migration\migration
{
	/**
	 * @return array Array update data
	 * @access public
	 */
	public function update_schema()
	{
		// Add new column to groups table
		return [
			'add_columns' => [
				$this->table_prefix . 'groups' => [
					'group_verified_member' => ['VCHAR:50', ''],
				],
			],
		];
	}

	/**
	 * Drop the schemas from the database
	 *
	 * @return array Array of table schema
	 * @access public
	 */
	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'groups' => [
					'group_verified_member',
				],
			],
		];
	}
}
