<x-layout title="Change password" :admin="true">
    <header class="page-header">
        <h1>Change password</h1>
        <p>Changing your password signs you out on other devices.</p>
    </header>
    <form class="panel form-stack" method="POST" action="{{ route('admin.password.update') }}">
        @csrf
        @method('PATCH')
        <label>Current password
            <input type="password" name="current_password" autocomplete="current-password" maxlength="72" required>
        </label>
        <label>New password
            <input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required>
        </label>
        <label>Confirm new password
            <input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="72" required>
        </label>
        <small>Use at least 12 characters.</small>
        <button class="button">Save new password</button>
    </form>
</x-layout>
