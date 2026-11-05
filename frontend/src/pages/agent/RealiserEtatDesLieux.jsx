import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, erreurApi } from '../../api'
import Champ from '../../components/Champ'
import PhotosConstat from '../../components/PhotosConstat'
import { fcfa, NIVEAUX_CARBURANT } from '../../libelles'

const DOCUMENTS = { carte_grise: 'Carte grise', assurance: 'Assurance' }
const km = (n) => `${new Intl.NumberFormat('fr-FR').format(n)} km`

/** Rappel du constat de départ, pour que l'agent compare au retour. */
function ConstatDepart({ depart }) {
  return (
    <article className="carte constat-depart">
      <h2>Constat de départ</h2>
      <dl className="details">
        <dt>Kilométrage</dt><dd>{km(depart.kilometrage)}</dd>
        <dt>Carburant</dt><dd>{depart.niveau_carburant}</dd>
      </dl>
      {depart.observations && <p className="champ-aide">Observations : {depart.observations}</p>}
      <p className="champ-aide">Dommages au départ : {depart.dommages || 'aucun'}</p>
      <PhotosConstat photos={depart.photos} legende="Photo du départ" />
    </article>
  )
}

/** Documents du véhicule, à comparer avec le véhicule présenté. */
function DocumentsVehicule({ documents }) {
  return (
    <article className="carte">
      <h2>Documents déposés par le partenaire</h2>
      <ul className="liste-docs">
        {documents.map((d) => (
          <li key={d.id}><a href={d.url} target="_blank" rel="noopener noreferrer">{DOCUMENTS[d.type] ?? d.type}</a> <span className="champ-aide">({d.nom})</span></li>
        ))}
      </ul>
    </article>
  )
}

/**
 * Cas « Réaliser un état des lieux » et « Inspecter un véhicule » (agent) :
 * départ ou remise, retour comparé au départ avec décision sur la caution, inspection d'un véhicule publié.
 */
