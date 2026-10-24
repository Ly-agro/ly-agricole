# LY AGRICOLE — Réponses au questionnaire

> Version de travail — 29 septembre 2026
> Les réponses ci-dessous reprennent les décisions déjà prises dans l'état du projet. Lorsqu'aucune décision du responsable projet n'est documentée, la réponse est une **proposition de décision** destinée à débloquer le développement. Elle devra être validée avant engagement contractuel ou réglementaire.

---

## 1. Combien de producteurs et de prêts pour la première campagne, et dans quelles zones ?

**Réponse proposée :**

Pour le pilote, commencer avec un périmètre volontairement limité :

* 1 à 2 agents de terrain ;
* un premier groupe de producteurs représentatif ;
* une ou deux zones de collecte ;
* un volume de prêts suffisamment faible pour permettre un contrôle manuel parallèle.

Le nombre définitif de producteurs, de prêts et les zones exactes restent à renseigner par la direction.

**Décision technique :** ne pas coder de limite fixe dans l'application. Ces valeurs doivent être configurables.

---

## 2. Les prêts sont-ils versés en espèces, Mobile Money, intrants, ou mélange ?

**Réponse :**

Les trois formes sont possibles :

* espèces ;
* Mobile Money ;
* intrants.

Un même prêt peut combiner plusieurs formes de décaissement.

Cette règle est déjà retenue dans l'état actuel du projet.

---

## 3. Comment rembourser un prêt en kilos ?

**Réponse :**

Deux règles sont nécessaires :

1. prix fixé au moment du prêt ;
2. prix applicable au jour de la livraison.

La règle doit être sélectionnable dans les paramètres de la campagne ou du prêt.

**En attendant le choix de la direction, aucun achat ne doit automatiquement rembourser un prêt.**

La plateforme doit conserver la règle choisie avec le prêt afin que le calcul historique reste traçable.

---

## 4. Y a-t-il un intérêt ou une marge sur les prêts ?

**Réponse :**

Non.

Le producteur rembourse la valeur reçue.

Aucun intérêt ni marge de crédit ne doit être automatiquement ajouté au montant du prêt.

---

## 5. Au-dessus de quel montant faut-il une validation supplémentaire ?

**Réponse proposée :**

Tant que la direction n'a pas fixé les seuils définitifs :

* toute dépense nécessite une validation par une autre personne ;
* tout achat nécessite une validation ;
* tout prêt nécessite une validation ;
* toute vente nécessite une validation.

Après validation des seuils, la configuration devra permettre :

* seuil normal ;
* double validation au-dessus du seuil ;
* éventuellement validation spécifique selon le type d'opération.

---

## 6. LY achète-t-elle sans prêt et utilise-t-elle des pisteurs ?

**Réponse :**

Oui, LY peut acheter :

* auprès de producteurs ayant reçu un prêt ;
* auprès de producteurs n'ayant pas reçu de prêt.

Les achats via pisteurs sont possibles.

**Point restant à définir :** la commission des pisteurs et son mode de calcul/paiement.

**Proposition :** prévoir dès maintenant le champ `pisteur` et la commission dans le modèle d'achat, même si le calcul automatique de commission est activé ultérieurement.

---

## 7. Combien de magasins de stockage et où ?

**Réponse :**

L'information n'est pas encore fournie.

La plateforme doit néanmoins supporter plusieurs magasins.

Chaque magasin doit avoir :

* un identifiant ;
* un nom ;
* une localisation ;
* une capacité éventuelle ;
* un responsable ;
* les mouvements de stock associés.

Le nombre et les emplacements réels seront renseignés par LY.

---

## 8. Quels critères de qualité à l'achat et avec quel matériel ?

**Réponse proposée :**

Pour l'anacarde, conserver au minimum :

* poids ;
* KOR ;
* taux d'humidité ;
* nombre de noix au kilo ;
* état/qualité du lot.

Le matériel réellement disponible reste à confirmer.

**Proposition :** les critères doivent être configurables par produit afin de ne pas enfermer la plateforme dans l'anacarde.

---

## 9. Quels téléphones Android utilisent les agents ?

**Réponse :**

Information encore manquante.

Avant le pilote, chaque téléphone destiné au terrain doit être identifié :

* marque/modèle ;
* version Android ;
* RAM ;
* espace disponible ;
* GPS ;
* appareil photo ;
* Bluetooth ;
* autonomie.

Le développement ne doit pas considérer Chrome desktop comme preuve de compatibilité mobile.

