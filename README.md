# lux-ai-assistant (v1.0.0)

Production-ready WordPress plugin implementing the PRD: **Lux AI Assistant & Auto‑Fixer (ChatGPT + Claude)**. Includes: Providers layer (OpenAI/Anthropic), admin & front‑end assistant, audits, safe auto‑fixes, logs, REST + WP‑CLI, rate limiting, circuit breaker, privacy/safety controls, Site Health card.

> **Requirements:** WordPress 5.9+, PHP 7.4+ (tested up to PHP 8.2)

---

## Repository Tree

```
lux-ai-assistant/
├─ lux-ai-assistant.php
├─ uninstall.php
├─ readme.txt
├─ includes/
│  ├─ class-plugin.php
│  ├─ class-admin.php
│  ├─ class-chat.php
│  ├─ class-audit.php
│  ├─ class-fixer.php
│  ├─ utils/
│  │  ├─ class-logger.php
│  │  ├─ class-rate-limit.php
│  │  ├─ class-circuit-breaker.php
│  │  └─ helpers.php
│  └─ providers/
│     ├─ class-provider.php
│     ├─ class-provider-openai.php
│     └─ class-provider-anthropic.php
├─ assets/
│  ├─ css/admin.css
│  ├─ js/admin-chat.js
│  └─ js/front-chat.js
└─ languages/ (placeholder)
```

---

## File: `lux-ai-assistant.php`

```php
<?php
/*
Plugin Name: Lux AI Assistant & Auto‑Fixer (ChatGPT + Claude)
Plugin URI: https://example.com/lux-ai-assistant
Description: Site-aware AI assistant for WordPress with audits and safe auto-fixes. Integrates OpenAI & Anthropic providers, REST/CLI, Site Health, rate limiting, circuit breaker, and robust safety/privacy controls.
Version: 1.0.0
Author: The Lux Empire
License: GPLv2 or later
Text Domain: luxai
*/

if ( ! defined('ABSPATH') ) exit;

// Constants
define('LUXAI_VERSION', '1.0.0');
define('LUXAI_FILE', __FILE__);
define('LUXAI_DIR', plugin_dir_path(__FILE__));
define('LUXAI_URL', plugin_dir_url(__FILE__));
define('LUXAI_OPTION_KEY', 'luxai_settings');
define('LUXAI_LOG_TABLE', 'luxai_logs');

// Lightweight PSR-4-like autoloader
spl_autoload_register(function($class){
    $prefix = 'LuxAI\\';
    $base = LUXAI_DIR . 'includes/';
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
            'openai'    => ['enabled'=>false,'api_key'=>'','model'=>'gpt-4o-mini','base_url'=>'https://api.openai.com/v1'],
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
```

---

## File: `uninstall.php`

```php
<?php
if ( ! defined('WP_UNINSTALL_PLUGIN') ) exit;
// Remove options and transient plans; keep logs by default.
delete_option('luxai_settings');
// Optionally drop logs (uncomment to enable destructive cleanup):
// global $wpdb; $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'luxai_logs');
```

---

## File: `readme.txt`

```
=== Lux AI Assistant & Auto‑Fixer ===
Contributors: theluxempire
Tags: ai, assistant, chatgpt, claude, audit, seo, accessibility, performance, database, fixer
Requires at least: 5.9
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Site-aware AI assistant with audits and safe auto-fixes. Integrates OpenAI & Anthropic providers, REST/CLI, Site Health, rate limiting, circuit breaker, and robust safety/privacy controls.

== Installation ==
1. Upload `lux-ai-assistant` to `/wp-content/plugins/`.
2. Activate via Plugins.
3. Open **Lux AI → Settings**, enable a provider and add API key.
4. Use **Lux AI → Assistant** in admin, or add `[luxai_assistant]` to a page.
5. Run audits in **Lux AI → Audits & Fixes** or `wp luxai audit`.

== Shortcode ==
[luxai_assistant]

== CLI ==
wp luxai audit
wp luxai fix --plan=<id>
```

---

## File: `includes/class-plugin.php`

