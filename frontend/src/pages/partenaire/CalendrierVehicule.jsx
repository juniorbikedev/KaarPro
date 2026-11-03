import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, erreurApi } from '../../api'
import Calendrier from '../../components/Calendrier'
import { dateLisible } from '../../libelles'

/** Cas « Gérer le calendrier de disponibilité » : le loueur bloque des périodes. */
export default function CalendrierVehicule() {
  const { id } = useParams()
  const [vehicule, setVehicule] = useState(null)
  const [debut, setDebut] = useState('')
  const [fin, setFin] = useState('')
  const [motif, setMotif] = useState('')
  const [erreur, setErreur] = useState('')

  const charger = useCallback(() => {
    api.get(`/partenaire/vehicules/${id}`).then(({ data }) => setVehicule(data.data))
  }, [id])

  useEffect(() => { charger() }, [charger])

  // Premier clic : début ; second clic : fin (si avant le début, on recommence)
  function choisir(jour) {
    if (!debut || fin || jour < debut) {
      setDebut(jour)
      setFin('')
    } else {
      setFin(jour)
    }
  }

  async function bloquer(e) {
    e.preventDefault()
    setErreur('')
    try {
      await api.post(`/partenaire/vehicules/${id}/indisponibilites`, { date_debut: debut, date_fin: fin || debut, motif })
      setDebut(''); setFin(''); setMotif('')
      charger()
    } catch (err) {
      const e2 = erreurApi(err)
      setErreur(Object.values(e2.champs)[0] ?? e2.message)
    }
  }

  async function supprimer(indispo) {
    await api.delete(`/partenaire/vehicules/${id}/indisponibilites/${indispo.id}`)
    charger()
  }

  if (!vehicule) return <p className="chargement">Chargement…</p>
  const aVenir = vehicule.indisponibilites.filter((i) => i.date_fin >= new Date().toISOString().slice(0, 10))

  return (
    <section>
      <p><Link to="/partenaire/vehicules">← Mes véhicules</Link></p>
      <h1>Calendrier — {vehicule.marque} {vehicule.modele}</h1>
      <div className="grille-2">
        <article className="carte">
          <Calendrier indisponibilites={vehicule.indisponibilites} debut={debut} fin={fin} onChoisir={choisir} />
        </article>
        <div className="formulaire">
          <form className="carte formulaire" onSubmit={bloquer}>
            <h2>Bloquer une période</h2>
            <p className="champ-aide">
              {debut ? <>Du <strong>{dateLisible(debut)}</strong>{fin && <> au <strong>{dateLisible(fin)}</strong></>}</> : 'Cliquez sur le premier jour, puis sur le dernier.'}
            </p>
            <input className="select" placeholder="Motif (ex. : entretien, usage personnel)" value={motif} onChange={(e) => setMotif(e.target.value)} maxLength={120} />
            {erreur && <p className="champ-erreur">{erreur}</p>}
            <button className="bouton" disabled={!debut}>Bloquer cette période</button>
          </form>
          <article className="carte">
            <h2>Périodes bloquées et réservées</h2>
            {aVenir.length === 0 && <p className="champ-aide">Aucune période bloquée à venir.</p>}
            <ul className="liste-periodes">
              {aVenir.map((i) => (
                <li key={i.id}>
                  <span>{dateLisible(i.date_debut)} → {dateLisible(i.date_fin)}{i.motif && <span className="champ-aide"> · {i.motif}</span>}</span>
                  {i.reservation
                    ? <Link to="/partenaire/demandes" className="statut statut-valide">Location</Link>
                    : <button type="button" className="lien" onClick={() => supprimer(i)}>Libérer</button>}
                </li>
              ))}
            </ul>
          </article>
        </div>
      </div>
    </section>
  )
}
