# KaarPro

Plateforme web de location et de vente de véhicules, exploitée par TerangaAuto (Dakar).
Projet de fin de cycle — Licence 3 Génie logiciel.

- `backend/` : API REST Laravel 12 (PHP 8.3, Sanctum, PostgreSQL)
- `frontend/` : interface React (Vite, React Router, Axios)
- `docker-compose.yml` : base PostgreSQL de développement

## Avancement

| Sprint | Contenu | État |
|---|---|---|
| 2 | Comptes et rôles : client, vendeur/loueur (type d'activité, pièce d'identité), agent, administrateur ; validation des partenaires | fait |
| 3 | Publication des véhicules (photos, carte grise, assurance), calendrier de disponibilité, catalogue avec filtres | fait |
| 4 | Demandes de location et d'achat, contrat généré et signé en ligne, messagerie client ↔ partenaire | fait |
| 5 | Paiement Wave / Orange Money, caution, commission, notifications | à faire |
| 6 | État des lieux (agent), avis, signalements, tableau de bord | à faire |
| 7 | Sécurité, tests, déploiement Docker + GitHub Actions | à faire |

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

```powershell
$env:PHPRC = "$PWD\.php"; cd backend; C:\wamp64\bin\php\php8.3.14\php.exe artisan test
```

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
