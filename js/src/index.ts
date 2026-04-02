export type IslandInitData = {
  slug: string;
  instanceId: string;
  initialState: Record<string, unknown>;
  snapshot: string;
  restNamespace: string;
  restEndpointBaseUrl: string;
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
      throw new Error(`WOPR Islands update failed (${res.status}): ${text || res.statusText}`);
    }

    const data = (await res.json()) as UpdateResult;
    if (!data || typeof data.snapshot !== 'string' || typeof data.state !== 'object') {
      throw new Error('WOPR Islands update returned an unexpected payload.');
    }

    state = data.state as Record<string, unknown>;
    snapshot = data.snapshot;
    emitAll();

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
