# FragmentTool — Spécifications détaillées

> Spécification de l'outil tel qu'implémenté dans l'application Laravel `fragment_boutique` (back-office, section « Outil Fragment »). La V1 avait été prototypée en Python / FastAPI ; elle a été portée dans Laravel, puis complétée par les projets sauvegardés (§13).

## 1. Contexte et objectif

**Fragment** est un loisir créatif : on assemble de petits morceaux de bois collés côte à côte pour former une image.
**FragmentTool** est un outil interne qui transforme une image (déjà plus ou moins pixelisée) en :

1. une **image pixel art** dans un format Fragment (carré, paysage ou portrait, de 16 × 16 à 96 × 48 cases), restreinte aux essences de bois d'une palette ;
2. un **plan de montage** (grille codée + légende essence / quantités) ;
3. un **rendu bois** réaliste à partir des photos des essences.

L'outil est réservé aux administrateurs de la boutique (`/fragment-admin/outil`, middleware `admin.auth`). Les projets sont partagés entre tous les admins.

## 2. Périmètre

### Inclus
- Interface web dans le back-office (Blade + JS bundlé par Vite).
- Import d'une image PNG ou JPG, recadrage manuel aux proportions du format.
- Formats et palettes gérés dans l'admin (base de données), choisis dans l'outil.
- Redessin par IA (Gemini) au cœur de l'outil : générations successives, choix de la version par l'utilisateur, raffinement à la demande (§5.8).
- **Mode test** : tout le parcours IA sans aucun appel à Gemini ni coût (§5.8).
- Finitions : motifs de fond, **retouche manuelle case par case** (éditeur pixel), suggestions de Gemini.
- Gestion de la transparence (remplacement par une essence choisie).
- Export : PNG en aplats agrandi, **rendu bois**, PNG du plan de montage, ou les trois dans un ZIP.
- **Projets sauvegardés** : tout ce qui est généré est enregistré et peut être rouvert (§13).
- Les méthodes de conversion classiques (réduction × association, §5.5–5.6) restent disponibles dans l'API, mais l'interface ne les propose plus.

### Reporté
- Galerie publique des créations, recherche et filtres dans la liste des projets.
- Export PDF, rotation / miroir, exclusion d'essences.
- Reprojection des images brutes de Gemini vers un autre format ou une autre palette.
- Le tramage (Floyd–Steinberg, Bayer) a été essayé puis retiré : à ces tailles, il produit un bruit peu lisible une fois réalisé en bois.

## 3. Essences, palettes et formats

### 3.1 Essences (`wood_textures`)
Les essences sont celles du configurateur de la boutique (admin « Essences »). Champs utilisés par l'outil : `slug`, `name`, `hex`, `texture_path` (photo sur le disque `public`), `grid_code` et `is_active`.

