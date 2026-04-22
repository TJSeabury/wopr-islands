<?php

declare(strict_types=1);

use Tjseabury\WoprIslands\Component;
use Tjseabury\WoprIslands\Attributes\ComponentSlug;
use Tjseabury\WoprIslands\Attributes\Reactive;
use Tjseabury\WoprIslands\Attributes\Action;

#[ComponentSlug('view-counter')]
final class ViewCounter extends Component
{
  private const METADATA_KEY = 'wopr_islands_post_view_count';
  private const NONCE_ACTION_PREFIX = 'wopr_islands_view_';
  private const NONCE_TRANSIENT_PREFIX = 'wopr_islands_viewed_';

  #[Reactive]
  public int $postId = 0;

  #[Reactive]
  public int $count = 0;

  /**
   * One-time nonce used by the client to increment on first attach.
   * Cleared after a successful increment to prevent double counting.
   */
  #[Reactive]
  public string $viewNonce = '';

  public function __construct()
  {
    if (function_exists('get_the_ID')) {
      $this->postId = (int) get_the_ID();
    }

    if (function_exists('get_post_meta') && $this->postId > 0) {
      $this->count = (int) get_post_meta($this->postId, self::METADATA_KEY, true);
    }

    if (function_exists('wp_create_nonce') && $this->postId > 0) {
      $this->viewNonce = (string) wp_create_nonce(self::NONCE_ACTION_PREFIX . $this->postId);
    }
  }

  /**
   * @param array<string, mixed> $props
   */
  public function hydrate(array $props): void
  {
    parent::hydrate($props);

    if (function_exists('get_post_meta') && $this->postId > 0) {
      $this->count = (int) get_post_meta($this->postId, self::METADATA_KEY, true);
    }
  }

  #[Action(name: 'increment')]
  public function increment(string $nonce = ''): void
  {
    if ($this->postId <= 0) {
      return;
    }

    if (!function_exists('wp_verify_nonce')) {
      return;
    }

    if ($nonce === '' || wp_verify_nonce($nonce, self::NONCE_ACTION_PREFIX . $this->postId) !== 1) {
      return;
    }

    if (function_exists('get_transient') && function_exists('set_transient')) {
      $key = self::NONCE_TRANSIENT_PREFIX . $this->postId . '_' . substr(sha1($nonce), 0, 12);
      if (get_transient($key)) {
        return;
      }
      set_transient($key, 1, defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600);
    }

    $this->count++;
    if (function_exists('update_post_meta')) {
      update_post_meta($this->postId, self::METADATA_KEY, $this->count);
    }

    $this->viewNonce = '';
  }

  public function render(): string
  {
    $escaped = htmlspecialchars((string) $this->count, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return '<span class="wopr-islands-view-counter"><strong data-wopr-bind="count">' . $escaped . '</strong></span>';
  }
}

