# LY AGRICOLE — plateforme de gestion

*Cultiver – Élever – Durer*

Plateforme de gestion de LY AGRICOLE (Côte d'Ivoire). Elle suit chaque franc et chaque
kilo, à chaque stade :

- prêts de campagne aux producteurs, remboursés en argent ou en kilos ;
- achats bord-champ et stock par lot ;
- reventes, dépenses, caisses et Mobile Money ;
- rendement de chaque parcelle ;
- plus tard, diagnostic des cultures par IA locale.

État : **conception terminée, développement à partir du samedi 26 septembre 2026.**

| Document | Contenu |
| --- | --- |
| [Cahier des charges](docs/CAHIER_DES_CHARGES.md) | Quoi et pourquoi, les 6 modules, les compléments, le phasage |
| [Décisions](docs/DECISIONS.md) | Choix d'architecture et leurs raisons |
| [Modèle de données](docs/MODELE_DE_DONNEES.md) | Tables de la phase 1 et invariants |
| [Plan d'implémentation](docs/PLAN_IMPLEMENTATION.md) | 12 semaines jusqu'au début de campagne (21/12/2026) |
| [Questions ouvertes](docs/QUESTIONS_OUVERTES.md) | Ce que le responsable projet doit trancher |
| [Rapports de travail](docs/RAPPORTS_DE_TRAVAIL.md) | Journal des sessions |
| [CLAUDE.md](CLAUDE.md) | Règles et pièges pour développer sur ce dépôt |

Document partagé du cahier des charges (relecture, commentaires) :
<https://claude.ai/code/artifact/e20c4dc8-2b2f-4808-809a-da343a2a9222>

## Structure prévue

```
ly-agricole/
├── app/, routes/, …   Laravel 13 + Livewire (back-office)   — semaine 1
├── terrain/           appli terrain SvelteKit + Capacitor    — semaine 8
├── ia/                service IA Python FastAPI + Ollama     — phase 3
├── docs/              documentation du projet
└── .claude/skills/    skills Claude Code du projet
```
