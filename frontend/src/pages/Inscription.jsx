import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, erreurApi } from '../api'
import { LIBELLES_ACTIVITE, useAuth } from '../auth/AuthContext'
import Champ from '../components/Champ'

/** Choix du type de compte. */
export function ChoixInscription() {
  return (
    <section className="formulaire-page">
      <h1>Créer un compte</h1>
      <div className="grille-2">
        <Link to="/inscription/client" className="carte carte-choix">
          <h2>Je suis client</h2>
          <p>Je veux louer ou acheter un véhicule.</p>
        </Link>
        <Link to="/inscription/partenaire" className="carte carte-choix">
          <h2>Je suis vendeur ou loueur</h2>
          <p>Je veux proposer mes véhicules à la vente, à la location, ou les deux.</p>
        </Link>
      </div>
    </section>
  )
}

const VIDE = { nom: '', email: '', telephone: '', password: '', password_confirmation: '' }

function ChampsCompte({ form, changer, champs }) {
  return (
    <>
      <Champ label="Nom complet" nom="nom" value={form.nom} onChange={changer} erreur={champs.nom} autoComplete="name" required />
      <div className="grille-2">
        <Champ label="E-mail" nom="email" type="email" value={form.email} onChange={changer} erreur={champs.email} autoComplete="email" required />
        <Champ label="Téléphone" nom="telephone" type="tel" value={form.telephone} onChange={changer} erreur={champs.telephone} autoComplete="tel" placeholder="77 123 45 67" />
      </div>
      <div className="grille-2">
        <Champ label="Mot de passe" nom="password" type="password" value={form.password} onChange={changer} erreur={champs.password} autoComplete="new-password" aide="8 caractères minimum" required />
        <Champ label="Confirmer le mot de passe" nom="password_confirmation" type="password" value={form.password_confirmation} onChange={changer} autoComplete="new-password" required />
      </div>
    </>
  )
}

export function InscriptionClient() {
  const { ouvrirSession } = useAuth()
  const naviguer = useNavigate()
  const [form, setForm] = useState(VIDE)
  const [erreur, setErreur] = useState({ message: '', champs: {} })
  const [envoi, setEnvoi] = useState(false)

  async function envoyer(e) {
    e.preventDefault()
    setEnvoi(true)
    try {
      const { data } = await api.post('/auth/inscription/client', form)
      ouvrirSession(data)
      naviguer('/espace')
    } catch (err) {
      setErreur(erreurApi(err))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <section className="formulaire-page">
      <h1>Créer un compte client</h1>
      <form className="carte formulaire" onSubmit={envoyer} noValidate>
        {erreur.message && !Object.keys(erreur.champs).length && <p className="alerte alerte-erreur">{erreur.message}</p>}
        <ChampsCompte form={form} changer={(e) => setForm({ ...form, [e.target.name]: e.target.value })} champs={erreur.champs} />
        <button className="bouton" disabled={envoi}>{envoi ? 'Création…' : 'Créer mon compte'}</button>
      </form>
    </section>
  )
}

export function InscriptionPartenaire() {
  const { ouvrirSession } = useAuth()
  const naviguer = useNavigate()
  const [form, setForm] = useState({ ...VIDE, nom_commercial: '', type_activite: 'vente_et_location' })
  const [piece, setPiece] = useState(null)
  const [erreur, setErreur] = useState({ message: '', champs: {} })
  const [envoi, setEnvoi] = useState(false)
  const changer = (e) => setForm({ ...form, [e.target.name]: e.target.value })

  async function envoyer(e) {
    e.preventDefault()
    setEnvoi(true)
    const donnees = new FormData()
    Object.entries(form).forEach(([cle, valeur]) => donnees.append(cle, valeur))
    if (piece) donnees.append('piece_identite', piece)
    try {
      const { data } = await api.post('/auth/inscription/partenaire', donnees)
      ouvrirSession(data)
      naviguer('/espace')
    } catch (err) {
      setErreur(erreurApi(err))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <section className="formulaire-page">
      <h1>Devenir partenaire</h1>
      <p className="intro">Votre compte sera vérifié par un administrateur avant que vous puissiez publier des véhicules.</p>
      <form className="carte formulaire" onSubmit={envoyer} noValidate>
        {erreur.message && !Object.keys(erreur.champs).length && <p className="alerte alerte-erreur">{erreur.message}</p>}
        <ChampsCompte form={form} changer={changer} champs={erreur.champs} />
        <Champ label="Nom commercial" nom="nom_commercial" value={form.nom_commercial} onChange={changer} erreur={erreur.champs.nom_commercial} placeholder="Ex. : Teranga Location" required />

        <fieldset className="choix-activite">
          <legend className="champ-libelle">Type d'activité</legend>
          {Object.entries(LIBELLES_ACTIVITE).map(([valeur, libelle]) => (
            <label key={valeur} className={`option ${form.type_activite === valeur ? 'option-active' : ''}`}>
              <input type="radio" name="type_activite" value={valeur} checked={form.type_activite === valeur} onChange={changer} />
              {libelle}
            </label>
          ))}
          {erreur.champs.type_activite && <span className="champ-erreur">{erreur.champs.type_activite}</span>}
        </fieldset>

        <label className="champ">
          <span className="champ-libelle">Pièce d'identité</span>
          <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => setPiece(e.target.files?.[0] ?? null)} aria-invalid={Boolean(erreur.champs.piece_identite)} />
          <span className={erreur.champs.piece_identite ? 'champ-erreur' : 'champ-aide'}>
            {erreur.champs.piece_identite ?? 'PDF, JPG ou PNG, 5 Mo maximum'}
          </span>
        </label>

        <button className="bouton" disabled={envoi}>{envoi ? 'Envoi…' : 'Créer mon compte partenaire'}</button>
      </form>
    </section>
  )
}
