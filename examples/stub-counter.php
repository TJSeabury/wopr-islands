<?php

declare(strict_types=1);

use Tjseabury\WoprIslands\Attributes\Action;
use Tjseabury\WoprIslands\Attributes\ComponentSlug;
use Tjseabury\WoprIslands\Attributes\Reactive;
use Tjseabury\WoprIslands\Component;

#[ComponentSlug('stub-counter')]
final class StubCounter extends Component
{
  private const OPTION_KEY = 'wopr_islands_stub_counter_count';

  #[Reactive]
  public int $count = 0;

  public function __construct()
  {
    if (function_exists('get_option')) {
      $this->count = (int) get_option(self::OPTION_KEY, 0);
    }
  }

  /**
   * @param array<string, mixed> $props
   */
  public function hydrate(array $props): void
  {
    parent::hydrate($props);

    // Server is source-of-truth for persisted state.
    if (function_exists('get_option')) {
      $this->count = (int) get_option(self::OPTION_KEY, $this->count);
    }
  }

  /**
   * @param array<string, mixed> $patch
   */
  public function applyPatch(array $patch): void
  {
    parent::applyPatch($patch);

    if (function_exists('update_option') && array_key_exists('count', $patch)) {
      // Keep persisted state in sync if the client patches directly.
      update_option(self::OPTION_KEY, (int) $this->count, false);
    }
  }

  #[Action(name: 'increment')]
  public function increment(): void
  {
    $current = $this->count;
    if (function_exists('get_option')) {
      $current = (int) get_option(self::OPTION_KEY, $current);
    }

    $this->count = $current + 1;

    if (function_exists('update_option')) {
      update_option(self::OPTION_KEY, (int) $this->count, false);
    }
  }

  #[Action(name: 'decrement')]
  public function decrement(): void
  {
    $current = $this->count;
    if (function_exists('get_option')) {
      $current = (int) get_option(self::OPTION_KEY, $current);
    }

    $this->count = $current - 1;

    if (function_exists('update_option')) {
      update_option(self::OPTION_KEY, (int) $this->count, false);
    }
  }

  public function render(): string
  {
    $escapedCount = htmlspecialchars((string) $this->count, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    ob_start();
    ?>
    <div class="wopr-islands-stub-counter" style="display:flex;gap:12px;align-items:center;">

      <button type="button" data-wopr-action="decrement">-</button>

      <span>Stub counter: <strong data-wopr-bind="count"><?php echo $escapedCount; ?></strong></span>

      <button type="button" data-wopr-action="increment">+</button>

    </div>
    <?php

    return (string) ob_get_clean();
  }
}

