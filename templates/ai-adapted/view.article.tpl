<article class="post">
  <header class="post-header">
    [if article.category]
      <a class="cat-link" href="{{ article.category.url }}">{{ article.category.title }}</a>
    [/if]
    <h1 class="post-title">{{ article.title }}</h1>
    <time class="post-date">{{ article.date|date:"d.m.Y" }}</time>
  </header>
  [if article.cover]
    <img class="post-cover" src="{{ article.cover }}" alt="{{ article.title }}">
  [/if]
  <div class="post-body">
    {{ article.full|raw }}
  </div>
  <footer class="post-footer">
    [if article.category]
      <a class="back-link" href="{{ article.category.url }}">← Назад в {{ article.category.title }}</a>
    [else]
      <a class="back-link" href="/">← На главную</a>
    [/if]
  </footer>
</article>
