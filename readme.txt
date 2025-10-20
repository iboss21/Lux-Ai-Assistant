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
