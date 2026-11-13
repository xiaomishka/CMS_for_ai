<section class="widget">
  <h3>Последние записи</h3>
  <ul class="widget-list">
    [each recent as r]
      <li><a href="{{ r.url }}">{{ r.title }}</a><time class="date">{{ r.date|date:"d.m.Y" }}</time></li>
    [else]
      <li class="hint">Пока пусто</li>
    [/each]
  </ul>
</section>
