<?php

declare(strict_types=1);

use Tjseabury\WoprIslands\Component;
use Tjseabury\WoprIslands\Attributes\ComponentSlug;
use Tjseabury\WoprIslands\Attributes\Reactive;
use Tjseabury\WoprIslands\Attributes\Action;

#[ComponentSlug('like-button')]
final class LikeButton extends Component
{
  private const META_COUNT = 'wopr_islands_like_count';
  private const META_LIKERS = 'wopr_islands_like_likers'; // map of userId => true

  #[Reactive]
  public int $postId = 0;

  #[Reactive]
  public int $count = 0;

  #[Reactive]
  public bool $liked = false;

  public function __construct()
  {
    if (function_exists('get_the_ID')) {
      $this->postId = (int) get_the_ID();
    }

    if ($this->postId > 0 && function_exists('get_post_meta')) {
      $this->count = (int) get_post_meta($this->postId, self::META_COUNT, true);
    }

    $this->liked = $this->computeLiked();
  }

  /**
   * @param array<string, mixed> $props
   */
  public function hydrate(array $props): void
  {
    parent::hydrate($props);

    if ($this->postId > 0 && function_exists('get_post_meta')) {
      $this->count = (int) get_post_meta($this->postId, self::META_COUNT, true);
    }

    $this->liked = $this->computeLiked();
  }

  private function computeLiked(): bool
  {
    if ($this->postId <= 0 || !function_exists('get_current_user_id') || !function_exists('get_post_meta')) {
      return false;
    }

    $userId = (int) get_current_user_id();
    if ($userId <= 0) {
      return false;
    }

    $likers = get_post_meta($this->postId, self::META_LIKERS, true);
    if (!is_array($likers)) {
      return false;
    }

    return isset($likers[(string) $userId]) && $likers[(string) $userId] === true;
  }

  #[Action(name: 'toggle')]
  public function toggle(): void
  {
    if ($this->postId <= 0 || !function_exists('get_current_user_id') || !function_exists('get_post_meta') || !function_exists('update_post_meta')) {
      return;
    }

    $userId = (int) get_current_user_id();
    if ($userId <= 0) {
      return; // only logged-in users can like
    }

    $likers = get_post_meta($this->postId, self::META_LIKERS, true);
    if (!is_array($likers)) {
      $likers = [];
    }

    $key = (string) $userId;
    $currentlyLiked = isset($likers[$key]) && $likers[$key] === true;

    if ($currentlyLiked) {
      unset($likers[$key]);
      $this->count = max(0, $this->count - 1);
      $this->liked = false;
    } else {
      $likers[$key] = true;
      $this->count = $this->count + 1;
      $this->liked = true;
    }

    update_post_meta($this->postId, self::META_LIKERS, $likers);
    update_post_meta($this->postId, self::META_COUNT, $this->count);
  }

  public function render(): string
  {
    $label = $this->liked ? 'Unlike' : 'Like';
    $count = htmlspecialchars((string) $this->count, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    ob_start();
    ?>
    <div class="wopr-islands-like-button" style="display:inline-flex;gap:10px;align-items:center;">
      <button type="button" data-wopr-action="toggle"><?php echo htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></button>
      <span>Likes: <strong data-wopr-bind="count"><?php echo $count; ?></strong></span>
    </div>
    <?php

    return (string) ob_get_clean();
  }
}

