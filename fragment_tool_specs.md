# FragmentTool — Spécifications détaillées (V1)

## 1. Contexte et objectif

**Fragment** est un loisir créatif : on assemble de petits morceaux de bois collés côte à côte pour former une image.
**FragmentTool** est un outil personnel qui transforme une image (déjà plus ou moins pixelisée) en :

1. une **image pixel art** dans un format Fragment (16×16 ou 32×32), restreinte aux essences de bois disponibles ;
2. un **plan de montage** (grille numérotée + légende essence / quantités).

En V1, l'outil tourne en local pour un seul utilisateur. Il est conçu dès le départ pour pouvoir être **hébergé plus tard sur un serveur et utilisé par l'équipe**, sans réécriture (voir §9).

## 2. Périmètre

### Inclus en V1
- Interface web locale (navigateur).
- Import d'une image PNG ou JPG.
- Recadrage manuel carré de la zone conservée.
- Choix du format 16×16 ou 32×32.
- Redessin par IA (Gemini) au cœur de l'outil : générations successives, choix de la version par l'utilisateur, raffinement à la demande (§5.8).
- Finitions : motifs de fond, **retouche manuelle case par case** (éditeur pixel), suggestions de Gemini.
- Gestion de la transparence (remplacement par une essence choisie).
- Export par téléchargement : PNG en aplats agrandi, **rendu bois** à partir des photos des essences, PNG du plan de montage.
- Les méthodes de conversion classiques (réduction × association, §5.5–5.6) restent disponibles dans l'API, mais l'interface ne les propose plus.
- Palette des essences dans un fichier de configuration.

### Reporté (versions ultérieures)
- Historique / galerie des créations.
- Hébergement serveur pour l'équipe et authentification (architecture préparée dès la V1).
- Export PDF, autres formats que 16×16 / 32×32, rotation / miroir, exclusion d'essences.
- Le tramage (Floyd–Steinberg, Bayer) a été essayé puis retiré : à 16×16 / 32×32, il produit un bruit peu lisible une fois réalisé en bois.

## 3. Palette des essences

Palette imposée, stockée dans `palette.toml` (modifiable à la main, chargée au démarrage). Le numéro est **fixe** par essence, identique sur tous les plans.

| N° | Essence        | Couleur   |
|----|----------------|-----------|
| 1  | Sycomore       | `#f2cc96` |
| 2  | Hêtre          | `#d6b47c` |
| 3  | Orme           | `#ad7944` |
| 4  | Merisier       | `#c26d2d` |
| 5  | Poirier        | `#d19f84` |
| 6  | Noyer          | `#94785f` |
| 7  | Chêne fumé     | `#524437` |
| 8  | Tulipier Noir  | `#17100e` |

> Le readme indique `d19f84` sans `#` pour le Poirier : supposé être `#d19f84`.

Format du fichier :

```toml
[[essence]]
id = 1
name = "Sycomore"
hex = "#f2cc96"
# ...
```

Validation au chargement : ids uniques, hex valides (`#rrggbb`), au moins 2 essences. Erreur explicite sinon.

## 4. Parcours utilisateur

