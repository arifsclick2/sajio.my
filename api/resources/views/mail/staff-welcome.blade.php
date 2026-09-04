<x-mail::message>
# Hi {{ $staffName }},

You have been added as staff at **{{ $restaurantName }}** on Sajio. Use the credentials below to log in and start your shift.

@component('mail::panel')
**Temporary password:** `{{ $temporaryPassword }}`
@endcomponent

Log in, clock in when your shift starts, and you'll be ready to take orders.

@component('mail::button', ['url' => $dashboardUrl, 'color' => 'success'])
Open Sajio
@endcomponent

For security, please keep this password private. Your restaurant owner can reset it if needed.

— **The Sajio team** ☕
</x-mail::message>
