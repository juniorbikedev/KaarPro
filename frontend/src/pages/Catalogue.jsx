import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { api, erreurApi } from '../api'
import CarteVehicule from '../components/CarteVehicule'
import { CATEGORIES } from '../libelles'

const CHAMPS = ['q', 'offre', 'categorie', 'ville', 'prix_max', 'date_debut', 'date_fin', 'tri', 'page']

/** Cas « Rechercher un véhicule » et « Filtrer par critères ». Les filtres sont gardés dans l'adresse. */
export default function Catalogue() {
  const [params, setParams] = useSearchParams()
  const [form, setForm] = useState(() => Object.fromEntries(CHAMPS.map((c) => [c, params.get(c) ?? ''])))
  const [resultat, setResultat] = useState({ data: [], meta: null })
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    const filtres = Object.fromEntries([...params.entries()].filter(([, v]) => v))
    setChargement(true)
    api.get('/vehicules', { params: filtres })
      .then(({ data }) => { setResultat(data); setErreur('') })
      .catch((err) => setErreur(erreurApi(err).message))
      .finally(() => setChargement(false))
  }, [params])

  const changer = (e) => setForm({ ...form, [e.target.name]: e.target.value })

  function rechercher(e) {
    e.preventDefault()
    setParams(Object.fromEntries(Object.entries({ ...form, page: '' }).filter(([, v]) => v)))
  }

  function allerPage(page) {
    const suivant = new URLSearchParams(params)
    suivant.set('page', page)
    setParams(suivant)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const meta = resultat.meta

  return (
    <section>
      <h1>Catalogue des véhicules</h1>
      <form className="carte filtres" onSubmit={rechercher}>
        <input className="select filtre-large" name="q" value={form.q} onChange={changer} placeholder="Marque ou modèle (ex. : Corolla)" aria-label="Recherche" />
        <select className="select" name="offre" value={form.offre} onChange={changer} aria-label="Type d'offre">
          <option value="">Louer ou acheter</option>
          <option value="location">À louer</option>
          <option value="vente">À vendre</option>
        </select>
        <select className="select" name="categorie" value={form.categorie} onChange={changer} aria-label="Catégorie">
          <option value="">Toutes catégories</option>
          {Object.entries(CATEGORIES).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
        </select>
        <input className="select" name="ville" value={form.ville} onChange={changer} placeholder="Ville" aria-label="Ville" />
        <input className="select" name="prix_max" type="number" min="0" step="1000" value={form.prix_max} onChange={changer}
          placeholder={form.offre === 'vente' ? 'Prix max (FCFA)' : 'Prix max / jour (FCFA)'} aria-label="Prix maximum" />
        {form.offre !== 'vente' && (
          <>
            <label className="filtre-date">Du <input className="select" type="date" name="date_debut" value={form.date_debut} onChange={changer} /></label>
            <label className="filtre-date">au <input className="select" type="date" name="date_fin" value={form.date_fin} min={form.date_debut || undefined} onChange={changer} /></label>
          </>
        )}
        <select className="select" name="tri" value={form.tri} onChange={changer} aria-label="Tri">
          <option value="">Plus récents</option>
          <option value="prix_croissant">Prix croissant</option>
          <option value="prix_decroissant">Prix décroissant</option>
        </select>
        <button className="bouton">Rechercher</button>
      </form>

      {erreur && <p className="alerte alerte-erreur">{erreur}</p>}
      {chargement ? (
        <p className="chargement">Chargement…</p>
      ) : (
        <>
          <p className="champ-aide">{meta?.total ?? 0} véhicule(s) trouvé(s)</p>
          {resultat.data.length === 0 && <article className="carte">Aucun véhicule ne correspond à ces critères.</article>}
          <div className="grille-vehicules">
            {resultat.data.map((v) => <CarteVehicule key={v.id} vehicule={v} />)}
          </div>
          {meta && meta.last_page > 1 && (
            <div className="pagination">
              <button type="button" className="onglet" disabled={meta.current_page <= 1} onClick={() => allerPage(meta.current_page - 1)}>Précédent</button>
              <span>Page {meta.current_page} sur {meta.last_page}</span>
              <button type="button" className="onglet" disabled={meta.current_page >= meta.last_page} onClick={() => allerPage(meta.current_page + 1)}>Suivant</button>
            </div>
          )}
        </>
      )}
    </section>
  )
}
