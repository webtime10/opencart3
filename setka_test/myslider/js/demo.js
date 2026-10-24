$(function () {
  $("[data-wt_slider]").wt_slider({
    prev: ".wt_slider_prev",
    next: ".wt_slider_next",
    loop: true,
    autoplay: true,
    autoplayDelay: 4000,
    pauseOnHover: true,
  });

  requestAnimationFrame(function () {
    $("[data-wt_slider]").each(function () {
      var inst = $(this).data("wt_slider_instance");
      if (inst) inst.layout();
    });
  });
});
