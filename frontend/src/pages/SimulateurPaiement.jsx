import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, erreurApi } from '../api'
import { fcfa } from '../libelles'

/**
 * Page de paiement simulée (passerelle « simulateur » en développement).
 * Elle tient la place de la page de l'opérateur : en production, le client validerait sur son téléphone.
 */
export default function SimulateurPaiement() {
  const { reference } = useParams()
  const [paiement, setPaiement] = useState(null)
  const [erreur, setErreur] = useState('')
  const [envoi, setEnvoi] = useState(false)

  useEffect(() => {
    api.get(`/paiements/${reference}`).then(({ data }) => setPaiement(data.data)).catch((err) => setErreur(erreurApi(err).message))
  }, [reference])

  async function repondre(resultat) {
    setEnvoi(true)
    try {
      const { data } = await api.post(`/paiements/${reference}/simulateur`, { resultat })
      setPaiement(data.data)
    } catch (err) {
      setErreur(erreurApi(err).message)
    } finally {
      setEnvoi(false)
    }
  }

  if (!paiement) return erreur ? <p className="alerte alerte-erreur">{erreur}</p> : <p className="chargement">Chargement…</p>

  return (
    <section className="page-paiement">
      <article className="carte">
        <p className="bandeau-simulateur">Simulateur de paiement — mode développement, aucun argent n’est débité</p>
        <h1>{paiement.operateur_libelle}</h1>
        <dl className="details">
          <dt>Bénéficiaire</dt><dd>KaarPro (TerangaAuto)</dd>
          <dt>Objet</dt><dd>{paiement.demande.type === 'location' ? 'Location' : 'Achat'} — {paiement.demande.vehicule}</dd>
          <dt>Compte débité</dt><dd>{paiement.telephone}</dd>
          <dt>Référence</dt><dd>{paiement.reference}</dd>
          {paiement.montant_caution > 0 && <><dt>Dont caution</dt><dd>{fcfa(paiement.montant_caution)}</dd></>}
          <dt>Montant</dt><dd><strong className="montant-paiement">{fcfa(paiement.montant_total)}</strong></dd>
        </dl>

        {paiement.statut === 'en_attente' && (
          <div className="actions">
            <button type="button" className="bouton" disabled={envoi} onClick={() => repondre('reussi')}>Valider le paiement</button>
            <button type="button" className="bouton bouton-discret" disabled={envoi} onClick={() => repondre('echoue')}>Refuser</button>
          </div>
        )}
        {paiement.statut === 'reussi' && (
          <p className="alerte alerte-succes">Paiement confirmé. Le partenaire est prévenu ; un agent vous contactera pour la remise du véhicule.</p>
        )}
        {paiement.statut === 'echoue' && (
          <p className="alerte alerte-erreur">Le paiement n’a pas abouti. Vous pouvez réessayer depuis « Mes demandes ».</p>
        )}
        {erreur && <p className="champ-erreur">{erreur}</p>}
        {paiement.statut !== 'en_attente' && <Link to="/demandes" className="bouton bouton-secondaire">Retour à mes demandes</Link>}
      </article>
    </section>
  )
}
