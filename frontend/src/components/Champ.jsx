/** Champ de formulaire avec libellé et message d'erreur renvoyé par l'API. */
export default function Champ({ label, nom, erreur, aide, type = 'text', ...props }) {
  return (
    <label className="champ">
      <span className="champ-libelle">{label}</span>
      <input name={nom} type={type} aria-invalid={Boolean(erreur)} {...props} />
      {aide && !erreur && <span className="champ-aide">{aide}</span>}
      {erreur && <span className="champ-erreur">{erreur}</span>}
    </label>
  )
}
