<?php

declare(strict_types=1);

namespace Tjseabury\WoprIslands;

use Tjseabury\WoprIslands\Snapshot\SnapshotSigner;
use Tjseabury\WoprIslands\RenderedComponent;

final class WoprIslands
{
  public const REST_NAMESPACE = 'wopr-islands/v1';

  private static ?ComponentRegistry $registry = null;

  private static ?SnapshotSigner $signer = null;

  private static ?ComponentFactory $factory = null;

  private static bool $booted = false;

  public static function boot(?SnapshotSigner $signer = null): void
  {
    if (self::$booted) {
      return;
    }

    self::$booted = true;
    self::$registry = new ComponentRegistry();
    self::$signer = $signer ?? new SnapshotSigner();
    self::$factory = new ComponentFactory(self::$signer, self::$registry);

    if (function_exists('add_action')) {
      add_action('rest_api_init', [Http\RouteRegistrar::class, 'register'], 10, 0);

      // WordPress integration: print queued init payloads late in the page.
      add_action('wp_footer', [RenderedComponent::class, 'flushQueuedInitScripts'], 5, 0);
    }
  }

  /**
   * @param class-string<Component> $className
   */
  public static function register(string $className): void
  {
    self::boot();

    if (!is_subclass_of($className, Component::class)) {
      throw new \InvalidArgumentException(sprintf('%s must extend %s.', $className, Component::class));
    }

    $slug = $className::islandSlug();
    self::registry()->register($className, $slug);
  }

  public static function factory(): ComponentFactory
  {
    self::boot();

    return self::$factory;
  }

  public static function registry(): ComponentRegistry
  {
    self::boot();

    return self::$registry;
  }

  public static function signer(): SnapshotSigner
  {
    self::boot();

    return self::$signer;
  }

  /**
   * Base URL for this package's REST routes (no trailing slash), e.g. https://example.com/wp-json/wopr-islands/v1
   */
  public static function restEndpointBaseUrl(): string
  {
    if (function_exists('rest_url')) {
      return rtrim((string) rest_url(self::REST_NAMESPACE), '/');
    }

    return rtrim('/wp-json/' . self::REST_NAMESPACE, '/');
  }

  /**
   * Enqueue the WOPR Islands browser bundle and (optionally) inject a REST nonce.
   *
   * This is a convenience for WordPress integrations. It is a no-op outside WP.
   *
   * @param array{
   *   handle?: string,
   *   deps?: list<string>,
   *   version?: string|null,
   *   in_footer?: bool,
   *   nonce?: string|null
   * } $args
   */
  public static function enqueueClientScript(string $src, array $args = []): void
  {
    if (!function_exists('wp_enqueue_script')) {
      return;
    }

    $handle = is_string($args['handle'] ?? null) ? (string) $args['handle'] : 'wopr-islands';
    $deps = is_array($args['deps'] ?? null) ? $args['deps'] : [];
    $version = array_key_exists('version', $args) ? (is_string($args['version']) ? $args['version'] : null) : null;
    $inFooter = array_key_exists('in_footer', $args) ? (bool) $args['in_footer'] : true;

    wp_enqueue_script($handle, $src, $deps, $version, $inFooter);

    if (!function_exists('wp_add_inline_script') || !function_exists('wp_json_encode')) {
      return;
    }

    $nonce = $args['nonce'] ?? null;
    if ($nonce === null && function_exists('wp_create_nonce')) {
      $nonce = wp_create_nonce('wp_rest');
    }

    if (is_string($nonce) && $nonce !== '') {
      wp_add_inline_script($handle, 'window.__WOPR_ISLANDS_NONCE=' . wp_json_encode($nonce) . ';', 'before');
    }
  }
}
