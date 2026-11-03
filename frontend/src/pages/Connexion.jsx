import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { api, erreurApi } from '../api'
import { useAuth } from '../auth/AuthContext'
import Champ from '../components/Champ'

export default function Connexion() {
  const { ouvrirSession } = useAuth()
  const naviguer = useNavigate()
  const emplacement = useLocation()
  const [form, setForm] = useState({ email: '', password: '' })
  const [erreur, setErreur] = useState({ message: '', champs: {} })
  const [envoi, setEnvoi] = useState(false)

  async function envoyer(e) {
    e.preventDefault()
    setEnvoi(true)
    setErreur({ message: '', champs: {} })
    try {
      const { data } = await api.post('/auth/connexion', form)
      ouvrirSession(data)
      naviguer(emplacement.state?.depuis ?? '/espace', { replace: true })
    } catch (err) {
      setErreur(erreurApi(err))
    } finally {
      setEnvoi(false)
    }
  }

  const changer = (e) => setForm({ ...form, [e.target.name]: e.target.value })

  return (
    <section className="formulaire-page">
      <h1>Se connecter</h1>
      <form className="carte formulaire" onSubmit={envoyer} noValidate>
        {erreur.message && !Object.keys(erreur.champs).length && <p className="alerte alerte-erreur">{erreur.message}</p>}
        <Champ label="E-mail" nom="email" type="email" autoComplete="email" value={form.email} onChange={changer} erreur={erreur.champs.email} required />
        <Champ label="Mot de passe" nom="password" type="password" autoComplete="current-password" value={form.password} onChange={changer} erreur={erreur.champs.password} required />
        <button className="bouton" disabled={envoi}>{envoi ? 'Connexion…' : 'Se connecter'}</button>
        <p className="lien-bas">Pas encore de compte ? <Link to="/inscription">Créer un compte</Link></p>
      </form>
    </section>
  )
}
