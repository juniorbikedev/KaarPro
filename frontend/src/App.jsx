import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './auth/AuthContext'
import Layout from './components/Layout'
import Protege from './components/Protege'
import Accueil from './pages/Accueil'
import Catalogue from './pages/Catalogue'
import Connexion from './pages/Connexion'
import Espace from './pages/Espace'
import FicheVehicule from './pages/FicheVehicule'
import CalendrierVehicule from './pages/partenaire/CalendrierVehicule'
import FormVehicule from './pages/partenaire/FormVehicule'
import MesVehicules from './pages/partenaire/MesVehicules'
import { ChoixInscription, InscriptionClient, InscriptionPartenaire } from './pages/Inscription'
import Profil from './pages/Profil'
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
