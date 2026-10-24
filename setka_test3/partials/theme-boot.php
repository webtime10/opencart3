<script>
(function () {
  var THEMES = ["theme-dark", "theme-plum", "theme-light", "theme-ocean", "theme-olive", "theme-emerald"];
  var t = window.__plumTheme;
  if (THEMES.indexOf(t) === -1) t = "theme-plum";
  var html = document.documentElement, body = document.body;
  for (var i = 0; i < THEMES.length; i++) {
    html.classList.remove(THEMES[i]);
    body.classList.remove(THEMES[i]);
  }
  html.classList.add(t);
  body.classList.add(t);
})();
</script>
