<header class="home-header">
  <h1 class="home-title">{{ meta.title }}</h1>
  [if meta.description]
    <p class="home-desc">{{ meta.description }}</p>
  [/if]
</header>

[if articles]
  [each articles as a]
    [include partial.news with a]
  [/each]

  <nav class="pager">
    [if pagination.prev_url]
      <a class="prev" href="{{ pagination.prev_url }}">‹ Назад</a>
    [/if]
    [if pagination.next_url]
      <a class="next" href="{{ pagination.next_url }}">Вперед ›</a>
    [/if]
  </nav>
[else]
  <p class="empty">Пока нет статей. Создайте первую в админке.</p>
[/if]
