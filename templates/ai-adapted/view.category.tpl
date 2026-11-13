<header class="category-header">
  <h1 class="category-title">{{ category.title }}</h1>
  [if category.description]
    <p class="category-desc">{{ category.description }}</p>
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
  <p class="empty">В этой рубрике пока нет статей.</p>
[/if]
