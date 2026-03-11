document.addEventListener("DOMContentLoaded", () => {
  // AOS
  if (window.AOS) AOS.init({ duration: 650, once: true });

  // SweetAlert delete links
  document.querySelectorAll("a[data-confirm-delete]").forEach(a => {
    a.addEventListener("click", (e) => {
      e.preventDefault();
      const url = a.getAttribute("href");
      const text = a.dataset.confirmText || "Action irréversible";
      if (window.UI) UI.confirmDelete(url, text);
      else window.location = url;
    });
  });

  // THEME TOGGLE (dark/light)
  const btn = document.querySelector("[data-theme-toggle]");
  const icon = document.querySelector("[data-theme-icon]");

  function applyTheme(t) {
    document.documentElement.setAttribute("data-theme", t);
    localStorage.setItem("theme", t);

    // switch icon
    if (icon) {
      icon.classList.remove("fa-moon", "fa-sun");
      icon.classList.add(t === "dark" ? "fa-moon" : "fa-sun");
    }
  }

  // init theme
  const saved = localStorage.getItem("theme");
  applyTheme(saved === "light" ? "light" : "dark");

  // click toggle
  if (btn) {
    btn.addEventListener("click", () => {
      const current = document.documentElement.getAttribute("data-theme") || "dark";
      applyTheme(current === "dark" ? "light" : "dark");
    });
  }
});
function goBack() {
  if (window.history.length > 1) {
    window.history.back();
  } else {
    window.location.href = "/edt/index.php";
  }
}

