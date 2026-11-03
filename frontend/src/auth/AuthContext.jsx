import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { api, enregistrerJeton, lireJeton } from '../api'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [utilisateur, setUtilisateur] = useState(null)
  const [chargement, setChargement] = useState(Boolean(lireJeton()))

  const rafraichir = useCallback(async () => {
    if (!lireJeton()) {
      setUtilisateur(null)
      setChargement(false)
      return
    }
    try {
      const { data } = await api.get('/moi')
      setUtilisateur(data.data)
    } catch {
      enregistrerJeton(null)
      setUtilisateur(null)
    } finally {
      setChargement(false)
    }
  }, [])

  useEffect(() => {
    rafraichir()
  }, [rafraichir])

  const ouvrirSession = useCallback(({ token, utilisateur }) => {
    enregistrerJeton(token)
    setUtilisateur(utilisateur)
  }, [])

  const deconnexion = useCallback(async () => {
    try {
      await api.post('/auth/deconnexion')
    } catch {
      // le jeton a peut-être déjà expiré : on ferme la session locale quoi qu'il arrive
    }
    enregistrerJeton(null)
    setUtilisateur(null)
  }, [])

  const valeur = useMemo(
    () => ({ utilisateur, setUtilisateur, chargement, ouvrirSession, deconnexion, rafraichir }),
    [utilisateur, chargement, ouvrirSession, deconnexion, rafraichir],
  )

  return <AuthContext.Provider value={valeur}>{children}</AuthContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth() {
  return useContext(AuthContext)
}

// eslint-disable-next-line react-refresh/only-export-components
export const LIBELLES_ROLE = {
  client: 'Client',
  vendeur: 'Vendeur / loueur',
  agent: 'Agent',
  admin: 'Administrateur',
}

// eslint-disable-next-line react-refresh/only-export-components
export const LIBELLES_ACTIVITE = {
  vente: 'Vente uniquement',
  location: 'Location uniquement',
  vente_et_location: 'Vente et location',
}
