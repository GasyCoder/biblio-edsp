# Consignes du projet biblio-edsp

## Référence UI/UX obligatoire

- Utiliser le template Dashwind fourni dans `template-inspire` comme référence obligatoire pour toute conception UI/UX.
- Privilégier son implémentation Vue située dans `template-inspire/vue` pour les layouts, composants, formulaires, tableaux, cartes, navigation et écrans d'authentification.
- Adapter les composants au socle Laravel/Inertia/Vue 3/TypeScript/Tailwind CSS 4 du projet ; ne pas intégrer le template comme une seconde application et ne pas remplacer la stack existante.
- Reprendre de manière cohérente son langage visuel : sidebar, header, espacements, cartes, états, palette, typographies et comportements responsive.
- Adapter les contenus et parcours au contexte interne de la bibliothèque EDSP. Ne conserver aucun contenu de démonstration sans rapport avec la bibliothèque.
- Réutiliser uniquement les assets utiles du template et les copier dans l'application avec une provenance claire ; ne pas charger directement des ressources depuis `template-inspire` en production.
