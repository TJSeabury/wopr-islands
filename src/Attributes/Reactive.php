<?php

declare(strict_types=1);

namespace Tjseabury\WoprIslands\Attributes;

use Attribute;

/**
 * Marks a public property as reactive server state (like Livewire properties).
 *
 * Snapshots, REST patches, `hydrate()`, and the JS client all use the **wire** key
 * (constructor argument `wire`, or the PHP property name when `wire` is null).
 *
 * `debounceMs` and `defer` are optional hints for the frontend when binding inputs
 * (e.g. debounced wire:model-style updates); see {@see Component::reactiveSchema()}.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Reactive
{
    public function __construct(
        public readonly ?string $wire = null,
        public readonly ?int $debounceMs = null,
        public readonly bool $defer = false,
    ) {
    }
}
