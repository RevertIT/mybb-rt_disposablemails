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

final class Backend
{
	/**
	 * Hook: admin_config_settings_change
	 *
	 * @return void
	 */
	public function admin_config_settings_change(): void
	{
		global $mybb, $gid, $lang;

		$lang->load('rt_disposablemails');

		if (isset($mybb->input['upsetting']['rt_disposablemails_task_enabled']))
		{
			// Revert quick change to no when plugin is disabled
			if ((int) $mybb->input['upsetting']['rt_disposablemails_task_enabled'] === 0 && (int) $mybb->input['upsetting']['rt_disposablemails_task_disableforum'] === 1)
			{
				$mybb->input['upsetting']['rt_disposablemails_task_disableforum'] = 0;
			}
		}

		// Prevent idiotic inputs
		if (isset($mybb->input['upsetting']['rt_disposablemails_task_time']))
		{
			if ((int) $mybb->input['upsetting']['rt_disposablemails_task_time'] <= 0)
			{
				flash_message($lang->rt_disposablemails_task_time_error, 'error');
				admin_redirect("index.php?module=config-settings&action=change&gid=".(int)$mybb->input['gid']);
			}
		}
	}

	/**
	 * Hook: admin_load
	 *
	 * @return void
	 */
	public function admin_load(): void
	{
		global $db, $mybb, $lang, $run_module, $action_file, $page, $sub_tabs, $form;

		if ($run_module === 'tools' && $action_file === \rt\DisposableMails\Core::$PLUGIN_DETAILS['prefix'])
		{
			$table = new \Table();
			$table_prefix = TABLE_PREFIX;
			$prefix = \rt\DisposableMails\Core::$PLUGIN_DETAILS['prefix'];
			$lang->load('rt_disposablemails');

			$page->add_breadcrumb_item($lang->{$prefix . '_menu'}, "index.php?module=tools-{$prefix}");

			$page_url = "index.php?module={$run_module}-{$action_file}";

			$sub_tabs = [];

			$allowed_actions =
			$tabs = [
				'statistics',
				'logs'
			];

			foreach ($tabs as $row)
			{
				$sub_tabs[$row] = [
					'link' => $page_url . '&amp;action=' . $row,
					'title' => $lang->{$prefix .'_tab_' . $row},
					'description' => $lang->{$prefix . '_tab_' . $row . '_desc'},
				];
			}

			if (!$mybb->input['action'] || $mybb->input['action'] === 'statistics')
			{
				$page->output_header($lang->{$prefix . '_menu'} . ' - ' . $lang->{$prefix .'_tab_' . 'statistics'});
				$page->output_nav_tabs($sub_tabs, 'statistics');

				// Query the data
				$sql_table = TABLE_PREFIX.'rt_dispmail_logs';

				$graph_all = $db->write_query(<<<SQL
				SELECT
					COUNT(*) AS `count`,
					DATE_FORMAT(FROM_UNIXTIME(dateline),
					'%M %d, %Y') AS `day`
				FROM
					{$sql_table}
				GROUP BY
					`day`
				ORDER BY
					`dateline` ASC
				LIMIT
					365;
				SQL);

				$graph_login = $db->write_query(<<<SQL
				SELECT
					COUNT(*) AS `count`,
					DATE_FORMAT(FROM_UNIXTIME(dateline),
					'%M %d, %Y') AS `day`
				FROM
					{$sql_table}
				WHERE
					`action` = 1
				GROUP BY
					`day`
				ORDER BY
					`dateline` ASC
				LIMIT
					365;
				SQL);

				$graph_register = $db->write_query(<<<SQL
				SELECT
					COUNT(*) AS `count`,
					DATE_FORMAT(FROM_UNIXTIME(dateline),
					'%M %d, %Y') AS `day`
				FROM
					{$sql_table}
				WHERE
					`action` = 2
				GROUP BY
					`day`
				ORDER BY
					`dateline` ASC
				LIMIT
					365;
				SQL);

				// Process the data into arrays for line charts
				$labels = [];
				$values = [];
				foreach ($graph_all as $row)
				{
					$labels[] = $row['day'];
					$values[] = $row['count'];
				}
				$labels = json_encode($labels);
				$values = json_encode($values);

				$values_login = [];
				foreach ($graph_login as $row)
				{
					$values_login[] = $row['count'];
				}
				$values_login = json_encode($values_login);

				$values_register = [];
				foreach ($graph_register as $row)
				{
					$values_register[] = $row['count'];
				}
				$values_register = json_encode($values_register);

				$graph_html = <<<GRAPH
				<!-- Create a canvas element to hold the chart -->
				<canvas id="disposable_mails" style="width: 100%; height: 300px;"></canvas>
				
				<!-- Generate the chart using PHP data -->
				<script>
				const search_log = document.getElementById('disposable_mails').getContext('2d');
				new Chart(search_log, {
				type: 'line',
				data:
				{
					labels: {$labels},
					datasets: [
						{
							label: '$lang->rt_disposablemails_chart_label',
							data: {$values},
							backgroundColor: 'rgba(54, 162, 235, 0.2)',
							borderColor: 'rgba(54, 162, 235, 1)',
							borderWidth: 1,
						},
						{
							label: '$lang->rt_disposablemails_chart_label_login',
							data: {$values_login},
							backgroundColor: 'rgba(82, 200, 15, 0.2)',
							borderColor: 'rgba(82, 200, 15, 1)',
							borderWidth: 1,
						},
						{
							label: '$lang->rt_disposablemails_chart_label_register',
							data: {$values_register},
							backgroundColor: 'rgba(200, 180, 15, 0.2)',
							borderColor: 'rgba(200, 180, 15, 1)',
							borderWidth: 1,
						},
					]
				},
				options:
				{
					plugins:
					{
						legend:
						{
							display: true
						}
					},
					responsive: true,
					scales:
					{
						x:
						{
							type: 'category',
							title: {
							  display: true,
							  text: '{$lang->rt_disposablemails_chart_date}'
							}
						},
						y:
						{
							title:
							{
								display: true,
								text: '{$lang->rt_disposablemails_chart_count}'
							},
							suggestedMin: 0
						}
					}
				}
				});
				</script>
				GRAPH;

				echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
				// Line graph
				$table->construct_header($lang->{$prefix . '_chart_desc'});
				$table->construct_cell($graph_html);
				$table->construct_row();
				$table->output($lang->{$prefix . '_chart_title'});
				$page->output_footer();
			}
			elseif ($mybb->input['action'] === 'logs')
			{
				$page->output_header($lang->{$prefix . '_menu'} . ' - ' . $lang->{$prefix .'_tab_' . 'logs'});
				$page->output_nav_tabs($sub_tabs, 'logs');

				$where = '';
				$where_a = [];
				$input = [
					'ip' => $mybb->get_input('ip'),
					'email' => $mybb->get_input('email'),
					'order_by' => $mybb->get_input('order_by'),
					'action_type' => $mybb->get_input('action_type', \MyBB::INPUT_INT)
				];

				$order_by = $mybb->get_input('order_by') === 'asc' ? 'ASC' : 'DESC';

				if ($mybb->request_method === 'post')
				{
					if (!empty($input['ip']))
					{
						$packed_ip = my_inet_pton($input['ip']);
						$where_a[] = "ip = {$db->escape_binary($packed_ip)}";
					}

					if (!empty($input['email']))
					{
						$where_a[] = "email LIKE '%{$db->escape_string($input['email'])}%'";
					}

					switch ($input['action_type'])
					{
						case 1:
							$where_a[] = "action = '1'";
							break;
						case 2:
							$where_a[] = "action = '2'";
							break;
					}

					if (!empty($where_a))
					{
						$where = 'WHERE ' . implode(' AND ', $where_a);
					}

					if (!empty($mybb->get_input('delete_all')))
					{
						$db->delete_query('rt_dispmail_logs');
						$num_deleted = $db->affected_rows();

						// Log admin action
						log_admin_action($num_deleted);

						flash_message($lang->rt_disposablemails_logs_all_deleted, 'success');
						admin_redirect("index.php?module=tools-rt_disposablemails&amp;action=logs");
					}

					if (!empty($mybb->get_input('log', \MyBB::INPUT_ARRAY)))
					{
						$log_ids = implode(",", array_map("intval", $mybb->get_input('log', \MyBB::INPUT_ARRAY)));

						if($log_ids)
						{
							$db->delete_query("rt_dispmail_logs", "id IN ({$log_ids})");
							$num_deleted = $db->affected_rows();
							// Log admin action
							log_admin_action($num_deleted);
						}
						flash_message($lang->rt_disposablemails_logs_selected_deleted, 'success');
						admin_redirect("index.php?module=tools-rt_disposablemails&amp;action=logs");
					}

				}

				$form = new \Form("index.php?module=tools-{$prefix}&amp;action=logs", "post", "logs");
				$table->construct_header($lang->{$prefix . '_tab_logs_desc'});

				$content = "{$lang->rt_disposablemails_from_email} {$form->generate_text_box('email', $mybb->get_input('email'))} ";
				$content .= "{$lang->rt_disposablemails_from_ip} {$form->generate_text_box('ip', $mybb->get_input('ip'))} ";
				$content .= "{$lang->rt_disposablemails_action_type} {$form->generate_select_box('action_type', [
					$lang->rt_disposablemails_action_type_all,
					$lang->rt_disposablemails_action_type_login,
					$lang->rt_disposablemails_action_type_register,
				], $input['action_type'])}";
				$content .= "{$lang->rt_disposablemails_sort} {$form->generate_select_box('order_by', [
                'desc' => $lang->rt_disposablemails_by_desc,
                'asc' => $lang->rt_disposablemails_by_asc,
            	], $input['order_by'])}";
				$content .= " ".$form->generate_submit_button($lang->view);
				$table->construct_cell($content);

				$table->construct_row();
				$table->output($lang->{$prefix . '_tab_logs'});
				$form->end();

				$query = $db->write_query(<<<SQL
				SELECT
					COUNT(*) as logs
				FROM
					{$table_prefix}rt_dispmail_logs
				{$where}
				SQL);

				$total_rows = $db->fetch_field($query, "logs");

				$per_page = 20;
				$pagenum = $mybb->get_input('page', \MyBB::INPUT_INT);

				if($pagenum)
				{
					$start = ($pagenum - 1) * $per_page;
					$pages = ceil($total_rows / $per_page);
					if($pagenum > $pages)
					{
						$start = 0;
						$pagenum = 1;
					}
				}
				else
				{
					$start = 0;
					$pagenum = 1;
				}

				$query = $db->write_query(<<<SQL
				SELECT
				   *
				FROM
					{$table_prefix}rt_dispmail_logs
				{$where}
				ORDER BY
					dateline {$order_by}
				LIMIT
					{$start}, {$per_page}
				SQL);

				$form = new \Form("index.php?module=tools-{$prefix}&amp;action=logs", "post", "logs");
				$table->construct_header($form->generate_check_box("allbox", 1, '', array('class' => 'checkall')));
				$table->construct_header($lang->{$prefix . '_logs_email'});
				$table->construct_header($lang->{$prefix . '_logs_ip'});
				$table->construct_header($lang->{$prefix . '_logs_action'});
				$table->construct_header($lang->{$prefix . '_logs_dateline'}, [
					'class' => 'align_center'
				]);
				$table->construct_header($lang->{$prefix . '_logs_controls'});
				foreach ($query as $row)
				{

					$row['dateline'] = my_date('relative', $row['dateline']);
					$row['email'] = htmlspecialchars_uni($row['email']);
					$row['ip'] = my_inet_ntop($row['ip']);

					$row['controls'] = "<a href='index.php?module=config-banning&filter={$row['ip']}'>{$lang->rt_disposablemails_logs_ban_ip}</a>";

					switch ($row['action'])
					{
						case 1:
							$row['action'] = htmlspecialchars_uni($lang->rt_disposablemails_action_type_login);
							break;
						case 2:
							$row['action'] = htmlspecialchars_uni($lang->rt_disposablemails_action_type_register);
							break;
						default:
							$row['action'] = htmlspecialchars_uni($lang->rt_disposablemails_action_type_all);
					}

					$table->construct_cell($form->generate_check_box("log[{$row['id']}]", $row['id'], ''));

					$table->construct_cell($row['email'], [
						'class' =>  'align_left',
					]);

					$table->construct_cell($row['ip'], [
						'class' => 'align_left'
					]);

					$table->construct_cell($row['action'], [
						'class' =>  'align_left',
					]);

					$table->construct_cell($row['dateline'], [
						'class' =>  'align_center',
					]);
					$table->construct_cell($row['controls'], [
						'class' =>  'align_center',
					]);
					$table->construct_row();
				}

				if($table->num_rows() === 0)
				{
					$table->construct_cell($lang->rt_disposablemails_logs_notfound, ['colspan' => '5']);
					$table->construct_row();
				}

				$table->output($lang->{$prefix . '_logs_list'});

				$buttons[] = $form->generate_submit_button($lang->delete_selected, array('onclick' => "return confirm('{$lang->rt_disposablemails_logs_delete_selected}');"));
				$buttons[] = $form->generate_submit_button($lang->delete_all, array('name' => 'delete_all', 'onclick' => "return confirm('{$lang->rt_disposablemails_logs_delete_all}');"));
				$form->output_submit_wrapper($buttons);
				$form->end();

				echo draw_admin_pagination($pagenum, $per_page, $total_rows, "index.php?module=tools-{$prefix}&amp;action=logs&amp;action_type={$input['action_type']}&amp;ip={$input['ip']}&amp;email={$input['email']}");

				$page->output_footer();
			}

			try
			{
				if (!in_array($mybb->get_input('action'), $allowed_actions))
				{
					throw new \Exception('Not allowed!');
				}
			}
			catch (\Exception $e)
			{
				flash_message($e->getMessage(), 'error');
				admin_redirect("index.php?module=tools-{$prefix}");
			}
		}
	}


	/**
	 * Hook: admin_tools_action_handler
	 *
	 * @param array $actions
	 * @return void
	 */
	public function admin_tools_action_handler(array &$actions): void
	{
		$prefix = \rt\DisposableMails\Core::$PLUGIN_DETAILS['prefix'];

		$actions[$prefix] = [
			'active'=> $prefix,
			'file'   => $prefix,
		];
	}

	/**
	 * Hook: admin_tools_menu
	 *
	 * @param array $sub_menu
	 * @return void
	 */
	public function admin_tools_menu(array &$sub_menu): void
	{
		global $lang;

		$lang->load('rt_disposablemails');
		$prefix = \rt\DisposableMails\Core::$PLUGIN_DETAILS['prefix'];

		$sub_menu[] = [
			'id' => $prefix,
			'title' => $lang->rt_disposablemails,
			'link' => 'index.php?module=tools-' . $prefix,
		];
	}
}