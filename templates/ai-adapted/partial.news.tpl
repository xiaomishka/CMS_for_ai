<article class="news-item">
  <h2 class="news-title"><a href="{{ a.url }}">{{ a.title }}</a></h2>
  [if a.cover]
    <img class="news-cover" src="{{ a.cover }}" alt="{{ a.title }}">
  [/if]
  <p class="news-short">{{ a.short }}</p>
  <time class="news-date">{{ a.date|date:"d.m.Y" }}</time>
  [if a.category]
    <a class="news-cat" href="{{ a.category.url }}">{{ a.category.title }}</a>
  [/if]
</article>
