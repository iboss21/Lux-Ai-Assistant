<?php
/*
Plugin Name: Lux AI Assistant & Auto-Fixer (ChatGPT + Claude)
Plugin URI: https://example.com/lux-ai-assistant
Description: AI assistant with audits, broken-link crawler, and safe auto-fixes. Integrates OpenAI & Anthropic providers, Gutenberg block, REST/CLI, Site Health, rate limiting, circuit breaker, and cost guardrail.
Version: 21.0.0
Author: The Lux Empire
Text Domain: luxai
License: GPLv2 or later
*/

if ( ! defined('ABSPATH') ) exit;

define('LUXAI_VERSION', '21.0.0');
define('LUXAI_FILE', __FILE__);
define('LUXAI_DIR', plugin_dir_path(__FILE__));
define('LUXAI_URL', plugin_dir_url(__FILE__));
define('LUXAI_OPTION_KEY', 'luxai_settings');
define('LUXAI_LOG_TABLE', 'luxai_logs');

// Autoloader
spl_autoload_register(function($class){
    $prefix = 'LuxAI\\';
    $base = LUXAI_DIR . 'includes/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $rel = substr($class, strlen($prefix));
    $file = $base . 'class-' . strtolower(str_replace('\\','-',$rel)) . '.php';
    if (!file_exists($file)) $file = $base . strtolower(str_replace('\\','/',$rel)) . '.php';
    if (file_exists($file)) require $file;
});

// Activation
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
    require_once ABSPATH . 'wp-admin/includes/upgrade.php'; dbDelta($sql);

    $defaults = [
        'providers' => [
            'openai'    => ['enabled'=>false,'api_key'=>'','model'=>'gpt-4o-mini','base_url'=>'https://api.openai.com/v1','cost_per_1k'=>0.15],
            'anthropic' => ['enabled'=>false,'api_key'=>'','model'=>'claude-3-5-sonnet-latest','base_url'=>'https://api.anthropic.com','cost_per_1k'=>0.20],
        ],
        'assistant' => ['enable_admin'=>true,'enable_front'=>true,'front_role'=>'subscriber','max_tokens'=>1200,'temperature'=>0.2],
        'audit' => ['schedule'=>'daily','auto_fix'=>false,'include_plugins'=>true,'include_themes'=>true,'seo'=>true,'accessibility'=>true,'performance'=>true,'database'=>true,'crawler'=>true],
        'permissions' => ['manage_cap'=>'manage_options','use_assistant_cap'=>'edit_posts'],
        'privacy' => ['mask_pii'=>true,'strip_html'=>true,'log_prompts'=>false,'log_responses'=>false],
        'rate_limiting' => ['per_minute'=>10,'per_hour'=>200],
        'safety' => ['dry_run'=>true,'require_confirm'=>true,'allow_file_edits'=>false],
        'cost_guardrail' => ['daily_usd_cap'=>3.00, 'enabled'=>true]
    ];
    add_option(LUXAI_OPTION_KEY, $defaults);

    if (! wp_next_scheduled('luxai/cron/audit')) wp_schedule_event(time()+60, 'daily', 'luxai/cron/audit');
    if (! wp_next_scheduled('luxai/cron/crawl')) wp_schedule_event(time()+120, 'daily', 'luxai/cron/crawl');
});

register_deactivation_hook(__FILE__, function(){
    wp_clear_scheduled_hook('luxai/cron/audit');
    wp_clear_scheduled_hook('luxai/cron/crawl');
});

// Bootstrap
add_action('plugins_loaded', function(){
    LuxAI\Plugin::instance();
    LuxAI\Admin::instance();
    LuxAI\Chat::instance();
    LuxAI\Audit::instance();
    LuxAI\Fixer::instance();
    LuxAI\Crawler::instance();
    add_action('rest_api_init', [LuxAI\Plugin::instance(),'register_rest']);
    add_filter('site_status_tests', [LuxAI\Plugin::instance(),'register_site_health']);
    add_action('luxai/cron/audit', [LuxAI\Audit::instance(),'run_scheduled_audit']);
    add_action('luxai/cron/crawl', [LuxAI\Crawler::instance(),'run_scheduled_crawl']);
    add_action('init', function(){ LuxAI\Admin::register_block(); });
});

// WP-CLI
if (defined('WP_CLI') && WP_CLI){
    \WP_CLI::add_command('luxai', function($args,$assoc){
        $sub = $args[0]??'help';
        switch($sub){
            case 'audit': $rep=LuxAI\Audit::instance()->run(); \WP_CLI::success('Issues: '.count($rep['issues'])); \WP_CLI::log(json_encode($rep, JSON_PRETTY_PRINT)); break;
            case 'fix': $plan=$assoc['plan']??null; if(!$plan){ \WP_CLI::error('--plan required'); } $req=new \WP_REST_Request('POST','/luxai/v1/fix/apply'); $req->set_param('plan_id',$plan); $res=LuxAI\Fixer::instance()->rest_apply($req); \WP_CLI::log(json_encode($res, JSON_PRETTY_PRINT)); break;
            case 'crawl': $r=LuxAI\Crawler::instance()->run(['limit'=>200]); \WP_CLI::success('Crawl complete: checked '.$r['checked'].' links, broken '.$r['broken']); \WP_CLI::log(json_encode($r, JSON_PRETTY_PRINT)); break;
            default: \WP_CLI::log('Usage: wp luxai audit | wp luxai fix --plan=<id> | wp luxai crawl');
        }
    });
}
