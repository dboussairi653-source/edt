// assets/js/login_fx.js
document.addEventListener("DOMContentLoaded", () => {
  const card = document.querySelector(".login-card");
  if (!card) return;

  // Micro parallax (très léger)
  const max = 6;
  function onMove(e){
    const r = card.getBoundingClientRect();
    const x = (e.clientX - (r.left + r.width/2)) / (r.width/2);
    const y = (e.clientY - (r.top + r.height/2)) / (r.height/2);
    card.style.transform = `translateY(0) rotateX(${(-y*max)}deg) rotateY(${(x*max)}deg)`;
  }
  function reset(){
    card.style.transform = "translateY(0) rotateX(0deg) rotateY(0deg)";
  }

  card.addEventListener("mousemove", onMove);
  card.addEventListener("mouseleave", reset);

  // Glow focus inputs
  document.querySelectorAll(".login-form .form-control").forEach(inp=>{
    inp.addEventListener("focus", ()=> inp.closest(".login-form").style.boxShadow =
      "0 0 0 4px rgba(168,85,247,.10), 0 24px 60px rgba(0,0,0,.14)");
    inp.addEventListener("blur", ()=> inp.closest(".login-form").style.boxShadow = "");
  });
});
