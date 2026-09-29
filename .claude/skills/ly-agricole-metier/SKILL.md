---
name: ly-agricole-metier
description: Vocabulaire et règles de la filière agricole ivoirienne pour LY AGRICOLE — campagne d'anacarde, prix bord-champ, qualité des noix (KOR, humidité, grainage), pisteurs, prêts de campagne remboursés en kilos, contrat de campagne avec les investisseurs. À charger dès qu'on nomme, modélise ou calcule quelque chose du métier (producteur, parcelle, prêt, achat, lot, campagne, rendement), ou quand une règle métier semble ambiguë — même si la demande est formulée simplement (« ajoute le prix au kilo », « le producteur a livré »).
---

# Métier de LY AGRICOLE

LY AGRICOLE (Côte d'Ivoire, créée en 2026, issue de LUNA YEO AGRICOLE) : exploitation
agricole, négoce et commercialisation — anacarde (noix de cajou) d'abord, puis beurre de
karité, tomate et autres. Mission écrite : promouvoir, moderniser l'agriculture
ivoirienne et créer des emplois ; vision : transformer localement et bâtir un réseau
de commercialisation. Slogan : « Cultiver – Élever – Durer ».

## Vocabulaire

| Terme | Sens | Dans le code |
| --- | --- | --- |
| Campagne | Saison de commercialisation d'un produit. Pour l'anacarde, la récolte a lieu en saison sèche (environ février à mai). La campagne **du contrat** démarre le 21/12/2026. | `Campagne` (code `2026-2027`) |
| Prix bord-champ | Prix minimum d'achat au producteur fixé chaque campagne par l'État pour l'anacarde. Ne jamais coder une valeur en dur : c'est un paramètre de la campagne. | `campagnes.prix_officiel_kg_fcfa` |
| Pisteur | Intermédiaire qui collecte dans les villages pour un acheteur, payé à la commission. | `Pisteur` |
| KOR (Kernel Outturn Ratio) | Rendement en amande, exprimé en **livres d'amandes par sac de 80 kg** de noix brutes. Critère n°1 du prix à l'export. | `kor_centieme_lbs` (ex. 48,50 lbs → 4850) |
| Grainage | Nombre de noix par kilo : moins il y en a, plus les noix sont grosses et chères. | `grainage_noix_kg` |
| Humidité | Taux d'humidité des noix ; trop humides, elles moisissent et perdent du poids en stock. | `humidite_pour_mille` (8 % → 80) |
| Prêt de campagne / avance | Argent ou intrants remis au producteur avant la récolte, remboursé **en argent ou en kilos livrés**. | `Pret`, `Remboursement` (`especes` / `nature`) |
| Intrants | Engrais, sacs, bâches, produits de traitement. | `Intrant` |
| Lot | Quantité homogène d'un produit suivie de l'achat à la revente ; porte ses coûts. | `Lot` |
| Warrantage | Stock mis en gage pour garantir un financement (phase 2). | — |

Les faits datés ou chiffrés de la filière (prix officiel, calendrier exact, règles de
l'agrément acheteur du Conseil du Coton et de l'Anacarde) **changent chaque année** :
ne jamais les inventer ni les coder en dur. Les demander au responsable projet et les
mettre en paramètre.

## Le contrat de campagne (source des règles financières)

Document « CONTRAT CAMPAGNE LY AGRICOLE » : des investisseurs apportent des fonds
(jusqu'à 10 M FCFA dans l'exemple) pour une campagne ; LY peut faire un apport propre ;
le résultat net est partagé **40 % investisseurs / 60 % LY**, les pertes au prorata des
apports. Articles qui touchent directement le code :

| Article | Ce qu'il impose au logiciel |
| --- | --- |
| 5 | Les fonds sont sur un compte dédié → compte de trésorerie propre à la campagne |
| 6.3 | Remboursement intégral si la campagne est annulée → suivre chaque apport |
| 9 / 9.2 | Affectation exclusive des fonds ; avances aux producteurs autorisées → module prêts, rattachement de chaque dépense à la campagne |
| 10.1 / 10.2 | Résultat net = recettes **encaissées** − charges ; commissions et personnel imputables → rattachement des dépenses, plafonds |
| 10.3 | Charges exclues → catégories `exclue_fonds_campagne` bloquées |
| 11 | Stock invendu vendu jusqu'au 30/09/2027 ou repris par LY → valorisation du stock, trace de la décision |
| 12 à 14 | Calcul des quotes-parts → à coder exactement comme écrit, avec l'exemple de l'art. 14 en test |
| 17.3 | Opérations avec des parties liées → accord écrit, pas une simple information |
| 18 | Point d'étape et rapport final → rapports générés depuis les données |

Faiblesses relevées lors de la relecture (à ne pas aggraver dans le code) : apport de
LY facultatif, stock invendu arbitré par LY seule, « faute de gestion » non définie,
calendrier du rapport final (31 août) antérieur à la fin de vente du stock (30 sept.).
Si le code doit trancher l'une d'elles, **poser la question** au lieu de choisir.

## Plusieurs activités, une plateforme centrale

Selon le responsable projet (2026-09-29), LY AGRICOLE **regroupe plusieurs activités** —
agriculture (anacarde, karité, tomate…), élevage, pisciculture… — et a besoin d'un site
central pour toutes (« tout est pareil »). Le projet de maraîchage, poules pondeuses et
jus d'hibiscus noté ailleurs sur son compte (près d'Arles, en France) en ferait donc
partie — lieu et calendrier à confirmer (question 28).

Même pour le négoce, **l'anacarde n'est pas seul** : plusieurs produits peuvent entrer
selon la période et le prix (précision du responsable projet, 2026-09-29). Plusieurs
campagnes de produits différents peuvent donc être ouvertes en même temps ; tout écran
ou calcul part de la campagne (donc du produit), jamais d'un produit supposé.

Conséquences pour le code, **en attendant le cahier des charges de ces activités**
(question ouverte n° 28) :

- ne rien coder en dur pour l'anacarde : les produits, campagnes et magasins restent
  des référentiels ;
- ne pas coder de module élevage ou pisciculture (lots d'animaux, bassins, mortalité,
  aliment) sans ses règles écrites et validées : poser la question.
