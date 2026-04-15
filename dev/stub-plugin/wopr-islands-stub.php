<?php

/**
 * Plugin Name:       WOPR Islands (dev stub)
 * Description:       Loads tjseabury/wopr-islands in wp-env for manual integration testing.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:       8.1
 */

declare(strict_types=1);

/**
 * wp-env bind-mounts only this plugin dir under wp-content/plugins, so paths like
 * dirname(__DIR__, 2) resolve to wp-content/, not the monorepo. The package root is
 * mounted separately — see .wp-env.json "mappings" → /var/www/html/wopr-islands-package.
 *
 * @var list<string>
 */
$candidate_roots = [
    '/var/www/html/wopr-islands-package',
    dirname(__DIR__, 2),
];

$wopr_root = null;
foreach ($candidate_roots as $root) {
    if (is_readable($root . '/vendor/autoload.php')) {
        $wopr_root = $root;
        break;
    }
}

if ($wopr_root === null) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>WOPR Islands stub: could not load Composer autoload. Run <code>composer install</code> at the package repository root, confirm <code>.wp-env.json</code> maps that root (see <code>wopr-islands-package</code>), then run <code>wp-env start</code> again.</p></div>';
    });

    return;
}

$autoload = $wopr_root . '/vendor/autoload.php';
require_once $autoload;
require_once __DIR__ . '/includes/stub-counter.php';

use Tjseabury\WoprIslands\WoprIslands;

/**
 * Renders the dev island and registers the inline init script once per request.
 */
function wopr_stub_render_stub_counter_island(): string
{
    WoprIslands::boot();
    $rendered = WoprIslands::factory()->make(StubCounter::class, []);

    static $init_script_registered = false;
    if (!$init_script_registered) {
        $init_script_registered = true;
        add_action(
            'wp_footer',
            static function () use ($rendered): void {
                echo $rendered->inlineInitScript();
                if (function_exists('wp_create_nonce')) {
                    $nonce = wp_create_nonce('wp_rest');
                    echo '<script>window.__WOPR_ISLANDS_NONCE=' . wp_json_encode($nonce) . ';</script>';
                }

                echo <<<'HTML'
<script>
(function(){
  const inits = window.__WOPR_ISLANDS_INIT || [];
  const nonce = window.__WOPR_ISLANDS_NONCE || null;

  function q(sel, root){ return (root || document).querySelector(sel); }
  function qa(sel, root){ return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  async function attach(init){
    const el = q(`[data-wopr-island="${init.slug}"][data-wopr-instance="${init.instanceId}"]`);
    if (!el) return;

    let state = { ...(init.initialState || {}) };
    let snapshot = init.snapshot;
    let updating = false;

    const reactiveKeys = Array.isArray(init.reactiveSchema) && init.reactiveSchema.length
      ? init.reactiveSchema.map((f) => f.wire).filter(Boolean)
      : Object.keys(state);

    function render(){
      for (const key of reactiveKeys) {
        const nodes = qa(`[data-wopr-bind="${CSS.escape(key)}"]`, el);
        for (const node of nodes) {
          const v = state[key];
          node.textContent = v == null ? '' : String(v);
        }
      }
    }

    async function request(body){
      const url = `${init.restEndpointBaseUrl}/${encodeURIComponent(init.slug)}/${encodeURIComponent(init.instanceId)}/update`;
      const headers = { 'Content-Type': 'application/json' };
      if (nonce) headers['X-WP-Nonce'] = nonce;

      const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers,
        body: JSON.stringify({ snapshot, ...(body || {}) }),
      });

      if (!res.ok) throw new Error(`Update failed (${res.status})`);

      const data = await res.json();
      state = data.state || {};
      snapshot = data.snapshot || snapshot;
      render();
    }

    qa('[data-wopr-action]', el).forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (updating) return;
        updating = true;
        const name = btn.getAttribute('data-wopr-action');
        try {
          await request({ action: { name, args: [] } });
        } finally {
          updating = false;
        }
      });
    });

    render();
  }

  for (let i = 0; i < inits.length; i++) {
    if (inits[i] && inits[i].slug && inits[i].instanceId) void attach(inits[i]);
  }
})();
</script>
HTML;
            },
            5
        );
    }

    return $rendered->toHtml();
}

add_action('plugins_loaded', static function (): void {
    WoprIslands::register(StubCounter::class);
});

/** Blog posts on the front page: append after the main loop. */
add_action('loop_end', static function (\WP_Query $query): void {
    if (is_admin() || !$query->is_main_query() || !$query->is_front_page() || !$query->is_home()) {
        return;
    }

    echo wopr_stub_render_stub_counter_island();
}, 20);

/** Static page as the front page: append to that page’s content. */
add_filter('the_content', static function (string $content): string {
    if (is_admin() || !is_front_page() || is_home() || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    return $content . wopr_stub_render_stub_counter_island();
}, 20);

add_action('admin_notices', static function (): void {
    if (!current_user_can('manage_options') || !is_admin()) {
        return;
    }

    WoprIslands::boot();

    $url = WoprIslands::restEndpointBaseUrl();
    echo '<div class="notice notice-info"><p><strong>WOPR Islands stub:</strong> REST base <code>' . esc_html($url) . '</code></p></div>';
});
