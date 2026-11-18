import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api } from '../api'
import { useAuth } from '../auth/AuthContext'
import CarteVehicule from '../components/CarteVehicule'
import { fcfa } from '../libelles'

/** Petites icônes au trait, dans la couleur du texte. */
function Icone({ nom }) {
  const traits = {
    bouclier: <><path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6z" /><path d="M9 12l2 2 4-4" /></>,
    appareil: <><path d="M4 8h3l2-2h6l2 2h3v11H4z" /><circle cx="12" cy="13" r="3.5" /></>,
    telephone: <><rect x="7" y="3" width="10" height="18" rx="2" /><path d="M11 18h2" /></>,
    contrat: <><path d="M7 3h7l4 4v14H7z" /><path d="M14 3v4h4M10 12h5M10 16h3" /></>,
    loupe: <><circle cx="11" cy="11" r="6" /><path d="M20 20l-4.5-4.5" /></>,
    message: <><path d="M4 5h16v11H9l-5 4z" /></>,
  }
  return (
    <svg className="acc-icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {traits[nom]}
    </svg>
  )
}

const ETAPES = [
  ['Trouvez', 'Filtrez par marque, ville ou prix, et ne gardez que les véhicules libres à vos dates.'],
  ['Demandez', 'Envoyez une demande de location ou d’achat : le partenaire l’accepte en un clic.'],
  ['Signez', 'Le contrat est généré automatiquement et se signe en ligne, sans papier.'],
  ['Payez', 'Réglez avec Wave ou Orange Money ; la caution vous est restituée au retour.'],
]

const ATOUTS = [
  ['bouclier', 'Partenaires vérifiés', 'Chaque vendeur ou loueur dépose sa pièce d’identité, contrôlée par TerangaAuto avant toute publication.'],
  ['appareil', 'État des lieux photographié', 'Un agent constate l’état du véhicule au départ et au retour, photos à l’appui : plus de litige sur la caution.'],
  ['telephone', 'Paiement mobile', 'Wave et Orange Money, avec confirmation automatique et reçu dans votre espace.'],
  ['contrat', 'Contrat en ligne', 'Un contrat clair, généré pour chaque location ou vente, signé par les deux parties.'],
  ['loupe', 'Disponibilité en temps réel', 'Le calendrier de chaque véhicule empêche qu’il soit promis à deux clients sur les mêmes dates.'],
  ['message', 'Messagerie intégrée', 'Posez vos questions au partenaire directement depuis la fiche du véhicule.'],
]

