<?php
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
