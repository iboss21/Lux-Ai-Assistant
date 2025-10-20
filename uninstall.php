<?php
if ( ! defined('WP_UNINSTALL_PLUGIN') ) exit;
delete_option('luxai_settings');
// Keep logs by default.
// global $wpdb; $wpdb->query('DROP TABLE IF EXISTS '.$wpdb->prefix.'luxai_logs');
