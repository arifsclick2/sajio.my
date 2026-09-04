<x-mail::message>
# New restaurant registered 🏪

A new restaurant has just signed up for Sajio.

@component('mail::table')
| Field | Value |
|:------|:------|
| Restaurant | **{{ $restaurantName }}** |
| Subdomain | `{{ $subdomain }}.sajio.my` |
| Owner | {{ $ownerName }} |
| Owner email | {{ $ownerEmail }} |
| Registered | {{ $registeredAt }} |
@endcomponent

@component('mail::button', ['url' => config('app.url').'/super-admin'])
Open Super Admin
@endcomponent

— Sajio system notification
</x-mail::message>
