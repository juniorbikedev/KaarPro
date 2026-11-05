import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, erreurApi } from '../../api'
import { dateHeure, echeanceMission } from '../../libelles'

const FILTRES = { disponibles: 'À attribuer', miennes: 'Mes missions', realisees: 'Réalisées' }


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
      <h1>Mes missions</h1>
      <p className="intro">États des lieux de départ et de retour, contrôles avant remise, inspections des véhicules publiés.</p>
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
                <p className="carte-demande-titre"><strong>{m.libelle}</strong> · {m.vehicule.marque} {m.vehicule.modele} <span className="champ-aide">{m.vehicule.immatriculation} · {m.vehicule.ville}</span></p>
                <p>{echeanceMission(m)}</p>
                <p className="champ-aide">
                  {d && <>Client : {d.client.nom} · {d.client.telephone ?? '—'} — </>}Partenaire : {m.partenaire.nom_commercial} · {m.partenaire.telephone ?? '—'}
                </p>
                {m.type === 'inspection' && m.statut === 'realise' && (
                  <p><span className={`statut ${m.conforme ? 'statut-valide' : 'statut-refuse'}`}>{m.conforme ? 'Conforme' : 'Non conforme'}</span></p>
                )}
                {m.confiee_par_admin && <p className="champ-aide"><span className="statut statut-valide">Confiée par l’administrateur</span></p>}
                {m.consigne && <blockquote className="citation">Consigne : {m.consigne}</blockquote>}
                {m.type === 'retour' && !m.depart_realise && <p className="champ-aide">En attente de l’état des lieux de départ.</p>}
                {m.realise_le && <p className="champ-aide">Réalisé le {dateHeure(m.realise_le)}</p>}
              </div>
              <div className="ligne-vehicule-actions">
                {m.statut === 'a_attribuer' && <button type="button" className="bouton" onClick={() => prendre(m)}>Je m’en charge</button>}
                {m.statut === 'a_faire' && m.depart_realise && <Link to={`/agent/missions/${m.id}`} className="bouton">{m.type === 'inspection' ? 'Inspecter' : 'Réaliser'}</Link>}
                {m.statut === 'realise' && <Link to={`/etats-des-lieux/${m.id}`} className="bouton bouton-discret">Voir le constat</Link>}
              </div>
            </article>
          )
        })}
      </div>
    </section>
  )
}
