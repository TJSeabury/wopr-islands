<?php

declare(strict_types=1);

namespace Tjseabury\WoprIslands;

final class ComponentRegistry
{
    /** @var array<string, class-string<Component>> */
    private array $slugToClass = [];

    /** @var array<class-string<Component>, true> */
    private array $registeredClasses = [];

    /**
     * @param class-string<Component> $className
     */
    public function register(string $className, string $slug): void
    {
        if (isset($this->slugToClass[$slug]) && $this->slugToClass[$slug] !== $className) {
            throw new \InvalidArgumentException(sprintf(
                'Island slug "%s" is already registered to %s.',
                $slug,
                $this->slugToClass[$slug]
            ));
        }

        $this->slugToClass[$slug] = $className;
        $this->registeredClasses[$className] = true;
    }

    /**
     * @return class-string<Component>|null
     */
    public function getClassForSlug(string $slug): ?string
    {
        return $this->slugToClass[$slug] ?? null;
    }

    /**
     * @return array<string, class-string<Component>>
     */
    public function all(): array
    {
        return $this->slugToClass;
    }

    /**
     * @param class-string<Component> $className
     */
    public function hasClass(string $className): bool
    {
        return isset($this->registeredClasses[$className]);
    }
}
