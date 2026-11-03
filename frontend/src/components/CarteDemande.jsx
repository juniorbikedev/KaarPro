import { Link } from 'react-router-dom'
import { dateHeure, dateLisible, fcfa, STATUTS_CAUTION, STATUTS_DEMANDE, TYPES_DEMANDE } from '../libelles'
import { SansPhoto } from './CarteVehicule'

/** Résumé d'une demande, commun à l'espace client et à l'espace partenaire ; les actions sont passées en enfants. */
export default function CarteDemande({ demande, pourPartenaire = false, children }) {
  const [libelle, classe] = STATUTS_DEMANDE[demande.statut]
  const v = demande.vehicule

  return (
    <article className="carte ligne-vehicule carte-demande">
      <div className="vignette vignette-petite">
        {v.photo ? <img src={v.photo} alt="" /> : <SansPhoto />}
      </div>
      <div className="ligne-vehicule-infos">
        <p className="carte-demande-titre">
          <span className={`statut ${classe}`}>{libelle}</span>
          <strong>{TYPES_DEMANDE[demande.type]}</strong> · {v.marque} {v.modele} <span className="champ-aide">{v.immatriculation}</span>
        </p>
        {demande.type === 'location' ? (
          <p>
            Du {dateLisible(demande.date_debut)} au {dateLisible(demande.date_fin)} ({demande.nb_jours} j) ·{' '}
            <strong>{fcfa(demande.montant)}</strong> <span className="champ-aide">+ caution {fcfa(demande.montant_caution)}</span>
          </p>
        ) : (
          <p>Prix : <strong>{fcfa(demande.montant)}</strong></p>
        )}
        {pourPartenaire
          ? <p className="champ-aide">Client : {demande.client.nom} · {demande.client.telephone ?? 'téléphone non renseigné'} · {demande.client.email}</p>
          : <p className="champ-aide">Partenaire : {demande.partenaire.nom_commercial}</p>}
        {demande.message && <blockquote className="citation">{demande.message}</blockquote>}
        {demande.motif_refus && <p className="champ-aide">Motif du refus : {demande.motif_refus}</p>}
        <p className="champ-aide">
          Envoyée le {dateHeure(demande.cree_le)}
          {demande.contrat && <> · <Link to={`/contrats/${demande.contrat.id}`}>Contrat {demande.contrat.numero}</Link>{demande.contrat.signe_client_le ? ' (signé)' : ' (à signer par le client)'}</>}
        </p>
        {demande.paiement && (
          <p className="champ-aide">
            Payé {fcfa(demande.paiement.montant_total)} par {demande.paiement.operateur} le {dateHeure(demande.paiement.paye_le)}
            {demande.paiement.statut_caution && <> · {STATUTS_CAUTION[demande.paiement.statut_caution]}</>}
            {demande.paiement.caution_retenue > 0 && <> ({fcfa(demande.paiement.caution_retenue)} retenus : {demande.paiement.motif_retenue})</>}
          </p>
        )}
      </div>
      {children && <div className="ligne-vehicule-actions">{children}</div>}
    </article>
  )
}
