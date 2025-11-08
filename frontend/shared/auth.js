(function () {
  "use strict";

  const script = document.currentScript;
  const origin = window.location.origin;

  const normalizeRoot = (value) => {
    if (!value) {
      return "/public_html/";
    }
    if (/^https?:\/\//i.test(value)) {
      const url = new URL(value);
      return url.pathname.endsWith("/") ? url.pathname : `${url.pathname}/`;
    }
    if (value.startsWith("/")) {
      return value.endsWith("/") ? value : `${value}/`;
    }
    return `/${value.replace(/^\/+/, "")}${value.endsWith("/") ? "" : "/"}`;
  };

  const rootPath = normalizeRoot(script?.dataset.root);

  const toAbsolute = (value, fallback) => {
    const target = value ?? fallback;
    if (!target) {
      return origin;
    }
    if (/^https?:\/\//i.test(target)) {
      return target;
    }
    if (target.startsWith("/")) {
      return `${origin}${target}`;
    }
    return `${origin}${rootPath}${target}`;
  };

  const loginPath = toAbsolute(script?.dataset.login, "frontend/top/account/login.html");
  const sessionUrl = toAbsolute(script?.dataset.session, "backend/account/session.php");
  const logoutUrl = toAbsolute(script?.dataset.logout, "backend/account/logout.php");
  const redirectOnFail = script?.dataset.redirectOnFail !== "false";

  const state = {
    account: null,
    readyCallbacks: [],
  };

  const updateAccountDom = () => {
    const apply = (account) => {
      document.querySelectorAll("[data-account-name]").forEach((el) => {
        el.textContent = account?.name ?? "";
      });
      document.querySelectorAll("[data-account-section]").forEach((el) => {
        el.classList.toggle("is-authenticated", Boolean(account));
      });
      document.querySelectorAll("[data-logout-button]").forEach((el) => {
        el.disabled = !account;
        el.onclick = (event) => {
          event.preventDefault();
          window.AccountAuth.logout();
        };
      });
    };

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", () => apply(state.account), { once: true });
    } else {
      apply(state.account);
    }
  };

  const setAccount = (account) => {
    state.account = account;
    updateAccountDom();
    state.readyCallbacks.splice(0).forEach((cb) => {
      try {
        cb(account);
      } catch (error) {
        console.error("AccountAuth ready callback error:", error);
      }
    });
  };

  const ensureRedirect = () => {
    if (!redirectOnFail) {
      return;
    }

    try {
      const currentUrl = new URL(window.location.href);
      const loginUrl = new URL(loginPath, origin);
      if (currentUrl.pathname === loginUrl.pathname) {
        return;
      }
    } catch (error) {
      console.warn("Failed to evaluate redirect target:", error);
    }

    window.location.replace(loginPath);
  };

  window.AccountAuth = {
    get account() {
      return state.account;
    },
    ready(callback) {
      if (typeof callback !== "function") {
        return;
      }
      if (state.account) {
        callback(state.account);
      } else {
        state.readyCallbacks.push(callback);
      }
    },
    async logout() {
      try {
        await fetch(logoutUrl, {
          method: "POST",
          credentials: "include",
        });
      } catch (error) {
        console.error("Failed to logout:", error);
      } finally {
        setAccount(null);
        window.location.replace(loginPath);
      }
    },
  };

  const checkSession = async () => {
    try {
      const response = await fetch(sessionUrl, { credentials: "include" });

      if (response.status === 401 || response.status === 403) {
        ensureRedirect();
        return;
      }

      if (!response.ok) {
        console.warn(
          `Session check returned unexpected status ${response.status}`
        );
        return;
      }

      const payload = await response.json();
      if (!payload || payload.authenticated !== true || !payload.account) {
        ensureRedirect();
        return;
      }

      setAccount(payload.account);
    } catch (error) {
      console.error("Failed to verify session:", error);
      // ネットワークエラーなどではリダイレクトしない。ユーザーに任せる。
    }
  };

  checkSession();
})();
