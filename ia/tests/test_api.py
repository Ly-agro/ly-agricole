"""L'API du service : jeton obligatoire, conseil contrôlé, diagnostic prudent."""

import os
import unittest
from unittest import mock

from fastapi.testclient import TestClient

from app import llm
from app.main import app

JETON = {"X-Jeton-Ia": "secret-de-test"}
FICHES = [
    {"id": 1, "type": "pratique", "cible": "général", "titre": "Désherber autour des arbres"},
    {"id": 2, "type": "biologique", "cible": "anthracnose", "titre": "Extrait de neem"},
]


@mock.patch.dict(os.environ, {"IA_JETON": "secret-de-test", "IA_LLM": "faux"})
class ApiTest(unittest.TestCase):
    def setUp(self):
        self.client = TestClient(app)

    def test_sans_jeton_ou_avec_un_mauvais_jeton_rien_ne_passe(self):
        demande = {"culture": "Anacarde", "observation": "Taches noires", "fiches": FICHES}
        self.assertEqual(401, self.client.post("/conseil", json=demande).status_code)
        self.assertEqual(401, self.client.post("/conseil", json=demande, headers={"X-Jeton-Ia": "faux"}).status_code)

    def test_sans_jeton_configure_le_service_refuse_tout(self):
        with mock.patch.dict(os.environ, {"IA_JETON": ""}):
            r = self.client.post("/conseil", json={"culture": "A", "observation": "B"}, headers=JETON)
            self.assertEqual(401, r.status_code)

    def test_un_conseil_est_un_brouillon_qui_cite_les_fiches(self):
        r = self.client.post("/conseil", headers=JETON, json={
            "culture": "Anacarde", "observation": "Taches noires sur les feuilles", "fiches": FICHES,
        })
        self.assertEqual(200, r.status_code, r.text)
        corps = r.json()
        self.assertEqual("brouillon", corps["statut"])
        self.assertEqual([1, 2], corps["fiches_citees"])
        self.assertEqual("faux", corps["modele"])

    def test_un_modele_qui_invente_une_dose_est_rejete(self):
        with mock.patch.object(llm.FauxModele, "generer", return_value="Pulvériser 20 ml par litre de [FICHE-2]."):
            r = self.client.post("/conseil", headers=JETON, json={
                "culture": "Anacarde", "observation": "Taches", "fiches": FICHES,
            })
        corps = r.json()
        self.assertEqual("rejete", corps["statut"])
        self.assertTrue(any("Dose" in m for m in corps["motifs_rejet"]))

    def test_sans_fiche_le_conseil_renvoie_a_l_agronome(self):
        r = self.client.post("/conseil", headers=JETON, json={"culture": "Tomate", "observation": "Feuilles jaunes"})
        corps = r.json()
        self.assertEqual("brouillon", corps["statut"])
        self.assertIn("agronome", corps["texte"])
        self.assertEqual([], corps["fiches_citees"])

    def test_sans_modele_de_vision_tout_diagnostic_est_incertain(self):
        r = self.client.post("/diagnostic", headers=JETON, data={"culture": "Anacarde"},
                             files={"photo": ("f.jpg", b"\xff\xd8\xff\xe0 jpeg", "image/jpeg")})
        self.assertEqual(200, r.status_code, r.text)
        self.assertEqual("incertain", r.json()["statut"])
        self.assertIsNone(r.json()["classe"])

    def test_la_sante_se_lit_sans_jeton_et_ne_donne_pas_le_jeton(self):
        r = self.client.get("/sante")
        self.assertEqual(200, r.status_code)
        self.assertTrue(r.json()["jeton_configure"])
        self.assertNotIn("secret-de-test", r.text)


if __name__ == "__main__":
    unittest.main()
