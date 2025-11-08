"use strict";

document.addEventListener("DOMContentLoaded", () => {
  const yearEl = document.getElementById("currentYear");
  if (yearEl) {
    yearEl.textContent = new Date().getFullYear().toString();
  }

  const origin = window.location.origin;
  const basePath = `${origin}/public_html`;
  const sessionEndpoint = `${basePath}/backend/account/session.php`;
  const loginEndpoint = `${basePath}/backend/account/login.php`;
  const selectPageUrl = `${basePath}/frontend/top/select/select_page.html`;

  const bookContainer = document.getElementById("bookContainer");
  const bookScene = document.querySelector(".book-scene");
  const form = document.getElementById("loginForm");
  const nameInput = document.getElementById("nameInput");
  const button = document.getElementById("loginButton");
  const messageEl = document.getElementById("message");
  const screenFade = document.getElementById("screenFade");
  const consentBanner = document.getElementById("cookieConsent");
  const consentAcceptButton = document.getElementById("cookieConsentAccept");
  const consentDeclineButton = document.getElementById("cookieConsentDecline");

  if (!bookContainer || !form || !nameInput || !button || !messageEl) {
    return;
  }

  const DESIRED_PAGE_COUNT = 100;
  const SUCCESS_FLIP_COUNT = 10;

  let pages = [];
  let totalPages = 0;
  let currentPage = 0;
  let zIndexCounter = 0;
  let processing = false;
  let cookiesAllowed = false;

  const COOKIE_CONSENT_KEY = "connectionDiaryCookieConsent";

  const updateFormState = () => {
    const disabled = processing || !cookiesAllowed;
    button.disabled = disabled;
    form.classList.toggle("book-form--disabled", disabled);
  };

  const showConsentBanner = () => {
    consentBanner?.classList.add("is-visible");
  };

  const hideConsentBanner = () => {
    consentBanner?.classList.remove("is-visible");
  };

  const recordConsent = (value) => {
    try {
      localStorage.setItem(COOKIE_CONSENT_KEY, value);
    } catch (error) {
      console.warn("Failed to persist cookie consent:", error);
    }
  };

  const verifyCookieSupport = () => {
    try {
      const testName = "cd_cookie_test";
      const attributes = ["path=/public_html", "max-age=60"];
      if (window.location.protocol === "https:") {
        attributes.push("SameSite=None", "Secure");
      }
      document.cookie = `${testName}=1; ${attributes.join("; ")}`;
      const supported = document.cookie.includes(`${testName}=1`);
      document.cookie = `${testName}=; path=/public_html; max-age=0`;
      return supported;
    } catch (error) {
      console.warn("Cookie support check failed:", error);
      return false;
    }
  };

  const createBlankPage = (index) => {
    const page = document.createElement("div");
    page.className = "page page--inner";
    page.dataset.page = String(index);

    const front = document.createElement("div");
    front.className = "page-front inner-front";
    const frontContent = document.createElement("div");
    frontContent.className = "inner-page-content inner-page-content--blank";
    frontContent.setAttribute("aria-hidden", "true");
    front.appendChild(frontContent);

    const back = document.createElement("div");
    back.className = "page-back inner-back";
    const backPaper = document.createElement("div");
    backPaper.className = "lined-paper";
    backPaper.setAttribute("aria-hidden", "true");
    back.appendChild(backPaper);

    page.append(front, back);
    return page;
  };

  const ensurePageCount = (desiredCount) => {
    const existing = Array.from(bookContainer.querySelectorAll(".page"));
    let nextIndex = existing.length;
    while (nextIndex < desiredCount) {
      bookContainer.appendChild(createBlankPage(nextIndex));
      nextIndex += 1;
    }
    pages = Array.from(bookContainer.querySelectorAll(".page"));
    totalPages = pages.length;
    zIndexCounter = totalPages + 1;
  };

  ensurePageCount(DESIRED_PAGE_COUNT);

  if (!pages.length) {
    return;
  }

  updateFormState();

  if (screenFade) {
    screenFade.classList.remove("screen-fade--active");
    void screenFade.offsetWidth;
  }

  const delay = (ms) => new Promise((resolve) => {
    window.setTimeout(resolve, ms);
  });

  const setFastFlip = (enabled) => {
    bookContainer.classList.toggle("book-fast-flip", Boolean(enabled));
  };

  const clearTravelEffect = () => {
    setFastFlip(false);
    if (bookScene) {
      bookScene.classList.remove("book-scene--travel");
    }
    if (screenFade) {
      screenFade.classList.remove("screen-fade--active");
    }
  };

  pages.forEach((page, index) => {
    page.style.zIndex = String(totalPages - index);
  });

  const flipForward = () => {
    if (currentPage >= totalPages) {
      return;
    }
    const pageToFlip = pages[currentPage];
    pageToFlip.classList.add("flipped");
    pageToFlip.style.zIndex = String(zIndexCounter++);
    currentPage += 1;
  };

  const flipBackward = () => {
    if (currentPage <= 0) {
      return;
    }
    currentPage -= 1;
    const pageToFlip = pages[currentPage];
    pageToFlip.classList.remove("flipped");

    const resetIndex = (event) => {
      if (event.propertyName !== "transform") {
        return;
      }
      pageToFlip.style.zIndex = String(totalPages - currentPage);
      pageToFlip.removeEventListener("transitionend", resetIndex);
    };

    pageToFlip.addEventListener("transitionend", resetIndex);
  };

  const resetToCover = () => {
    clearTravelEffect();
    while (currentPage > 0) {
      flipBackward();
    }
  };

  bookContainer.addEventListener("click", (event) => {
    if (processing) {
      return;
    }
    if (event.target.closest(".cover-form")) {
      return;
    }
    if (currentPage === 0) {
      flipForward();
    }
  });

  form.addEventListener("focusin", () => {
    if (processing) {
      return;
    }
    resetToCover();
  });

  const showMessage = (text, type) => {
    messageEl.textContent = text;
    messageEl.classList.remove("error", "success");
    if (type) {
      messageEl.classList.add(type);
    }
  };

  const redirectToHome = () => {
    window.location.assign(selectPageUrl);
  };

  const playSuccessSequence = async () => {
    const targetPage = Math.min(totalPages, SUCCESS_FLIP_COUNT);
    if (currentPage >= targetPage) {
      return;
    }

    setFastFlip(true);
    await delay(140);

    while (currentPage < targetPage) {
      flipForward();
      await delay(160);
    }

    setFastFlip(false);
  };

  const travelIntoBook = async () => {
    if (!bookScene) {
      return;
    }

    bookScene.classList.add("book-scene--travel");

    await Promise.race([
      new Promise((resolve) => {
        const handleTransitionEnd = (event) => {
          if (event.target !== bookScene || event.propertyName !== "transform") {
            return;
          }
          bookScene.removeEventListener("transitionend", handleTransitionEnd);
          resolve();
        };
        bookScene.addEventListener("transitionend", handleTransitionEnd);
      }),
      delay(900),
    ]);
  };

  const triggerFade = async () => {
    if (screenFade) {
      screenFade.classList.remove("screen-fade--active");
      void screenFade.offsetWidth;
      screenFade.classList.add("screen-fade--active");
      await delay(1200);
      return;
    }
    await delay(600);
  };

  const checkSession = async () => {
    if (!cookiesAllowed) {
      return;
    }

    try {
      const response = await fetch(sessionEndpoint, {
        credentials: "include",
      });
      if (response.ok) {
        const payload = await response.json();
        if (payload && payload.authenticated) {
          processing = true;
          updateFormState();
          showMessage("すでにログインしています。ページを移動します…", "success");
          await delay(220);
          await triggerFade();
          redirectToHome();
          return;
        }
      }
    } catch (error) {
      console.warn("Failed to check existing session:", error);
    }
  };

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const trimmed = nameInput.value.trim();
    if (!trimmed) {
      resetToCover();
      nameInput.focus();
      showMessage("お名前を入力してください。", "error");
      return;
    }

    if (!cookiesAllowed) {
      showConsentBanner();
      showMessage("Cookieを許可するとログインできます。ブラウザの設定をご確認ください。", "error");
      return;
    }

    processing = true;
    updateFormState();
    showMessage("ログイン処理中です…", null);

    const formData = new FormData();
    formData.set("name", trimmed);

    try {
      const response = await fetch(loginEndpoint, {
        method: "POST",
        body: formData,
        credentials: "include",
      });

      const result = await response.json();

      if (!response.ok || result.status !== "success") {
        const errorMessage = result?.message || "ログインに失敗しました。";
        processing = false;
        updateFormState();
        showMessage(errorMessage, "error");
        clearTravelEffect();
        resetToCover();

        return;
      }

      showMessage("ログインしました。ページにご案内します…", "success");

      await delay(220);
      await playSuccessSequence();
      await travelIntoBook();
      await triggerFade();
      redirectToHome();
      return;
    } catch (error) {
      console.error("Login error:", error);
      processing = false;
      updateFormState();
      showMessage("通信に失敗しました。時間をおいて再度お試しください。", "error");
      clearTravelEffect();
      resetToCover();

    }
  });

  const initialiseCookieConsent = () => {
    let storedConsent = null;
    try {
      storedConsent = localStorage.getItem(COOKIE_CONSENT_KEY);
    } catch (error) {
      console.warn("Unable to read cookie consent from storage:", error);
    }

    if (storedConsent === "accepted") {
      cookiesAllowed = verifyCookieSupport();
      if (cookiesAllowed) {
        hideConsentBanner();
        updateFormState();
        checkSession();
      } else {
        cookiesAllowed = false;
        showConsentBanner();
        updateFormState();
        showMessage("ブラウザでCookieが無効化されています。設定を確認してください。", "error");
      }
    } else if (storedConsent === "declined") {
      cookiesAllowed = false;
      showConsentBanner();
      updateFormState();
      showMessage("Cookieを許可しない場合、ログインをご利用いただけません。", "error");
    } else {
      cookiesAllowed = false;
      showConsentBanner();
      updateFormState();
    }
  };

  consentAcceptButton?.addEventListener("click", () => {
    const supported = verifyCookieSupport();
    if (!supported) {
      showConsentBanner();
      showMessage("ブラウザの設定でCookieを有効にしてください。", "error");
      return;
    }

    cookiesAllowed = true;
    recordConsent("accepted");
    hideConsentBanner();
    updateFormState();
    showMessage("Cookieが許可されました。", "success");
    checkSession();
  });

  consentDeclineButton?.addEventListener("click", () => {
    cookiesAllowed = false;
    recordConsent("declined");
    showConsentBanner();
    updateFormState();
    showMessage("Cookieが無効なためログインできません。ブラウザの設定をご確認ください。", "error");
  });

  initialiseCookieConsent();
});
