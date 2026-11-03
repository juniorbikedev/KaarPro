import { useCallback, useEffect, useState } from 'react'
import { api, erreurApi } from '../../api'
import CarteDemande from '../../components/CarteDemande'

const FILTRES = { en_attente: 'En attente', acceptee: 'Acceptées', refusee: 'Refusées', '': 'Toutes' }

function Actions({ demande, onChange }) {
  const [refus, setRefus] = useState(false)
  const [motif, setMotif] = useState('')
  const [erreur, setErreur] = useState('')

  async function decider(action) {
    setErreur('')
    try {
      await api.post(`/partenaire/demandes/${demande.id}/${action}`, action === 'refuser' ? { motif } : {})
      onChange()
    } catch (err) {
      const e = erreurApi(err)
      setErreur(Object.values(e.champs)[0] ?? e.message)
    }
  }

  return (
    <div className="actions-demande">
      {!refus ? (
        <>
          <button type="button" className="bouton" onClick={() => decider('accepter')}>Accepter</button>
          <button type="button" className="bouton bouton-discret" onClick={() => setRefus(true)}>Refuser</button>
        </>
      ) : (
        <>
          <textarea rows={2} placeholder="Motif du refus (obligatoire)" value={motif} onChange={(e) => setMotif(e.target.value)} />
          <button type="button" className="bouton bouton-danger" onClick={() => decider('refuser')}>Confirmer le refus</button>
          <button type="button" className="lien" onClick={() => setRefus(false)}>Annuler</button>
        </>
      )}
      {erreur && <p className="champ-erreur">{erreur}</p>}
    </div>
  )
}

/** Cas « Traiter les demandes reçues » (vendeur/loueur). Accepter génère le contrat. */
export default function DemandesRecues() {
  const [filtre, setFiltre] = useState('en_attente')
  const [demandes, setDemandes] = useState(null)
  const [version, setVersion] = useState(0)
  const charger = useCallback(() => setVersion((v) => v + 1), [])

  useEffect(() => {
    // Une réponse arrivée après un changement d'onglet est ignorée
    let actuelle = true
    api.get('/partenaire/demandes', { params: filtre ? { statut: filtre } : {} })
      .then(({ data }) => { if (actuelle) setDemandes(data.data) })
    return () => { actuelle = false }
  }, [filtre, version])

  return (
    <section>
      <h1>Demandes reçues</h1>
      <div className="onglets">
        {Object.entries(FILTRES).map(([valeur, libelle]) => (
          <button key={valeur} type="button" className={filtre === valeur ? 'onglet onglet-actif' : 'onglet'} onClick={() => setFiltre(valeur)}>{libelle}</button>
        ))}
      </div>
      {!demandes && <p className="chargement">Chargement…</p>}
      {demandes?.length === 0 && <p className="carte">Aucune demande dans cette catégorie.</p>}
      <div className="liste-vehicules">
        {demandes?.map((d) => (
          <CarteDemande key={d.id} demande={d} pourPartenaire>
            {d.statut === 'en_attente' && <Actions demande={d} onChange={charger} />}
          </CarteDemande>
        ))}
      </div>
    </section>
  )
}
