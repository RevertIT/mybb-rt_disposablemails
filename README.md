## RT Disposable Mails
RT Disposable Mails is a plugin which checks an external API to retrieve filtered spam mails, and saves them into database periodically via tasks. it follows the strict type declaration and is future-proof for upcoming PHP versions.

### Table of contents

1. [❗ Dependencies](#-dependencies)
2. [📃 Features](#-features)
3. [➕ Installation](#-installation)
4. [🔼 Update](#-update)
5. [➖ Removal](#-removal)
6. [💡 Feature request](#-feature-request)
7. [🙏 Questions](#-questions)
8. [🐞 Bug reports](#-bug-reports)
9. [📷 Preview](#-preview)

### ❗ Dependencies
- MyBB 1.8.x
- https://github.com/frostschutz/MyBB-PluginLibrary (>= 13)
- PHP >= 7.4.0 (Preferred 8.0 or above)

### 📃 Features
- Using strict typing declaration.
- Select API provider to use for Disposable/Spam mails source
- Set time when task will run (in days)
- Option to disable forum for users while task is running.
- Safety checks will be shown while task is running to let you know not to uninstall/deactivate plugin.

### ➕ Installation
1. Copy the directories from the plugin inside your root MyBB installation.
2. Settings for the plugin are located in the "Plugin Settings" tab. (`/admin/index.php?module=config-settings`)

### 🔼 Update
1. Deactivate the plugin.
2. Replace the plugin files with the new files.
3. Activate the plugin again.

### ➖ Removal
1. Uninstall the plugin from your plugin manager.
2. _Optional:_ Delete all the RT Disposable Mails plugin files from your MyBB folder.

### 💡 Feature request
Open a new idea by [clicking here](https://github.com/RevertIT/mybb-rt_disposablemails/discussions/new?category=ideas)

### 🙏 Questions
Open a new question by [clicking here](https://github.com/RevertIT/mybb-rt_disposablemails/discussions/new?category=q-a)

### 🐞 Bug reports
Open a new bug report by [clicking here](https://github.com/RevertIT/mybb-rt_disposablemails/issues/new)

### 📷 Preview
<img src="https://i.postimg.cc/yY85mcQv/rt1.png" alt="ss1"/>
<img src="https://i.postimg.cc/m2pn8kKR/rt2.png" alt="ss2"/>
