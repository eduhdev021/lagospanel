<div class="field"><label>Sua senha atual</label><input type="password" name="password" autocomplete="current-password" required></div>
@if(auth()->user()->totp_secret)<div class="field"><label>Código novo do autenticador</label><input name="code" maxlength="6" inputmode="numeric" required></div>@endif
