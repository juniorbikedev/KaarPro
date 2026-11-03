import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, erreurApi } from '../api'
import { useAuth } from '../auth/AuthContext'
import { dateLisible, fcfa, nbJours } from '../libelles'
import Calendrier from './Calendrier'

/**
 * Bloc de la fiche véhicule : envoyer une demande de location ou d'achat, contacter le partenaire.
 * Seul un client connecté peut agir ; les autres visiteurs voient le calendrier et une invitation à se connecter.
 */
export default function DemandeVehicule({ vehicule }) {
  const { utilisateur, chargement } = useAuth()
  const naviguer = useNavigate()
  const [type, setType] = useState(vehicule.a_louer ? 'location' : 'achat')
  const [debut, setDebut] = useState('')
  const [fin, setFin] = useState('')
  const [message, setMessage] = useState('')
  const [erreur, setErreur] = useState('')
  const [envoyee, setEnvoyee] = useState(false)
  const [envoi, setEnvoi] = useState(false)

  const estClient = utilisateur?.role === 'client'
  const location = type === 'location'
  const jours = debut ? nbJours(debut, fin || debut) : 0

  // Premier clic : début ; second clic : fin (si avant le début, on recommence)
  function choisir(jour) {
    if (!debut || fin || jour < debut) {
      setDebut(jour)
      setFin('')
    } else {
      setFin(jour)
    }
  }

  async function envoyer(e) {
    e.preventDefault()
    setErreur('')
    setEnvoi(true)
    try {
      await api.post(`/vehicules/${vehicule.id}/demandes`, location
        ? { type, date_debut: debut, date_fin: fin || debut, message }
        : { type, message })
      setEnvoyee(true)
    } catch (err) {
      const e2 = erreurApi(err)
      setErreur(Object.values(e2.champs)[0] ?? e2.message)
    } finally {
      setEnvoi(false)
    }
  }

  async function contacter() {
    try {
      const { data } = await api.post(`/vehicules/${vehicule.id}/conversation`)
      naviguer(`/messages/${data.data.id}`)
    } catch (err) {
      setErreur(erreurApi(err).message)
    }
  }

  const calendrier = vehicule.a_louer && (
    <Calendrier
      indisponibilites={vehicule.indisponibilites ?? []}
      debut={estClient && location ? debut : ''}
      fin={estClient && location ? fin : ''}
      onChoisir={estClient && location && !envoyee ? choisir : undefined}
    />
  )

  // Tant que la session n'est pas relue, on n'affiche pas l'invitation à se connecter
  if (chargement) return <article className="carte bloc-calendrier chargement">Chargement…</article>

  if (!estClient) {
    return (
      <article className="carte bloc-calendrier">
        {vehicule.a_louer && <h2>Disponibilités</h2>}
        {calendrier}
        <p className="champ-aide">
          {utilisateur
            ? 'Les demandes de location et d’achat sont réservées aux comptes clients.'
            : <><Link to="/connexion">Connectez-vous</Link> ou <Link to="/inscription/client">créez un compte client</Link> pour envoyer une demande ou contacter le partenaire.</>}
        </p>
      </article>
    )
  }

  if (envoyee) {
    return (
      <article className="carte bloc-calendrier">
        <p className="alerte alerte-succes">
          Demande envoyée à {vehicule.partenaire.nom_commercial}. Vous serez informé de sa réponse dans « Mes demandes ».
        </p>
        <div className="actions">
          <Link to="/demandes" className="bouton">Voir mes demandes</Link>
          <button type="button" className="bouton bouton-discret" onClick={contacter}>Contacter le partenaire</button>
        </div>
      </article>
    )
  }

  return (
    <form className="carte bloc-calendrier formulaire" onSubmit={envoyer}>
      <h2>Votre demande</h2>
      {vehicule.a_louer && vehicule.a_vendre && (
        <div className="onglets">
          <button type="button" className={location ? 'onglet onglet-actif' : 'onglet'} onClick={() => setType('location')}>Louer</button>
          <button type="button" className={!location ? 'onglet onglet-actif' : 'onglet'} onClick={() => setType('achat')}>Acheter</button>
        </div>
      )}

      {location ? (
        <>
          {calendrier}
          <p className="champ-aide">
            {debut
              ? <>Du <strong>{dateLisible(debut)}</strong> au <strong>{dateLisible(fin || debut)}</strong> · {jours} jour{jours > 1 ? 's' : ''}</>
              : 'Cliquez sur le premier jour de location, puis sur le dernier.'}
          </p>
          {debut && (
            <dl className="details">
              <dt>Location</dt><dd>{fcfa(jours * vehicule.prix_jour)}</dd>
              <dt>Caution</dt><dd>{fcfa(vehicule.montant_caution)} <small>(restituée après l’état des lieux de retour)</small></dd>
            </dl>
          )}
        </>
      ) : (
        <dl className="details">
          <dt>Prix de vente</dt><dd><strong>{fcfa(vehicule.prix_vente)}</strong></dd>
        </dl>
      )}

      <textarea rows={3} maxLength={1000} placeholder="Message au partenaire (facultatif)" value={message} onChange={(e) => setMessage(e.target.value)} />
      {erreur && <p className="champ-erreur">{erreur}</p>}
      <p className="champ-aide">Le paiement par Wave ou Orange Money se fera sur la plateforme une fois la demande acceptée.</p>
      <div className="actions">
        <button className="bouton" disabled={envoi || (location && !debut)}>
          {location ? 'Envoyer la demande de location' : "Envoyer la demande d'achat"}
        </button>
        <button type="button" className="bouton bouton-discret" onClick={contacter}>Contacter le partenaire</button>
      </div>
    </form>
  )
}