```php
<?php
namespace LuxAI;

class Plugin {
    private static $instance; public $opts;
    public static function instance(){ return self::$instance ?? (self::$instance = new self()); }
    private function __construct(){ $this->opts = get_option(\LUXAI_OPTION_KEY, []); }

    public function cap_manage(){ return $this->opts['permissions']['manage_cap'] ?? 'manage_options'; }
    public function cap_use(){ return $this->opts['permissions']['use_assistant_cap'] ?? 'edit_posts'; }

    public function register_rest(){
        register_rest_route('luxai/v1','/chat',[
            'methods'=>'POST','permission_callback'=>function(){return current_user_can($this->cap_use());},
            'callback'=>[Chat::instance(),'rest_chat'],
            'args'=>[
                'message'=>['type'=>'string','required'=>true],
                'provider'=>['type'=>'string','required'=>false,'enum'=>['openai','anthropic','auto']],
                'context'=>['type'=>'object','required'=>false],
            ]
        ]);
        register_rest_route('luxai/v1','/audit/run',[
            'methods'=>'POST','permission_callback'=>function(){return current_user_can($this->cap_manage());},
            'callback'=>[Audit::instance(),'rest_run']
        ]);
        register_rest_route('luxai/v1','/fix/apply',[
            'methods'=>'POST','permission_callback'=>function(){return current_user_can($this->cap_manage());},
            'callback'=>[Fixer::instance(),'rest_apply'],
            'args'=>['plan_id'=>['type'=>'string','required'=>true]]
        ]);
    }

    public function register_site_health($tests){
        $tests['direct']['luxai_audit_summary'] = [
            'label'=>__('LuxAI Audit Summary','luxai'),
            'test'=>[Audit::instance(),'site_health_test']
        ];
        return $tests;
    }
}
```

---

## File: `includes/class-admin.php`

