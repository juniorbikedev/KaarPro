import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, erreurApi } from '../api'
import { useAuth } from '../auth/AuthContext'
import AvisVehicule from '../components/AvisVehicule'
import { SansPhoto } from '../components/CarteVehicule'
import DemanderInspection from '../components/DemanderInspection'
import DemandeVehicule from '../components/DemandeVehicule'
import Signaler from '../components/Signaler'
import { BOITES, CARBURANTS, CATEGORIES, ETATS, fcfa } from '../libelles'

/** Cas « Consulter la fiche d'un véhicule ». */
export default function FicheVehicule() {
  const { id } = useParams()
  const { utilisateur } = useAuth()
  const [vehicule, setVehicule] = useState(null)
  const [photo, setPhoto] = useState(0)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    api.get(`/vehicules/${id}`).then(({ data }) => setVehicule(data.data)).catch((err) => setErreur(erreurApi(err).message))
  }, [id])

  if (erreur) return <p className="alerte alerte-erreur">{erreur} <Link to="/vehicules">Retour au catalogue</Link></p>
  if (!vehicule) return <p className="chargement">Chargement…</p>

  const photos = vehicule.photos ?? []
  // Tout utilisateur connecté peut signaler une annonce, sauf la sienne
  const peutSignaler = utilisateur && utilisateur.role !== 'admin' && utilisateur.partenaire?.id !== vehicule.partenaire.id

  return (
    <section>
      <p><Link to="/vehicules">← Retour au catalogue</Link></p>
      <div className="fiche">
        <div>
          <div className="galerie-principale">{photos.length ? <img src={photos[photo].url} alt={`${vehicule.marque} ${vehicule.modele}`} /> : <SansPhoto />}</div>
          {photos.length > 1 && (
            <div className="galerie-miniatures">
              {photos.map((p, i) => (
                <button key={p.id} type="button" className={i === photo ? 'miniature active' : 'miniature'} onClick={() => setPhoto(i)} aria-label={`Photo ${i + 1}`}>
                  <img src={p.url} alt="" />
                </button>
              ))}
            </div>
          )}
          <AvisVehicule vehicule={vehicule} />
        </div>

        <div className="fiche-colonne">
          <div className="carte">
            <div className="pastilles">
              {vehicule.a_louer && <span className="pastille pastille-location">À louer</span>}
              {vehicule.a_vendre && <span className="pastille pastille-vente">À vendre</span>}
            {vehicule.verifie_le && <span className="pastille pastille-verifie" title="Le véhicule correspond à ses documents">✓ Vérifié par un agent</span>}
            </div>
            <h1>{vehicule.marque} {vehicule.modele}</h1>
            <p className="champ-aide">Proposé par {vehicule.partenaire.nom_commercial} · {vehicule.ville}</p>
            <div className="prix prix-grand">
              {vehicule.a_louer && <span><strong>{fcfa(vehicule.prix_jour)}</strong> / jour <small>(caution {fcfa(vehicule.montant_caution)})</small></span>}
              {vehicule.a_vendre && <span>Prix de vente : <strong>{fcfa(vehicule.prix_vente)}</strong></span>}
            </div>
            <dl className="details">
              <dt>Année</dt><dd>{vehicule.annee}</dd>
              <dt>Catégorie</dt><dd>{CATEGORIES[vehicule.categorie]}</dd>
              <dt>Carburant</dt><dd>{CARBURANTS[vehicule.carburant]}</dd>
              <dt>Boîte</dt><dd>{BOITES[vehicule.boite]}</dd>
              <dt>Places</dt><dd>{vehicule.places}</dd>
              <dt>Kilométrage</dt><dd>{new Intl.NumberFormat('fr-FR').format(vehicule.kilometrage)} km</dd>
              <dt>État</dt><dd>{ETATS[vehicule.etat]}</dd>
            </dl>
            {vehicule.description && <p>{vehicule.description}</p>}
            {peutSignaler && <Signaler cible={{ vehicule_id: vehicule.id }} libelle="Signaler cette annonce" />}
            {utilisateur?.role === 'admin' && <DemanderInspection vehicule={vehicule} />}
          </div>
          <DemandeVehicule vehicule={vehicule} />
        </div>
      </div>
    </section>
  )
}
