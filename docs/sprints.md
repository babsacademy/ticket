# Plan des Sprints — TerangaTicket

Durée de chaque sprint : **2 semaines**. Priorité : livrer un MVP fonctionnel end-to-end avant d'ajouter des fonctionnalités avancées.

---

## Sprint 1 — Fondations & Auth (semaines 1–2)

**Objectif** : Avoir une application qui tourne avec les 3 rôles authentifiés, les modèles de base, et le CRUD événement minimal.

### Backend

- [ ] Installer et configurer **Laravel Sanctum** (`composer require laravel/sanctum`)
- [ ] Ajouter le champ `role ENUM('admin','organizer','scanner')` à la migration `users`
- [ ] Créer le middleware `EnsureRole` pour protéger les routes par rôle
- [ ] Créer les modèles + migrations : `Event`, `TicketType`, `Order`, `Ticket`
- [ ] Créer les factories et seeders pour chaque modèle
- [ ] Configurer `config/tickets.php` + variable `APP_TICKET_SECRET`
- [ ] Seeder initial : 1 admin, 2 organisateurs, 2 scanners de test

### API Flutter

- [ ] Créer `POST /api/v1/scanner/login` (`Api\V1\Scanner\AuthController`)
- [ ] Tester l'endpoint avec Postman / test Pest

### Frontend Web

- [ ] Adapter les pages Fortify existantes (login, register) pour prendre en compte le `role`
- [ ] Page de profil montrant le rôle de l'utilisateur connecté
- [ ] Redirect post-login selon rôle : admin → `/admin`, organizer → `/dashboard`, scanner → page d'erreur

### Tests

- [ ] `Feature/Auth/ScannerLoginTest.php` — succès, mauvais mdp, mauvais rôle
- [ ] `Feature/Auth/RoleMiddlewareTest.php` — accès refusé si mauvais rôle

### Critères de Done