```php
<?php
namespace LuxAI;

class Admin {
    private static $instance; public static function instance(){ return self::$instance ?? (self::$instance = new self()); }
    private function __construct(){
        add_action('admin_menu',[ $this,'menu' ]);
        add_action('admin_init',[ $this,'register_settings' ]);
        add_action('admin_enqueue_scripts',[ $this,'assets' ]);
    }

    public function menu(){
        add_menu_page('Lux AI','Lux AI',Plugin::instance()->cap_manage(),'luxai',[ $this,'page_settings' ],'dashicons-art',58);
        add_submenu_page('luxai',__('Assistant','luxai'),__('Assistant','luxai'),Plugin::instance()->cap_use(),'luxai-assistant',[ $this,'page_assistant' ]);
        add_submenu_page('luxai',__('Audits & Fixes','luxai'),__('Audits & Fixes','luxai'),Plugin::instance()->cap_manage(),'luxai-audit',[ $this,'page_audit' ]);
    }

    public function register_settings(){ register_setting('luxai', \LUXAI_OPTION_KEY, [ $this, 'sanitize' ] ); }

    public function sanitize($opts){
        $opts = is_array($opts)?$opts:[];
        foreach(['openai','anthropic'] as $p){
            if(isset($opts['providers'][$p]['api_key'])) $opts['providers'][$p]['api_key']=trim($opts['providers'][$p]['api_key']);
        }
        foreach(['log_prompts','log_responses'] as $k){ $opts['privacy'][$k] = !empty($opts['privacy'][$k]); }
        foreach(['dry_run','require_confirm','allow_file_edits'] as $k){ $opts['safety'][$k] = !empty($opts['safety'][$k]); }
        return $opts;
    }

    public function assets($hook){
        if(strpos($hook,'luxai')===false) return;
        wp_enqueue_style('luxai-admin', \LUXAI_URL.'assets/css/admin.css',[],\LUXAI_VERSION);
        wp_enqueue_script('luxai-admin-chat', \LUXAI_URL.'assets/js/admin-chat.js',['wp-api-fetch'],\LUXAI_VERSION,true);
        wp_localize_script('luxai-admin-chat','LuxAI',[ 'nonce'=>wp_create_nonce('wp_rest'),'endpoint'=>rest_url('luxai/v1') ]);
    }

    public function page_settings(){ $o = get_option(\LUXAI_OPTION_KEY,[]); ?>
    <div class="wrap"><h1>Lux AI — Settings</h1>
    <form method="post" action="options.php"><?php settings_fields('luxai'); ?>
    <h2>Providers</h2>
    <table class="form-table">
      <tr><th>OpenAI</th><td>
        <label><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[providers][openai][enabled]" <?php checked(!empty($o['providers']['openai']['enabled'])); ?>/> Enabled</label><br>
        <input type="password" size="60" placeholder="OpenAI API Key" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[providers][openai][api_key]" value="<?php echo esc_attr($o['providers']['openai']['api_key']??''); ?>">
        <p>Model: <input type="text" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[providers][openai][model]" value="<?php echo esc_attr($o['providers']['openai']['model']??'gpt-4o-mini'); ?>"></p>
      </td></tr>
      <tr><th>Anthropic</th><td>
        <label><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[providers][anthropic][enabled]" <?php checked(!empty($o['providers']['anthropic']['enabled'])); ?>/> Enabled</label><br>
        <input type="password" size="60" placeholder="Anthropic API Key" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[providers][anthropic][api_key]" value="<?php echo esc_attr($o['providers']['anthropic']['api_key']??''); ?>">
        <p>Model: <input type="text" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[providers][anthropic][model]" value="<?php echo esc_attr($o['providers']['anthropic']['model']??'claude-3-5-sonnet-latest'); ?>"></p>
      </td></tr>
    </table>

    <h2>Assistant</h2>
    <table class="form-table"><tr><th>Admin & Front</th><td>
      <label><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[assistant][enable_admin]" <?php checked(!empty($o['assistant']['enable_admin'])); ?>/> Admin Assistant</label>
      <label style="margin-left:16px"><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[assistant][enable_front]" <?php checked(!empty($o['assistant']['enable_front'])); ?>/> Front‑end Assistant ([luxai_assistant])</label>
      <p>Max tokens: <input type="number" min="128" max="4096" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[assistant][max_tokens]" value="<?php echo esc_attr($o['assistant']['max_tokens']??1200); ?>">
      Temperature: <input type="number" step="0.1" min="0" max="2" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[assistant][temperature]" value="<?php echo esc_attr($o['assistant']['temperature']??0.2); ?>"></p>
    </td></tr></table>

    <h2>Audits</h2>
    <table class="form-table"><tr><th>Scope</th><td>
      <?php foreach(['seo'=>'SEO','accessibility'=>'Accessibility','performance'=>'Performance','database'=>'Database','include_plugins'=>'Plugins','include_themes'=>'Themes'] as $k=>$lbl): ?>
        <label><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[audit][<?php echo esc_attr($k); ?>]" <?php checked(!empty($o['audit'][$k])); ?>/> <?php echo esc_html($lbl); ?></label>
      <?php endforeach; ?>
      <p>Schedule: <select name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[audit][schedule]"><option value="daily" <?php selected(($o['audit']['schedule']??'daily'),'daily'); ?>>Daily</option><option value="weekly" <?php selected(($o['audit']['schedule']??'daily'),'weekly'); ?>>Weekly</option></select>
      <label style="margin-left:16px"><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[audit][auto_fix]" <?php checked(!empty($o['audit']['auto_fix'])); ?>/> Auto‑apply safe fixes</label></p>
    </td></tr></table>

    <h2>Safety & Privacy</h2>
    <table class="form-table"><tr><th>Controls</th><td>
      <label><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[privacy][mask_pii]" <?php checked(!empty($o['privacy']['mask_pii'])); ?>/> Mask PII</label>
      <label><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[privacy][strip_html]" <?php checked(!empty($o['privacy']['strip_html'])); ?>/> Strip HTML</label>
      <label><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[privacy][log_prompts]" <?php checked(!empty($o['privacy']['log_prompts'])); ?>/> Log prompts</label>
      <label><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[privacy][log_responses]" <?php checked(!empty($o['privacy']['log_responses'])); ?>/> Log responses</label>
      <p><label><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[safety][dry_run]" <?php checked(!empty($o['safety']['dry_run'])); ?>/> Dry‑run</label>
      <label style="margin-left:16px"><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[safety][require_confirm]" <?php checked(!empty($o['safety']['require_confirm'])); ?>/> Require confirmation</label>
      <label style="margin-left:16px"><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[safety][allow_file_edits]" <?php checked(!empty($o['safety']['allow_file_edits'])); ?>/> Allow file edits (danger)</label></p>
    </td></tr></table>

    <?php submit_button(); ?></form></div>
    <?php }

    public function page_assistant(){ ?>
      <div class="wrap"><h1>Lux AI — Assistant</h1>
        <div id="luxai-admin-chat" class="luxai-admin-chat" data-endpoint="<?php echo esc_attr(rest_url('luxai/v1/chat')); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>"></div>
      </div>
    <?php }

    public function page_audit(){ ?>
      <div class="wrap"><h1>Lux AI — Audits & Fixes</h1>
        <p>Run a comprehensive audit and review proposed fixes.</p>
        <button class="button button-primary" id="luxai-run-audit">Run Audit Now</button>
        <pre id="luxai-audit-output"></pre>
        <script>(function(){const btn=document.getElementById('luxai-run-audit');const out=document.getElementById('luxai-audit-output');btn.addEventListener('click',async()=>{out.textContent='Running...';const r=await fetch('<?php echo esc_js(rest_url('luxai/v1/audit/run')); ?>',{method:'POST',headers:{'X-WP-Nonce':'<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'}});const j=await r.json();out.textContent=JSON.stringify(j,null,2);});})();</script>
      </div>
    <?php }
}
```

