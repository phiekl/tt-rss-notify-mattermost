<?php

// Copyright 2023 Philip Eklöf
//
// SPDX-License-Identifier: GPL-3.0-only

class Notify_Mattermost extends Plugin
{
    private $host;

    function about()
    {
        return [
            null,
            "Notifications to Mattermost",
            "phi",
            "https://github.com/phiekl/tt-rss-notify-mattermost"
        ];
    }

    function api_version()
    {
        return 2;
    }

    function init($host)
    {
        $this->host = $host;
        $host->add_hook(PluginHost::HOOK_ARTICLE_FILTER_ACTION, $this);
        $host->add_hook(PluginHost::HOOK_PREFS_TAB, $this);
        $host->add_filter_action($this, "mattermost_notify", "Notify Mattermost");
    }

    private function _config_get()
    {
        $cfg = [];

        foreach ($this->_config_schema() as $k => $v) {
            $cfg[$k] = $this->host->get($this, $k);
            if (empty($cfg[$k])) {
                if (!empty($v["default"])) {
                    $cfg[$k] = $v["default"];
                } elseif (!empty($v["required"]) ) {
                    return null;
                } else {
                    $cfg[$k] = null;
                }
            }
        }

        return $cfg;
    }

    private function _config_schema()
    {
        $categories = $this->_sql_get_root_categories();
        if (empty($categories)) {
            $categories = [""];
        }

        return [
            "mattermost_webhook_url" => [
                "title" => "Mattermost webhook URL",
                "type" => "text",
                "default" => "",
                "placeholder" => "https://mattermost-host/hooks/hook-id...",
                "regexp" => "^https://.*/hooks/[0-9a-z]+$",
                "required" => true,
            ],
            "timezone" => [
                "title" => "Time zone",
                "description" => "Article timestamps will be converted to this time zone in outgoing messages.",
                "type" => "select",
                "default" => "UTC",
                "values" => DateTimeZone::listIdentifiers(),
            ],
            "parent_category_mode" => [
                "title" => "Parent category mode",
                "description" => "If set to <i>Enabled</i>, and the selected parent category of the feed matches, the feed's category name is used as the channel name to send notifications to. If set to <i>Disabled</i>, notifications will be sent to the default channel (as set in Mattermost). If set to <i>Forced</i>, feeds with non-matching categories won't be notified at all.",
                "type" => "select",
                "default" => "Disabled",
                "values" => ["Disabled", "Enabled", "Forced"],
            ],
            "parent_category_name" => [
                "title" => "Parent category name",
                "description" => "Only used if <i><b>Parent category mode</b></i> is set to <i>Enabled</i> or <i>Forced</i>.",
                "type" => "select",
                "values" => $categories
            ],
            "max_announce_age" => [
                "title" => "Maximum article age in days",
                "description" => "Do not send a message if the article is older than this many days.",
                "type" => "spinner",
                "default" => 7,
            ]
        ];
    }

    private function _generate_hook_prefs_tab__setting($key, $schema, $value)
    {
        if (!empty($schema["internal"])) {
            return;
        }

        $html = "";

        $id = "notify_mattermost_${key}";
        if ($value === null && !empty($schema["default"])) {
            $value = $schema["default"];
        }

        $html .= "<fieldset class='prefs'>";
        $html .= "<label for='${id}'>${schema["title"]}:</label>";

        $attributes = [];

        switch ($schema["type"]) {
        case "bool":
            $html .= \Controls\checkbox_tag($id, $value);
            break;
        case "select":
            $attributes["dojoType"] = "dijit.form.FilteringSelect";
            $html .= \Controls\select_tag($id, $value, $schema["values"], $attributes);
            break;
        case "spinner":
            if (!empty($schema["required"])) {
                $attributes["required"] = true;
            }
            $html .= \Controls\number_spinner_tag($id, $value, $attributes);
            break;
        case "text":
            $html .= "<input style='width: 500px' dojoType='dijit.form.ValidationTextBox'";
            $html .= " name='${id}'";
            if ($value !== null) {
                $html .= " value='${value}'";
            }
            if (!empty($schema["placeholder"])) {
                $html .= " placeholder='${schema["placeholder"]}'";
            } else {
                $html .= " placeholder='${schema["title"]}'";
            }
            if (!empty($schema["required"])) {
                $html .= " required='1'";
            }
            if (!empty($schema["regexp"])) {
                $html .= " regexp='${schema["regexp"]}'";
            }
            $html .= ">";
            break;
        }

        $html .= "</fieldset>";
        if (!empty($schema["description"])) {
            $html .= "<div class='help-text text-muted' style='margin-left: 320px; display: inline-block;'>";
            $html .= "<label>${schema["description"]}</label>";
            $html .= "</div>";
        }

        $html .= "<hr>";

        return $html;
    }

