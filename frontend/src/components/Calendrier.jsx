import { useState } from 'react'

const JOURS = ['lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.']

const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

/**
 * Calendrier mensuel des disponibilités.
 * Les jours compris dans une indisponibilité sont barrés. Avec onChoisir, un clic sélectionne un jour libre.
 */
export default function Calendrier({ indisponibilites = [], debut, fin, onChoisir }) {
  const aujourdhui = iso(new Date())
  const [mois, setMois] = useState(() => {
    const d = new Date()
    return new Date(d.getFullYear(), d.getMonth(), 1)
  })

  const estBloque = (jour) => indisponibilites.some((i) => i.date_debut <= jour && jour <= i.date_fin)
  const premier = new Date(mois.getFullYear(), mois.getMonth(), 1)
  const decalage = (premier.getDay() + 6) % 7 // lundi en premier
  const nbJours = new Date(mois.getFullYear(), mois.getMonth() + 1, 0).getDate()
  const cases = [...Array(decalage).fill(null), ...Array.from({ length: nbJours }, (_, i) => iso(new Date(mois.getFullYear(), mois.getMonth(), i + 1)))]

  const changerMois = (delta) => setMois(new Date(mois.getFullYear(), mois.getMonth() + delta, 1))
  const titre = mois.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })

  return (
    <div className="calendrier">
      <div className="calendrier-entete">
        <button type="button" className="onglet" onClick={() => changerMois(-1)} aria-label="Mois précédent">‹</button>
        <strong>{titre}</strong>
        <button type="button" className="onglet" onClick={() => changerMois(1)} aria-label="Mois suivant">›</button>
      </div>
      <div className="calendrier-grille">
        {JOURS.map((j) => <span key={j} className="calendrier-jour-nom">{j}</span>)}
        {cases.map((jour, i) => {
          if (!jour) return <span key={`v${i}`} />
          const passe = jour < aujourdhui
          const bloque = estBloque(jour)
          const choisi = debut && (jour === debut || (fin && debut <= jour && jour <= fin))
          const classes = ['calendrier-case', passe && 'passe', bloque && 'bloque', choisi && 'choisi'].filter(Boolean).join(' ')
          const cliquable = onChoisir && !passe && !bloque
          return (
            <button key={jour} type="button" className={classes} disabled={!cliquable} onClick={() => onChoisir?.(jour)} title={bloque ? 'Indisponible' : undefined}>
              {Number(jour.slice(8))}
            </button>
          )
        })}
      </div>
      <div className="calendrier-legende">
        <span><i className="puce puce-libre" /> Libre</span>
        <span><i className="puce puce-bloque" /> Indisponible</span>
        {onChoisir && <span><i className="puce puce-choisi" /> Sélection</span>}
      </div>
    </div>
  )
}
