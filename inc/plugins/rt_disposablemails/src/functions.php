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

/**
 * Autoload plugin hooks
 *
 * @param array $class Array of classes to load for hooks
 * @return void
 */
function autoload_plugin_hooks(array $class): void
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
 * PHP version check
 *
 * @return void
 */
function check_php_version(): void
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
function load_pluginlibrary(): void
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
			flash_message("PluginLibrary version is outdated. You can update it by <a href=\"https://community.mybb.com/mods.php?action=view&pid=573\">clicking here</a>.", "error");
			admin_redirect("index.php?module=config-plugins");
		}
	}
	else
	{
		flash_message("PluginLibrary is missing. You can download it by <a href=\"https://community.mybb.com/mods.php?action=view&pid=573\">clicking here</a>.", "error");
		admin_redirect("index.php?module=config-plugins");
	}
}