    private function _generate_hook_prefs_tab__settings()
    {
        $cfg = $this->_config_get();
        $html = "";
        foreach ($this->_config_schema() as $k => $v) {
            $html_setting = $this->_generate_hook_prefs_tab__setting(
                $k, $v, isset($cfg[$k]) ? $cfg[$k] : null
            );
            if (!empty($html_setting)) {
                $html .= $html_setting;
            }
        }
        return $html;
    }

    private function _generate_hook_prefs_tab()
    {
        $title = \Controls\icon("chat") . __("Mattermost Notification Settings");

        $html = [
            "<div dojoType='dijit.layout.AccordionPane' style='padding: 0' title='${title}'>",
            "<form dojoType='dijit.form.Form'>",
            \Controls\pluginhandler_tags($this, "settings_save"),
            '<script type="dojo/method" event="onSubmit" args="evt">
               evt.preventDefault();
               if (this.validate()) {
                   Notify.progress("Saving...", true);
                       xhr.post("backend.php", this.getValues(), (reply) => {
                           Notify.info(reply);
                       })
               }
            </script>
            ',
            "<div dojoType='dijit.layout.BorderContainer' gutters='false'>",
            "<div dojoType='dijit.layout.ContentPane' region='center' style='overflow-y : auto'>",
            "<h2>",
            __("Mattermost Notification Settings"),
            "</h2>",
            "<hr>",
            $this->_generate_hook_prefs_tab__settings(),
            "<br />",
            "<button dojoType='dijit.form.Button' type='submit' class='alt-primary'>",
            \Controls\icon("save") . __("Save"),
            "</button>",
            "<button dojoType='dijit.form.Button' onclick='return Plugins.Notify_Mattermost.SendTestNotification()'>",
            \Controls\icon("chat") . __("Send test notification"),
            "</button>",
            "<button dojoType='dijit.form.Button' onclick='return Plugins.Notify_Mattermost.ResetPluginSettings()' class='alt-danger'>",
            \Controls\icon("clear") . __("Reset to defaults"),
            "</button>",
            "<p>",
            __("To trigger notifications, make sure to add one or more filters that invokes the plugin."),
            "</p>",
            "</div>",
            "</div>",
            "</form>",
            "</div>",
        ];

        return implode("\n", $html);
    }

    private function _get_feed_notification_channel($cfg, $feed_id)
    {
        if ($cfg["parent_category_mode"] == "Disabled") {
            return null;
        }
        if (empty($cfg["parent_category_name"])) {
            return null;
        }

        // Fetch the feed category and its parent category (if any).
        $res = $this->_sql_get_category_name_by_feed_id($feed_id);

        if (empty($res["parent_category"])) {
            if ($cfg["parent_category_mode"] == "Forced") {
                $feed_title = $this->_sql_get_feed_title_by_feed_id($feed_id);
                throw new Exception("Parent category mode is forced, and no parent category found for feed '${feed_title}' with id '${feed_id}'.");
            }
            return null;
        }

        if ($res["parent_category"] != $cfg["parent_category_name"]) {
            if ($cfg["parent_category_mode"] == "Forced") {
                $feed_title = $this->_sql_get_feed_title_by_feed_id($feed_id);
                throw new Exception("Parent category mode is forced, and parent category for feed '{$feed_title}' with id '${feed_id}' is not matching.");
            }
            return null;
        }

        return $res["category"];
    }

    private function _get_timestamp_human($ts = null)
    {
        $cfg = $this->_config_get();
        $dt = new DateTime("now", new DateTimeZone($cfg["timezone"]));
        if ($ts) {
            $dt->setTimeStamp($ts);
        }
        return $dt->format("Y-m-d H:i:s O");
    }

