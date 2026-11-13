<div class="page-head">
  <h1 class="page-title">{{ category.title }}</h1>
  [if category.description]<p class="page-desc">{{ category.description }}</p>[/if]
</div>
<div class="post-list">
  [each articles as a]
    [include partial.news]
  [else]
    <p>В рубрике пока нет статей.</p>
  [/each]
</div>
[include partial.pager]
