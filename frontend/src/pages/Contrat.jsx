import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, erreurApi } from '../api'
import { useAuth } from '../auth/AuthContext'
import { dateLisible, fcfa } from '../libelles'

// Sur un contrat, la date complète (avec l'année) est indispensable
const horodatage = (iso) => new Date(iso).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' })

/** Cas « Consulter et signer le contrat ». Le document s'imprime (ou s'enregistre en PDF) depuis le navigateur. */
export default function Contrat() {
  const { id } = useParams()
  const { utilisateur } = useAuth()
  const [contrat, setContrat] = useState(null)
  const [accepte, setAccepte] = useState(false)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    api.get(`/contrats/${id}`).then(({ data }) => setContrat(data.data)).catch((err) => setErreur(erreurApi(err).message))
  }, [id])

  async function signer() {
    setErreur('')
    try {
      const { data } = await api.post(`/contrats/${id}/signer`, { accepte })
      setContrat(data.data)
    } catch (err) {
      const e = erreurApi(err)
      setErreur(Object.values(e.champs)[0] ?? e.message)
    }
  }

  if (!contrat) return erreur ? <p className="alerte alerte-erreur">{erreur}</p> : <p className="chargement">Chargement…</p>

  const location = contrat.type === 'location'
  const retour = utilisateur.role === 'client' ? '/demandes' : '/partenaire/demandes'
  const v = contrat.vehicule

  return (
    <section>
      <div className="titre-actions sans-impression">
        <Link to={retour}>← Retour aux demandes</Link>
        <button type="button" className="bouton bouton-discret" onClick={() => window.print()}>Imprimer / PDF</button>
      </div>

      <article className="carte contrat">
        <header className="contrat-entete">
          <div>
            <p className="logo">Kaar<strong>Pro</strong></p>
            <p className="champ-aide">Plateforme exploitée par TerangaAuto · Dakar</p>
          </div>
          <div className="contrat-numero">
            <strong>Contrat n° {contrat.numero}</strong>
            <span className="champ-aide">Généré le {horodatage(contrat.genere_le)}</span>
          </div>
        </header>

        <h1>{location ? 'Contrat de location de véhicule' : 'Contrat de vente de véhicule'}</h1>

        <div className="grille-2">
          <div>
            <h2>{location ? 'Le loueur' : 'Le vendeur'}</h2>
            <p>{contrat.partenaire.nom_commercial}<br />représenté par {contrat.partenaire.representant}<br />{contrat.partenaire.telephone}</p>
          </div>
          <div>
            <h2>{location ? 'Le locataire' : "L'acheteur"}</h2>
            <p>{contrat.client.nom}<br />{contrat.client.email}<br />{contrat.client.telephone}</p>
          </div>
        </div>

        <h2>Le véhicule</h2>
        <dl className="details">
          <dt>Véhicule</dt><dd>{v.marque} {v.modele} ({v.annee})</dd>
          <dt>Immatriculation</dt><dd>{v.immatriculation}</dd>
          <dt>Kilométrage</dt><dd>{new Intl.NumberFormat('fr-FR').format(v.kilometrage)} km</dd>
        </dl>

        <h2>Conditions financières</h2>
        <dl className="details">
          {location ? (
            <>
              <dt>Période</dt><dd>du {dateLisible(contrat.date_debut)} au {dateLisible(contrat.date_fin)} ({contrat.nb_jours} jour{contrat.nb_jours > 1 ? 's' : ''})</dd>
              <dt>Prix par jour</dt><dd>{fcfa(contrat.prix_jour)}</dd>
              <dt>Montant de la location</dt><dd><strong>{fcfa(contrat.montant)}</strong></dd>
              <dt>Caution</dt><dd>{fcfa(contrat.montant_caution)}</dd>
            </>
          ) : (
            <>
              <dt>Prix de vente</dt><dd><strong>{fcfa(contrat.montant)}</strong></dd>
            </>
          )}
        </dl>

        <h2>Clauses</h2>
        <ol className="clauses">
          {contrat.clauses.map((c) => <li key={c}>{c}</li>)}
        </ol>

        <h2>Signatures</h2>
        <div className="grille-2">
          <p>{location ? 'Le loueur' : 'Le vendeur'} : <span className="statut statut-valide">signé le {horodatage(contrat.signe_partenaire_le)}</span></p>
          <p>
            {location ? 'Le locataire' : "L'acheteur"} :{' '}
            {contrat.signe_client_le
              ? <span className="statut statut-valide">signé le {horodatage(contrat.signe_client_le)}</span>
              : <span className="statut statut-attente">en attente de signature</span>}
          </p>
        </div>

        {utilisateur.role === 'client' && !contrat.signe_client_le && (
          <div className="signature sans-impression">
            <label className="case">
              <input type="checkbox" checked={accepte} onChange={(e) => setAccepte(e.target.checked)} />
              J’ai lu le contrat et j’en accepte les clauses.
            </label>
            {erreur && <p className="champ-erreur">{erreur}</p>}
            <button type="button" className="bouton" disabled={!accepte} onClick={signer}>Signer le contrat</button>
          </div>
        )}
      </article>
    </section>
  )
}
