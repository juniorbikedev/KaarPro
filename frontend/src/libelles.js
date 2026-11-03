export const CATEGORIES = {
  citadine: 'Citadine',
  berline: 'Berline',
  suv: 'SUV',
  '4x4': '4x4',
  utilitaire: 'Utilitaire',
  minibus: 'Minibus',
}

export const CARBURANTS = { essence: 'Essence', diesel: 'Diesel', hybride: 'Hybride', electrique: 'Électrique' }
export const BOITES = { manuelle: 'Manuelle', automatique: 'Automatique' }
export const ETATS = { disponible: 'Disponible', en_location: 'En location', en_maintenance: 'En maintenance', vendu: 'Vendu' }
export const MODES_OFFRE = { location: 'À louer', vente: 'À vendre', vente_et_location: 'À louer ou à vendre' }

/** Modes d'offre qu'un partenaire peut choisir selon son activité. */
export function modesPourActivite(typeActivite) {
  if (typeActivite === 'vente') return ['vente']
  if (typeActivite === 'location') return ['location']
  return ['location', 'vente', 'vente_et_location']
}

export function fcfa(montant) {
  if (montant === null || montant === undefined || montant === '') return '—'
  return `${new Intl.NumberFormat('fr-FR').format(montant)} FCFA`
}

export function dateLisible(iso) {
  return new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
}
