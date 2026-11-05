import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, erreurApi } from '../../api'
import { dateHeure, echeanceMission } from '../../libelles'

const FILTRES = { a_attribuer: 'À attribuer', a_faire: 'En cours', realise: 'Réalisées', '': 'Toutes' }
const STATUT = { a_attribuer: ['À attribuer', 'statut-attente'], a_faire: ['Confiée', 'statut-valide'], realise: ['Réalisée', 'statut-annule'] }


/** Choix de l'agent et consigne ; sert aussi à réattribuer une mission en cours. */
function Attribution({ mission, agents, onFait }) {
  const [ouvert, setOuvert] = useState(mission.statut === 'a_attribuer')
  const [agentId, setAgentId] = useState(mission.agent?.id ?? '')
  const [consigne, setConsigne] = useState(mission.consigne ?? '')
  const [erreur, setErreur] = useState('')

  async function attribuer(e) {
    e.preventDefault()
    setErreur('')
    try {
      await api.post(`/admin/missions/${mission.id}/attribuer`, { agent_id: Number(agentId), consigne })
      onFait()
    } catch (err) {
      const e2 = erreurApi(err)
      setErreur(Object.values(e2.champs)[0] ?? e2.message)
    }
  }

  if (!ouvert) return <button type="button" className="bouton bouton-discret bouton-petit" onClick={() => setOuvert(true)}>Réattribuer</button>

  return (
    <form className="attribution" onSubmit={attribuer}>
      <select className="select" value={agentId} onChange={(e) => setAgentId(e.target.value)} required>
        <option value="">Choisir un agent…</option>
        {agents.map((a) => (
          <option key={a.id} value={a.id}>
            {a.nom}{a.zone ? ` — ${a.zone}` : ''} ({a.missions_en_cours} en cours)
          </option>
        ))}
      </select>
      <textarea rows={2} maxLength={1000} placeholder="Consigne pour l’agent (facultatif)" value={consigne} onChange={(e) => setConsigne(e.target.value)} />
      {erreur && <p className="champ-erreur">{erreur}</p>}
      <div className="actions">
        <button className="bouton bouton-petit" disabled={!agentId}>{mission.agent ? 'Confier à cet agent' : 'Attribuer'}</button>
        {mission.statut !== 'a_attribuer' && <button type="button" className="lien" onClick={() => setOuvert(false)}>Annuler</button>}
      </div>
    </form>
  )
}

/** Cas « Attribuer une mission à un agent » (administrateur). */
export default function Missions() {
  const [filtre, setFiltre] = useState('a_attribuer')
  const [reponse, setReponse] = useState(null)
  const [version, setVersion] = useState(0)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    let actuelle = true
    api.get('/admin/missions', { params: filtre ? { statut: filtre } : {} })
      .then(({ data }) => { if (actuelle) setReponse(data) })
      .catch((err) => { if (actuelle) setErreur(erreurApi(err).message) })
    return () => { actuelle = false }
  }, [filtre, version])

  const agents = reponse?.agents ?? []

  return (
    <section>
      <h1>Missions des agents</h1>
      <p className="intro">États des lieux de départ et de retour, contrôles avant la remise d’un véhicule vendu, inspections des véhicules publiés.</p>
      <div className="onglets">
        {Object.entries(FILTRES).map(([valeur, libelle]) => (
          <button key={valeur} type="button" className={filtre === valeur ? 'onglet onglet-actif' : 'onglet'} onClick={() => { setReponse(null); setFiltre(valeur) }}>{libelle}</button>
        ))}
      </div>
      {erreur && <p className="alerte alerte-erreur">{erreur}</p>}
      {reponse && agents.length === 0 && (
        <p className="alerte alerte-attente">Aucun agent actif. Créez un compte agent dans <Link to="/admin/utilisateurs">Utilisateurs</Link>.</p>
      )}
      {!reponse && <p className="chargement">Chargement…</p>}
      {reponse?.data.length === 0 && <p className="carte">Aucune mission dans cette catégorie.</p>}
      <div className="liste-vehicules">
        {reponse?.data.map((m) => {
          const d = m.demande
          const [libelle, classe] = STATUT[m.statut]
          return (
            <article key={m.id} className="carte ligne-vehicule mission">
              <div className="ligne-vehicule-infos">
                <p className="carte-demande-titre">
                  <span className={`statut ${classe}`}>{libelle}</span>
                  <strong>{m.libelle}</strong> · {m.vehicule.marque} {m.vehicule.modele}
                  <span className="champ-aide">{m.vehicule.immatriculation} · {m.vehicule.ville}</span>
                </p>
                <p>{echeanceMission(m)}</p>
                <p className="champ-aide">{d && <>Client : {d.client.nom} · </>}Partenaire : {m.partenaire.nom_commercial}</p>
                {m.type === 'inspection' && m.statut === 'realise' && (
                  <p><span className={`statut ${m.conforme ? 'statut-valide' : 'statut-refuse'}`}>{m.conforme ? 'Conforme' : 'Non conforme'}</span></p>
                )}
                {m.agent && (
                  <p className="champ-aide">
                    Agent : <strong>{m.agent.nom}</strong>
                    {m.attribuee_le && <> · {m.confiee_par_admin ? 'confiée' : 'prise'} le {dateHeure(m.attribuee_le)}</>}
                  </p>
                )}
                {m.consigne && <blockquote className="citation">{m.consigne}</blockquote>}
                {m.realise_le && <p className="champ-aide">Réalisée le {dateHeure(m.realise_le)} · <Link to={`/etats-des-lieux/${m.id}`}>Voir le constat</Link></p>}
              </div>
              {m.statut !== 'realise' && agents.length > 0 && (
                <div className="ligne-vehicule-actions">
                  <Attribution mission={m} agents={agents} onFait={() => setVersion((v) => v + 1)} />
                </div>
              )}
            </article>
          )
        })}
      </div>
    </section>
  )
}
