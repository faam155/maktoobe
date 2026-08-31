@props(['name','label','type'=>'text','autocomplete'=>null,'value'=>'','required'=>true,'hint'=>null,'dir'=>null])
<div class="auth-field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        value="{{ $type==='password' || $name==='code' ? '' : old($name,$value) }}"
        @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if($dir) dir="{{ $dir }}" @endif @required($required)
        @if($hint) aria-describedby="{{ $name }}-hint{{ $errors->has($name) ? ' '.$name.'-error':'' }}" @elseif($errors->has($name)) aria-describedby="{{ $name }}-error" @endif
        aria-invalid="{{ $errors->has($name) ? 'true':'false' }}" {{ $attributes }}>
    @if($hint)<p id="{{ $name }}-hint" class="auth-hint">{{ $hint }}</p>@endif
    @error($name)<p id="{{ $name }}-error" class="auth-field-error">{{ $message }}</p>@enderror
</div>
