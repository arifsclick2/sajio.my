<x-mail::message>
# Hi {{ $ownerName }},

Your **14-day free trial** for **{{ $restaurantName }}** has ended.

To keep selling without interruption, just pick a package — your restaurant data (menu, tables, staff, history) is safe and waiting.

@component('mail::button', ['url' => $billingUrl, 'color' => 'success'])
Subscribe now
@endcomponent

- **Basic RM299** / **Premium RM499** / **Pro RM999** per month
- No setup fee · Cancel anytime

Terima kasih,  
**The Sajio team** ☕
</x-mail::message>
