import { Link } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

export default function Accueil() {
  const { utilisateur } = useAuth()

  return (
    <>
      <section className="hero">
        <div>
          <p className="surtitre">Dakar · Location et vente de véhicules</p>
          <h1>Trouvez, louez ou achetez un véhicule, payez avec Wave ou Orange Money.</h1>
          <p className="hero-texte">
            KaarPro réunit les véhicules de partenaires vérifiés par TerangaAuto : particuliers et agences qui vendent,
            louent, ou font les deux.
          </p>
          {utilisateur ? (
            <Link to="/espace" className="bouton">Accéder à mon espace</Link>
          ) : (
            <div className="actions">
              <Link to="/inscription/client" className="bouton">Je cherche un véhicule</Link>
              <Link to="/inscription/partenaire" className="bouton bouton-secondaire">Je propose mes véhicules</Link>
            </div>
          )}
        </div>
      </section>

      <section className="grille-3">
        <article className="carte">
          <h2>Partenaires vérifiés</h2>
          <p>Chaque vendeur ou loueur dépose sa pièce d'identité. Un administrateur la contrôle avant toute publication.</p>
        </article>
        <article className="carte">
          <h2>Vente, location ou les deux</h2>
          <p>Le partenaire choisit son activité. La plateforme ne lui propose que les fonctions qui lui correspondent.</p>
        </article>
        <article className="carte">
          <h2>État des lieux par un agent</h2>
          <p>Un agent de TerangaAuto constate l'état du véhicule au départ et au retour, photos à l'appui.</p>
        </article>
      </section>
    </>
  )
}
