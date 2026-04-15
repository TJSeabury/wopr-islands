<?php

declare(strict_types=1);

namespace Tjseabury\WoprIslands\Attributes;

use Attribute;

/**
 * Declares WordPress capabilities required to invoke this action (AND semantics).
 *
 * Stack multiple attributes to require multiple capabilities, e.g.
 * `#[Authorize('edit_posts')] #[Authorize('publish_posts')]`.
 *
 * When no {@see Authorize} is present on an action method, only authentication
 * enforced by the REST route applies (see {@see \Tjseabury\WoprIslands\Http\UpdateRequestHandler::permission}).
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Authorize
{
    /**
     * @param non-empty-string $capability WordPress capability name (e.g. `edit_posts`, `manage_options`).
     */
    public function __construct(
        public readonly string $capability
    ) {
    }
}
