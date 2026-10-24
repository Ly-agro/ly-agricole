"""Entraîner le modèle de vision (à lancer sur une machine LOUÉE avec carte graphique).

    pip install -r requirements-entrainement.txt
    python -m entrainement.entrainer --culture anacarde --ccmt /donnees/ccmt --ly /donnees/export-ly \\
        --sortie /donnees/modele-anacarde-v1 [--ancien /donnees/modele-anacarde-v0/rapport.json]

Sortie : modele.onnx, classes.json, rapport.json (rappel et précision PAR maladie, matrice de
confusion, décision de remplacement). Le modèle ne s'installe sur le serveur IA que si la
décision est « oui » — et il reste « à confirmer par un agronome » pour chaque diagnostic.

NON EXÉCUTÉ sur le poste de dev (ni PyTorch ni carte graphique). MobileNetV3-small :
petit, rapide, exportable en ONNX et plus tard sur le téléphone (phase 4).
"""

from __future__ import annotations

import argparse
import json
import random
from pathlib import Path

from .outils import Exemple, comparer, lire_ccmt, lire_export_ly, mesures


def main() -> None:
    a = argparse.ArgumentParser(description="Entraîner le modèle de vision de LY AGRICOLE")
    a.add_argument("--culture", required=True, choices=["anacarde", "tomate"])
    a.add_argument("--ccmt", type=Path, help="dossier du jeu brut CCMT (CC BY 4.0, citer les auteurs)")
    a.add_argument("--ly", type=Path, help="dossier de `php artisan ia:exporter-jeu`")
    a.add_argument("--avec-provisoires", action="store_true", help="annotations provisoires (entraînement seulement)")
    a.add_argument("--sortie", type=Path, required=True)
    a.add_argument("--ancien", type=Path, help="rapport.json du modèle en service, pour comparer")
    a.add_argument("--epoques", type=int, default=15)
    a.add_argument("--taille", type=int, default=224)
    args = a.parse_args()

    exemples: list[Exemple] = []
    if args.ccmt:
        exemples += lire_ccmt(args.ccmt, args.culture)
    if args.ly:
        exemples += lire_export_ly(args.ly, args.culture, args.avec_provisoires)
    if not exemples:
        raise SystemExit("Aucune photo : donner --ccmt et/ou --ly.")

    classes = sorted({e.classe for e in exemples})
    entrainement = [e for e in exemples if e.jeu == "entrainement"]
    test = [e for e in exemples if e.jeu == "test"]
    print(f"{args.culture} : {len(entrainement)} photos d'entraînement, {len(test)} de test, classes {classes}")

    import torch  # importés ici : les outils ci-dessus se testent sans PyTorch
    from PIL import Image
    from torch import nn
    from torch.utils.data import DataLoader, Dataset
    from torchvision import models, transforms

    appareil = "cuda" if torch.cuda.is_available() else "cpu"
    if appareil == "cpu":
        print("ATTENTION : pas de carte graphique, l'entraînement sera très long.")

    normaliser = transforms.Normalize([0.485, 0.456, 0.406], [0.229, 0.224, 0.225])
    augmenter = transforms.Compose([
        transforms.RandomResizedCrop(args.taille, scale=(0.6, 1.0)), transforms.RandomHorizontalFlip(),
        transforms.ColorJitter(0.3, 0.3, 0.2), transforms.ToTensor(), normaliser,
    ])
    evaluer = transforms.Compose([transforms.Resize(int(args.taille * 1.15)), transforms.CenterCrop(args.taille), transforms.ToTensor(), normaliser])

    class Photos(Dataset):
        def __init__(self, liste: list[Exemple], tr) -> None:
            self.liste, self.tr = liste, tr

        def __len__(self) -> int:
            return len(self.liste)

        def __getitem__(self, i):
            e = self.liste[i]
            return self.tr(Image.open(e.chemin).convert("RGB")), classes.index(e.classe)

    random.seed(20260930)
    torch.manual_seed(20260930)
    charge_ent = DataLoader(Photos(entrainement, augmenter), batch_size=32, shuffle=True, num_workers=4)
    charge_test = DataLoader(Photos(test, evaluer), batch_size=64, num_workers=4)

    modele = models.mobilenet_v3_small(weights=models.MobileNet_V3_Small_Weights.IMAGENET1K_V1)
    modele.classifier[3] = nn.Linear(modele.classifier[3].in_features, len(classes))
    modele.to(appareil)

    # Classes déséquilibrées : on pèse les rares, pour qu'elles comptent.
    effectifs = torch.tensor([sum(1 for e in entrainement if e.classe == c) for c in classes], dtype=torch.float)
    perte = nn.CrossEntropyLoss(weight=(effectifs.sum() / (len(classes) * effectifs.clamp(min=1))).to(appareil))
    optimiseur = torch.optim.AdamW(modele.parameters(), lr=1e-3, weight_decay=1e-4)
    planning = torch.optim.lr_scheduler.CosineAnnealingLR(optimiseur, T_max=args.epoques)

    for epoque in range(1, args.epoques + 1):
        modele.train()
        total = 0.0
        for x, y in charge_ent:
            x, y = x.to(appareil), y.to(appareil)
            optimiseur.zero_grad()
            l = perte(modele(x), y)
            l.backward()
            optimiseur.step()
            total += l.item() * len(y)
        planning.step()
        print(f"époque {epoque}/{args.epoques} : perte {total / max(1, len(entrainement)):.4f}")

    modele.eval()
    vrais, predits = [], []
    with torch.no_grad():
        for x, y in charge_test:
            sortie = modele(x.to(appareil)).argmax(1).cpu().tolist()
            vrais += [classes[i] for i in y.tolist()]
            predits += [classes[i] for i in sortie]

    rapport = mesures(vrais, predits, classes)
    ancien = json.loads(args.ancien.read_text(encoding="utf-8")) if args.ancien else None
    remplacer, motifs = comparer(rapport, ancien)
    rapport.update({
        "culture": args.culture, "remplacer": remplacer, "motifs": motifs,
        "sources": sorted({e.source for e in exemples}),
        "attribution": "Jeu CCMT (Mensah Kwabena P. et al., 2023, DOI 10.17632/bwh3zbpkpv.1), CC BY 4.0" if args.ccmt else None,
        "avertissement": "Modèle d'essai : diagnostics « à confirmer par un agronome ».",
    })

    args.sortie.mkdir(parents=True, exist_ok=True)
    (args.sortie / "classes.json").write_text(json.dumps(classes, ensure_ascii=False, indent=2), encoding="utf-8")
    (args.sortie / "rapport.json").write_text(json.dumps(rapport, ensure_ascii=False, indent=2), encoding="utf-8")
    modele.to("cpu")
    torch.onnx.export(modele, torch.randn(1, 3, args.taille, args.taille), str(args.sortie / "modele.onnx"),
                      input_names=["image"], output_names=["scores"], dynamic_axes={"image": {0: "lot"}, "scores": {0: "lot"}})

    print(json.dumps({c: m for c, m in rapport["par_classe"].items()}, ensure_ascii=False, indent=2))
    print("Remplacer l'ancien modèle :", "OUI" if remplacer else "NON")
    for m in motifs:
        print(" -", m)


if __name__ == "__main__":
    main()
