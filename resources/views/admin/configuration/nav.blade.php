<nav class="settings-subnav" aria-label="Seções de configurações"><a class="settings-back" href="{{ route('admin.settings.index') }}">{!! panel_icon('settings',18) !!} Configurações</a><div>
@foreach([['general','globe','Geral'],['homepage','home','Página inicial'],['email','mail','E-mail'],['social','key','Login social'],['security','shield','Segurança'],['updates','refresh','Atualizações'],['environment','monitor','Ambiente']] as [$page,$icon,$label])
@if(!in_array($page,['social','security','updates'])||auth()->user()->is_admin)<a href="{{ route('admin.settings.'.$page) }}" class="{{ request()->routeIs('admin.settings.'.$page)?'is-active':'' }}" @if(request()->routeIs('admin.settings.'.$page))aria-current="page"@endif>{!! panel_icon($icon,16) !!} {{ $label }}</a>@endif
@endforeach
</div></nav>