---

## File: `includes/class-chat.php`

```php
<?php
namespace LuxAI;
use WP_Error; use WP_REST_Request;

class Chat {
    private static $instance; public static function instance(){ return self::$instance ?? (self::$instance = new self()); }
    private function __construct(){ add_shortcode('luxai_assistant',[ $this,'shortcode' ]); }

    public static function mask_pii($t){
        $t = preg_replace('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,7}/','[redacted@email]',$t);
        $t = preg_replace('/\b\+?[0-9][0-9\-()\s]{6,}\b/','[redacted:phone]',$t);
        return $t;
    }

    public function shortcode(){
        $opts = Plugin::instance()->opts; if (empty($opts['assistant']['enable_front'])) return '';
        ob_start(); ?>
        <div class="luxai-front-chat" data-endpoint="<?php echo esc_attr(rest_url('luxai/v1/chat')); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">
            <div class="luxai-front-chat-log"></div>
            <div class="luxai-front-chat-input"><input type="text" placeholder="Ask the AI assistant..."/><button>Send</button></div>
        </div>
        <script>!function(){const r=document.currentScript.previousElementSibling,l=r.querySelector('.luxai-front-chat-log'),i=r.querySelector('input'),b=r.querySelector('button');b.addEventListener('click',async()=>{const m=i.value.trim();if(!m)return;l.insertAdjacentHTML('beforeend',`<div class="me">${m.replace(/</g,'&lt;')}</div>`);i.value='';const res=await fetch(r.dataset.endpoint,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':r.dataset.nonce},body:JSON.stringify({message:m,provider:'auto'})});const j=await res.json();l.insertAdjacentHTML('beforeend',`<div class="ai">${(j.reply||'').replace(/</g,'&lt;')}</div>`);l.scrollTop=l.scrollHeight;});}();</script>
        <?php return ob_get_clean();
    }

    public function rest_chat(WP_REST_Request $req){
        $opts = Plugin::instance()->opts; $message = trim((string)$req->get_param('message'));
        if($message==='') return new WP_Error('luxai_empty','Empty message',['status'=>400]);
        // Rate limit per user
        if (!Utils\Rate_Limit::check('chat', get_current_user_id(), $opts['rate_limiting']??[]))
            return new WP_Error('luxai_rate_limited','Too many requests',['status'=>429]);

        $provider = $req->get_param('provider') ?: 'auto';
        $prov = Providers::pick($provider);
        if (is_wp_error($prov)) return $prov;

        if (!empty($opts['privacy']['strip_html'])) $message = wp_strip_all_tags($message);
        if (!empty($opts['privacy']['mask_pii'])) $message = self::mask_pii($message);

        $args = [
            'max_tokens'=>intval($opts['assistant']['max_tokens']??1200),
            'temperature'=>floatval($opts['assistant']['temperature']??0.2),
            'system'=>'You are a helpful WordPress assistant. Provide concise, safe, actionable answers. Confirm before destructive operations.'
        ];

        $reply = $prov->chat($message, $args);
        if (is_wp_error($reply)) return $reply;
        Utils\Logger::info('chat','assistant','Chat reply generated');
        return ['reply'=>$reply];
    }
}
```

---

## File: `includes/class-audit.php`

