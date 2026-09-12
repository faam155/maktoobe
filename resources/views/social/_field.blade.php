@php $fieldValue=old($name,$value??''); @endphp
<label>{{ __('social.'.($name==='title'?'title_field':$name)) }}
@if(isset($choices))<select name="{{ $name }}" @if($required??false) required @endif>
@foreach($choices as $option=>$label)<option value="{{ $option }}" @selected((string)$fieldValue===(string)$option)>{{ $label }}</option>@endforeach</select>
@elseif(($type??'text')==='textarea')<textarea name="{{ $name }}" rows="5" @if($required??false) required @endif>{{ $fieldValue }}</textarea>
@else<input name="{{ $name }}" type="{{ $type??'text' }}" value="{{ $fieldValue }}" @if($required??false) required @endif @if(($type??'')==='number') step="any" @endif>
@endif</label>
