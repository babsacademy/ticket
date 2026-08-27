<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HMAC Secret des Billets
    |--------------------------------------------------------------------------
    |
    | Clé secrète utilisée pour signer et vérifier les QR codes des billets
    | (HMAC-SHA256). Ne jamais stocker cette clé en dur dans le code.
    |
    */

    'secret' => env('APP_TICKET_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Paiement activé
    |--------------------------------------------------------------------------
    |
    | Quand cette option est désactivée (ou que le total d'une commande est
    | de 0 FCFA), CheckoutController::store contourne entièrement Wave :
    | la commande est directement marquée payée. Utile pour tester le
    | parcours d'achat sans passerelle de paiement configurée.
    |
    */

    'payment_enabled' => (bool) env('PAYMENT_ENABLED', true),

];
