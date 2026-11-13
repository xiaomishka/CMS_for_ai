[if pagination]
  [if pagination.pages > 1]
  <nav class="pager">
    [if pagination.prev_url]<a class="pager-btn" href="{{ pagination.prev_url }}">← Новее</a>[/if]
    <span class="pager-info">Стр. {{ pagination.page }} из {{ pagination.pages }}</span>
    [if pagination.next_url]<a class="pager-btn" href="{{ pagination.next_url }}">Старее →</a>[/if]
  </nav>
  [/if]
[/if]
