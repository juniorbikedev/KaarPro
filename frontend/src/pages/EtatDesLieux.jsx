import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, erreurApi } from '../api'
import { useAuth } from '../auth/AuthContext'
import PhotosConstat from '../components/PhotosConstat'
import { dateHeure, fcfa } from '../libelles'

const RETOUR = { client: '/demandes', vendeur: '/partenaire/demandes', agent: '/agent/missions', admin: '/admin/missions' }
const DOCUMENTS = { carte_grise: 'Carte grise', assurance: 'Assurance' }
const km = (n) => `${new Intl.NumberFormat('fr-FR').format(n)} km`

/** Retour comparé au départ : kilométrage, carburant, dommages et photos côte à côte. */
function Comparaison({ depart, retour }) {
  return (
    <article className="carte">
      <h2>Comparaison avec le départ</h2>
      <div className="tableau-defilant">
        <table className="tableau">
          <thead><tr><th /><th>Départ</th><th>Retour</th></tr></thead>
          <tbody>
            <tr><th>Date</th><td>{dateHeure(depart.realise_le)}</td><td>{dateHeure(retour.realise_le)}</td></tr>
            <tr><th>Kilométrage</th><td>{km(depart.kilometrage)}</td><td>{km(retour.kilometrage)} <span className="champ-aide">(+{km(retour.kilometrage - depart.kilometrage)})</span></td></tr>
            <tr><th>Carburant</th><td>{depart.niveau_carburant}</td><td>{retour.niveau_carburant}</td></tr>
            <tr><th>Dommages</th><td>{depart.dommages || 'Aucun'}</td><td>{retour.dommages || 'Aucun'}</td></tr>
          </tbody>
        </table>
      </div>
      <div className="grille-2">
        <div><h3>Photos du départ</h3><PhotosConstat photos={depart.photos} legende="Photo du départ" /></div>
        <div><h3>Photos du retour</h3><PhotosConstat photos={retour.photos} legende="Photo du retour" /></div>
      </div>
    </article>
  )
}

/** Constat consultable par les parties (client, partenaire), les agents et l'administrateur. */
export default function EtatDesLieux() {
  const { id } = useParams()
  const { utilisateur } = useAuth()
  const [constat, setConstat] = useState(null)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    api.get(`/etats-des-lieux/${id}`).then(({ data }) => setConstat(data.data)).catch((err) => setErreur(erreurApi(err).message))
  }, [id])

  if (!constat) return erreur ? <p className="alerte alerte-erreur">{erreur}</p> : <p className="chargement">Chargement…</p>

  const v = constat.vehicule
  const inspection = constat.type === 'inspection'
  const comparer = constat.type === 'retour' && constat.depart && constat.statut === 'realise'

  return (
    <section>
      <p><Link to={RETOUR[utilisateur.role]}>← Retour</Link></p>
      <h1>{constat.libelle}</h1>
      <p className="intro">{v.marque} {v.modele} ({v.annee}) · {v.immatriculation}</p>

      {constat.statut !== 'realise' ? (
        <p className="carte">Ce constat n’a pas encore été réalisé{constat.agent ? ` (mission confiée à ${constat.agent.nom})` : ''}.</p>
      ) : (
        <div className="constat">
          <div className="grille-2">
            <article className="carte">
              {inspection && (
                <p className={`alerte ${constat.conforme ? 'alerte-succes' : 'alerte-erreur'}`}>
                  {constat.conforme ? 'Véhicule conforme à ses documents et à son annonce.' : 'Non-conformité relevée : un signalement a été transmis à l’administrateur.'}
                </p>
              )}
              <dl className="details">
                <dt>Réalisé le</dt><dd>{dateHeure(constat.realise_le)}</dd>
                <dt>Agent</dt><dd>{constat.agent?.nom}</dd>
                <dt>Kilométrage</dt><dd>{km(constat.kilometrage)}{inspection && <span className="champ-aide"> (annoncé : {km(v.kilometrage)})</span>}</dd>
                {!inspection && <><dt>Carburant</dt><dd>{constat.niveau_carburant}</dd></>}
                {constat.demande && <><dt>Client</dt><dd>{constat.demande.client.nom}</dd></>}
                <dt>Partenaire</dt><dd>{constat.partenaire.nom_commercial}</dd>
              </dl>
              {constat.observations && <><h2>Observations</h2><p>{constat.observations}</p></>}
              <h2>{inspection ? 'Non-conformités' : 'Dommages'}</h2>
              <p>{constat.dommages || (inspection ? 'Aucune.' : 'Aucun dommage constaté.')}</p>

              {inspection && (
                <>
                  <h2>Points contrôlés</h2>
                  <ul className="points-resultat">
                    {constat.points_controle.map((p) => (
                      <li key={p.cle} className={p.valeur ? 'ok' : 'ko'}>{p.valeur ? '✓' : '✗'} {p.libelle}</li>
                    ))}
                  </ul>
                </>
              )}

              {constat.caution && constat.type === 'retour' && (
                <>
                  <h2>Caution</h2>
                  <p>
                    {constat.caution.statut === 'restituee'
                      ? constat.caution.retenue > 0
                        ? <>Restituée : {fcfa(constat.caution.montant - constat.caution.retenue)} rendus, {fcfa(constat.caution.retenue)} retenus ({constat.caution.motif}).</>
                        : <>Restituée entièrement ({fcfa(constat.caution.montant)}).</>
                      : <>{fcfa(constat.caution.montant)} encore bloqués.</>}
                  </p>
                </>
              )}

              {constat.documents && (
                <>
                  <h2>Documents comparés</h2>
                  <ul className="liste-docs">
                    {constat.documents.map((d) => <li key={d.id}><a href={d.url} target="_blank" rel="noopener noreferrer">{DOCUMENTS[d.type] ?? d.type}</a></li>)}
                  </ul>
                </>
              )}
            </article>
            {!comparer && (
              <article className="carte">
                <h2>Photos</h2>
                <PhotosConstat photos={constat.photos} legende="Photo du constat" />
              </article>
            )}
          </div>
          {comparer && <Comparaison depart={constat.depart} retour={constat} />}
        </div>
      )}
    </section>
  )
}
