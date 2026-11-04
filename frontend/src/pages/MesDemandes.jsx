import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, erreurApi } from '../api'
import CarteDemande from '../components/CarteDemande'
import Etoiles from '../components/Etoiles'
import { fcfa, OPERATEURS } from '../libelles'

/** Choix de l'opérateur et du numéro, puis redirection vers la page de paiement de l'opérateur. */
function FormPaiement({ demande, onFermer }) {
  const naviguer = useNavigate()
  const [operateur, setOperateur] = useState('wave')
  const [telephone, setTelephone] = useState('')
  const [erreur, setErreur] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const caution = demande.type === 'location' ? demande.montant_caution ?? 0 : 0

  async function payer(e) {
    e.preventDefault()
    setErreur('')
    setEnvoi(true)
    try {
      const { data } = await api.post(`/demandes/${demande.id}/paiements`, { operateur, telephone })
      // Avec une vraie passerelle, l'adresse est celle de l'opérateur ; avec le simulateur, une page de l'application
      const pageSimulateur = `/paiement/${data.data.reference}`
      if (new URL(data.url_paiement).pathname === pageSimulateur) naviguer(pageSimulateur)
      else window.location.assign(data.url_paiement)
    } catch (err) {
      const e2 = erreurApi(err)
      setErreur(Object.values(e2.champs)[0] ?? e2.message)
      setEnvoi(false)
    }
  }

  return (
    <form className="form-paiement" onSubmit={payer}>
      <div className="choix-operateur" role="radiogroup" aria-label="Opérateur">
        {Object.entries(OPERATEURS).map(([valeur, libelle]) => (
          <label key={valeur} className={operateur === valeur ? 'option option-active' : 'option'}>
            <input type="radio" name={`operateur-${demande.id}`} value={valeur} checked={operateur === valeur} onChange={() => setOperateur(valeur)} />
            {libelle}
          </label>
        ))}
      </div>
      <input className="select" inputMode="tel" placeholder="Numéro du compte (ex. : 77 123 45 67)" value={telephone} onChange={(e) => setTelephone(e.target.value)} />
      <dl className="details">
        <dt>{demande.type === 'location' ? 'Location' : 'Prix'}</dt><dd>{fcfa(demande.montant)}</dd>
        {caution > 0 && <><dt>Caution</dt><dd>{fcfa(caution)}</dd></>}
        <dt>Total</dt><dd><strong>{fcfa(demande.montant + caution)}</strong></dd>
      </dl>
      {erreur && <p className="champ-erreur">{erreur}</p>}
      <div className="actions">
        <button className="bouton" disabled={envoi || !telephone}>Payer {fcfa(demande.montant + caution)}</button>
        <button type="button" className="lien" onClick={onFermer}>Annuler</button>
      </div>
    </form>
  )
}

/** Avis du client une fois la transaction terminée (véhicule rendu ou remis). */
function FormAvis({ demande, onEnvoye }) {
  const [note, setNote] = useState(0)
  const [commentaire, setCommentaire] = useState('')
  const [erreur, setErreur] = useState('')

  async function envoyer(e) {
    e.preventDefault()
    setErreur('')
    try {
      await api.post(`/demandes/${demande.id}/avis`, { note, commentaire })
      onEnvoye({ note, commentaire })
    } catch (err) {
      const e2 = erreurApi(err)
      setErreur(Object.values(e2.champs)[0] ?? e2.message)
    }
  }

  return (
    <form className="form-avis" onSubmit={envoyer}>
      <span className="champ-libelle">Votre avis</span>
      <Etoiles note={note} onChange={setNote} />
      <textarea rows={2} maxLength={1000} placeholder="Commentaire (facultatif)" value={commentaire} onChange={(e) => setCommentaire(e.target.value)} />
      {erreur && <p className="champ-erreur">{erreur}</p>}
      <button className="bouton bouton-petit" disabled={!note}>Publier l’avis</button>
    </form>
  )
}

/** Cas « Suivre ses demandes » (client) : annuler, signer le contrat, payer, donner son avis. */
export default function MesDemandes() {
  const [demandes, setDemandes] = useState(null)
  const [erreur, setErreur] = useState('')
  const [aPayer, setAPayer] = useState(null)

  useEffect(() => {
    api.get('/demandes').then(({ data }) => setDemandes(data.data)).catch((err) => setErreur(erreurApi(err).message))
  }, [])

  async function annuler(demande) {
    if (!window.confirm('Annuler cette demande ?')) return
    try {
      const { data } = await api.post(`/demandes/${demande.id}/annuler`)
      setDemandes((liste) => liste.map((d) => (d.id === demande.id ? data.data : d)))
    } catch (err) {
      setErreur(erreurApi(err).message)
    }
  }

  if (!demandes) return erreur ? <p className="alerte alerte-erreur">{erreur}</p> : <p className="chargement">Chargement…</p>

  return (
    <section>
      <div className="titre-actions">
        <h1>Mes demandes</h1>
        <Link to="/vehicules" className="bouton bouton-discret">Parcourir le catalogue</Link>
      </div>
      {erreur && <p className="alerte alerte-erreur">{erreur}</p>}
      {demandes.length === 0 && <p className="carte">Vous n’avez encore envoyé aucune demande.</p>}
      <div className="liste-vehicules">
        {demandes.map((d) => {
          const signe = d.contrat?.signe_client_le
          return (
            <CarteDemande key={d.id} demande={d}>
              {d.statut === 'en_attente' && <button type="button" className="bouton bouton-discret" onClick={() => annuler(d)}>Annuler</button>}
              {d.statut === 'acceptee' && !signe && <Link to={`/contrats/${d.contrat.id}`} className="bouton">Signer le contrat</Link>}
              {d.statut === 'acceptee' && signe && aPayer !== d.id && <button type="button" className="bouton" onClick={() => setAPayer(d.id)}>Payer</button>}
              {aPayer === d.id && <FormPaiement demande={d} onFermer={() => setAPayer(null)} />}
              {d.peut_donner_avis && <FormAvis demande={d} onEnvoye={(avis) => setDemandes((liste) => liste.map((x) => (x.id === d.id ? { ...x, avis, peut_donner_avis: false } : x)))} />}
              {d.avis && <p className="avis-donne"><Etoiles note={d.avis.note} /> <span className="champ-aide">Votre avis</span></p>}
            </CarteDemande>
          )
        })}
      </div>
    </section>
  )
}
