@php
$series=$series??['impressions']; $colors=['#46664d','#1c5984','#9b4e16']; $numbers=[];
foreach($trend as $r){foreach($series as $metric){if(isset($r[$metric]))$numbers[]=$r[$metric];}}
$max=max([1,...$numbers]); $count=count($trend);
$min=$series===['followers'] && $numbers ? max(0,floor(min($numbers)*0.995)) : 0;
$scale=max(1,$max-$min);
@endphp
@if(!$count)<p class="social-empty">{{ __('social.empty') }}</p>@else
<div class="social-chart"><svg viewBox="0 0 640 210" role="img" aria-label="{{ $chartTitle }}">
<text x="5" y="14" fill="currentColor" font-size="11">{{ \App\Services\Social\SocialFormat::number($max) }}</text><path d="M 40 20 V 175 H 625" fill="none" stroke="#aaa"/>
@foreach($series as $s=>$metric)
@php $points=[]; foreach($trend as $i=>$r){ if(isset($r[$metric]))$points[]=(40+($count>1?$i/($count-1)*580:0)).','.(175-($r[$metric]-$min)/$scale*145); } @endphp
<polyline points="{{ implode(' ',$points) }}" fill="none" stroke="{{ $colors[$s%3] }}" stroke-width="2.5"/>
@foreach($trend as $i=>$r)@if(isset($r[$metric]))<circle cx="{{ 40+($count>1?$i/($count-1)*580:0) }}" cy="{{ 175-($r[$metric]-$min)/$scale*145 }}" r="3.5" fill="{{ $colors[$s%3] }}"><title>{{ $r['date'] }} · {{ __('social.'.$metric) }}: {{ \App\Services\Social\SocialFormat::number($r[$metric]) }}</title></circle>@endif @endforeach
@endforeach
<text x="5" y="179" font-size="11" fill="currentColor">{{ \App\Services\Social\SocialFormat::number($min) }}</text><text x="40" y="202" font-size="11" fill="currentColor">{{ $trend[0]['date'] }}</text><text x="620" y="202" text-anchor="end" font-size="11" fill="currentColor">{{ $trend[$count-1]['date'] }}</text>
</svg></div>
<div class="social-legend">@foreach($series as $s=>$metric)<span style="border-inline-start:4px solid {{ $colors[$s%3] }}">{{ __('social.'.$metric) }}</span>@endforeach</div>
<details><summary>{{ __('social.chart_data') }}</summary><div class="social-table"><table><thead><tr><th>{{ __('social.period') }}</th>@foreach($series as $metric)<th>{{ __('social.'.$metric) }}</th>@endforeach</tr></thead><tbody>
@foreach($trend as $r)<tr><td><bdi>{{ $r['date'] }}</bdi></td>@foreach($series as $metric)<td>{{ isset($r[$metric])?\App\Services\Social\SocialFormat::number($r[$metric]):'—' }}</td>@endforeach</tr>@endforeach
</tbody></table></div></details>
@endif
