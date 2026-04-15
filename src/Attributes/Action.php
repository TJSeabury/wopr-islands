<?php

declare(strict_types=1);

namespace Tjseabury\WoprIslands\Attributes;

use Attribute;

/**
 * Marks a public instance method as callable from the client (like Livewire actions).
 *
 * The client invokes actions by {@see self::$name}; when null, the PHP method name is used.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Action
{
    public function __construct(
        public readonly ?string $name = null,
    ) {
    }
}
