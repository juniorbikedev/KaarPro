import { useEffect, useState } from 'react'
import { api, erreurApi } from '../../api'
import { dateHeure, fcfa } from '../../libelles'

/** Cas « Consulter ses encaissements » : ce que les clients ont payé, la commission, le net qui revient au partenaire. */
export default function Encaissements() {
  const [reponse, setReponse] = useState(null)
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    api.get('/partenaire/paiements').then(({ data }) => setReponse(data)).catch((err) => setErreur(erreurApi(err).message))
  }, [])

  if (!reponse) return erreur ? <p className="alerte alerte-erreur">{erreur}</p> : <p className="chargement">Chargement…</p>
  const { data: paiements, totaux } = reponse

  return (
    <section>
      <h1>Encaissements</h1>
      <div className="grille-3 chiffres">
        <article className="carte"><span className="champ-aide">Montants payés</span><strong>{fcfa(totaux.montant)}</strong></article>
        <article className="carte"><span className="champ-aide">Commission KaarPro</span><strong>{fcfa(totaux.commission)}</strong></article>
        <article className="carte"><span className="champ-aide">Net pour vous</span><strong>{fcfa(totaux.montant_partenaire)}</strong></article>
      </div>
      <article className="carte">
        {paiements.length === 0 ? <p className="champ-aide">Aucun paiement reçu pour le moment.</p> : (
          <div className="tableau-defilant">
            <table className="tableau">
              <thead>
                <tr><th>Date</th><th>Client</th><th>Objet</th><th>Montant</th><th>Commission</th><th>Net</th><th>Caution</th></tr>
              </thead>
              <tbody>
                {paiements.map((p) => (
                  <tr key={p.reference}>
                    <td>{dateHeure(p.paye_le)}</td>
                    <td>{p.client}</td>
                    <td>{p.demande.type === 'location' ? 'Location' : 'Vente'} — {p.demande.vehicule}</td>
                    <td>{fcfa(p.montant)}</td>
                    <td>{fcfa(p.commission)} <span className="champ-aide">({p.taux_commission} %)</span></td>
                    <td><strong>{fcfa(p.montant_partenaire)}</strong></td>
                    <td>{p.montant_caution > 0 ? fcfa(p.montant_caution) : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </article>
    </section>
  )
}
