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
    // フォームが取得できない場合は処理しない
    return;
  }

  const showMessage = (text, type) => {
    messageEl.textContent = text;
    messageEl.classList.remove("error", "success");
    if (type) {
      messageEl.classList.add(type);
    }
  };

  const redirectToHome = () => {
    window.location.assign("../top/select/select_page.html");
  };

  const checkSession = async () => {
    try {
      const response = await fetch("../../backend/account/session.php", {
        credentials: "same-origin",
      });
      if (response.ok) {
        const payload = await response.json();
        if (payload && payload.authenticated) {
          showMessage("すでにログインしています。ページを移動します…", "success");
          setTimeout(redirectToHome, 400);
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
      nameInput.focus();
      showMessage("お名前を入力してください。", "error");
      return;
    }

    button.disabled = true;
    showMessage("ログイン処理中です…", null);

    const formData = new FormData();
    formData.set("name", trimmed);

    try {
      const response = await fetch("../../backend/account/login.php", {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      });

      const result = await response.json();

      if (!response.ok || result.status !== "success") {
        const errorMessage = result.message || "ログインに失敗しました。";
        showMessage(errorMessage, "error");
        button.disabled = false;
        return;
      }

      showMessage("ログインしました。ページを移動します…", "success");
      setTimeout(() => {
        redirectToHome();
      }, 600);
    } catch (error) {
      console.error("Login error:", error);
      showMessage("通信に失敗しました。時間をおいて再度お試しください。", "error");
      button.disabled = false;
    }
  });

  checkSession();
});