1. Lancer l'outil (`uv run --env-file .env fragmenttool`) → le serveur démarre et ouvre le navigateur sur `http://localhost:8000`. L'ouverture automatique du navigateur n'a lieu qu'en mode local. Une fois hébergé, l'outil s'utilise simplement via son URL.
2. **Importer** une image (glisser-déposer ou sélecteur de fichier).
3. **Recadrer** : un cadre carré se superpose à l'image ; on le déplace et on le redimensionne pour choisir la zone conservée. Par défaut : le plus grand carré centré (bouton « Recadrage par défaut » pour y revenir, « Changer d'image » pour repartir d'une autre image). Choisir le format (16×16 / 32×32) et, si l'image a de la transparence, l'essence de fond.
4. **Générer** : 1, 2 ou 4 images par lancement, avec une consigne facultative. Chaque lancement **ajoute** des versions à la liste : on peut relancer autant qu'on veut.
5. **Choisir** : l'utilisateur est le juge. Chaque version affiche l'avis de Gemini (note sur 10, ★ sur sa préférée, problèmes relevés au survol), à titre indicatif. Un clic sélectionne la version à garder. « Tout effacer » vide la liste (avec confirmation).
6. **Raffiner** (facultatif) : Gemini corrige la version choisie selon des remarques (pré-remplies avec l'avis du juge, modifiables). Le résultat s'ajoute comme nouvelle version, qu'on garde ou non.
7. **Finitions** : motifs de fond, retouches pixel par pixel (pinceau, remplissage, pipette, annuler / rétablir), suggestions de Gemini à cocher.
8. **Télécharger** : PNG en aplats, rendu bois, plan de montage. Les trois aperçus se mettent à jour après chaque retouche.

## 5. Pipeline de traitement

```
Image source → Recadrage → Luminosité/Contraste → Transparence → Réduction N×N → Association couleurs → Grille d'ids d'essences
```

C'est le pipeline de la conversion classique (§6, API seulement). Le pipeline IA (§5.8) reprend l'entrée (§5.1), le recadrage (§5.2) et la transparence (§5.4) : la zone recadrée, composée sur l'essence de fond, est envoyée au modèle ; la réduction et l'association servent ensuite à la projection de ses images.

### 5.1 Entrée
- Formats : PNG, JPG/JPEG. Taille max : 20 Mo.
- Conversion en RGBA en interne. Pour les JPG, alpha = 255 partout.
- Erreur claire si le fichier est illisible ou dans un autre format.

### 5.2 Recadrage
- Rectangle carré, en coordonnées de l'image source (x, y, côté), envoyé par le front.
- Le côté minimum est égal à N (taille de la grille).

### 5.3 Luminosité / contraste
- Deux paramètres (`brightness`, `contrast`), de −100 à +100, valeur par défaut 0.
- Appliqués sur la zone recadrée, avant la réduction.
- Conversion classique uniquement : l'interface ne les propose plus et l'image envoyée à l'IA n'est pas ajustée.

### 5.4 Transparence
- Un pixel est considéré transparent si alpha < 128.
- **Choix de l'essence de fond** : une liste déroulante présente les essences de la palette (pastille de couleur, numéro et nom). Elle n'apparaît que si l'image importée contient de la transparence. Valeur par défaut : Hêtre (`DEFAULT_BACKGROUND_ID`). La transparence est détectée dans le navigateur. L'essence choisie s'applique aux générations suivantes (fond imposé et image envoyée à l'IA).
- Après la réduction, une case majoritairement transparente reçoit l'**essence de fond** choisie. Elle n'est pas soumise à l'association de couleurs : elle prend exactement cette essence. Sur le plan, elle est numérotée et comptée dans les quantités comme n'importe quelle autre case.
- Les pixels semi-transparents restants sont composés sur la couleur de l'essence de fond.

### 5.5 Méthodes de réduction (vers N×N)

| Clé        | Méthode                    | Description |
|------------|----------------------------|-------------|
| `grid`     | Détection auto de la grille | Détecte la taille des « gros pixels » de l'image déjà pixelisée (analyse des transitions de couleur sur les lignes et colonnes), échantillonne le centre de chaque gros pixel, puis réduit à N×N par couleur majoritaire. Si aucune grille n'est détectée, se comporte comme `mode`. |
| `mean`     | Moyenne par bloc           | Découpe en N×N blocs, couleur moyenne de chaque bloc. |
| `median`   | Médiane par bloc           | Médiane par canal, plus robuste aux contours et au bruit. |
| `mode`     | Couleur majoritaire        | Couleur la plus fréquente du bloc (après une légère quantification pour regrouper les teintes proches). |
| `nearest`  | Plus proche voisin         | Pixel central de chaque bloc. |
| `contours` | Contours                   | Comme `mode`, mais un bloc traversé par un contour net (écart de clarté ≥ 25 entre les 10 % les plus sombres et les 10 % les plus clairs, mesuré sur une image lissée pour ignorer la texture fine) prend la couleur de son côté sombre. Préserve les traits fins (contours, yeux) que les autres méthodes effacent à 16×16 / 32×32. |

