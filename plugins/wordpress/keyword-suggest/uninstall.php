<?php
// Runs when the plugin is deleted from the Plugins screen.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
delete_option('keyword_suggest_settings');
