<?php

namespace Database\Seeders;

use App\Models\Crop;
use App\Models\PesticideProduct;
use App\Models\TreatmentRecommendation;
use Illuminate\Database\Seeder;

class TreatmentRegistrySeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            'Mancozeb' => [
                'product_name' => 'Mancozeb fungicide',
                'active_ingredient' => 'Mancozeb',
                'formulation' => 'WP/WG',
                'product_type' => 'fungicide',
            ],
            'Chlorothalonil' => [
                'product_name' => 'Chlorothalonil fungicide',
                'active_ingredient' => 'Chlorothalonil',
                'formulation' => 'SC/WP',
                'product_type' => 'fungicide',
            ],
            'Copper' => [
                'product_name' => 'Fixed copper product',
                'active_ingredient' => 'Copper hydroxide or copper oxychloride',
                'formulation' => 'WP/WG/SC',
                'product_type' => 'bactericide/fungicide',
            ],
            'RustFungicide' => [
                'product_name' => 'Rust fungicide',
                'active_ingredient' => 'Propiconazole, tebuconazole, azoxystrobin, or locally registered equivalent',
                'formulation' => 'EC/SC',
                'product_type' => 'fungicide',
            ],
            'Miticide' => [
                'product_name' => 'Tomato miticide',
                'active_ingredient' => 'Abamectin, spiromesifen, or locally registered equivalent for mites',
                'formulation' => 'EC/SC',
                'product_type' => 'miticide',
            ],
            'VectorControl' => [
                'product_name' => 'Whitefly/vector control product',
                'active_ingredient' => 'Locally registered whitefly or vector-control active ingredient',
                'formulation' => 'SC/WG/EC',
                'product_type' => 'insecticide',
            ],
        ];

        $productModels = [];
        foreach ($products as $key => $product) {
            $productModels[$key] = PesticideProduct::updateOrCreate(
                [
                    'product_name' => $product['product_name'],
                    'active_ingredient' => $product['active_ingredient'],
                ],
                [
                    'formulation' => $product['formulation'],
                    'product_type' => $product['product_type'],
                    'registration_status' => 'locally_verified_required',
                    'label_warning' => 'Use only when the exact product is locally registered for the crop and disease. Follow label dosage, PHI, REI, PPE, and disposal instructions.',
                    'is_active' => true,
                ],
            );
        }

        $cropIds = Crop::query()
            ->whereIn('name', ['Tomato', 'Potato', 'Maize', 'Pepper'])
            ->pluck('id', 'name')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $entries = [
            [
                'crop' => 'Potato',
                'disease_key' => 'potato_early_blight',
                'disease_keyword' => 'blight',
                'product' => 'Mancozeb',
                'title' => 'Potato early blight treatment',
                'summary' => 'Confirmed early blight needs sanitation, reduced leaf wetness, and protective fungicide rotation when disease pressure is high.',
                'natural_treatment' => 'Remove heavily infected lower leaves where safe, avoid overhead irrigation, improve spacing, and destroy infected debris away from the field.',
                'modern_treatment' => 'Apply a locally registered protectant fungicide such as mancozeb or chlorothalonil, rotating fungicide groups if repeat sprays are needed.',
            ],
            [
                'crop' => 'Potato',
                'disease_key' => 'potato_late_blight',
                'disease_keyword' => 'blight',
                'product' => 'Chlorothalonil',
                'title' => 'Potato late blight treatment',
                'summary' => 'Late blight can spread rapidly in cool wet weather and requires urgent field sanitation plus registered blight control.',
                'natural_treatment' => 'Remove severely infected foliage carefully, improve drainage, avoid leaf wetness, and do not compost infected plant material.',
                'modern_treatment' => 'Use a locally registered late blight fungicide program and rotate active ingredient groups under expert advice.',
            ],
            [
                'crop' => 'Tomato',
                'disease_key' => 'tomato_late_blight',
                'disease_keyword' => 'blight',
                'product' => 'Chlorothalonil',
                'title' => 'Tomato late blight treatment',
                'summary' => 'Confirmed late blight requires fast sanitation and protective fungicide application before spread reaches surrounding plants.',
                'natural_treatment' => 'Remove severely diseased leaves, keep foliage dry, improve airflow, and separate affected plants when practical.',
                'modern_treatment' => 'Apply a locally registered tomato blight fungicide and avoid repeated use of the same fungicide group.',
            ],
            [
                'crop' => 'Tomato',
                'disease_key' => 'tomato_early_blight',
                'disease_keyword' => 'blight',
                'product' => 'Mancozeb',
                'title' => 'Tomato early blight treatment',
                'summary' => 'Early blight management combines removal of infected leaves, mulch to reduce soil splash, and protectant fungicide where needed.',
                'natural_treatment' => 'Prune lower infected leaves, mulch soil surface, stake plants, and avoid wet foliage during irrigation.',
                'modern_treatment' => 'Use locally registered mancozeb, chlorothalonil, or equivalent protectant fungicide according to the product label.',
            ],
            [
                'crop' => 'Tomato',
                'disease_key' => 'tomato_leaf_mold',
                'disease_keyword' => 'mold',
                'product' => 'Copper',
                'title' => 'Tomato leaf mold treatment',
                'summary' => 'Leaf mold is favored by high humidity; priority is ventilation and humidity control with registered fungicide support when needed.',
                'natural_treatment' => 'Increase airflow, remove infected leaves, avoid crowded canopy, and reduce humidity around plants.',
                'modern_treatment' => 'Use a locally registered copper or tomato leaf mold fungicide if symptoms continue spreading.',
            ],
            [
                'crop' => 'Tomato',
                'disease_key' => 'tomato_bacterial_spot',
                'disease_keyword' => 'bacterial',
                'product' => 'Copper',
                'title' => 'Tomato bacterial spot management',
                'summary' => 'Bacterial disease treatment focuses on reducing splash spread and using approved copper-based products where locally recommended.',
                'natural_treatment' => 'Avoid overhead irrigation, remove infected debris, disinfect tools, and do not work plants when leaves are wet.',
                'modern_treatment' => 'Apply a locally registered fixed copper product where recommended and avoid unnecessary spraying in hot stress conditions.',
            ],
            [
                'crop' => 'Tomato',
                'disease_key' => 'tomato_septoria_leaf_spot',
                'disease_keyword' => 'spot',
                'product' => 'Mancozeb',
                'title' => 'Tomato Septoria leaf spot treatment',
                'summary' => 'Septoria leaf spot is managed by sanitation, reducing splash, and protectant fungicide where symptoms are spreading.',
                'natural_treatment' => 'Remove infected lower leaves, mulch soil to reduce splash, stake plants, and avoid wet foliage.',
                'modern_treatment' => 'Use a locally registered tomato leaf spot fungicide such as mancozeb, chlorothalonil, or equivalent protectant under label guidance.',
            ],
            [
                'crop' => 'Tomato',
                'disease_key' => 'tomato_target_spot',
                'disease_keyword' => 'spot',
                'product' => 'Chlorothalonil',
                'title' => 'Tomato target spot treatment',
                'summary' => 'Target spot needs canopy sanitation and fungicide rotation when lesions expand under warm humid conditions.',
                'natural_treatment' => 'Improve airflow, remove heavily infected leaves, avoid overhead irrigation, and clean tools after pruning.',
                'modern_treatment' => 'Use a locally registered tomato target spot fungicide and rotate active ingredient groups if repeated sprays are required.',
            ],
            [
                'crop' => 'Tomato',
                'disease_key' => 'tomato_spider_mites_two_spotted_spider_mite',
                'disease_keyword' => 'mite',
                'product' => 'Miticide',
                'title' => 'Tomato spider mite management',
                'summary' => 'Spider mites are not fungal disease; manage dust, water stress, and use a registered miticide only after confirming mites.',
                'natural_treatment' => 'Reduce dust, avoid plant water stress, conserve beneficial insects, and inspect leaf undersides for mites and webbing.',
                'modern_treatment' => 'Use a locally registered tomato miticide, not a fungicide, and rotate mode of action if repeated treatment is needed.',
            ],
            [
                'crop' => 'Tomato',
                'disease_key' => 'tomato_tomato_yellowleaf_curl_virus',
                'disease_keyword' => 'virus',
                'product' => 'VectorControl',
                'title' => 'Tomato yellow leaf curl virus management',
                'summary' => 'There is no curative pesticide for viral infection; priority is infected plant management and whitefly vector control.',
                'natural_treatment' => 'Remove severely infected plants where safe, control weeds that host whiteflies, and use reflective mulch or netting where available.',
                'modern_treatment' => 'Use locally registered whitefly control only when vectors are present; do not spray fungicide for the virus itself.',
            ],
            [
                'crop' => 'Tomato',
                'disease_key' => 'tomato_tomato_mosaic_virus',
                'disease_keyword' => 'virus',
                'product' => 'VectorControl',
                'title' => 'Tomato mosaic virus management',
                'summary' => 'Mosaic virus has no curative spray; sanitation and removal of infected plant material are the main controls.',
                'natural_treatment' => 'Remove severely infected plants, wash hands and tools, avoid tobacco contamination, and use resistant seed where available.',
                'modern_treatment' => 'No pesticide cures mosaic virus. Use vector or sanitation products only if locally recommended for the actual field cause.',
            ],
            [
                'crop' => 'Maize',
                'disease_key' => 'corn_common_rust',
                'disease_keyword' => 'rust',
                'product' => 'RustFungicide',
                'title' => 'Maize common rust treatment',
                'summary' => 'Rust treatment is most useful when symptoms appear early and weather favors spread.',
                'natural_treatment' => 'Remove heavily diseased residues after harvest, improve plant nutrition, and use tolerant varieties in future seasons.',
                'modern_treatment' => 'Use a locally registered rust fungicide if disease appears before grain filling and severity is increasing.',
            ],
            [
                'crop' => 'Maize',
                'disease_key' => 'corn_cercospora_leaf_spot_gray_leaf_spot',
                'disease_keyword' => 'spot',
                'product' => 'RustFungicide',
                'title' => 'Maize gray leaf spot treatment',
                'summary' => 'Gray leaf spot pressure increases with residue, humidity, and susceptible varieties; manage residue and treat early high-risk cases.',
                'natural_treatment' => 'Rotate away from maize where possible, bury or remove infected residue, improve spacing, and use tolerant varieties.',
                'modern_treatment' => 'Use a locally registered maize foliar fungicide if disease appears early and continues spreading before grain filling.',
            ],
            [
                'crop' => 'Maize',
                'disease_key' => 'corn_northern_leaf_blight',
                'disease_keyword' => 'blight',
                'product' => 'RustFungicide',
                'title' => 'Maize northern leaf blight treatment',
                'summary' => 'Northern leaf blight can reduce yield when lesions expand before grain filling; combine resistant varieties and timely fungicide where justified.',
                'natural_treatment' => 'Rotate crops, remove infected residue after harvest, avoid dense planting, and select resistant seed in future seasons.',
                'modern_treatment' => 'Use a locally registered maize blight fungicide only when disease is active early enough to justify treatment.',
            ],
            [
                'crop' => 'Pepper',
                'disease_key' => 'pepper_bell_bacterial_spot',
                'disease_keyword' => 'bacterial',
                'product' => 'Copper',
                'title' => 'Pepper bacterial spot management',
                'summary' => 'Bacterial spot spreads through splash and infected debris; reduce leaf wetness and use copper-based products only where locally recommended.',
                'natural_treatment' => 'Avoid overhead irrigation, remove infected debris, disinfect tools, and avoid working wet plants.',
                'modern_treatment' => 'Use a locally registered fixed copper product for pepper bacterial spot where recommended by an expert.',
            ],
        ];

        foreach ($entries as $entry) {
            $cropId = $cropIds[$entry['crop']] ?? null;
            if ($cropId === null) {
                continue;
            }

            TreatmentRecommendation::updateOrCreate(
                [
                    'crop_id' => $cropId,
                    'disease_key' => $entry['disease_key'],
                    'recommendation_type' => 'chemical',
                ],
                [
                    'pesticide_product_id' => $productModels[$entry['product']]->id,
                    'disease_keyword' => $entry['disease_keyword'],
                    'title' => $entry['title'],
                    'summary' => $entry['summary'],
                    'natural_treatment' => $entry['natural_treatment'],
                    'modern_treatment' => $entry['modern_treatment'],
                    'dosage_text' => 'Use only the exact dose printed on the locally registered product label for this crop and disease.',
                    'application_timing' => 'Apply when expert confirmation is complete, weather is calm, and rain is not expected soon. Repeat only according to label interval and expert advice.',
                    'pre_harvest_interval_days' => null,
                    're_entry_interval_hours' => null,
                    'max_applications' => null,
                    'ppe' => 'Chemical-resistant gloves, mask/respirator as label requires, eye protection, long sleeves, long trousers, and closed shoes.',
                    'restrictions' => 'Do not spray without confirming local registration, crop label, PHI, REI, dose, water volume, and farmer PPE availability.',
                    'monitoring_steps' => [
                        'Check the same plants again after 48 to 72 hours.',
                        'Record whether lesions are spreading to new leaves.',
                        'Escalate if neighboring plots show similar symptoms.',
                    ],
                    'prevention_steps' => [
                        'Rotate crops where practical.',
                        'Avoid overhead irrigation during disease pressure.',
                        'Remove infected debris after harvest.',
                        'Use clean tools and disease-free planting material.',
                    ],
                    'approval_status' => 'approved',
                    'approved_at' => now(),
                    'is_active' => true,
                ],
            );
        }
    }
}