- Les 3 types de comptes peuvent se connecter.
- Un scanner reçoit un token Sanctum via `/scanner/login`.
- Un organizer connecté voit son dashboard (vide pour l'instant).
- Tous les tests de ce sprint passent.

---

## Sprint 2 — Gestion des Événements & Billets (semaines 3–4)

**Objectif** : Les organisateurs peuvent créer des événements avec des types de billets. Les billets sont achetables (sans paiement réel pour l'instant).

### Backend

- [ ] CRUD complet `Event` (scoped à l'organisateur connecté — `EventPolicy`)
- [ ] CRUD `TicketType` rattaché à un événement
- [ ] `CommissionService::calculate(float $price): array` → `[total, commission, net]`
- [ ] Création d'`Order` + `Ticket`(s) avec calcul de commission à l'achat
- [ ] `TicketSignatureService::generate(Ticket $ticket): string`
- [ ] `TicketSignatureService::verify(string $qrString): array`
- [ ] Génération du QR code (librairie PHP, ex: `endroid/qr-code`)
- [ ] Job `SendTicketEmail` — envoyer les billets PDF + QR par e-mail

### API Flutter

- [ ] `POST /api/v1/scanner/tickets/verify` (`TicketVerifyController`)
- [ ] `POST /api/v1/scanner/tickets/checkin` (`TicketCheckinController`)
- [ ] Middleware `role:scanner` sur le groupe de routes

### Frontend Web

- [ ] Page liste des événements de l'organisateur
- [ ] Page création / édition d'un événement
- [ ] Page gestion des types de billets (prix, quantité, dates de vente)
- [ ] Page publique d'un événement (pour les acheteurs)
- [ ] Formulaire d'achat de billet (sans paiement — statut `paid` direct pour tester)

### Tests

- [ ] `Feature/Events/CreateEventTest.php`
- [ ] `Feature/Events/TicketTypeTest.php`
- [ ] `Feature/Scanner/VerifyTicketTest.php` — signature valide, invalide, déjà scanné
- [ ] `Feature/Scanner/CheckinTicketTest.php` — succès, double scan (409)
- [ ] `Unit/Services/TicketSignatureServiceTest.php`
- [ ] `Unit/Services/CommissionServiceTest.php`

### Critères de Done

- Un organisateur peut créer un événement avec 2 types de billets.
- Un acheteur peut acheter un billet et reçoit un e-mail avec le QR.
- L'app Flutter peut vérifier et checker un billet.
- La commission de 10 % est correctement stockée dans chaque `Order`.

---

## Sprint 3 — Paiement & Tableau de Bord (semaines 5–6)

**Objectif** : Intégration d'un vrai moyen de paiement (Wave Sénégal ou Orange Money) et tableaux de bord organizer/admin.

### Backend

- [ ] Intégration gateway paiement (Wave API ou Stripe selon disponibilité)
- [ ] `PaymentGatewayService::initiate(Order $order): string` → URL de paiement
- [ ] Webhook de confirmation de paiement → passer `Order::status` à `paid`
- [ ] Génération effective des `Ticket`(s) et envoi e-mail **après** confirmation paiement
- [ ] Endpoint de vérification du statut de commande (polling Flutter / web)
- [ ] `ProcessPayout` job — calcul du montant à reverser à l'organisateur

### Frontend Web

- [ ] Page tableau de bord organisateur :
  - Nombre de billets vendus / disponibles par événement
  - Revenus (net après commission)
  - Dernières transactions
- [ ] Page tableau de bord admin :
  - Total des commissions collectées
  - Liste des organisateurs et leurs revenus
  - Événements actifs / à venir
- [ ] Page de paiement (redirection vers gateway ou formulaire intégré)
- [ ] Page de confirmation d'achat avec lien de téléchargement du billet

### Tests

- [ ] `Feature/Payment/PaymentInitiateTest.php`
- [ ] `Feature/Payment/PaymentWebhookTest.php`
- [ ] `Feature/Dashboard/OrganizerDashboardTest.php`
- [ ] `Feature/Dashboard/AdminDashboardTest.php`

### Critères de Done

- Un acheteur peut payer via le gateway et reçoit son billet après confirmation.
- L'organisateur voit ses stats de ventes en temps réel.
- L'admin voit les commissions collectées.

---

## Sprint 4 — Scanner Flutter & Qualité (semaines 7–8)

**Objectif** : L'app Flutter de scan est fonctionnelle sur le terrain. Robustesse et gestion des cas limites.

### Backend

- [ ] Gestion des cas limites scan : événement annulé, billet d'un autre événement, billet remboursé
- [ ] Logs d'audit des scans (table `scan_logs` ou colonne enrichie sur `tickets`)
- [ ] Rate limiting stricts sur les endpoints scanner (60 req/min)
- [ ] Endpoint de récupération des infos d'un événement pour le mode offline Flutter
- [ ] API de stats de scan en temps réel pour l'organisateur (WebSocket ou polling)
- [ ] Rotation des tokens Sanctum (expiration configurable)

### Frontend Web

- [ ] Page de suivi des scans en temps réel pour l'organisateur (tableau + compteur)
- [ ] Page de gestion des scanners (admin : créer, désactiver des comptes scanner)
- [ ] Notifications e-mail à l'organisateur : alerte si le seuil de capacité est atteint

### Flutter (spécifications pour l'équipe mobile)

- [ ] Écran login scanner
- [ ] Écran caméra QR avec feedback visuel (vert / rouge)
- [ ] Affichage des infos du détenteur du billet après scan
- [ ] Bouton de confirmation de check-in
- [ ] Gestion du mode hors-ligne (cache des événements du jour)
- [ ] Historique local des scans de la session

### Tests

- [ ] `Feature/Scanner/EdgeCasesTest.php` — événement annulé, mauvais événement, billet remboursé
- [ ] `Feature/Scanner/RateLimitTest.php`
- [ ] Tests de charge (optionnel : k6 ou Artillery pour 100 req/s simultanées)

### Critères de Done

- L'app Flutter scan valide un billet en < 2 secondes sur 4G.
- Les cas d'erreurs sont tous gérés avec des messages clairs.
- Aucun double check-in possible même en cas de tap double.

---

## Sprint 5 — Finalisation MVP & Mise en Production (semaines 9–10)

**Objectif** : Prêt pour la production. Performance, sécurité, déploiement.

### Sécurité & Performance

- [ ] Audit de sécurité des endpoints API (injection, IDOR, mass assignment)
- [ ] HTTPS forcé, headers de sécurité (`Content-Security-Policy`, `X-Frame-Options`…)
- [ ] Optimisation des requêtes N+1 (Laravel Debugbar en dev, Telescope en staging)
- [ ] Cache des pages publiques (événements publiés) avec `cache()` ou Redis
- [ ] Compression des images de couverture d'événements (Intervention Image ou Spatie Media)
- [ ] Pagination sur toutes les listes (billets, événements, commandes)

### Déploiement

- [ ] Configuration Laravel Cloud (ou VPS + Forge)
- [ ] Variables d'environnement production (DB, mail, gateway, `APP_TICKET_SECRET`)
- [ ] Migration et seeding de la base de production (admin initial)
- [ ] Queue worker en production (Supervisor ou Laravel Cloud)
- [ ] Monitoring : Laravel Telescope (staging), Sentry (prod)
- [ ] Backup automatique de la base de données

### Qualité Finale

- [ ] Suite de tests complète qui passe : `php artisan test --compact`
- [ ] Couverture des cas critiques : paiement, scan, commission
- [ ] Documentation API mise à jour (`docs/api.md`)
- [ ] README avec instructions d'installation

### Tests d'Acceptance

- [ ] Parcours complet : créer un événement → acheter un billet → payer → recevoir e-mail → scanner le QR → voir "Entrée validée"
- [ ] Test de charge sur les endpoints scanner avec 50 scanners simultanés
- [ ] Vérification que la commission est correcte sur 100 commandes de test

### Critères de Done

- Tous les parcours utilisateur fonctionnent de bout en bout.
- Zéro erreur 5xx en production après 24h de smoke tests.
- L'app Flutter peut scanner 200 billets/heure sans dégradation.

---

## Récapitulatif des Sprints

| Sprint | Semaines | Livrable clé |
|--------|----------|-------------|
| 1 | 1–2 | Auth 3 rôles + API login scanner |
| 2 | 3–4 | CRUD événements + billets + vérification QR |
| 3 | 5–6 | Paiement réel + dashboards |
| 4 | 7–8 | App Flutter terrain + robustesse |
| 5 | 9–10 | Production-ready + déploiement |

**Date de début estimée** : semaine du 25 août 2026
**MVP livré** : ~mi-octobre 2026
