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
    private static function debugLog(string $message, array $context = []): void
    {
        $line = '[wopr-islands] ' . $message;
        if ($context !== []) {
            try {
                $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } catch (\Throwable) {
                // Ignore JSON failures in debug output.
            }
        }

        if (function_exists('error_log')) {
            error_log($line);
        }
    }

    public static function permission(): bool|WP_Error
    {
        if (!function_exists('add_action')) {
            return new WP_Error('wopr_no_wp', 'WordPress is not loaded.', ['status' => 500]);
        }

        // This endpoint is designed to work for both authenticated and anonymous visitors.
        // Sensitive actions should be protected using component-level authorization attributes
        // (capabilities) and/or action-specific verification inside the component method.
        return true;
    }

    public static function handle(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $slug = (string) $request['component_slug'];
        $instanceId = (string) $request['instance_id'];
        $params = $request->get_json_params();

        self::debugLog('handle: incoming', [
            'slug' => $slug,
            'instanceId' => $instanceId,
            'hasParams' => is_array($params),
            'hasNonceHeader' => (string) $request->get_header('X-WP-Nonce') !== '',
        ]);

        if (!is_array($params)) {
            self::debugLog('handle: invalid json body', []);
            return new WP_Error('wopr_invalid_json', 'Expected JSON body.', ['status' => 400]);
        }

        $snapshotToken = $params['snapshot'] ?? null;
        if (!is_string($snapshotToken) || $snapshotToken === '') {
            self::debugLog('handle: missing snapshot', []);
            return new WP_Error('wopr_missing_snapshot', 'Missing snapshot.', ['status' => 400]);
        }

        $patch = $params['patch'] ?? [];
        if ($patch === null) {
            $patch = [];
        }
        if (!is_array($patch)) {
            self::debugLog('handle: invalid patch', ['patchType' => gettype($patch)]);
            return new WP_Error('wopr_invalid_patch', 'Patch must be an object.', ['status' => 400]);
        }

        $action = $params['action'] ?? null;
        if (is_array($action)) {
            self::debugLog('handle: action provided', [
                'name' => isset($action['name']) ? $action['name'] : null,
                'argsType' => isset($action['args']) ? gettype($action['args']) : null,
            ]);
        }

        try {
            $signer = WoprIslands::signer();
            $payload = $signer->parseToken($snapshotToken);
        } catch (SnapshotException $e) {
            self::debugLog('handle: bad snapshot', ['error' => $e->getMessage()]);
            return new WP_Error('wopr_bad_snapshot', $e->getMessage(), ['status' => 403]);
        }

        if (($payload['v'] ?? null) !== 1) {
            self::debugLog('handle: unsupported snapshot version', ['v' => $payload['v'] ?? null]);
            return new WP_Error('wopr_bad_snapshot', 'Unsupported snapshot version.', ['status' => 400]);
        }

        if (($payload['slug'] ?? '') !== $slug || ($payload['instanceId'] ?? '') !== $instanceId) {
            self::debugLog('handle: snapshot route mismatch', [
                'payloadSlug' => $payload['slug'] ?? null,
                'payloadInstanceId' => $payload['instanceId'] ?? null,
            ]);
            return new WP_Error('wopr_mismatch', 'Snapshot does not match route.', ['status' => 400]);
        }

        $class = WoprIslands::registry()->getClassForSlug($slug);
        $payloadClass = $payload['class'] ?? null;

        if ($class === null || !is_string($payloadClass) || $payloadClass !== $class) {
            self::debugLog('handle: class mismatch', [
                'registryClass' => $class,
                'payloadClass' => $payloadClass,
            ]);
            return new WP_Error('wopr_bad_class', 'Unknown or mismatched component class.', ['status' => 400]);
        }

        $state = $payload['state'] ?? [];
        if (!is_array($state)) {
            return new WP_Error('wopr_bad_state', 'Snapshot state must be an object.', ['status' => 400]);
        }

        /** @var Component $instance */
        $instance = new $class();
        $instance->hydrate($state);
        self::debugLog('handle: hydrated', ['class' => $class]);

        if ($patch !== []) {
            try {
                $instance->applyPatch($patch);
            } catch (\InvalidArgumentException $e) {
                self::debugLog('handle: invalid patch keys', ['error' => $e->getMessage()]);
                return new WP_Error('wopr_invalid_patch', $e->getMessage(), ['status' => 400]);
            }
        }

        if (is_array($action) && isset($action['name']) && is_string($action['name'])) {
            $name = $action['name'];
            $args = $action['args'] ?? [];
            if ($args !== null && !is_array($args)) {
                self::debugLog('handle: invalid action args', ['argsType' => gettype($args)]);
                return new WP_Error('wopr_invalid_action', 'Action args must be an array.', ['status' => 400]);
            }
            if (!is_array($args)) {
                $args = [];
            }

            /** @var class-string<Component> $class */
            $authError = self::authorizeAction($class, $name);
            if ($authError instanceof WP_Error) {
                self::debugLog('handle: action forbidden', ['name' => $name, 'error' => $authError->get_error_message()]);
                return $authError;
            }

            try {
                self::debugLog('handle: calling action', ['name' => $name, 'argsCount' => count($args)]);
                $instance->callAction($name, array_values($args));
            } catch (\InvalidArgumentException $e) {
                self::debugLog('handle: invalid action', ['error' => $e->getMessage()]);
                return new WP_Error('wopr_invalid_action', $e->getMessage(), ['status' => 400]);
            }
        }

        $newState = $instance->getReactiveState();
        self::debugLog('handle: returning', ['stateKeys' => array_keys($newState)]);
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

    /**
     * @param class-string<Component> $componentClass
     */
    private static function authorizeAction(string $componentClass, string $actionWire): true|WP_Error
    {
        $caps = $componentClass::authorizeCapabilitiesForActionWire($actionWire);
        if ($caps === []) {
            return true;
        }

        if (!function_exists('current_user_can')) {
            return new WP_Error('wopr_no_wp', 'WordPress capabilities are not available.', ['status' => 500]);
        }

        foreach ($caps as $cap) {
            if (!current_user_can($cap)) {
                return new WP_Error(
                    'wopr_forbidden',
                    sprintf('You do not have permission to perform this action (requires "%s").', $cap),
                    ['status' => 403]
                );
            }
        }

        return true;
    }
}
