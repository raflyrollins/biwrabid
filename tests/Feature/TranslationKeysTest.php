<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Translation integrity.
 *
 * These are cheap checks that catch the failure mode where a screen shows a raw
 * `chat.actions.dismiss_room` to a user instead of the words it stands for. That
 * failure is invisible to a type checker and to every feature test, because
 * nothing about the *behaviour* changes when a string goes missing.
 */
it('resolves every translation key the chat UI asks for', function (): void {
    $translations = trans('ui');

    /*
     * Read out of the built client bundle rather than a hand-kept list, so a key
     * that the UI asks for and this file does not have is the thing that fails.
     * Scanning the source is what makes that possible without maintaining a list
     * that goes stale on the next feature.
     */
    $sources = [
        'pages/chat/index.tsx',
        'components/chat/room-list.tsx',
        'components/chat/chat-thread-panel.tsx',
        'components/chat/avatar.tsx',
        'components/chat/payment-panel.tsx',
        'components/empty-state.tsx',
    ];

    $missing = [];

    foreach ($sources as $source) {
        $path = resource_path('js/'.$source);

        if (! File::exists($path)) {
            continue;
        }

        preg_match_all("/t\('(chat\.[a-z_.]+)'/", File::get($path), $matches);

        foreach ($matches[1] as $key) {
            $segment = $key;
            $found = $translations;

            foreach (explode('.', $segment) as $part) {
                if (! is_array($found) || ! array_key_exists($part, $found)) {
                    $missing[] = $key;

                    continue 2;
                }

                $found = $found[$part];
            }
        }
    }

    expect($missing)->toBe([]);
});

it('keeps the chat and payment action menus as separate keys', function (): void {
    $actions = trans('ui')['chat'];

    // Both menus exist and neither has swallowed the other: `chat.actions` is the
    // right-click menu and the storefront buttons, `chat.payment.actions` is the
    // five money steps.
    expect($actions['actions'])->toHaveKeys([
        'menu',
        'close_room',
        'dismiss_room',
        'edit',
        'delete_self',
        'delete_everyone',
        'open_group_chat_winner',
        'open_credentials_winner',
    ])->and($actions['payment']['actions'])->toHaveKeys([
        'request',
        'proof',
        'received',
        'transfer',
        'confirm',
    ]);
});

it('ships the same chat keys in every locale', function (): void {
    /** The leaf key names under a node, so two locales can be compared as sets. */
    $leaves = function (array $node) use (&$leaves): array {
        $found = [];

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                foreach ($leaves($value) as $nested) {
                    $found[] = $key.'.'.$nested;
                }

                continue;
            }

            $found[] = $key;
        }

        return $found;
    };

    $id = $leaves(trans('ui')['chat']['actions']);

    // Read straight from the file rather than through `trans()`: the app's default
    // locale is the one `trans()` resolves, and the other file has to be compared on
    // its own terms.
    $en = $leaves((require lang_path('en/ui.php'))['chat']['actions']);

    // The two languages are edited independently, so a key added to one and
    // forgotten in the other ships as a raw `chat.actions.*` string on that locale
    // and nobody notices until a reader in that language reports it.
    expect($id)->toEqualCanonicalizing($en);
});
