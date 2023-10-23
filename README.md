# Tiny Tiny RSS - Notify Mattermost

A plugin for [Tiny Tiny RSS](https://tt-rss.org/) that sends notifications to a [Mattermost](https://mattermost.com/) server upon fetching new RSS feed articles.

## Installation

1. Create the directory `notify_mattermost` in the `plugins.local` directory of tt-rss, and add `init.php` from this repo into the directory.
2. Add `notify_mattermost` to `TTRSS_PLUGINS` in `config.php` (comma separated value).
3. Make sure the plugin is enabled in Preferences => Plugins.
4. Configure the Mattermost webhook URL via Preferences => Mattermost Notification Settings.
5. Add a filter that invokes the plugin. To make all feeds notify, add a filter that matches anything, such as regex "." on Title, and then add an "Invoke plugin" action.
