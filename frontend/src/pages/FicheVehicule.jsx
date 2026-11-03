import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, erreurApi } from '../api'
import Calendrier from '../components/Calendrier'
import { SansPhoto } from '../components/CarteVehicule'
import { BOITES, CARBURANTS, CATEGORIES, ETATS, fcfa } from '../libelles'

/** Cas « Consulter la fiche d'un véhicule ». */
export default function FicheVehicule() {
  const { id } = useParams()
  const [vehicule, setVehicule] = useState(null)
  const [photo, setPhoto] = useState(0)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    api.get(`/vehicules/${id}`).then(({ data }) => setVehicule(data.data)).catch((err) => setErreur(erreurApi(err).message))
  }, [id])

  if (erreur) return <p className="alerte alerte-erreur">{erreur} <Link to="/vehicules">Retour au catalogue</Link></p>
  if (!vehicule) return <p className="chargement">Chargement…</p>

  const photos = vehicule.photos ?? []

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
        </div>

        <div className="carte">
          <div className="pastilles">
            {vehicule.a_louer && <span className="pastille pastille-location">À louer</span>}
            {vehicule.a_vendre && <span className="pastille pastille-vente">À vendre</span>}
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
          <div className="actions">
            {vehicule.a_louer && <button type="button" className="bouton" disabled title="Disponible au sprint 4">Envoyer une demande de location</button>}
            {vehicule.a_vendre && <button type="button" className="bouton bouton-secondaire" disabled title="Disponible au sprint 4">Envoyer une demande d'achat</button>}
          </div>
          <p className="champ-aide">Les demandes de location et d'achat arrivent au sprint 4.</p>
        </div>
      </div>

      {vehicule.a_louer && (
        <article className="carte bloc-calendrier">
          <h2>Disponibilités</h2>
          <Calendrier indisponibilites={vehicule.indisponibilites ?? []} />
        </article>
      )}
    </section>
  )
}
