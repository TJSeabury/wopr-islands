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
require_once $wopr_root . '/examples/stub-counter.php';
require_once $wopr_root . '/examples/view-counter.php';
require_once $wopr_root . '/examples/flash-message.php';
require_once $wopr_root . '/examples/like-button.php';
require_once $wopr_root . '/examples/auth-status.php';
require_once $wopr_root . '/examples/live-chat.php';

use Tjseabury\WoprIslands\WoprIslands;

/**
 * Enqueue the WOPR Islands browser bundle + REST nonce.
 *
 * In this dev stub plugin, we load the bundle from the package root `dist/`.
 * Real consumers should enqueue the same bundle from their own plugin/theme URL.
 */
add_action('wp_enqueue_scripts', static function (): void {
  if (!function_exists('wp_enqueue_script')) {
    return;
  }

  // `wopr_root` is computed above and points at the package repository root in wp-env.
  global $wopr_root;
  if (!is_string($wopr_root) || $wopr_root === '') {
    return;
  }

  // Map filesystem path to URL in wp-env. The package root is mounted at `/wopr-islands-package`.
  $src = site_url('/wopr-islands-package/dist/wopr-islands.js');
  WoprIslands::enqueueClientScript($src);
}, 5);

/**
 * Renders the dev island and registers the inline init script once per request.
 */
function wopr_stub_render_stub_counter_island(): string
{
  WoprIslands::boot();
  $rendered = WoprIslands::factory()->make(StubCounter::class, []);

  return $rendered->toHtml();
}

add_action('plugins_loaded', static function (): void {
  WoprIslands::register(StubCounter::class);
  WoprIslands::register(ViewCounter::class);
  WoprIslands::register(FlashMessage::class);
  WoprIslands::register(LikeButton::class);
  WoprIslands::register(AuthStatus::class);
  WoprIslands::register(LiveChat::class);
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

add_action('loop_end', static function (\WP_Query $query): void {
  if (is_admin() || !$query->is_main_query() || !$query->is_front_page() || !$query->is_home()) {
    return;
  }

  $rendered = WoprIslands::factory()->make(ViewCounter::class, []);
  echo $rendered->toHtml();
}, 20);

add_filter('the_content', static function (string $content): string {
  if (is_admin() || is_home() || !in_the_loop() || !is_main_query()) {
    return $content;
  }

  $rendered = WoprIslands::factory()->make(ViewCounter::class, []);
  $flash = WoprIslands::factory()->make(FlashMessage::class, [])->toHtml();
  $like = WoprIslands::factory()->make(LikeButton::class, [])->toHtml();
  $auth = WoprIslands::factory()->make(AuthStatus::class, [])->toHtml();
  $chat = WoprIslands::factory()->make(LiveChat::class, [])->toHtml();

  return $content . $rendered->toHtml() . '<hr />' . $flash . '<hr />' . $like . '<hr />' . $auth . '<hr />' . $chat;
}, 20);

add_action('admin_notices', static function (): void {
  if (!current_user_can('manage_options') || !is_admin()) {
    return;
  }

  WoprIslands::boot();

  $url = WoprIslands::restEndpointBaseUrl();
  echo '<div class="notice notice-info"><p><strong>WOPR Islands stub:</strong> REST base <code>' . esc_html($url) . '</code></p></div>';
});
