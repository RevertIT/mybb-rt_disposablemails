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

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

RT_DisposableMails::autoload_plugin_hooks([
    'RT_DisposableMails_FrontEnd',
    'RT_DisposableMails_BackEnd'
]);

function rt_disposablemails_info(): array
{
    global $mybb;

    return [
        'name' => 'RT Disposable Mails',
        'description' => 'RT Disposable Mails is a plugin which checks an external API to retrieve filtered spam mails, and saves them into database periodically via tasks.
		<br><br><b><a href="/'.$mybb->config['admin_dir'].'/index.php?module=tools-tasks&action=run&tid=1&my_post_key='.$mybb->post_code.'" style="color: red">Force run plugin task</a></b> <i>(Works only if your last ban email entry was at least 1 day ago)</i> &middot; <a href="/'.$mybb->config['admin_dir'].'/index.php?module=config-banning&type=emails">Check banned mails</a>',
        'website' => 'https://github.com/RevertIT/mybb-rt_disposablemails',
        'author' => 'RevertIT',
        'authorsite' => 'https://github.com/RevertIT',
        'version' => '1.0',
        'compatibility' => '18*',
        'codename' => 'rt_disposablemails',
    ];
}

function rt_disposablemails_install(): void
{
    RT_DisposableMails::check_php_version();
    RT_DisposableMails::load_pluginlibrary();

    RT_DisposableMails::add_settings();
}

function rt_disposablemails_is_installed(): bool
{
    return RT_DisposableMails::is_installed();
}

function rt_disposablemails_uninstall(): void
{
    RT_DisposableMails::check_php_version();
    RT_DisposableMails::load_pluginlibrary();

    RT_DisposableMails::remove_settings();
}

function rt_disposablemails_activate(): void
{
    RT_DisposableMails::check_php_version();
    RT_DisposableMails::load_pluginlibrary();

    RT_DisposableMails::add_settings();

}

function rt_disposablemails_deactivate(): void
{
    RT_DisposableMails::check_php_version();
    RT_DisposableMails::load_pluginlibrary();

}

class RT_DisposableMails
{

    private const API_PROVIDERS = [
        1 => 'https://raw.githubusercontent.com/ivolo/disposable-email-domains/master/index.json',
        2 => 'https://raw.githubusercontent.com/RevertIT/disposable-email-domains/master/index.json'
    ];

    /**
     * Autoload plugin hooks
     *
     * @param array $class Array of classes to load for hooks
     * @return void
     */
    public static function autoload_plugin_hooks(array $class): void
    {
        global $plugins;

        foreach ($class as $hook)
        {
            if (!class_exists($hook))
            {
                continue;
            }

            $user_functions = get_class_methods(new $hook());

            foreach ($user_functions as $function)
            {
                $plugins->add_hook($function, [new $hook(), $function]);
            }
        }
    }

    public static function is_installed(): bool
    {
        global $mybb;

        if (isset($mybb->settings['rt_disposablemails_api_provider']))
        {
            return true;
        }

        return false;
    }

    public static function check_php_version(): void
    {
        if (version_compare(PHP_VERSION, '7.4.0', '<'))
        {
            flash_message("PHP version must be at least 7.4 due to security reasons.", "error");
            admin_redirect("index.php?module=config-plugins");
        }
    }

    /**
     * PluginLibrary check loader
     *
     * @return void
     */
    public static function load_pluginlibrary(): void
    {
        global $PL;

        if (!defined('PLUGINLIBRARY'))
        {
            define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');
        }

        if (file_exists(PLUGINLIBRARY))
        {
            if (!$PL)
            {
                require_once PLUGINLIBRARY;
            }
            if (version_compare((string) $PL->version, '13', '<'))
            {
                flash_message("PluginLibrary version is outdated, please update the plugin.", "error");
                admin_redirect("index.php?module=config-plugins");
            }
        }
        else
        {
            flash_message("PluginLibrary is missing.", "error");
            admin_redirect("index.php?module=config-plugins");
        }
    }

