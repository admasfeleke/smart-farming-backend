<?php

return [
    'pending_review' => [
        'review_status' => 'advisory',
        'expert_verified' => false,
        'verification_note' => 'Draft guidance only. Expert agronomy review is required before pesticide use.',
        'headline' => 'Diagnosis pending verification',
        'next_step' => 'Do not apply treatment yet. Wait for supporter confirmation or rescan clearer leaf images.',
        'actions' => [
            'Keep affected plants separated from healthy plants if possible.',
            'Avoid overhead irrigation until diagnosis is confirmed.',
            'Capture new close-up images from multiple angles if symptoms change.',
        ],
        'monitoring' => [
            'Check spread to nearby plants every 24 hours.',
            'Track any rapid yellowing, wilting, or new lesions.',
        ],
        'prevention' => [
            'Disinfect tools before moving between plots.',
            'Avoid handling wet leaves during scouting.',
        ],
        'escalate_if' => [
            'Symptoms spread rapidly within 48 hours.',
            'Multiple plants collapse or severe wilting appears.',
        ],
    ],
    'rejected' => [
        'review_status' => 'advisory',
        'expert_verified' => false,
        'verification_note' => 'Draft guidance only. Expert agronomy review is required before pesticide use.',
        'headline' => 'Previous diagnosis rejected',
        'next_step' => 'Do not use previous treatment advice. Rescan and wait for a new verified diagnosis.',
        'actions' => [
            'Collect clearer photos in good daylight.',
            'Remove heavily damaged leaves only if agronomically safe.',
        ],
        'monitoring' => [
            'Observe disease spread daily until a new diagnosis is confirmed.',
        ],
        'prevention' => [
            'Limit movement between infected and healthy plots.',
        ],
        'escalate_if' => [
            'Severe crop decline occurs before new verification.',
        ],
    ],
    'healthy' => [
        'review_status' => 'advisory',
        'expert_verified' => false,
        'verification_note' => 'No pesticide treatment is recommended for a healthy result.',
        'headline' => 'No disease symptoms confirmed',
        'next_step' => 'No treatment needed now. Continue preventive crop care.',
        'actions' => [
            'Maintain balanced irrigation and nutrient schedule.',
            'Continue weekly scouting for early symptoms.',
        ],
        'monitoring' => [
            'Rescan immediately if spots, mold, rust, or wilting appears.',
        ],
        'prevention' => [
            'Keep canopy ventilation and field hygiene practices.',
        ],
        'escalate_if' => [
            'New symptoms emerge after rain or humidity spikes.',
        ],
    ],
    'default' => [
        'review_status' => 'advisory',
        'expert_verified' => false,
        'verification_note' => 'Advisory treatment options only. Confirm the locally registered product label for dosage, PHI, and REI before spraying.',
        'headline' => 'Disease management guidance',
        'next_step' => 'Use the advisory treatment options below and confirm the registered product label before spraying.',
        'active_ingredient' => 'Select a locally registered product for this crop and disease.',
        'dosage' => 'Use the exact dose on the locally registered product label.',
        'ppe' => 'Gloves, mask/respirator (as per label), eye protection, long sleeves',
        'pre_harvest_interval' => 'Follow the locally registered product label PHI for this crop.',
        're_entry_interval' => 'Follow the locally registered product label REI for this product.',
        'actions' => [
            'Remove heavily infected plant material where agronomically safe.',
            'Use approved crop-specific treatment under label guidance.',
            'Improve field sanitation and avoid water splash between plants.',
        ],
        'monitoring' => [
            'Recheck symptom progression after 48 to 72 hours.',
            'Rescan if symptoms worsen or spread.',
        ],
        'prevention' => [
            'Rotate crops and avoid repeated susceptible varieties.',
            'Use clean tools and certified planting material.',
        ],
        'escalate_if' => [
            'No improvement after initial treatment cycle.',
            'Rapid spread continues despite intervention.',
        ],
    ],
    'families' => [
        'tomato' => [
            'headline' => 'Tomato disease guidance',
            'next_step' => 'Start tomato-specific disease control and keep canopy dry and ventilated.',
        ],
        'potato' => [
            'headline' => 'Potato disease guidance',
            'next_step' => 'Start potato disease control and remove severely infected foliage safely.',
        ],
        'corn' => [
            'headline' => 'Corn disease guidance',
            'next_step' => 'Start corn disease control and monitor rapid spread on upper leaves.',
        ],
        'pepper' => [
            'headline' => 'Pepper disease guidance',
            'next_step' => 'Start pepper disease control and avoid handling wet foliage.',
        ],
        'grape' => [
            'headline' => 'Grape disease guidance',
            'next_step' => 'Start grape disease control and improve canopy aeration.',
        ],
    ],
    'keywords' => [
        'virus' => [
            'headline' => 'Likely viral disease',
            'next_step' => 'No curative spray for viral diseases. Remove highly infected plants and control vectors early.',
            'active_ingredient' => 'No curative pesticide for the virus itself. If vector control is needed, use a locally registered product for the target vector.',
            'dosage' => 'Use the exact dose on the locally registered product label for whiteflies, aphids, or thrips where relevant.',
            'ppe' => 'Gloves, mask/respirator (as per label), eye protection, long sleeves',
            'pre_harvest_interval' => 'Follow the locally registered product label PHI if a vector-control spray is used.',
            're_entry_interval' => 'Follow the locally registered product label REI if a vector-control spray is used.',
            'actions' => [
                'Rogue severely infected plants when agronomically safe.',
                'Control vectors (whiteflies/aphids/thrips) using approved integrated methods.',
                'Avoid moving workers/tools from infected to clean areas without disinfection.',
            ],
        ],
        'bacterial' => [
            'headline' => 'Likely bacterial disease',
            'next_step' => 'Reduce moisture spread and apply approved bactericidal management where recommended.',
            'active_ingredient' => 'Fixed copper bactericide; copper plus mancozeb where locally registered.',
            'dosage' => 'Use the exact dose on the locally registered product label for the selected bactericide.',
            'ppe' => 'Gloves, mask/respirator (as per label), eye protection, long sleeves',
            'pre_harvest_interval' => 'Follow the locally registered product label PHI for this crop.',
            're_entry_interval' => 'Follow the locally registered product label REI for this product.',
            'actions' => [
                'Avoid overhead irrigation and reduce leaf wetness period.',
                'Use approved copper-based or local recommended bactericidal measures.',
                'Remove heavily infected leaves to reduce inoculum.',
            ],
        ],
        'blight' => [
            'headline' => 'Blight management',
            'next_step' => 'Begin protective fungicidal program and remove severe lesions promptly.',
            'active_ingredient' => 'Protectant fungicide such as mancozeb or chlorothalonil, with locally registered systemic blight fungicides rotated under high pressure.',
            'dosage' => 'Use the exact dose on the locally registered fungicide label for this crop and blight disease.',
            'ppe' => 'Gloves, mask/respirator (as per label), eye protection, long sleeves',
            'pre_harvest_interval' => 'Follow the locally registered fungicide label PHI for this crop.',
            're_entry_interval' => 'Follow the locally registered fungicide label REI.',
        ],
        'mildew' => [
            'headline' => 'Mildew management',
            'next_step' => 'Improve airflow and start mildew-specific fungicidal control.',
            'active_ingredient' => 'Use a locally registered mildew fungicide for this crop, rotated by fungicide group.',
            'dosage' => 'Use the exact dose on the locally registered fungicide label for this crop.',
            'ppe' => 'Gloves, mask/respirator (as per label), eye protection, long sleeves',
            'pre_harvest_interval' => 'Follow the locally registered fungicide label PHI for this crop.',
            're_entry_interval' => 'Follow the locally registered fungicide label REI.',
        ],
        'rust' => [
            'headline' => 'Rust management',
            'next_step' => 'Control rust early with approved fungicides and reduce leaf wetness.',
            'active_ingredient' => 'Foliar fungicide such as propiconazole, tebuconazole, azoxystrobin, or a locally registered premix for rust.',
            'dosage' => 'Use the exact dose on the locally registered fungicide label for rust control.',
            'ppe' => 'Gloves, mask/respirator (as per label), eye protection, long sleeves',
            'pre_harvest_interval' => 'Follow the locally registered fungicide label PHI for this crop.',
            're_entry_interval' => 'Follow the locally registered fungicide label REI.',
        ],
        'scab' => [
            'headline' => 'Scab management',
            'next_step' => 'Apply preventive disease control and maintain orchard/field sanitation.',
            'active_ingredient' => 'Use a locally registered scab fungicide program for this crop.',
            'dosage' => 'Use the exact dose on the locally registered fungicide label for scab control.',
            'ppe' => 'Gloves, mask/respirator (as per label), eye protection, long sleeves',
            'pre_harvest_interval' => 'Follow the locally registered fungicide label PHI for this crop.',
            're_entry_interval' => 'Follow the locally registered fungicide label REI.',
        ],
        'spot' => [
            'headline' => 'Leaf spot management',
            'next_step' => 'Start leaf spot control and remove highly infected foliage.',
            'active_ingredient' => 'Protectant fungicide such as mancozeb or chlorothalonil; use a locally registered product for this crop and leaf spot disease.',
            'dosage' => 'Use the exact dose on the locally registered fungicide label for this crop.',
            'ppe' => 'Gloves, mask/respirator (as per label), eye protection, long sleeves',
            'pre_harvest_interval' => 'Follow the locally registered fungicide label PHI for this crop.',
            're_entry_interval' => 'Follow the locally registered fungicide label REI.',
        ],
    ],
];

