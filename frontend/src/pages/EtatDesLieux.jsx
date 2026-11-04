import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, erreurApi } from '../api'
import { useAuth } from '../auth/AuthContext'
import { dateHeure, fcfa } from '../libelles'

const RETOUR = { client: '/demandes', vendeur: '/partenaire/demandes', agent: '/agent/missions', admin: '/admin/paiements' }

/** Constat d'état des lieux, consultable par le client, le partenaire, les agents et l'administrateur. */
export default function EtatDesLieux() {
  const { id } = useParams()
  const { utilisateur } = useAuth()
  const [constat, setConstat] = useState(null)
  const [erreur, setErreur] = useState('')
  const [agrandie, setAgrandie] = useState(null)

  useEffect(() => {
    api.get(`/etats-des-lieux/${id}`).then(({ data }) => setConstat(data.data)).catch((err) => setErreur(erreurApi(err).message))
  }, [id])

  if (!constat) return erreur ? <p className="alerte alerte-erreur">{erreur}</p> : <p className="chargement">Chargement…</p>

  const d = constat.demande
  const v = d.vehicule

  return (
    <section>
      <p><Link to={RETOUR[utilisateur.role]}>← Retour</Link></p>
      <h1>{constat.libelle}</h1>
      <p className="intro">{v.marque} {v.modele} · {v.immatriculation}</p>

      {constat.statut !== 'realise' ? (
        <p className="carte">Ce constat n’a pas encore été réalisé{constat.agent ? ` (mission confiée à ${constat.agent.nom})` : ''}.</p>
      ) : (
        <div className="grille-2">
          <article className="carte">
            <dl className="details">
              <dt>Réalisé le</dt><dd>{dateHeure(constat.realise_le)}</dd>
              <dt>Agent</dt><dd>{constat.agent?.nom}</dd>
              <dt>Kilométrage</dt><dd>{new Intl.NumberFormat('fr-FR').format(constat.kilometrage)} km</dd>
              <dt>Carburant</dt><dd>{constat.niveau_carburant}</dd>
              <dt>Client</dt><dd>{d.client.nom}</dd>
              <dt>Partenaire</dt><dd>{d.partenaire.nom_commercial}</dd>
            </dl>
            {constat.observations && <><h2>Observations</h2><p>{constat.observations}</p></>}
            <h2>Dommages</h2>
            <p>{constat.dommages || 'Aucun dommage constaté.'}</p>
            {constat.type === 'retour' && constat.montant_dommages > 0 && (
              <p className="alerte alerte-erreur">Retenue proposée sur la caution : {fcfa(constat.montant_dommages)}</p>
            )}
          </article>
          <article className="carte">
            <h2>Photos</h2>
            <div className="photos-constat">
              {constat.photos.map((p, i) => (
                <button key={p.id} type="button" className="miniature-constat" onClick={() => setAgrandie(p.url)} aria-label={`Agrandir la photo ${i + 1}`}>
                  <img src={p.url} alt={`Photo ${i + 1} du constat`} />
                </button>
              ))}
            </div>
          </article>
        </div>
      )}

      {agrandie && (
        <div className="visionneuse" role="dialog" aria-label="Photo agrandie" onClick={() => setAgrandie(null)}>
          <img src={agrandie} alt="Photo agrandie du constat" />
        </div>
      )}
    </section>
  )
}
