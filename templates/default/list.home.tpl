<div class="page-head">
  <h1 class="page-title">Свежие записи</h1>
</div>
<div class="post-list">
  [each articles as a]
    [include partial.news]
  [else]
    <p>Статей пока нет. <a href="/admin/">Добавьте первую</a>.</p>
  [/each]
</div>
[include partial.pager]
