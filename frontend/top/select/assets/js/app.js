"use strict";

document.addEventListener("DOMContentLoaded", () => {
  const yearEl = document.getElementById("y");
  if (yearEl) {
    yearEl.textContent = new Date().getFullYear().toString();
  }
});
