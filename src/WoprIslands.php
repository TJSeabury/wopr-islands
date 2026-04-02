<?php

declare(strict_types=1);

namespace Tjseabury\WoprIslands;

use Tjseabury\WoprIslands\Snapshot\SnapshotSigner;

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
}
