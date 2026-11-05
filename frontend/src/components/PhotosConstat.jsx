import { useState } from 'react'

/** Grille de photos d'un constat ; un clic agrandit la photo. */
export default function PhotosConstat({ photos = [], legende = 'Photo' }) {
  const [agrandie, setAgrandie] = useState(null)

  if (photos.length === 0) return <p className="champ-aide">Aucune photo.</p>

  return (
    <>
      <div className="photos-constat">
        {photos.map((p, i) => (
          <button key={p.id} type="button" className="miniature-constat" onClick={() => setAgrandie(p.url)} aria-label={`Agrandir : ${legende} ${i + 1}`}>
            <img src={p.url} alt={`${legende} ${i + 1}`} loading="lazy" />
          </button>
        ))}
      </div>
      {agrandie && (
        <div className="visionneuse" role="dialog" aria-label="Photo agrandie" onClick={() => setAgrandie(null)}>
          <img src={agrandie} alt={`${legende} agrandie`} />
        </div>
      )}
    </>
  )
}
