<x-layouts.portal :title="__('social.posts')"><div class="social-workspace">@include('social._nav')
<form class="social-form" method="POST" action="{{ $post?route('social.posts.update',[$account,$post]):route('social.posts.store',$account) }}">@csrf
@if($post)@method('PUT')<input type="hidden" name="revision" value="{{ $post->revision }}">@endif
@foreach(['platform_post_id','title','url','thumbnail_url'] as $field)@include('social._field',['name'=>$field,'value'=>$post?->$field,'required'=>in_array($field,['platform_post_id','title'])])@endforeach
@include('social._field',['name'=>'content','type'=>'textarea','value'=>$post?->content])
@include('social._field',['name'=>'published_at','type'=>'datetime-local','value'=>$post?->published_at->setTimezone($account->timezone)->format('Y-m-d\TH:i'),'required'=>true])
@foreach(['content_type_id'=>'types','campaign_id'=>'campaigns','event_id'=>'events'] as $field=>$key)
@include('social._field',['name'=>$field,'value'=>$post?->$field,'choices'=>[''=>__('social.all')]+$options[$key]->mapWithKeys(fn($i)=>[$i->id=>$key==='types'?$i->label():($i->name??$i->title)])->all(),'required'=>$field==='content_type_id'])
@endforeach
@include('social._field',['name'=>'language','value'=>$post?->language??'en','choices'=>collect(['en','ar','bilingual','other'])->mapWithKeys(fn($x)=>[$x=>__('social.'.$x)])->all()])
@include('social._field',['name'=>'status','value'=>$post?->status??'published','choices'=>['published'=>__('social.published'),'archived'=>__('social.archived')]])
<fieldset><legend>{{ __('social.tags') }}</legend>@foreach($options['tags'] as $tag)<label class="social-checkbox"><input type="checkbox" name="tags[]" value="{{ $tag->id }}" @checked(in_array($tag->id,old('tags',$post?->tags->pluck('id')->all()??[])))>{{ $tag->name }}</label>@endforeach</fieldset>
<button class="social-button">{{ __('social.save') }}</button></form></div></x-layouts.portal>
