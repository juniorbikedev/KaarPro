import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api, erreurApi } from '../api'
import { LIBELLES_ACTIVITE, useAuth } from '../auth/AuthContext'

const STATUTS = {
  en_attente: { libelle: 'En attente de validation', classe: 'statut-attente', texte: "Un administrateur vérifie votre pièce d'identité. Vous pourrez publier vos véhicules dès la validation." },
  valide: { libelle: 'Compte validé', classe: 'statut-valide', texte: 'Votre compte est validé : vous pouvez publier vos véhicules.' },
  refuse: { libelle: 'Compte refusé', classe: 'statut-refuse', texte: "Votre inscription a été refusée. Contactez TerangaAuto pour en savoir plus." },
}

function EspacePartenaire({ partenaire }) {
  const { setUtilisateur } = useAuth()
  const [activite, setActivite] = useState(partenaire.type_activite)
  const [message, setMessage] = useState('')
  const statut = STATUTS[partenaire.statut_validation]

  async function changerActivite(e) {
    e.preventDefault()
    try {
      const { data } = await api.put('/moi/type-activite', { type_activite: activite })
      setUtilisateur(data.data)
      setMessage("Type d'activité mis à jour.")
    } catch (err) {
      setMessage(erreurApi(err).message)
    }
  }

  return (
    <div className="grille-2">
      <article className="carte">
        <h2>{partenaire.nom_commercial}</h2>
        <p><span className={`statut ${statut.classe}`}>{statut.libelle}</span></p>
        <p>{statut.texte}</p>
        {partenaire.statut_validation === 'valide' && (
          <div className="actions">
            <Link to="/partenaire/vehicules" className="bouton">Gérer mes véhicules</Link>
            <Link to="/partenaire/demandes" className="bouton bouton-secondaire">Demandes reçues</Link>
          </div>
        )}
        {partenaire.motif_refus && <p className="alerte alerte-erreur">Motif : {partenaire.motif_refus}</p>}
      </article>
      <form className="carte" onSubmit={changerActivite}>
        <h2>Mon activité</h2>
        <p className="intro">
          Vous {partenaire.peut_vendre && partenaire.peut_louer ? 'vendez et louez' : partenaire.peut_vendre ? 'vendez' : 'louez'} vos véhicules.
        </p>
        <select value={activite} onChange={(e) => setActivite(e.target.value)} className="select">
          {Object.entries(LIBELLES_ACTIVITE).map(([valeur, libelle]) => <option key={valeur} value={valeur}>{libelle}</option>)}
        </select>
        <button className="bouton" disabled={activite === partenaire.type_activite}>Modifier</button>
        {message && <p className="champ-aide">{message}</p>}
      </form>
    </div>
  )
}

export default function Espace() {
  const { utilisateur } = useAuth()

  return (
    <section>
      <h1>Bonjour, {utilisateur.nom}</h1>

      {utilisateur.role === 'vendeur' && utilisateur.partenaire && <EspacePartenaire partenaire={utilisateur.partenaire} />}

      {utilisateur.role === 'client' && (
        <article className="carte">
          <h2>Votre espace client</h2>
          <p>Parcourez le catalogue, envoyez une demande de location ou d'achat et échangez avec les partenaires.</p>
          <div className="actions">
            <Link to="/vehicules" className="bouton">Voir le catalogue</Link>
            <Link to="/demandes" className="bouton bouton-secondaire">Mes demandes</Link>
          </div>
        </article>
      )}

      {utilisateur.role === 'agent' && (
        <article className="carte">
          <h2>Votre espace agent{utilisateur.zone ? ` — ${utilisateur.zone}` : ''}</h2>
          <p>Vos missions d'état des lieux et d'inspection apparaîtront ici au sprint 6.</p>
        </article>
      )}

      {utilisateur.role === 'admin' && (
        <div className="grille-2">
          <Link to="/admin/partenaires" className="carte carte-choix">
            <h2>Valider les partenaires</h2>
            <p>Consulter les inscriptions en attente et leurs documents.</p>
          </Link>
          <Link to="/admin/utilisateurs" className="carte carte-choix">
            <h2>Gérer les utilisateurs</h2>
            <p>Créer un compte agent, désactiver ou réactiver un compte.</p>
          </Link>
        </div>
      )}
    </section>
  )
}
