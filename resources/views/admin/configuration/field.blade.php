@php
    $value = $draft ? old('values.'.$field, config($path)) : config($path);
@endphp
<div class="field {{ $type==='textarea'?'operation-wide':'' }}">
    @if($type==='boolean')
        <input type="hidden" name="values[{{ $field }}]" value="0">
        <label class="operation-toggle"><span>{{ $label }}</span><input type="checkbox" name="values[{{ $field }}]" value="1" @checked($value)></label>
    @else
        <label for="operation-{{ $field }}">{{ $label }}</label>
        @if($type==='secret')
            <input id="operation-{{ $field }}" type="password" name="values[{{ $field }}]" maxlength="{{ $max }}" autocomplete="new-password" placeholder="{{ config($path)?'Credencial configurada — vazio mantém':'Informe a credencial' }}">
            <label class="check-line"><input type="checkbox" name="clear[{{ $field }}]" value="1"> Apagar a credencial efetiva</label>
            <small>{{ config($path)?'Credencial salva. Deixar vazio mantém o valor atual.':'Nenhuma credencial configurada.' }}</small>
        @elseif($field==='efi_environment')
            <select id="operation-{{ $field }}" name="values[{{ $field }}]">
                <option value="homologacao" @selected($value==='homologacao')>Homologação</option>
                <option value="producao" @selected($value==='producao')>Produção</option>
            </select>
        @elseif($field==='efi_certificate_type')
            <select id="operation-{{ $field }}" name="values[{{ $field }}]">
                <option value="PEM" @selected(strtoupper((string)$value)==='PEM')>PEM</option>
                <option value="P12" @selected(strtoupper((string)$value)==='P12')>P12 / PFX</option>
            </select>
        @elseif($type==='textarea')
            <textarea id="operation-{{ $field }}" name="values[{{ $field }}]" maxlength="{{ $max }}" rows="4">{{ $value }}</textarea>
        @else
            <input id="operation-{{ $field }}" name="values[{{ $field }}]" type="{{ $type==='integer'?'number':'text' }}" value="{{ $value }}" @if($type==='integer')min="{{ $min }}" max="{{ $max }}" step="1"@else maxlength="{{ $max }}"@endif @required($min>0 || $type==='integer')>
        @endif
    @endif
</div>
