"use strict";
var WoprIslands = (() => {
  var __defProp = Object.defineProperty;
  var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
  var __getOwnPropNames = Object.getOwnPropertyNames;
  var __hasOwnProp = Object.prototype.hasOwnProperty;
  var __export = (target, all) => {
    for (var name in all)
      __defProp(target, name, { get: all[name], enumerable: true });
  };
  var __copyProps = (to, from, except, desc) => {
    if (from && typeof from === "object" || typeof from === "function") {
      for (let key of __getOwnPropNames(from))
        if (!__hasOwnProp.call(to, key) && key !== except)
          __defProp(to, key, { get: () => from[key], enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable });
    }
    return to;
  };
  var __toCommonJS = (mod) => __copyProps(__defProp({}, "__esModule", { value: true }), mod);

  // js/src/index.ts
  var index_exports = {};
  __export(index_exports, {
    attachIsland: () => attachIsland,
    attachIslandsFromWindow: () => attachIslandsFromWindow,
    createComponentClient: () => createComponentClient,
    createComponentClientFromInit: () => createComponentClientFromInit
  });
  function createComponentClient(slug, instanceId, options) {
    let state = { ...options.initialState };
    let snapshot = options.snapshot;
    const listeners = /* @__PURE__ */ new Map();
    const emit = (key, value) => {
      const set = listeners.get(key);
      if (!set) {
        return;
      }
      for (const fn of set) {
        fn(value);
      }
    };
    const emitAll = () => {
      for (const key of Object.keys(state)) {
        emit(key, state[key]);
      }
    };
    const requestUpdate = async (body) => {
      const url = `${options.restEndpointBaseUrl}/${encodeURIComponent(slug)}/${encodeURIComponent(instanceId)}/update`;
      const headers = {
        "Content-Type": "application/json"
      };
      if (options.nonce) {
        headers["X-WP-Nonce"] = options.nonce;
      }
      if (typeof console !== "undefined") {
        console.log("[WoprIslands] request", {
          url,
          body,
          hasWpNonce: Boolean(options.nonce)
        });
      }
      const res = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers,
        body: JSON.stringify({
          snapshot,
          ...body
        })
      });
      if (!res.ok) {
        const text = await res.text().catch(() => "");
        if (typeof console !== "undefined") {
          console.log("[WoprIslands] request failed", { status: res.status, text });
        }
        throw new Error(`WOPR Islands update failed (${res.status}): ${text || res.statusText}`);
      }
      const data = await res.json();
      if (!data || typeof data.snapshot !== "string" || typeof data.state !== "object") {
        throw new Error("WOPR Islands update returned an unexpected payload.");
      }
      state = data.state;
      snapshot = data.snapshot;
      emitAll();
      if (typeof console !== "undefined") {
        console.log("[WoprIslands] request ok", { state, snapshot: "[omitted]" });
      }
      return data;
    };
    return {
      slug,
      instanceId,
      getState() {
        return { ...state };
      },
      getSnapshot() {
        return snapshot;
      },
      on(key, listener) {
        let set = listeners.get(key);
        if (!set) {
          set = /* @__PURE__ */ new Set();
          listeners.set(key, set);
        }
        set.add(listener);
      },
      off(key, listener) {
        listeners.get(key)?.delete(listener);
      },
      update(key, value) {
        return requestUpdate({ patch: { [key]: value } });
      },
      call(name, ...args) {
        return requestUpdate({ action: { name, args } });
      }
    };
  }
  function createComponentClientFromInit(init, extra) {
    return createComponentClient(init.slug, init.instanceId, {
      initialState: init.initialState,
      snapshot: init.snapshot,
      restEndpointBaseUrl: init.restEndpointBaseUrl,
      nonce: extra?.nonce
    });
  }
  function queryOne(sel, root) {
    return (root ?? document).querySelector(sel);
  }
  function queryAll(sel, root) {
    return Array.prototype.slice.call((root ?? document).querySelectorAll(sel));
  }
  function attachIsland(init, options) {
    const root = options?.root ?? document;
    const win = window;
    const nonce = options?.nonce ?? win.__WOPR_ISLANDS_NONCE ?? null;
    const el = queryOne(
      `[data-wopr-island="${CSS.escape(init.slug)}"][data-wopr-instance="${CSS.escape(init.instanceId)}"]`,
      root
    );
    if (!el) {
      return;
    }
    const client = createComponentClientFromInit(init, { nonce: nonce ?? void 0 });
    const reactiveKeys = Array.isArray(init.reactiveSchema) && init.reactiveSchema.length ? init.reactiveSchema.map((f) => f.wire).filter(Boolean) : Object.keys(client.getState());
    const renderKey = (key, value) => {
      const nodes = queryAll(`[data-wopr-bind="${CSS.escape(key)}"]`, el);
      for (const node of nodes) {
        node.textContent = value == null ? "" : String(value);
      }
    };
    for (const key of reactiveKeys) {
      client.on(key, (value) => renderKey(key, value));
    }
    const state = client.getState();
    for (const key of reactiveKeys) {
      renderKey(key, state[key]);
    }
    let updating = false;
    queryAll("[data-wopr-action]", el).forEach((btn) => {
      btn.addEventListener("click", async () => {
        if (updating) {
          return;
        }
        updating = true;
        const name = btn.getAttribute("data-wopr-action") || "";
        try {
          const rawArgs = btn.getAttribute("data-wopr-action-args");
          let args = [];
          if (rawArgs && rawArgs.trim() !== "") {
            try {
              const parsed = JSON.parse(rawArgs);
              if (Array.isArray(parsed)) {
                args = parsed;
              }
            } catch {
            }
          }
          await client.call(name, ...args);
        } finally {
          updating = false;
        }
      });
    });
    if (init.slug === "view-counter") {
      const st = client.getState();
      const viewNonce = typeof st.viewNonce === "string" ? st.viewNonce : "";
      const already = el.dataset.woprViewIncremented === "1";
      if (viewNonce && !already) {
        el.dataset.woprViewIncremented = "1";
        if (typeof console !== "undefined") {
          console.log("[WoprIslands] view-counter: calling increment", {
            instanceId: init.instanceId,
            postId: st.postId,
            hasViewNonce: true
          });
        }
        void client.call("increment", viewNonce).catch((err) => {
          if (typeof console !== "undefined") {
            console.log("[WoprIslands] view-counter: increment failed", err);
          }
        });
      }
    }
  }
  function attachIslandsFromWindow(options) {
    const win = window;
    const inits = Array.isArray(win.__WOPR_ISLANDS_INIT) ? win.__WOPR_ISLANDS_INIT : [];
    for (let i = 0; i < inits.length; i++) {
      const init = inits[i];
      if (init && init.slug && init.instanceId) {
        attachIsland(init, options);
      }
    }
  }
  if (typeof window !== "undefined" && typeof document !== "undefined") {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", () => attachIslandsFromWindow());
    } else {
      attachIslandsFromWindow();
    }
  }
  return __toCommonJS(index_exports);
})();
//# sourceMappingURL=wopr-islands.js.map
