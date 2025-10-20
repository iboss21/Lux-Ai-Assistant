<?php
if ( ! defined('WP_UNINSTALL_PLUGIN') ) exit;
// Remove options and transient plans; keep logs by default.
delete_option('luxai_settings');
// Optionally drop logs (uncomment to enable destructive cleanup):
// global $wpdb; $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'luxai_logs');
