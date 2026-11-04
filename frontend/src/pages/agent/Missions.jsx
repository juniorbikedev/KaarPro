import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, erreurApi } from '../../api'
import { dateHeure, dateLisible } from '../../libelles'

const FILTRES = { disponibles: 'À attribuer', miennes: 'Mes missions', realisees: 'Réalisées' }

/** Date utile de la mission : départ ou remise au début de la période, retour à la fin. */
function quand(mission) {
  const d = mission.demande
  if (mission.type === 'retour') return d.date_fin ? `Retour prévu le ${dateLisible(d.date_fin)}` : ''
  if (mission.type === 'depart') return d.date_debut ? `Départ prévu le ${dateLisible(d.date_debut)}` : ''
  return 'Remise après la vente'
}

/** Cas « Réaliser un état des lieux » : liste des missions de l'agent. */
export default function Missions() {
  const [filtre, setFiltre] = useState('disponibles')
  const [missions, setMissions] = useState(null)
  const [version, setVersion] = useState(0)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    let actuelle = true
    api.get('/agent/missions', { params: { filtre } })
      .then(({ data }) => { if (actuelle) setMissions(data.data) })
      .catch((err) => { if (actuelle) setErreur(erreurApi(err).message) })
    return () => { actuelle = false }
  }, [filtre, version])

  async function prendre(mission) {
    setErreur('')
    try {
      await api.post(`/agent/missions/${mission.id}/prendre`)
      setVersion((v) => v + 1)
    } catch (err) {
      setErreur(erreurApi(err).message)
    }
  }

  return (
    <section>
      <h1>Missions d’état des lieux</h1>
      <div className="onglets">
        {Object.entries(FILTRES).map(([valeur, libelle]) => (
          <button key={valeur} type="button" className={filtre === valeur ? 'onglet onglet-actif' : 'onglet'} onClick={() => { setMissions(null); setFiltre(valeur) }}>{libelle}</button>
        ))}
      </div>
      {erreur && <p className="alerte alerte-erreur">{erreur}</p>}
      {!missions && <p className="chargement">Chargement…</p>}
      {missions?.length === 0 && <p className="carte">Aucune mission dans cette catégorie.</p>}
      <div className="liste-vehicules">
        {missions?.map((m) => {
          const d = m.demande
          return (
            <article key={m.id} className="carte ligne-vehicule">
              <div className="ligne-vehicule-infos">
                <p className="carte-demande-titre"><strong>{m.libelle}</strong> · {d.vehicule.marque} {d.vehicule.modele} <span className="champ-aide">{d.vehicule.immatriculation} · {d.vehicule.ville}</span></p>
                <p>{quand(m)}</p>
                <p className="champ-aide">Client : {d.client.nom} · {d.client.telephone ?? '—'} — Partenaire : {d.partenaire.nom_commercial} · {d.partenaire.telephone ?? '—'}</p>
                {m.type === 'retour' && !m.depart_realise && <p className="champ-aide">En attente de l’état des lieux de départ.</p>}
                {m.realise_le && <p className="champ-aide">Réalisé le {dateHeure(m.realise_le)}</p>}
              </div>
              <div className="ligne-vehicule-actions">
                {m.statut === 'a_attribuer' && <button type="button" className="bouton" onClick={() => prendre(m)}>Je m’en charge</button>}
                {m.statut === 'a_faire' && m.depart_realise && <Link to={`/agent/missions/${m.id}`} className="bouton">Réaliser</Link>}
                {m.statut === 'realise' && <Link to={`/etats-des-lieux/${m.id}`} className="bouton bouton-discret">Voir le constat</Link>}
              </div>
            </article>
          )
        })}
      </div>
    </section>
  )
}
