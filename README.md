# KaarPro

Plateforme web de location et de vente de véhicules, exploitée par TerangaAuto (Dakar).
Projet de fin de cycle — Licence 3 Génie logiciel.

- `backend/` : API REST Laravel 12 (PHP 8.3, Sanctum, PostgreSQL)
- `frontend/` : interface React (Vite, React Router, Axios)
- `docker-compose.yml` : base PostgreSQL de développement (port 5433 du poste)
- `docker-compose.prod.yml` : déploiement (PostgreSQL, API, interface, Caddy pour HTTPS)
- `.github/workflows/ci-cd.yml` : intégration et déploiement continus

## Avancement

| Sprint | Contenu | État |
|---|---|---|
| 2 | Comptes et rôles : client, vendeur/loueur (type d'activité, pièce d'identité), agent, administrateur ; validation des partenaires | fait |
| 3 | Publication des véhicules (photos, carte grise, assurance), calendrier de disponibilité, catalogue avec filtres | fait |
| 4 | Demandes de location et d'achat, contrat généré et signé en ligne, messagerie client ↔ partenaire | fait |
| 5 | Paiement Wave / Orange Money (passerelle simulée), commission, caution bloquée puis restituée, notifications | fait |
| 6 | États des lieux par l'agent (photos, dommages), avis et notes, signalements, tableau de bord administrateur | fait |
| 7 | Sécurité (hachage, rôles, HTTPS, en-têtes, expiration des jetons), tests sur PostgreSQL, images Docker, GitHub Actions | fait |

## Démarrer en développement (Windows)