---

## 10. Serveur chez LY ou hébergement externe ?

**Réponse proposée :**

Pour le pilote et la production :

* application Laravel accessible en ligne ;
* HTTPS obligatoire ;
* sauvegardes automatiques ;
* accès administrateur sécurisé.

L'emplacement exact du serveur reste à choisir.

**IA :** conserver la possibilité de faire tourner Ollama localement chez LY, conformément à l'architecture prévue.

---

## 11. Quel fournisseur SMS et plus tard WhatsApp ?

**Réponse :**

Le fournisseur n'est pas encore choisi.

Actuellement, les SMS sont uniquement enregistrés dans un journal et aucun message réel n'est envoyé.

**Proposition pour le pilote :**

choisir un fournisseur SMS disposant d'une API fiable en Côte d'Ivoire et intégrer une abstraction de fournisseur afin de pouvoir changer de prestataire sans modifier le métier.

WhatsApp sera traité dans une étape ultérieure.

---

## 12. Quelles langues locales pour les messages ?

**Réponse :**

La langue française constitue le premier niveau.

Les langues locales doivent être déterminées selon les zones et les producteurs concernés.

Le système doit donc prévoir une préférence de langue par producteur.

Pour les messages courts :

* français simple ;
* sans accent si nécessaire pour les SMS ;
* pictogrammes lorsque le canal le permet ;
* message vocal à prévoir ultérieurement pour les langues locales.

Les langues définitives restent à confirmer.

---

## 13. Un agronome est-il disponible ?

**Réponse :**

La disponibilité définitive reste à confirmer.

Elle est cependant indispensable avant de considérer le module de diagnostic IA comme fiable.

L'agronome doit notamment :

* valider le référentiel des traitements ;
* confirmer/corriger les diagnostics IA ;
* valider les recommandations ;
* participer à la constitution du jeu de données d'entraînement.

---

## 14. Qui tient la comptabilité et sous quel format ?

**Réponse :**

La plateforme doit néanmoins prévoir :

* export comptable ;
* grand livre ;
* balance ;
* compte de résultat par campagne ;
* plan comptable SYSCOHADA révisé.

---

## 15. Quels acheteurs pour la revente et quelles modalités de paiement ?

**Réponse :**

Une vente peut être encaissée :

* en une fois ;
* en plusieurs fois.

Le montant restant à encaisser doit rester visible.

Les types d'acheteurs doivent être configurables :

* exportateur ;
* usine ;
* grossiste ;
* autre acheteur professionnel.

La liste réelle des acheteurs reste à fournir.

---

## 16. Obligations envers le Conseil du Coton et de l'Anacarde ?

**Réponse :**

La plateforme doit cependant prévoir un module permettant de conserver :

* agréments ;
* déclarations ;
* volumes déclarés ;
* périodes ;
* justificatifs ;
* historique des déclarations.

---

## 17. Quel budget et quel délai pour la phase 1 ?

**Réponse proposée :**

La priorité est la disponibilité du socle avant le début de campagne du **novembre 2026**.

La phase 1 doit donc prioriser :

1. producteurs ;
2. parcelles ;
3. prêts ;
4. remboursements ;
5. achats ;
6. stock ;
7. dépenses ;
8. caisses ;
9. Mobile Money ;
10. application terrain hors ligne.

Le budget définitif reste à fournir par la direction.

---

# Contrôles et règles métier

## 18. Le domaine ylagro.com est-il réservé ?

**Réponse :**

oui.

---

## 19. Qui a le droit de faire quoi ?

**Réponse proposée :**

Séparation stricte des responsabilités.

Rôles principaux :

* Direction / responsable projet ;
* Agent de terrain ;
* Agronome / conseiller ;
* Comptable ;
* Investisseur ;
* Producteur.

Un utilisateur possède actuellement un seul rôle.

Principe obligatoire :

> la personne qui saisit une opération ne doit pas être la seule personne à pouvoir la valider.

La modification des paramètres sensibles doit également être journalisée.

---

## 20. Une seule campagne ouverte par produit ?

**Réponse actuelle :**

Oui.

Une seule campagne peut être ouverte simultanément par produit.

La clôture de campagne doit être contrôlée et auditée.

**Admin :** qui demande et qui valide la clôture.

---

## 21. Consentement des producteurs et données personnelles

**Réponse provisoire :**

Un texte de consentement provisoire et une case de confirmation par l'agent sont actuellement prévus.

Le dispositif définitif doit préciser :

