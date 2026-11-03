import { Link } from 'react-router-dom'
import { CATEGORIES, fcfa } from '../libelles'

/** Illustration affichée quand un véhicule n'a pas encore de photo. */
export function SansPhoto() {
  return (
    <div className="sans-photo" aria-hidden="true">
      <svg viewBox="0 0 64 40" width="72">
        <path d="M6 28h52l-6-14a6 6 0 0 0-5.5-4h-29A6 6 0 0 0 12 14z" fill="currentColor" opacity=".35" />
        <circle cx="18" cy="30" r="5" fill="currentColor" />
        <circle cx="46" cy="30" r="5" fill="currentColor" />
      </svg>
    </div>
  )
}

export default function CarteVehicule({ vehicule, lien }) {
  const photo = vehicule.photos?.[0]?.url

  return (
    <Link to={lien ?? `/vehicules/${vehicule.id}`} className="carte carte-vehicule">
      <div className="vignette">{photo ? <img src={photo} alt={`${vehicule.marque} ${vehicule.modele}`} loading="lazy" /> : <SansPhoto />}</div>
      <div className="carte-vehicule-corps">
        <div className="pastilles">
          {vehicule.a_louer && <span className="pastille pastille-location">À louer</span>}
          {vehicule.a_vendre && <span className="pastille pastille-vente">À vendre</span>}
        </div>
        <h2>{vehicule.marque} {vehicule.modele}</h2>
        <p className="champ-aide">{vehicule.annee} · {CATEGORIES[vehicule.categorie]} · {vehicule.ville}</p>
        <div className="prix">
          {vehicule.a_louer && <span><strong>{fcfa(vehicule.prix_jour)}</strong> / jour</span>}
          {vehicule.a_vendre && <span><strong>{fcfa(vehicule.prix_vente)}</strong></span>}
        </div>
      </div>
    </Link>
  )
}
