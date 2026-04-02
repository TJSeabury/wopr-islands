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
     * @param array<string, mixed> $props
     */
    public function hydrate(array $props): void
    {
        foreach (static::reactivePropertyNames() as $name) {
            if (array_key_exists($name, $props)) {
                $this->{$name} = $props[$name];
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getReactiveState(): array
    {
        $out = [];
        foreach (static::reactivePropertyNames() as $name) {
            $out[$name] = $this->{$name};
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $patch
     */
    public function applyPatch(array $patch): void
    {
        $allowed = array_flip(static::reactivePropertyNames());

        foreach ($patch as $key => $value) {
            if (!isset($allowed[$key])) {
                throw new \InvalidArgumentException(sprintf('Unknown reactive property: %s', (string) $key));
            }

            $this->{$key} = $value;
        }
    }

    /**
     * @param list<mixed> $args
     */
    public function callAction(string $name, array $args = []): void
    {
        $actions = static::actionMethodNames();
        if (!isset($actions[$name])) {
            throw new \InvalidArgumentException(sprintf('Unknown action: %s', $name));
        }

        $method = new ReflectionMethod($this, $name);
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
     * @return list<string>
     */
    public static function reactivePropertyNames(): array
    {
        $ref = new ReflectionClass(static::class);
        $names = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            foreach ($property->getAttributes(Reactive::class) as $_) {
                $names[] = $property->getName();
            }
        }

        return $names;
    }

    /**
     * @return array<string, ReflectionMethod>
     */
    public static function actionMethods(): array
    {
        $ref = new ReflectionClass(static::class);
        $methods = [];

        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic()) {
                continue;
            }

            if ($method->getDeclaringClass()->getName() === self::class) {
                continue;
            }

            foreach ($method->getAttributes(Action::class) as $_) {
                $methods[$method->getName()] = $method;
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
