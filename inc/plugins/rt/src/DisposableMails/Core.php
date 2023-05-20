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

class Core
{
    private const API_PROVIDERS = [
        1 => 'https://raw.githubusercontent.com/ivolo/disposable-email-domains/master/index.json',
        2 => 'https://raw.githubusercontent.com/RevertIT/disposable-email-domains/master/index.json'
    ];

    public static array $PLUGIN_DETAILS = [
        'name' => 'RT Disposable Mails',
        'website' => 'https://github.com/RevertIT/mybb-rt_disposablemails',
        'author' => 'RevertIT',
        'authorsite' => 'https://github.com/RevertIT/',
        'version' => '1.6',
        'compatibility' => '18*',
        'codename' => 'rt_disposablemails',
        'prefix' => 'rt_disposablemails',
    ];

    /**
     * Plugin description
     *
     * @return void
     */
    public static function set_plugin_description(): void
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
                $plugin_description = <<<DISCLAIMER
				{$plugin_description}
				{$lang->rt_disposablemails_plugin_description_disclaimer}
				DISCLAIMER;
            }
        }

        if (rt_disposablemails_is_installed() === true)
        {
            $plugin_description = $plugin_description_extra;
        }

        self::$PLUGIN_DETAILS['description'] = $plugin_description;
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

        $current = $cache->read(self::$PLUGIN_DETAILS['prefix']);

        if (!empty($current) && self::is_installed() &&
            (version_compare(self::$PLUGIN_DETAILS['version'], $current['version'], '>') || version_compare(self::$PLUGIN_DETAILS['version'], $current['version'], '<')))
        {
            return false;
        }

        return true;
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
                "log_actions" => [
                    "title" => "Log blocked actions",
                    "description" => "This option will log into database every blocked attempt",
                    "optionscode" => "yesno",
                    "value" => 1
                ],
                "disable_register" => [
                    "title" => "Prevent guests registering with banned/temporary mail",
                    "description" => "This option will prevent guests from registering with temporary mail",
                    "optionscode" => "yesno",
                    "value" => 1
                ],
                "disable_login" => [
                    "title" => "Prevent guests from logging with banned/temporary mail",
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

        if (!empty(self::$PLUGIN_DETAILS))
        {
            $cache->update(self::$PLUGIN_DETAILS['prefix'], self::$PLUGIN_DETAILS);
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

        if (!empty($cache->read(self::$PLUGIN_DETAILS['prefix'])))
        {
            $cache->delete(self::$PLUGIN_DETAILS['prefix'], true);
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
     * @return array
     */
    private static function read_cached_data(): array
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

    public static function add_database_modifications(): void
    {
        global $db;

        switch ($db->type)
        {
            case 'pgsql':
                $db->write_query("
                CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "rt_dispmail_logs (
                    id serial,
                    ip bytea NOT NULL,
                    email text NULL,
                    action int DEFAULT '0',
                    dateline int NOT NULL,
                    PRIMARY KEY (id)
                )
            ");
                break;
            case 'sqlite':
                $db->write_query("
                CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "rt_dispmail_logs (
                    id integer primary key,
                    ip bytea NOT NULL,
                    email text NULL,
                    action integer DEFAULT '0',
                    dateline integer NOT NULL,
                )
            ");
                break;
            default:
                $db->write_query("
                CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "rt_dispmail_logs (
                    id int(11) NOT NULL auto_increment,
                    ip varbinary(16) NOT NULL,
                    email text NULL,
                    action tinyint DEFAULT '0',
                    dateline int(11) NOT NULL,
                    PRIMARY KEY (id)
                );
            ");
                break;
        }
    }

    public static function remove_database_modifications(): void
    {
        global $db;

        $db->drop_table('rt_dispmail_logs');
    }

    /**
     * @param string $email
     * @param int $action 1 = Login Blocked / 2 = Register blocked
     * @return void
     */
    public static function insert_log(string $email, int $action): void
    {
        global $mybb, $db;

        if (isset($mybb->settings['rt_disposablemails_log_actions']) && (int) $mybb->settings['rt_disposablemails_log_actions'] !== 1)
        {
            return;
        }

        $mybb->binary_fields['rt_dispmail_logs'] = ['ip' => true];

        $ip = my_inet_pton(get_ip());
        $date = TIME_NOW;

        $db->insert_query('rt_dispmail_logs', [
            'ip' => $db->escape_binary($ip),
            'dateline' => $date,
            'action' => $action,
            'email' => $db->escape_string($email),
        ]);
    }
}