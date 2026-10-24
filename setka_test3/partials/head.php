<meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo isset($page_title) ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') : 'setka_test3 · Plum Store'; ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/setka.css?v=themeflash2">
  <script>
  (function () {
    var KEY = "wtshop-setka-test3-theme";
    var THEMES = ["theme-dark", "theme-plum", "theme-light", "theme-ocean", "theme-olive", "theme-emerald"];
    var t = null;
    try { t = localStorage.getItem(KEY); } catch (e) {}
    if (THEMES.indexOf(t) === -1) t = "theme-plum";
    var html = document.documentElement;
    for (var i = 0; i < THEMES.length; i++) html.classList.remove(THEMES[i]);
    html.classList.add(t);
    window.__plumTheme = t;
  })();
  </script>
