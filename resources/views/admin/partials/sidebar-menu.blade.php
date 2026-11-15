<ul class="main-menu" role="menu">
    @foreach ($menu as $item)
        @if (isset($item['can']) && ! auth('web')->user()?->can($item['can']))
            @continue
        @endif

        @if (isset($item['title']))
            <li class="menu-title" role="presentation">{{ $item['title'] }}</li>
        @elseif (isset($item['children']))
            <li class="slide">
                <a href="#!" class="side-menu__item" role="menuitem">
                    <span class="side_menu_icon"><i class="{{ $item['icon'] }}"></i></span>
                    <span class="side-menu__label">{{ $item['label'] }}</span>
                    <i class="ri-arrow-down-s-line side-menu__angle"></i>
                </a>
                <ul class="slide-menu" role="menu">
                    @foreach ($item['children'] as $child)
                        <li class="slide">
                            <a href="{{ route($child['route']) }}" @unless (! empty($child['plain'])) wire:navigate @endunless class="side-menu__item" role="menuitem" data-mw-link>{{ $child['label'] }}@if (! empty($child['plain'])) <i class="ri-external-link-line fs-12 opacity-50"></i>@endif</a>
                        </li>
                    @endforeach
                </ul>
            </li>
        @elseif (! empty($item['soon']))
            <li class="slide">
                <a href="#!" class="side-menu__item opacity-75" role="menuitem" title="Coming in a later phase">
                    <span class="side_menu_icon"><i class="{{ $item['icon'] }}"></i></span>
                    <span class="side-menu__label">{{ $item['label'] }}</span>
                    <span class="badge bg-light text-muted fs-10 ms-auto">Soon</span>
                </a>
            </li>
        @else
            <li class="slide">
                <a href="{{ route($item['route']) }}" wire:navigate class="side-menu__item" role="menuitem" data-mw-link>
                    <span class="side_menu_icon"><i class="{{ $item['icon'] }}"></i></span>
                    <span class="side-menu__label">{{ $item['label'] }}</span>
                </a>
            </li>
        @endif
    @endforeach
</ul>