    public static function add_settings(): void
    {
        global $PL;

        $PL->settings("rt_disposablemails",
            "RT Disposable Mails Settings",
            "Setting group for the RT Disposable Mails plugin.",
            [
                "task_enabled" => [
                    "title" => "Enable task to run in background?",
                    "description" => "Task will run periodically in background via MyBB tasks to add new spam mail data.",
                    "optionscode" => "yesno",
                    "value" => 1
                ],
                "task_time" => [
                    "title" => "Time when task will run (in days)",
                    "description" => "Set a time when task will run. PS: New mails are not added frequently to the spam list, so there is no need to force small values",
                    "optionscode" => "numeric",
                    "value" => 7,
                ],
                "api_provider" => [
                    "title" => "API provider for Disposable Mails",
                    "description" => "Choose an API provider from where we will get the data:
					<br>1. <a href='https://github.com/ivolo/disposable-email-domains' target='_blank' rel='noreferrer'>Ivolo - Disposable email domains</a>
					<br>2. <a href='https://github.com/RevertIT/disposable-email-domains' target='_blank' rel='noreferrer'>RevertIT - Disposable email domains (Fork)</a>",
                    "optionscode" => "select\n1=Ivolo - Disposable email domains\n2=RevertIT - Disposable email domains (Frequent update)",
                    "value" => 2,
                ],
            ],
		);
    }

    /**
     * Delete settings
     *
     * @return void
     */
    public static function remove_settings(): void
    {
        global $PL;

        $PL->settings_delete('rt_disposablemails', true);
    }

    public static function fetch_api(): ?array
    {
        global $mybb;

        $mybb->settings['rt_disposablemails_api_provider'] = self::API_PROVIDERS[(int) $mybb->settings['rt_disposablemails_api_provider']];

        if (!empty($mybb->settings['rt_disposablemails_api_provider']))
        {
            $data = fetch_remote_file($mybb->settings['rt_disposablemails_api_provider']);

            return json_decode($data, true);
        }

        return null;
    }
}

final class RT_DisposableMails_FrontEnd
{
    public function task_hourlycleanup(&$args)
    {
        global $db, $mybb, $cache, $args;

        if (isset($mybb->settings['rt_disposablemails_task_enabled'], $mybb->settings['rt_disposablemails_task_time']) &&
            (int) $mybb->settings['rt_disposablemails_task_enabled'] === 1
        )
        {
            $rt_disposablemails_clean_time = TIME_NOW - (60 * 60 * 24 * (int) $mybb->settings['rt_disposablemails_task_time']);
            $rt_query = $db->simple_select("banfilters", "dateline", "type = '3'", [
                "order_by" => 'dateline',
                "order_dir" => 'DESC',
                "limit" => 1
            ]);
            $rt_last_entry = (int) $db->fetch_field($rt_query, 'dateline');

            // Heavy DB stress incoming, but this is the best way to make it multi-db engine compatible.
            // Finger point on you PSG and MariaDB
            if ($rt_last_entry === 0 || $rt_last_entry < $rt_disposablemails_clean_time)
            {
                $api = RT_DisposableMails::fetch_api();

                if (!empty($api))
                {
                    foreach ($api as $row)
                    {
                        $row = '@' . $row;
                        $rt_disposablemails_duplicate_check = $db->simple_select("banfilters", "filter", "type = '3' AND filter = '{$db->escape_string($row)}'", [
                            "limit" => 1
                        ]);
                        $rt_duplicate_result = '@' . $db->fetch_field($rt_disposablemails_duplicate_check, 'filter');

                        // Skip duplicate entries but still we query them above to check whether they exist, too many queries!
                        if ($rt_duplicate_result === $row)
                        {
                            continue;
                        }

                        // Insert new stuff
                        $db->insert_query('banfilters', [
                            'filter' => $row,
                            'dateline' => TIME_NOW,
                            'type' => 3,
                        ]);
                    }
                    $cache->update_bannedemails();
                }
            }
        }
    }
}

final class RT_DisposableMails_BackEnd
{
    // Nothing here
}