Prérequis : WAMP (PHP 8.3), Composer, Node.js, Docker Desktop.
Le dossier `.php/` contient un `php.ini` propre au projet qui active `pdo_pgsql` sans modifier WAMP
(il n'est pas versionné : le recréer à partir du `php.ini` de WAMP en activant `pdo_pgsql` et `pgsql`).

```powershell
# 1. Base de données (Docker Desktop doit être lancé)
docker compose up -d

# 2. API sur http://localhost:8000 (migrations + comptes de démonstration)
cd backend; copy .env.example .env; cd ..
.\dev-api.ps1            # avec PostgreSQL
.\dev-api.ps1 -Sqlite    # sans Docker, base SQLite locale

# 3. Interface sur http://localhost:5173
cd frontend; copy .env.example .env; npm install; npm run dev
```

Les comptes de démonstration (un par rôle) sont créés par `php artisan db:seed` ;
leurs identifiants de test sont définis dans `backend/.env.example`.

## Tests

Par défaut, les tests tournent sur SQLite en mémoire. GitHub Actions les lance sur PostgreSQL ; pour faire de même
sur le poste, démarrer la base de développement et créer une fois la base `kaarpro_tests` :

```powershell
$env:PHPRC = "$PWD\.php"; cd backend; C:\wamp64\bin\php\php8.3.14\php.exe artisan test

# Sur PostgreSQL (docker compose up -d, puis une fois : docker exec kaarpro-postgres createdb -U kaarpro kaarpro_tests)
$env:DB_CONNECTION="pgsql"; $env:DB_PORT="5433"; $env:DB_DATABASE="kaarpro_tests"; $env:DB_USERNAME="kaarpro"; $env:DB_PASSWORD="kaarpro-dev"
C:\wamp64\bin\php\php8.3.14\php.exe artisan test
```

## Sécurité

- **Mots de passe hachés** (bcrypt), d'au moins 8 caractères.
- **Accès selon le rôle** : chaque route est réservée à son rôle (client, vendeur/loueur validé, agent, administrateur)
  et chaque ressource vérifie qu'elle appartient à l'utilisateur (demande, contrat, conversation, constat…).
- **HTTPS** en production par Caddy (certificat Let's Encrypt renouvelé automatiquement), avec HSTS.
- Compléments : jetons d'accès expirant après 7 jours (`SANCTUM_EXPIRATION`), essais de connexion limités (10 par minute),
  en-têtes de sécurité, origines autorisées (`CORS_ORIGINES`), documents et photos de constat privés (lien signé).

## Déploiement

```
Internet ──HTTPS──▶ Caddy ──▶ web (Nginx : React compilé ; /api ─FastCGI─▶ api : Laravel PHP-FPM) ──▶ postgres
```

Sur un serveur Linux avec Docker :

```bash
git clone https://github.com/juniorbikedev/KaarPro.git /opt/kaarpro && cd /opt/kaarpro
cp .env.production.example .env.production   # renseigner DOMAINE, APP_URL, mots de passe…
docker compose -f docker-compose.prod.yml --env-file .env.production run --rm api php artisan key:generate --show
# copier la clé affichée dans APP_KEY, puis :
docker compose -f docker-compose.prod.yml --env-file .env.production up -d
```

Au démarrage, l'API met sa configuration en cache, applique les migrations et crée le compte administrateur
(`ADMIN_EMAIL`, `ADMIN_PASSWORD`). Aucun compte de démonstration n'est créé en production.

**GitHub Actions** (`.github/workflows/ci-cd.yml`) :

1. à chaque push : tests de l'API sur PostgreSQL, lint et compilation de l'interface ;
2. sur `main` : construction et publication des images `ghcr.io/juniorbikedev/kaarpro-api` et `kaarpro-web` ;
3. sur `main`, si la variable de dépôt `DEPLOIEMENT_ACTIF` vaut `true` : connexion SSH au serveur
   (secrets `SERVEUR_HOTE`, `SERVEUR_UTILISATEUR`, `SERVEUR_CLE_SSH`), puis `docker compose pull` et `up -d`.

## API du sprint 2

| Méthode | Route | Rôle |
|---|---|---|
| POST | `/api/auth/inscription/client` | public |
| POST | `/api/auth/inscription/partenaire` (multipart, `piece_identite`) | public |
| POST | `/api/auth/connexion`, `/api/auth/deconnexion` | public / connecté |
| GET, PUT | `/api/moi` | connecté |
| PUT | `/api/moi/type-activite` | vendeur/loueur |
| GET | `/api/admin/utilisateurs?role=` | admin |
| POST | `/api/admin/agents` | admin |
| PATCH | `/api/admin/utilisateurs/{id}/activation` | admin |
| GET | `/api/admin/partenaires?statut=`, `/api/admin/partenaires/{id}` | admin |
| POST | `/api/admin/partenaires/{id}/valider`, `/refuser` | admin |
| GET | `/api/admin/documents/{id}/fichier` | admin |

## API du sprint 3

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/api/vehicules?q=&categorie=&offre=&ville=&prix_max=&date_debut=&date_fin=&tri=` | public |
| GET | `/api/vehicules/{id}`, `/api/photos/{id}` | public |
| GET, POST | `/api/partenaire/vehicules` (multipart : `photos[]`, `carte_grise`, `assurance`) | vendeur/loueur validé |
| GET, PUT | `/api/partenaire/vehicules/{id}` | vendeur/loueur validé |
| POST | `/api/partenaire/vehicules/{id}/retirer`, `/republier` | vendeur/loueur validé |
| POST, DELETE | `/api/partenaire/vehicules/{id}/photos`, `/photos/{photo}` | vendeur/loueur validé |
| POST, DELETE | `/api/partenaire/vehicules/{id}/indisponibilites`, `/indisponibilites/{id}` | vendeur/loueur validé |

## API du sprint 4

| Méthode | Route | Rôle |
|---|---|---|
| POST | `/api/vehicules/{id}/demandes` (`type` = location \| achat, `date_debut`, `date_fin`, `message`) | client |
| GET | `/api/demandes`, POST `/api/demandes/{id}/annuler` | client |
| GET | `/api/partenaire/demandes?statut=` | vendeur/loueur validé |
| POST | `/api/partenaire/demandes/{id}/accepter` (bloque le calendrier, génère le contrat), `/refuser` (`motif`) | vendeur/loueur validé |
| GET | `/api/contrats/{id}` | client, partenaire concerné, admin |
| POST | `/api/contrats/{id}/signer` (`accepte`) | client |
| POST | `/api/vehicules/{id}/conversation` | client |
| GET | `/api/conversations`, `/api/conversations/{id}` ; POST `/api/conversations/{id}/messages` | client, partenaire concerné |

## API du sprint 5

Le paiement passe par une passerelle (`App\Paiement\PasserellePaiement`). En développement, `PAIEMENT_PASSERELLE=simulateur`
affiche une page de paiement simulée dans l'interface : aucun argent n'est débité. Les passerelles Wave et Orange Money
se brancheront au même endroit une fois les comptes marchands obtenus. La commission est réglée par `COMMISSION_LOCATION`
et `COMMISSION_VENTE` (en %).

| Méthode | Route | Rôle |
|---|---|---|
| POST | `/api/demandes/{id}/paiements` (`operateur` = wave \| orange_money, `telephone`) — contrat signé requis | client |
| GET | `/api/paiements/{reference}` ; POST `/api/paiements/{reference}/simulateur` (`resultat` = reussi \| echoue) | client |
| GET | `/api/partenaire/paiements` (montants, commission, net) | vendeur/loueur validé |
| GET | `/api/admin/paiements?statut=` ; POST `/api/admin/paiements/{reference}/caution` (`caution_retenue`, `motif_retenue`) | admin |
| GET | `/api/notifications` ; POST `/api/notifications/{id}/lire`, `/api/notifications/tout-lire` | connecté |

## API du sprint 6

Le paiement crée les missions : état des lieux de départ et de retour pour une location, contrôle avant remise pour
une vente. La caution d'une location ne se restitue qu'après l'état des lieux de retour ; la retenue proposée par
l'agent pré-remplit le formulaire de l'administrateur. Les photos des constats sont privées et servies par lien signé.

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/api/agent/missions?filtre=disponibles\|miennes\|realisees` | agent |
| POST | `/api/agent/missions/{id}/prendre`, `/realiser` (multipart : `kilometrage`, `niveau_carburant`, `observations`, `dommages`, `montant_dommages`, `photos[]`) | agent |
| GET | `/api/etats-des-lieux/{id}` | client, partenaire concerné, agent, admin |
| GET | `/api/vehicules/{id}/avis` ; POST `/api/demandes/{id}/avis` (`note`, `commentaire`) | public ; client |
| POST | `/api/signalements` (`vehicule_id` ou `utilisateur_id`, `motif`, `description`) | connecté |
| GET | `/api/admin/signalements?statut=` ; POST `/api/admin/signalements/{id}/traiter` (`statut`, `decision`, `mesure`) | admin |
| POST | `/api/admin/avis/{id}/masquer` | admin |
| GET | `/api/admin/tableau-de-bord` | admin |
