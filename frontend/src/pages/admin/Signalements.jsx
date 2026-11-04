import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, erreurApi } from '../../api'
import { dateHeure, MOTIFS_SIGNALEMENT } from '../../libelles'

const FILTRES = { ouvert: 'Ouverts', traite: 'Traités', rejete: 'Rejetés', '': 'Tous' }
const STATUT = { ouvert: ['Ouvert', 'statut-attente'], traite: ['Traité', 'statut-valide'], rejete: ['Rejeté', 'statut-annule'] }

function Traitement({ signalement, onFait }) {
  const [decision, setDecision] = useState('')
  const [mesure, setMesure] = useState('')
  const [erreur, setErreur] = useState('')
  const annonce = signalement.cible.type === 'vehicule'

  async function decider(statut) {
    setErreur('')
    try {
      await api.post(`/admin/signalements/${signalement.id}/traiter`, { statut, decision, mesure: statut === 'traite' ? mesure || null : null })
      onFait()
    } catch (err) {
      const e = erreurApi(err)
      setErreur(Object.values(e.champs)[0] ?? e.message)
    }
  }

  return (
    <div className="traitement">
      <textarea rows={2} placeholder="Décision communiquée à l’auteur du signalement" value={decision} onChange={(e) => setDecision(e.target.value)} />
      <label className="case">
        <input type="checkbox" checked={Boolean(mesure)} onChange={(e) => setMesure(e.target.checked ? (annonce ? 'retirer_annonce' : 'desactiver_compte') : '')} />
        {annonce ? 'Retirer l’annonce du catalogue' : 'Désactiver le compte signalé'}
      </label>
      {erreur && <p className="champ-erreur">{erreur}</p>}
      <div className="actions">
        <button type="button" className="bouton bouton-petit" onClick={() => decider('traite')}>Valider le signalement</button>
        <button type="button" className="bouton bouton-discret bouton-petit" onClick={() => decider('rejete')}>Rejeter</button>
      </div>
    </div>
  )
}

/** Cas « Traiter les signalements » (administrateur). */
export default function Signalements() {
  const [filtre, setFiltre] = useState('ouvert')
  const [liste, setListe] = useState(null)
  const [version, setVersion] = useState(0)

  useEffect(() => {
    let actuelle = true
    api.get('/admin/signalements', { params: filtre ? { statut: filtre } : {} }).then(({ data }) => { if (actuelle) setListe(data.data) })
    return () => { actuelle = false }
  }, [filtre, version])

  return (
    <section>
      <h1>Signalements</h1>
      <div className="onglets">
        {Object.entries(FILTRES).map(([valeur, libelle]) => (
          <button key={valeur} type="button" className={filtre === valeur ? 'onglet onglet-actif' : 'onglet'} onClick={() => setFiltre(valeur)}>{libelle}</button>
        ))}
      </div>
      {!liste && <p className="chargement">Chargement…</p>}
      {liste?.length === 0 && <p className="carte">Aucun signalement dans cette catégorie.</p>}
      <div className="liste-vehicules">
        {liste?.map((s) => {
          const [libelle, classe] = STATUT[s.statut]
          return (
            <article key={s.id} className="carte signalement">
              <p className="carte-demande-titre">
                <span className={`statut ${classe}`}>{libelle}</span>
                <strong>{MOTIFS_SIGNALEMENT[s.motif]}</strong>
                <span className="champ-aide">· signalé par {s.auteur} le {dateHeure(s.cree_le)}</span>
              </p>
              <p>
                {s.cible.type === 'vehicule' ? 'Annonce : ' : 'Utilisateur : '}
                {s.cible.type === 'vehicule' && !s.cible.retiree ? <Link to={`/vehicules/${s.cible.id}`}>{s.cible.libelle}</Link> : s.cible.libelle}
                {s.cible.retiree && <span className="champ-aide"> (retirée)</span>}
                {s.cible.actif === false && <span className="champ-aide"> (compte désactivé)</span>}
              </p>
              <blockquote className="citation">{s.description}</blockquote>
              {s.decision && <p className="champ-aide">Décision : {s.decision}</p>}
              {s.statut === 'ouvert' && <Traitement signalement={s} onFait={() => setVersion((v) => v + 1)} />}
            </article>
          )
        })}
      </div>
    </section>
  )
}
