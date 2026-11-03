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

export const TYPES_DEMANDE = { location: 'Location', achat: 'Achat' }

/** Libellé et classe CSS de chaque statut de demande. */
export const STATUTS_DEMANDE = {
  en_attente: ['En attente', 'statut-attente'],
  acceptee: ['Acceptée', 'statut-valide'],
  refusee: ['Refusée', 'statut-refuse'],
  annulee: ['Annulée', 'statut-annule'],
  payee: ['Payée', 'statut-valide'],
}

/** Nombre de jours d'une location, bornes comprises (du 10 au 12 = 3 jours). */
export function nbJours(debut, fin) {
  return Math.round((new Date(`${fin}T00:00:00`) - new Date(`${debut}T00:00:00`)) / 86_400_000) + 1
}

export function dateHeure(iso) {
  return new Date(iso).toLocaleString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}

export const OPERATEURS = { wave: 'Wave', orange_money: 'Orange Money' }

export const STATUTS_CAUTION = { bloquee: 'Caution bloquée', restituee: 'Caution restituée' }
