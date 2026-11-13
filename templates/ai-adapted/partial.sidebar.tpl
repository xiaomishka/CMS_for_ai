<div class="sidebar-widget">
  <h3 class="widget-title">Последние статьи</h3>
  <ul class="recent-list">
    [each recent as r]
      <li><a href="{{ r.url }}">{{ r.title }}</a></li>
    [/each]
  </ul>
</div>
<div class="sidebar-widget">
  <h3 class="widget-title">Рубрики</h3>
  <ul class="nav-list">
    [each nav as n]
      <li><a href="{{ n.url }}">{{ n.title }}</a></li>
    [/each]
  </ul>
</div>
