<?php

$l['rt_disposablemails_plugin_description'] = 'RT Disposable Mails is a plugin which checks an external API to retrieve filtered spam mails, and saves them into database periodically via tasks.
<br> For this plugins maximum caching performance it is recommended to use Memcache(d) or Redis cache handlers.';
$l['rt_disposablemails_plugin_description_extra'] = '<br><br><b><a href="index.php?module=tools-tasks&action=run&tid=1&my_post_key={1}" style="color: red">Force run plugin task</a></b> <i>(Works only if your last ban email entry was at least 1 day ago)</i> &middot;
<a href="index.php?module=config-banning&type=emails">Check banned mails</a>';
$l['rt_disposablemails_plugin_description_disclaimer'] = '<br><br><b style="color: red">DISCLAIMER: Do not deactivate/uninstall plugin while task is running. Please wait until this message disappears.</b>';
$l['rt_disposablemails_task_time_error'] = 'Invalid value for Time when task will run (in days)';
$l['rt_disposablemails_plugin_update_required'] = '<br><br><b style="color: darkorange">New update available! Please update the plugin.</b>';