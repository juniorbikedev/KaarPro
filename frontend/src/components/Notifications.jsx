import { useCallback, useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../api'
import { dateHeure } from '../libelles'

const INTERVALLE = 30_000 // rafraîchissement du compteur, en millisecondes

/** Cloche de l'en-tête : nombre de notifications non lues et liste déroulante. */
export default function Notifications() {
  const naviguer = useNavigate()
  const [ouvert, setOuvert] = useState(false)
  const [liste, setListe] = useState([])
  const [nonLues, setNonLues] = useState(0)
  const zone = useRef(null)

  const charger = useCallback(() => {
    api.get('/notifications').then(({ data }) => { setListe(data.data); setNonLues(data.non_lues) }).catch(() => {})
  }, [])

  useEffect(() => {
    charger()
    const minuterie = setInterval(charger, INTERVALLE)
    return () => clearInterval(minuterie)
  }, [charger])

  // Un clic en dehors de la liste la referme
  useEffect(() => {
    if (!ouvert) return undefined
    const fermer = (e) => { if (!zone.current?.contains(e.target)) setOuvert(false) }
    document.addEventListener('mousedown', fermer)
    return () => document.removeEventListener('mousedown', fermer)
  }, [ouvert])

  async function ouvrir(notification) {
    setOuvert(false)
    if (!notification.lu) {
      await api.post(`/notifications/${notification.id}/lire`).catch(() => {})
      charger()
    }
    if (notification.lien) naviguer(notification.lien)
  }

  async function toutLire() {
    await api.post('/notifications/tout-lire').catch(() => {})
    charger()
  }

  return (
    <div className="notifications" ref={zone}>
      <button type="button" className="cloche" onClick={() => setOuvert(!ouvert)} aria-expanded={ouvert}
        aria-label={nonLues ? `Notifications, ${nonLues} non lue${nonLues > 1 ? 's' : ''}` : 'Notifications'}>
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
          <path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 0 0-5.5-6.84V3.5a1.5 1.5 0 0 0-3 0v.66A7 7 0 0 0 5 11v5l-2 2v1h18v-1l-2-2Z" />
        </svg>
        {nonLues > 0 && <span className="pastille-non-lus">{nonLues > 9 ? '9+' : nonLues}</span>}
      </button>
      {ouvert && (
        <div className="notifications-liste">
          <div className="notifications-entete">
            <strong>Notifications</strong>
            {nonLues > 0 && <button type="button" className="lien" onClick={toutLire}>Tout marquer comme lu</button>}
          </div>
          {liste.length === 0 && <p className="champ-aide">Aucune notification.</p>}
          {liste.map((n) => (
            <button key={n.id} type="button" className={n.lu ? 'notification' : 'notification non-lue'} onClick={() => ouvrir(n)}>
              <strong>{n.titre}</strong>
              <span>{n.texte}</span>
              <small>{dateHeure(n.cree_le)}</small>
            </button>
          ))}
        </div>
      )}
    </div>
  )
}
