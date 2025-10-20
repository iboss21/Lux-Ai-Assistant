<?php
if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
$rel = substr($class, strlen($prefix));
$file = $base . 'class-' . strtolower(str_replace('\\','-',$rel)) . '.php';
if (! file_exists($file)) {
// Try nested (utils/providers)
$file = $base . strtolower(str_replace('\\','/',$rel)) . '.php';
}
if (file_exists($file)) require $file;
});


// Activation: create logs table, defaults, cron
register_activation_hook(__FILE__, function(){
global $wpdb; $charset = $wpdb->get_charset_collate();
$table = $wpdb->prefix . LUXAI_LOG_TABLE;
$sql = "CREATE TABLE IF NOT EXISTS {$table} (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
created_at DATETIME NOT NULL,
level VARCHAR(20) NOT NULL,
actor VARCHAR(60) NOT NULL,
context VARCHAR(190) NOT NULL,
message LONGTEXT NULL,
PRIMARY KEY (id),
KEY created_at (created_at),
KEY level (level),
KEY context (context)
) {$charset};";
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta($sql);


$defaults = [
'providers' => [
'openai' => ['enabled'=>false,'api_key'=>'','model'=>'gpt-4o-mini','base_url'=>'https://api.openai.com/v1'],
'anthropic' => ['enabled'=>false,'api_key'=>'','model'=>'claude-3-5-sonnet-latest','base_url'=>'https://api.anthropic.com'],
],
'assistant' => ['enable_admin'=>true,'enable_front'=>true,'front_role'=>'subscriber','max_tokens'=>1200,'temperature'=>0.2],
'audit' => ['schedule'=>'daily','auto_fix'=>false,'include_plugins'=>true,'include_themes'=>true,'seo'=>true,'accessibility'=>true,'performance'=>true,'database'=>true],
'permissions' => ['manage_cap'=>'manage_options','use_assistant_cap'=>'edit_posts'],
'privacy' => ['mask_pii'=>true,'strip_html'=>true,'log_prompts'=>false,'log_responses'=>false],
'rate_limiting' => ['per_minute'=>10,'per_hour'=>200],
'safety' => ['dry_run'=>true,'require_confirm'=>true,'allow_file_edits'=>false],
];
add_option(LUXAI_OPTION_KEY, $defaults);


if (! wp_next_scheduled('luxai/cron/audit')) {
wp_schedule_event(time()+60, 'daily', 'luxai/cron/audit');
}
});


register_deactivation_hook(__FILE__, function(){ wp_clear_scheduled_hook('luxai/cron/audit'); });


// Bootstrap
add_action('plugins_loaded', function(){
load_plugin_textdomain('luxai', false, dirname(plugin_basename(__FILE__)).'/languages');
LuxAI\Plugin::instance();
LuxAI\Admin::instance();
LuxAI\Chat::instance();
LuxAI\Audit::instance();
LuxAI\Fixer::instance();
add_action('rest_api_init', [LuxAI\Plugin::instance(),'register_rest']);
add_filter('site_status_tests', [LuxAI\Plugin::instance(),'register_site_health']);
add_action('luxai/cron/audit', [LuxAI\Audit::instance(),'run_scheduled_audit']);
});