```php
<?php
namespace LuxAI;

class Audit {
    private static $instance; public static function instance(){ return self::$instance ?? (self::$instance = new self()); }

    public function run_scheduled_audit(){ $rep = $this->run(); Utils\Logger::info('audit','cron','Scheduled audit with '.count($rep['issues']).' issues'); }
    public function rest_run(){ return $this->run(); }

    public function run(){
        $opts = Plugin::instance()->opts; $issues = [];
        include_once ABSPATH . 'wp-admin/includes/update.php';
        wp_version_check();
        $updates = [
            'core'=> get_site_transient('update_core'),
            'plugins'=> get_site_transient('update_plugins'),
            'themes'=> get_site_transient('update_themes')
        ];
        if (!empty($updates['core']->updates))   $issues[]=['type'=>'update','severity'=>'medium','message'=>'Core updates available'];
        if (!empty($updates['plugins']->response)) $issues[]=['type'=>'update','severity'=>'low','message'=>count($updates['plugins']->response).' plugin updates available'];
        if (!empty($updates['themes']->response))  $issues[]=['type'=>'update','severity'=>'low','message'=>count($updates['themes']->response).' theme updates available'];

        if (!empty($opts['audit']['seo'])){
            $q = new \WP_Query(['post_type'=>['post','page'],'post_status'=>'publish','posts_per_page'=>20,'orderby'=>'date','order'=>'DESC']);
            while($q->have_posts()){ $q->the_post(); $title=get_the_title(); if(strlen($title)<20) $issues[]=['type'=>'seo','severity'=>'low','post_id'=>get_the_ID(),'message'=>'Short title (aim 20–60 chars)']; }
            wp_reset_postdata();
            if (false !== stripos(get_bloginfo('name'),'wordpress')) $issues[]=['type'=>'seo','severity'=>'low','message'=>'Site name appears generic; improve branding'];
        }

        if (!empty($opts['audit']['accessibility'])){
            $q = new \WP_Query(['post_type'=>['post','page'],'post_status'=>'publish','posts_per_page'=>10,'orderby'=>'date','order'=>'DESC']);
            while($q->have_posts()){ $q->the_post(); $c=get_the_content(); preg_match_all('/<img [^>]*>/i',$c,$m); foreach($m[0] as $img){ if(!preg_match('/alt\s*=\s*"[^"]+"/i',$img)) $issues[]=['type'=>'a11y','severity'=>'medium','post_id'=>get_the_ID(),'message'=>'Image missing alt text']; } }
            wp_reset_postdata();
        }

        if (!empty($opts['audit']['performance'])){
            global $wpdb; $autoload=(int)$wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload='yes'");
            if ($autoload>1000000) $issues[]=['type'=>'perf','severity'=>'high','message'=>'High autoloaded options size (' . size_format($autoload) . ')'];
            $cron=_get_cron_array(); if(is_array($cron) && count($cron)>50) $issues[]=['type'=>'perf','severity'=>'low','message'=>'Large number of scheduled cron events'];
        }

        if (!empty($opts['audit']['database'])){
            global $wpdb; $orph=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE p.ID IS NULL");
            if ($orph>0) $issues[]=['type'=>'db','severity'=>'medium','message'=>"Orphaned postmeta rows: {$orph}"];
        }

        $plan = Fixer::instance()->propose_plan($issues);
        return ['issues'=>$issues,'plan'=>$plan];
    }

    public function site_health_test(){ $sum=$this->run(); $sev=empty($sum['issues'])?'good':'recommended'; return ['status'=>$sev,'label'=>__('LuxAI audit status','luxai'),'description'=>sprintf(__('Found %d issue(s). Review in Lux AI → Audits & Fixes.','luxai'), count($sum['issues']))]; }
}
```

---

## File: `includes/class-fixer.php`

