<?php

declare(strict_types=1);

use Tjseabury\WoprIslands\Component;
use Tjseabury\WoprIslands\Attributes\ComponentSlug;
use Tjseabury\WoprIslands\Attributes\Reactive;
use Tjseabury\WoprIslands\Attributes\Action;

#[ComponentSlug('live-chat')]
final class LiveChat extends Component
{
  private const OPTION_KEY = 'wopr_islands_example_live_chat_messages';

  /**
   * @var list<array{at:int,user:string,text:string}>
   */
  #[Reactive]
  public array $messages = [];

  #[Reactive]
  public string $draft = '';

  public function __construct()
  {
    $this->messages = $this->loadMessages();
  }

  /**
   * @return list<array{at:int,user:string,text:string}>
   */
  private function loadMessages(): array
  {
    if (!function_exists('get_option')) {
      return [];
    }

    $raw = get_option(self::OPTION_KEY, []);
    if (!is_array($raw)) {
      return [];
    }

    // Ensure predictable shape.
    $out = [];
    foreach (array_slice($raw, -50) as $m) {
      if (!is_array($m)) {
        continue;
      }
      $out[] = [
        'at' => isset($m['at']) ? (int) $m['at'] : 0,
        'user' => isset($m['user']) ? (string) $m['user'] : '',
        'text' => isset($m['text']) ? (string) $m['text'] : '',
      ];
    }

    return $out;
  }

  private function saveMessages(): void
  {
    if (function_exists('update_option')) {
      update_option(self::OPTION_KEY, $this->messages, false);
    }
  }

  #[Action(name: 'refresh')]
  public function refresh(): void
  {
    $this->messages = $this->loadMessages();
  }

  #[Action(name: 'send')]
  public function send(string $text = ''): void
  {
    if (!function_exists('is_user_logged_in') || !is_user_logged_in()) {
      return;
    }

    if (!function_exists('wp_get_current_user')) {
      return;
    }

    $text = trim($text !== '' ? $text : $this->draft);
    if ($text === '') {
      return;
    }

    $user = wp_get_current_user();
    $name = is_object($user) && isset($user->display_name) ? (string) $user->display_name : 'User';

    $this->messages = $this->loadMessages();
    $this->messages[] = [
      'at' => time(),
      'user' => $name,
      'text' => $text,
    ];
    $this->messages = array_slice($this->messages, -50);

    $this->draft = '';
    $this->saveMessages();
  }

  public function render(): string
  {
    $canSend = function_exists('is_user_logged_in') ? (bool) is_user_logged_in() : false;

    ob_start();
    ?>
    <div class="wopr-islands-live-chat" style="border:1px solid #ddd;border-radius:10px;padding:12px;max-width:520px;">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
        <strong>Live chat (example)</strong>
        <button type="button" data-wopr-action="refresh">Refresh</button>
      </div>

      <div style="margin-top:10px;max-height:220px;overflow:auto;display:flex;flex-direction:column;gap:8px;">
        <?php foreach ($this->messages as $m): ?>
          <div style="padding:8px 10px;background:#f7f7f7;border-radius:8px;">
            <div style="font-size:12px;color:#555;">
              <strong><?php echo htmlspecialchars((string) $m['user'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
            </div>
            <div><?php echo htmlspecialchars((string) $m['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div style="margin-top:10px;display:flex;gap:10px;align-items:center;">
        <input
          type="text"
          value="<?php echo htmlspecialchars($this->draft, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
          placeholder="<?php echo $canSend ? 'Type a message…' : 'Log in to chat'; ?>"
          style="flex:1;padding:8px 10px;border:1px solid #ccc;border-radius:8px;"
          <?php echo $canSend ? '' : 'disabled'; ?>
        />
        <button
          type="button"
          data-wopr-action="send"
          data-wopr-action-args="<?php echo htmlspecialchars(wp_json_encode([$this->draft]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
          <?php echo $canSend ? '' : 'disabled'; ?>
        >
          Send
        </button>
      </div>

      <?php if (!$canSend): ?>
        <div style="margin-top:8px;font-size:12px;color:#777;">Only logged-in users can send messages.</div>
      <?php endif; ?>
    </div>
    <?php

    return (string) ob_get_clean();
  }
}

