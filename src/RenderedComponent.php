<?php

declare(strict_types=1);

namespace Tjseabury\WoprIslands;

final class RenderedComponent
{
    public function __construct(
        private readonly Component $component,
        private readonly string $slug,
        private readonly string $instanceId,
        private readonly string $snapshot
    ) {
    }

    public function component(): Component
    {
        return $this->component;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function instanceId(): string
    {
        return $this->instanceId;
    }

    public function snapshot(): string
    {
        return $this->snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    public function getInitData(): array
    {
        return [
            'slug' => $this->slug,
            'instanceId' => $this->instanceId,
            'initialState' => $this->component->getReactiveState(),
            'snapshot' => $this->snapshot,
            'restNamespace' => WoprIslands::REST_NAMESPACE,
            'restEndpointBaseUrl' => WoprIslands::restEndpointBaseUrl(),
        ];
    }

    public function toHtml(): string
    {
        $html = $this->component->render();

        $attrs = sprintf(
            ' data-wopr-island="%s" data-wopr-instance="%s" data-wopr-snapshot="%s"',
            htmlspecialchars($this->slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($this->instanceId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($this->snapshot, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );

        return '<div' . $attrs . '>' . $html . '</div>';
    }

    public function inlineInitScript(): string
    {
        $json = json_encode($this->getInitData(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);

        return '<script>(function(){window.__WOPR_ISLANDS_INIT=window.__WOPR_ISLANDS_INIT||[];window.__WOPR_ISLANDS_INIT.push('
            . $json . ');})();</script>';
    }
}
