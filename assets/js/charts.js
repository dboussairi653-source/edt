(function(){
  function donut(el, justified, nonJustified){
    if (!window.Chart || !el) return;
    new Chart(el, {
      type: "doughnut",
      data: {
        labels: ["Justifiées", "Non justifiées"],
        datasets: [{
          data: [justified, nonJustified]
        }]
      },
      options: {
        plugins: { legend: { position: "bottom" } },
        cutout: "70%"
      }
    });
  }

  window.Charts = { donut };

  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("canvas[data-chart='donut']").forEach(c => {
      const j = parseInt(c.dataset.j || "0", 10);
      const nj = parseInt(c.dataset.nj || "0", 10);
      donut(c, j, nj);
    });
  });
})();