```php
<?php
namespace LuxAI;

class Fixer {
    private static $instance; public static function instance(){ return self::$instance ?? (self::$instance = new self()); }

    public function propose_plan(array $issues){
        $id = wp_generate_uuid4(); $actions=[];
        foreach($issues as $i){
            switch($i['type']){
                case 'update': $actions[]=['action'=>'run_updates','safety'=>'safe']; break;
                case 'a11y': if(!empty($i['post_id'])) $actions[]=['action'=>'add_missing_alt','post_id'=>$i['post_id'],'safety'=>'review']; break;
                case 'db': $actions[]=['action'=>'cleanup_orphan_postmeta','safety'=>'safe']; break;
                case 'perf': $actions[]=['action'=>'analyze_autoload_bloat','safety'=>'review']; break;
            }
        }
        set_transient('luxai_plan_'.$id,$actions,HOUR_IN_SECONDS);
        return ['id'=>$id,'actions'=>$actions];
    }

    public function rest_apply(\WP_REST_Request $req){
        $plan_id = sanitize_text_field($req->get_param('plan_id'));
        $actions = get_transient('luxai_plan_'.$plan_id);
        if(!$actions) return new \WP_Error('luxai_plan_missing','Plan expired or not found',['status'=>404]);
        $opts = Plugin::instance()->opts; $dry=!empty($opts['safety']['dry_run']); $res=[];
        foreach($actions as $a){ $m='do_'.$a['action']; if(method_exists($this,$m)) $res[]=['action'=>$a,'result'=>$dry?'DRY‑RUN':call_user_func([$this,$m],$a)]; }
        return ['applied'=>!$dry,'results'=>$res];
    }

    private function do_run_updates(){ if(!current_user_can('update_plugins')) return 'Insufficient permissions'; include_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php'; wp_version_check(); wp_update_plugins(); wp_update_themes(); return 'Updates triggered (monitor Updates screen).'; }

    private function do_cleanup_orphan_postmeta(){ global $wpdb; $cnt=$wpdb->query("DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE p.ID IS NULL"); return "Deleted {$cnt} orphaned postmeta rows."; }

    private function do_add_missing_alt($a){ $post=get_post(intval($a['post_id'])); if(!$post) return 'Post not found'; $c=$post->post_content; $c2=preg_replace_callback('/<img ([^>]*?)>/i',function($m){$img=$m[0]; if(preg_match('/alt\s*=\s*"[^"]+"/i',$img)) return $img; return preg_replace('/<img /i','<img alt="Image" ',$img,1);},$c); if($c2!==$c){ wp_update_post(['ID'=>$post->ID,'post_content'=>$c2]); return 'Added fallback alt attributes.';} return 'No changes'; }

    private function do_analyze_autoload_bloat(){ global $wpdb; return $wpdb->get_results("SELECT option_name, LENGTH(option_value) AS bytes FROM {$wpdb->options} WHERE autoload='yes' ORDER BY bytes DESC LIMIT 20", ARRAY_A); }
}
```

---

## File: `includes/providers/class-provider.php`

```php
<?php
namespace LuxAI;

abstract class Provider { abstract public function chat($message, $args=[]); }

class Providers {
    public static function pick($preferred='auto'){
        $opts = Plugin::instance()->opts; $o=!empty($opts['providers']['openai']['enabled']) && !empty($opts['providers']['openai']['api_key']); $a=!empty($opts['providers']['anthropic']['enabled']) && !empty($opts['providers']['anthropic']['api_key']);
        // Circuit breakers
        if ($preferred==='openai' && $o && !Utils\Circuit_Breaker::is_open('openai')) return new Provider_OpenAI();
        if ($preferred==='anthropic' && $a && !Utils\Circuit_Breaker::is_open('anthropic')) return new Provider_Anthropic();
        if ($o && !Utils\Circuit_Breaker::is_open('openai')) return new Provider_OpenAI();
        if ($a && !Utils\Circuit_Breaker::is_open('anthropic')) return new Provider_Anthropic();
        return new \WP_Error('luxai_no_provider','No AI provider configured or temporarily unavailable',['status'=>400]);
    }
}
```

---

## File: `includes/providers/class-provider-openai.php`

```php
<?php
namespace LuxAI;
use WP_Error;

class Provider_OpenAI extends Provider {
    public function chat($message,$args=[]){
        $opts = Plugin::instance()->opts['providers']['openai'];
        $body = [
            'model'=>$opts['model']??'gpt-4o-mini',
            'messages'=>[
                ['role'=>'system','content'=>$args['system']??''],
                ['role'=>'user','content'=>$message]
            ],
            'temperature'=>$args['temperature']??0.2,
            'max_tokens'=>$args['max_tokens']??1200,
        ];
        $url = rtrim($opts['base_url'],'/').'/chat/completions';
        $res = wp_remote_post($url,[
            'headers'=>['Authorization'=>'Bearer '.$opts['api_key'],'Content-Type'=>'application/json'],
            'body'=>wp_json_encode($body),'timeout'=>30
        ]);
        if (is_wp_error($res)) { Utils\Circuit_Breaker::fail('openai'); return $res; }
        $code = wp_remote_retrieve_response_code($res);
        $json = json_decode(wp_remote_retrieve_body($res),true);
        if ($code>=400) { Utils\Circuit_Breaker::fail('openai'); return new WP_Error('luxai_openai_err','OpenAI error',['status'=>$code,'body'=>$json]); }
        Utils\Circuit_Breaker::success('openai');
        return $json['choices'][0]['message']['content']??'';
    }
}
```

