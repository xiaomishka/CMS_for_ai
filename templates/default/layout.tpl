<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ meta.title }}</title>
[if meta.description]<meta name="description" content="{{ meta.description }}">[/if]
<link rel="stylesheet" href="/templates/default/assets/style.css">
[slot head]
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <a class="logo" href="/">{{ site.name }}</a>
    <nav class="nav">
      [each nav as n]
        <a href="{{ n.url }}">{{ n.title }}</a>
      [else]
        <span class="hint">Создайте рубрики в админке</span>
      [/each]
    </nav>
  </div>
</header>

<main class="container layout">
  <div class="content">
    {{ content|raw }}
  </div>
  <aside class="sidebar">
    [include partial.sidebar]
  </aside>
</main>

<footer class="site-footer">
  <div class="container footer-inner">
    <span>© {{ site.year }} {{ site.name }}</span>
    [if user.logged]<a href="/admin/">Админка</a>[/if]
    <span class="hint">Работает на легковесной CMS</span>
  </div>
</footer>
[slot before_end]
</body>
</html>
