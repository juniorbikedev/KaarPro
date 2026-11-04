import { useState } from 'react'
import { api, erreurApi } from '../api'
import { MOTIFS_SIGNALEMENT } from '../libelles'

/** Lien « Signaler » qui ouvre un petit formulaire ; cible = { vehicule_id } ou { utilisateur_id }. */
export default function Signaler({ cible, libelle = 'Signaler' }) {
  const [ouvert, setOuvert] = useState(false)
  const [motif, setMotif] = useState(cible.utilisateur_id ? 'comportement' : 'annonce_trompeuse')
  const [description, setDescription] = useState('')
  const [erreur, setErreur] = useState('')
  const [envoye, setEnvoye] = useState('')

  async function envoyer(e) {
    e.preventDefault()
    setErreur('')
    try {
      const { data } = await api.post('/signalements', { ...cible, motif, description })
      setEnvoye(data.message)
      setOuvert(false)
    } catch (err) {
      const e2 = erreurApi(err)
      setErreur(Object.values(e2.champs)[0] ?? e2.message)
    }
  }

  if (envoye) return <p className="champ-aide">{envoye}</p>
  if (!ouvert) return <button type="button" className="lien lien-discret" onClick={() => setOuvert(true)}>{libelle}</button>

  return (
    <form className="signaler" onSubmit={envoyer}>
      <select className="select" value={motif} onChange={(e) => setMotif(e.target.value)}>
        {Object.entries(MOTIFS_SIGNALEMENT).map(([valeur, texte]) => <option key={valeur} value={valeur}>{texte}</option>)}
      </select>
      <textarea rows={3} maxLength={2000} placeholder="Décrivez le problème (10 caractères minimum)" value={description} onChange={(e) => setDescription(e.target.value)} />
      {erreur && <p className="champ-erreur">{erreur}</p>}
      <div className="actions">
        <button className="bouton bouton-danger bouton-petit">Envoyer le signalement</button>
        <button type="button" className="lien" onClick={() => setOuvert(false)}>Annuler</button>
      </div>
    </form>
  )
}