    private function _hook_article_filter_action($article)
    {
        $log_prefix = "notify_mattermost.hook_article_filter_action(article_guid=${article["guid"]}): ";

        $cfg = $this->_config_get();
        if (empty($cfg["mattermost_webhook_url"])) {
            error_log("${log_prefix}Mattermost webhook URL not configured.");
            return $article;
        }

        if (empty($article["link"])) {
            error_log("${log_prefix}No link found for article.");
            return $article;
        }
        if (!preg_match("|^https?://|", $article["link"])) {
            error_log("${log_prefix}Invalid article link: ${article["link"]}");
            return $article;
        }
        $article_link = $article["link"];

        $feed_id = $article["feed"]["id"];
        $owner_id = $article["owner_uid"];
        $log_prefix = "notify_mattermost.hook_article_filter_action";
        $log_prefix .= "(owner_id=${owner_id}, feed_id=${feed_id}, article_link=${article_link}): ";

        // The article link column in the the mattermost_notifications db table is VARCHAR(768).
        if (strlen($article_link) > 768) {
            error_log("${log_prefix}Article link > 768 characters, ignoring.");
            return $article;
        }

        // Avoid re-renotifying already notified articles.
        if ($this->_sql_get_notification_count($owner_id, $feed_id, $article_link) > 0) {
            error_log("${log_prefix}Article has already been notified.");
            return $article;
        }

        // Ignore articles that are too old.
        $article_age = time() - $article["timestamp"];
        $article_max_age = ($cfg["max_announce_age"] * 3600 * 24);
        if ($article_age > $article_max_age) {
            error_log("${log_prefix}Article too old: ${article_age}s > ${article_max_age}s");
            return $article;
        }

        // Fetch the feed title.
        $feed_title = $this->_sql_get_feed_title_by_feed_id($feed_id);
        if (empty($feed_title)) {
            error_log("${log_prefix}Unable to find title of feed with id '${feed_id}'.");
            return $article;
        }

        // Try to set the channel name if parent category mode is enabled.
        $channel = null;
        try {
            $channel = $this->_get_feed_notification_channel($cfg, $feed_id);
        } catch (Exception $e) {
            error_log($log_prefix . $e->getMessage());
            return $article;
        }

        $title = "";
        if (!empty($article["title"])) {
            $title = trim(html_entity_decode(strip_tags($article["title"])));
            if (strlen($title) > 256) {
                $title = substr($title, 0, 256) . "...";
            }
        }

        // If the article is missing a title, tt-rss sets the title to the
        // article timestamp, but the timestamp is already handled below.
        if (preg_match('/^2[0-9]{3}-[0-1]{2}-[0-3][0-9]$/', $title)) {
            $title = "";
        }

        $msg = [];

        // The earlier article age check makes sure that a proper timestamp is set.
        $ts = $this->_get_timestamp_human($article["timestamp"]);
        $site_url = $article["feed"]["site_url"];
        $msg[] = "**[${feed_title}](${site_url})** *${ts}*";

        if (!empty($title)) {
            $msg[] = "> ${title}";
        }

        $msg[] = $article_link;

        try {
            $res = $this->_send_notification(
                $cfg["mattermost_webhook_url"], $channel, trim(implode("\n\n", $msg))
            );
        } catch (Exception $e) {
            error_log("${log_prefix}Failed sending notification: ${res}");
            return $article;
        }

        try {
            $this->_sql_insert_notification($owner_id, $feed_id, $article_link);
        } catch (Exception $e) {
            error_log("${log_prefix}Failed marking article as notified:\n${e}");
        }

        return $article;
    }

