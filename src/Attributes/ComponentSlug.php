<?php

declare(strict_types=1);

namespace Tjseabury\WoprIslands\Attributes;

use Attribute;

/**
 * Stable URL / registry identifier for this island (REST path segment and snapshot slug).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ComponentSlug
{
    public function __construct(
        public readonly string $slug
    ) {
    }
}
