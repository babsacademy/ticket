# Architecture — E-Ticketing Sénégal

## Vue d'Ensemble

```
┌─────────────────────────────────────────────────────────────┐
│                     Navigateur Web                          │
│          (React 19 + Inertia v3 + Tailwind 4)               │
└──────────────────────┬──────────────────────────────────────┘
                       │  HTTPS / Inertia XHR
┌──────────────────────▼──────────────────────────────────────┐
│                  Laravel 13 (PHP 8.3)                       │
│  ┌─────────────┐  ┌───────────────┐  ┌───────────────────┐  │
│  │  Web Routes │  │   API Routes  │  │  Queue / Jobs     │  │
│  │  (Inertia)  │  │   /api/v1/    │  │  (paiements, mail)│  │
│  └─────────────┘  └───────────────┘  └───────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐   │
│  │                    Services                          │   │
│  │  TicketSignatureService | CommissionService          │   │
│  │  QrGeneratorService     | PaymentGatewayService      │   │
│  └──────────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────────┐   │
│  │                    Models Eloquent                   │   │
│  │  User | Event | TicketType | Ticket | Order          │   │
│  └──────────────────────────────────────────────────────┘   │
└──────────────────────┬──────────────────────────────────────┘
                       │
              ┌────────┴────────┐
              │   MySQL (prod)  │
              │  SQLite (dev)   │
              └─────────────────┘

┌─────────────────────────────┐
│  App Flutter (Scanner)      │
│  Android / iOS              │
│  ↕ REST JSON + Sanctum      │
│  ↕ /api/v1/scanner/*        │
└─────────────────────────────┘
```

## Couches Applicatives

### 1. Frontend (SPA Inertia)

- **Moteur** : Inertia.js v3 — pas d'API REST entre le frontend et le backend web, les props sont transmises côté serveur via `Inertia::render()`.
- **Pages** : `resources/js/pages/` — une page par route principale.
- **Composants** : `resources/js/components/` — composants réutilisables (UI, layouts, formulaires).
- **Routes typées** : Wayfinder génère `@/actions/` et `@/routes/` pour éviter les chaînes de route en dur.
- **Styles** : Tailwind CSS v4 — configurer via `tailwind.config.ts`.
- **Build** : Vite 8, HMR en dev, `npm run build` pour le manifest de production.

### 2. Backend Web (Controllers Inertia)

- `app/Http/Controllers/` — controllers standard Laravel.
- Chaque controller retourne `Inertia::render('PageName', $props)`.
- Les middlewares d'auth web utilisent Fortify (sessions + cookies).
- Autorisation via policies (`app/Policies/`) et gates.

### 3. API Flutter

- Préfixe : `/api/v1/scanner/`
- Auth : Laravel Sanctum — token Bearer, pas de cookie.
- Controllers dédiés : `app/Http/Controllers/Api/V1/Scanner/`
- Resources : `app/Http/Resources/Api/V1/`
- Middleware : `auth:sanctum` + `role:scanner`

### 4. Services Métier

| Service | Responsabilité |
|---------|---------------|
| `TicketSignatureService` | Générer et vérifier les signatures HMAC-SHA256 des QR codes |
| `QrGeneratorService` | Produire l'image QR à partir du payload signé |
| `CommissionService` | Calculer `commission_amount` et `net_amount` à la commande |
| `PaymentGatewayService` | Interface avec le prestataire de paiement (Wave, Orange Money…) |

### 5. Jobs & Queue

- `SendTicketEmail` — envoi de la confirmation + QR par e-mail après paiement.
- `ProcessPayout` — reversement des fonds aux organisateurs.
- Driver queue : `database` (dev), Redis recommandé en production.

## Authentification

### Web (organizers, admins)
- Laravel Fortify : login, register, password reset, email verification.
- Session PHP + cookie CSRF.
- Rôles vérifiés via middleware ou policy.

