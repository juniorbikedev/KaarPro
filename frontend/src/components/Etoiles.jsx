/** Note sur 5 : affichage simple, ou saisie quand onChange est fourni. */
export default function Etoiles({ note, onChange }) {
  return (
    <span className="etoiles" role={onChange ? 'radiogroup' : 'img'} aria-label={`${note ?? 0} sur 5`}>
      {[1, 2, 3, 4, 5].map((n) => onChange ? (
        <button key={n} type="button" role="radio" aria-checked={note === n} aria-label={`${n} sur 5`}
          className={n <= (note ?? 0) ? 'etoile pleine' : 'etoile'} onClick={() => onChange(n)}>★</button>
      ) : (
        <span key={n} className={n <= Math.round(note ?? 0) ? 'etoile pleine' : 'etoile'} aria-hidden="true">★</span>
      ))}
    </span>
  )
}
