@props(['bank'])
<dl class="hours">
    <div><dt>Bank name</dt><dd>{{ $bank['bank_name'] }}</dd></div>
    <div><dt>Account name</dt><dd>{{ $bank['account_name'] }}</dd></div>
    <div><dt>Account number</dt><dd><strong>{{ $bank['account_number'] }}</strong></dd></div>
</dl>
<div class="transfer-copy print-hide">
    <button class="button small outline" type="button" data-copy-account="{{ $bank['account_number'] }}" hidden><span class="icon-label"><x-icon name="copy"/> Copy Account Number</span></button>
    <span role="status" data-copy-status></span>
</div>
