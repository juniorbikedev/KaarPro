import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './auth/AuthContext'
import Layout from './components/Layout'
import Protege from './components/Protege'
import Accueil from './pages/Accueil'
import Catalogue from './pages/Catalogue'
import Connexion from './pages/Connexion'
import Contrat from './pages/Contrat'
import Espace from './pages/Espace'
import FicheVehicule from './pages/FicheVehicule'
import MesDemandes from './pages/MesDemandes'
import Messagerie from './pages/Messagerie'
import CalendrierVehicule from './pages/partenaire/CalendrierVehicule'
import DemandesRecues from './pages/partenaire/DemandesRecues'
import Encaissements from './pages/partenaire/Encaissements'
import FormVehicule from './pages/partenaire/FormVehicule'
import MesVehicules from './pages/partenaire/MesVehicules'
import { ChoixInscription, InscriptionClient, InscriptionPartenaire } from './pages/Inscription'
import Profil from './pages/Profil'
import SimulateurPaiement from './pages/SimulateurPaiement'
import Paiements from './pages/admin/Paiements'
import Partenaires from './pages/admin/Partenaires'
import Utilisateurs from './pages/admin/Utilisateurs'

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route element={<Layout />}>
            <Route index element={<Accueil />} />
            <Route path="connexion" element={<Connexion />} />
            <Route path="inscription" element={<ChoixInscription />} />
            <Route path="inscription/client" element={<InscriptionClient />} />
            <Route path="inscription/partenaire" element={<InscriptionPartenaire />} />
            <Route path="vehicules" element={<Catalogue />} />
            <Route path="vehicules/:id" element={<FicheVehicule />} />
            <Route path="espace" element={<Protege><Espace /></Protege>} />
            <Route path="partenaire/vehicules" element={<Protege roles={['vendeur']}><MesVehicules /></Protege>} />
            <Route path="partenaire/vehicules/nouveau" element={<Protege roles={['vendeur']}><FormVehicule /></Protege>} />
            <Route path="partenaire/vehicules/:id/modifier" element={<Protege roles={['vendeur']}><FormVehicule /></Protege>} />
            <Route path="partenaire/vehicules/:id/calendrier" element={<Protege roles={['vendeur']}><CalendrierVehicule /></Protege>} />
            <Route path="partenaire/demandes" element={<Protege roles={['vendeur']}><DemandesRecues /></Protege>} />
            <Route path="demandes" element={<Protege roles={['client']}><MesDemandes /></Protege>} />
            <Route path="paiement/:reference" element={<Protege roles={['client']}><SimulateurPaiement /></Protege>} />
            <Route path="partenaire/paiements" element={<Protege roles={['vendeur']}><Encaissements /></Protege>} />
            <Route path="admin/paiements" element={<Protege roles={['admin']}><Paiements /></Protege>} />
            <Route path="contrats/:id" element={<Protege roles={['client', 'vendeur', 'admin']}><Contrat /></Protege>} />
            <Route path="messages" element={<Protege roles={['client', 'vendeur']}><Messagerie /></Protege>} />
            <Route path="messages/:id" element={<Protege roles={['client', 'vendeur']}><Messagerie /></Protege>} />
            <Route path="profil" element={<Protege><Profil /></Protege>} />
            <Route path="admin/partenaires" element={<Protege roles={['admin']}><Partenaires /></Protege>} />
            <Route path="admin/utilisateurs" element={<Protege roles={['admin']}><Utilisateurs /></Protege>} />
            <Route path="*" element={<p>Page introuvable.</p>} />
          </Route>
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}
