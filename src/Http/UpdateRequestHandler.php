<?php

declare(strict_types=1);

namespace Tjseabury\WoprIslands\Http;

use Tjseabury\WoprIslands\Component;
use Tjseabury\WoprIslands\WoprIslands;
use Tjseabury\WoprIslands\Snapshot\SnapshotException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
final class UpdateRequestHandler
{
    public static function permission(): bool|WP_Error
    {
        if (!function_exists('is_user_logged_in')) {
            return new WP_Error('wopr_no_wp', 'WordPress is not loaded.', ['status' => 500]);
        }

        if (!is_user_logged_in()) {
            return new WP_Error('wopr_forbidden', 'Authentication required.', ['status' => 401]);
        }

        return true;
    }

    public static function handle(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $slug = (string) $request['component_slug'];
        $instanceId = (string) $request['instance_id'];
        $params = $request->get_json_params();

        if (!is_array($params)) {
            return new WP_Error('wopr_invalid_json', 'Expected JSON body.', ['status' => 400]);
        }

        $snapshotToken = $params['snapshot'] ?? null;
        if (!is_string($snapshotToken) || $snapshotToken === '') {
            return new WP_Error('wopr_missing_snapshot', 'Missing snapshot.', ['status' => 400]);
        }

        $patch = $params['patch'] ?? [];
        if ($patch === null) {
            $patch = [];
        }
        if (!is_array($patch)) {
            return new WP_Error('wopr_invalid_patch', 'Patch must be an object.', ['status' => 400]);
        }

        $action = $params['action'] ?? null;

        try {
            $signer = WoprIslands::signer();
            $payload = $signer->parseToken($snapshotToken);
        } catch (SnapshotException $e) {
            return new WP_Error('wopr_bad_snapshot', $e->getMessage(), ['status' => 403]);
        }

        if (($payload['v'] ?? null) !== 1) {
            return new WP_Error('wopr_bad_snapshot', 'Unsupported snapshot version.', ['status' => 400]);
        }

        if (($payload['slug'] ?? '') !== $slug || ($payload['instanceId'] ?? '') !== $instanceId) {
            return new WP_Error('wopr_mismatch', 'Snapshot does not match route.', ['status' => 400]);
        }

        $class = WoprIslands::registry()->getClassForSlug($slug);
        $payloadClass = $payload['class'] ?? null;

        if ($class === null || !is_string($payloadClass) || $payloadClass !== $class) {
            return new WP_Error('wopr_bad_class', 'Unknown or mismatched component class.', ['status' => 400]);
        }

        $state = $payload['state'] ?? [];
        if (!is_array($state)) {
            return new WP_Error('wopr_bad_state', 'Snapshot state must be an object.', ['status' => 400]);
        }

        /** @var Component $instance */
        $instance = new $class();
        $instance->hydrate($state);

        if ($patch !== []) {
            try {
                $instance->applyPatch($patch);
            } catch (\InvalidArgumentException $e) {
                return new WP_Error('wopr_invalid_patch', $e->getMessage(), ['status' => 400]);
            }
        }

        if (is_array($action) && isset($action['name']) && is_string($action['name'])) {
            $name = $action['name'];
            $args = $action['args'] ?? [];
            if ($args !== null && !is_array($args)) {
                return new WP_Error('wopr_invalid_action', 'Action args must be an array.', ['status' => 400]);
            }
            if (!is_array($args)) {
                $args = [];
            }

            try {
                $instance->callAction($name, array_values($args));
            } catch (\InvalidArgumentException $e) {
                return new WP_Error('wopr_invalid_action', $e->getMessage(), ['status' => 400]);
            }
        }

        $newState = $instance->getReactiveState();
        $newPayload = [
            'v' => 1,
            'slug' => $slug,
            'instanceId' => $instanceId,
            'class' => $class,
            'state' => $newState,
        ];
        $newSnapshot = $signer->createToken($newPayload);

        return new WP_REST_Response([
            'state' => $newState,
            'snapshot' => $newSnapshot,
        ], 200);
    }
}