    private function _send_notification($url, $channel, $msg)
    {
        if (!function_exists("curl_init")) {
            throw new Exception("curl_init() not found.");
        }

        $data = ["text" => $msg];
        if (!empty($channel)) {
            $data["channel"] = $channel;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);

        $res = curl_exec($ch);

        $curl_error_msg = null;
        if (curl_errno($ch)) {
            $curl_error_msg = curl_error($ch);
        } else {
            $info = curl_getinfo($ch);
        }

        curl_close($ch);

        if ($curl_error_msg) {
            throw new Exception("cURL error: ${curl_error_msg}");
        }

        $headers_tmp = explode("\r\n", trim(substr($res, 0, $info["header_size"])));
        $headers = [];
        foreach ($headers_tmp as $v) {
            $parts = explode(":", $v, 2);
            if (count($parts) === 2) {
                $headers[trim($parts[0])] = trim($parts[1]);
            }
        }
        $body = substr($res, $info["header_size"]);

        if (strlen($body) > 512) {
            $body = substr($res, 0, 512) . "... **TRUNCATED**";
        }

        if ($info["http_code"] === 200) {
            if ($body === "ok") {
                return null;
            }
            throw new Exception("Received HTTP 200, but expected response data 'ok' rather than: ${body}");
        }

        if (!$retry_default_channel
            && $info["http_code"] === 404
            && !empty($headers["content-type"])
            && $headers["content-type"] === "application/json"
        ) {
            $body_decoded = json_decode($body, true);
            if (!empty($body_decoded["id"])
                && $body_decoded["id"] === "web.incoming_webhook.channel.app_error"
            ) {
                // Retry send with default channel.
                $msg .= "\n\n**WARNING:** This message should have been sent to channel *${channel}*, which could not be found.";
                return $this->_send_notification($url, null, $msg);
            }
        }

        throw new Exception("Received HTTP ${info["http_code"]}: ${body}");
    }

    private function _sql_create_table_notifications()
    {
        $sth = $this->pdo->prepare(
            "
            SELECT TABLE_COLLATION FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
        "
        );
        $sth->execute(["ttrss_users"]);
        $res = $sth->fetch(PDO::FETCH_ASSOC);

        $sth = $this->pdo->prepare(
            "
            CREATE TABLE IF NOT EXISTS mattermost_notifications (
                id INT NOT NULL AUTO_INCREMENT,
                timestamp TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                owner_id INT NOT NULL,
                feed_id INT NOT NULL,
                article_link VARCHAR(768) NOT NULL,

                PRIMARY KEY (id),

                FOREIGN KEY (owner_id)
                    REFERENCES ttrss_users (`id`)
                    ON DELETE CASCADE,

                FOREIGN KEY (feed_id)
                    REFERENCES ttrss_feeds (`id`)
                    ON DELETE CASCADE,

                UNIQUE (owner_id, feed_id, article_link)
            )
            COLLATE ?;
        "
        );
        $sth->execute([$res['TABLE_COLLATION']]);
    }

    private function _sql_get_category_name_by_feed_id($feed_id)
    {
        $sth = $this->pdo->prepare(
            "
            SELECT
                c1.title AS category,
                c2.title AS parent_category
            FROM ttrss_feeds AS f
            LEFT JOIN ttrss_feed_categories AS c1 ON f.cat_id=c1.id
            LEFT JOIN ttrss_feed_categories AS c2 ON c1.parent_cat=c2.id
            WHERE f.id = ?;
        "
        );
        $sth->execute([$feed_id]);
        return $sth->fetch(PDO::FETCH_ASSOC);
    }

    private function _sql_get_feed_title_by_feed_id($feed_id)
    {
        $sth = $this->pdo->prepare(
            "SELECT title FROM ttrss_feeds WHERE id = ?"
        );
        $sth->execute([$feed_id]);
        $res = $sth->fetch(PDO::FETCH_ASSOC);
        return $res["title"];
    }

    private function _sql_get_notification_count($owner_id, $feed_id, $article_link)
    {
        $sth = $this->pdo->prepare(
            "
            SELECT COUNT(*) AS count FROM mattermost_notifications
            WHERE owner_id = ? AND feed_id = ? AND article_link = ?
            "
        );
        $sth->execute([$owner_id, $feed_id, $article_link]);
        $line = $sth->fetch(PDO::FETCH_ASSOC);
        return $line["count"];
    }

    private function _sql_get_root_categories()
    {
        $sth = $this->pdo->prepare(
            "SELECT title FROM ttrss_feed_categories WHERE parent_cat IS NULL"
        );
        $sth->execute();
        return $sth->fetchAll(PDO::FETCH_COLUMN);
    }

    private function _sql_get_user_name_by_user_id($user_id)
    {
        $sth = $this->pdo->prepare(
            "SELECT login FROM ttrss_users WHERE id = ?"
        );
        $sth->execute([$user_id]);
        $res = $sth->fetch(PDO::FETCH_ASSOC);
        if (empty($res["login"])) {
            return null;
        }
        return $res["login"];
    }

    private function _sql_insert_notification($owner_id, $feed_id, $article_link)
    {
        $sth = $this->pdo->prepare(
            "INSERT INTO mattermost_notifications (owner_id, feed_id, article_link) VALUES (?, ?, ?)"
        );
        $sth->execute([$owner_id, $feed_id, $article_link]);
    }

