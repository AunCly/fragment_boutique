<x-mail::message>
# Nouveau message de contact

**Nom :** {{ $senderName }}<br>
**Email :** {{ $senderEmail }}

<x-mail::panel>
{!! nl2br(e($body)) !!}
</x-mail::panel>

Répondez directement à cet email pour écrire à {{ $senderName }}.
</x-mail::message>
