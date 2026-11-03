import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

/** Réserve une page aux utilisateurs connectés, et éventuellement à certains rôles. */
export default function Protege({ roles, children }) {
  const { utilisateur, chargement } = useAuth()
  const emplacement = useLocation()

  if (chargement) return <p className="chargement">Chargement…</p>
  if (!utilisateur) return <Navigate to="/connexion" replace state={{ depuis: emplacement.pathname }} />
  if (roles && !roles.includes(utilisateur.role)) return <Navigate to="/espace" replace />
  return children
}
