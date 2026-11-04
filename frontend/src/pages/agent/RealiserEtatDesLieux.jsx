import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, erreurApi } from '../../api'
import Champ from '../../components/Champ'
import { fcfa, NIVEAUX_CARBURANT } from '../../libelles'

/** Formulaire de constat : kilométrage, carburant, observations, dommages et photos. */
export default function RealiserEtatDesLieux() {
  const { id } = useParams()
  const naviguer = useNavigate()
  const [mission, setMission] = useState(null)
  const [valeurs, setValeurs] = useState({ kilometrage: '', niveau_carburant: 'plein', observations: '', dommages: '', montant_dommages: '' })
  const [photos, setPhotos] = useState([])
  const [erreurs, setErreurs] = useState({})
  const [message, setMessage] = useState('')
  const [envoi, setEnvoi] = useState(false)

  useEffect(() => {
    api.get(`/etats-des-lieux/${id}`).then(({ data }) => {
      setMission(data.data)
      setValeurs((v) => ({ ...v, kilometrage: String(data.data.demande.vehicule.kilometrage) }))
    }).catch((err) => setMessage(erreurApi(err).message))
  }, [id])

  const changer = (e) => setValeurs({ ...valeurs, [e.target.name]: e.target.value })

  async function enregistrer(e) {
    e.preventDefault()
    setErreurs({})
    setMessage('')
    setEnvoi(true)
    const formulaire = new FormData()
    Object.entries(valeurs).forEach(([cle, valeur]) => { if (valeur !== '') formulaire.append(cle, valeur) })
    photos.forEach((photo) => formulaire.append('photos[]', photo))
    try {
      await api.post(`/agent/missions/${id}/realiser`, formulaire)
      naviguer(`/etats-des-lieux/${id}`)
    } catch (err) {
      const e2 = erreurApi(err)
      setErreurs(e2.champs)
      if (!Object.keys(e2.champs).length) setMessage(e2.message)
      setEnvoi(false)
    }
  }

  if (!mission) return message ? <p className="alerte alerte-erreur">{message}</p> : <p className="chargement">Chargement…</p>

  const d = mission.demande
  const retour = mission.type === 'retour'
  const erreurPhotos = erreurs.photos ?? Object.entries(erreurs).find(([cle]) => cle.startsWith('photos.'))?.[1]

  return (
    <section>
      <p><Link to="/agent/missions">← Missions</Link></p>
      <h1>{mission.libelle}</h1>
      <p className="intro">{d.vehicule.marque} {d.vehicule.modele} · {d.vehicule.immatriculation} — client : {d.client.nom}</p>

      <form className="carte formulaire formulaire-large" onSubmit={enregistrer}>
        <div className="grille-2">
          <Champ label="Kilométrage au compteur" name="kilometrage" type="number" min={d.vehicule.kilometrage} value={valeurs.kilometrage} onChange={changer} erreur={erreurs.kilometrage}
            aide={`Dernier relevé : ${new Intl.NumberFormat('fr-FR').format(d.vehicule.kilometrage)} km`} />
          <label className="champ">
            <span className="champ-libelle">Niveau de carburant</span>
            <select className="select" name="niveau_carburant" value={valeurs.niveau_carburant} onChange={changer}>
              {NIVEAUX_CARBURANT.map((n) => <option key={n} value={n}>{n}</option>)}
            </select>
          </label>
        </div>
        <label className="champ">
          <span className="champ-libelle">Observations</span>
          <textarea name="observations" rows={3} value={valeurs.observations} onChange={changer} placeholder="Propreté, équipements présents (roue de secours, cric, triangle)…" />
        </label>
        <label className="champ">
          <span className="champ-libelle">Dommages constatés</span>
          <textarea name="dommages" rows={2} value={valeurs.dommages} onChange={changer} placeholder="Laisser vide si aucun dommage" />
          {erreurs.dommages && <span className="champ-erreur">{erreurs.dommages}</span>}
        </label>
        {retour && (
          <Champ label="Retenue proposée sur la caution (FCFA)" name="montant_dommages" type="number" min="0" max={d.montant_caution ?? 0} value={valeurs.montant_dommages} onChange={changer}
            erreur={erreurs.montant_dommages} aide={`Caution versée : ${fcfa(d.montant_caution)}. L’administrateur décide de la restitution.`} />
        )}
        <label className="champ">
          <span className="champ-libelle">Photos du véhicule (1 à 10)</span>
          <input type="file" accept="image/*" multiple capture="environment" onChange={(e) => setPhotos([...e.target.files].slice(0, 10))} />
          <span className="champ-aide">{photos.length ? `${photos.length} photo(s) choisie(s)` : 'Avant, arrière, côtés, intérieur, compteur.'}</span>
          {erreurPhotos && <span className="champ-erreur">{erreurPhotos}</span>}
        </label>
        {(erreurs.type || message) && <p className="alerte alerte-erreur">{erreurs.type ?? message}</p>}
        <button className="bouton" disabled={envoi}>{envoi ? 'Enregistrement…' : 'Enregistrer le constat'}</button>
      </form>
    </section>
  )
}