### API (scanners Flutter)
- `POST /api/v1/scanner/login` retourne un token Sanctum.
- Token stocké dans Flutter SecureStorage.
- Chaque requête envoie `Authorization: Bearer {token}`.
- Les tokens scanners n'ont pas d'accès aux routes web.

## Sécurité des Billets (HMAC-SHA256)

```
Génération (serveur) :
  payload   = base64url( JSON({ ticket_id, event_id, holder_id, issued_at }) )
  signature = HMAC-SHA256( payload, APP_TICKET_SECRET )
  qr_string = payload + "." + signature

Vérification (serveur, endpoint /verify) :
  1. Splitter qr_string sur "."
  2. Recalculer HMAC-SHA256( payload, APP_TICKET_SECRET )
  3. Comparer en constant-time (hash_equals)
  4. Vérifier ticket_id en base (existe ? déjà scanné ? événement actif ?)
```

La clé secrète `APP_TICKET_SECRET` est une chaîne aléatoire 64 caractères hex stockée dans `.env`, jamais en dur dans le code.

## Base de Données

### Schéma des Tables Principales

```sql
users
  id, name, email, password, role ENUM('admin','organizer','scanner'),
  email_verified_at, created_at, updated_at

events
  id, organizer_id (FK users), title, description, date, venue,
  city, capacity, cover_image, status ENUM('draft','published','cancelled','ended'),
  created_at, updated_at

ticket_types
  id, event_id (FK events), name, price DECIMAL(10,2), quantity,
  sold_count, sale_start, sale_end, created_at, updated_at

orders
  id, user_id (FK users, nullable pour achat invité), event_id,
  total_amount DECIMAL(10,2), commission_amount DECIMAL(10,2),
  net_amount DECIMAL(10,2),
  status ENUM('pending','paid','refunded','failed'),
  payment_reference, created_at, updated_at

tickets
  id, order_id (FK orders), ticket_type_id,
  holder_name, holder_email,
  qr_payload TEXT, signature VARCHAR(64),
  scanned_at TIMESTAMP NULL, scanned_by (FK users, role scanner),
  created_at, updated_at
```

### Index Importants

- `tickets.qr_payload` : index si recherche fréquente (ou stocker `ticket_uuid` dans le payload et indexer dessus).
- `tickets.scanned_at` : utile pour les rapports de scan en temps réel.
- `events.status, events.date` : index composite pour la page d'accueil.

## Structure des Dossiers (à créer)

```
app/
  Http/
    Controllers/
      Api/
        V1/
          Scanner/
            AuthController.php
            TicketVerifyController.php
            TicketCheckinController.php
    Resources/
      Api/
        V1/
          TicketResource.php
          ScannerUserResource.php
    Middleware/
      EnsureRole.php
  Models/
    User.php
    Event.php
    TicketType.php
    Ticket.php
    Order.php
  Services/
    TicketSignatureService.php
    QrGeneratorService.php
    CommissionService.php
  Policies/
    EventPolicy.php
    TicketPolicy.php
resources/
  js/
    pages/
      Auth/
      Dashboard/
      Events/
      Tickets/
      Admin/
    components/
      ui/          ← shadcn/ui components
      events/
      tickets/
docs/
  architecture.md  ← ce fichier
  api.md
  sprints.md
```

## Décisions d'Architecture

| Décision | Choix | Raison |
|----------|-------|--------|
| Auth API | Sanctum token | Stateless, compatible Flutter, simple |
| Signature billets | HMAC-SHA256 | Natif PHP, pas de lib externe, vérifiable offline partiel |
| Monnaie stockée | DECIMAL(10,2) en FCFA | Pas de centimes en FCFA, mais decimal garde la flexibilité |
| Versioning API | `/api/v1/` | Permet d'évoluer sans casser l'app Flutter déployée |
| Frontend | Inertia (pas d'API REST full) | Réduit la duplication serveur/client, DX Laravel naturelle |
