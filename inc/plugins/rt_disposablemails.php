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
if (!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

RT_DisposableMails::autoload_plugin_hooks([
    'RT_DisposableMails_FrontEnd',
    'RT_DisposableMails_BackEnd'
]);

function rt_disposablemails_info(): array
{
    return [
        'name' => RT_DisposableMails::get_plugin_info('name'),
        'description' => RT_DisposableMails::get_plugin_description(),
        'website' => RT_DisposableMails::get_plugin_info('website'),
        'author' => RT_DisposableMails::get_plugin_info('author'),
        'authorsite' => RT_DisposableMails::get_plugin_info('authorsite'),
        'version' => RT_DisposableMails::get_plugin_info('version'),
        'compatibility' => RT_DisposableMails::get_plugin_info('compatibility'),
        'codename' => RT_DisposableMails::get_plugin_info('codename'),
    ];
}

function rt_disposablemails_install(): void
{
    RT_DisposableMails::check_php_version();
    RT_DisposableMails::load_pluginlibrary();

    RT_DisposableMails::add_settings();
    RT_DisposableMails::set_cache();
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
    RT_DisposableMails::remove_cache();
}

function rt_disposablemails_activate(): void
{
    RT_DisposableMails::check_php_version();
    RT_DisposableMails::load_pluginlibrary();

    RT_DisposableMails::add_settings();
    RT_DisposableMails::set_cache();
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

    private const PLUGIN_DETAILS = [
        'name' => 'RT Disposable Mails',
        'website' => 'https://github.com/RevertIT/mybb-rt_disposablemails',
        'author' => 'RevertIT',
        'authorsite' => 'https://github.com/RevertIT/',
        'version' => '1.3',
        'compatibility' => '18*',
        'codename' => 'rt_disposablemails',
        'prefix' => 'rt_disposablemails',
    ];

    /**
     * Get plugin details
     *
     * @param string $info Plugin info to return
     * @return string|null
     */
    public static function get_plugin_info(string $info): ?string
    {
        if (isset(self::PLUGIN_DETAILS[$info]))
        {
            return self::PLUGIN_DETAILS[$info];
        }

        return null;
    }

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

    /**
     * Plugin description
     *
     * @return string
     */
    public static function get_plugin_description(): string
    {
        global $mybb, $db, $lang;

        $lang->load('rt_disposablemails');

        // Plugin description
        $plugin_description = <<<DESCRIPTION
		{$lang->rt_disposablemails_plugin_description}
		DESCRIPTION;

        // Check if new updates available
        if (self::is_current() !== true)
        {
            $plugin_description = <<<DESCRIPTION
            {$lang->rt_disposablemails_plugin_description}
            {$lang->rt_disposablemails_plugin_update_required}
            DESCRIPTION;
        }

        // Add plugin option links
        $plugin_description_extra = <<<OPTIONS
		{$plugin_description}
		{$lang->sprintf($lang->rt_disposablemails_plugin_description_extra, $mybb->post_code)}
		OPTIONS;

        // Check if task is active and add disclaimer
        if (isset($mybb->settings['rt_disposablemails_task_enabled']) && (int) $mybb->settings['rt_disposablemails_task_enabled'] === 1)
        {
            $query = $db->simple_select("tasks", "locked", "file = 'hourlycleanup' AND locked != '0'");

            $row = $db->fetch_field($query, 'locked');

            if (!empty($row))
            {
                return <<<DISCLAIMER
				{$plugin_description}
				{$lang->rt_disposablemails_plugin_description_disclaimer}
				DISCLAIMER;
            }
        }

        if (rt_disposablemails_is_installed() === true)
        {
            return $plugin_description_extra;
        }

        return $plugin_description;
    }

    /**
     * Check if plugin is installed
     *
     * @return bool
     */
    public static function is_installed(): bool
    {
        global $mybb;

        if (isset($mybb->settings['rt_disposablemails_api_provider']))
        {
            return true;
        }

        return false;
    }

    /**
     * Check if plugin is up-to-date
     *
     * @return bool
     */
    public static function is_current(): bool
    {
        global $cache;

        $current = $cache->read(self::PLUGIN_DETAILS['prefix']);

        if (!empty($current) && self::is_installed() && (version_compare(self::PLUGIN_DETAILS['version'], $current['version'], '>') || version_compare(self::PLUGIN_DETAILS['version'], $current['version'], '<')))
        {
            return false;
        }

        return true;
    }

    /**
     * PHP version check
     *
     * @return void
     */
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

    /**
     * Add settings
     *
     * @return void
     */
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
                "task_disableforum" => [
                    "title" => "Disable forum for users while task is running?",
                    "description" => "This option will prevent users to browse forum while task is running. It is not required as all data now is being stored in cache chunks and will finish up pretty quickly.",
                    "optionscode" => "yesno",
                    "value" => 0
                ],
                "task_time" => [
                    "title" => "Time when task will run (in days)",
                    "description" => "Set a time when task will run. 
                    <br><b style='color: red'>Notice:</b> New temporary mails are not added daily to the spam list, so there is no need to force small values.",
                    "optionscode" => "numeric",
                    "value" => 30,
                ],
                "api_provider" => [
                    "title" => "API provider for Disposable Mails",
                    "description" => "Choose an API provider from where we will get the data:
					<br>1. <a href='https://github.com/ivolo/disposable-email-domains' target='_blank' rel='noreferrer'>Ivolo - Disposable email domains</a>
					<br>2. <a href='https://github.com/RevertIT/disposable-email-domains' target='_blank' rel='noreferrer'>RevertIT - Disposable email domains (Fork)</a>",
                    "optionscode" => "select\n1=Ivolo - Disposable email domains\n2=RevertIT - Disposable email domains (Frequent update)",
                    "value" => 2,
                ],
                "disable_register" => [
                    "title" => "Prevent guests registering with banned/temporary mail",
                    "description" => "This option will prevent guests from registering with temporary mail",
                    "optionscode" => "yesno",
                    "value" => 1
                ],
                "disable_login" => [
                    "title" => "Prevent guests from logging with banned/temporary mails",
                    "description" => "This option will prevent guests from logging with temporary mail",
                    "optionscode" => "yesno",
                    "value" => 0
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

    /**
     * Set plugin cache
     *
     * @return void
     */
    public static function set_cache()
    {
        global $cache;

        if (!empty(self::PLUGIN_DETAILS))
        {
            $cache->update(self::PLUGIN_DETAILS['prefix'], self::PLUGIN_DETAILS);
        }
    }

    /**
     * Remove plugin cache
     *
     * @return void
     */
    public static function remove_cache(): void
    {
        global $cache;

        if (!empty($cache->read(self::PLUGIN_DETAILS['prefix'])))
        {
            $cache->delete(self::PLUGIN_DETAILS['prefix'], true);
        }
    }

    /**
     * Fetch api data
     *
     * @return array|null
     */
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

    /**
     * Save banlist
     *
     * Due to MyBB limitations we had to save our cache in chunks so that we could keep up the speed and avoid database errors.
     *
     * @param mixed $data
     * @return void
     */
    public static function save_mail_list(array $data): void
    {
        global $cache;

        $count_items = round(count((array) $data) / 3000);

        $cache->update('rt_disposablemails_total_chunks', [
            'cached_at' => TIME_NOW,
            'count' => $count_items,
        ]);

        $chunks = array_chunk($data, 2000);

        foreach ($chunks as $key => $chunk)
        {
            $cache->update('rt_disposablemails_chunk_' . $key, $chunk);
        }
    }

    /**
     * Read cached data in array chunks
     *
     * @return mixed
     */
    private static function read_cached_data(): mixed
    {
        global $cache;

        $chunks = $cache->read('rt_disposablemails_total_chunks');

        $emails = [];
        if (!empty($chunks['count']))
        {
            for ($chunk = 0; $chunk <= $chunks['count']; $chunk++)
            {
                $emails[] = $cache->read('rt_disposablemails_chunk_' . $chunk);
            }
        }

        return $emails;
    }

    /**
     * Check if email is banned
     *
     * @param string $email
     * @return bool
     */
    public static function is_banned_email(string $email): bool
    {
        $banned_mails = self::read_cached_data();

        if(!empty($banned_mails))
        {
            foreach((array) $banned_mails as $chunks)
            {
                foreach ($chunks as $mail)
                {
                    // Make regular expression * match
                    $mail = str_replace('\*', '(.*)', preg_quote($mail, '#'));

                    if(preg_match("#{$mail}#i", $email))
                    {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}

final class RT_DisposableMails_FrontEnd
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
                if (RT_DisposableMails::is_banned_email($mybb->get_input('email')))
                {
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
                if (RT_DisposableMails::is_banned_email($mybb->get_input('username')))
                {
                    error($lang->sprintf($lang->rt_disposablemails_prevent_login, $mybb->get_input('username')), $lang->error);
                }
            }
            elseif (!empty($mybb->get_input('quick_username')))
            {
                if (RT_DisposableMails::is_banned_email($mybb->get_input('quick_username')))
                {
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
                $api = RT_DisposableMails::fetch_api();

                if (!empty($api))
                {
                    // Set cache lock so we don't query db
                    $cache->update('rt_disposablemails_locked', 1);

                    RT_DisposableMails::save_mail_list($api);

                    $cache->delete('rt_disposablemails_locked');
                }
            }
        }
    }
}

final class RT_DisposableMails_BackEnd
{
    /**
     * Hook: admin_config_settings_change
     *
     * @return void
     */
    function admin_config_settings_change(): void
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
}
