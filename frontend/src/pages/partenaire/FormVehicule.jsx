import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, erreurApi } from '../../api'
import { useAuth } from '../../auth/AuthContext'
import Champ from '../../components/Champ'
import { BOITES, CARBURANTS, CATEGORIES, ETATS, MODES_OFFRE, modesPourActivite } from '../../libelles'

const VIDE = {
  immatriculation: '', marque: '', modele: '', annee: '', categorie: 'berline', carburant: 'essence', boite: 'manuelle',
  places: '5', kilometrage: '', ville: 'Dakar', description: '', mode_offre: '', prix_jour: '', montant_caution: '', prix_vente: '', etat: 'disponible',
}

function Liste({ label, nom, valeur, options, onChange, erreur }) {
  return (
    <label className="champ">
      <span className="champ-libelle">{label}</span>
      <select className="select" name={nom} value={valeur} onChange={onChange}>
        {Object.entries(options).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
      </select>
      {erreur && <span className="champ-erreur">{erreur}</span>}
    </label>
  )
}

/** « Publier un véhicule » (sans identifiant) ou « Modifier un véhicule ». */
export default function FormVehicule() {
  const { id } = useParams()
  const edition = Boolean(id)
  const naviguer = useNavigate()
  const { utilisateur } = useAuth()
  const modes = modesPourActivite(utilisateur.partenaire.type_activite)

  const [form, setForm] = useState({ ...VIDE, mode_offre: modes[0] })
  const [photosExistantes, setPhotosExistantes] = useState([])
  const [photos, setPhotos] = useState([])
  const [carteGrise, setCarteGrise] = useState(null)
  const [assurance, setAssurance] = useState(null)
  const [erreur, setErreur] = useState({ message: '', champs: {} })
  const [envoi, setEnvoi] = useState(false)

  useEffect(() => {
    if (!edition) return
    api.get(`/partenaire/vehicules/${id}`).then(({ data }) => {
      const v = data.data
      setForm(Object.fromEntries(Object.keys(VIDE).map((cle) => [cle, v[cle] ?? ''])))
      setPhotosExistantes(v.photos)
    })
  }, [edition, id])

  const changer = (e) => setForm({ ...form, [e.target.name]: e.target.value })
  const louable = form.mode_offre !== 'vente'
  const vendable = form.mode_offre !== 'location'

  async function envoyer(e) {
    e.preventDefault()
    setEnvoi(true)
    setErreur({ message: '', champs: {} })
    try {
      if (edition) {
        await api.put(`/partenaire/vehicules/${id}`, form)
        if (photos.length) {
          const donnees = new FormData()
          photos.forEach((p) => donnees.append('photos[]', p))
          await api.post(`/partenaire/vehicules/${id}/photos`, donnees)
        }
      } else {
        const donnees = new FormData()
        Object.entries(form).forEach(([cle, valeur]) => { if (cle !== 'etat' && valeur !== '') donnees.append(cle, valeur) })
        photos.forEach((p) => donnees.append('photos[]', p))
        if (carteGrise) donnees.append('carte_grise', carteGrise)
        if (assurance) donnees.append('assurance', assurance)
        await api.post('/partenaire/vehicules', donnees)
      }
      naviguer('/partenaire/vehicules')
    } catch (err) {
      setErreur(erreurApi(err))
    } finally {
      setEnvoi(false)
    }
  }

  async function supprimerPhoto(photo) {
    try {
      const { data } = await api.delete(`/partenaire/vehicules/${id}/photos/${photo.id}`)
      setPhotosExistantes(data.data.photos)
    } catch (err) {
      setErreur(erreurApi(err))
    }
  }

  const champs = erreur.champs
  const erreurPhotos = Object.entries(champs).find(([cle]) => cle.startsWith('photos'))?.[1]

  return (
    <section className="formulaire-page formulaire-large">
      <p><Link to="/partenaire/vehicules">← Mes véhicules</Link></p>
      <h1>{edition ? 'Modifier le véhicule' : 'Publier un véhicule'}</h1>
      <form className="carte formulaire" onSubmit={envoyer} noValidate>
        {erreur.message && !Object.keys(champs).length && <p className="alerte alerte-erreur">{erreur.message}</p>}
        {Object.keys(champs).length > 0 && <p className="alerte alerte-erreur">Certains champs sont à corriger.</p>}

        <h2>Le véhicule</h2>
        <div className="grille-3">
          <Champ label="Marque" nom="marque" value={form.marque} onChange={changer} erreur={champs.marque} placeholder="Toyota" />
          <Champ label="Modèle" nom="modele" value={form.modele} onChange={changer} erreur={champs.modele} placeholder="Corolla" />
          <Champ label="Année" nom="annee" type="number" value={form.annee} onChange={changer} erreur={champs.annee} placeholder="2021" />
        </div>
        <div className="grille-3">
          <Champ label="Immatriculation" nom="immatriculation" value={form.immatriculation} onChange={changer} erreur={champs.immatriculation} placeholder="DK-1234-AB" />
          <Liste label="Catégorie" nom="categorie" valeur={form.categorie} options={CATEGORIES} onChange={changer} erreur={champs.categorie} />
          <Champ label="Ville" nom="ville" value={form.ville} onChange={changer} erreur={champs.ville} />
        </div>
        <div className="grille-3">
          <Liste label="Carburant" nom="carburant" valeur={form.carburant} options={CARBURANTS} onChange={changer} erreur={champs.carburant} />
          <Liste label="Boîte de vitesses" nom="boite" valeur={form.boite} options={BOITES} onChange={changer} erreur={champs.boite} />
          <Champ label="Places" nom="places" type="number" value={form.places} onChange={changer} erreur={champs.places} />
        </div>
        <div className="grille-2">
          <Champ label="Kilométrage" nom="kilometrage" type="number" value={form.kilometrage} onChange={changer} erreur={champs.kilometrage} />
          {edition && <Liste label="État" nom="etat" valeur={form.etat} options={ETATS} onChange={changer} erreur={champs.etat} />}
        </div>
        <label className="champ">
          <span className="champ-libelle">Description</span>
          <textarea name="description" rows={3} value={form.description} onChange={changer} placeholder="Entretien, équipements, conditions…" />
        </label>

        <h2>L'offre</h2>
        {modes.length > 1 ? (
          <Liste label="Mode d'offre" nom="mode_offre" valeur={form.mode_offre} options={Object.fromEntries(modes.map((m) => [m, MODES_OFFRE[m]]))} onChange={changer} erreur={champs.mode_offre} />
        ) : (
          <p className="champ-aide">Votre activité : {MODES_OFFRE[modes[0]].toLowerCase()}. Le mode d'offre est fixé automatiquement.</p>
        )}
        <div className="grille-3">
          {louable && <Champ label="Prix par jour (FCFA)" nom="prix_jour" type="number" value={form.prix_jour} onChange={changer} erreur={champs.prix_jour} />}
          {louable && <Champ label="Caution (FCFA)" nom="montant_caution" type="number" value={form.montant_caution} onChange={changer} erreur={champs.montant_caution} />}
          {vendable && <Champ label="Prix de vente (FCFA)" nom="prix_vente" type="number" value={form.prix_vente} onChange={changer} erreur={champs.prix_vente} />}
        </div>

        <h2>Photos et documents</h2>
        {edition && (
          <div className="photos-existantes">
            {photosExistantes.map((p) => (
              <div key={p.id} className="photo-existante">
                <img src={p.url} alt="" />
                <button type="button" className="bouton bouton-petit bouton-danger" onClick={() => supprimerPhoto(p)} disabled={photosExistantes.length <= 1}>Supprimer</button>
              </div>
            ))}
          </div>
        )}
        <label className="champ">
          <span className="champ-libelle">{edition ? 'Ajouter des photos' : 'Photos du véhicule'}</span>
          <input type="file" accept=".jpg,.jpeg,.png,.webp" multiple onChange={(e) => setPhotos([...e.target.files])} aria-invalid={Boolean(erreurPhotos)} />
          <span className={erreurPhotos ? 'champ-erreur' : 'champ-aide'}>{erreurPhotos ?? 'JPG, PNG ou WebP, 8 photos au maximum'}</span>
        </label>
        {!edition && (
          <div className="grille-2">
            <label className="champ">
              <span className="champ-libelle">Carte grise</span>
              <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => setCarteGrise(e.target.files?.[0] ?? null)} />
              <span className={champs.carte_grise ? 'champ-erreur' : 'champ-aide'}>{champs.carte_grise ?? 'PDF, JPG ou PNG'}</span>
            </label>
            <label className="champ">
              <span className="champ-libelle">Attestation d'assurance</span>
              <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => setAssurance(e.target.files?.[0] ?? null)} />
              <span className={champs.assurance ? 'champ-erreur' : 'champ-aide'}>{champs.assurance ?? 'PDF, JPG ou PNG'}</span>
            </label>
          </div>
        )}

        <button className="bouton" disabled={envoi}>{envoi ? 'Enregistrement…' : edition ? 'Enregistrer les modifications' : 'Publier le véhicule'}</button>
      </form>
    </section>
  )
}
