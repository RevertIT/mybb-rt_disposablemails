<?php
/**
 * RT Disposable Mails
 *
 * RT Disposable Mails is a plugin which checks an external API to retrieve filtered spam mails,
 * and saves them into database periodically via tasks.
 *
 * @package rt_disposablemails
 * @author  RevertIT <https://github.com/revertit>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 */

declare(strict_types=1);

namespace rt\DisposableMails;

final class Frontend
{
	/**
	 * Hook: global_start
	 *
	 * @return void
	 */
	public function global_start(): void
	{
		global $mybb, $cache;

		if (isset($mybb->settings['rt_disposablemails_task_enabled'], $mybb->settings['rt_disposablemails_task_disableforum']) &&
			(int) $mybb->settings['rt_disposablemails_task_enabled'] === 1 && (int) $mybb->settings['rt_disposablemails_task_disableforum'] === 1
		)
		{
			if (!empty($cache->read('rt_disposablemails_locked')))
			{
				$mybb->settings['boardclosed'] = 1;
			}
		}

	}

	/**
	 * Hook: member_do_register_start
	 *
	 * @return void
	 */
	public function member_do_register_start(): void
	{
		global $mybb, $lang;

		$lang->load('rt_disposablemails');

		if (isset($mybb->settings['rt_disposablemails_disable_register']) && (int) $mybb->settings['rt_disposablemails_disable_register'] === 1)
		{
			if (!empty($mybb->get_input('email')))
			{
				if (\rt\DisposableMails\Core::is_banned_email($mybb->get_input('email')))
				{
					\rt\DisposableMails\Core::insert_log($mybb->get_input('email'), 2);
					error($lang->sprintf($lang->rt_disposablemails_prevent_registration, $mybb->get_input('email')), $lang->error);
				}
			}
		}
	}

	/**
	 * Hook: member_do_login_start
	 *
	 * @return void
	 */
	public function member_do_login_start(): void
	{
		global $mybb, $lang;

		$lang->load('rt_disposablemails');

		if (isset($mybb->settings['rt_disposablemails_disable_login']) && (int) $mybb->settings['rt_disposablemails_disable_login'] === 1)
		{
			if (!empty($mybb->get_input('username')))
			{
				if (\rt\DisposableMails\Core::is_banned_email($mybb->get_input('username')))
				{
					\rt\DisposableMails\Core::insert_log($mybb->get_input('username'), 1);
					error($lang->sprintf($lang->rt_disposablemails_prevent_login, $mybb->get_input('username')), $lang->error);
				}
			}
			elseif (!empty($mybb->get_input('quick_username')))
			{
				if (\rt\DisposableMails\Core::is_banned_email($mybb->get_input('quick_username')))
				{
					\rt\DisposableMails\Core::insert_log($mybb->get_input('quick_username'), 1);
					error($lang->sprintf($lang->rt_disposablemails_prevent_login, $mybb->get_input('quick_username')), $lang->error);
				}
			}
		}
	}

	/**
	 * Hook: task_hourlycleanup
	 *
	 * @param $args
	 * @return void
	 */
	public function task_hourlycleanup(&$args): void
	{
		global $db, $mybb, $cache, $args;

		if (isset($mybb->settings['rt_disposablemails_task_enabled'], $mybb->settings['rt_disposablemails_task_time']) &&
			(int) $mybb->settings['rt_disposablemails_task_enabled'] === 1
		)
		{
			$rt_disposablemails_clean_time = TIME_NOW - (60 * 60 * 24 * (int) $mybb->settings['rt_disposablemails_task_time']);

			$chunks = $cache->read('rt_disposablemails_total_chunks');

			$rt_last_entry = isset($chunks['cached_at']) ? (int) $chunks['cached_at'] : 0;

			// Heavy DB stress incoming, but this is the best way to make it multi-db engine compatible.
			// Finger point on you PSG and MariaDB
			if ($rt_last_entry === 0 || $rt_last_entry < $rt_disposablemails_clean_time)
			{
				$api = \rt\DisposableMails\Core::fetch_api();

				if (!empty($api))
				{
					// Set cache lock so we don't query db
					$cache->update('rt_disposablemails_locked', 1);

					\rt\DisposableMails\Core::save_mail_list($api);

					$cache->delete('rt_disposablemails_locked');
				}
			}
		}
	}
}