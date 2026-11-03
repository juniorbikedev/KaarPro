import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom'
import { LIBELLES_ROLE, useAuth } from '../auth/AuthContext'
import Notifications from './Notifications'

export default function Layout() {
  const { utilisateur, deconnexion } = useAuth()
  const naviguer = useNavigate()

  async function quitter() {
    await deconnexion()
    naviguer('/')
  }

  return (
    <div className="page">
      <header className="entete">
        <Link to="/" className="logo">
          <span className="logo-icone" aria-hidden="true">K</span>
          Kaar<strong>Pro</strong>
        </Link>
        <nav className="menu">
          <NavLink to="/vehicules" end>Catalogue</NavLink>
          {utilisateur ? (
            <>
              <NavLink to="/espace">Mon espace</NavLink>
              {utilisateur.role === 'client' && (
                <>
                  <NavLink to="/demandes">Mes demandes</NavLink>
                  <NavLink to="/messages">Messages</NavLink>
                </>
              )}
              {utilisateur.role === 'vendeur' && utilisateur.partenaire?.statut_validation === 'valide' && (
                <>
                  <NavLink to="/partenaire/vehicules">Mes véhicules</NavLink>
                  <NavLink to="/partenaire/demandes">Demandes</NavLink>
                  <NavLink to="/partenaire/paiements">Encaissements</NavLink>
                  <NavLink to="/messages">Messages</NavLink>
                </>
              )}
              {utilisateur.role === 'admin' && (
                <>
                  <NavLink to="/admin/partenaires">Partenaires</NavLink>
                  <NavLink to="/admin/utilisateurs">Utilisateurs</NavLink>
                  <NavLink to="/admin/paiements">Paiements</NavLink>
                </>
              )}
              <NavLink to="/profil">Profil</NavLink>
              <Notifications />
              <span className="badge-role">{LIBELLES_ROLE[utilisateur.role]}</span>
              <button type="button" className="bouton bouton-discret" onClick={quitter}>
                Se déconnecter
              </button>
            </>
          ) : (
            <>
              <NavLink to="/connexion">Se connecter</NavLink>
              <Link to="/inscription" className="bouton bouton-petit">
                Créer un compte
              </Link>
            </>
          )}
        </nav>
      </header>
      <main className="contenu">
        <Outlet />
      </main>
      <footer className="pied">KaarPro — plateforme de location et de vente de véhicules, exploitée par TerangaAuto (Dakar).</footer>
    </div>
  )
}
