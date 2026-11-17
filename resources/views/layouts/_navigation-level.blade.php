@php($levelId = $mode.'-level-'.$node['id'])
<div class="{{ $mode === 'desktop' ? 'mega-menu__level' : 'mobile-category-drawer__level' }}" data-nav-level="{{ $levelId }}" data-nav-title="{{ $node['name'] }}" @if (! $root) hidden @endif>
    @if (! $root)
        <button type="button" class="{{ $mode === 'desktop' ? 'mega-menu__back' : 'mobile-category-link mobile-category-link--back' }}" data-nav-back>‹ {{ $node['name'] }}</button>
    @endif
    <a class="{{ $mode === 'desktop' ? 'mega-menu__all' : 'mobile-category-link mobile-category-link--all' }}" href="{{ $node['url'] }}">مشاهده همه {{ $node['name'] }} <span aria-hidden="true">←</span></a>
    <div class="{{ $mode === 'desktop' ? 'mega-menu__level-grid' : 'mobile-category-drawer__level-list' }}">
        @foreach ($node['children'] as $child)
            <div class="{{ $mode === 'desktop' ? 'mega-menu__column' : '' }}">
                <a class="{{ $mode === 'desktop' ? 'mega-menu__parent' : 'mobile-category-link' }}" href="{{ $child['url'] }}"><span>{{ $child['name'] }}</span>@if ($child['children'])<span aria-hidden="true">›</span>@endif</a>
                @if ($child['children'])
                    <button type="button" class="{{ $mode === 'desktop' ? 'mega-menu__drill' : 'mobile-category-link mobile-category-link--drill' }}" data-nav-drill="{{ $mode }}-level-{{ $child['id'] }}">مشاهده زیر‌دسته‌ها <span aria-hidden="true">‹</span></button>
                    @include('layouts._navigation-level', ['node' => $child, 'mode' => $mode, 'root' => false])
                @endif
            </div>
        @endforeach
    </div>
</div>
