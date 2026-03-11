(function(){
  const reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function createCursorGlow(){
    // disable on touch devices / reduced motion
    if (reduceMotion) return;
    if (window.matchMedia && window.matchMedia("(pointer: coarse)").matches) return;

    const el = document.createElement("div");
    el.className = "fx-cursor";
    document.body.appendChild(el);

    let shown = false;
    window.addEventListener("mousemove", (e) => {
      el.style.left = e.clientX + "px";
      el.style.top  = e.clientY + "px";
      if (!shown){
        el.style.opacity = ".95";
        shown = true;
      }
    });

    window.addEventListener("mouseleave", () => {
      el.style.opacity = "0";
      shown = false;
    });
  }

  function spotlightCards(){
    if (reduceMotion) return;

    const cards = document.querySelectorAll(".card, .action-card");
    cards.forEach(card => {
      card.addEventListener("mousemove", (e) => {
        const r = card.getBoundingClientRect();
        const x = ((e.clientX - r.left) / r.width) * 100;
        const y = ((e.clientY - r.top) / r.height) * 100;
        card.style.setProperty("--mx", x + "%");
        card.style.setProperty("--my", y + "%");
      });
    });
  }

  function rippleButtons(){
    if (reduceMotion) return;

    document.addEventListener("click", (e) => {
      const btn = e.target.closest(".btn");
      if (!btn) return;

      // Don't ripple for links styled as button if it navigates instantly (still OK)
      const r = btn.getBoundingClientRect();
      const x = e.clientX - r.left;
      const y = e.clientY - r.top;

      const ripple = document.createElement("span");
      ripple.className = "ripple";
      ripple.style.left = x + "px";
      ripple.style.top  = y + "px";
      btn.appendChild(ripple);

      setTimeout(() => ripple.remove(), 700);
    });
  }

  window.UI = {
    confirmDelete(url, text = "Action irréversible") {
      if (!window.Swal) return (window.location = url);
      Swal.fire({
        title: "Supprimer ?",
        text,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Oui",
        cancelButtonText: "Annuler",
        confirmButtonColor: "#9D7BFF"
      }).then(r => { if (r.isConfirmed) window.location = url; });
    },

    toast(msg, type="success"){
      if (!window.Swal) return alert(msg);
      Swal.fire({
        toast:true,
        position:"top-end",
        showConfirmButton:false,
        timer:2200,
        timerProgressBar:true,
        icon:type,
        title:msg
      });
    },

    tableSearch(inputSelector, tableSelector){
      const input = document.querySelector(inputSelector);
      const table = document.querySelector(tableSelector);
      if (!input || !table) return;

      input.addEventListener("input", () => {
        const q = input.value.toLowerCase().trim();
        table.querySelectorAll("tbody tr").forEach(tr => {
          const show = tr.innerText.toLowerCase().includes(q);
          tr.style.display = show ? "" : "none";
        });
      });
    }
  };

  document.addEventListener("DOMContentLoaded", () => {
    createCursorGlow();
    spotlightCards();
    rippleButtons();
  });
})();
