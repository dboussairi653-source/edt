// assets/js/theme.js
(function () {
  const root = document.documentElement;

  function setTheme(t) {
    root.setAttribute("data-theme", t);
    localStorage.setItem("theme", t);

    const icon = document.querySelector("[data-theme-icon]");
    if (icon) {
      icon.classList.remove("fa-moon", "fa-sun");
      icon.classList.add(t === "dark" ? "fa-sun" : "fa-moon");
    }
  }

  const saved = localStorage.getItem("theme");
  setTheme(saved === "light" || saved === "dark" ? saved : "dark");

  document.addEventListener("DOMContentLoaded", () => {
    const btn = document.querySelector("[data-theme-toggle]");
    if (!btn) return;

    btn.addEventListener("click", () => {
      const current = root.getAttribute("data-theme") || "dark";
      setTheme(current === "dark" ? "light" : "dark");
    });
  });
})();
