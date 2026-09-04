<x-mail::message>
# Welcome to Sajio, {{ $ownerName }}! 🎉

Great news — **{{ $restaurantName }}** is all set up and your **14-day free trial** has started.

@component('mail::panel')
**Your restaurant subdomain:** `{{ $subdomain }}.sajio.my`  
**Trial ends:** {{ $trialEndsAt }}
@endcomponent

You can start selling right away from your dashboard:

@component('mail::button', ['url' => $dashboardUrl, 'color' => 'success'])
Open your dashboard
@endcomponent

Need a hand getting started? Just reply to this email — we're happy to help you set up your menu, tables and staff.

Terima kasih,  
**The Sajio team** ☕

<x-mail::subcopy>
This email was sent to you as the owner of {{ $restaurantName }} on Sajio.my.
</x-mail::subcopy>
</x-mail::message>