* les informations collectées ;
* la finalité ;
* GPS ;
* photographie ;
* téléphone ;
* identité ;
* durée de conservation ;
* droits du producteur ;
* retrait du consentement ;
* procédure applicable auprès de l'autorité compétente.

Le texte définitif doit être validé avant production.

---

## 22. Un agent voit-il tous les producteurs ?

**Réponse actuelle :**

Oui.

Les agents voient actuellement tous les producteurs.

Cette règle pourra être restreinte ultérieurement par zone si l'organisation opérationnelle le nécessite.

---

## 23. Quelles catégories de dépenses et quels codes SYSCOHADA ?

**Réponse :**


La plateforme doit éviter de supprimer les possibilités de configuration et permettre d'associer :

* catégorie métier ;
* compte SYSCOHADA ;
* éligibilité aux fonds de campagne ;
* justificatif obligatoire ;
* niveau de validation.

---

## 24. Quel contenu pour les SMS de confirmation ?

**Réponse actuelle :**

Les SMS doivent être :

* en français ;
* courts ;
* sans accents ;
* un SMS par achat, versement ou remboursement.

Le contenu définitif doit être validé.

Le besoin d'un SMS spécifique pour les intrants remis à crédit reste à confirmer.

---

## 25. Les connexions terrain doivent-elles expirer ?

**Réponse actuelle :**

Pas d'expiration automatique.

Un compte désactivé ne peut plus envoyer de données.

**Amélioration recommandée :**

prévoir ultérieurement :

* révocation d'un appareil ;
* liste des appareils associés ;
* dernière synchronisation ;
* possibilité de désactiver à distance un téléphone perdu.

---

## 26. Comment construire et tester l'APK ?

**Réponse :**

La construction Android reste à faire.

Avant le pilote :

1. installer/configurer Android Studio ;
2. générer l'APK ;
3. installer sur un vrai téléphone ;
4. tester GPS ;
5. tester appareil photo ;
6. tester mode avion ;
7. tester mise en veille ;
8. tester redémarrage ;
9. tester synchronisation après reconnexion ;
10. tester les permissions Android.

Le test Chrome ne suffit pas pour valider l'application terrain.

---

# Questions métier supplémentaires

## 27. Serveur en ligne ou local ?

**Décision déjà prise :**

L'application doit être en ligne et accessible en HTTPS dès le pilote.

Il reste uniquement à choisir l'hébergement.

---

## 28. Quelles activités agricoles doivent être couvertes ?

**Réponse actuelle :**

La phase actuelle ne contient pas de module élevage ou autre culture.

L'anacarde constitue le cas métier principal actuellement documenté.

L'architecture doit cependant rester extensible afin de pouvoir ajouter :

* agriculture ;
* élevage ;
* autres cultures.

Aucun module supplémentaire ne doit être développé en dur.

---

## 29. Comment gérer le budget de campagne ?

**Réponse actuelle :**

La direction fixe le budget.

La comptabilité dispose d'un accès en lecture.

Toute modification doit conserver :

* ancienne valeur ;
* nouvelle valeur ;
* utilisateur ;
* date ;
* motif éventuel.

L'historique ne doit jamais être écrasé.

---

## 30. Quelles pratiques sont suivies lors des visites ?

**Réponse actuelle :**

Huit pratiques communes sont actuellement retenues.

L'agronome peut également saisir ses propres visites.

Le système doit conserver :

* date ;
* parcelle ;
* agent/agronome ;
* pratiques observées ;
* photos ;
* observations ;
* position GPS lorsque disponible.

---

## 31. Comment calculer le rendement ?

**Réponse actuelle :**

Le rendement porte sur **tous les achats validés de la campagne**, et pas uniquement sur les kilos servant au remboursement du prêt.

Formule de base :

> rendement = kilos achetés/livrés ÷ hectares concernés

La plateforme doit néanmoins distinguer :

* production ;
* achats ;
* remboursements en nature ;
* ventes.

---

## 32. Comment calculer le résultat de campagne ?

**Réponse provisoire :**

* arrondi au franc le plus proche ;
* les avances aux producteurs non remboursées sont affichées séparément ;
* elles ne sont pas incluses dans le résultat selon la règle actuellement retenue.

Restent à définir :

* traitement d'une perte supérieure aux fonds disponibles ;
* valorisation du stock invendu ;
* traitement précis des pertes.

Ces règles doivent être validées avant le rapport final aux investisseurs.

---

# Rapports et exports

