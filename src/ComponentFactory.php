<?php

declare(strict_types=1);

namespace Tjseabury\WoprIslands;

use Tjseabury\WoprIslands\Snapshot\SnapshotSigner;
use Tjseabury\WoprIslands\Util\IslandId;

final class ComponentFactory
{
    public function __construct(
        private readonly SnapshotSigner $signer,
        private readonly ComponentRegistry $registry
    ) {
    }

    /**
     * @param class-string<Component> $class
     * @param array<string, mixed> $initialProps
     */
    public function make(string $class, array $initialProps = []): RenderedComponent
    {
        if (!is_subclass_of($class, Component::class)) {
            throw new \InvalidArgumentException(sprintf('%s must extend %s.', $class, Component::class));
        }

        $slug = $class::islandSlug();

        if (!$this->registry->hasClass($class)) {
            $this->registry->register($class, $slug);
        }

        $instance = new $class();
        $instance->hydrate($initialProps);
        $instanceId = IslandId::generate();

        $payload = [
            'v' => 1,
            'slug' => $slug,
            'instanceId' => $instanceId,
            'class' => $class,
            'state' => $instance->getReactiveState(),
        ];

        $snapshot = $this->signer->createToken($payload);

        return new RenderedComponent($instance, $slug, $instanceId, $snapshot);
    }
}
