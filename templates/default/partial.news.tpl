<article class="post card">
  [if a.cover]
    <a href="{{ a.url }}"><img class="card-cover" src="{{ a.cover }}" alt="{{ a.title }}" loading="lazy"></a>
  [/if]
  <div class="card-head">
    [if a.category]
      <a class="cat" href="{{ a.category.url }}">{{ a.category.title }}</a>
    [/if]
    <time class="date">{{ a.date|date:"d.m.Y" }}</time>
  </div>
  <h2 class="card-title"><a href="{{ a.url }}">{{ a.title }}</a></h2>
  [if a.short]<p class="card-text">{{ a.short|truncate:"180" }}</p>[/if]
</article>
