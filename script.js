/* =========================================================
   SIP STREET 7 — interactions
   Smooth scroll · scroll reveals · parallax · counters ·
   custom cursor · nav · marquee. All progressively enhanced:
   if a CDN lib fails to load, the page still works.
   ========================================================= */
(function () {
  "use strict";

  const prefersReduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const $  = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));

  /* =========================================================
     BUSINESS CONFIG  —  default test data.
     👉 Swap these three values for the client's real details.
     ========================================================= */
  const SS7 = {
    orderUrl:     "https://www.ubereats.com/au",          // real online-ordering link goes here
    phone:        "+61413697307",                          // dialled number (E.164 format)
    phoneDisplay: "+61 413 697 307",                       // shown on screen
    instagram:    "https://www.instagram.com/sipstreet.7/",// Instagram profile
  };

  // Point the gallery tiles at the Instagram profile (open in a new tab)
  $$(".gallery__grid a").forEach((a) => {
    a.href = SS7.instagram;
    a.target = "_blank";
    a.rel = "noopener";
  });

  /* ---------- Loader ---------- */
  const loader = $("#loader");
  const loaderBar = $("#loaderBar");
  if (loaderBar) {
    let p = 0;
    const t = setInterval(() => {
      p = Math.min(100, p + Math.random() * 26);
      loaderBar.style.width = p + "%";
      if (p >= 100) clearInterval(t);
    }, 130);
  }
  function hideLoader() {
    if (loaderBar) loaderBar.style.width = "100%";
    setTimeout(() => loader && loader.classList.add("done"), 350);
  }
  window.addEventListener("load", () => setTimeout(hideLoader, 500));
  // safety net so the loader never traps the page
  setTimeout(hideLoader, 3500);

  /* ---------- Smooth scrolling (Lenis) ---------- */
  let lenis = null;
  if (window.Lenis && !prefersReduced) {
    lenis = new Lenis({
      duration: 1.15,
      easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
      smoothWheel: true,
    });
    function raf(time) { lenis.raf(time); requestAnimationFrame(raf); }
    requestAnimationFrame(raf);

    if (window.ScrollTrigger) {
      lenis.on("scroll", ScrollTrigger.update);
      gsap.ticker.add((t) => lenis.raf(t * 1000));
      gsap.ticker.lagSmoothing(0);
    }
  }

  /* ---------- Anchor links (works with or without Lenis) ---------- */
  $$('a[href^="#"]').forEach((a) => {
    a.addEventListener("click", (e) => {
      const id = a.getAttribute("href");
      if (id === "#" || id.length < 2) return;
      const target = document.querySelector(id);
      if (!target) return;
      e.preventDefault();
      closeDrawer();
      if (lenis) lenis.scrollTo(target, { offset: -80, duration: 1.2 });
      else target.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  });

  /* ---------- Nav scroll state ---------- */
  const nav = $("#nav");
  const onScroll = () => {
    if (!nav) return;
    nav.classList.toggle("scrolled", window.scrollY > 40);
  };
  onScroll();
  window.addEventListener("scroll", onScroll, { passive: true });

  /* ---------- Mobile drawer ---------- */
  const burger = $("#burger");
  function closeDrawer() { nav && nav.classList.remove("open"); }
  if (burger) {
    burger.addEventListener("click", () => nav.classList.toggle("open"));
  }

  /* ---------- Reveal on scroll (IntersectionObserver) ---------- */
  const revealEls = $$("[data-reveal]");
  const hero = $("#hero");
  if ("IntersectionObserver" in window && !prefersReduced) {
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((en) => {
          if (en.isIntersecting) {
            en.target.classList.add("in");
            io.unobserve(en.target);
          }
        });
      },
      { threshold: 0.16, rootMargin: "0px 0px -8% 0px" }
    );
    revealEls.forEach((el) => io.observe(el));
  } else {
    revealEls.forEach((el) => el.classList.add("in"));
  }
  // trigger the hero line-reveal right away.
  // rAF gives a paint-at-0 first (so the transition plays); the setTimeout is a
  // safety net for environments where rAF is throttled (e.g. a hidden/background tab).
  const revealHero = () => hero && hero.classList.add("in");
  requestAnimationFrame(revealHero);
  setTimeout(revealHero, 80);

  /* ---------- Count-up stats ---------- */
  const counters = $$("[data-count]");
  if ("IntersectionObserver" in window && counters.length) {
    const cio = new IntersectionObserver(
      (entries) => {
        entries.forEach((en) => {
          if (!en.isIntersecting) return;
          const el = en.target;
          const end = parseInt(el.dataset.count, 10);
          const dur = 1400;
          const start = performance.now();
          const tick = (now) => {
            const t = Math.min(1, (now - start) / dur);
            const eased = 1 - Math.pow(1 - t, 3);
            el.textContent = Math.round(end * eased);
            if (t < 1) requestAnimationFrame(tick);
          };
          requestAnimationFrame(tick);
          cio.unobserve(el);
        });
      },
      { threshold: 0.6 }
    );
    counters.forEach((c) => cio.observe(c));
  } else {
    counters.forEach((c) => (c.textContent = c.dataset.count));
  }

  /* ---------- Parallax floats (GSAP if present, else rAF) ---------- */
  const floats = $$("[data-float]");
  if (floats.length && !prefersReduced) {
    if (window.gsap && window.ScrollTrigger) {
      floats.forEach((el) => {
        const speed = parseFloat(el.dataset.float) || 0.1;
        gsap.to(el, {
          yPercent: -speed * 180,
          ease: "none",
          scrollTrigger: { trigger: el, start: "top bottom", end: "bottom top", scrub: 1 },
        });
      });
    } else {
      // lightweight fallback
      const update = () => {
        const sy = window.scrollY;
        floats.forEach((el) => {
          const speed = parseFloat(el.dataset.float) || 0.1;
          el.style.transform = `translateY(${-sy * speed * 0.25}px)`;
        });
      };
      window.addEventListener("scroll", update, { passive: true });
      update();
    }
  }

  /* ---------- Hero product subtle tilt on mouse ---------- */
  const tilts = $$("[data-tilt]");
  if (!prefersReduced && window.matchMedia("(hover:hover)").matches) {
    tilts.forEach((el) => {
      const parent = el.closest("section, header") || el.parentElement;
      parent.addEventListener("mousemove", (e) => {
        const r = parent.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width - 0.5;
        const y = (e.clientY - r.top) / r.height - 0.5;
        el.style.transform = `rotate(${el.classList.contains("spotlight__img") ? -2 : 2}deg) rotateY(${x * 8}deg) rotateX(${-y * 8}deg)`;
      });
      parent.addEventListener("mouseleave", () => {
        el.style.transform = "";
      });
    });
  }

  /* ---------- Custom cursor ---------- */
  const dot = $("#cursorDot");
  const ring = $("#cursorRing");
  if (dot && ring && window.matchMedia("(hover:hover)").matches && !prefersReduced) {
    let mx = innerWidth / 2, my = innerHeight / 2, rx = mx, ry = my;
    window.addEventListener("mousemove", (e) => {
      mx = e.clientX; my = e.clientY;
      dot.style.transform = `translate(${mx}px, ${my}px) translate(-50%, -50%)`;
    });
    const follow = () => {
      rx += (mx - rx) * 0.18;
      ry += (my - ry) * 0.18;
      ring.style.transform = `translate(${rx}px, ${ry}px) translate(-50%, -50%)`;
      requestAnimationFrame(follow);
    };
    follow();
    $$("a, button, .card, [data-tilt]").forEach((el) => {
      el.addEventListener("mouseenter", () => ring.classList.add("grow"));
      el.addEventListener("mouseleave", () => ring.classList.remove("grow"));
    });
  }

  /* ---------- Magnetic buttons ---------- */
  if (!prefersReduced && window.matchMedia("(hover:hover)").matches) {
    $$(".btn").forEach((btn) => {
      btn.addEventListener("mousemove", (e) => {
        const r = btn.getBoundingClientRect();
        const x = e.clientX - r.left - r.width / 2;
        const y = e.clientY - r.top - r.height / 2;
        btn.style.transform = `translate(${x * 0.18}px, ${y * 0.28}px)`;
      });
      btn.addEventListener("mouseleave", () => (btn.style.transform = ""));
    });
  }

  /* ---------- Order modal (3 ways to order) ---------- */
  const modal = $("#orderModal");
  if (modal) {
    const sub = $("#orderSub", modal);
    const online = $("#orderOnline", modal);
    const call = $("#orderCall", modal);
    const phoneTxt = $("#orderPhone", modal);
    const visitBtn = $("#orderVisit", modal);
    let lastFocus = null;

    // apply config defaults
    if (online) online.href = SS7.orderUrl;
    if (call) call.href = "tel:" + SS7.phone;
    if (phoneTxt) phoneTxt.textContent = SS7.phoneDisplay;

    const openModal = (item) => {
      lastFocus = document.activeElement;
      if (sub) {
        sub.textContent = item
          ? `Grab a ${item} — pick the easiest way below.`
          : "Pick the easiest way to get your Sip Street 7 fix.";
      }
      modal.classList.add("open");
      modal.setAttribute("aria-hidden", "false");
      if (lenis) lenis.stop();
      document.body.style.overflow = "hidden";
      const x = $(".order__x", modal);
      x && x.focus();
    };
    const closeModal = () => {
      modal.classList.remove("open");
      modal.setAttribute("aria-hidden", "true");
      if (lenis) lenis.start();
      document.body.style.overflow = "";
      lastFocus && lastFocus.focus && lastFocus.focus();
    };

    // open from any .js-order trigger; pull the product name from the card or data-item
    $$(".js-order").forEach((trigger) => {
      trigger.addEventListener("click", (e) => {
        e.preventDefault();
        const card = trigger.closest(".card");
        const name =
          trigger.dataset.item ||
          (card && card.querySelector("h3") &&
            card.querySelector("h3").childNodes[0].textContent.trim());
        openModal(name);
      });
    });

    // close interactions
    $$("[data-close]", modal).forEach((el) => el.addEventListener("click", closeModal));
    if (visitBtn) {
      visitBtn.addEventListener("click", () => {
        closeModal();
        const target = $("#visit");
        if (target) {
          if (lenis) lenis.scrollTo(target, { offset: -80, duration: 1.2 });
          else target.scrollIntoView({ behavior: "smooth" });
        }
      });
    }
    document.addEventListener("keydown", (e) => {
      if (!modal.classList.contains("open")) return;
      if (e.key === "Escape") { closeModal(); return; }
      // focus trap: keep Tab cycling within the modal
      if (e.key === "Tab") {
        const f = $$('a[href], button:not([disabled])', modal).filter(
          (el) => el.offsetParent !== null
        );
        if (!f.length) return;
        const first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
  }

  /* ---------- Newsletter (demo handler) ---------- */
  const form = $("#newsletterForm");
  if (form) {
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      const input = form.querySelector("input");
      const btn = form.querySelector("button span");
      if (btn) btn.textContent = "You're on the street! 🎉";
      input.value = "";
      input.disabled = true;
      setTimeout(() => {
        if (btn) btn.textContent = "Join the Street ↗";
        input.disabled = false;
      }, 3000);
    });
  }
})();