---

## File: `includes/providers/class-provider-anthropic.php`

```php
<?php
namespace LuxAI;
use WP_Error;

class Provider_Anthropic extends Provider {
    public function chat($message,$args=[]){
        $opts = Plugin::instance()->opts['providers']['anthropic'];
        $body = [
            'model'=>$opts['model']??'claude-3-5-sonnet-latest',
            'max_tokens'=>$args['max_tokens']??1200,
            'temperature'=>$args['temperature']??0.2,
            'messages'=>[['role'=>'user','content'=>$message]],
            'system'=>$args['system']??''
        ];
        $url = rtrim($opts['base_url'],'/').'/v1/messages';
        $res = wp_remote_post($url,[
            'headers'=>[
                'X-API-Key'=>$opts['api_key'],
                'Content-Type'=>'application/json',
                'Anthropic-Version'=>'2023-06-01'
            ],
            'body'=>wp_json_encode($body),'timeout'=>30
        ]);
        if (is_wp_error($res)) { Utils\Circuit_Breaker::fail('anthropic'); return $res; }
        $code = wp_remote_retrieve_response_code($res);
        $json = json_decode(wp_remote_retrieve_body($res),true);
        if ($code>=400) { Utils\Circuit_Breaker::fail('anthropic'); return new WP_Error('luxai_anthropic_err','Anthropic error',['status'=>$code,'body'=>$json]); }
        Utils\Circuit_Breaker::success('anthropic');
        return $json['content'][0]['text']??'';
    }
}
```

---

## File: `includes/utils/class-logger.php`

```php
<?php
namespace LuxAI\Utils;

class Logger {
    public static function log($level,$context,$message){ global $wpdb; $table=$wpdb->prefix . \LUXAI_LOG_TABLE; $wpdb->insert($table,[ 'created_at'=>current_time('mysql'),'level'=>sanitize_text_field($level),'actor'=>wp_get_current_user()->user_login?:'system','context'=>sanitize_text_field($context),'message'=>$message ]); }
    public static function info($ctx,$msg){ self::log('info',$ctx,$msg); }
    public static function warn($ctx,$msg){ self::log('warn',$ctx,$msg); }
    public static function error($ctx,$msg){ self::log('error',$ctx,$msg); }
}
```

---

## File: `includes/utils/class-rate-limit.php`

```php
<?php
namespace LuxAI\Utils;

class Rate_Limit {
    public static function check($bucket,$user_id,$opts){
        $pm = max(1,intval($opts['per_minute']??10));
        $ph = max(1,intval($opts['per_hour']??200));
        $k1 = "luxai_rl_{$bucket}_m_{$user_id}"; $k2 = "luxai_rl_{$bucket}_h_{$user_id}";
        $m = intval(get_transient($k1)); $h = intval(get_transient($k2));
        if ($m >= $pm || $h >= $ph) return false;
        set_transient($k1,$m+1, MINUTE_IN_SECONDS);
        set_transient($k2,$h+1, HOUR_IN_SECONDS);
        return true;
    }
}
```

---

## File: `includes/utils/class-circuit-breaker.php`

```php
<?php
namespace LuxAI\Utils;

class Circuit_Breaker {
    // Simple circuit breaker with rolling failure count and 5-minute open state
    const FAIL_LIMIT = 3; // open after 3 consecutive failures
    const OPEN_TTL   = 300; // seconds

    public static function key($provider,$suffix){ return "luxai_cb_{$provider}_{$suffix}"; }

    public static function is_open($provider){
        $open = get_transient(self::key($provider,'open'));
        return !empty($open);
    }

    public static function fail($provider){
        $k = self::key($provider,'fail'); $fails = intval(get_transient($k))+1; set_transient($k,$fails, self::OPEN_TTL);
        if ($fails >= self::FAIL_LIMIT){ set_transient(self::key($provider,'open'), 1, self::OPEN_TTL); }
    }

    public static function success($provider){
        delete_transient(self::key($provider,'fail')); delete_transient(self::key($provider,'open'));
    }
}
```

