@foreach ($nodes as $node)
    <li class="category-tree__node" data-category-node data-category-id="{{ $node['id'] }}" data-parent-id="{{ $node['parent_id'] ?? '' }}" data-sort-order="{{ $node['sort_order'] }}" data-category-name="{{ $node['name'] }}" data-category-slug="{{ $node['slug'] }}" data-category-active="{{ $node['is_active'] ? '1' : '0' }}" data-category-description="{{ $node['description'] ?? '' }}" data-category-image="{{ $node['image_url'] ?? '' }}" data-category-edit-url="{{ $node['edit_url'] }}" data-category-update-url="{{ $node['update_url'] }}" data-category-delete-url="{{ $node['delete_url'] }}" draggable="true">
        <div class="category-tree__row" data-category-row tabindex="0">
            <button type="button" class="category-tree__toggle" data-category-toggle aria-label="باز و بسته کردن زیر‌دسته‌ها" @if ($node['children_count'] === 0) disabled @endif>{{ $node['children_count'] ? '⌄' : '·' }}</button>
            <button type="button" class="category-tree__select" data-category-select>
                @if ($node['image_url'])<img src="{{ $node['image_url'] }}" alt="" width="40" height="40" loading="lazy">@else<span class="category-tree__placeholder">✦</span>@endif
                <span class="category-tree__name"><strong>{{ $node['name'] }}</strong><small dir="ltr">/{{ $node['slug'] }}</small></span>
                <span class="category-tree__metrics"><b>{{ $node['total_products'] }}</b> محصول <b>{{ $node['children_count'] }}</b> زیر‌دسته</span>
                <span class="admin-status {{ $node['is_active'] ? 'is-paid' : 'is-other' }}">{{ $node['is_active'] ? 'فعال' : 'غیرفعال' }}</span>
            </button>
            <span class="category-tree__drag" aria-label="قابل جابه‌جایی">⠿</span>
        </div>
        @if ($node['children'])
            <ul class="category-tree__children" data-category-level data-parent-id="{{ $node['id'] }}">
                @include('admin.categories._tree-node', ['nodes' => $node['children']])
            </ul>
        @endif
    </li>
@endforeach
