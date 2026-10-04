"""Le contrôle après génération : chaque façon de désobéir est rejetée par le code."""

import unittest

from app.controle import controler
from app.modeles import Fiche

PRATIQUE = Fiche(id=1, type="pratique", cible="général", titre="Désherber et brûler les feuilles tombées")
BIO = Fiche(id=2, type="biologique", cible="anthracnose", titre="Extrait de neem")
CHIMIQUE = Fiche(
    id=3, type="chimique", cible="anthracnose", titre="Traitement homologué",
    nom_commercial="Produit-Test", matiere_active="Matière-Test", dose="selon fiche", passages=2,
    delai_avant_recolte_jours=21, toxicite_humaine="III", protection="gants, masque", effet_abeilles="toxique",
)
NOMS = ["Produit-Test", "Matière-Test", "Produit-Interdit"]


class ControleTest(unittest.TestCase):
    def test_un_conseil_qui_cite_les_fiches_dans_l_ordre_est_accepte(self):
        v = controler("Commencer par [FICHE-1], puis [FICHE-2]. Si cela ne suffit pas : [FICHE-3].",
                      [PRATIQUE, BIO, CHIMIQUE], NOMS)
        self.assertTrue(v.accepte, v.motifs)
        self.assertEqual([1, 2, 3], v.fiches_citees)

    def test_une_dose_ecrite_par_le_modele_est_rejetee(self):
        for dose in ["20 ml par pulvérisateur", "2 l/ha", "500 g par hectare", "1,5 kg", "30 cc"]:
            v = controler(f"Appliquer [FICHE-1] à {dose}.", [PRATIQUE], NOMS)
            self.assertFalse(v.accepte, dose)
            self.assertIn("Dose", v.motifs[0])

    def test_des_jours_ou_un_pourcentage_ne_sont_pas_des_doses(self):
        v = controler("Répéter [FICHE-1] tous les 15 jours ; 30 % des arbres sont touchés.", [PRATIQUE], NOMS)
        self.assertTrue(v.accepte, v.motifs)

    def test_un_nom_de_produit_en_clair_est_rejete_meme_retire_ou_interdit(self):
        for texte in ["Utiliser Produit-Test.", "La matiere-test est efficace.", "Évitez le PRODUIT-INTERDIT."]:
            v = controler(texte, [PRATIQUE, BIO, CHIMIQUE], NOMS)
            self.assertFalse(v.accepte, texte)

    def test_une_fiche_non_fournie_ne_peut_pas_etre_citee(self):
        v = controler("Voir [FICHE-99].", [PRATIQUE], NOMS)
        self.assertFalse(v.accepte)
        self.assertIn("inconnu", v.motifs[0])

    def test_parler_de_fongicide_sans_fiche_chimique_est_rejete(self):
        v = controler("Faites [FICHE-1] et achetez un fongicide au marché.", [PRATIQUE, BIO], NOMS)
        self.assertFalse(v.accepte)

    def test_le_chimique_ne_passe_pas_avant_les_pratiques_ni_sans_elles(self):
        avant = controler("D'abord [FICHE-3], ensuite [FICHE-1] et [FICHE-2].", [PRATIQUE, BIO, CHIMIQUE], NOMS)
        self.assertFalse(avant.accepte)
        sans = controler("Utilisez [FICHE-3].", [PRATIQUE, BIO, CHIMIQUE], NOMS)
        self.assertFalse(sans.accepte)

    def test_une_fiche_chimique_incomplete_n_est_pas_proposable(self):
        incomplete = CHIMIQUE.model_copy(update={"delai_avant_recolte_jours": None})
        v = controler("[FICHE-1], [FICHE-2], puis [FICHE-3].", [PRATIQUE, BIO, incomplete], NOMS)
        self.assertFalse(v.accepte)
        self.assertIn("incomplète", " ".join(v.motifs))


if __name__ == "__main__":
    unittest.main()
