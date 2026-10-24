"""Outils d'entraînement (sans PyTorch) : lecture des jeux, classes, répartition, mesures."""

import csv
import tempfile
import unittest
from pathlib import Path

from entrainement.outils import ClasseInconnue, comparer, jeu_fixe, lire_ccmt, lire_export_ly, mesures


def photo(chemin: Path) -> None:
    chemin.parent.mkdir(parents=True, exist_ok=True)
    chemin.write_bytes(b"\xff\xd8 jpeg")


class LectureTest(unittest.TestCase):
    def setUp(self):
        self.dossier = Path(tempfile.mkdtemp())

    def test_ccmt_garde_la_culture_demandee_et_traduit_les_classes(self):
        racine = self.dossier / "Raw Data" / "CCMT Dataset"
        for i in range(3):
            photo(racine / "Cashew" / "gumosis" / f"g{i}.jpg")
            photo(racine / "Cashew" / "healthy" / f"h{i}.JPG")
            photo(racine / "Maize" / "fall armyworm" / f"m{i}.jpg")  # autre culture : ignorée
        (racine / "Cashew" / "healthy" / "notes.txt").write_text("pas une photo")

        ex = lire_ccmt(self.dossier, "anacarde")
        self.assertEqual(6, len(ex))
        self.assertEqual({"gommose", "sain"}, {e.classe for e in ex})
        self.assertTrue(all(e.source == "ccmt" and e.culture == "anacarde" for e in ex))

    def test_une_classe_inconnue_arrete_au_lieu_de_deviner(self):
        photo(self.dossier / "Cashew" / "powdery mildew" / "x.jpg")
        with self.assertRaises(ClasseInconnue):
            lire_ccmt(self.dossier, "anacarde")

    def test_l_export_ly_garde_son_jeu_et_ne_prend_les_provisoires_que_sur_demande(self):
        with open(self.dossier / "manifeste.csv", "w", newline="", encoding="utf-8") as f:
            w = csv.writer(f, delimiter=";")
            w.writerow(["fichier", "culture", "classe", "jeu", "statut", "valide_le", "source"])
            w.writerow(["anacarde/anthracnose/a.jpg", "anacarde", "anthracnose", "test", "confirme", "2027-01-05", "agronome"])
            w.writerow(["anacarde/sain/b.jpg", "anacarde", "sain", "entrainement", "incertain", "2026-10-01", "provisoire:claude"])
            w.writerow(["tomate/sain/c.jpg", "tomate", "sain", "entrainement", "confirme", "2027-01-05", "agronome"])

        sans = lire_export_ly(self.dossier, "anacarde")
        self.assertEqual([("anthracnose", "test", "ly")], [(e.classe, e.jeu, e.source) for e in sans])
        avec = lire_export_ly(self.dossier, "anacarde", avec_provisoires=True)
        self.assertIn(("sain", "entrainement", "ly-provisoire"), [(e.classe, e.jeu, e.source) for e in avec])

    def test_la_repartition_est_fixe_et_d_environ_un_dixieme(self):
        cles = [f"ccmt:Cashew/healthy/{i}.jpg" for i in range(2000)]
        self.assertEqual([jeu_fixe(c) for c in cles], [jeu_fixe(c) for c in cles])
        part = sum(jeu_fixe(c) == "test" for c in cles) / len(cles)
        self.assertTrue(0.07 < part < 0.13, part)


class MesuresTest(unittest.TestCase):
    CLASSES = ["anthracnose", "gommose", "sain"]

    def test_rappel_et_precision_par_classe_avec_matrice(self):
        vrais = ["anthracnose"] * 4 + ["gommose"] * 2 + ["sain"] * 4
        predits = ["anthracnose", "anthracnose", "anthracnose", "sain", "sain", "gommose", "sain", "sain", "sain", "sain"]
        m = mesures(vrais, predits, self.CLASSES)
        self.assertEqual(0.75, m["par_classe"]["anthracnose"]["rappel"])
        self.assertEqual(0.5, m["par_classe"]["gommose"]["rappel"])
        self.assertEqual(1.0, m["par_classe"]["sain"]["rappel"])
        self.assertEqual([[3, 0, 1], [0, 1, 1], [0, 0, 4]], m["matrice_confusion"])

    def test_un_recul_sur_une_seule_maladie_refuse_le_remplacement(self):
        ancien = {"par_classe": {"anthracnose": {"rappel": 0.90, "effectif_test": 50}, "gommose": {"rappel": 0.80, "effectif_test": 30}}, "rappel_moyen": 0.85}
        nouveau = {"par_classe": {"anthracnose": {"rappel": 0.97, "effectif_test": 50}, "gommose": {"rappel": 0.70, "effectif_test": 30}}, "rappel_moyen": 0.835}
        ok, motifs = comparer(nouveau, ancien)
        self.assertFalse(ok)
        self.assertTrue(any("gommose" in m for m in motifs))

    def test_trop_peu_de_photos_de_test_n_est_pas_un_bon_resultat(self):
        nouveau = {"par_classe": {"gommose": {"rappel": 1.0, "effectif_test": 5}}, "rappel_moyen": 1.0}
        ok, motifs = comparer(nouveau, None)
        self.assertFalse(ok)
        self.assertIn("peu fiable", motifs[0])


if __name__ == "__main__":
    unittest.main()
