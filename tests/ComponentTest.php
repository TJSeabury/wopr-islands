<?php

declare(strict_types=1);

namespace Tjseabury\WoprIslands\Tests;

use PHPUnit\Framework\TestCase;
use Tjseabury\WoprIslands\Attributes\Action;
use Tjseabury\WoprIslands\Attributes\ComponentSlug;
use Tjseabury\WoprIslands\Attributes\Reactive;
use Tjseabury\WoprIslands\Component;
use Tjseabury\WoprIslands\ComponentRegistry;
use Tjseabury\WoprIslands\ComponentFactory;
use Tjseabury\WoprIslands\Snapshot\SnapshotSigner;

final class ComponentTest extends TestCase
{
    public function testReactiveWireKeyMapsStateAndHydration(): void
    {
        $c = new class () extends Component {
            #[Reactive(wire: 'count')]
            public int $internalCount = 0;

            public function render(): string
            {
                return (string) $this->internalCount;
            }
        };

        $this->assertSame(['count'], $c::reactivePropertyNames());
        $this->assertSame(['internalCount' => 'count'], array_column($c::reactiveBindings(), 'wire', 'property'));

        $c->hydrate(['count' => 7]);
        $this->assertSame(7, $c->internalCount);
        $this->assertSame(['count' => 7], $c->getReactiveState());

        $c->applyPatch(['count' => 8]);
        $this->assertSame(8, $c->internalCount);
    }

    public function testReactiveSchemaIncludesDebounceHints(): void
    {
        $c = new class () extends Component {
            #[Reactive(debounceMs: 250, defer: true)]
            public string $label = '';

            public function render(): string
            {
                return $this->label;
            }
        };

        $schema = $c::reactiveSchema();
        $this->assertSame('label', $schema[0]['wire']);
        $this->assertSame(250, $schema[0]['debounceMs']);
        $this->assertTrue($schema[0]['defer']);
    }

    public function testActionWireNameDispatchesMethod(): void
    {
        $c = new class () extends Component {
            #[Reactive]
            public int $n = 0;

            #[Action(name: 'tick')]
            public function bump(): void
            {
                $this->n++;
            }

            public function render(): string
            {
                return (string) $this->n;
            }
        };

        $this->assertArrayHasKey('tick', $c::actionMethods());
        $this->assertArrayNotHasKey('bump', $c::actionMethods());

        $c->callAction('tick');
        $this->assertSame(1, $c->n);
    }

    public function testRenderedInitDataIncludesSchemas(): void
    {
        $signer = new SnapshotSigner('k');
        $registry = new ComponentRegistry();
        $factory = new ComponentFactory($signer, $registry);

        $rendered = $factory->make(SchemaIsland::class, ['value' => 3]);

        $init = $rendered->getInitData();
        $this->assertSame([['wire' => 'value', 'debounceMs' => null, 'defer' => false]], $init['reactiveSchema']);
        $this->assertSame([['wire' => 'ping']], $init['actionSchema']);
        $this->assertSame(['value' => 3], $init['initialState']);
    }
}

#[ComponentSlug('schema-island')]
final class SchemaIsland extends Component
{
    #[Reactive]
    public int $value = 0;

    #[Action(name: 'ping')]
    public function noop(): void
    {
    }

    public function render(): string
    {
        return (string) $this->value;
    }
}
