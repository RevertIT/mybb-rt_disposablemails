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

// Autoload classes
require_once MYBB_ROOT . 'inc/plugins/rt/vendor/autoload.php';

\rt\Autoload\psr4_autoloader(
    'rt',
    'src',
    'rt\\DisposableMails\\',
    [
        'rt/DisposableMails/functions.php',
    ]
);

$hooks = [];
// Hooks manager
if (defined('IN_ADMINCP'))
{
    $hooks[] = '\rt\DisposableMails\Hooks\Backend';
}
$hooks[] = '\rt\DisposableMails\Hooks\Frontend';


// Autoload plugin hooks
\rt\DisposableMails\autoload_plugin_hooks($hooks);

function rt_disposablemails_info(): array
{
    \rt\DisposableMails\Core::set_plugin_description();

    return \rt\DisposableMails\Core::$PLUGIN_DETAILS;
}

function rt_disposablemails_install(): void
{
    \rt\DisposableMails\check_php_version();
    \rt\DisposableMails\load_pluginlibrary();

    \rt\DisposableMails\Core::add_database_modifications();
    \rt\DisposableMails\Core::add_settings();
    \rt\DisposableMails\Core::set_cache();
}

function rt_disposablemails_is_installed(): bool
{
    return \rt\DisposableMails\Core::is_installed();
}

function rt_disposablemails_uninstall(): void
{
    \rt\DisposableMails\check_php_version();
    \rt\DisposableMails\load_pluginlibrary();

    \rt\DisposableMails\Core::remove_database_modifications();
    \rt\DisposableMails\Core::remove_settings();
    \rt\DisposableMails\Core::remove_cache();
}

function rt_disposablemails_activate(): void
{
    \rt\DisposableMails\check_php_version();
    \rt\DisposableMails\load_pluginlibrary();

    \rt\DisposableMails\Core::add_settings();
    \rt\DisposableMails\Core::set_cache();
}

function rt_disposablemails_deactivate(): void
{
    \rt\DisposableMails\check_php_version();
    \rt\DisposableMails\load_pluginlibrary();
}