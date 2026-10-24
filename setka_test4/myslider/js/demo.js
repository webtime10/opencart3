$(function () {
  $("[data-slider]").slider({
    prev: ".slider-prev",
    next: ".slider-next",
    loop: true,
    autoplay: true,
    autoplayDelay: 4000,
    pauseOnHover: true,
  });

  requestAnimationFrame(function () {
    $("[data-slider]").each(function () {
      var inst = $(this).data("slider-instance");
      if (inst) inst.layout();
    });
  });
});
