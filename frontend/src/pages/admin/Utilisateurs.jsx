import { useCallback, useEffect, useState } from 'react'
import { api, erreurApi } from '../../api'
import { LIBELLES_ROLE, useAuth } from '../../auth/AuthContext'
import Champ from '../../components/Champ'

const VIDE = { nom: '', email: '', telephone: '', zone: '', password: '', password_confirmation: '' }

function CreerAgent({ onCree }) {
  const [form, setForm] = useState(VIDE)
  const [erreur, setErreur] = useState({ message: '', champs: {} })
  const changer = (e) => setForm({ ...form, [e.target.name]: e.target.value })

  async function envoyer(e) {
    e.preventDefault()
    try {
      await api.post('/admin/agents', form)
      setForm(VIDE)
      setErreur({ message: '', champs: {} })
      onCree()
    } catch (err) {
      setErreur(erreurApi(err))
    }
  }

  return (
    <form className="carte formulaire" onSubmit={envoyer} noValidate>
      <h2>Créer un compte agent</h2>
      <Champ label="Nom complet" nom="nom" value={form.nom} onChange={changer} erreur={erreur.champs.nom} />
      <Champ label="E-mail" nom="email" type="email" value={form.email} onChange={changer} erreur={erreur.champs.email} />
      <div className="grille-2">
        <Champ label="Téléphone" nom="telephone" value={form.telephone} onChange={changer} erreur={erreur.champs.telephone} />
        <Champ label="Zone d'intervention" nom="zone" value={form.zone} onChange={changer} erreur={erreur.champs.zone} placeholder="Ex. : Pikine" />
      </div>
      <div className="grille-2">
        <Champ label="Mot de passe" nom="password" type="password" value={form.password} onChange={changer} erreur={erreur.champs.password} autoComplete="new-password" />
        <Champ label="Confirmer" nom="password_confirmation" type="password" value={form.password_confirmation} onChange={changer} autoComplete="new-password" />
      </div>
      <button className="bouton">Créer l'agent</button>
    </form>
  )
}

export default function Utilisateurs() {
  const { utilisateur: moi } = useAuth()
  const [role, setRole] = useState('')
  const [liste, setListe] = useState([])
  const [erreur, setErreur] = useState('')

  const charger = useCallback(() => {
    api.get('/admin/utilisateurs', { params: role ? { role } : {} }).then(({ data }) => setListe(data.data))
  }, [role])

  useEffect(() => { charger() }, [charger])

  async function basculer(u) {
    setErreur('')
    try {
      await api.patch(`/admin/utilisateurs/${u.id}/activation`, { est_actif: !u.est_actif })
      charger()
    } catch (err) {
      setErreur(erreurApi(err).message)
    }
  }

  return (
    <section>
      <h1>Gérer les utilisateurs</h1>
      <div className="maitre-detail">
        <div className="carte">
          <select className="select" value={role} onChange={(e) => setRole(e.target.value)}>
            <option value="">Tous les rôles</option>
            {Object.entries(LIBELLES_ROLE).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </select>
          {erreur && <p className="champ-erreur">{erreur}</p>}
          <table className="tableau">
            <thead><tr><th>Nom</th><th>Rôle</th><th>Statut</th><th /></tr></thead>
            <tbody>
              {liste.map((u) => (
                <tr key={u.id}>
                  <td><strong>{u.nom}</strong><br /><span className="champ-aide">{u.email}</span></td>
                  <td>{LIBELLES_ROLE[u.role]}{u.zone ? ` (${u.zone})` : ''}</td>
                  <td><span className={`statut ${u.est_actif ? 'statut-valide' : 'statut-refuse'}`}>{u.est_actif ? 'Actif' : 'Désactivé'}</span></td>
                  <td>
                    {u.id !== moi.id && (
                      <button type="button" className="bouton bouton-petit bouton-discret" onClick={() => basculer(u)}>
                        {u.est_actif ? 'Désactiver' : 'Réactiver'}
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <CreerAgent onCree={charger} />
      </div>
    </section>
  )
}
