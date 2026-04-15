<?php

declare(strict_types=1);

namespace Tjseabury\WoprIslands;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Tjseabury\WoprIslands\Attributes\Action;
use Tjseabury\WoprIslands\Attributes\ComponentSlug;
use Tjseabury\WoprIslands\Attributes\Reactive;

abstract class Component
{
    /**
     * @param array<string, mixed> $props keyed by wire names (same keys as {@see getReactiveState()}).
     */
    public function hydrate(array $props): void
    {
        foreach (static::reactiveBindings() as $binding) {
            $wire = $binding['wire'];
            if (array_key_exists($wire, $props)) {
                $this->{$binding['property']} = $props[$wire];
            }
        }
    }

    /**
     * @return array<string, mixed> keyed by wire names (REST / snapshot / client state).
     */
    public function getReactiveState(): array
    {
        $out = [];
        foreach (static::reactiveBindings() as $binding) {
            $out[$binding['wire']] = $this->{$binding['property']};
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $patch keyed by wire names
     */
    public function applyPatch(array $patch): void
    {
        foreach ($patch as $key => $value) {
            $prop = static::propertyForWire((string) $key);
            if ($prop === null) {
                throw new \InvalidArgumentException(sprintf('Unknown reactive property: %s', (string) $key));
            }

            $this->{$prop} = $value;
        }
    }

    /**
     * @param list<mixed> $args
     */
    public function callAction(string $name, array $args = []): void
    {
        $actions = static::actionMethods();
        if (!isset($actions[$name])) {
            throw new \InvalidArgumentException(sprintf('Unknown action: %s', $name));
        }

        $method = $actions[$name];
        if (!$method->isPublic()) {
            throw new \InvalidArgumentException(sprintf('Action "%s" must be public.', $name));
        }

        $method->invoke($this, ...$args);
    }

    abstract public function render(): string;

    public static function islandSlug(): string
    {
        $ref = new ReflectionClass(static::class);

        foreach ($ref->getAttributes(ComponentSlug::class) as $attr) {
            return $attr->newInstance()->slug;
        }

        return static::defaultSlugFromShortName($ref->getShortName());
    }

    /**
     * Wire keys allowed in patches and snapshots (same keys as {@see getReactiveState()}).
     *
     * @return list<string>
     */
    public static function reactivePropertyNames(): array
    {
        return array_values(array_map(
            static fn (array $b): string => $b['wire'],
            static::reactiveBindings()
        ));
    }

    /**
     * Per-field metadata for clients (wire name, debounce/defer hints).
     *
     * @return list<array{wire: string, debounceMs: int|null, defer: bool}>
     */
    public static function reactiveSchema(): array
    {
        $out = [];
        foreach (static::reactiveBindings() as $b) {
            $out[] = [
                'wire' => $b['wire'],
                'debounceMs' => $b['debounceMs'],
                'defer' => $b['defer'],
            ];
        }

        return $out;
    }

    /**
     * Action metadata for clients (wire name used in {@see callAction()} over REST).
     *
     * @return list<array{wire: string}>
     */
    public static function actionSchema(): array
    {
        $out = [];
        foreach (array_keys(static::actionMethods()) as $wire) {
            $out[] = ['wire' => $wire];
        }

        return $out;
    }

    /**
     * @return list<array{property: string, wire: string, debounceMs: int|null, defer: bool}>
     */
    public static function reactiveBindings(): array
    {
        $ref = new ReflectionClass(static::class);
        $seen = [];
        $out = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() === self::class) {
                continue;
            }

            $attrs = $property->getAttributes(Reactive::class);
            if ($attrs === []) {
                continue;
            }

            /** @var Reactive $reactive */
            $reactive = $attrs[0]->newInstance();
            $wire = $reactive->wire ?? $property->getName();

            if (isset($seen[$wire])) {
                throw new \LogicException(sprintf(
                    'Duplicate reactive wire "%s" on %s (properties %s and %s).',
                    $wire,
                    static::class,
                    $seen[$wire],
                    $property->getName()
                ));
            }

            $seen[$wire] = $property->getName();

            $out[] = [
                'property' => $property->getName(),
                'wire' => $wire,
                'debounceMs' => $reactive->debounceMs,
                'defer' => $reactive->defer,
            ];
        }

        return $out;
    }

    public static function propertyForWire(string $wire): ?string
    {
        foreach (static::reactiveBindings() as $b) {
            if ($b['wire'] === $wire) {
                return $b['property'];
            }
        }

        return null;
    }

    /**
     * @return array<string, ReflectionMethod> keyed by action wire name
     */
    public static function actionMethods(): array
    {
        $ref = new ReflectionClass(static::class);
        $methods = [];
        $seen = [];

        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic()) {
                continue;
            }

            if ($method->getDeclaringClass()->getName() === self::class) {
                continue;
            }

            foreach ($method->getAttributes(Action::class) as $attr) {
                /** @var Action $action */
                $action = $attr->newInstance();
                $wire = $action->name ?? $method->getName();

                if (isset($seen[$wire])) {
                    throw new \LogicException(sprintf(
                        'Duplicate action wire "%s" on %s (methods %s and %s).',
                        $wire,
                        static::class,
                        $seen[$wire],
                        $method->getName()
                    ));
                }

                $seen[$wire] = $method->getName();
                $methods[$wire] = $method;
            }
        }

        return $methods;
    }

    /**
     * @return array<string, true>
     */
    public static function actionMethodNames(): array
    {
        return array_fill_keys(array_keys(static::actionMethods()), true);
    }

    private static function defaultSlugFromShortName(string $shortName): string
    {
        $slug = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $shortName) ?? $shortName);

        return ltrim($slug, '-');
    }
}
