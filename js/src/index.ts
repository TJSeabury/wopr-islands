/** Mirrors PHP {@see \Tjseabury\WoprIslands\Component::reactiveSchema()} */
export type ReactiveFieldSchema = {
  wire: string;
  debounceMs: number | null;
  defer: boolean;
};

/** Mirrors PHP {@see \Tjseabury\WoprIslands\Component::actionSchema()} */
export type ActionFieldSchema = {
  wire: string;
};

export type IslandInitData = {
  slug: string;
  instanceId: string;
  initialState: Record<string, unknown>;
  snapshot: string;
  restNamespace: string;
  restEndpointBaseUrl: string;
  /** When present, use for automatic input binding / debounce (Livewire-style). */
  reactiveSchema?: ReactiveFieldSchema[];
  actionSchema?: ActionFieldSchema[];
};

export type CreateClientOptions = {
  initialState: Record<string, unknown>;
  snapshot: string;
  /** Base URL with no trailing slash, e.g. from PHP WoprIslands::restEndpointBaseUrl() */
  restEndpointBaseUrl: string;
  /** WordPress REST nonce for cookie-authenticated requests */
  nonce?: string;
};

type Listener = (value: unknown) => void;

export type ComponentClient = {
  readonly slug: string;
  readonly instanceId: string;
  getState(): Record<string, unknown>;
  getSnapshot(): string;
  on(key: string, listener: Listener): void;
  off(key: string, listener: Listener): void;
  update(key: string, value: unknown): Promise<UpdateResult>;
  call(name: string, ...args: unknown[]): Promise<UpdateResult>;
};

export type UpdateResult = {
  state: Record<string, unknown>;
  snapshot: string;
};

type WindowWithWoprIslands = Window & {
  __WOPR_ISLANDS_INIT?: IslandInitData[];
  __WOPR_ISLANDS_NONCE?: string | null;
};

export function createComponentClient(
  slug: string,
  instanceId: string,
  options: CreateClientOptions
): ComponentClient {
  let state: Record<string, unknown> = { ...options.initialState };
  let snapshot = options.snapshot;
  const listeners = new Map<string, Set<Listener>>();

  const emit = (key: string, value: unknown): void => {
    const set = listeners.get(key);
    if (!set) {
      return;
    }
    for (const fn of set) {
      fn(value);
    }
  };

  const emitAll = (): void => {
    for (const key of Object.keys(state)) {
      emit(key, state[key]);
    }
  };

  const requestUpdate = async (body: {
    patch?: Record<string, unknown>;
    action?: { name: string; args: unknown[] };
  }): Promise<UpdateResult> => {
    const url = `${options.restEndpointBaseUrl}/${encodeURIComponent(slug)}/${encodeURIComponent(instanceId)}/update`;
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
    };
    if (options.nonce) {
      headers['X-WP-Nonce'] = options.nonce;
    }

    if (typeof console !== 'undefined') {
      console.log('[WoprIslands] request', {
        url,
        body,
        hasWpNonce: Boolean(options.nonce),
      });
    }

    const res = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers,
      body: JSON.stringify({
        snapshot,
        ...body,
      }),
    });

    if (!res.ok) {
      const text = await res.text().catch(() => '');
      if (typeof console !== 'undefined') {
        console.log('[WoprIslands] request failed', { status: res.status, text });
      }
      throw new Error(`WOPR Islands update failed (${res.status}): ${text || res.statusText}`);
    }

    const data = (await res.json()) as UpdateResult;
    if (!data || typeof data.snapshot !== 'string' || typeof data.state !== 'object') {
      throw new Error('WOPR Islands update returned an unexpected payload.');
    }

    state = data.state as Record<string, unknown>;
    snapshot = data.snapshot;
    emitAll();

    if (typeof console !== 'undefined') {
      console.log('[WoprIslands] request ok', { state, snapshot: '[omitted]' });
    }

    return data;
  };

  return {
    slug,
    instanceId,

    getState(): Record<string, unknown> {
      return { ...state };
    },

    getSnapshot(): string {
      return snapshot;
    },

    on(key: string, listener: Listener): void {
      let set = listeners.get(key);
      if (!set) {
        set = new Set();
        listeners.set(key, set);
      }
      set.add(listener);
    },

    off(key: string, listener: Listener): void {
      listeners.get(key)?.delete(listener);
    },

    update(key: string, value: unknown): Promise<UpdateResult> {
      return requestUpdate({ patch: { [key]: value } });
    },

    call(name: string, ...args: unknown[]): Promise<UpdateResult> {
      return requestUpdate({ action: { name, args } });
    },
  };
}