## 33. CSV ou XLSX ?

**Réponse proposée :**

Le CSV peut rester disponible pour les exports techniques.

Pour les utilisateurs métier, prévoir également un véritable fichier **XLSX** lorsque le besoin est confirmé.

Le système peut donc proposer :

* CSV ;
* XLSX ;
* PDF.

---

## 34. À partir de quel écart de poids déclencher une alerte ?

**Réponse actuelle :**

Tout écart est signalé.

L'alerte doit indiquer :

* poids attendu ;
* poids constaté ;
* différence absolue ;
* différence en pourcentage ;
* étape concernée ;
* utilisateur ;
* motif renseigné.

Un seuil de criticité pourra être ajouté ultérieurement.

---

# Décisions prioritaires avant le pilote

Les points suivants doivent être considérés comme **bloquants avant le pilote terrain** :

1. choisir les téléphones Android ;
2. construire l'APK ;
3. tester l'application sur téléphone réel ;
4. tester hors ligne ;
5. tester GPS ;
6. tester appareil photo ;
7. choisir l'hébergement ;
8. confirmer le domaine ;
9. choisir le fournisseur SMS ;
10. valider le consentement producteur ;
11. valider les règles de remboursement ;
12. valider les seuils de validation financière ;
13. confirmer les zones et le nombre de producteurs du pilote ;
14. confirmer le matériel de mesure de qualité ;
15. confirmer la disponibilité de l'agronome.

---

# Ce qui peut attendre après le pilote

Ne pas bloquer la campagne sur les fonctionnalités suivantes :

* diagnostic IA complet ;
* diagnostic hors ligne sur téléphone ;
* qualité des noix par photo ;
* balance Bluetooth ;
* API Mobile Money complète ;
* comptabilité SYSCOHADA complète ;
* groupes et caution solidaire ;
* warrantage ;
* véhicules et matériel ;
* indicateurs d'impact avancés ;
* ventes en devises ;
* images Sentinel-2 ;
* transformation ;
* WhatsApp avancé.

La priorité reste le fonctionnement fiable du cycle :

**Producteur → Parcelle → Prêt → Décaissement → Achat/Livraison → Stock → Dépense → Trésorerie → Remboursement → Rapport.**





Oui. Pour ces deux questions, je te conseille de **ne pas laisser des règles implicites dans le code**. Il faut inscrire une décision métier claire dans le questionnaire, tout en conservant la prudence actuelle.

### Question 35 — Note de fiabilité / plafond proposé

Le cahier des charges dit seulement que la plateforme doit **proposer un plafond pour la campagne suivante et que la direction garde la décision**. Il ne définit effectivement aucune formule. 

La règle actuellement codée est prudente : plus gros prêt soldé à temps, plafonné par le paramètre producteur, aucune proposition sans historique favorable, et aucune augmentation. Pour répondre au questionnaire, je formulerais ainsi :

## 35. Note de fiabilité du producteur et plafond proposé

**Décision retenue : conserver une règle prudente en phase 2, sans coefficient d'augmentation automatique.**

Le système propose comme plafond de la campagne suivante :

> **le plus gros prêt précédemment soldé à l'échéance ou avant**, dans la limite du plafond maximum configuré par producteur dans les Paramètres.

Aucun plafond proposé automatiquement dans les cas suivants :

* aucun historique de prêt ;
* aucun prêt précédemment soldé à temps ;
* présence d'un prêt actuellement en retard ;
* historique insuffisant pour établir une référence fiable.

### Pas d'augmentation automatique

Aucune règle du type `+10 %`, `+20 %`, etc. n'est appliquée tant que la direction n'a pas validé une politique de progression.

Le système est donc un **outil de proposition**, et non un moteur automatique d'octroi de crédit.

### Critères complémentaires

Pour la première version, le calcul du plafond proposé ne doit pas être augmenté automatiquement selon :

* le rendement ;
* le volume de livraisons ;
* le taux de remboursement ;
* la progression entre campagnes.

Ces données peuvent toutefois être affichées à la direction comme éléments d'aide à la décision.

Une évolution ultérieure pourra introduire une formule validée par LY, par exemple avec :

* taux minimal de remboursement ;
* nombre de campagnes réussies ;
* rendement ;
* régularité des livraisons ;
* retards courts ou longs ;
* historique des pertes.

Aucune formule ni aucun coefficient ne doit être inventé ou activé sans validation métier.

### Qui peut voir la note ?

La note et le plafond proposé sont visibles par :

