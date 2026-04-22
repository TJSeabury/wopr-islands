<?php

declare(strict_types=1);

use Tjseabury\WoprIslands\Component;
use Tjseabury\WoprIslands\Attributes\ComponentSlug;
use Tjseabury\WoprIslands\Attributes\Reactive;
use Tjseabury\WoprIslands\Attributes\Action;

#[ComponentSlug('auth-status')]
final class AuthStatus extends Component
{
  #[Reactive]
  public bool $isLoggedIn = false;

  #[Reactive]
  public string $userName = '';

  public function __construct()
  {
    $this->refresh();
  }

  #[Action(name: 'refresh')]
  public function refresh(): void
  {
    if (!function_exists('is_user_logged_in') || !function_exists('wp_get_current_user')) {
      $this->isLoggedIn = false;
      $this->userName = '';
      return;
    }

    $this->isLoggedIn = (bool) is_user_logged_in();
    $user = wp_get_current_user();
    $name = is_object($user) && isset($user->display_name) ? (string) $user->display_name : '';
    $this->userName = $this->isLoggedIn ? $name : '';
  }

  public function render(): string
  {
    $name = htmlspecialchars($this->userName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $status = $this->isLoggedIn ? 'Logged in as ' . $name : 'Not logged in';

    ob_start();
    ?>
    <div class="wopr-islands-auth-status" style="display:flex;gap:10px;align-items:center;">
      <span data-wopr-bind="isLoggedIn"><?php echo $status; ?></span>
      <button type="button" data-wopr-action="refresh">Refresh</button>
    </div>
    <?php

    return (string) ob_get_clean();
  }
}