export default function RealiserEtatDesLieux() {
  const { id } = useParams()
  const naviguer = useNavigate()
  const [mission, setMission] = useState(null)
  const [valeurs, setValeurs] = useState({ kilometrage: '', niveau_carburant: 'plein', observations: '', dommages: '', decision_caution: '', montant_dommages: '' })
  const [points, setPoints] = useState({})
  const [photos, setPhotos] = useState([])
  const [erreurs, setErreurs] = useState({})
  const [message, setMessage] = useState('')
  const [envoi, setEnvoi] = useState(false)

  useEffect(() => {
    api.get(`/etats-des-lieux/${id}`).then(({ data }) => {
      const m = data.data
      setMission(m)
      setValeurs((v) => ({ ...v, kilometrage: String(m.depart?.kilometrage ?? m.vehicule.kilometrage) }))
    }).catch((err) => setMessage(erreurApi(err).message))
  }, [id])

  const changer = (e) => setValeurs({ ...valeurs, [e.target.name]: e.target.value })

  if (!mission) return message ? <p className="alerte alerte-erreur">{message}</p> : <p className="chargement">Chargement…</p>

  const v = mission.vehicule
  const inspection = mission.type === 'inspection'
  const retour = mission.type === 'retour'
  const statuerCaution = retour && mission.caution?.statut === 'bloquee'
  const retenir = valeurs.decision_caution === 'retenir'
  const nonConforme = Object.values(points).includes('0')
  const parcourus = mission.depart && Number(valeurs.kilometrage) - mission.depart.kilometrage
  const erreurPhotos = erreurs.photos ?? Object.entries(erreurs).find(([cle]) => cle.startsWith('photos.'))?.[1]
  const erreurPoints = erreurs.points_controle ?? Object.entries(erreurs).find(([cle]) => cle.startsWith('points_controle.'))?.[1]

  async function enregistrer(e) {
    e.preventDefault()
    setErreurs({})
    setMessage('')
    setEnvoi(true)
    const formulaire = new FormData()
    const champs = inspection
      ? ['kilometrage', 'observations', 'dommages']
      : ['kilometrage', 'niveau_carburant', 'observations', 'dommages', ...(statuerCaution ? ['decision_caution', ...(retenir ? ['montant_dommages'] : [])] : [])]
    champs.forEach((cle) => { if (valeurs[cle] !== '') formulaire.append(cle, valeurs[cle]) })
    if (inspection) Object.entries(points).forEach(([cle, valeur]) => formulaire.append(`points_controle[${cle}]`, valeur))
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

  return (
    <section>
      <p><Link to="/agent/missions">← Missions</Link></p>
      <h1>{mission.libelle}</h1>
      <p className="intro">
        {v.marque} {v.modele} ({v.annee}) · {v.immatriculation} — {inspection ? `partenaire : ${mission.partenaire.nom_commercial}` : `client : ${mission.demande.client.nom}`}
      </p>
      {mission.consigne && <p className="alerte alerte-attente">Consigne de l’administrateur : {mission.consigne}</p>}

      <div className={retour || inspection ? 'grille-2 realisation' : 'realisation'}>
        {retour && mission.depart && <ConstatDepart depart={mission.depart} />}
        {inspection && <DocumentsVehicule documents={mission.documents ?? []} />}

        <form className="carte formulaire" onSubmit={enregistrer}>
          <h2>{inspection ? 'Contrôle du véhicule' : retour ? 'Constat de retour' : 'Constat'}</h2>

          {inspection && (
            <fieldset className="points-controle">
              <legend className="champ-libelle">Points de contrôle</legend>
              {mission.points_controle.map((p) => (
                <div key={p.cle} className="point-controle">
                  <span>{p.libelle}</span>
                  <span className="choix-oui-non">
                    <label><input type="radio" name={p.cle} checked={points[p.cle] === '1'} onChange={() => setPoints({ ...points, [p.cle]: '1' })} /> Conforme</label>
                    <label><input type="radio" name={p.cle} checked={points[p.cle] === '0'} onChange={() => setPoints({ ...points, [p.cle]: '0' })} /> Non conforme</label>
                  </span>
                </div>
              ))}
              {erreurPoints && <span className="champ-erreur">Indiquez le résultat de chaque point.</span>}
            </fieldset>
          )}

          <div className="grille-2">
            <Champ label="Kilométrage au compteur" name="kilometrage" type="number" min={inspection ? 0 : mission.depart?.kilometrage ?? v.kilometrage}
              value={valeurs.kilometrage} onChange={changer} erreur={erreurs.kilometrage}
              aide={inspection
                ? `Kilométrage annoncé : ${km(v.kilometrage)}`
                : retour && mission.depart
                  ? `Départ : ${km(mission.depart.kilometrage)}${parcourus > 0 ? ` · ${km(parcourus)} parcourus` : ''}`
                  : `Dernier relevé : ${km(v.kilometrage)}`} />
            {!inspection && (
              <label className="champ">
                <span className="champ-libelle">Niveau de carburant</span>
                <select className="select" name="niveau_carburant" value={valeurs.niveau_carburant} onChange={changer}>
                  {NIVEAUX_CARBURANT.map((n) => <option key={n} value={n}>{n}</option>)}
                </select>
                {retour && mission.depart && <span className="champ-aide">Au départ : {mission.depart.niveau_carburant}</span>}
              </label>
            )}
          </div>

          <label className="champ">
            <span className="champ-libelle">Observations</span>
            <textarea name="observations" rows={3} value={valeurs.observations} onChange={changer}
              placeholder={inspection ? 'Numéro de châssis, état général, équipements…' : 'Propreté, équipements présents (roue de secours, cric, triangle)…'} />
          </label>

          {(!inspection || nonConforme) && (
            <label className="champ">
              <span className="champ-libelle">{inspection ? 'Non-conformités constatées' : retour ? 'Dommages constatés depuis le départ' : 'Dommages constatés'}</span>
              <textarea name="dommages" rows={2} value={valeurs.dommages} onChange={changer}
                placeholder={inspection ? 'Décrivez ce qui ne correspond pas aux documents ou à l’annonce' : 'Laisser vide si aucun dommage'} />
              {erreurs.dommages && <span className="champ-erreur">{erreurs.dommages}</span>}
            </label>
          )}
          {inspection && nonConforme && (
            <p className="champ-aide">Une non-conformité est transmise à l’administrateur sous forme de signalement.</p>
          )}

          {statuerCaution && (
            <fieldset className="decision-caution">
              <legend className="champ-libelle">Caution versée : {fcfa(mission.caution.montant)}</legend>
              <label className="case"><input type="radio" name="decision_caution" value="restituer" checked={valeurs.decision_caution === 'restituer'} onChange={changer} /> La restituer entièrement</label>
              <label className="case"><input type="radio" name="decision_caution" value="retenir" checked={retenir} onChange={changer} /> En retenir une partie pour les dommages constatés</label>
              {retenir && (
                <Champ label="Montant retenu (FCFA)" name="montant_dommages" type="number" min="1" max={mission.caution.montant} value={valeurs.montant_dommages} onChange={changer}
                  erreur={erreurs.montant_dommages}
                  aide={valeurs.montant_dommages ? `Rendu au client : ${fcfa(mission.caution.montant - Number(valeurs.montant_dommages))}` : 'Décrivez les dommages ci-dessus.'} />
              )}
              {erreurs.decision_caution && <span className="champ-erreur">Choisissez ce que devient la caution.</span>}
            </fieldset>
          )}

          <label className="champ">
            <span className="champ-libelle">Photos du véhicule (1 à 10)</span>
            <input type="file" accept="image/*" multiple capture="environment" onChange={(e) => setPhotos([...e.target.files].slice(0, 10))} />
            <span className="champ-aide">
              {photos.length ? `${photos.length} photo(s) choisie(s)` : inspection ? 'Plaque, numéro de châssis, compteur, extérieur, intérieur.' : 'Avant, arrière, côtés, intérieur, compteur.'}
            </span>
            {erreurPhotos && <span className="champ-erreur">{erreurPhotos}</span>}
          </label>

          {(erreurs.type || message) && <p className="alerte alerte-erreur">{erreurs.type ?? message}</p>}
          <button className="bouton" disabled={envoi}>{envoi ? 'Enregistrement…' : inspection ? 'Enregistrer l’inspection' : 'Enregistrer le constat'}</button>
        </form>
      </div>
    </section>
  )
}
