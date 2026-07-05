<?php

namespace Database\Seeders;

use App\Models\WoodTexture;
use Illuminate\Database\Seeder;

class WoodTextureSeeder extends Seeder
{
    public function run(): void
    {
        $textures = [
            ['slug' => 'chene',                           'name' => 'Chêne',                      'hex' => '#d4ac7b', 'tags' => ['Bois français',  'Couleur naturelle'], 'texture_path' => 'wood_textures/Chene.jpg',                          'grid_code' => 'Z',  'sort_order' => 1],
            ['slug' => 'amarante',                         'name' => 'Amarante',                   'hex' => '#6B1839', 'tags' => ['Bois exotiques', 'Couleur naturelle'], 'texture_path' => 'wood_textures/Amarante.jpg',                       'grid_code' => 'A',  'sort_order' => 2],
            ['slug' => 'amarello',                         'name' => 'Amarello',                   'hex' => '#C8A84E', 'tags' => ['Bois exotiques', 'Couleur naturelle'], 'texture_path' => 'wood_textures/Amarello.jpg',                       'grid_code' => 'B',  'sort_order' => 3],
            ['slug' => 'charme_jaune',                     'name' => 'Charme Jaune',               'hex' => '#D4B85A', 'tags' => ['Bois français',  'Couleur teintée'],   'texture_path' => 'wood_textures/Charme_Jaune.jpg',                   'grid_code' => 'C',  'sort_order' => 4],
            ['slug' => 'charme_rouge',                     'name' => 'Charme Rouge',               'hex' => '#8B3A3A', 'tags' => ['Bois français',  'Couleur teintée'],   'texture_path' => 'wood_textures/Charme_Rouge.jpg',                   'grid_code' => 'D',  'sort_order' => 5],
            ['slug' => 'charme_vert',                      'name' => 'Charme Vert',                'hex' => '#4A6B4A', 'tags' => ['Bois français',  'Couleur teintée'],   'texture_path' => 'wood_textures/Charme_Vert.jpg',                    'grid_code' => 'E',  'sort_order' => 6],
            ['slug' => 'sycomore-bleu-lapis-lazuli-lr3wk', 'name' => 'Sycomore Bleu Lapis Lazuli', 'hex' => '#42738D', 'tags' => ['Bois français',  'Couleur teintée'],   'texture_path' => 'wood_textures/Sycomore_Bleu_Lapis_Lazuli.jpg',      'grid_code' => 'F',  'sort_order' => 7],
            ['slug' => 'chene_fume',                       'name' => 'Chêne Fumé',                 'hex' => '#5C4033', 'tags' => ['Bois français',  'Couleur teintée'],   'texture_path' => 'wood_textures/Chene_Fume.jpg',                     'grid_code' => 'G',  'sort_order' => 8],
            ['slug' => 'citronnier',                       'name' => 'Citronnier',                 'hex' => '#E8D44D', 'tags' => ['Bois exotiques', 'Couleur naturelle'], 'texture_path' => 'wood_textures/Citronnier.jpg',                     'grid_code' => 'H',  'sort_order' => 9],
            ['slug' => 'sycomore',                         'name' => 'Sycomore',                   'hex' => '#E8DCC8', 'tags' => ['Bois français',  'Couleur naturelle'], 'texture_path' => 'wood_textures/Sycomore.jpg',                       'grid_code' => 'I',  'sort_order' => 10],
            ['slug' => 'frene_bleu',                       'name' => 'Frêne Bleu',                 'hex' => '#4A6B8A', 'tags' => ['Bois français',  'Couleur teintée'],   'texture_path' => 'wood_textures/Frene_Bleu.jpg',                     'grid_code' => 'J',  'sort_order' => 11],
            ['slug' => 'frene_jaune',                      'name' => 'Frêne Jaune',                'hex' => '#D4C25A', 'tags' => ['Bois français',  'Couleur teintée'],   'texture_path' => 'wood_textures/Frene_Jaune.jpg',                    'grid_code' => 'K',  'sort_order' => 12],
            ['slug' => 'frene_orange',                     'name' => 'Frêne Orange',               'hex' => '#D4813A', 'tags' => ['Bois français',  'Couleur teintée'],   'texture_path' => 'wood_textures/Frene_Orange.jpg',                   'grid_code' => 'L',  'sort_order' => 13],
            ['slug' => 'frene_rouge',                      'name' => 'Frêne Rouge',                'hex' => '#9B3A3A', 'tags' => ['Bois français',  'Couleur teintée'],   'texture_path' => 'wood_textures/Frene_Rouge.jpg',                    'grid_code' => 'M',  'sort_order' => 14],
            ['slug' => 'frene_vert',                       'name' => 'Frêne Vert',                 'hex' => '#5A8B5A', 'tags' => ['Bois français',  'Couleur teintée'],   'texture_path' => 'wood_textures/Frene_Vert.jpg',                     'grid_code' => 'N',  'sort_order' => 15],
            ['slug' => 'hetre',                            'name' => 'Hêtre',                      'hex' => '#D4A76A', 'tags' => ['Bois français',  'Couleur naturelle'], 'texture_path' => 'wood_textures/Hetre.jpg',                          'grid_code' => 'O',  'sort_order' => 16],
            ['slug' => 'merisier',                         'name' => 'Merisier',                   'hex' => '#B5651D', 'tags' => ['Bois français',  'Couleur naturelle'], 'texture_path' => 'wood_textures/Merisier.jpg',                       'grid_code' => 'P',  'sort_order' => 17],
            ['slug' => 'movingui',                         'name' => 'Movingui',                   'hex' => '#C8B438', 'tags' => ['Bois exotiques', 'Couleur naturelle'], 'texture_path' => 'wood_textures/Movingui.jpg',                       'grid_code' => 'Q',  'sort_order' => 18],
            ['slug' => 'noyer',                            'name' => 'Noyer',                      'hex' => '#5C3A1E', 'tags' => ['Bois français',  'Couleur naturelle'], 'texture_path' => 'wood_textures/Noyer.jpg',                          'grid_code' => 'R',  'sort_order' => 19],
            ['slug' => 'orme',                             'name' => 'Orme',                       'hex' => '#8B7355', 'tags' => ['Bois français',  'Couleur naturelle'], 'texture_path' => 'wood_textures/Orme.jpg',                           'grid_code' => 'S',  'sort_order' => 20],
            ['slug' => 'padouk',                           'name' => 'Padouk',                     'hex' => '#A63A2D', 'tags' => ['Bois exotiques', 'Couleur naturelle'], 'texture_path' => 'wood_textures/Padouk.jpg',                         'grid_code' => 'T',  'sort_order' => 21],
            ['slug' => 'poirier_alisier',                  'name' => 'Poirier Alisier',            'hex' => '#D4A088', 'tags' => ['Bois français',  'Couleur naturelle'], 'texture_path' => 'wood_textures/Poirier_Alisier.jpg',                'grid_code' => 'U',  'sort_order' => 22],
            ['slug' => 'sycomore_bleu',                    'name' => 'Sycomore Bleu',              'hex' => '#6A8BA0', 'tags' => ['Bois français',  'Couleur teintée'],   'texture_path' => 'wood_textures/Sycomore_Bleu.jpg',                  'grid_code' => 'V',  'sort_order' => 23],
            ['slug' => 'sycomore_rose',                    'name' => 'Sycomore Rose',              'hex' => '#C8A0A0', 'tags' => ['Bois français',  'Couleur teintée'],   'texture_path' => 'wood_textures/Sycomore_Rose.jpg',                  'grid_code' => 'W',  'sort_order' => 24],
            ['slug' => 'tulipier_noir',                    'name' => 'Tulipier Noir',              'hex' => '#2A2A2A', 'tags' => ['Bois exotique',  'Couleur teintée'],   'texture_path' => 'wood_textures/Tulipier_Noir.jpg',                  'grid_code' => 'X',  'sort_order' => 25],
            ['slug' => 'tulipier_violet',                  'name' => 'Tulipier Violet',            'hex' => '#5A2D5A', 'tags' => ['Bois exotique',  'Couleur teintée'],   'texture_path' => 'wood_textures/Tulipier_Violet.jpg',                'grid_code' => 'Y',  'sort_order' => 26],
            ['slug' => 'sycomore-bleu-adriatique-5di0j',   'name' => 'Sycomore Bleu Adriatique',  'hex' => '#00B0D4', 'tags' => ['Bois français',  'Couleur teintée'],   'texture_path' => 'wood_textures/Sycomore_Bleu_Adriatique.jpg',       'grid_code' => 'AA', 'sort_order' => 27],
            ['slug' => 'sycomore-bleu-marine-dm0i1',       'name' => 'Sycomore Bleu Marine',      'hex' => '#324265', 'tags' => ['Bois français',  'Couleur teintée'],   'texture_path' => 'wood_textures/sycomore-bleu-marine-1779346396615.jpg', 'grid_code' => 'AB', 'sort_order' => 28],
            ['slug' => 'sycomore-vert-trefle-u50xv',       'name' => 'Sycomore Vert Trèfle',      'hex' => '#67A072', 'tags' => ['Bois français',  'Couleur teintée'],   'texture_path' => 'wood_textures/sycomore-vert-trefle-1779347734730.jpg', 'grid_code' => 'AC', 'sort_order' => 29],
            ['slug' => 'frene-c93qd',                      'name' => 'Frêne',                      'hex' => '#E3CFAC', 'tags' => ['Bois français',  'Couleur naturelle'], 'texture_path' => 'wood_textures/frene-1779370472970.jpg',            'grid_code' => 'AD', 'sort_order' => 30],
        ];

        foreach ($textures as $data) {
            WoodTexture::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
