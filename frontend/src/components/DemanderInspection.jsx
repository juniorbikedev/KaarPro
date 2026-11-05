import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api, erreurApi } from '../api'

/** Administrateur : demander l'inspection d'un véhicule publié (l'agent est choisi ensuite dans « Missions »). */
export default function DemanderInspection({ vehicule }) {
  const [ouvert, setOuvert] = useState(false)
  const [consigne, setConsigne] = useState('')
  const [resultat, setResultat] = useState('')
  const [erreur, setErreur] = useState('')

  async function demander(e) {
    e.preventDefault()
    setErreur('')
    try {
      await api.post(`/admin/vehicules/${vehicule.id}/inspection`, { consigne })
      setResultat('Inspection demandée : les agents sont prévenus.')
      setOuvert(false)
    } catch (err) {
      setErreur(erreurApi(err).message)
    }
  }

  if (resultat) return <p className="champ-aide">{resultat} <Link to="/admin/missions">Confier la mission à un agent</Link></p>
  if (!ouvert) return <button type="button" className="bouton bouton-discret bouton-petit" onClick={() => setOuvert(true)}>Demander une inspection</button>

  return (
    <form className="signaler" onSubmit={demander}>
      <textarea rows={2} maxLength={1000} placeholder="Consigne pour l’agent (facultatif)" value={consigne} onChange={(e) => setConsigne(e.target.value)} />
      {erreur && <p className="champ-erreur">{erreur}</p>}
      <div className="actions">
        <button className="bouton bouton-petit">Demander l’inspection</button>
        <button type="button" className="lien" onClick={() => setOuvert(false)}>Annuler</button>
      </div>
    </form>
  )
}
