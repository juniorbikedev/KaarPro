import { useCallback, useEffect, useState } from 'react'
import { api } from '../api'
import { useAuth } from '../auth/AuthContext'
import { dateHeure } from '../libelles'
import Etoiles from './Etoiles'

/** Avis des clients sur un véhicule et note moyenne de son partenaire ; l'administrateur peut masquer un avis. */
export default function AvisVehicule({ vehicule }) {
  const { utilisateur } = useAuth()
  const [reponse, setReponse] = useState(null)

  const charger = useCallback(() => {
    api.get(`/vehicules/${vehicule.id}/avis`).then(({ data }) => setReponse(data)).catch(() => {})
  }, [vehicule.id])

  useEffect(() => { charger() }, [charger])

  async function masquer(avis) {
    await api.post(`/admin/avis/${avis.id}/masquer`, { est_masque: true })
    charger()
  }

  if (!reponse) return null
  const { data: avis, partenaire } = reponse

  return (
    <article className="carte bloc-avis">
      <h2>Avis des clients</h2>
      {partenaire.nombre > 0 && (
        <p className="note-partenaire">
          <Etoiles note={partenaire.moyenne} /> <strong>{partenaire.moyenne.toLocaleString('fr-FR')}/5</strong>
          <span className="champ-aide"> · {vehicule.partenaire.nom_commercial}, {partenaire.nombre} avis</span>
        </p>
      )}
      {avis.length === 0 && <p className="champ-aide">Pas encore d’avis sur ce véhicule.</p>}
      <ul className="liste-avis">
        {avis.map((a) => (
          <li key={a.id}>
            <p><Etoiles note={a.note} /> <strong>{a.auteur}</strong> <span className="champ-aide">· {dateHeure(a.cree_le)}</span></p>
            {a.commentaire && <p>{a.commentaire}</p>}
            {utilisateur?.role === 'admin' && <button type="button" className="lien lien-discret" onClick={() => masquer(a)}>Masquer cet avis</button>}
          </li>
        ))}
      </ul>
    </article>
  )
}
