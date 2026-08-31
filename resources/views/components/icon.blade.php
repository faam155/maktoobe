@props(['name'])
<svg {{ $attributes->class('icon') }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('grid')<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>@break
        @case('layers')<path d="m3 8 9-5 9 5-9 5-9-5Zm0 5 9 5 9-5M3 18l9 5 9-5"/>@break
        @case('settings')<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3" fill="currentColor"/><circle cx="16" cy="17" r="3" fill="currentColor"/>@break
        @case('language')<circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/>@break
        @case('menu')<path d="M4 7h16M4 12h16M4 17h16"/>@break
        @case('close')<path d="m6 6 12 12M18 6 6 18"/>@break
        @case('arrow')<path d="M4 12h16m-6-6 6 6-6 6"/>@break
        @case('screens')<rect x="3" y="4" width="15" height="11" rx="1"/><path d="M7 20h6m-3-5v5"/><rect x="16" y="11" width="5" height="10" rx="1" fill="var(--surface)"/>@break
        @case('check')<path d="m5 12 4 4L19 6"/>@break
        @case('lock')<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>@break
    @endswitch
</svg>
