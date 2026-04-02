<?php

declare(strict_types=1);

namespace Tjseabury\WoprIslands\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class ComponentSlug
{
    public function __construct(
        public readonly string $slug
    ) {
    }
}
