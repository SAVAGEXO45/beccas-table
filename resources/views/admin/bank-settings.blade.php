<x-layout title="Bank transfer settings" :admin="true">
    <header class="page-header">
        <a class="text-link" href="{{ route('admin.payments.index') }}">Back to payments</a>
        <h1>Bank transfer settings</h1>
        <p>Changes apply to new orders. Existing orders retain their original transfer details.</p>
    </header>
    <form class="panel form-stack" method="POST" action="{{ route('admin.settings.bank.update') }}">
        @csrf @method('PATCH')
        <input type="hidden" name="enabled" value="0">
        <label class="check"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $bank['enabled']))> Offer bank transfer at checkout</label>
        <x-field name="bank_name" label="Bank name" :value="$bank['bank_name']" required maxlength="120"/>
        <x-field name="account_name" label="Account name" :value="$bank['account_name']" required maxlength="160"/>
        <label>Account number<input name="account_number" value="{{ old('account_number', $bank['account_number']) }}" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" required></label>
        <x-field name="instructions" label="Payment instructions" :value="$bank['instructions']" rows="4" required maxlength="1000"/>
        <button class="button">Save bank settings</button>
    </form>
</x-layout>