* la direction ;
* le comptable lorsque son rôle nécessite l'accès aux informations financières.

Ils ne sont pas visibles par défaut par :

* l'agent de terrain ;
* le producteur.

### Information du producteur

Le producteur doit pouvoir connaître les informations qui ont une conséquence sur ses opérations et exercer ses droits sur ses données personnelles, mais la **note interne de risque et la proposition de plafond ne sont pas communiquées automatiquement** tant que LY n'a pas défini sa politique d'information.

Le système doit néanmoins conserver la traçabilité de :

* la note calculée ;
* les données utilisées ;
* la date du calcul ;
* le plafond proposé ;
* la décision finale de la direction ;
* l'utilisateur ayant validé ou modifié le plafond.

**Statut : règle provisoire validée pour le fonctionnement technique ; politique définitive de notation et d'information du producteur à valider avant généralisation.**

Cette approche est cohérente avec le document d'état, qui classe justement la **note de fiabilité** parmi les éléments de phase 2 à faire valider. 

---

### Question 32 — Résultat de campagne

Ici, je serais plus strict : **les deux points déjà décidés peuvent être considérés comme acquis**, mais les deux autres ne doivent pas être inventés.

Le document actuel confirme déjà :

* arrondi au franc le plus proche ;
* avances non remboursées affichées séparément, hors résultat. 

Pour les éléments non tranchés, je proposerais cette réponse :

## 32. Résultat de campagne — règles non tranchées par le contrat

### (a) Arrondi

**Décision : validée.**

La part globale revenant aux investisseurs est arrondie au franc le plus proche.

La répartition entre investisseurs utilise la règle du **plus fort reste**, afin que la somme des parts corresponde exactement au montant à répartir.

Le reliquat d'arrondi revient à LY conformément à la règle actuellement codée.

### (b) Avances aux producteurs non remboursées

**Décision : ne pas les intégrer directement aux charges du résultat de campagne.**

À la clôture :

* les avances non remboursées sont affichées séparément ;
* elles restent identifiées individuellement ;
* elles ne sont pas intégrées au résultat de campagne tant que leur traitement contractuel n'est pas défini ;
* elles doivent apparaître dans les informations de risque/exposition de la campagne.

Leur éventuelle qualification ultérieure en perte nécessitera une décision métier et/ou comptable.

### (c) Perte supérieure aux fonds disponibles

**Décision : règle non définie.**

Le système ne doit pas attribuer automatiquement cette perte à LY ou aux investisseurs.

Si les pertes dépassent les fonds disponibles, le montant non imputé doit être affiché séparément comme :

> **Perte non imputée — traitement à décider**

Aucun investisseur ne doit être automatiquement débité au-delà de son investissement sur la base d'une règle qui n'a pas été validée.

Le rapport final ne doit pas présenter une répartition définitive de cette perte tant que la règle n'est pas validée.

### (d) Valorisation du stock invendu

**Décision : conserver la valorisation manuelle prévue.**

La valeur du stock invendu est saisie par la direction à partir de **deux offres de prix écrites**.

La plateforme ne doit pas inventer automatiquement une valeur de marché.

Elle doit conserver :

* les deux offres utilisées ;
* leur date ;
* leur fournisseur ;
* les prix proposés ;
* la valeur finalement retenue ;
* l'utilisateur ayant effectué la saisie ;
* la date de valorisation.

### Règle de clôture

Le résultat de campagne ne peut être considéré comme définitivement validé tant que :

1. la perte supérieure aux fonds n'a pas de traitement validé ;
2. le stock invendu n'a pas été valorisé ;
3. les avances non remboursées ont été correctement présentées.

**Statut :**

* arrondi : **validé** ;
* avances non remboursées : **validé provisoirement selon la règle actuelle** ;
* perte supérieure aux fonds : **à valider** ;
* stock invendu : **règle opérationnelle définie, validation métier à confirmer**.

### Ce que je modifierais dans le suivi

Il y a donc une différence importante entre les deux questions :

**Q35 :** le code peut continuer avec la règle prudente actuelle, mais il faut marquer explicitement la formule comme **règle provisoire**, pas comme vérité métier définitive.

**Q32 :** ne pas bloquer tout le développement, mais **bloquer la publication d'un résultat final aux investisseurs** tant que la règle de perte supérieure aux fonds et la validation de la valorisation du stock ne sont pas arrêtées. Le document actuel dit déjà que ces éléments restent à définir avant le rapport final. 