Les blocs sont calculés avec des bornes en flottant, pour gérer les côtés qui ne sont pas multiples de N.

### 5.6 Méthodes d'association de couleurs (vers la palette)

| Clé         | Méthode                           | Description |
|-------------|-----------------------------------|-------------|
| `rgb`       | Distance RGB euclidienne          | Simple, rapide. |
| `redmean`   | RGB pondéré (« redmean »)         | Approximation perceptuelle peu coûteuse. |
| `lab`       | Distance CIE76 en espace Lab      | Proche de la perception humaine. |
| `ciede2000` | Distance CIEDE2000                | La plus fidèle perceptuellement. |
| `tone`      | Clarté adaptée                    | Transpose l'image dans l'« espace bois » avant l'association (voir ci-dessous). |

**Clarté adaptée (`tone`).** Les essences sont toutes dans les bruns : comparer les teintes d'une image colorée (ciel, feuillage…) donne des choix arbitraires. Cette méthode :
1. étire la clarté (Lab L) de l'image, des percentiles 2 % / 98 %, vers la plage de clarté des essences, puis la mélange à 50 % avec la clarté d'origine (un étirement total forcerait une image sans noir à en contenir) ;
2. conserve les teintes déjà « bois » (teinte à moins de 30° de la teinte moyenne des essences, chroma ≥ 10) ; les autres (gris, bleus, verts) sont atténuées de moitié et recentrées sur la teinte moyenne du bois, avec une transition progressive entre 30° et 60° ;
3. associe à l'essence la plus proche, avec un poids de 0,5 sur les écarts de teinte face à la clarté.

Les cases de fond (transparence) sont exclues du calcul de la plage de clarté : les méthodes d'association reçoivent pour cela le masque des cases de fond. Une image composée uniquement de couleurs de la palette reste inchangée.

Les méthodes de réduction et d'association sont des **modules interchangeables**, enregistrés dans un registre : ajouter une méthode ne demande pas de modifier l'interface.

### 5.7 Sortie interne
- Matrice N×N d'ids d'essence. Chaque rendu (aperçu, PNG en aplats, rendu bois, plan) est calculé à partir de cette matrice.

### 5.8 Redessin IA

Un modèle génératif réinterprète l'image comme le ferait un illustrateur : formes simplifiées, contours marqués, détails superflus supprimés. C'est le cœur de l'outil.

**Principe directeur :** on ne demande jamais à l'IA de respecter les contraintes de Fragment (grille N×N, essences), on les impose nous-mêmes. Chaque image produite est immédiatement *projetée* sur la grille et les essences. Ce sont ces grilles projetées, donc ce qui sera réellement fabriqué, que le juge note, que l'utilisateur choisit et que le raffinement corrige.

**L'utilisateur arbitre.** Le juge Gemini ne fait que donner un avis (note, problèmes, préférée) : rien n'est choisi ni enchaîné automatiquement. Raffinement et suggestions ne sont lancés qu'à la demande.

- **Activation** : uniquement si une clé API Google Gemini est configurée (`GEMINI_API_KEY`). Sinon, l'interface affiche un message et les boutons IA sont désactivés.
- **Fond et bordure imposés** (génération et raffinement, critère du juge et des suggestions) : fond uni dans l'essence de fond (Hêtre #d6b47c par défaut) ; sujet cerné d'un contour brun foncé continu (essence la plus sombre), lui-même entouré d'un liseré blanc continu en Sycomore #f2cc96 (`HALO_ID`) qui le détache du fond. Le modèle ne respectant ni l'un ni l'autre de façon fiable, **le code les impose après chaque projection** (`frame_subject`) : le fond est la zone de l'essence majoritaire sur le bord, reliée au bord ; elle passe dans l'essence de fond, puis ses cases qui touchent le sujet (diagonales comprises) passent en Sycomore. Un liseré déjà dessiné n'est pas doublé ; pas de liseré si l'essence de fond est elle-même le Sycomore.

