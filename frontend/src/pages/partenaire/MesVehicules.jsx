import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, erreurApi } from '../../api'
import { SansPhoto } from '../../components/CarteVehicule'
import { ETATS, MODES_OFFRE, fcfa } from '../../libelles'
import { useEspace } from '../../espace'

/** Cas « Gérer ses véhicules ». */
export default function MesVehicules() {
  const { base, parc, libelleVehicules } = useEspace()
  const [vehicules, setVehicules] = useState(null)
  const [erreur, setErreur] = useState('')

  const charger = useCallback(() => {
    api.get(`${base}/vehicules`).then(({ data }) => setVehicules(data.data)).catch((err) => setErreur(erreurApi(err).message))
  }, [base])

  useEffect(() => { charger() }, [charger])

  async function basculer(v) {
    setErreur('')
    try {
      await api.post(`${base}/vehicules/${v.id}/${v.statut_publication === 'publie' ? 'retirer' : 'republier'}`)
      charger()
    } catch (err) {
      setErreur(erreurApi(err).message)
    }
  }

  return (
    <section>
      <div className="titre-actions">
        <h1>{libelleVehicules}</h1>
        <Link to={`${base}/vehicules/nouveau`} className="bouton">Publier un véhicule</Link>
      </div>
      {parc && (
        <p className="champ-aide">
          Véhicules de l'entreprise, publiés sous la marque TerangaAuto et sans commission.{' '}
          <Link to={`${base}/demandes`}>Demandes du parc</Link> · <Link to={`${base}/paiements`}>Encaissements du parc</Link>
        </p>
      )}
      {erreur && <p className="alerte alerte-erreur">{erreur}</p>}
      {vehicules === null && !erreur && <p className="chargement">Chargement…</p>}
      {vehicules?.length === 0 && <article className="carte">{parc ? 'Aucun véhicule dans le parc pour le moment.' : "Vous n'avez encore publié aucun véhicule."}</article>}
      <div className="liste-vehicules">
        {vehicules?.map((v) => (
          <article key={v.id} className={`carte ligne-vehicule ${v.statut_publication === 'retire' ? 'retire' : ''}`}>
            <div className="vignette vignette-petite">{v.photos?.[0] ? <img src={v.photos[0].url} alt="" /> : <SansPhoto />}</div>
            <div className="ligne-vehicule-infos">
              <h2>{v.marque} {v.modele} <span className="champ-aide">· {v.immatriculation}</span></h2>
              <p className="champ-aide">
                {MODES_OFFRE[v.mode_offre]} · {ETATS[v.etat]}
                {v.a_louer && ` · ${fcfa(v.prix_jour)} / jour`}
                {v.a_vendre && ` · ${fcfa(v.prix_vente)}`}
              </p>
              <span className={`statut ${v.statut_publication === 'publie' ? 'statut-valide' : 'statut-refuse'}`}>
                {v.statut_publication === 'publie' ? 'Publié' : 'Retiré du catalogue'}
              </span>
            </div>
            <div className="ligne-vehicule-actions">
              <Link to={`${base}/vehicules/${v.id}/modifier`} className="bouton bouton-petit bouton-discret">Modifier</Link>
              {v.a_louer && <Link to={`${base}/vehicules/${v.id}/calendrier`} className="bouton bouton-petit bouton-discret">Calendrier</Link>}
              <button type="button" className="bouton bouton-petit bouton-discret" onClick={() => basculer(v)}>
                {v.statut_publication === 'publie' ? 'Retirer' : 'Republier'}
              </button>
            </div>
          </article>
        ))}
      </div>
    </section>
  )
}
