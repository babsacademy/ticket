# Contrat API Flutter — E-Ticketing Sénégal

## Informations Générales

| Propriété | Valeur |
|-----------|--------|
| Base URL | `https://e-ticketing.sn/api/v1` |
| Format | JSON (`Content-Type: application/json`) |
| Auth | Bearer Token (Laravel Sanctum) |
| Versioning | Préfixe `/v1/` dans l'URL |
| Rate limit | 60 req/min par token (endpoints scanner) |
| Timezone | `Africa/Dakar` (UTC+0) |

## Codes HTTP Utilisés

| Code | Signification |
|------|--------------|
| 200 | Succès |
| 201 | Ressource créée |
| 401 | Non authentifié (token absent ou expiré) |
| 403 | Accès refusé (mauvais rôle) |
| 409 | Conflit (billet déjà scanné) |
| 422 | Erreur de validation |
| 429 | Rate limit dépassé |
| 500 | Erreur serveur |

## Format des Erreurs

```json
{
  "message": "Description lisible de l'erreur",
  "errors": {
    "champ": ["Message de validation"]
  }
}
```

---

## Endpoints Scanner

Tous les endpoints ci-dessous sont préfixés par `/api/v1/scanner/`.

---

### 1. Authentification du Scanner

```
POST /api/v1/scanner/login
```

Authentifie un agent scanner et retourne un token Sanctum.

**Aucune authentification requise.**

#### Corps de la requête

```json
{
  "email": "scanner@evenement.sn",
  "password": "motdepasse123",
  "device_name": "Samsung Galaxy A54"
}
```

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `email` | string | oui | Email du compte scanner |
| `password` | string | oui | Mot de passe |
| `device_name` | string | non | Identifiant de l'appareil (pour nommer le token Sanctum) |

#### Réponse 200 — Succès

```json
{
  "token": "1|aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789",
  "scanner": {
    "id": 42,
    "name": "Mamadou Diallo",
    "email": "scanner@evenement.sn"
  }
}
```

#### Réponse 422 — Identifiants invalides

```json
{
  "message": "Les identifiants fournis sont incorrects.",
  "errors": {
    "email": ["Les identifiants fournis sont incorrects."]
  }
}
```

#### Réponse 403 — Compte non-scanner

```json
{
  "message": "Ce compte n'a pas les droits de scanner."
}
```

---

### 2. Vérification d'un Billet

```
POST /api/v1/scanner/tickets/verify
Authorization: Bearer {token}
```

Vérifie l'authenticité d'un billet à partir du payload QR scanné. Ne marque **pas** le billet comme utilisé — utiliser `/checkin` pour cela.

#### Corps de la requête

```json
{
  "qr_payload": "eyJ0aWNrZXRfaWQiOjEyMywiZXZlbnRfaWQiOjcsImhvbGRlcl9pZCI6NDU2LCJpc3N1ZWRfYXQiOiIyMDI2LTA4LTEwVDEwOjAwOjAwWiJ9.a1b2c3d4e5f6..."
}
```

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `qr_payload` | string | oui | Chaîne complète lue depuis le QR code (`payload.signature`) |

#### Réponse 200 — Billet valide

```json
{
  "valid": true,
  "ticket": {
    "id": 123,
    "holder_name": "Fatou Sow",
    "holder_email": "fatou@example.com",
    "ticket_type": "VIP",
    "event": {
      "id": 7,
      "title": "Dakar Jazz Festival 2026",
      "date": "2026-09-15T20:00:00+00:00",
      "venue": "Théâtre National Daniel Sorano"
    },
    "scanned_at": null
  }
}
```

#### Réponse 200 — Billet invalide

```json
{
  "valid": false,
  "reason": "already_scanned",
  "scanned_at": "2026-09-15T19:47:00+00:00"
}
```

```json
{
  "valid": false,
  "reason": "invalid_signature"
}
```

```json
{
  "valid": false,
  "reason": "event_ended"
}
```

```json
{
  "valid": false,
  "reason": "ticket_not_found"
}
```

| Valeur `reason` | Signification |
|----------------|--------------|
| `already_scanned` | Le billet a déjà été utilisé |
| `invalid_signature` | La signature HMAC ne correspond pas |
| `ticket_not_found` | L'ID du billet n'existe pas en base |
| `event_ended` | L'événement est terminé ou annulé |
| `wrong_event` | Le billet appartient à un autre événement |