    function get_prefs_js()
    {
        return '
            Plugins.Notify_Mattermost = {
                ResetPluginSettings: function() {
                    if (confirm(__("About to reset all Mattermost Notifications settings to their default values. Continue?"))) {
                        Notify.progress("Resetting plugin settings...");

                        xhr.post("backend.php", App.getPhArgs("Notify_Mattermost", "settings_reset"), (reply) => {
                            Notify.info(reply);
                            setTimeout(function () {
                                window.location.reload();
                            }, 1000);
                        });
                    }
                },
                SendTestNotification: function() {
                    Notify.progress("Sending test notification...");

                    xhr.post("backend.php", App.getPhArgs("Notify_Mattermost", "settings_send_test_notification"), (reply) => {
                        Notify.info(reply);
                    });
                }
            }
        ';
    }

    function hook_article_filter_action($article, $action)
    {
        if ($action == "mattermost_notify") {
            return $this->_hook_article_filter_action($article);
        }
    }

    function hook_prefs_tab($args)
    {
        if ($args == "prefPrefs") {
            echo $this->_generate_hook_prefs_tab();
        }
    }

    function settings_reset()
    {
        $this->host->clear_data($this);
        echo "Cleared plugin settings.";
    }

    function settings_save()
    {
        try {
            $this->_sql_create_table_notifications();
        } catch (Exception $e) {
            error_log("notify_mattermost.settings_save(): Failed creating database table:\n${e}");
            echo "Failed creating database table. See error log.";
            return;
        }

        $settings = [];
        $schema = $this->_config_schema();

        foreach ($schema as $k => $v) {
            $post_k = "notify_mattermost_${k}";
            if (!isset($_POST[$post_k])) {
                continue;
            }

            $post_v = $_POST[$post_k];

            switch ($v["type"]) {
            case "bool":
                if (!preg_match("/^[01]$/", $post_v)) {
                    echo "Invalid bool value for '${v["title"]}'.";
                    return;
                }
                $post_v = boolval($post_v);
                break;
            case "select":
                if (!in_array($post_v, $v["values"])) {
                    echo "Invalid selection for '${v["title"]}'.";
                    return;
                }
                break;
            case "spinner":
                if (!preg_match("/^[1-9][0-9]*$/", $post_v)) {
                    echo "Invalid non-positive number for '${v["title"]}'.";
                    return;
                }
                $v["integer"] = true;
                break;
            case "text":
                if (!empty($v["regexp"]) && !preg_match("|${v["regexp"]}|", $post_v)) {
                    echo "Regexp '${v["regex"]}' not matching value for '${v["title"]}'.";
                    return;
                }
                break;
            }

            if (!empty($v["integer"])) {
                $post_v = intval($post_v);
            }

            $settings[$k] = $post_v;
        }

        if ($settings["parent_category_mode"] != "Disabled") {
            if (empty($settings["parent_category_name"])) {
                $title_name = $schema["parent_category_name"]["title"];
                $title_mode = $schema["parent_category_mode"]["title"];
                echo "<i><b>${title_name}</b></i> required when <i><b>${title_mode}</b></i> is set to <i>${settings["parent_category_mode"]}</i>.";
                return;
            }
        }

        foreach ($settings as $k => $v) {
            $this->host->set($this, $k, $v);
        }
        echo "Settings saved.";
    }

    function settings_send_test_notification()
    {
        $cfg = $this->_config_get();

        if (empty($cfg["mattermost_webhook_url"])) {
            echo "<b>ERROR:</b> Mattermost webhook URL not configured.";
            return;
        }
        $url = $cfg['mattermost_webhook_url'];

        $user = $this->_sql_get_user_name_by_user_id($this->host->get_owner_uid());
        $ts = $this->_get_timestamp_human();

        try {
            $this->_send_notification($url, null, "**Tiny Tiny RSS**: *${user}* tested connectivity at *${ts}*.");
        } catch (Exception $e) {
            error_log("notify_mattermost.settings_send_test_notification(): Failed sending test message: ${e->getMessage}");
            $errmsg = html_entity_decode(strip_tags($e-getMessage));
            echo "<b>ERROR:</b> Failed sending test message: <i>${errmsg}</i>";
            return;
        }

        echo "Successfully sent test message.";
        return;
    }
}
