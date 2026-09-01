@props(['category'])
<article {{ $attributes->class('prompt-category-card') }}>
    <span class="prompt-category-icon" aria-hidden="true">{{ mb_strtoupper(mb_substr($category->localizedName(), 0, 1)) }}</span>
    <div><h3>{{ $category->localizedName() }}</h3>@if($category->localizedDescription())<p>{{ $category->localizedDescription() }}</p>@endif</div>
</article>
