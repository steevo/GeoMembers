# GeoMembers

Cartes de répartition des adhérents pour Joomla 5 et 6 : où sont les membres
d'une association, par pays, par département, par région ou selon des
regroupements propres au club.

GeoMembers est générique : il ne contient rien de propre à un club. Les fonds
de carte, les regroupements et les effectifs se règlent dans l'administration.

*Membership distribution maps for Joomla 5/6 — by country, department, region
or custom grouping. Interface available in French, English, German and Spanish.*

## Fonctionnalités

- **Vue monde** et **vue France** par départements. Dans la vue France, les pays
  voisins sont dessinés autour de la carte et les pays ayant des adhérents
  portent un badge drapeau + effectif, sur la carte ou dans les marges.
- **Encarts** : agrandissement d'une zone dense (ex. Île-de-France, Europe sur
  la carte du monde) et zones éloignées affichées à part (ex. outre-mer).
- **Regroupements** : régions officielles et continents fournis à
  l'installation, plus autant de regroupements personnalisés que nécessaire
  (délégations, anciennes régions…), avec une couleur par groupe.
- **Jeux de données** : un par année, ou par thème (ex. présents à
  l'assemblée générale), avec niveau d'accès Joomla.
- **Saisie des effectifs** à la maille la plus fine (département en France,
  pays ailleurs) ; les totaux par pays, région ou groupe sont calculés.
  Import et export CSV.
- **Représentations** : aplats selon l'effectif (échelle logarithmique
  automatique si les valeurs sont très dispersées), couleurs des groupes ou
  cercles proportionnels.
- **Affichage** : sélecteur de regroupement pour le visiteur, légende,
  infobulles, tableau des effectifs, export SVG et PNG, seuil de
  confidentialité optionnel (« < 3 »).
- **Modes clair et sombre**, compatibles avec les gabarits Bootstrap 5
  (Cassiopeia) et T4.
- **Module** pour afficher une carte compacte dans une position de gabarit.
- Rendu **SVG** avec d3-geo, sans tuiles ni service externe : aucune donnée ne
  quitte le site.

## Prérequis

- Joomla 5.0 ou plus récent (testé sur Joomla 6.2)
- PHP 8.1 ou plus récent
- MySQL ou MariaDB

## Installation

1. Construire le paquet (voir [Construction](#construction)) ou récupérer
   `pkg_geomembers.zip` dans une version publiée.
2. Dans Joomla : **Système → Installer → Extensions**, envoyer
   `pkg_geomembers.zip`. Le paquet installe le composant et le module.
3. L'installation crée deux découpages (« Pays du monde » et
   « France – départements ») et deux regroupements (« Régions » et
   « Continents »), avec des noms dans la langue par défaut du site.

## Prise en main

1. **Composants → GeoMembers → Jeux de données** : créer un jeu
   (ex. « Adhérents 2026 »), puis **Saisir les effectifs**.
2. Créer un élément de menu **GeoMembers → Carte de répartition** : choisir le
   découpage, le jeu de données, le regroupement par défaut et les options
   d'affichage.
3. Facultatif : publier le module **GeoMembers** dans une position du gabarit.

### Saisie et import CSV

La grille de saisie liste toutes les zones de chaque découpage. Un nombre saisi
sur un pays qui a des subdivisions (ex. France) compte comme « non ventilé ».

L'import accepte un fichier séparé par `;` ou `,`, en UTF-8 ou en ANSI
(Excel), dans l'un de ces formats :

```
decoupage;code;nom;effectif     (format de l'export)
decoupage;code;effectif
code;effectif
```

Les zones présentes dans le fichier remplacent les valeurs actuelles. Les
codes de département peuvent être saisis sans zéro initial (`1` pour l'Ain).

### Encarts

Champ **Encarts** d'un découpage, un encart par ligne :

```
zoom|Île-de-France|75,77,78,91,92,93,94,95
detached|Guadeloupe|971
```

- `zoom` : agrandissement d'une zone, la zone reste aussi sur la carte.
- `detached` : zone éloignée retirée de la carte principale et affichée à part.

### Placement des pays étrangers

Par défaut, un pays ayant des adhérents porte son étiquette sur la carte dès
qu'une partie de son territoire y est visible ; les autres vont en marge, à
gauche ou à droite selon leur position, du nord au sud. Une marge vide est
retirée.

Le champ **Placement des pays étrangers** du découpage permet de forcer ce
placement, un pays par ligne :

```
PT|map              sur la carte (point automatique)
DE|map|9,49.5       sur la carte, au point longitude,latitude
IT|right|1          marge droite, en première position
US|left             marge gauche
*|right             marge droite pour tous les pays non cités
```

Les mots `carte`, `gauche` et `droite` sont acceptés à la place de `map`,
`left` et `right`.

## Fonds de carte

| Fichier | Contenu | Source | Licence |
|---|---|---|---|
| `fr-departements.json` | 101 départements (métropole et DROM) | IGN ADMIN EXPRESS via [etalab contours-administratifs](https://github.com/etalab/contours-administratifs) 2024 | Licence Ouverte 2.0 |
| `world-countries.json` | Pays du monde, noms fr/en/de/es | [Natural Earth](https://www.naturalearthdata.com/) 1:50m | Domaine public |
| `flags/*.svg` | Drapeaux | [flag-icons](https://github.com/lipis/flag-icons) 7.2.3 | MIT |

D'autres fonds peuvent être ajoutés : un découpage accepte un fichier TopoJSON
(recommandé) ou GeoJSON en coordonnées WGS84, placé dans
`media/com_geomembers/geo/` ou ailleurs dans le site (ex.
`images/cartes/belgique.json`). Les zones sont créées à l'enregistrement du
découpage.

## Construction

```
php build/package.php
```

Le script génère les fichiers de langue dérivés (`.sys.ini`, module, paquet),
vérifie que les quatre langues ont les mêmes clés, puis produit
`dist/com_geomembers.zip`, `dist/mod_geomembers.zip` et
`dist/pkg_geomembers.zip`.

Les fonds de carte se régénèrent depuis les données sources avec
`build/build.html` (à ouvrir sur un serveur local ; les sources vont dans
`build/src/`, non versionné).

`build/testdata-camp-2026.sql` contient un jeu d'essai (effectifs 2026 du Club
Autos Miniatures Peugeot).

## Structure

```
com_geomembers/     composant (admin, site, media, script d'installation)
mod_geomembers/     module
language/           langues du paquet
pkg_geomembers.xml  manifeste du paquet
build/              construction, génération des fonds, jeu d'essai
```

Espaces de noms : `Steevo\Component\GeoMembers` et `Steevo\Module\GeoMembers`.

## Évolutions envisagées

- Import de nouveaux fonds de carte depuis l'administration.
- Plugins de sources d'effectifs : profils Joomla, Community Builder, phpBB.
- Fonds de carte tuilés en option.

## Licence

GNU General Public License version 2 ou ultérieure.
Copyright © 2026 Philippe Ousset.

Bibliothèques incluses : [d3](https://d3js.org/) et
[topojson-client](https://github.com/topojson/topojson-client) (ISC).
