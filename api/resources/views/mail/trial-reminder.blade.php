<x-mail::message>
# Hi {{ $ownerName }},

Your Sajio free trial for **{{ $restaurantName }}** ends in **{{ $daysRemaining > 1 ? $daysRemaining.' days' : '1 day' }}** ({{ $trialEndsAt }}).

Once the trial ends, ordering will pause until you choose a package — your menu, tables and data stay safe.

@component('mail::button', ['url' => $billingUrl, 'color' => 'success'])
Choose a package
@endcomponent

Basic, Premium and Pro plans start from RM299/month — no setup fee.

Terima kasih,  
**The Sajio team** ☕
</x-mail::message>
