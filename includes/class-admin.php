<?php
namespace LuxAI;

class Admin {
    private static $instance; public static function instance(){ return self::$instance ?? (self::$instance = new self()); }
    private function __construct(){
        add_action('admin_menu',[ $this,'menu' ]);
        add_action('admin_init',[ $this,'register_settings' ]);
        add_action('admin_enqueue_scripts',[ $this,'assets' ]);
    }

    public static function register_block(){
        $dir = \LUXAI_DIR . 'blocks/assistant';
        wp_register_script('luxai-assistant-block', \LUXAI_URL.'blocks/assistant/index.js', ['wp-blocks','wp-element'], \LUXAI_VERSION, true);
        register_block_type($dir, [
            'render_callback' => function(){ return do_shortcode('[luxai_assistant]'); }
        ]);
    }

    public function menu(){
        add_menu_page('Lux AI','Lux AI',Plugin::instance()->cap_manage(),'luxai',[ $this,'page_settings' ],'dashicons-art',58);
        add_submenu_page('luxai',__('Assistant','luxai'),__('Assistant','luxai'),Plugin::instance()->cap_use(),'luxai-assistant',[ $this,'page_assistant' ]);
        add_submenu_page('luxai',__('Audits & Fixes','luxai'),__('Audits & Fixes','luxai'),Plugin::instance()->cap_manage(),'luxai-audit',[ $this,'page_audit' ]);
        add_submenu_page('luxai',__('Crawler','luxai'),__('Crawler','luxai'),Plugin::instance()->cap_manage(),'luxai-crawler',[ $this,'page_crawler' ]);
    }

    public function register_settings(){ register_setting('luxai', \LUXAI_OPTION_KEY, [ $this, 'sanitize' ] ); }

