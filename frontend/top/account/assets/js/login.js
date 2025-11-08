"use strict";

document.addEventListener("DOMContentLoaded", () => {
  const yearEl = document.getElementById("currentYear");
  if (yearEl) {
    yearEl.textContent = new Date().getFullYear().toString();
  }

  const form = document.getElementById("loginForm");
  const nameInput = document.getElementById("nameInput");
  const button = document.getElementById("loginButton");
  const messageEl = document.getElementById("message");

  if (!form || !nameInput || !button || !messageEl) {
    return;
  }

  // ===== ベースパスの決定 (/public_html のところまで) =====
  // ===== ベースパスの決定 =====
  // 例: /public_html/frontend/top/account/login.html から /public_html 部分を取り出す
  const origin = window.location.origin;

  const basePrefix = (() => {
    const path = window.location.pathname || "";
    // /public_html/frontend/top/account/login.html という前提で、/public_html 部分を抜き出す
    const m = path.match(/^(.*)\/frontend\/top\/account\/login\.html$/);
    if (m && m[1]) {
      return m[1]; // 例: "/public_html"
    }
    return "";     // 想定外パスの場合はルート扱い
  })();

  const basePath = origin + basePrefix; // 例: https://xxx.ngrok-free.app/public_html

  const loginEndpoint   = `${basePath}/backend/account/login.php`;
  const sessionEndpoint = `${basePath}/backend/account/session.php`;
  const selectPageUrl   = `${basePath}/frontend/top/select/select_page.html`;

  let processing = false;

  const updateFormState = () => {
    button.disabled = processing;
    form.classList.toggle("book-form--disabled", processing);
  };

  const showMessage = (text, type) => {
    messageEl.textContent = text;
    messageEl.classList.remove("error", "success");
    if (type) {
      messageEl.classList.add(type);
    }
  };

  // ===== すでにログインしているか軽くチェック =====
  const checkSession = async () => {
    try {
      const response = await fetch(sessionEndpoint, {
        credentials: "include",
        cache: "no-store",
      });
      if (!response.ok) return;

      const ct = response.headers.get("content-type") || "";
      if (!ct.includes("application/json")) return;

      const payload = await response.json();
      if (payload && payload.authenticated) {
        // すでにログイン済みならそのまま select_page へ
        window.location.href = selectPageUrl;
      }
    } catch (e) {
      // セッションチェック失敗時は何もしない（画面だけ表示）
      console.warn("session check failed", e);
    }
  };

  checkSession();

  // ===== フォーム送信（ログイン） =====
  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const trimmed = nameInput.value.trim();
    if (!trimmed) {
      nameInput.focus();
      showMessage("お名前を入力してください。", "error");
      return;
    }

    processing = true;
    updateFormState();
    showMessage("", "");

    try {
      const formData = new FormData();
      formData.set("name", trimmed);

      const response = await fetch(loginEndpoint, {
        method: "POST",
        body: formData,
        credentials: "include",
      });

      const ct = response.headers.get("content-type") || "";

      // ★ JSON じゃなければそのままテキストを画面に出す
      if (!ct.includes("application/json")) {
        const text = await response.text();
        showMessage("JSONではない応答が返ってきました:\n" + text, "error");
        processing = false;
        updateFormState();
        return;
      }

      const result = await response.json();

      // ★ 返ってきた status をそのまま表示
      if (!response.ok || result.status !== "success") {
        const msg = (result && result.message)
          ? `status=${result.status} / message=${result.message}`
          : `status=${result && result.status}`;
        showMessage("ログインに失敗しました: " + msg, "error");
        processing = false;
        updateFormState();
        return;
      }

      showMessage("ログインしました。ページにご案内します…", "success");
      setTimeout(() => {
        window.location.href = selectPageUrl;
      }, 600);

    } catch (error) {
      console.error("Login error:", error);
      showMessage(
        error instanceof Error ? error.message : "通信に失敗しました。時間をおいて再度お試しください。",
        "error"
      );
      processing = false;
      updateFormState();
    }
  });


  updateFormState();
});

