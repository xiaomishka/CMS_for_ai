<article class="post">
  <div class="post-head">
    [if article.category]
      <a class="cat" href="{{ article.category.url }}">{{ article.category.title }}</a>
    [/if]
    <time class="date">{{ article.date|date:"d.m.Y" }}</time>
  </div>
  <h1 class="post-title">{{ article.title }}</h1>
  [if article.cover]
    <img class="post-cover" src="{{ article.cover }}" alt="{{ article.title }}">
  [/if]
  <div class="post-body">
    {{ article.full|raw }}
  </div>
</article>
[if article.category]
<p class="backlink"><a href="{{ article.category.url }}">← {{ article.category.title }}</a></p>
[else]
<p class="backlink"><a href="/">← На главную</a></p>
[/if]