export function createComponentClientFromInit(
  init: IslandInitData,
  extra?: { nonce?: string }
): ComponentClient {
  return createComponentClient(init.slug, init.instanceId, {
    initialState: init.initialState,
    snapshot: init.snapshot,
    restEndpointBaseUrl: init.restEndpointBaseUrl,
    nonce: extra?.nonce,
  });
}

export type AttachIslandOptions = {
  /** Optional root to query within. Defaults to document. */
  root?: ParentNode;
  /** Override nonce (otherwise reads window.__WOPR_ISLANDS_NONCE). */
  nonce?: string | null;
};

function queryOne(sel: string, root?: ParentNode): Element | null {
  return (root ?? document).querySelector(sel);
}

function queryAll(sel: string, root?: ParentNode): Element[] {
  return Array.prototype.slice.call((root ?? document).querySelectorAll(sel));
}

/**
 * Attaches DOM bindings for one island init payload.
 *
 * - Updates `[data-wopr-bind="key"]` nodes when the server returns new state.
 * - Wires `[data-wopr-action]` buttons to server actions.
 */
export function attachIsland(init: IslandInitData, options?: AttachIslandOptions): void {
  const root = options?.root ?? document;
  const win = window as WindowWithWoprIslands;
  const nonce = options?.nonce ?? win.__WOPR_ISLANDS_NONCE ?? null;

  const el = queryOne(
    `[data-wopr-island="${CSS.escape(init.slug)}"][data-wopr-instance="${CSS.escape(init.instanceId)}"]`,
    root
  );
  if (!el) {
    return;
  }

  const client = createComponentClientFromInit(init, { nonce: nonce ?? undefined });

  const reactiveKeys =
    Array.isArray(init.reactiveSchema) && init.reactiveSchema.length
      ? init.reactiveSchema.map((f) => f.wire).filter(Boolean)
      : Object.keys(client.getState());

  const renderKey = (key: string, value: unknown): void => {
    const nodes = queryAll(`[data-wopr-bind="${CSS.escape(key)}"]`, el);
    for (const node of nodes) {
      (node as HTMLElement).textContent = value == null ? '' : String(value);
    }
  };

  for (const key of reactiveKeys) {
    client.on(key, (value) => renderKey(key, value));
  }

  // Initial render.
  const state = client.getState();
  for (const key of reactiveKeys) {
    renderKey(key, state[key]);
  }

  // Action wiring.
  let updating = false;
  queryAll('[data-wopr-action]', el).forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (updating) {
        return;
      }
      updating = true;
      const name = btn.getAttribute('data-wopr-action') || '';
      try {
        const rawArgs = btn.getAttribute('data-wopr-action-args');
        let args: unknown[] = [];
        if (rawArgs && rawArgs.trim() !== '') {
          try {
            const parsed = JSON.parse(rawArgs);
            if (Array.isArray(parsed)) {
              args = parsed;
            }
          } catch {
            // Ignore invalid args attribute.
          }
        }

        await client.call(name, ...args);
      } finally {
        updating = false;
      }
    });
  });

  // Special case (dev stub): ViewCounter auto-increments once per rendered instance.
  // Uses a one-time nonce provided in state and cleared server-side after a successful call.
  if (init.slug === 'view-counter') {
    const st = client.getState() as Record<string, unknown>;
    const viewNonce = typeof st.viewNonce === 'string' ? st.viewNonce : '';
    const already = (el as HTMLElement).dataset.woprViewIncremented === '1';

    if (viewNonce && !already) {
      (el as HTMLElement).dataset.woprViewIncremented = '1';
      if (typeof console !== 'undefined') {
        console.log('[WoprIslands] view-counter: calling increment', {
          instanceId: init.instanceId,
          postId: st.postId,
          hasViewNonce: true,
        });
      }
      void client.call('increment', viewNonce).catch((err) => {
        if (typeof console !== 'undefined') {
          console.log('[WoprIslands] view-counter: increment failed', err);
        }
      });
    }
  }
}

/**
 * Attaches all islands described by `window.__WOPR_ISLANDS_INIT`.
 * Safe to call multiple times (it will re-attach handlers if you do).
 */
export function attachIslandsFromWindow(options?: AttachIslandOptions): void {
  const win = window as WindowWithWoprIslands;
  const inits = Array.isArray(win.__WOPR_ISLANDS_INIT) ? win.__WOPR_ISLANDS_INIT : [];
  for (let i = 0; i < inits.length; i++) {
    const init = inits[i];
    if (init && init.slug && init.instanceId) {
      attachIsland(init, options);
    }
  }
}

// Default behavior for the distributed bundle: auto-attach on DOM ready.
if (typeof window !== 'undefined' && typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => attachIslandsFromWindow());
  } else {
    attachIslandsFromWindow();
  }
}
