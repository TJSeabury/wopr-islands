<?php

declare(strict_types=1);

namespace Tjseabury\WoprIslands\Http;

use Tjseabury\WoprIslands\WoprIslands;
use WP_REST_Server;

final class RouteRegistrar
{
    public static function register(): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        register_rest_route(
            WoprIslands::REST_NAMESPACE,
            '/(?P<component_slug>[a-z0-9-]+)/(?P<instance_id>[a-zA-Z0-9-]+)/update',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [UpdateRequestHandler::class, 'handle'],
                'permission_callback' => [UpdateRequestHandler::class, 'permission'],
                'args' => [
                    'component_slug' => [
                        'required' => true,
                    ],
                    'instance_id' => [
                        'required' => true,
                    ],
                ],
            ]
        );
    }
}
