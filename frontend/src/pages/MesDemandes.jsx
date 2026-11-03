import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, erreurApi } from '../api'
import CarteDemande from '../components/CarteDemande'

/** Cas « Suivre ses demandes » (client). */
export default function MesDemandes() {
  const [demandes, setDemandes] = useState(null)
  const [erreur, setErreur] = useState('')

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
        {demandes.map((d) => (
          <CarteDemande key={d.id} demande={d}>
            {d.statut === 'en_attente' && <button type="button" className="bouton bouton-discret" onClick={() => annuler(d)}>Annuler</button>}
            {d.contrat && !d.contrat.signe_client_le && <Link to={`/contrats/${d.contrat.id}`} className="bouton">Signer le contrat</Link>}
          </CarteDemande>
        ))}
      </div>
    </section>
  )
}
