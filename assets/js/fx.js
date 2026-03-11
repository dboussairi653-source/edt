// assets/js/fx.js
// Futuristic FX: particles + soft nebula + cinematic page transitions
// Safe: disables on reduced-motion, adapts to theme, no dependency.

(function () {
  const reduceMotion =
    (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches) ||
    false;

  // Don't run heavy effects on low-end / touch pointers
  const coarsePointer = window.matchMedia && window.matchMedia("(pointer: coarse)").matches;

  // You can tune these
  const SETTINGS = {
    maxParticles: coarsePointer ? 40 : 90,
    linkDist: coarsePointer ? 90 : 130,
    speed: coarsePointer ? 0.18 : 0.28,
    fpsCap: coarsePointer ? 40 : 60,
    overlayDurationMs: 260
  };

  if (reduceMotion) {
    // Keep only very light page transition without animation
    setupPageTransitions({ enabled: false });
    return;
  }

  // Helpers
  const root = document.documentElement;

  function getTheme() {
    const t = root.getAttribute("data-theme");
    return t === "light" ? "light" : "dark";
  }

  function getVar(name, fallback) {
    const v = getComputedStyle(root).getPropertyValue(name).trim();
    return v || fallback;
  }

  // ===== 1) Cinematic page transitions =====
  function setupPageTransitions(opts = { enabled: true }) {
    const enabled = opts.enabled !== false;

    const overlay = document.createElement("div");
    overlay.setAttribute("data-fx-overlay", "1");
    Object.assign(overlay.style, {
      position: "fixed",
      inset: "0",
      zIndex: "99999",
      pointerEvents: "none",
      opacity: "0",
      transition: enabled ? `opacity ${SETTINGS.overlayDurationMs}ms cubic-bezier(.2,.8,.2,1)` : "none",
      background:
        "radial-gradient(900px 520px at 50% 40%, rgba(157,123,255,.18), rgba(0,0,0,0) 60%)," +
        "linear-gradient(180deg, rgba(10,6,20,.55), rgba(10,6,20,.85))"
    });
    document.body.appendChild(overlay);

    // Fade in on page load
    requestAnimationFrame(() => {
      overlay.style.opacity = "0";
    });

    // Intercept internal navigation for a quick fade-out
    document.addEventListener("click", (e) => {
      const a = e.target.closest("a");
      if (!a) return;

      const href = a.getAttribute("href");
      if (!href) return;

      // ignore new tabs, anchors, downloads, external links
      if (a.target === "_blank") return;
      if (href.startsWith("#")) return;
      if (a.hasAttribute("download")) return;
      if (/^https?:\/\//i.test(href) && !href.includes(location.host)) return;
      if (href.startsWith("mailto:") || href.startsWith("tel:")) return;

      // allow JS confirm delete links etc.
      if (a.hasAttribute("data-confirm-delete")) return;

      // Only intercept same-origin navigations
      let url;
      try { url = new URL(href, location.href); } catch { return; }
      if (url.origin !== location.origin) return;

      // If it's the same page, skip
      if (url.href === location.href) return;

      e.preventDefault();

      if (!enabled) {
        location.href = url.href;
        return;
      }

      overlay.style.pointerEvents = "auto";
      overlay.style.opacity = "1";

      setTimeout(() => {
        location.href = url.href;
      }, SETTINGS.overlayDurationMs + 20);
    });
  }

  // ===== 2) Particles canvas =====
  function createCanvasLayer() {
    const canvas = document.createElement("canvas");
    canvas.setAttribute("data-fx-canvas", "1");
    Object.assign(canvas.style, {
      position: "fixed",
      inset: "0",
      width: "100%",
      height: "100%",
      zIndex: "-1",
      pointerEvents: "none",
      opacity: "0.95"
    });
    document.body.appendChild(canvas);
    return canvas;
  }

  function createNebulaLayer() {
    const nebula = document.createElement("div");
    nebula.setAttribute("data-fx-nebula", "1");
    Object.assign(nebula.style, {
      position: "fixed",
      inset: "-18%",
      zIndex: "-2",
      pointerEvents: "none",
      opacity: "0.9",
      filter: "saturate(1.15)",
      transform: "translate3d(0,0,0)",
      background:
        "radial-gradient(900px 540px at 20% 15%, rgba(157,123,255,.20), rgba(0,0,0,0) 60%)," +
        "radial-gradient(750px 460px at 85% 20%, rgba(199,182,255,.14), rgba(0,0,0,0) 62%)," +
        "radial-gradient(920px 680px at 50% 92%, rgba(109,60,255,.12), rgba(0,0,0,0) 65%)",
      transition: "opacity 260ms cubic-bezier(.2,.8,.2,1)"
    });
    document.body.appendChild(nebula);
    return nebula;
  }

  function hexToRgba(hex, a) {
    // supports #RGB or #RRGGBB
    let h = hex.replace("#", "").trim();
    if (h.length === 3) h = h.split("").map(c => c + c).join("");
    const n = parseInt(h, 16);
    const r = (n >> 16) & 255;
    const g = (n >> 8) & 255;
    const b = n & 255;
    return `rgba(${r},${g},${b},${a})`;
  }

  function buildPalette() {
    const theme = getTheme();
    const primary = getVar("--primary", theme === "light" ? "#8B5CF6" : "#9D7BFF");
    const accent  = getVar("--accent",  theme === "light" ? "#C4B5FD" : "#C7B6FF");
    const text    = getVar("--text",    theme === "light" ? "#14111F" : "#EEEAFB");

    // particle + link colors
    return {
      dot: hexToRgba(primary, theme === "light" ? 0.40 : 0.32),
      dot2: hexToRgba(accent, theme === "light" ? 0.35 : 0.28),
      link: hexToRgba(primary, theme === "light" ? 0.18 : 0.14),
      glow: hexToRgba(accent, theme === "light" ? 0.12 : 0.10),
      text: text
    };
  }

  function runParticles(canvas) {
    const ctx = canvas.getContext("2d", { alpha: true });

    let w = 0, h = 0, dpr = Math.min(window.devicePixelRatio || 1, 2);
    let particles = [];
    let palette = buildPalette();

    const mouse = { x: -9999, y: -9999, active: false };

    function resize() {
      w = Math.floor(window.innerWidth);
      h = Math.floor(window.innerHeight);
      dpr = Math.min(window.devicePixelRatio || 1, 2);
      canvas.width = Math.floor(w * dpr);
      canvas.height = Math.floor(h * dpr);
      canvas.style.width = w + "px";
      canvas.style.height = h + "px";
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

      // Re-seed particles for new size
      seedParticles(true);
    }

    function seedParticles(soft) {
      const count = SETTINGS.maxParticles;
      const next = [];
      for (let i = 0; i < count; i++) {
        next.push({
          x: Math.random() * w,
          y: Math.random() * h,
          vx: (Math.random() - 0.5) * SETTINGS.speed,
          vy: (Math.random() - 0.5) * SETTINGS.speed,
          r: 1 + Math.random() * (coarsePointer ? 1.6 : 2.2),
          t: Math.random() * Math.PI * 2,
          k: Math.random() < 0.5 ? 0 : 1
        });
      }
      particles = next;

      if (!soft) ctx.clearRect(0, 0, w, h);
    }

    function step(dt) {
      // background clear (transparent)
      ctx.clearRect(0, 0, w, h);

      // glow around mouse
      if (mouse.active) {
        const g = ctx.createRadialGradient(mouse.x, mouse.y, 0, mouse.x, mouse.y, 180);
        g.addColorStop(0, palette.glow);
        g.addColorStop(1, "rgba(0,0,0,0)");
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, w, h);
      }

      // move
      for (const p of particles) {
        p.t += dt * 0.002;
        p.x += p.vx * (dt * 0.06);
        p.y += p.vy * (dt * 0.06);

        // soft drift (wave)
        p.x += Math.sin(p.t) * 0.04;
        p.y += Math.cos(p.t) * 0.04;

        // bounce edges
        if (p.x < 0) p.x = w, p.y = Math.random() * h;
        if (p.x > w) p.x = 0, p.y = Math.random() * h;
        if (p.y < 0) p.y = h, p.x = Math.random() * w;
        if (p.y > h) p.y = 0, p.x = Math.random() * w;
      }

      // links
      for (let i = 0; i < particles.length; i++) {
        for (let j = i + 1; j < particles.length; j++) {
          const a = particles[i], b = particles[j];
          const dx = a.x - b.x, dy = a.y - b.y;
          const d = Math.hypot(dx, dy);
          if (d < SETTINGS.linkDist) {
            const alpha = (1 - d / SETTINGS.linkDist);
            ctx.strokeStyle = palette.link.replace(/[\d.]+\)$/, (0.10 + alpha * 0.18) + ")");
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(a.x, a.y);
            ctx.lineTo(b.x, b.y);
            ctx.stroke();
          }
        }
      }

      // dots
      for (const p of particles) {
        ctx.fillStyle = p.k ? palette.dot : palette.dot2;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fill();
      }
    }

    // FPS cap loop
    let last = performance.now();
    let acc = 0;
    const frame = 1000 / SETTINGS.fpsCap;

    function loop(now) {
      const dt = now - last;
      last = now;
      acc += dt;

      if (acc >= frame) {
        // limit spiral of death
        const clamped = Math.min(acc, 60);
        step(clamped);
        acc = 0;
      }
      requestAnimationFrame(loop);
    }

    // Mouse tracking
    window.addEventListener("mousemove", (e) => {
      mouse.x = e.clientX;
      mouse.y = e.clientY;
      mouse.active = true;
    }, { passive: true });

    window.addEventListener("mouseleave", () => {
      mouse.active = false;
      mouse.x = -9999;
      mouse.y = -9999;
    });

    // Theme updates live
    const obs = new MutationObserver(() => { palette = buildPalette(); });
    obs.observe(root, { attributes: true, attributeFilter: ["data-theme"] });

    window.addEventListener("resize", resize, { passive: true });

    resize();
    requestAnimationFrame(loop);
  }

  // ===== Boot =====
  document.addEventListener("DOMContentLoaded", () => {
    // 1) Transitions
    setupPageTransitions({ enabled: true });

    // 2) Nebula
    const nebula = createNebulaLayer();
    const obs = new MutationObserver(() => {
      // tiny light/dark tuning for nebula opacity
      nebula.style.opacity = getTheme() === "light" ? "0.70" : "0.92";
    });
    obs.observe(root, { attributes: true, attributeFilter: ["data-theme"] });
    nebula.style.opacity = getTheme() === "light" ? "0.70" : "0.92";

    // 3) Particles canvas (skip if super low power)
    const canvas = createCanvasLayer();
    runParticles(canvas);
  });
})();
