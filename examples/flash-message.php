<?php

declare(strict_types=1);

use Tjseabury\WoprIslands\Component;
use Tjseabury\WoprIslands\Attributes\ComponentSlug;
use Tjseabury\WoprIslands\Attributes\Reactive;
use Tjseabury\WoprIslands\Attributes\Action;

#[ComponentSlug('flash-message')]
final class FlashMessage extends Component
{
  #[Reactive]
  public bool $visible = false;

  #[Reactive]
  public string $type = 'info'; // info|success|warning|error

  #[Reactive]
  public string $message = '';

  #[Action(name: 'show')]
  public function show(string $message = '', string $type = 'info'): void
  {
    $this->message = $message;
    $this->type = $type !== '' ? $type : 'info';
    $this->visible = true;
  }

  #[Action(name: 'hide')]
  public function hide(): void
  {
    $this->visible = false;
  }

  public function render(): string
  {
    $msg = htmlspecialchars($this->message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $type = htmlspecialchars($this->type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $hiddenStyle = $this->visible ? '' : 'display:none;';

    ob_start();
    ?>
    <div class="wopr-islands-flash-message" style="display:flex;gap:12px;align-items:center;">
      <div data-wopr-bind="visible" style="<?php echo $hiddenStyle; ?>padding:10px 12px;border:1px solid #ddd;border-radius:8px;">
        <strong><?php echo $type; ?>:</strong>
        <span data-wopr-bind="message"><?php echo $msg; ?></span>
        <button type="button" data-wopr-action="hide" style="margin-left:10px;">Dismiss</button>
      </div>
      <button
        type="button"
        data-wopr-action="show"
        data-wopr-action-args="<?php echo htmlspecialchars(wp_json_encode(['Hello from FlashMessage!', 'success']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
      >
        Show toast
      </button>
    </div>
    <?php

    return (string) ob_get_clean();
  }
}

