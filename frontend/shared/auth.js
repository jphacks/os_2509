(function () {
  "use strict";

  const script = document.currentScript;
  const root = script?.dataset.root ?? "../../";
  const loginPath = script?.dataset.login ?? `${root}frontend/top/account/login.html`;
  const sessionUrl = script?.dataset.session ?? `${root}backend/account/session.php`;
  const logoutUrl = script?.dataset.logout ?? `${root}backend/account/logout.php`;
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
    if (redirectOnFail) {
      window.location.replace(loginPath);
    }
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
          credentials: "same-origin",
        });
      } catch (error) {
        console.error("Failed to logout:", error);
      } finally {
        setAccount(null);
        window.location.replace(loginPath);
      }
    },
  };

  fetch(sessionUrl, { credentials: "same-origin" })
    .then(async (response) => {
      if (!response.ok) {
        throw new Error(`Session check failed with status ${response.status}`);
      }
      return response.json();
    })
    .then((payload) => {
      if (!payload || payload.authenticated !== true || !payload.account) {
        throw new Error("Not authenticated");
      }
      setAccount(payload.account);
    })
    .catch((error) => {
      console.warn("Authentication required:", error);
      ensureRedirect();
    });
})();