    public function sanitize($opts){
        $opts = is_array($opts)?$opts:[];
        foreach(['openai','anthropic'] as $p){
            if(isset($opts['providers'][$p]['api_key'])) $opts['providers'][$p]['api_key']=trim($opts['providers'][$p]['api_key']);
            if(isset($opts['providers'][$p]['cost_per_1k'])) $opts['providers'][$p]['cost_per_1k']=floatval($opts['providers'][$p]['cost_per_1k']);
        }
        foreach(['log_prompts','log_responses'] as $k){ $opts['privacy'][$k] = !empty($opts['privacy'][$k]); }
        foreach(['dry_run','require_confirm','allow_file_edits'] as $k){ $opts['safety'][$k] = !empty($opts['safety'][$k]); }
        if(isset($opts['cost_guardrail']['daily_usd_cap'])) $opts['cost_guardrail']['daily_usd_cap']=max(0, floatval($opts['cost_guardrail']['daily_usd_cap']));
        $opts['cost_guardrail']['enabled'] = !empty($opts['cost_guardrail']['enabled']);
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
        <p>Model: <input type="text" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[providers][openai][model]" value="<?php echo esc_attr($o['providers']['openai']['model']??'gpt-4o-mini'); ?>">
        Cost per 1K tokens (USD): <input type="number" step="0.01" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[providers][openai][cost_per_1k]" value="<?php echo esc_attr($o['providers']['openai']['cost_per_1k']??0.15); ?>"></p>
      </td></tr>
      <tr><th>Anthropic</th><td>
        <label><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[providers][anthropic][enabled]" <?php checked(!empty($o['providers']['anthropic']['enabled'])); ?>/> Enabled</label><br>
        <input type="password" size="60" placeholder="Anthropic API Key" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[providers][anthropic][api_key]" value="<?php echo esc_attr($o['providers']['anthropic']['api_key']??''); ?>">
        <p>Model: <input type="text" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[providers][anthropic][model]" value="<?php echo esc_attr($o['providers']['anthropic']['model']??'claude-3-5-sonnet-latest'); ?>">
        Cost per 1K tokens (USD): <input type="number" step="0.01" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[providers][anthropic][cost_per_1k]" value="<?php echo esc_attr($o['providers']['anthropic']['cost_per_1k']??0.20); ?>"></p>
      </td></tr>
    </table>

    <h2>Assistant</h2>
    <table class="form-table"><tr><th>Admin & Front</th><td>
      <label><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[assistant][enable_admin]" <?php checked(!empty($o['assistant']['enable_admin'])); ?>/> Admin Assistant</label>
      <label style="margin-left:16px"><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[assistant][enable_front]" <?php checked(!empty($o['assistant']['enable_front'])); ?>/> Front-end Assistant ([luxai_assistant])</label>
      <p>Max tokens: <input type="number" min="128" max="4096" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[assistant][max_tokens]" value="<?php echo esc_attr($o['assistant']['max_tokens']??1200); ?>">
      Temperature: <input type="number" step="0.1" min="0" max="2" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[assistant][temperature]" value="<?php echo esc_attr($o['assistant']['temperature']??0.2); ?>"></p>
    </td></tr></table>

    <h2>Audits & Crawler</h2>
    <table class="form-table"><tr><th>Scope</th><td>
      <?php foreach(['seo'=>'SEO','accessibility'=>'Accessibility','performance'=>'Performance','database'=>'Database','include_plugins'=>'Plugins','include_themes'=>'Themes','crawler'=>'Broken Link Crawler'] as $k=>$lbl): ?>
        <label><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[audit][<?php echo esc_attr($k); ?>]" <?php checked(!empty($o['audit'][$k])); ?>/> <?php echo esc_html($lbl); ?></label>
      <?php endforeach; ?>
      <p>Schedule: <select name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[audit][schedule]"><option value="daily" <?php selected(($o['audit']['schedule']??'daily'),'daily'); ?>>Daily</option><option value="weekly" <?php selected(($o['audit']['schedule']??'daily'),'weekly'); ?>>Weekly</option></select>
      <label style="margin-left:16px"><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[audit][auto_fix]" <?php checked(!empty($o['audit']['auto_fix'])); ?>/> Auto-apply safe fixes</label></p>
    </td></tr></table>

    <h2>Safety, Privacy & Cost</h2>
    <table class="form-table"><tr><th>Controls</th><td>
      <label><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[privacy][mask_pii]" <?php checked(!empty($o['privacy']['mask_pii'])); ?>/> Mask PII</label>
      <label><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[privacy][strip_html]" <?php checked(!empty($o['privacy']['strip_html'])); ?>/> Strip HTML</label>
      <label><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[privacy][log_prompts]" <?php checked(!empty($o['privacy']['log_prompts'])); ?>/> Log prompts</label>
      <label><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[privacy][log_responses]" <?php checked(!empty($o['privacy']['log_responses'])); ?>/> Log responses</label>
      <p><label><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[safety][dry_run]" <?php checked(!empty($o['safety']['dry_run'])); ?>/> Dry-run</label>
      <label style="margin-left:16px"><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[safety][require_confirm]" <?php checked(!empty($o['safety']['require_confirm'])); ?>/> Require confirmation</label>
      <label style="margin-left:16px"><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[safety][allow_file_edits]" <?php checked(!empty($o['safety']['allow_file_edits'])); ?>/> Allow file edits (danger)</label></p>
      <p>Cost Guardrail: <label><input type="checkbox" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[cost_guardrail][enabled]" <?php checked(!empty($o['cost_guardrail']['enabled'])); ?>/> Enable</label>
      Daily USD cap: <input type="number" step="0.01" name="<?php echo esc_attr(\LUXAI_OPTION_KEY); ?>[cost_guardrail][daily_usd_cap]" value="<?php echo esc_attr($o['cost_guardrail']['daily_usd_cap']??3.00); ?>"></p>
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
        <button class="button button-primary" id="luxai-run-audit">Run Audit Now</button>
        <pre id="luxai-audit-output"></pre>
        <script>(function(){const b=document.getElementById('luxai-run-audit');const out=document.getElementById('luxai-audit-output');b.addEventListener('click',async()=>{out.textContent='Running...';const r=await fetch('<?php echo esc_js(rest_url('luxai/v1/audit/run')); ?>',{method:'POST',headers:{'X-WP-Nonce':'<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'}});out.textContent=JSON.stringify(await r.json(),null,2);});})();</script>
      </div>
    <?php }

    public function page_crawler(){ ?>
      <div class="wrap"><h1>Lux AI — Broken Link Crawler</h1>
        <button class="button" id="luxai-run-crawl">Run Crawl (200 links)</button>
        <pre id="luxai-crawl-output"></pre>
        <script>(function(){const b=document.getElementById('luxai-run-crawl');const out=document.getElementById('luxai-crawl-output');b.addEventListener('click',async()=>{out.textContent='Running...';const r=await fetch('<?php echo esc_js(rest_url('luxai/v1/crawl/run')); ?>',{method:'POST',headers:{'X-WP-Nonce':'<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'},body:JSON.stringify({limit:200})});out.textContent=JSON.stringify(await r.json(),null,2);});})();</script>
      </div>
    <?php }
}
