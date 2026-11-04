import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, erreurApi } from '../../api'
import { dateHeure, fcfa } from '../../libelles'

const FILTRES = { reussi: 'Réussis', en_attente: 'En attente', echoue: 'Échoués', '': 'Tous' }
const STATUT = { reussi: ['Réussi', 'statut-valide'], en_attente: ['En attente', 'statut-attente'], echoue: ['Échoué', 'statut-refuse'] }

/** Restitution de la caution, avec une retenue éventuelle pour les dommages constatés. */
function Restitution({ paiement, onFait }) {
  // La retenue proposée par l'agent au retour sert de point de départ
  const constat = paiement.demande.etat_retour
  const [ouvert, setOuvert] = useState(false)
  const [retenue, setRetenue] = useState(String(constat?.montant_dommages ?? 0))
  const [motif, setMotif] = useState(constat?.dommages ?? '')
  const [erreur, setErreur] = useState('')

  async function restituer(e) {
    e.preventDefault()
    setErreur('')
    try {
      await api.post(`/admin/paiements/${paiement.reference}/caution`, { caution_retenue: Number(retenue || 0), motif_retenue: motif })
      onFait()
    } catch (err) {
      const e2 = erreurApi(err)
      setErreur(Object.values(e2.champs)[0] ?? e2.message)
    }
  }

  if (constat && !constat.realise) return <span className="champ-aide">En attente de l’état des lieux de retour</span>
  if (!ouvert) {
    return (
      <>
        {constat && <Link to={`/etats-des-lieux/${constat.id}`} className="champ-aide">Voir le constat de retour</Link>}<br />
        <button type="button" className="lien" onClick={() => setOuvert(true)}>Restituer</button>
      </>
    )
  }

  return (
    <form className="restitution" onSubmit={restituer}>
      <label className="champ-aide">Retenue (FCFA)
        <input className="select" type="number" min="0" max={paiement.montant_caution} step="500" value={retenue} onChange={(e) => setRetenue(e.target.value)} />
      </label>
      {Number(retenue) > 0 && <input className="select" placeholder="Motif de la retenue" value={motif} onChange={(e) => setMotif(e.target.value)} />}
      <p className="champ-aide">Rendu au client : {fcfa(paiement.montant_caution - Number(retenue || 0))}</p>
      {erreur && <p className="champ-erreur">{erreur}</p>}
      <div className="actions">
        <button className="bouton bouton-petit">Confirmer</button>
        <button type="button" className="lien" onClick={() => setOuvert(false)}>Annuler</button>
      </div>
    </form>
  )
}

/** Cas « Suivre les paiements » et « Restituer la caution » (administrateur). */
export default function Paiements() {
  const [filtre, setFiltre] = useState('reussi')
  const [reponse, setReponse] = useState(null)
  const [version, setVersion] = useState(0)

  useEffect(() => {
    let actuelle = true
    api.get('/admin/paiements', { params: filtre ? { statut: filtre } : {} })
      .then(({ data }) => { if (actuelle) setReponse(data) })
    return () => { actuelle = false }
  }, [filtre, version])

  return (
    <section>
      <h1>Paiements</h1>
      {reponse && (
        <div className="grille-3 chiffres">
          <article className="carte"><span className="champ-aide">Total encaissé</span><strong>{fcfa(reponse.totaux.encaisse)}</strong></article>
          <article className="carte"><span className="champ-aide">Commissions KaarPro</span><strong>{fcfa(reponse.totaux.commission)}</strong></article>
          <article className="carte"><span className="champ-aide">Cautions bloquées</span><strong>{fcfa(reponse.totaux.cautions_bloquees)}</strong></article>
        </div>
      )}
      <div className="onglets">
        {Object.entries(FILTRES).map(([valeur, libelle]) => (
          <button key={valeur} type="button" className={filtre === valeur ? 'onglet onglet-actif' : 'onglet'} onClick={() => setFiltre(valeur)}>{libelle}</button>
        ))}
      </div>
      <article className="carte">
        {!reponse && <p className="chargement">Chargement…</p>}
        {reponse?.data.length === 0 && <p className="champ-aide">Aucun paiement.</p>}
        {reponse?.data.length > 0 && (
          <div className="tableau-defilant">
            <table className="tableau">
              <thead>
                <tr><th>Référence</th><th>Client</th><th>Partenaire</th><th>Objet</th><th>Opérateur</th><th>Total</th><th>Commission</th><th>Statut</th><th>Caution</th></tr>
              </thead>
              <tbody>
                {reponse.data.map((p) => {
                  const [libelle, classe] = STATUT[p.statut]
                  return (
                    <tr key={p.reference}>
                      <td>{p.reference}<br /><span className="champ-aide">{dateHeure(p.paye_le ?? p.cree_le)}</span></td>
                      <td>{p.client}</td>
                      <td>{p.demande.partenaire}</td>
                      <td>{p.demande.type === 'location' ? 'Location' : 'Vente'} — {p.demande.vehicule}</td>
                      <td>{p.operateur_libelle}</td>
                      <td>{fcfa(p.montant_total)}</td>
                      <td>{fcfa(p.commission)}</td>
                      <td><span className={`statut ${classe}`}>{libelle}</span></td>
                      <td>
                        {!p.statut_caution && '—'}
                        {p.statut_caution === 'bloquee' && <>{fcfa(p.montant_caution)}<br /><Restitution paiement={p} onFait={() => setVersion((v) => v + 1)} /></>}
                        {p.statut_caution === 'restituee' && (
                          <span className="champ-aide">Restituée{p.caution_retenue > 0 ? ` (retenue ${fcfa(p.caution_retenue)} : ${p.motif_retenue})` : ''}</span>
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </article>
    </section>
  )
}
