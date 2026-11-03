import axios from 'axios'

const CLE_JETON = 'kaarpro.jeton'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api',
  headers: { Accept: 'application/json' },
})

export function lireJeton() {
  try {
    return localStorage.getItem(CLE_JETON)
  } catch {
    return null
  }
}

export function enregistrerJeton(jeton) {
  try {
    if (jeton) localStorage.setItem(CLE_JETON, jeton)
    else localStorage.removeItem(CLE_JETON)
  } catch {
    // stockage indisponible (navigation privée) : la session durera le temps de l'onglet
  }
}

api.interceptors.request.use((config) => {
  const jeton = lireJeton()
  if (jeton) config.headers.Authorization = `Bearer ${jeton}`
  return config
})

/** Transforme une erreur Axios en { message, champs } lisible par les formulaires. */
export function erreurApi(erreur) {
  const donnees = erreur?.response?.data
  if (!erreur?.response) {
    return { message: "Impossible de joindre le serveur. Vérifiez que l'API est démarrée.", champs: {} }
  }
  const champs = Object.fromEntries(Object.entries(donnees?.errors ?? {}).map(([cle, msgs]) => [cle, msgs[0]]))
  return { message: donnees?.message ?? 'Une erreur est survenue.', champs }
}