#### Réponse 401 — Token absent ou expiré

```json
{
  "message": "Unauthenticated."
}
```

#### Réponse 422 — Payload manquant

```json
{
  "message": "Le champ qr_payload est obligatoire.",
  "errors": {
    "qr_payload": ["Le champ qr_payload est obligatoire."]
  }
}
```

---

### 3. Confirmation du Scan (Check-in)

```
POST /api/v1/scanner/tickets/checkin
Authorization: Bearer {token}
```

Marque le billet comme scanné (utilisé). Doit être appelé **après** un `/verify` retournant `valid: true`. Idempotent si appelé deux fois pour le même billet : retourne 409.

#### Corps de la requête

```json
{
  "ticket_id": 123
}
```

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `ticket_id` | integer | oui | ID du billet à valider |

#### Réponse 200 — Check-in réussi

```json
{
  "checked_in_at": "2026-09-15T19:52:14+00:00",
  "ticket": {
    "id": 123,
    "holder_name": "Fatou Sow",
    "ticket_type": "VIP"
  },
  "event": {
    "id": 7,
    "title": "Dakar Jazz Festival 2026"
  },
  "scanned_by": {
    "id": 42,
    "name": "Mamadou Diallo"
  }
}
```

#### Réponse 409 — Billet déjà scanné

```json
{
  "message": "Ce billet a déjà été scanné.",
  "scanned_at": "2026-09-15T19:47:00+00:00",
  "scanned_by": "Mamadou Diallo"
}
```

#### Réponse 404 — Billet introuvable

```json
{
  "message": "Billet introuvable."
}
```

---

## Format du QR Code

Le QR code contient une chaîne au format :

```
{payload_base64url}.{signature_hex}
```

### Génération côté serveur

```php
// Dans TicketSignatureService::generate(Ticket $ticket): string

$data = [
    'ticket_id' => $ticket->id,
    'event_id'  => $ticket->ticketType->event_id,
    'holder_id' => $ticket->order->user_id,
    'issued_at' => $ticket->created_at->toIso8601String(),
];

$payload   = base64_encode(json_encode($data)); // ou rtrim(strtr(base64_encode(...), '+/', '-_'), '=') pour URL-safe
$signature = hash_hmac('sha256', $payload, config('tickets.secret'));

return $payload . '.' . $signature;
```

### Vérification côté serveur

```php
// Dans TicketSignatureService::verify(string $qrString): array

[$payload, $signature] = explode('.', $qrString, 2);

$expected = hash_hmac('sha256', $payload, config('tickets.secret'));

if (! hash_equals($expected, $signature)) {
    return ['valid' => false, 'reason' => 'invalid_signature'];
}

$data = json_decode(base64_decode($payload), true);
// Vérifier ticket_id en base...
```

---

## Flux d'Utilisation Flutter

```
Lancement app
     │
     ▼
POST /scanner/login
     │  ← token stocké dans SecureStorage
     ▼
Ouverture caméra QR
     │
     ▼
Scan QR → récupérer qr_payload
     │
     ▼
POST /scanner/tickets/verify  (qr_payload)
     │
     ├─ valid: false → afficher raison (rouge)
     │
     └─ valid: true  → afficher infos billet (vert)
              │
              ▼ (agent confirme d'un tap)
         POST /scanner/tickets/checkin  (ticket_id)
              │
              └─ 200 → "Entrée validée" ✓
```

---

## Configuration Laravel (`config/tickets.php`)

```php
<?php

return [
    'secret' => env('APP_TICKET_SECRET'),
];
```

`.env` :
```
APP_TICKET_SECRET=votre_cle_aleatoire_64_caracteres_hex
```

Générer la clé :
```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

---

## Notes d'Implémentation

- **Idempotence** : `/checkin` doit être idempotent — un double appel retourne 409, pas une erreur 500.
- **Atomic check** : utiliser une transaction DB ou `updateOrFail` avec un verrou pour éviter les double check-ins concurrents sur le même billet.
- **Offline partiel** : l'app Flutter peut mettre en cache les événements du jour pour afficher les infos sans réseau, mais la validation HMAC et l'écriture `scanned_at` nécessitent une connexion.
- **Logs** : chaque scan (réussi ou non) doit être logué avec `ticket_id`, `scanner_id`, `timestamp`, `result`, pour l'audit.