- Le **code** d'une essence (`grid_code`, ex. `O` pour le Hêtre, `AA` pour le Sycomore Bleu Adriatique) est son identifiant sur le plan de montage, dans l'éditeur et dans les échanges avec Gemini. Sans `grid_code`, c'est son id.
- Les essences et leurs photos sont créées par `WoodTextureSeeder` (30 essences) ; les photos sont copiées de `resources/wood_textures/` vers le disque `public` si elles y manquent (une photo remplacée depuis l'admin est conservée).

### 3.2 Palettes (`palettes`, `palette_wood_texture`)
Une palette est une sélection d'essences, gérée dans l'admin (« Palettes ») : nom, description, ordre, active ou non. Elle doit contenir **au moins 2 essences** (`Palette::MIN_ESSENCES`) pour être utilisable.

- `PaletteSeeder` crée la palette d'origine **« Bois naturels »** : Sycomore, Hêtre, Orme, Merisier, Poirier Alisier, Noyer, Chêne Fumé, Tulipier Noir.
- L'outil ne propose que les palettes actives d'au moins 2 essences (plus celle du projet ouvert, même archivée).
- Chaque palette est convertie en `ToolPalette` (instantané immuable des essences, avec couleurs RGB et Lab précalculées).

**Essences par défaut** (`config/fragment_tool.php`), résolues par slug dans la palette choisie, avec repli si le slug en est absent :

| Rôle | Slug | Repli |
|------|------|-------|
| Fond (transparence, fond imposé à l'IA) | `hetre` | essence la plus claire |
| Liseré autour du sujet (`halo`) | `sycomore` | essence la plus claire |
| Motifs de fond | `poirier_alisier` | essence de clarté médiane |
| Contour du sujet (consigne IA) | — | essence la plus sombre |

### 3.3 Formats (`formats`)
Les formats sont ceux du configurateur (admin « Formats »), avec un drapeau `is_tool_enabled` pour l'outil. `FormatSeeder` crée :

| Forme | Formats boutique (aussi dans l'outil) | Formats outil seulement |
|-------|----------------------------------------|-------------------------|
| Carré | 16 × 16, 32 × 32 | 64 × 64 |
| Paysage | 32 × 16, 64 × 32 | 48 × 24, 96 × 48 |
| Portrait | 16 × 32, 32 × 64 | 24 × 48, 48 × 96 |

Dans l'outil, les formats sont regroupés par forme (carré, paysage, portrait), triés par nombre de cases.

Sans palette utilisable ou sans format activé, la page de l'outil l'explique et renvoie vers les admins « Formats » et « Palettes ».

## 4. Parcours utilisateur

Une barre d'étapes suit la progression : Image → Générer → Choisir → Finitions → Exporter.

1. **Ouvrir l'outil** (`/fragment-admin/outil`) : nouveau projet, ou un projet existant depuis la liste (`/fragment-admin/outil/projets`).
2. Choisir la **palette** (pastilles des essences affichées) et le **format**.
3. **Importer** une image (glisser-déposer ou sélecteur de fichier).
4. **Recadrer** : un cadre aux proportions du format se superpose à l'image ; on le déplace et on le redimensionne. Par défaut : le plus grand cadre centré (bouton « Recadrage par défaut »). Si l'image a de la transparence, choisir l'essence de fond.
5. **Générer** : 1, 2, 4, 6 ou 8 images par lancement. Chaque lancement **ajoute** des versions à la liste. La première génération réelle crée le projet (§13).
6. **Choisir** : l'utilisateur est le juge. Chaque version affiche son numéro, son style, l'avis de Gemini (note sur 10, ★ sur sa préférée, problèmes relevés au survol), à titre indicatif. Un clic sélectionne la version à garder.
7. **Aide IA** (facultatif) : onglet « Raffiner » (Gemini corrige la version choisie selon des remarques pré-remplies avec l'avis du juge, modifiables ; le résultat s'ajoute comme nouvelle version) et onglet « Suggestions » (corrections case par case à cocher).
8. **Finitions** : motif de fond, retouches pixel par pixel (pinceau, remplissage, pipette, annuler / rétablir).
9. **Exporter** : onglets Bois / Aplats / Plan avec miniatures, téléchargement de chaque PNG ou « Tout télécharger (.zip) ». Les aperçus se mettent à jour après chaque retouche.

Une barre d'état en bas de page affiche : progression de l'appel IA en cours (message, chronomètre, bouton « Arrêter »), mode test, modèle, coût cumulé du projet, durée de la dernière exécution et durée totale, raccourcis clavier.

## 5. Pipeline de traitement

```
Image source → Recadrage → Luminosité/Contraste → Transparence → Réduction cols×rows → Association couleurs → Grille d'ids d'essences
```

C'est le pipeline de la conversion classique (§6, API seulement, `ConversionPipeline`). Le pipeline IA (§5.8) reprend l'entrée (§5.1), le recadrage (§5.2) et la transparence (§5.4) : la zone recadrée, composée sur l'essence de fond, est envoyée au modèle ; la réduction et l'association servent ensuite à la projection de ses images.

### 5.1 Entrée (`SourceImage`)
- Formats : PNG, JPG/JPEG. Taille max : 20 Mo (`FRAGMENT_MAX_UPLOAD_KB`), vérifiée aussi dans le navigateur.
- Le type est validé d'après le contenu (signature PNG / JPEG), pas d'après l'extension.
- Le nombre de pixels (40 millions max, `FRAGMENT_MAX_PIXELS`) est lu dans l'en-tête **avant** le décodage (protection contre les « bombes de décompression »).
- Décodage en mémoire avec GD, en truecolor avec alpha. L'orientation EXIF des JPEG est appliquée, les autres métadonnées sont ignorées.
- Erreur claire si le fichier est illisible ou dans un autre format.

### 5.2 Recadrage (`CropRect`)
- Rectangle en coordonnées de l'image source (`crop_x`, `crop_y`, `crop_width`, `crop_height`), envoyé par le front.
- Doit rester dans l'image, faire au moins `cols × rows` pixels et avoir les proportions du format (tolérance de 3 % pour les arrondis du navigateur).
- La zone recadrée est ramenée à 768 px de grand côté (`working_long_side`) avant traitement, pour garder les boucles PHP rapides.

### 5.3 Luminosité / contraste
- Deux paramètres (`brightness`, `contrast`), de −100 à +100, valeur par défaut 0, appliqués sur la zone recadrée avant la réduction (filtres GD).
- Conversion classique uniquement : l'interface ne les propose pas et l'image envoyée à l'IA n'est pas ajustée.

### 5.4 Transparence
- Un pixel est considéré transparent si alpha < 128.
- **Choix de l'essence de fond** : liste déroulante des essences de la palette (code et nom), affichée seulement si l'image importée (PNG) contient de la transparence, détectée dans le navigateur. Valeur par défaut : §3.2. L'essence choisie s'applique aux générations suivantes (fond imposé et image envoyée à l'IA).
- Après la réduction, une case majoritairement transparente reçoit exactement l'**essence de fond** (pas d'association de couleurs). Sur le plan, elle est comptée comme n'importe quelle autre case.
- Les pixels semi-transparents restants sont composés sur la couleur de l'essence de fond.

### 5.5 Méthodes de réduction (vers cols × rows)

| Clé        | Méthode                    | Description |
|------------|----------------------------|-------------|
| `grid`     | Détection auto de la grille | Détecte la taille des « gros pixels » de l'image déjà pixelisée (analyse des transitions de couleur sur les lignes et colonnes), échantillonne le centre de chaque gros pixel, puis réduit au format par couleur majoritaire. Si aucune grille n'est détectée, se comporte comme `mode`. |
| `mean`     | Moyenne par bloc           | Couleur moyenne de chaque bloc. |
| `median`   | Médiane par bloc           | Médiane par canal, plus robuste aux contours et au bruit. |
| `mode`     | Couleur majoritaire        | Couleur la plus fréquente du bloc (après une légère quantification pour regrouper les teintes proches). |
| `nearest`  | Plus proche voisin         | Pixel central de chaque bloc. |
| `contours` | Contours                   | Comme `mode`, mais un bloc traversé par un contour net (écart de clarté ≥ 25 entre les 10 % les plus sombres et les 10 % les plus clairs, mesuré sur une image lissée) prend la couleur de son côté sombre. Préserve les traits fins (contours, yeux) que les autres méthodes effacent. |

Les blocs sont calculés avec des bornes en flottant, pour gérer les côtés qui ne sont pas multiples du format.

### 5.6 Méthodes d'association de couleurs (vers la palette)

| Clé         | Méthode                           | Description |
|-------------|-----------------------------------|-------------|
| `rgb`       | Distance RGB euclidienne          | Simple, rapide. |
| `redmean`   | RGB pondéré (« redmean »)         | Approximation perceptuelle peu coûteuse. |
| `lab`       | Distance CIE76 en espace Lab      | Proche de la perception humaine. |
| `ciede2000` | Distance CIEDE2000                | La plus fidèle perceptuellement. |
| `tone`      | Clarté adaptée                    | Transpose l'image dans l'« espace bois » avant l'association (voir ci-dessous). |

**Clarté adaptée (`tone`).** Les essences naturelles sont toutes dans les bruns : comparer les teintes d'une image colorée (ciel, feuillage…) donne des choix arbitraires. Cette méthode :
1. étire la clarté (Lab L) de l'image, des percentiles 2 % / 98 %, vers la plage de clarté des essences, puis la mélange à 50 % avec la clarté d'origine ;
2. conserve les teintes déjà « bois » (teinte à moins de 30° de la teinte moyenne des essences, chroma ≥ 10) ; les autres sont atténuées de moitié et recentrées sur la teinte moyenne du bois, avec une transition progressive entre 30° et 60° ;
3. associe à l'essence la plus proche, avec un poids de 0,5 sur les écarts de teinte face à la clarté.

Les cases de fond (transparence) sont exclues du calcul de la plage de clarté (les méthodes reçoivent le masque des cases de fond). Une image composée uniquement de couleurs de la palette reste inchangée.

Réductions et associations sont des **classes interchangeables** (`App\Services\FragmentTool\Reduction\*`, `Matching\*`), enregistrées dans `ReducerRegistry` / `MatcherRegistry`.

### 5.7 Sortie interne (`Grid`)
- Matrice `rows × cols` d'ids d'essence (ids des `wood_textures`). Chaque rendu (aperçu, PNG en aplats, rendu bois, plan) est calculé à partir de cette matrice.
- Toute grille envoyée par le navigateur est validée (`Grid::parse`) : taille du format, essences de la palette.

### 5.8 Redessin IA

Un modèle génératif réinterprète l'image comme le ferait un illustrateur : formes simplifiées, contours marqués, détails superflus supprimés. C'est le cœur de l'outil.

**Principe directeur :** on ne demande jamais à l'IA de respecter les contraintes de Fragment (grille exacte, essences), on les impose nous-mêmes. Chaque image produite est immédiatement *projetée* sur la grille et les essences. Ce sont ces grilles projetées, donc ce qui sera réellement fabriqué, que le juge note, que l'utilisateur choisit et que le raffinement corrige.

**L'utilisateur arbitre.** Le juge Gemini ne fait que donner un avis (note, problèmes, préférée) : rien n'est choisi ni enchaîné automatiquement. Raffinement et suggestions ne sont lancés qu'à la demande.

- **Activation** : uniquement si une clé API Google Gemini est configurée (`GEMINI_API_KEY`, ou `FRAGMENT_GEMINI_API_KEY`). Sinon, l'interface l'indique et seul le mode test est disponible (les routes IA répondent 503 hors mode test).
- **Mode test** (case « Mode test », cochée par défaut en environnement `local`) : Gemini est remplacé par `FakeGeminiClient`. Les « images générées » sont la zone recadrée avec un filtre différent à chaque appel, le juge et les suggestions sont aléatoires mais valides, une pause de 1,5 s (`FRAGMENT_AI_TEST_DELAY_MS`) précède chaque réponse. Aucun appel réseau, aucun coût, **rien n'est enregistré** (§13).
- **Fond et bordure imposés** (génération et raffinement, critère du juge) : fond uni dans l'essence de fond ; sujet cerné d'un contour continu dans l'essence la plus sombre, lui-même entouré d'un liseré continu dans l'essence de liseré (§3.2). Le modèle ne respectant ni l'un ni l'autre de façon fiable, **le code les impose après chaque projection** (`SubjectFramer`) : le fond est la zone de l'essence majoritaire sur le bord, reliée au bord ; elle passe dans l'essence de fond, puis ses cases qui touchent le sujet (diagonales comprises) passent dans l'essence de liseré. Un liseré déjà dessiné n'est pas doublé ; pas de liseré si l'essence de fond est l'essence de liseré.

#### Étapes (`App\Services\FragmentTool\Ai\AiPipeline`)

| Étape | Déclenchement | Modèle (par défaut) | Appels |
|-------|---------------|---------------------|--------|
| **Génération** (`generate`) : K images en parallèle (requêtes HTTP asynchrones) à partir de la zone recadrée. Chaque candidat reçoit une variante de style (`base` Standard, `contrast` Contrasté, `faithful` Fidèle, `cute` Mignon), reprises dans le même ordre au-delà de 4 images | bouton « Générer » (1, 2, 4, 6 ou 8 images) | `gemini-nano-banana-2.1` | K |
| **Projection** (`AiProjector`) : image étirée aux proportions exactes du format (640 px de grand côté), détection de grille avec cases carrées, logique « Contours » si aucune grille n'est trouvée ou si le modèle a dessiné plus de 20 % plus fin, association CIEDE2000, puis `SubjectFramer` | automatique | notre code | 0 |
| **Avis du juge** : note 0–10, problèmes concrets en français (au plus 4), préférée du lot | automatique après chaque génération et chaque raffinement | `gemini-3.1-flash-lite` | 1 |
| **Raffinement** (`refine`) : l'original et la version choisie (rendue en aplats, 512 px) sont renvoyés au modèle d'image avec les remarques de l'utilisateur (une par ligne ; « Améliore la lisibilité du sujet. » si vide), puis re-projetés et notés. Le résultat est une **nouvelle version** ; si la grille n'a pas changé, rien n'est ajouté | onglet « Raffiner » | image + juge | 2 |
| **Suggestions** (`suggest`) : la grille est transmise en codes d'essence avec la légende. Corrections case par case (ligne, colonne, code, raison), au plus 15, validées (dans la grille, code connu, changement réel, une par case), sur la grille actuelle **retouches et motifs compris**. Jamais appliquées d'office | onglet « Suggestions », bouton « Demander » | `gemini-3.6-flash` | 1 |

Une **consigne** libre (`hint`, 500 caractères max) est acceptée par l'API de génération et de raffinement et enregistrée sur le projet ; l'interface ne la propose pas.

Coût indicatif : ≈ 0,08 $ par image générée ou raffinée, quelques millièmes de dollar par avis ou suggestion. Le coût réel cumulé du projet est affiché.

#### Robustesse
- Une génération qui échoue (surcharge, quota, image illisible) n'arrête pas le lot : un avertissement est émis et le lot continue tant qu'il reste au moins une image.
- Juge indisponible : les versions sont ajoutées sans note, avec un avertissement.
- Raffinement ou suggestions en échec : message d'erreur, les versions existantes ne changent pas (suggestions : réponse 502).
- Les réponses JSON des modèles sont demandées avec un schéma et lues de façon tolérante (balises ```json, texte autour, notes ramenées à 0–10). Les blocs de réflexion (`thought`) sont ignorés.
- Une surcharge temporaire de Google (503) est réessayée une fois à chaque appel.
- Bouton « Arrêter » : coupe l'attente côté navigateur. Pour un projet enregistré, le serveur termine l'appel et l'enregistre quand même (`ignore_user_abort`) ; les versions apparaissent à la réouverture.

#### API et état
- `POST …/outil/ai/generate` (image + recadrage + format + palette + essence de fond + `count` + `hint`) et `POST …/outil/ai/refine` (mêmes champs + `grid` + `remarks` + `version_id`) répondent en **flux NDJSON** (`application/x-ndjson`), un événement JSON par ligne : `project` (projet créé par cet appel), `stage` (progression), `warning`, `candidate` (génération : grille projetée de chaque image au fil de l'eau, avec la version enregistrée), puis `result` ou `error`. Génération : `result` = candidats notés + préférée du juge + consommation. Raffinement : `result` = grille, note, problèmes, `unchanged`, consommation. L'image brute du modèle n'est jamais envoyée au navigateur.
- `POST …/outil/ai/suggest` (mêmes champs + `grid` + `version_id`) répond en JSON : suggestions + consommation.
- Chaque appel envoie `test_mode`. Avec un `project_id`, l'image, le recadrage, le format et la palette sont relus depuis le projet et ne sont plus envoyés.
- Le navigateur garde l'état d'affichage (versions, version choisie, motif, retouches, historique d'annulation) et renvoie la grille voulue à chaque appel ; le serveur la valide. L'état durable est enregistré côté serveur (§13).

#### Finitions
- **Motifs de fond** (`MotifPainter`, `POST …/outil/motifs`) : pour un fond moins uni, l'utilisateur choisit un motif (aucun ; semés : points, petites étoiles en croix de 5 cases, confettis de formes variées, cœurs 5×4, rosaces, losanges évidés et flocons 5×5 ; décors : diagonales, chevrons, coins, cadre perlé ou plein), une densité (faible, moyenne) et une essence (défaut : §3.2). Ils sont posés **par le code, jamais par l'IA** : gratuits, réglables à tout moment, déterministes (graine fixe). Seul le fond relié au bord est concerné, avec une case de marge autour du sujet et de son liseré. Sur les petits formats, les dessins de 5×5 tiennent rarement ; points, confettis, coins et cadre y sont les plus utiles.
- **Éditeur pixel par pixel** (navigateur) : pinceau (clic ou glisser, touche B), remplissage d'une zone (4-connexité, touche F), pipette (touche I, ou Alt + clic) ; palette des essences (touches 1 à 9 puis 0 pour les dix premières) ; annuler / rétablir (Ctrl/Cmd + Z, Ctrl/Cmd + Maj + Z ou Ctrl/Cmd + Y, 200 niveaux) ; effacer toutes les retouches ; grille affichable avec repères toutes les 4 cases (formats ≤ 16) ou 8 cases ; survol : ligne, colonne et essence de la case. Les retouches sont gardées **par-dessus** la grille motifs compris : changer de motif les conserve. **Chaque version garde ses propres retouches et son motif** : passer d'une version à l'autre ne perd rien.
- **Suggestions** : survoler une suggestion encadre la case dans l'éditeur ; « Appliquer » ajoute les suggestions cochées comme retouches (annulable).
- Grille finale = version choisie → motif → retouches. C'est elle qui est rendue et exportée.
- **Résultat** : aperçu en aplats dessiné dans le navigateur (avec le nombre d'essences et de morceaux) ; rendu bois et plan de montage recalculés par le serveur (`…/outil/render`) 500 ms après la dernière modification (requête précédente annulée). Les essences sans photo sont signalées.

#### Appels à Gemini (`GeminiClient`)
- API Interactions : `POST https://generativelanguage.googleapis.com/v1beta/interactions`, clé dans l'en-tête `x-goog-api-key`, en-tête `Api-Revision: 2026-05-20`, `store: false` (rien n'est conservé chez Google). Délai maximal : 120 s par appel.
- Images générées : 1K, **JPEG** (seul format de sortie accepté). Proportions demandées : `1:1`, `16:9` (paysage, rapport ≥ 1,5) ou `9:16` (portrait) ; la projection ramène ensuite l'image aux proportions exactes du format.
- **Grille demandée : moitié du format** (au moins 4). Constat sur des essais réels (novembre 2026) : le modèle dessine presque toujours une grille environ 2 fois plus fine que demandé. Indiquer la taille des cases en pixels a eu l'effet inverse ; cette piste est écartée. Les consignes ne listent pas les essences, seulement les couleurs de fond, de contour et de liseré.
- **Coût** (`GeminiUsage`) : calculé à partir des tokens renvoyés par l'API (entrée, sortie + réflexion, tarif image pour le modèle d'image), avec la table `fragment_tool.ai.prices_per_m` (à mettre à jour si Google change ses tarifs). Il est affiché et écrit dans les logs Laravel.
- **Durée observée** : 12 à 45 s par génération d'image.

## 6. Conversion classique (API seulement)

- `POST …/outil/convert` : une combinaison réduction × association (+ luminosité, contraste) → grille et comptages.
- `POST …/outil/preview` : les 6 × 5 = 30 combinaisons.
- L'interface ne les affiche plus depuis la réorganisation autour de l'IA ; elles restent disponibles (et testées) pour un usage sans clé Gemini ou une réintégration future.
- Performance : les 30 combinaisons d'un 32 × 32 sont calculées en moins de 10 s (en PHP, sur une zone ramenée à 768 px).

## 7. Fichiers exportés

Routes `POST …/outil/render` (`kind` = `image`, `wood` ou `plan`) et `POST …/outil/render/zip`, à partir de la grille finale. Nommage, avec `<nom>` = nom du projet (ou de l'image) en slug : `<nom>_<cols>x<rows>.png` (aplats), `…_bois.png` (rendu bois), `…_plan.png` (plan). Le ZIP `<nom>_<cols>x<rows>.zip` contient les trois fichiers dans un dossier du même nom.

### 7.1 PNG en aplats (`FlatRenderer`)
- Image agrandie aux pixels nets : **32 px par case** (512 × 512 pour du 16 × 16, 3072 × 1536 pour du 96 × 48).
- Couleurs exactes de la palette, sans transparence.

### 7.2 Rendu bois (`WoodRenderer`)
- Chaque case est dessinée avec un morceau de la photo de son essence (`texture_path`, disque `public`). Une essence sans photo, ou dont la photo est illisible, est rendue en aplat (l'interface le signale).
- 1536 px sur le grand côté (cases de 1536 / max(cols, rows) px) ; une pièce montre 1/5 du côté court de la photo.
- Une photo dont le fil est horizontal est d'abord redressée (comparaison des variations horizontales et verticales). Chaque pièce est ensuite **tournée d'un quart de tour aléatoire** (0°, 90°, −90° ou 180°), comme des pièces posées à la main.
- Chaque pièce montre une portion différente de la photo, avec une légère variation de luminosité (± 3,5 %) et un joint fin plus sombre entre les pièces. Tirages à graine fixe : le rendu est identique d'un appel à l'autre.
- Les photos (et leurs rotations) sont gardées en mémoire pendant la requête.

### 7.3 Plan de montage (`PlanRenderer`)
- **En-tête** : nom du projet, puis « Format cols × rows · palette · IA : modèle ».
- **Grille** :
  - cases remplies de la couleur de l'essence ;
  - **code** de l'essence centré dans chaque case, en noir ou en blanc selon la luminance du fond (ex. blanc sur Chêne Fumé et Tulipier Noir) ;
  - traits fins entre les cases ;
  - **traits épais de repère** toutes les 4 cases si le grand côté est ≤ 16, toutes les 8 cases sinon, et cadre épais ;
  - cases de 40 px, réduites pour les grands formats (grille de 2400 px max, cases de 22 px min).
- **Légende** sous la grille, une ligne par essence **utilisée**, de la plus utilisée à la moins utilisée : code, pastille de couleur, nom, code hex, **nombre de pièces** ; total des pièces (cols × rows) en bas.
- Fond blanc, police DejaVu Sans (fournie par `dompdf`, accents et rendu identiques sur toutes les machines).

## 8. Architecture technique

- **Back** : Laravel 13, PHP 8.3. Traitement d'image avec GD, en mémoire (aucun fichier temporaire hormis le ZIP, supprimé aussitôt). Conversions Lab / CIEDE2000 implémentées en PHP (`Color`). Appels Gemini avec le client HTTP de Laravel (Guzzle, promesses pour le parallélisme).
- **Front** : page Blade du back-office + modules JS natifs bundlés par Vite (`resources/js/fragment-tool/`), sans dépendance JS externe. Recadrage maison sur canvas (déplacement, redimensionnement par les coins, proportions du format, aperçu de la grille).
- **Données** : formats, palettes, essences et projets en base ; images des projets sur le disque privé `local` (§13).
- **Accès** : routes sous `/fragment-admin`, protégées par le middleware `admin.auth`. Front et API sur la même origine (pas de CORS) ; URLs générées par `route()` et transmises au front dans la configuration de la page.

### Structure

```
app/Http/Controllers/Admin/
  FragmentToolController.php      # page de l'outil, IA (NDJSON), motifs, rendus, ZIP, conversion classique
  FragmentProjectController.php   # liste, sauvegarde, suppression, fichiers des projets
  PaletteController.php           # palettes (archiver, dupliquer)
  FormatController.php, WoodTextureController.php
app/Http/Requests/Admin/FragmentTool/
  ToolRequest.php                 # format, palette, essence de fond, grille (ou projet)
  ToolImageRequest.php            # + image et recadrage (ou projet)
  UpdateProjectRequest.php, UpdateVersionRequest.php
app/Models/
  Format.php, Palette.php, WoodTexture.php
  FragmentProject.php, FragmentVersion.php, FragmentAiRun.php
app/Services/FragmentTool/
  SourceImage.php, CropRect.php, PixelBuffer.php   # entrée, recadrage, pixels
  ToolPalette.php, Essence.php, Color.php, Grid.php, GridMasks.php
  ConversionPipeline.php          # conversion classique, image envoyée à l'IA
  Reduction/                      # registre + méthodes de réduction, GridDetector
  Matching/                       # registre + méthodes d'association
  SubjectFramer.php               # fond et liseré imposés
  MotifPainter.php                # motifs de fond
  Rendering/                      # FlatRenderer, WoodRenderer, PlanRenderer
  Ai/                             # AiPipeline, AiProjector, AiPrompts, GeminiClient, FakeGeminiClient, GeminiUsage, JsonAnswer
  Projects/                       # ProjectStore (fichiers), AiRunRecorder (enregistrement des appels)
config/fragment_tool.php          # réglages de l'outil
resources/js/fragment-tool/
  index.js                        # orchestration de la page, versions, sauvegarde auto
  api.js, cropper.js, editor.js, grid.js
resources/views/admin/tool/
  index.blade.php, projects/index.blade.php, palettes/, mockup.blade.php
```

### Routes (préfixe `/fragment-admin`, noms `admin.*`)

| Méthode | Route | Entrée | Sortie |
|---------|-------|--------|--------|
| GET     | `/outil` | — | page de l'outil (nouveau projet) |
| GET     | `/outil/projets/{project}` | — | page de l'outil sur un projet |
| POST    | `/outil/ai/generate` | image + crop + format + palette + fond + `count` + `hint` (ou `project_id`) + `test_mode` | flux NDJSON (§5.8) |
| POST    | `/outil/ai/refine` | idem + `grid` + `remarks` + `version_id` | flux NDJSON |
| POST    | `/outil/ai/suggest` | idem + `grid` + `version_id` | suggestions (JSON) |
| POST    | `/outil/motifs` | grille + motif + densité + essence + fond | grille avec motif |
| POST    | `/outil/render` | grille + `kind` + nom + modèle | PNG |
| POST    | `/outil/render/zip` | grille + nom + modèle | ZIP des trois PNG |
| POST    | `/outil/convert` | image + crop + format + palette + fond + réduction + association + luminosité + contraste | conversion classique : grille + comptages |
| POST    | `/outil/preview` | image + crop + format + palette + fond + luminosité + contraste | conversion classique : 30 grilles + comptages |
| GET     | `/outil/projets` | — | liste des projets |
| PATCH   | `/outil/projets/{project}` | `name`, `selected_version_id` | sauvegarde |
| PATCH   | `/outil/projets/{project}/versions/{version}` | `edits`, `motif` | sauvegarde |
| DELETE  | `/outil/projets/{project}` | — | suppression |
| GET     | `/outil/projets/{project}/source` · `/miniature` · `/apercu` | — | image source, miniature, grille finale en aplats |
| PATCH   | `/palettes/{palette}/archive` · `/formats/{format}/archive` · `/wood-textures/{wood_texture}/archive` | — | archivage |
| POST    | `/palettes/{palette}/duplicate` | — | copie de la palette |
| GET     | `/maquette-outil` | — | maquette statique de l'interface |

## 9. Configuration et déploiement

L'outil est déployé avec la boutique (pipeline CI/CD du dépôt). Réglages dans `config/fragment_tool.php` et `config/services.php` :

| Variable                  | Défaut          | Rôle |
|---------------------------|-----------------|------|
| `GEMINI_API_KEY`          | *(vide)*        | Clé API Google Gemini (ou `FRAGMENT_GEMINI_API_KEY`). Active le redessin IA. |
| `FRAGMENT_AI_MODEL`       | `gemini-nano-banana-2.1` | Modèle de génération d'images. |
| `FRAGMENT_AI_JUDGE_MODEL` | `gemini-3.1-flash-lite` | Modèle juge (note et problèmes). |
| `FRAGMENT_AI_SUGGEST_MODEL` | `gemini-3.6-flash` | Modèle des suggestions case par case. |
| `FRAGMENT_AI_TEST_DELAY_MS` | `1500`        | Pause avant chaque réponse simulée du mode test. |
| `FRAGMENT_MAX_UPLOAD_KB`  | `20480`         | Taille maximale d'un upload. |
| `FRAGMENT_MAX_PIXELS`     | `40000000`      | Nombre maximal de pixels de l'image décodée. |

Autres réglages (fichier de config) : slugs des essences par défaut, taille de travail (768 px), taille de projection (640 px), tailles des rendus (32 px par case en aplats, 1536 px pour le bois, 40 px par case pour le plan), polices, nombres d'images proposés, limites (consigne 500 caractères, 15 suggestions, 4 problèmes), table de prix.

**Serveur** : les appels IA durent jusqu'à plusieurs minutes et sont diffusés en flux : pas de limite de temps PHP pendant le flux (`set_time_limit(0)`), en-tête `X-Accel-Buffering: no` pour Nginx. Le stockage `local` doit être persistant (images des projets).

## 10. Gestion des erreurs
- Fichier non supporté, corrompu ou trop grand : message dans l'interface (validation 422), pas de crash.
- Recadrage hors de l'image, trop petit ou aux mauvaises proportions : refusé (422).
- Format non activé, palette inactive ou de moins de 2 essences, essence de fond ou de motif hors palette, grille invalide : refusés (422).
- IA désactivée hors mode test : 503 avec message.
- Version d'un autre projet : refusée (422 / 404).

## 11. Tests (PHPUnit, `tests/Feature/FragmentTool/`, sans appel à Gemini)
- `ColorAndGridTest` : CIEDE2000, Lab et luminance, essences par défaut et replis de la palette, palette d'au moins 2 essences, code d'essence, validation des grilles, proportions et taille du recadrage.
- `ReductionAndMatchingTest` : chaque réduction sur une grille connue, détection de grille malgré le bruit JPEG, contours, bornes non multiples, chaque association (couleurs de la palette → elles-mêmes), `tone` (ciel bleu → tons bois), transparence, 30 combinaisons et performance.
- `FramingAndMotifsTest` : fond relié au bord, liseré (non doublé, absent si fond = liseré), motifs (cases de fond libres, marge, dessins entiers, densités, cadre symétrique, déterminisme, paramètres invalides).
- `RenderingTest` : dimensions des aplats (carrés et rectangulaires), légende et repères du plan, cases réduites pour les grands formats, lisibilité sur bois sombres, rendu bois (photo, déterminisme, quarts de tour, orientation du fil, repli en aplat).
- `AiToolTest` (client Gemini simulé par `Http::fake`) : flux de génération, échecs partiels, nouvelle tentative sur 503, juge absent, proportions demandées, raffinement (nouvelle version, inchangé, échec, validation), suggestions validées, mode test complet, IA désactivée sans clé, lecture tolérante du JSON, coût, réflexions ignorées, styles, consignes (moitié de la grille, pas de liste d'essences), projection.
- `FragmentToolTest` : accès admin, page (formats par forme, palettes, mode test par défaut), validations (image, recadrage, format, palette, fond), conversion classique, utilisateurs simultanés, motifs, rendus et ZIP.
- `ProjectTest` et `UsedByProjectsTest` : voir §13.8.
- `tests/Feature/Admin/WoodTextureSeederTest` : photos des essences copiées sur le disque `public`.

## 12. Critères d'acceptation
- [ ] Une image PNG/JPG quelconque peut être importée, recadrée et convertie dans chaque format activé (carré, paysage, portrait).
- [ ] Plusieurs lots d'images peuvent être générés ; l'utilisateur choisit librement la version à garder, la raffine s'il veut et la retouche case par case.
- [ ] Le mode test permet tout le parcours sans appel à Gemini ni coût.
- [ ] Le rendu bois utilise la photo de chaque essence de la palette.
- [ ] Le PNG final ne contient que des couleurs de la palette.
- [ ] Le plan de montage affiche les codes des essences, les cases colorées, les repères 4/8 et une légende avec les quantités, dont le total vaut cols × rows.
- [ ] Les zones transparentes reçoivent l'essence de fond choisie.
- [ ] Modifier une palette dans l'admin change les essences disponibles sans toucher au code.
- [ ] Deux utilisateurs qui travaillent en même temps n'interfèrent pas.
- [ ] Une image trop grande, ou un fichier qui n'est pas une image, est refusé proprement.

## 13. Projets sauvegardés

### 13.1 Objectif
Tout ce qui est généré par l'IA est enregistré côté serveur : un F5 réaffiche le travail sans repayer de génération, et l'on peut rouvrir un projet des jours plus tard pour l'améliorer. Rien n'est jamais supprimé automatiquement.

### 13.2 Notion de projet
- Un **projet** = une image source + un recadrage + un format + une palette.
- Les projets sont **partagés entre tous les admins** (on note seulement qui l'a créé et qui a lancé chaque appel IA).
- Le projet est créé au **premier appel IA réel** (génération, ou raffinement / suggestions sans projet), hors mode test. Avant, rien n'est enregistré.
- Tant qu'aucune version n'existe, recadrage, format et palette se changent librement. Ensuite, ils sont **figés** (cadre verrouillé, bouton « Modifier le recadrage (nouveau projet) ») : changer de recadrage, de format, de palette ou d'image, ou « Tout effacer », affiche un avertissement puis détache le projet ; la génération suivante crée un **nouveau projet** avec les nouveaux réglages. L'ancien projet reste intact.
- **Mode test** : rien n'est enregistré (ni projet, ni version, ni appel), y compris sur un projet ouvert.

### 13.3 Données enregistrées
- **Projet** (`fragment_projects`) : nom (par défaut le nom du fichier), image source et miniature (disque privé), dimensions de la source, recadrage, format, palette, essence de fond, consigne, version choisie, coût cumulé, auteur, dates.
- **Appel IA** (`fragment_ai_runs`) : type (`generate`, `refine`, `suggest`), auteur, version de départ (raffinement, suggestions), paramètres envoyés (nombre d'images, consigne, remarques, grille), statut (`running`, `succeeded`, `partial`, `failed`), avertissements, erreur, consommation par appel et coût, suggestions produites. Les appels échoués sont enregistrés aussi : ils sont facturés.
- **Version** (`fragment_versions`) : numéro dans le projet, appel IA d'origine, type (`generation`, `refinement`), version parente (raffinement), style, grille (JSON), essence de fond, note et problèmes du juge, préférée, **image brute renvoyée par Gemini** (JPEG sur disque privé).
- **État de travail de chaque version** : retouches case par case (`[ligne, colonne, essence]`) et motif (type, densité, essence).
- Fichiers dans `storage/app/private/fragment-tool/projects/{id}/` : `source.png|jpg`, `thumbnail.jpg` (320 px), `raw/{numéro}.jpg`.
- Non conservés : l'historique d'annulation de l'éditeur, les exports PNG / ZIP (recalculés à la demande).

### 13.4 Sauvegarde automatique
- Chaque version est enregistrée **dès son arrivée** (événement `candidate`) ; note, problèmes et préférée sont complétés à l'événement `result`.
- Le serveur termine et enregistre l'appel même si le navigateur coupe (F5, onglet fermé, bouton « Arrêter ») ; un appel qui s'arrête sans résultat est marqué échoué.
- Retouches, motif, version choisie, nom : enregistrés automatiquement 800 ms après le dernier changement, avec un indicateur « Enregistrement… / Enregistré ».
- Chaque projet a sa propre URL (`/fragment-admin/outil/projets/{id}`), mise dans la barre d'adresse dès sa création : un F5 recharge l'image, les versions, la version choisie, ses retouches et son motif.
- Les appels IA d'un projet n'envoient plus l'image : le serveur la relit depuis le projet. Format et palette du projet restent utilisés même s'ils ont été archivés depuis.
- Deux admins sur le même projet : la dernière sauvegarde l'emporte (pas de verrou).

### 13.5 Liste des projets
- Page listant les projets, du plus récemment modifié au plus ancien, 24 par page.
- Pour chaque projet : miniature de l'image source, miniature en aplats du résultat final (version choisie + motif + retouches, rendue par le serveur), nom, date, palette (« archivée » le cas échéant), format, nombre de versions, coût.
- Actions : ouvrir, supprimer (manuel uniquement, avec confirmation ; supprime aussi versions, appels et fichiers). Le renommage se fait dans l'outil, en tête de page.

### 13.6 Palettes, essences et formats utilisés
- Un élément est **utilisé** dès qu'un projet y fait référence. Une essence est utilisée dès qu'elle appartient à une palette utilisée ou sert de fond à un projet.
- Un élément utilisé **ne peut pas être supprimé** : le bouton « Supprimer » devient « Archiver » (`is_active = false` ; pour un format, `is_tool_enabled` passe aussi à faux).
- Archivé : absent de l'outil pour les nouveaux projets, mais les projets existants s'ouvrent, s'affichent, se retouchent, se génèrent et s'exportent normalement.
- **Retirer une essence** d'une palette utilisée est interdit (depuis la palette comme depuis l'essence), avec un message invitant à **dupliquer la palette** (action « Dupliquer », copie « (copie) » avec les mêmes essences). Ajouter une essence reste permis.
- Changer la couleur ou la photo d'une essence reste permis : le rendu des anciens projets suit.

### 13.7 Hors périmètre
- Reprojection des images brutes vers un autre format ou une autre palette (les images sont gardées pour plus tard).
- Purge automatique, publication dans la galerie, recherche et filtres dans la liste.

### 13.8 Tests (`ProjectTest`, `UsedByProjectsTest`, client simulé)
- Création du projet au premier appel ; aucun enregistrement en mode test, même sur un projet ouvert.
- Versions enregistrées au fil du flux, numérotées à la suite des existantes ; image relue depuis le projet ; format et palette du projet conservés même archivés.
- Génération partiellement échouée ou échouée : versions reçues conservées, statut, erreur et coût enregistrés.
- Raffinement : version enfant avec remarques ; raffinement inchangé sans version ; version d'un autre projet refusée. Suggestions enregistrées, y compris en échec.
- Réouverture d'un projet avec ses versions ; source et miniature servies.
- Sauvegarde du nom, de la version choisie, des retouches et du motif par version ; refus des valeurs invalides.
- Liste des projets, aperçu final en aplats, suppression d'un projet et de ses fichiers ; accès réservé aux admins.
- Suppression refusée d'une palette, d'une essence ou d'un format utilisé ; archivage ; retrait d'essence refusé, ajout permis ; duplication de palette.

### 13.9 Critères d'acceptation
- [ ] Après une génération, un F5 réaffiche toutes les versions, la version choisie, ses retouches et son motif, sans nouvel appel IA.
- [ ] Un projet créé des jours plus tôt se rouvre depuis la liste et peut être raffiné, retouché et exporté.
- [ ] Une génération interrompue conserve les versions déjà reçues.
- [ ] Changer le recadrage, le format ou la palette d'un projet qui a des versions crée un nouveau projet après avertissement.
- [ ] Le mode test n'enregistre rien.
- [ ] Une palette, une essence ou un format utilisé ne peut être qu'archivé, et les projets qui l'utilisent fonctionnent toujours.
