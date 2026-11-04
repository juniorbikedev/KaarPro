import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, erreurApi } from '../../api'
import { fcfa, STATUTS_DEMANDE } from '../../libelles'

function Chiffre({ libelle, valeur, lien }) {
  const contenu = <><span className="champ-aide">{libelle}</span><strong>{valeur}</strong></>
  return lien ? <Link to={lien} className="carte carte-choix chiffre">{contenu}</Link> : <article className="carte chiffre">{contenu}</article>
}

/** Cas « Consulter le tableau de bord » : réservé à l'administrateur. */
export default function TableauDeBord() {
  const [donnees, setDonnees] = useState(null)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    api.get('/admin/tableau-de-bord').then(({ data }) => setDonnees(data.data)).catch((err) => setErreur(erreurApi(err).message))
  }, [])

  if (!donnees) return erreur ? <p className="alerte alerte-erreur">{erreur}</p> : <p className="chargement">Chargement…</p>

  const { utilisateurs, vehicules, demandes, finances, a_traiter: aTraiter, satisfaction } = donnees
  const maxCommission = Math.max(1, ...donnees.commissions_par_mois.map((m) => m.commission))
  const totalDemandes = Object.values(demandes).reduce((a, b) => a + b, 0)

  return (
    <section>
      <h1>Tableau de bord</h1>

      <h2 className="titre-section">À traiter</h2>
      <div className="grille-chiffres">
        <Chiffre libelle="Partenaires à valider" valeur={aTraiter.partenaires_a_valider} lien="/admin/partenaires" />
        <Chiffre libelle="Signalements ouverts" valeur={aTraiter.signalements_ouverts} lien="/admin/signalements" />
        <Chiffre libelle="Missions sans agent" valeur={aTraiter.missions_a_attribuer} />
        <Chiffre libelle="Cautions bloquées" valeur={fcfa(finances.cautions_bloquees)} lien="/admin/paiements" />
      </div>

      <h2 className="titre-section">Activité</h2>
      <div className="grille-chiffres">
        <Chiffre libelle="Total encaissé" valeur={fcfa(finances.encaisse)} />
        <Chiffre libelle="Commissions (total)" valeur={fcfa(finances.commission_totale)} />
        <Chiffre libelle="Commissions du mois" valeur={fcfa(finances.commission_du_mois)} />
        <Chiffre libelle="Satisfaction" valeur={satisfaction.nombre_avis ? `${satisfaction.note_moyenne.toLocaleString('fr-FR')}/5 (${satisfaction.nombre_avis} avis)` : '—'} />
      </div>

      <div className="grille-2 tableau-de-bord">
        <article className="carte">
          <h2>Commissions des six derniers mois</h2>
          <div className="histogramme" role="img" aria-label="Commissions par mois">
            {donnees.commissions_par_mois.map((m) => (
              <div key={m.mois} className="histogramme-colonne" title={`${m.libelle} : ${fcfa(m.commission)} (${m.transactions} paiement${m.transactions > 1 ? 's' : ''})`}>
                <span className="histogramme-valeur">{m.commission ? new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 }).format(m.commission) : ''}</span>
                <div className="histogramme-barre" style={{ height: `${(m.commission / maxCommission) * 100}%` }} />
                <span className="histogramme-libelle">{m.libelle}</span>
              </div>
            ))}
          </div>
        </article>

        <article className="carte">
          <h2>Demandes ({totalDemandes})</h2>
          <ul className="repartition">
            {Object.entries(demandes).map(([statut, nombre]) => (
              <li key={statut}>
                <span className={`statut ${STATUTS_DEMANDE[statut][1]}`}>{STATUTS_DEMANDE[statut][0]}</span>
                <span className="repartition-barre"><i style={{ width: `${totalDemandes ? (nombre / totalDemandes) * 100 : 0}%` }} /></span>
                <strong>{nombre}</strong>
              </li>
            ))}
          </ul>
        </article>

        <article className="carte">
          <h2>Plateforme</h2>
          <dl className="details">
            <dt>Clients</dt><dd>{utilisateurs.clients}</dd>
            <dt>Vendeurs / loueurs</dt><dd>{utilisateurs.partenaires}</dd>
            <dt>Agents</dt><dd>{utilisateurs.agents}</dd>
            <dt>Véhicules publiés</dt><dd>{vehicules.publies}</dd>
            <dt>En location</dt><dd>{vehicules.en_location}</dd>
            <dt>Vendus</dt><dd>{vehicules.vendus}</dd>
          </dl>
        </article>

        <article className="carte">
          <h2>Véhicules les plus demandés</h2>
          {donnees.vehicules_les_plus_demandes.length === 0 && <p className="champ-aide">Aucune demande pour le moment.</p>}
          <ol className="classement">
            {donnees.vehicules_les_plus_demandes.map((v) => (
              <li key={v.id}><Link to={`/vehicules/${v.id}`}>{v.libelle}</Link> <span className="champ-aide">· {v.demandes} demande{v.demandes > 1 ? 's' : ''}</span></li>
            ))}
          </ol>
        </article>
      </div>
    </section>
  )
}
