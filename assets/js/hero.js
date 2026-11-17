/* ==========================================================================
   SMART TOURISM MANAGEMENT SYSTEM
   Hero Section Script (hero.js)
   Automatically rotates 5 fixed background images + their location label.
   No user controls — this is intentionally NOT a carousel/slider.
   ========================================================================== */

(function () {
  "use strict";

  // ----------------------------------------------------------------------
  // 1. EDIT THIS: your 5 hero images + the place name shown for each one.
  //    Swap the "image" paths for your real files.
  // ----------------------------------------------------------------------
  var HERO_SLIDES = [
    { image: "assets/images/hero/herosection_img_1.png", location: "Baga Beach · Goa, India" },
    { image: "assets/images/hero/herosection_img_2.png", location: "Kashmir Valley · Jammu & Kashmir, India" },
    { image: "assets/images/hero/herosection_img_3.png", location: "Sam Sand Dunes · Jaisalmer, Rajasthan" },
    { image: "assets/images/hero/herosection_img_4.avif", location: "Manali · Himachal Pradesh, India" },
    { image: "assets/images/hero/herosection_img_5.png", location: "Brihadeeswarar Temple · Thanjavur, Tamil Nadu" }
  ];

  var SLIDE_INTERVAL_MS = 3000; // time each image stays on screen2

  var currentIndex = 0;
  var layerA = document.getElementById("heroBgA");
  var layerB = document.getElementById("heroBgB");
  var locationEl = document.getElementById("heroLocationText");
  var dotsWrap = document.getElementById("heroDots");

  if (!layerA || !layerB || !locationEl || !dotsWrap) {
    return; // hero markup not present on this page
  }

  var activeLayer = layerA;
  var idleLayer = layerB;

  // Preload images so the crossfade never shows a blank frame
  HERO_SLIDES.forEach(function (slide) {
    var img = new Image();
    img.src = slide.image;
  });

  // Build the passive dot indicators
  HERO_SLIDES.forEach(function (_, i) {
    var dot = document.createElement("span");
    dot.className = "hero-dot" + (i === 0 ? " is-active" : "");
    dotsWrap.appendChild(dot);
  });

  function updateDots(index) {
    var dots = dotsWrap.querySelectorAll(".hero-dot");
    dots.forEach(function (dot, i) {
      dot.classList.toggle("is-active", i === index);
    });
  }

  function goToSlide(index) {
    var slide = HERO_SLIDES[index];

    // Crossfade background: fade the idle layer in, then swap roles
    idleLayer.style.backgroundImage = "url('" + slide.image + "')";
    idleLayer.classList.add("is-active");
    activeLayer.classList.remove("is-active");

    var swap = activeLayer;
    activeLayer = idleLayer;
    idleLayer = swap;

    // Fade the location text out, swap it, fade back in
    locationEl.parentElement.classList.add("is-fading");
    window.setTimeout(function () {
      locationEl.innerHTML = slide.location;
      locationEl.parentElement.classList.remove("is-fading");
    }, 300);

    updateDots(index);
  }

  window.setInterval(function () {
    currentIndex = (currentIndex + 1) % HERO_SLIDES.length;
    goToSlide(currentIndex);
  }, SLIDE_INTERVAL_MS);
})();

 