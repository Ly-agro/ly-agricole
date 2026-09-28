# Questions ouvertes

À poser au responsable projet. **Bloquante** = la semaine indiquée ne peut pas
commencer sans la réponse. Quand une question est tranchée, noter la réponse et la
date, et la reporter dans le document concerné (cahier, décisions, modèle).

| # | Question | Bloque | Réponse |
| --- | --- | --- | --- |
| 1 | Combien de producteurs et de prêts pour la première campagne, et dans quelles zones ? | sem. 1 (référentiels) | |
| 2 | Les prêts sont-ils versés en espèces, Mobile Money, intrants, ou un mélange ? | **sem. 4** | |
| 3 | Remboursement en kilos : à quel prix — prix officiel du jour de livraison, ou prix fixé au moment du prêt ? | **sem. 4** | |
| 4 | Y a-t-il un intérêt ou une marge sur les prêts ? Sous quelle forme ? | **sem. 4** | |
| 5 | Seuils : au-dessus de quel montant une dépense, un achat ou un prêt demande une validation ? | sem. 3 | |
| 6 | LY achète-t-elle aussi à des producteurs sans prêt ? Passe-t-elle par des pisteurs payés à la commission ? | sem. 6 | |
| 7 | Combien de magasins de stockage, et où ? | sem. 6 | |
| 8 | Quels critères de qualité sont mesurés à l'achat (humidité, KOR, grainage) et avec quel matériel ? | sem. 6 | |
| 9 | Les agents ont-ils des téléphones Android, et lesquels (modèle, version) ? | **sem. 8** | |
| 10 | Serveur chez LY ou hébergement externe ? (l'IA locale peut rester chez LY même si l'application est hébergée dehors) | sem. 12 | |
| 11 | Quel fournisseur SMS (et WhatsApp plus tard) ? | sem. 11 | |
| 12 | Quelles langues locales pour les messages aux producteurs ? | phase 2 | |
| 13 | Un agronome est-il disponible pour le référentiel des traitements et la validation des diagnostics ? | phase 3 | |
| 14 | Qui tient la comptabilité (cabinet, logiciel) et sous quel format veut-il les données ? | phase 3 | |
| 15 | Quels acheteurs pour la revente, et paient-ils à la livraison ou à terme ? | phase 2 | |
| 16 | Obligations envers le Conseil du Coton et de l'Anacarde (agrément, déclarations) ? | phase 2 | |
| 17 | Budget et délai acceptés pour la phase 1 ? | — | |
| 18 | Nom de domaine : `ylagro.com` est-il réservé ? (le nom de marque est LY AGRICOLE) | sem. 12 | |
| 19 | Qui a le droit de quoi ? Proposition en place (2026-09-28) : seul l'**admin** gère les comptes ; l'admin **ne** valide **ni** prêt **ni** dépense (séparation des tâches) ; un utilisateur a **un seul** rôle ; journal lisible par admin et direction ; zones, villages, produits, magasins, points de collecte gérés par admin et direction ; **campagnes (prix officiel) et seuils : direction seule**. Faut-il qu'une personne cumule deux rôles (ex. direction + comptable) ? Changer un seuil doit-il être validé par une 2ᵉ personne (baisser un contrôle) ? Droits définis dans `AppServiceProvider::definirLesDroits()`, figés par `AccesReferentielsTest`. | sem. 3 | |
| 20 | Une seule campagne **ouverte** à la fois par produit : est-ce juste (l'appli terrain télécharge « la » campagne ouverte) ? Et la clôture : qui la demande, qui la valide ? | sem. 4 | |
