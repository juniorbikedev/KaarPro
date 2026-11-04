import { useCallback, useEffect, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { api, erreurApi } from '../api'
import { fcfa } from '../libelles'

const INTERVALLE = 5_000 // vérification de la confirmation PayTech, en millisecondes

/**
 * Page de paiement d'une demande.
 * - Avec le simulateur (développement), elle tient la place de l'opérateur : le client valide ou refuse.
 * - Avec PayTech, le client y revient après avoir payé ; la page attend la confirmation envoyée par PayTech au serveur.
 */
export default function PagePaiement() {
  const { reference } = useParams()
  const [parametres] = useSearchParams()
  const annule = parametres.get('annule') === '1'
  const [paiement, setPaiement] = useState(null)
  const [erreur, setErreur] = useState('')
  const [envoi, setEnvoi] = useState(false)

  const charger = useCallback(() => {
    api.get(`/paiements/${reference}`).then(({ data }) => setPaiement(data.data)).catch((err) => setErreur(erreurApi(err).message))
  }, [reference])

  useEffect(() => { charger() }, [charger])

  // Retour de PayTech : on interroge le serveur jusqu'à ce que la notification soit arrivée
  const attendConfirmation = paiement && !paiement.simulation && paiement.statut === 'en_attente' && !annule
  useEffect(() => {
    if (!attendConfirmation) return undefined
    const minuterie = setInterval(charger, INTERVALLE)
    return () => clearInterval(minuterie)
  }, [attendConfirmation, charger])

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
        {paiement.simulation && <p className="bandeau-simulateur">Simulateur de paiement — mode développement, aucun argent n’est débité</p>}
        <h1>{paiement.simulation ? paiement.operateur_libelle : `Paiement ${paiement.operateur_libelle}`}</h1>
        <dl className="details">
          <dt>Bénéficiaire</dt><dd>KaarPro (TerangaAuto)</dd>
          <dt>Objet</dt><dd>{paiement.demande.type === 'location' ? 'Location' : 'Achat'} — {paiement.demande.vehicule}</dd>
          <dt>Compte débité</dt><dd>{paiement.telephone}</dd>
          <dt>Référence</dt><dd>{paiement.reference}</dd>
          {paiement.montant_caution > 0 && <><dt>Dont caution</dt><dd>{fcfa(paiement.montant_caution)}</dd></>}
          <dt>Montant</dt><dd><strong className="montant-paiement">{fcfa(paiement.montant_total)}</strong></dd>
        </dl>

        {paiement.statut === 'en_attente' && paiement.simulation && (
          <div className="actions">
            <button type="button" className="bouton" disabled={envoi} onClick={() => repondre('reussi')}>Valider le paiement</button>
            <button type="button" className="bouton bouton-discret" disabled={envoi} onClick={() => repondre('echoue')}>Refuser</button>
          </div>
        )}
        {attendConfirmation && (
          <p className="alerte alerte-attente">Paiement en cours de confirmation par {paiement.operateur_libelle}… Cette page se met à jour toute seule.</p>
        )}
        {paiement.statut === 'en_attente' && !paiement.simulation && annule && (
          <p className="alerte alerte-erreur">Vous avez annulé le paiement. Vous pouvez réessayer depuis « Mes demandes ».</p>
        )}
        {paiement.statut === 'reussi' && (
          <p className="alerte alerte-succes">Paiement confirmé. Le partenaire est prévenu ; un agent vous contactera pour la remise du véhicule.</p>
        )}
        {paiement.statut === 'echoue' && (
          <p className="alerte alerte-erreur">Le paiement n’a pas abouti. Vous pouvez réessayer depuis « Mes demandes ».</p>
        )}
        {erreur && <p className="champ-erreur">{erreur}</p>}
        {(paiement.statut !== 'en_attente' || annule) && <Link to="/demandes" className="bouton bouton-secondaire">Retour à mes demandes</Link>}
      </article>
    </section>
  )
}
