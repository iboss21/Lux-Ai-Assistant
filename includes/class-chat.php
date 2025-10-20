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
