import { useState } from 'react'
import { api, erreurApi } from '../api'
import { useAuth } from '../auth/AuthContext'
import Champ from '../components/Champ'

export default function Profil() {
  const { utilisateur, setUtilisateur } = useAuth()
  const [form, setForm] = useState({ nom: utilisateur.nom, telephone: utilisateur.telephone ?? '', password: '', password_confirmation: '' })
  const [erreur, setErreur] = useState({ message: '', champs: {} })
  const [succes, setSucces] = useState('')
  const changer = (e) => setForm({ ...form, [e.target.name]: e.target.value })

  async function envoyer(e) {
    e.preventDefault()
    setSucces('')
    setErreur({ message: '', champs: {} })
    const donnees = { nom: form.nom, telephone: form.telephone }
    if (form.password) Object.assign(donnees, { password: form.password, password_confirmation: form.password_confirmation })
    try {
      const { data } = await api.put('/moi', donnees)
      setUtilisateur(data.data)
      setForm({ ...form, password: '', password_confirmation: '' })
      setSucces('Profil enregistré.')
    } catch (err) {
      setErreur(erreurApi(err))
    }
  }

  return (
    <section className="formulaire-page">
      <h1>Mon profil</h1>
      <form className="carte formulaire" onSubmit={envoyer} noValidate>
        {succes && <p className="alerte alerte-succes">{succes}</p>}
        {erreur.message && !Object.keys(erreur.champs).length && <p className="alerte alerte-erreur">{erreur.message}</p>}
        <Champ label="E-mail" nom="email" value={utilisateur.email} disabled aide="L'e-mail ne peut pas être modifié." />
        <Champ label="Nom complet" nom="nom" value={form.nom} onChange={changer} erreur={erreur.champs.nom} />
        <Champ label="Téléphone" nom="telephone" type="tel" value={form.telephone} onChange={changer} erreur={erreur.champs.telephone} />
        <div className="grille-2">
          <Champ label="Nouveau mot de passe" nom="password" type="password" value={form.password} onChange={changer} erreur={erreur.champs.password} autoComplete="new-password" aide="Laisser vide pour ne pas le changer" />
          <Champ label="Confirmer" nom="password_confirmation" type="password" value={form.password_confirmation} onChange={changer} autoComplete="new-password" />
        </div>
        <button className="bouton">Enregistrer</button>
      </form>
    </section>
  )
}
