/**
 * About page interactions
 * ------------------------
 * 1. Tilt: vision/mission cards rotate toward the cursor (3D perspective).
 * 2. Flip accessibility: team cards flip on hover (CSS handles that),
 *    this just adds keyboard/touch support (tap to flip on touch devices).
 *
 * Note: the section entrance animation (fade/rise on load) is pure CSS
 * (see .reveal / .journey-item in about.css) — it does not depend on
 * this file running at all, so a JS failure never hides page content.
 */
(function () {
  "use strict";

  // Iterate a NodeList/array without depending on NodeList.prototype.forEach
  // (missing in a handful of older/embedded WebViews).
  function each(list, fn) {
    for (var i = 0; i < list.length; i++) fn(list[i], i);
  }

  var prefersReducedMotion = false;
  try {
    prefersReducedMotion = window.matchMedia(
      "(prefers-reduced-motion: reduce)"
    ).matches;
  } catch (err) {
    prefersReducedMotion = false;
  }

  /* ------------------------------------------------------------------ */
  /* 1. Tilt effect                                                     */
  /* ------------------------------------------------------------------ */
  function initTilt() {
    var cards = document.querySelectorAll("[data-tilt]");
    if (!cards.length || prefersReducedMotion) return;

    each(cards, function (card) {
      var maxTilt = parseFloat(card.dataset.tiltMax) || 8;
      var scale = parseFloat(card.dataset.tiltScale) || 1.02;

      function onMove(e) {
        var rect = card.getBoundingClientRect();
        var x = (e.clientX - rect.left) / rect.width; // 0 -> 1
        var y = (e.clientY - rect.top) / rect.height; // 0 -> 1

        var rotateY = (x - 0.5) * maxTilt * 2;
        var rotateX = (0.5 - y) * maxTilt * 2;

        card.style.transform =
          "rotateX(" + rotateX + "deg) rotateY(" + rotateY + "deg) scale(" + scale + ")";
      }

      function onLeave() {
        card.style.transform = "rotateX(0deg) rotateY(0deg) scale(1)";
      }

      card.addEventListener("mousemove", onMove);
      card.addEventListener("mouseleave", onLeave);
    });
  }

  /* ------------------------------------------------------------------ */
  /* 2. Team card flip — keyboard & touch support                      */
  /* ------------------------------------------------------------------ */
  function initFlipCards() {
    var cards = document.querySelectorAll(".team-card");

    function supportsHover() {
      try {
        return window.matchMedia("(hover: hover)").matches;
      } catch (err) {
        return true; // assume desktop-like hover if matchMedia is unavailable
      }
    }

    each(cards, function (card) {
      // Touch devices: tap toggles the flip instead of relying on hover.
      card.addEventListener("click", function (e) {
        if (supportsHover()) return; // desktop: hover already works
        e.preventDefault();
        each(cards, function (other) {
          if (other !== card) other.classList.remove("is-flipped");
        });
        card.classList.toggle("is-flipped");
      });

      // Keyboard: Enter / Space flips when the card (or its hint) has focus.
      card.setAttribute("tabindex", card.getAttribute("tabindex") || "0");
      card.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          card.classList.toggle("is-flipped");
        }
      });
    });
  }

  function safeRun(fn) {
    try {
      fn();
    } catch (err) {
      // A single feature failing (unsupported API, unexpected markup,
      // etc.) should never block the others from initializing.
      if (window.console && console.warn) {
        console.warn("[about.js] " + fn.name + " failed:", err);
      }
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    safeRun(initTilt);
    safeRun(initFlipCards);
  });
})();