
gsap.registerPlugin(ScrollTrigger);


/* ==========================================================================
   1. HERO ENTRANCE (runs on page load, no scroll trigger needed)
   ========================================================================== */
function heroEntrance() {
  const tl = gsap.timeline({ defaults: { ease: "power3.out" } });

tl.from(".hero-badge", { y: -20, autoAlpha: 0, duration: 0.6 })
  .from(".hero-heading", { y: 40, autoAlpha: 0, duration: 0.8 }, "-=0.3")
  .from(".hero-description", { y: 30, autoAlpha: 0, duration: 0.7 }, "-=0.5")
  .from(".hero-cta-group .hero-btn", { y: 20, autoAlpha: 0, duration: 0.6, stagger: 0.15 }, "-=0.4")
  .from(".hero-location", { autoAlpha: 0, duration: 0.6 }, "-=0.3")
  .from(".hero-card", { x: 40, autoAlpha: 0, duration: 0.6, stagger: 0.15 }, "-=0.8");
}

/* ==========================================================================
   2. STATS COUNTER (animates numbers when scrolled into view)
   ========================================================================== */
function statsCounter() {
  const items = document.querySelectorAll(".stats-item");

  items.forEach((item) => {
    const countEl = item.querySelector(".stats-count");
    const target = parseFloat(item.dataset.target) || 0;
    const decimals = parseInt(item.dataset.decimals) || 0;
    const counter = { val: 0 };

    ScrollTrigger.create({
      trigger: item,
      start: "top 85%",
      once: true,
      onEnter: () => {
        gsap.to(counter, {
          val: target,
          duration: 1.8,
          ease: "power2.out",
          onUpdate: () => {
            countEl.textContent = decimals > 0
              ? counter.val.toFixed(decimals)
              : Math.floor(counter.val);
          },
        });
      },
    });
  });

  // Fade in the whole stats bar
  gsap.from(".stats-item", {
    scrollTrigger: {
      trigger: ".stats",
      start: "top 85%",
      once: true,
    },
    y: 25,
    opacity: 0,
    duration: 0.7,
    stagger: 0.12,
    ease: "power2.out",
  });
}

/* ==========================================================================
   3. ABOUT / WHY SECTION
   ========================================================================== */
function whySection() {
  gsap.from(".why-image-wrapper", {
    scrollTrigger: {
      trigger: ".why-section",
      start: "top 75%",
      once: true,
    },
    x: -50,
    opacity: 0,
    duration: 0.9,
    ease: "power3.out",
  });

  gsap.from(".why-image-badge", {
    scrollTrigger: {
      trigger: ".why-section",
      start: "top 65%",
      once: true,
    },
    scale: 0.8,
    opacity: 0,
    duration: 0.6,
    delay: 0.4,
    ease: "back.out(1.6)",
  });

  gsap.from(".why-content > *", {
    scrollTrigger: {
      trigger: ".why-content",
      start: "top 75%",
      once: true,
    },
    y: 30,
    opacity: 0,
    duration: 0.7,
    stagger: 0.15,
    ease: "power2.out",
  });
}

/* ==========================================================================
   4. GENERIC CARD-GRID REVEAL
   (reused for categories, features, smart-features)
   ========================================================================== */
function revealCardGrid(sectionSelector, cardSelector) {
  const section = document.querySelector(sectionSelector);
  if (!section) return;

  // Header (badge + title) fades up first
  gsap.from(section.querySelectorAll(".section-header > *"), {
    scrollTrigger: {
      trigger: section,
      start: "top 80%",
      once: true,
    },
    y: 25,
    opacity: 0,
    duration: 0.6,
    stagger: 0.1,
    ease: "power2.out",
  });

  // Cards stagger in
  gsap.from(section.querySelectorAll(cardSelector), {
    scrollTrigger: {
      trigger: section,
      start: "top 70%",
      once: true,
    },
    y: 40,
    opacity: 0,
    scale: 0.96,
    duration: 0.6,
    stagger: 0.1,
    ease: "power2.out",
  });
}

/* ==========================================================================
   5. CTA BLOCK
   ========================================================================== */
function ctaBlock() {
  gsap.from(".cta-block", {
    scrollTrigger: {
      trigger: ".cta-block",
      start: "top 80%",
      once: true,
    },
    scale: 0.96,
    opacity: 0,
    duration: 0.8,
    ease: "power2.out",
  });

  gsap.from(".cta-heading, .cta-subtext, .cta-buttons", {
    scrollTrigger: {
      trigger: ".cta-block",
      start: "top 75%",
      once: true,
    },
    y: 25,
    opacity: 0,
    duration: 0.6,
    stagger: 0.15,
    delay: 0.2,
    ease: "power2.out",
  });
}

/* ==========================================================================
   INIT
   ========================================================================== */
document.addEventListener("DOMContentLoaded", () => {
  heroEntrance();
  statsCounter();
  whySection();
  revealCardGrid(".categories-section", ".category-card");
  revealCardGrid(".features-section", ".feature-card");
  revealCardGrid(".smart-features-section", ".smart-feature-card");
  ctaBlock();
});