import { useCallback, useEffect, useState } from 'react'
import { api, erreurApi } from '../../api'
import { LIBELLES_ACTIVITE } from '../../auth/AuthContext'

const FILTRES = { en_attente: 'En attente', valide: 'Validés', refuse: 'Refusés', '': 'Tous' }
const STATUT = { en_attente: ['En attente', 'statut-attente'], valide: ['Validé', 'statut-valide'], refuse: ['Refusé', 'statut-refuse'] }
const DOCS = { piece_identite: "Pièce d'identité", carte_grise: 'Carte grise', assurance: 'Assurance' }

function Detail({ id, onChange }) {
  const [partenaire, setPartenaire] = useState(null)
  const [motif, setMotif] = useState('')
  const [erreur, setErreur] = useState('')

  useEffect(() => {
    api.get(`/admin/partenaires/${id}`).then(({ data }) => setPartenaire(data.data))
  }, [id])

  async function decider(action) {
    setErreur('')
    try {
      const { data } = await api.post(`/admin/partenaires/${id}/${action}`, action === 'refuser' ? { motif } : {})
      setPartenaire(data.data)
      setMotif('')
      onChange()
    } catch (err) {
      const e = erreurApi(err)
      setErreur(e.champs.motif ?? e.message)
    }
  }

  async function ouvrirDocument(doc) {
    const reponse = await api.get(`/admin/documents/${doc.id}/fichier`, { responseType: 'blob' })
    window.open(URL.createObjectURL(reponse.data), '_blank', 'noopener')
  }

  if (!partenaire) return <article className="carte">Chargement…</article>
  const [libelle, classe] = STATUT[partenaire.statut_validation]

  return (
    <article className="carte">
      <h2>{partenaire.nom_commercial}</h2>
      <p><span className={`statut ${classe}`}>{libelle}</span></p>
      <dl className="details">
        <dt>Responsable</dt><dd>{partenaire.utilisateur.nom}</dd>
        <dt>E-mail</dt><dd>{partenaire.utilisateur.email}</dd>
        <dt>Téléphone</dt><dd>{partenaire.utilisateur.telephone ?? '—'}</dd>
        <dt>Activité</dt><dd>{LIBELLES_ACTIVITE[partenaire.type_activite]}</dd>
      </dl>
      <h3>Documents fournis</h3>
      <ul className="liste-docs">
        {partenaire.documents.map((doc) => (
          <li key={doc.id}>
            <button type="button" className="lien" onClick={() => ouvrirDocument(doc)}>{DOCS[doc.type]} — {doc.nom_original}</button>
            {doc.est_verifie && <span className="statut statut-valide">vérifié</span>}
          </li>
        ))}
      </ul>
      {partenaire.statut_validation !== 'valide' && (
        <button type="button" className="bouton" onClick={() => decider('valider')}>Valider le partenaire</button>
      )}
      {partenaire.statut_validation !== 'refuse' && (
        <div className="refus">
          <textarea placeholder="Motif du refus (obligatoire)" value={motif} onChange={(e) => setMotif(e.target.value)} rows={2} />
          <button type="button" className="bouton bouton-danger" onClick={() => decider('refuser')}>Refuser</button>
        </div>
      )}
      {partenaire.motif_refus && <p className="champ-aide">Motif enregistré : {partenaire.motif_refus}</p>}
      {erreur && <p className="champ-erreur">{erreur}</p>}
    </article>
  )
}

export default function Partenaires() {
  const [filtre, setFiltre] = useState('en_attente')
  const [liste, setListe] = useState([])
  const [selection, setSelection] = useState(null)

  const charger = useCallback(() => {
    api.get('/admin/partenaires', { params: filtre ? { statut: filtre } : {} }).then(({ data }) => setListe(data.data))
  }, [filtre])

  useEffect(() => { charger() }, [charger])

  return (
    <section>
      <h1>Valider les partenaires</h1>
      <div className="onglets">
        {Object.entries(FILTRES).map(([valeur, libelle]) => (
          <button key={valeur} type="button" className={filtre === valeur ? 'onglet onglet-actif' : 'onglet'} onClick={() => setFiltre(valeur)}>{libelle}</button>
        ))}
      </div>
      <div className="maitre-detail">
        <div className="carte liste">
          {liste.length === 0 && <p className="champ-aide">Aucun partenaire dans cette catégorie.</p>}
          {liste.map((p) => (
            <button key={p.id} type="button" className={`ligne ${selection === p.id ? 'ligne-active' : ''}`} onClick={() => setSelection(p.id)}>
              <strong>{p.nom_commercial}</strong>
              <span>{p.utilisateur.nom} · {LIBELLES_ACTIVITE[p.type_activite]}</span>
            </button>
          ))}
        </div>
        {selection ? <Detail key={selection} id={selection} onChange={charger} /> : <article className="carte champ-aide">Sélectionnez un partenaire.</article>}
      </div>
    </section>
  )
}
