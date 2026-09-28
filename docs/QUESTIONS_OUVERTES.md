# Questions ouvertes

À poser au responsable projet. **Bloquante** = la semaine indiquée ne peut pas
commencer sans la réponse. Quand une question est tranchée, noter la réponse et la
date, et la reporter dans le document concerné (cahier, décisions, modèle).

| # | Question | Bloque | Réponse |
| --- | --- | --- | --- |
| 1 | Combien de producteurs et de prêts pour la première campagne, et dans quelles zones ? | sem. 1 (référentiels) | |
| 2 | Les prêts sont-ils versés en espèces, Mobile Money, intrants, ou un mélange ? | **sem. 4** | |
| 3 | Remboursement en kilos : à quel prix — prix officiel du jour de livraison, ou prix fixé au moment du prêt ? *En attendant (2026-10-31) : le code offre les deux règles (« prix de l'achat du jour » / « prix de référence fixé dans le prêt ») ; la direction choisit dans Paramètres ; **tant qu'elle n'a pas choisi, aucun achat ne rembourse un prêt**. La base locale de dev a « prix de l'achat du jour » comme valeur d'essai.* | **sem. 4** | |
| 4 | Y a-t-il un intérêt ou une marge sur les prêts ? Sous quelle forme ? | **sem. 4** | |
| 5 | Seuils : au-dessus de quel montant une dépense, un achat ou un prêt demande une validation ? | sem. 3 | |
| 6 | LY achète-t-elle aussi à des producteurs sans prêt ? Passe-t-elle par des pisteurs payés à la commission ? *En attendant (2026-10-31) : achats possibles avec ou sans prêt, et à un pisteur ou une coopérative comme **vendeurs** ; la **commission** d'un pisteur intermédiaire n'est pas modélisée (combien, par kg ou en %, payée quand ?).* | sem. 6 | |
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
| 19 | Qui a le droit de quoi ? Proposition en place (2026-09-28) : seul l'**admin** gère les comptes ; l'admin **ne** valide **ni** prêt **ni** dépense (séparation des tâches) ; un utilisateur a **un seul** rôle ; journal lisible par admin et direction ; zones, villages, produits, magasins, points de collecte gérés par admin et direction ; **campagnes (prix officiel) et seuils : direction seule** ; producteurs (données personnelles) : saisis par direction et agents, lus aussi par comptable et agronome, **ni admin ni investisseur** ; trésorerie (comptes, entrées, virements, avances, contre-passations, catégories) et validation des dépenses : direction et comptable ; un **agent** saisit ses dépenses depuis **sa** caisse seulement ; aucun compte ne passe en négatif, banque comprise (découvert autorisé ?). Faut-il qu'une personne cumule deux rôles (ex. direction + comptable) ? Changer un seuil doit-il être validé par une 2ᵉ personne (baisser un contrôle) ? Droits définis dans `AppServiceProvider::definirLesDroits()`, figés par `AccesReferentielsTest`. | sem. 3 | |
| 23 | Quelles **catégories de dépense** exactement, et lesquelles sont **exclues des fonds de campagne** (contrat art. 10.3) ? Codes SYSCOHADA à fournir par le comptable. Aujourd'hui : saisies à la main, aucune fournie par défaut. | sem. 3 | |
| 21 | **Consentement des producteurs** (loi 2013-450, ARTCI) : quel texte exact leur lire, dans quelles langues ; faut-il une trace écrite (signature, empreinte) en plus de la case cochée par l'agent ? LY doit-elle faire une déclaration à l'ARTCI ? Texte provisoire dans le formulaire (2026-10-03). | sem. 11 (pilote) | |
| 22 | Un **agent** voit-il tous les producteurs, ou seulement ceux de sa zone ? (aujourd'hui : tous ; l'appli terrain ne téléchargera que sa zone, semaine 8) | sem. 8 | |
| 20 | Une seule campagne **ouverte** à la fois par produit : est-ce juste (l'appli terrain télécharge « la » campagne ouverte) ? Et la clôture : qui la demande, qui la valide ? | sem. 4 | |