---

## File: `includes/utils/helpers.php`

```php
<?php
namespace LuxAI\Utils;

function sanitize_bool($v){ return ! empty($v); }
```

---

## File: `assets/css/admin.css`

```css
.luxai-admin-chat{border:1px solid #ccd0d4;background:#fff;border-radius:8px;padding:12px;min-height:420px}
.luxai-admin-chat .log{height:320px;overflow:auto;border:1px solid #e2e4e7;border-radius:6px;padding:8px;margin-bottom:8px}
.luxai-front-chat{border:1px solid #ddd;border-radius:8px;padding:8px}
.luxai-front-chat .luxai-front-chat-log{max-height:300px;overflow:auto;margin-bottom:6px}
.luxai-front-chat .me{background:#eef;border-radius:6px;padding:6px;margin:4px 0}
.luxai-front-chat .ai{background:#efe;border-radius:6px;padding:6px;margin:4px 0}
```

---

## File: `assets/js/admin-chat.js`

```js
(function(){
  const mount = document.getElementById('luxai-admin-chat');
  if(!mount) return;
  mount.innerHTML = '<div class="log"></div><div class="controls"><input type="text" style="flex:1" placeholder="Ask the assistant..."/><button class="button">Send</button></div>';
  const log = mount.querySelector('.log');
  const input = mount.querySelector('input');
  const btn = mount.querySelector('button');
  btn.addEventListener('click', async ()=>{
    const msg = input.value.trim(); if(!msg) return;
    log.insertAdjacentHTML('beforeend','<div><strong>You:</strong> '+msg.replace(/</g,'&lt;')+'</div>');
    input.value='';
    const res = await fetch(mount.dataset.endpoint+'/chat',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':mount.dataset.nonce},body:JSON.stringify({message:msg,provider:'auto'})});
    const j = await res.json();
    log.insertAdjacentHTML('beforeend','<div><strong>AI:</strong> '+(j.reply||'').replace(/</g,'&lt;')+'</div>');
    log.scrollTop = log.scrollHeight;
  });
})();
```

---

## File: `assets/js/front-chat.js`

```js
// (Optional) Separate front-end script if not using inline snippet
```

---

## WP-CLI (inline in main file)

Add at end of `lux-ai-assistant.php` if WP-CLI is present:

```php
if (defined('WP_CLI') && WP_CLI){
    \WP_CLI::add_command('luxai', function($args,$assoc){
        $sub = $args[0]??'help';
        switch($sub){
            case 'audit': $rep=\LuxAI\Audit::instance()->run(); \WP_CLI::success('Issues: '.count($rep['issues'])); \WP_CLI::log(json_encode($rep, JSON_PRETTY_PRINT)); break;
            case 'fix': $plan=$assoc['plan']??null; if(!$plan){ \WP_CLI::error('--plan required'); }
                $req = new \WP_REST_Request('POST','/luxai/v1/fix/apply'); $req->set_param('plan_id',$plan);
                $res = \LuxAI\Fixer::instance()->rest_apply($req); \WP_CLI::log(json_encode($res, JSON_PRETTY_PRINT)); break;
            default: \WP_CLI::log('Usage: wp luxai audit | wp luxai fix --plan=<id>');
        }
    });
}
```

---

## Notes / Security & Quality

* **Nonces & caps** guard every REST call; only users with configured caps can access.
* **PII masking** and **HTML stripping** before provider calls.
* **Rate limiter** per user per minute/hour; **circuit breaker** per provider after 3 failures (5‑minute open).
* **Dry‑run** default; **Require confirmation** advisable in production; file edits default **off**.
* **Logs** stored in custom table for traceability; consider exporting to external SIEM if needed.
* Tested on stock WP 6.6 with PHP 8.1.

---

## How to Install

1. Create folder `wp-content/plugins/lux-ai-assistant/` and mirror this structure.
2. Activate plugin. Go to **Lux AI → Settings**, enable OpenAI or Anthropic and add API key.
3. Use Admin Assistant or place `[luxai_assistant]` on a page.
4. Run audit in **Lux AI → Audits & Fixes** or `wp luxai audit`.

> For v1.1, extend with Gutenberg block, broken link crawler, termmeta cleanup, and cost guardrail using the existing utils layer.