export default function Accueil() {
  const { utilisateur } = useAuth()
  const naviguer = useNavigate()
  const [recherche, setRecherche] = useState({ q: '', offre: '', ville: '' })
  const [vehicules, setVehicules] = useState([])
  const [total, setTotal] = useState(null)

  useEffect(() => {
    api.get('/vehicules')
      .then(({ data }) => { setVehicules(data.data); setTotal(data.meta?.total ?? data.data.length) })
      .catch(() => setVehicules([]))
  }, [])

  function chercher(e) {
    e.preventDefault()
    const params = new URLSearchParams(Object.entries(recherche).filter(([, v]) => v.trim()))
    naviguer(`/vehicules${params.size ? `?${params}` : ''}`)
  }

  const recents = vehicules.slice(0, 4)
  // Des photos différentes dans chaque bloc : la vedette et les vignettes du parc évitent les nouveautés
  const avecPhoto = vehicules.filter((v) => v.photos?.length)
  const parc = avecPhoto.filter((v) => v.partenaire?.est_interne)
  const horsNouveautes = (liste) => [...liste.filter((v) => !recents.includes(v)), ...liste.filter((v) => recents.includes(v))]
  const vedette = horsNouveautes(parc.length ? parc : avecPhoto)[0]
  const vignettes = horsNouveautes(parc.filter((v) => v !== vedette)).slice(0, 3)
  const lienPartenaire = utilisateur ? '/espace' : '/inscription/partenaire'

  return (
    <div className="accueil">
      <section className="acc-hero">
        <div className="acc-hero-texte">
          <p className="acc-surtitre">Dakar · Thiès · Mbour — location et vente de véhicules</p>
          <h1>La bonne voiture, au bon prix, payée en toute confiance.</h1>
          <p className="acc-chapo">
            Louez ou achetez auprès de partenaires vérifiés par TerangaAuto. Contrat signé en ligne,
            état des lieux photographié, paiement par Wave ou Orange Money.
          </p>

          <form className="acc-recherche" onSubmit={chercher} role="search">
            <label className="acc-champ">
              <span>Véhicule</span>
              <input value={recherche.q} onChange={(e) => setRecherche({ ...recherche, q: e.target.value })} placeholder="Marque ou modèle" />
            </label>
            <label className="acc-champ">
              <span>Je veux</span>
              <select value={recherche.offre} onChange={(e) => setRecherche({ ...recherche, offre: e.target.value })}>
                <option value="">Louer ou acheter</option>
                <option value="location">Louer</option>
                <option value="vente">Acheter</option>
              </select>
            </label>
            <label className="acc-champ">
              <span>Ville</span>
              <input value={recherche.ville} onChange={(e) => setRecherche({ ...recherche, ville: e.target.value })} placeholder="Dakar" />
            </label>
            <button className="bouton acc-chercher">Rechercher</button>
          </form>

          <ul className="acc-garanties">
            <li>Partenaires vérifiés</li>
            <li>Wave · Orange Money</li>
            <li>Contrat en ligne</li>
          </ul>
        </div>

        <div className="acc-hero-visuel" aria-hidden={!vedette}>
          {vedette ? (
            <Link to={`/vehicules/${vedette.id}`} className="acc-vedette">
              <img src={vedette.photos[0].url} alt={`${vedette.marque} ${vedette.modele}`} />
              <div className="acc-vedette-infos">
                {vedette.partenaire?.est_interne && <span className="pastille pastille-terangaauto">TerangaAuto</span>}
                <strong>{vedette.marque} {vedette.modele}</strong>
                <span>{vedette.a_louer ? `${fcfa(vedette.prix_jour)} / jour` : fcfa(vedette.prix_vente)}</span>
              </div>
            </Link>
          ) : (
            <div className="acc-vedette acc-vedette-vide" />
          )}
        </div>
      </section>

      <section className="acc-chiffres" aria-label="KaarPro en chiffres">
        <div><strong>{total ?? '—'}</strong><span>véhicules en ligne</span></div>
        <div><strong>100 %</strong><span>de partenaires vérifiés</span></div>
        <div><strong>2</strong><span>moyens de paiement mobile</span></div>
        <div><strong>0 %</strong><span>de commission sur le parc TerangaAuto</span></div>
      </section>

      {recents.length > 0 && (
        <section className="acc-section">
          <div className="acc-titre-section">
            <div>
              <p className="acc-surtitre-section">Nouveautés</p>
              <h2>Les derniers véhicules publiés</h2>
            </div>
            <Link to="/vehicules" className="acc-lien-fleche">Tout le catalogue →</Link>
          </div>
          <div className="grille-vehicules">
            {recents.map((v) => <CarteVehicule key={v.id} vehicule={v} />)}
          </div>
        </section>
      )}

      {parc.length > 0 && (
        <section className="acc-parc">
          <div className="acc-parc-texte">
            <p className="acc-surtitre">Le parc TerangaAuto</p>
            <h2>Des véhicules de l’entreprise, entretenus dans nos ateliers</h2>
            <p>
              En plus des offres de ses partenaires, TerangaAuto loue et vend ses propres véhicules récents.
              Repérez le badge <span className="pastille pastille-terangaauto">TerangaAuto</span> dans le catalogue.
            </p>
            <Link to="/vehicules" className="bouton">Découvrir le parc</Link>
          </div>
          <div className="acc-parc-vignettes">
            {vignettes.map((v) => (
              <Link key={v.id} to={`/vehicules/${v.id}`} className="acc-vignette">
                <img src={v.photos[0].url} alt={`${v.marque} ${v.modele}`} loading="lazy" />
                <span>{v.marque} {v.modele}</span>
              </Link>
            ))}
          </div>
        </section>
      )}

      <section className="acc-section">
        <div className="acc-titre-section">
          <div>
            <p className="acc-surtitre-section">Comment ça marche</p>
            <h2>De la recherche aux clés, en quatre étapes</h2>
          </div>
        </div>
        <ol className="acc-etapes">
          {ETAPES.map(([titre, texte], i) => (
            <li key={titre}>
              <span className="acc-numero">{i + 1}</span>
              <h3>{titre}</h3>
              <p>{texte}</p>
            </li>
          ))}
        </ol>
      </section>

      <section className="acc-section">
        <div className="acc-titre-section">
          <div>
            <p className="acc-surtitre-section">Pourquoi KaarPro</p>
            <h2>La confiance, à chaque étape</h2>
          </div>
        </div>
        <div className="acc-atouts">
          {ATOUTS.map(([icone, titre, texte]) => (
            <article key={titre} className="acc-atout">
              <Icone nom={icone} />
              <h3>{titre}</h3>
              <p>{texte}</p>
            </article>
          ))}
        </div>
      </section>

      <section className="acc-appel">
        <div>
          <h2>Vous avez des véhicules à louer ou à vendre ?</h2>
          <p>Publiez-les sur KaarPro : calendrier, demandes, contrats et encaissements réunis dans un seul espace.</p>
        </div>
        <Link to={lienPartenaire} className="bouton acc-bouton-clair">{utilisateur ? 'Accéder à mon espace' : 'Devenir partenaire'}</Link>
      </section>
    </div>
  )
}
