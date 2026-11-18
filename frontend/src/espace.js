import { useAuth } from './auth/AuthContext'

/**
 * Les pages de gestion des véhicules servent à deux espaces : celui du vendeur/loueur (/partenaire)
 * et le parc de TerangaAuto géré par l'administrateur (/admin/parc). Les chemins de l'interface
 * et de l'API sont les mêmes dans les deux cas.
 */
export function useEspace() {
  const { utilisateur } = useAuth()
  const parc = utilisateur?.role === 'admin'

  return {
    parc,
    base: parc ? '/admin/parc' : '/partenaire',
    libelleVehicules: parc ? 'Parc TerangaAuto' : 'Mes véhicules',
  }
}
