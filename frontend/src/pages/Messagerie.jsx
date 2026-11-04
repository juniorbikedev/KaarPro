import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, erreurApi } from '../api'
import Signaler from '../components/Signaler'
import { dateHeure } from '../libelles'

const INTERVALLE = 10_000 // rafraîchissement des messages, en millisecondes

function Fil({ id, onLu }) {
  const [conversation, setConversation] = useState(null)
  const [contenu, setContenu] = useState('')
  const [erreur, setErreur] = useState('')
  const bas = useRef(null)

  const charger = useCallback(() => {
    api.get(`/conversations/${id}`)
      .then(({ data }) => { setConversation(data.data); onLu() })
      .catch((err) => setErreur(erreurApi(err).message))
  }, [id, onLu])

  useEffect(() => {
    charger()
    const minuterie = setInterval(charger, INTERVALLE)
    return () => clearInterval(minuterie)
  }, [charger])

  const nbMessages = conversation?.messages.length
  useEffect(() => { bas.current?.scrollIntoView({ block: 'end' }) }, [nbMessages])

  async function envoyer(e) {
    e.preventDefault()
    if (!contenu.trim()) return
    setErreur('')
    try {
      const { data } = await api.post(`/conversations/${id}/messages`, { contenu })
      setConversation(data.data)
      setContenu('')
      onLu()
    } catch (err) {
      const e2 = erreurApi(err)
      setErreur(e2.champs.contenu ?? e2.message)
    }
  }

  if (!conversation) return <article className="carte">{erreur || 'Chargement…'}</article>

  return (
    <article className="carte fil">
      <header className="fil-entete">
        <strong>{conversation.interlocuteur}</strong>
        <Link to={`/vehicules/${conversation.vehicule.id}`} className="champ-aide">{conversation.vehicule.marque} {conversation.vehicule.modele}</Link>
        <Signaler key={conversation.interlocuteur_id} cible={{ utilisateur_id: conversation.interlocuteur_id }} libelle="Signaler" />
      </header>
      <div className="fil-messages">
        {conversation.messages.length === 0 && <p className="champ-aide">Écrivez votre premier message.</p>}
        {conversation.messages.map((m) => (
          <div key={m.id} className={m.de_moi ? 'bulle bulle-moi' : 'bulle'}>
            <p>{m.contenu}</p>
            <span>{dateHeure(m.cree_le)}{m.de_moi && m.lu ? ' · lu' : ''}</span>
          </div>
        ))}
        <div ref={bas} />
      </div>
      <form className="fil-saisie" onSubmit={envoyer}>
        <textarea rows={2} maxLength={2000} placeholder="Votre message" value={contenu} onChange={(e) => setContenu(e.target.value)}
          onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) envoyer(e) }} />
        <button className="bouton" disabled={!contenu.trim()}>Envoyer</button>
      </form>
      {erreur && <p className="champ-erreur">{erreur}</p>}
    </article>
  )
}

/** Cas « Échanger des messages » : liste des conversations et fil sélectionné. */
export default function Messagerie() {
  const { id } = useParams()
  const naviguer = useNavigate()
  const [conversations, setConversations] = useState(null)

  const charger = useCallback(() => {
    api.get('/conversations').then(({ data }) => setConversations(data.data))
  }, [])

  useEffect(() => {
    charger()
    const minuterie = setInterval(charger, INTERVALLE)
    return () => clearInterval(minuterie)
  }, [charger])

  return (
    <section>
      <h1>Messages</h1>
      <div className="maitre-detail messagerie">
        <div className="carte liste">
          {conversations?.length === 0 && <p className="champ-aide">Aucune conversation. Contactez un partenaire depuis la fiche d’un véhicule.</p>}
          {conversations?.map((c) => (
            <button key={c.id} type="button" className={String(c.id) === id ? 'ligne ligne-active' : 'ligne'} onClick={() => naviguer(`/messages/${c.id}`)}>
              <span className="ligne-conversation">
                <strong>{c.interlocuteur}</strong>
                {c.non_lus > 0 && <span className="pastille-non-lus">{c.non_lus}</span>}
              </span>
              <span className="champ-aide">{c.vehicule.marque} {c.vehicule.modele}</span>
              {c.dernier_message && <span className="apercu">{c.dernier_message.de_moi ? 'Vous : ' : ''}{c.dernier_message.contenu}</span>}
            </button>
          ))}
        </div>
        {id ? <Fil key={id} id={id} onLu={charger} /> : <article className="carte champ-aide">Choisissez une conversation.</article>}
      </div>
    </section>
  )
}