#### Étapes (`fragmenttool/ai_pipeline.py`)

| Étape | Déclenchement | Modèle (par défaut) | Appels |
|-------|---------------|---------------------|--------|
| **Génération** (`run_generation`) : K images en parallèle à partir de la zone recadrée. Dès 2 images, chaque candidat reçoit une variante de style (consigne de base, contraste marqué, fidélité à l'original, style « mignon ») pour diversifier le lot | bouton « Générer » (1, 2 ou 4 images, consigne facultative de 500 caractères) | `gemini-nano-banana-2.1` | K |
| **Projection** : `reduce_ai` (détection de grille, cases carrées, logique « Contours » si le modèle a dessiné plus fin), association CIEDE2000, puis `frame_subject` | automatique | notre code | 0 |
| **Avis du juge** : note 0–10, problèmes concrets (au plus 4), préférée du lot | automatique après chaque génération et chaque raffinement | `gemini-3.1-flash-lite` | 1 |
| **Raffinement** (`run_refine`) : la version choisie (agrandie) est renvoyée au modèle d'image avec l'original et les remarques de l'utilisateur, puis re-projetée et notée. Le résultat est une **nouvelle version** ; si la grille n'a pas changé, rien n'est ajouté | bouton « Raffiner » | image + juge | 2 |
| **Suggestions** (`suggest_edits`) : corrections case par case (ligne, colonne, essence, raison), au plus 15, validées (dans la grille, essence connue, changement réel), sur la grille actuelle **retouches et motifs compris**. Jamais appliquées d'office | bouton « Demander des suggestions » | `gemini-3.6-flash` | 1 |

Coût indicatif : ≈ 0,08 $ par image générée ou raffinée, quelques millièmes de dollar par avis ou suggestion (tarifs de novembre 2026). Le coût réel cumulé de la session est affiché.

#### Robustesse
- Une génération qui échoue (surcharge, quota) n'arrête pas le lot tant qu'il reste au moins une image.
- Juge indisponible : les versions sont ajoutées sans note, avec un avertissement.
- Raffinement ou suggestions en échec : message d'erreur, les versions existantes ne changent pas.
- Les réponses JSON des modèles sont lues de façon tolérante (balises ```json, texte autour, valeurs hors bornes ramenées à 0–10).
- Une surcharge temporaire de Google (503) est réessayée une fois à chaque appel.
- Bouton « Arrêter » : coupe l'attente côté navigateur ; les appels déjà partis chez Google peuvent être facturés.

#### API et état
- `POST /api/ai/generate` (image + recadrage + N + essence de fond + `count` + `hint`) et `POST /api/ai/refine` (mêmes champs + `grid` + `remarks`, une remarque par ligne) répondent en **flux NDJSON**, un événement JSON par ligne : `stage` (progression), `warning`, `candidate` (génération : grille projetée de chaque image, au fil de l'eau), puis `result` ou `error`. Génération : `result` = candidats notés + préférée du juge + consommation. Raffinement : `result` = grille, note, problèmes, `unchanged`, consommation.
- `POST /api/ai/suggest` (image + recadrage + N + essence de fond + `grid`) répond en JSON : suggestions + consommation (502 si Gemini échoue).
- **Sans état** : le navigateur garde les versions, la version choisie, les motifs et les retouches, et renvoie la grille voulue à chaque appel ; le serveur la valide (`parse_grid` : carrée, format supporté, essences connues). Changer d'image ou de format efface les versions (avec confirmation). Un changement de recadrage s'applique aux appels suivants (raffinement, suggestions, nouvelles générations).

#### Finitions
- **Motifs de fond** (`fragmenttool/motifs.py`, `POST /api/motifs`) : pour un fond moins uni, l'utilisateur choisit un motif (aucun ; semés : points, petites étoiles en croix de 5 cases, confettis de formes variées, cœurs 5×4, rosaces, losanges évidés et flocons 5×5 ; décors : diagonales, chevrons en zigzag, coins, cadre perlé ou plein), une densité (faible, moyenne) et une essence (Poirier #d19f84 par défaut, `DEFAULT_MOTIF_ESSENCE_ID`). Ils sont posés **par le code, jamais par l'IA** : gratuits, réglables à tout moment, de façon déterministe (graine fixe). Seul le fond relié au bord est concerné, avec une case de marge autour du sujet et de son liseré. En 16×16, il reste peu de place : les dessins de 5×5 n'y tiennent généralement pas (aucun n'est posé), les points, confettis, coins et le cadre y sont les plus utiles.
- **Éditeur pixel par pixel** (navigateur) : outils pinceau (clic ou glisser, touche B), remplissage d'une zone (4-connexité, touche F) et pipette (touche I, ou Alt + clic) ; palette des essences (touches 1 à 8) ; annuler / rétablir (Ctrl/Cmd + Z, Ctrl/Cmd + Maj + Z ou Ctrl/Cmd + Y, 200 niveaux) ; effacer toutes les retouches ; grille affichable avec repères 4/8 ; survol : ligne, colonne et essence de la case. Les retouches sont gardées **par-dessus** la grille motifs compris : changer de motif les conserve. Changer de version les efface (avec confirmation).
- **Suggestions** : survoler une suggestion encadre la case dans l'éditeur ; appliquer la sélection ajoute les retouches (annulable).
- Grille finale = version choisie → motifs → retouches. C'est elle qui est rendue et exportée.
- **Résultat** : aperçu en aplats (dessiné dans le navigateur) avec le nombre d'essences et de morceaux ; rendu bois et plan de montage recalculés par le serveur (`/api/render`) peu après chaque modification (regroupement des changements rapprochés, requête précédente annulée).

#### Appels à Gemini (`fragmenttool/ai.py`)
- API Interactions : `POST https://generativelanguage.googleapis.com/v1beta/interactions`, clé dans l'en-tête `x-goog-api-key`, `store: false` (rien n'est conservé chez Google). Délai maximal : 120 s par appel.
- Images générées : carrées, 1K, **JPEG** (seul format de sortie accepté par l'API).
- **Grille demandée : N/2.** Constat sur des essais réels (novembre 2026) : le modèle dessine presque toujours une grille environ 2 fois plus fine que demandé. On demande donc 16×16 pour obtenir 32×32, et 8×8 pour 16×16. Indiquer la taille des cases en pixels a eu l'effet inverse (grille encore plus fine), cette piste est écartée.
- **Coût** : calculé à partir des tokens renvoyés par l'API (entrée, texte + réflexion, image), avec la table de prix `PRICES_PER_M` (tarifs relevés le 2026-11-02, à mettre à jour si Google les change). Il est affiché à l'utilisateur et écrit dans les logs.
- **Durée observée** : 12 à 45 s par génération d'image.

## 6. Conversion classique (API seulement)

- 6 réductions × 5 associations = 30 combinaisons, calculées par `POST /api/preview` et exportées par `POST /api/export`. L'interface ne les affiche plus depuis la réorganisation autour de l'IA ; elles restent disponibles (et testées) pour un usage sans clé Gemini ou une réintégration future.
- Performance cible : calcul des 30 combinaisons en moins de 2 s pour du 32×32.

## 7. Fichiers exportés

Nommage (`POST /api/render`, à partir de la grille finale) : `<nom_source>_<N>x<N>.png` (aplats), `…_bois.png` (rendu bois) et `…_plan.png`. Conversion classique (`/api/export`) : `<nom_source>_<N>x<N>_<reduction>-<couleur>.png`.

### 7.1 PNG final
- Image agrandie aux pixels nets : **32 px par case** (512×512 pour du 16×16, 1024×1024 pour du 32×32).
- Couleurs exactes de la palette, sans transparence.

### 7.2 Rendu bois (PNG, `fragmenttool/textures.py`)
- Chaque case est dessinée avec un morceau de la photo de son essence, prise dans le dossier `PaletteBois/` (`FRAGMENT_TEXTURES_DIR`). Les photos sont associées aux essences par leur nom de fichier (`Hêtre.jpg` → Hêtre), sans tenir compte des majuscules ni de la forme des accents. Une essence sans photo est rendue en aplat (l'interface le signale).
- 1536 × 1536 px (48 px par case en 32×32, 96 en 16×16) ; une pièce montre 1/5 du côté court de la photo.
- **Fil du bois orienté dans le même sens** (vertical) pour toutes les essences : une photo dont le fil est horizontal est tournée automatiquement (comparaison des variations horizontales et verticales).
- Chaque pièce montre une portion différente de la photo, parfois retournée de 180° (le fil garde son sens), avec une légère variation de luminosité (± 3,5 %) et un joint fin plus sombre entre les pièces. Tirages à graine fixe : le rendu est identique d'un appel à l'autre.
- Les photos sont chargées au premier rendu bois (~0,3 s), puis gardées en mémoire. Un rendu prend ~40 ms.

### 7.3 Plan de montage (PNG)
- **Grille** :
- cases remplies de la couleur de l'essence ;
- numéro de l'essence centré dans chaque case, en noir ou en blanc selon la luminance du fond, pour rester lisible (ex. blanc sur Chêne fumé et Tulipier Noir) ;
- traits fins entre les cases ;
- **traits épais de repère** toutes les 4 cases en 16×16 et toutes les 8 cases en 32×32 ;
- taille de case suffisante pour la lisibilité (environ 40 px).
- **Légende** sous la grille, une ligne par essence **utilisée** :
- numéro, pastille de couleur, nom, code hex, **quantité de morceaux** ;
- total des morceaux (N×N) en bas.
- En-tête : nom de l'image source, format, modèle d'IA utilisé.
- Fond blanc, police DejaVu Sans embarquée dans `fragmenttool/fonts/` (accents et rendu identiques sur toutes les machines).

## 8. Architecture technique

- **Langage** : Python 3.13, gestion par `uv`.
- **Backend** : FastAPI + Uvicorn. Traitement d'image avec Pillow + NumPy. Conversions Lab / CIEDE2000 implémentées en NumPy (ou `scikit-image` si besoin).
- **Frontend** : une page HTML + JS servie par FastAPI, sans étape de build ni dépendance JS externe. Recadrage carré maison sur canvas : déplacement, redimensionnement par les coins, aperçu de la grille N×N.
- **Sans état (stateless)** : le serveur ne garde rien entre deux requêtes, ni sur disque ni en mémoire. Chaque requête porte l'image et tous les paramètres. Plusieurs personnes peuvent donc l'utiliser en même temps, et on peut lancer plusieurs instances derrière un load balancer sans rien changer.
- **Aucune dépendance au poste local** : pas de chemin absolu, pas d'écriture sur le disque (tout se passe en mémoire et les fichiers sont renvoyés en téléchargement), assets JS et polices servis par l'application (pas de CDN).
- **URLs relatives** côté front, pour que l'application fonctionne à la racine d'un domaine comme sous un sous-chemin (`https://outils.exemple.fr/fragment/`).

### Structure proposée

```
fragmenttool/
__init__.py
__main__.py         # lance uvicorn + ouvre le navigateur
palette.py          # chargement/validation de palette.toml
color.py            # conversion sRGB → Lab, luminance relative, CIEDE2000
pipeline.py         # crop, ajustements, transparence, orchestration
reduction.py        # registre des méthodes de réduction
matching.py         # registre des méthodes d'association
ai.py               # appels à Gemini et consignes
ai_pipeline.py      # étapes IA (génération, projection, avis du juge, raffinement, suggestions)
masks.py            # masques de grille (fond relié au bord, voisinage)
motifs.py           # motifs de fond posés sur la grille IA
render.py           # rendu PNG en aplats et plan de montage
textures.py         # rendu bois à partir des photos des essences
api.py              # routes FastAPI
config.py           # réglages lus depuis les variables d'environnement
static/             # index.html, app.js (recadrage, versions, éditeur pixel), style.css
fonts/              # DejaVu Sans (+ licence)
palette.toml
PaletteBois/          # photos des essences pour le rendu bois
tests/
Dockerfile
.dockerignore
```

### API

| Méthode | Route            | Entrée | Sortie |
|---------|------------------|--------|--------|
| GET     | `/`              | — | page web |
| GET     | `/api/palette`   | — | liste des essences |
| GET     | `/api/methods`   | — | formats, état de l'IA (nombres d'images, modèles), motifs, essences par défaut, méthodes classiques |
| POST    | `/api/ai/generate` | image + crop + N + essence de fond + nombre d'images + consigne | flux NDJSON : versions notées (§5.8) |
| POST    | `/api/ai/refine` | image + crop + N + essence de fond + grille + remarques + consigne | flux NDJSON : nouvelle version notée |
| POST    | `/api/ai/suggest` | image + crop + N + essence de fond + grille | suggestions case par case (JSON) |
| POST    | `/api/motifs`    | grille + motif + densité + essence + essence de fond | grille avec motifs |
| POST    | `/api/render`    | grille + `kind` (`image`, `wood` ou `plan`) + nom | PNG en aplats, rendu bois ou plan |
| GET     | `/api/textures`  | — | essences dont une photo est disponible |
| POST    | `/api/preview`   | image + crop + N + luminosité + contraste + essence de fond | conversion classique : 30 matrices d'ids + comptages |
| POST    | `/api/export`    | mêmes paramètres + couple de méthodes | conversion classique : PNG ou plan (`?kind=image|plan`) |
| GET     | `/health`        | — | `{"status": "ok"}` (sonde pour l'hébergement) |

## 9. Préparation à l'hébergement

L'objectif est qu'en V1, passer du local au serveur ne demande que du déploiement, sans modifier le code.

### 9.1 Configuration par variables d'environnement

| Variable                  | Défaut          | Rôle |
|---------------------------|-----------------|------|
| `FRAGMENT_HOST`           | `127.0.0.1`     | Adresse d'écoute (`0.0.0.0` sur un serveur). |
| `FRAGMENT_PORT`           | `8000`          | Port. |
| `FRAGMENT_MODE`           | `local`         | `local` : ouvre le navigateur au démarrage. `server` : ne l'ouvre pas, logs au format JSON. |
| `FRAGMENT_PALETTE_PATH`   | `./palette.toml`| Emplacement de la palette (elle est la même pour toute l'équipe). |
| `FRAGMENT_TEXTURES_DIR`   | `./PaletteBois` | Dossier des photos des essences (rendu bois). |
| `FRAGMENT_MAX_UPLOAD_MB`  | `20`            | Taille maximale d'un upload. |
| `FRAGMENT_MAX_PIXELS`     | `40_000_000`    | Nombre maximal de pixels de l'image décodée (protège contre les images piégées, dites « bombes de décompression »). |
| `FRAGMENT_ROOT_PATH`      | `""`            | Préfixe si l'outil est servi sous un sous-chemin. |
| `FRAGMENT_WORKERS`        | `1`             | Nombre de workers Uvicorn. |
| `GEMINI_API_KEY`          | *(vide)*        | Clé API Google Gemini (ou `FRAGMENT_GEMINI_API_KEY`). Active le redessin IA (§5.8). |
| `FRAGMENT_AI_MODEL`       | `gemini-nano-banana-2.1` | Modèle Gemini de génération d'images. |
| `FRAGMENT_AI_JUDGE_MODEL` | `gemini-3.1-flash-lite` | Modèle juge (avis : note et problèmes). |
| `FRAGMENT_AI_SUGGEST_MODEL` | `gemini-3.6-flash` | Modèle des suggestions case par case. |

### 9.2 Conteneurisation
- Un `Dockerfile` (image Python 3.13 slim + `uv`) est livré dès la V1. Le même conteneur sert en local et sur le serveur.
- Les photos de `PaletteBois/` sont copiées dans l'image (`FRAGMENT_TEXTURES_DIR=/app/PaletteBois`).
- `palette.toml` et `PaletteBois/` peuvent être montés en volume pour changer la palette ou les photos sans reconstruire l'image.
- Lancement en mode serveur : `uvicorn fragmenttool.api:app --host 0.0.0.0` (pas d'ouverture du navigateur).

### 9.3 Robustesse multi-utilisateurs
- Le traitement d'image (CPU) s'exécute dans un pool de threads, pour ne pas bloquer la boucle async de FastAPI.
- Les limites d'upload et de pixels sont vérifiées **avant** le décodage complet de l'image.
- Le type de fichier est validé d'après son contenu (signature PNG/JPEG), pas d'après son extension.
- Les métadonnées des images (EXIF) sont ignorées, à l'exception de l'orientation, qui est appliquée.

### 9.4 Sécurité et accès (au moment de l'hébergement)
- En V1, l'application n'a pas d'authentification. Une fois hébergée, l'accès sera protégé **en amont**, par un reverse proxy (Caddy ou Nginx) avec HTTPS, ou par le SSO de l'entreprise. L'application elle-même n'a donc pas besoin de changer.
- Pas de CORS : le front et l'API sont servis depuis la même origine.

### 9.5 Évolutions prévues
- **Historique / galerie** : il nécessitera un stockage partagé (SQLite ou Postgres pour les métadonnées, stockage objet pour les images) et une notion d'utilisateur. Ce n'est pas implémenté en V1, mais le pipeline produit déjà une matrice d'ids sérialisable (JSON), qui pourra être stockée telle quelle.

## 10. Gestion des erreurs
- Fichier non supporté ou corrompu : message dans l'interface, pas de crash.
- Recadrage plus petit que N : bouton désactivé et message.
- Palette invalide : le serveur refuse de démarrer, avec un message explicite.

## 11. Tests
- Unitaires : chargement de la palette, chaque méthode de réduction (image synthétique à grille connue → résultat exact), chaque méthode d'association (couleurs de la palette → elles-mêmes), comptages de la légende.
- Détection de grille : images de test agrandies ×k avec bruit JPEG léger.
- Rendu : dimensions des PNG exportés, présence de la légende.
- Rendu bois (`test_textures.py`) : association photo ↔ essence par le nom de fichier, orientation du fil, dossier absent, repli en aplat sans photo, route `/api/render` (`kind=wood`), photo présente pour chaque essence de la palette.
- Motifs (`test_motifs.py`) : seules les cases de fond libres sont touchées (marge autour du sujet et du liseré), dessins entiers, densité moyenne plus chargée que faible, cadre symétrique, route `/api/motifs` et refus des paramètres invalides.
- IA (`test_ai.py`, sans appel réseau) : consignes, lecture tolérante des réponses JSON, calcul du coût, nouvelle tentative sur 503, projection et `frame_subject` (fond, liseré), préférée du juge, validation des suggestions, flux de génération / raffinement / suggestions avec un client simulé (échecs partiels, juge absent), IA désactivée sans clé.
- API (`test_api.py`) : routes principales, service sous un sous-chemin, erreurs, utilisateurs simultanés sans interférence.

## 12. Critères d'acceptation V1
- [ ] Une image PNG/JPG quelconque peut être importée, recadrée et convertie en 16×16 et 32×32.
- [ ] Plusieurs lots d'images peuvent être générés ; l'utilisateur choisit librement la version à garder, la raffine s'il veut et la retouche case par case.
- [ ] Le rendu bois utilise les photos de `PaletteBois/` pour toutes les essences.
- [ ] Le PNG final ne contient que des couleurs de la palette.
- [ ] Le plan de montage affiche les numéros fixes, les cases colorées, les repères 4/8 et une légende avec les quantités, dont le total vaut N×N.
- [ ] Les zones transparentes reçoivent l'essence de fond choisie.
- [ ] Modifier `palette.toml` change les couleurs disponibles sans toucher au code.
- [ ] L'image Docker démarre en mode `server`, répond sur `/health` et fonctionne sous un sous-chemin.
- [ ] Deux utilisateurs qui travaillent en même temps n'interfèrent pas (aucun état partagé côté serveur).
- [ ] Une image trop grande, ou un fichier qui n'est pas une image, est refusé proprement.
