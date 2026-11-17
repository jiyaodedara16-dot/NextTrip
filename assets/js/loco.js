const locoScroll = new LocomotiveScroll({
  el: document.querySelector('main'),
  smooth: true,
});

gsap.registerPlugin(ScrollTrigger);
locoScroll.on('scroll', ScrollTrigger.update);

ScrollTrigger.scrollerProxy('main', {
  scrollTop(value) {
    return arguments.length
      ? locoScroll.scrollTo(value, { duration: 0, disableLerp: true })
      : locoScroll.scroll.instance.scroll.y;
  },
  getBoundingClientRect() {
    return { top: 0, left: 0, width: window.innerWidth, height: window.innerHeight };
  },
});

ScrollTrigger.addEventListener('refresh', () => locoScroll.update());
ScrollTrigger.refresh();