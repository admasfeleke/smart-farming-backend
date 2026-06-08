<?php

namespace App\Support;

use Illuminate\Http\Request;

class ApiLocalizer
{
    public static function language(Request $request): string
    {
        $raw = strtolower(trim((string) (
            $request->header('X-App-Language')
            ?: $request->header('Accept-Language')
            ?: $request->input('lang')
            ?: 'am'
        )));

        $raw = str_replace('_', '-', explode(',', $raw)[0] ?? $raw);

        return match (true) {
            str_starts_with($raw, 'om'), str_contains($raw, 'oromo') => 'om',
            str_starts_with($raw, 'ti'), str_contains($raw, 'tigrinya'), str_contains($raw, 'tigriya') => 'ti',
            str_starts_with($raw, 'en') => 'en',
            default => 'am',
        };
    }

    public static function message(Request|string $requestOrLang, string $key, array $replace = []): string
    {
        $lang = $requestOrLang instanceof Request ? self::language($requestOrLang) : $requestOrLang;
        $template = self::MESSAGES[$key][$lang]
            ?? self::MESSAGES[$key]['am']
            ?? self::MESSAGES[$key]['en']
            ?? $key;

        foreach ($replace as $name => $value) {
            $template = str_replace('{'.$name.'}', (string) $value, $template);
        }

        return $template;
    }

    public static function localizeDiseasePrevention(Request $request, array $analysis): array
    {
        $lang = self::language($request);
        $cropName = (string) ($analysis['crop_name'] ?? self::message($lang, 'crop'));
        $riskLevel = (string) ($analysis['risk_level'] ?? 'low');
        $riskDrivers = array_map(
            fn ($item) => self::localizeDriver($lang, is_array($item) ? $item : []),
            (array) ($analysis['risk_drivers'] ?? [])
        );

        $analysis['risk_level_label'] = self::message($lang, 'risk_'.$riskLevel);
        $analysis['headline'] = self::preventionHeadline($lang, $cropName, $riskLevel, $riskDrivers);
        $analysis['risk_drivers'] = $riskDrivers;
        $analysis['watch_items'] = self::localizeExactList($lang, (array) ($analysis['watch_items'] ?? []));
        $analysis['recommendations'] = self::localizeExactList($lang, (array) ($analysis['recommendations'] ?? []));

        return $analysis;
    }

    public static function localizeSoilAnalysis(Request $request, array $analysis): array
    {
        $lang = self::language($request);
        $overall = (string) ($analysis['overall_status'] ?? 'stable');
        $crop = (string) data_get($analysis, 'crop_context.active_crop', '');

        $analysis['headline'] = self::message($lang, 'soil_headline_'.$overall)
            . ($crop !== '' ? ' '.self::message($lang, 'active_crop_sentence', ['crop' => $crop]) : '');

        $analysis['issues'] = array_map(
            fn ($item) => self::localizeSoilIssue($lang, is_array($item) ? $item : []),
            (array) ($analysis['issues'] ?? [])
        );
        $analysis['actions'] = self::localizeExactList($lang, (array) ($analysis['actions'] ?? []));
        $analysis['watch_items'] = self::localizeExactList($lang, (array) ($analysis['watch_items'] ?? []));
        $analysis['trends'] = array_map(
            fn ($item) => self::localizeTrend($lang, is_array($item) ? $item : []),
            (array) ($analysis['trends'] ?? [])
        );
        $analysis['next_steps'] = self::localizeExactList($lang, (array) ($analysis['next_steps'] ?? []));

        return $analysis;
    }

    public static function localizeWeatherAnalysis(Request $request, array $analysis): array
    {
        $lang = self::language($request);
        $risk = (string) ($analysis['risk_level'] ?? 'low');
        $analysis['risk_level_label'] = self::message($lang, 'risk_'.$risk);
        $analysis['headline'] = self::message($lang, 'weather_headline_'.$risk);
        $analysis['watch_items'] = self::localizeExactList($lang, (array) ($analysis['watch_items'] ?? []));
        $analysis['actions'] = self::localizeExactList($lang, (array) ($analysis['actions'] ?? []));

        return $analysis;
    }

    public static function localizeYieldPrediction(Request $request, array $prediction): array
    {
        $lang = self::language($request);
        $cropName = (string) data_get($prediction, 'crop_name', '');
        if ($cropName === '') {
            $cropName = self::message($lang, 'crop');
        }

        $bandKey = (string) data_get($prediction, 'yield_band.key', 'near_baseline');
        $riskCount = count(array_filter((array) ($prediction['risk_flags'] ?? [])));

        if (isset($prediction['yield_band']) && is_array($prediction['yield_band'])) {
            $prediction['yield_band']['label'] = self::message($lang, 'yield_band_'.$bandKey);
        }
        if (isset($prediction['growth_context']) && is_array($prediction['growth_context'])) {
            $stageKey = (string) ($prediction['growth_context']['stage_key'] ?? 'unknown');
            $prediction['growth_context']['stage_label'] = self::message($lang, 'growth_stage_'.$stageKey);
        }

        $headlineKey = match ($bandKey) {
            'above_baseline' => 'yield_headline_above',
            'below_baseline' => $riskCount > 0 ? 'yield_headline_below_risk' : 'yield_headline_below',
            default => $riskCount > 0 ? 'yield_headline_near_risk' : 'yield_headline_near',
        };
        $prediction['headline'] = self::message($lang, $headlineKey, ['crop' => $cropName]);
        $prediction['recommendations'] = self::localizeExactList($lang, (array) ($prediction['recommendations'] ?? []));

        return $prediction;
    }

    public static function localizeTreatmentGuidance(Request $request, array $guidance): array
    {
        $lang = self::language($request);
        $registry = is_array($guidance['registry_localized_content'] ?? null)
            ? $guidance['registry_localized_content']
            : [];
        $activeIngredients = is_array($guidance['registry_product_localized_active_ingredients'] ?? null)
            ? $guidance['registry_product_localized_active_ingredients']
            : [];

        foreach ([
            'headline' => 'title',
            'next_step' => 'summary',
            'dosage' => 'dosage_text',
            'ppe' => 'ppe',
            'pre_harvest_interval' => 'pre_harvest_interval',
            're_entry_interval' => 're_entry_interval',
        ] as $responseKey => $registryKey) {
            $localized = self::localizedRegistryValue($registry, $lang, $registryKey);
            if ($localized !== null) {
                $guidance[$responseKey] = $localized;
            }
        }

        $localizedActiveIngredient = self::localizedRegistryScalar($activeIngredients, $lang);
        if ($localizedActiveIngredient !== null) {
            $guidance['active_ingredient'] = $localizedActiveIngredient;
        }

        foreach ([
            'actions' => ['natural_treatment', 'modern_treatment', 'application_timing'],
            'monitoring' => ['monitoring_steps'],
            'prevention' => ['prevention_steps'],
        ] as $responseKey => $registryKeys) {
            $localizedItems = [];
            foreach ($registryKeys as $registryKey) {
                $value = self::localizedRegistryValue($registry, $lang, $registryKey);
                if (is_array($value)) {
                    $localizedItems = [...$localizedItems, ...array_map('strval', $value)];
                } elseif (is_string($value) && trim($value) !== '') {
                    $localizedItems[] = $value;
                }
            }
            if ($localizedItems !== []) {
                $guidance[$responseKey] = array_values(array_unique($localizedItems));
            }
        }

        if (isset($guidance['treatment_options']) && is_array($guidance['treatment_options'])) {
            $guidance['treatment_options'] = array_map(
                fn ($option) => is_array($option)
                    ? self::localizeTreatmentOption($option, $lang)
                    : $option,
                $guidance['treatment_options']
            );
        }

        foreach (['headline', 'next_step', 'verification_note', 'active_ingredient', 'dosage', 'ppe', 'pre_harvest_interval', 're_entry_interval'] as $key) {
            if (isset($guidance[$key]) && is_string($guidance[$key])) {
                $guidance[$key] = self::localizeExact($lang, $guidance[$key]);
            }
        }

        foreach (['actions', 'monitoring', 'prevention', 'escalate_if', 'notes'] as $key) {
            if (isset($guidance[$key]) && is_array($guidance[$key])) {
                $guidance[$key] = self::localizeExactList($lang, $guidance[$key]);
            }
        }

        unset(
            $guidance['registry_localized_content'],
            $guidance['registry_product_localized_names'],
            $guidance['registry_product_localized_active_ingredients']
        );

        return $guidance;
    }

    private static function localizeTreatmentOption(array $option, string $lang): array
    {
        $registry = is_array($option['localized_content'] ?? null) ? $option['localized_content'] : [];
        foreach ([
            'title' => 'title',
            'summary' => 'summary',
            'natural_treatment' => 'natural_treatment',
            'modern_treatment' => 'modern_treatment',
            'dosage' => 'dosage_text',
            'application_timing' => 'application_timing',
            'ppe' => 'ppe',
        ] as $responseKey => $registryKey) {
            $localized = self::localizedRegistryValue($registry, $lang, $registryKey);
            if (is_string($localized) && trim($localized) !== '') {
                $option[$responseKey] = $localized;
            }
        }

        $productName = self::localizedRegistryScalar(
            is_array($option['localized_product_names'] ?? null) ? $option['localized_product_names'] : [],
            $lang
        );
        if ($productName !== null) {
            $option['product_name'] = $productName;
        }

        $activeIngredient = self::localizedRegistryScalar(
            is_array($option['localized_active_ingredients'] ?? null) ? $option['localized_active_ingredients'] : [],
            $lang
        );
        if ($activeIngredient !== null) {
            $option['active_ingredient'] = $activeIngredient;
        }

        unset($option['localized_content'], $option['localized_product_names'], $option['localized_active_ingredients']);

        return $option;
    }

    private static function localizedRegistryValue(array $localized, string $lang, string $key): string|array|null
    {
        $candidate = $localized[$lang][$key] ?? $localized['am'][$key] ?? $localized['en'][$key] ?? null;
        if (is_string($candidate) && trim($candidate) !== '') {
            return $candidate;
        }
        if (is_array($candidate) && $candidate !== []) {
            return $candidate;
        }

        return null;
    }

    private static function localizedRegistryScalar(array $localized, string $lang): ?string
    {
        $candidate = $localized[$lang] ?? $localized['am'] ?? $localized['en'] ?? null;
        return is_string($candidate) && trim($candidate) !== '' ? $candidate : null;
    }

    private static function localizeDriver(string $lang, array $item): array
    {
        $key = (string) ($item['key'] ?? '');
        if ($key !== '' && isset(self::DRIVER_LABELS[$key])) {
            $item['label'] = self::DRIVER_LABELS[$key][$lang]
                ?? self::DRIVER_LABELS[$key]['am']
                ?? $item['label']
                ?? $key;
        }

        return $item;
    }

    private static function localizeSoilIssue(string $lang, array $item): array
    {
        $metric = (string) ($item['metric'] ?? '');
        $severity = (string) ($item['severity'] ?? '');
        $metricLabel = self::metricLabel($lang, $metric);
        $item['metric'] = $metricLabel;

        if ($severity === 'critical' && str_contains(strtolower((string) ($item['message'] ?? '')), 'below')) {
            $item['message'] = self::message($lang, 'soil_far_below', ['metric' => $metricLabel]);
        } elseif ($severity === 'watch' && str_contains(strtolower((string) ($item['message'] ?? '')), 'below')) {
            $item['message'] = self::message($lang, 'soil_slightly_below', ['metric' => $metricLabel]);
        } elseif ($severity === 'critical' && str_contains(strtolower((string) ($item['message'] ?? '')), 'above')) {
            $item['message'] = self::message($lang, 'soil_far_above', ['metric' => $metricLabel]);
        } elseif ($severity === 'watch' && str_contains(strtolower((string) ($item['message'] ?? '')), 'above')) {
            $item['message'] = self::message($lang, 'soil_slightly_above', ['metric' => $metricLabel]);
        } elseif ($severity === 'good') {
            $item['message'] = self::message($lang, 'soil_metric_good', ['metric' => $metricLabel]);
        }

        if (! empty($item['action'])) {
            $item['action'] = self::localizeExact($lang, (string) $item['action']);
        }

        return $item;
    }

    private static function localizeTrend(string $lang, array $item): array
    {
        $metric = self::metricLabel($lang, (string) ($item['metric'] ?? ''));
        $item['metric'] = $metric;
        $direction = (string) ($item['direction'] ?? '');
        $item['message'] = match ($direction) {
            'stable' => self::message($lang, 'trend_stable', ['metric' => $metric]),
            'up', 'down', 'improving', 'worsening', 'changing' => self::message($lang, 'trend_changed', ['metric' => $metric]),
            default => (string) ($item['message'] ?? ''),
        };

        return $item;
    }

    private static function preventionHeadline(string $lang, string $cropName, string $riskLevel, array $drivers): string
    {
        if ($drivers === []) {
            return self::message($lang, 'prevention_no_strong_pressure', ['crop' => $cropName]);
        }

        return self::message($lang, 'prevention_risk_headline', [
            'risk' => self::message($lang, 'risk_'.$riskLevel),
            'crop' => $cropName,
            'driver' => (string) ($drivers[0]['label'] ?? ''),
        ]);
    }

    private static function localizeExactList(string $lang, array $items): array
    {
        return array_values(array_map(
            fn ($item) => self::localizeExact($lang, (string) $item),
            $items
        ));
    }

    private static function localizeExact(string $lang, string $text): string
    {
        return self::EXACT[$text][$lang] ?? self::EXACT[$text]['am'] ?? $text;
    }

    private static function metricLabel(string $lang, string $metric): string
    {
        $key = strtolower(trim($metric));
        return self::METRICS[$key][$lang] ?? self::METRICS[$key]['am'] ?? $metric;
    }

    private const MESSAGES = [
        'crop' => ['am' => 'ሰብል', 'om' => 'Midhaan', 'ti' => 'ሰብል', 'en' => 'crop'],
        'invalid_credentials' => ['am' => 'የስልክ ቁጥር ወይም የይለፍ ቃል ትክክል አይደለም።', 'om' => 'Lakkoofsi bilbilaa yookaan jechi iccitii sirrii miti.', 'ti' => 'ቁጽሪ ስልኪ ወይ ምስጢር ቃል ትኽክል ኣይኮነን።', 'en' => 'Invalid credentials.'],
        'farmer_only' => ['am' => 'የሞባይል መተግበሪያ መግቢያ ለገበሬ መለያዎች ብቻ ነው።', 'om' => 'Seensi appii moobaayilaa akkaawuntii qonnaan bulaa qofaa dha.', 'ti' => 'መእተዊ መተግበሪ ሞባይል ንመለያታት ገበሬ ጥራይ እዩ።', 'en' => 'Mobile app access is restricted to farmer accounts.'],
        'valid_phone_required' => ['am' => 'ትክክለኛ የስልክ ቁጥር ያስፈልጋል።', 'om' => 'Lakkoofsi bilbilaa sirrii barbaachisa.', 'ti' => 'ትኽክለኛ ቁጽሪ ስልኪ የድሊ።', 'en' => 'A valid phone number is required.'],
        'phone_registered' => ['am' => 'ይህ የስልክ ቁጥር ቀድሞ ተመዝግቧል።', 'om' => 'Lakkoofsi bilbilaa kun duraan galmaa’eera.', 'ti' => 'እዚ ቁጽሪ ስልኪ ቀዲሙ ተመዝጊቡ ኣሎ።', 'en' => 'This phone number is already registered.'],
        'email_registered' => ['am' => 'ይህ ኢሜይል ቀድሞ ተመዝግቧል።', 'om' => 'Imeeliin kun duraan galmaa’eera.', 'ti' => 'እዚ ኢመይል ቀዲሙ ተመዝጊቡ ኣሎ።', 'en' => 'This email address is already registered.'],
        'invalid_refresh_token' => ['am' => 'የማደሻ ቶከን ትክክል አይደለም።', 'om' => 'Tookeniin haaromsaa sirrii miti.', 'ti' => 'ቶከን ምሕዳስ ትኽክል ኣይኮነን።', 'en' => 'Invalid refresh token.'],
        'refresh_token_expired' => ['am' => 'የማደሻ ቶከን ጊዜው አልፏል።', 'om' => 'Tookeniin haaromsaa yeroon isaa darbeera.', 'ti' => 'ቶከን ምሕዳስ ግዜኡ ኣብቂዑ።', 'en' => 'Refresh token expired.'],
        'user_inactive' => ['am' => 'የተጠቃሚው መለያ ንቁ አይደለም።', 'om' => 'Akkaawuntiin fayyadamaa hojii irra hin jiru.', 'ti' => 'መለያ ተጠቃሚ ንጡፍ ኣይኮነን።', 'en' => 'User account is inactive.'],
        'logged_out' => ['am' => 'ወጥተዋል።', 'om' => 'Baateetta.', 'ti' => 'ወጺእካ።', 'en' => 'Logged out.'],
        'disease_prevention_completed' => ['am' => 'የበሽታ መከላከያ ትንተና ተጠናቋል።', 'om' => 'Xiinxalli ittisa dhukkubaa xumurameera.', 'ti' => 'ትንተና መከላኸሊ በሽታ ተዛዚሙ።', 'en' => 'Disease prevention analysis completed'],
        'soil_notice_provisional' => ['am' => 'ጊዜያዊ መመሪያ ነው። ኬሚካል ግብዓት ከመጠቀምዎ በፊት ከድጋፍ ሰጪ ጋር ያረጋግጡ።', 'om' => 'Qajeelfamni yeroo ti. Galtee keemikaalaa dura deeggartoota waliin mirkaneessi.', 'ti' => 'ግዝያዊ መምርሒ እዩ። ቅድሚ ኬሚካላዊ እታዎት ምጥቃም ምስ ደጋፊ ኣረጋግጽ።', 'en' => 'Provisional guidance. Verify with supporter before applying chemical inputs.'],
        'soil_deleted' => ['am' => 'የአፈር ጤና መረጃ ተሰርዟል።', 'om' => 'Daataan fayyaa biyyee haqameera.', 'ti' => 'ሓበሬታ ጤና መሬት ተሰሪዙ።', 'en' => 'Soil health data deleted successfully'],
        'weather_deleted' => ['am' => 'የአየር ሁኔታ መረጃ ተሰርዟል።', 'om' => 'Daataan qilleensaa haqameera.', 'ti' => 'ሓበሬታ ኣየር ተሰሪዙ።', 'en' => 'Weather data deleted successfully'],
        'risk_high' => ['am' => 'ከፍተኛ', 'om' => 'Ol’aanaa', 'ti' => 'ልዑል', 'en' => 'High'],
        'risk_moderate' => ['am' => 'መካከለኛ', 'om' => 'Giddu-galeessa', 'ti' => 'ማእከላይ', 'en' => 'Moderate'],
        'risk_low' => ['am' => 'ዝቅተኛ', 'om' => 'Gadi aanaa', 'ti' => 'ትሑት', 'en' => 'Low'],
        'prevention_no_strong_pressure' => ['am' => 'አሁን ያሉት ሁኔታዎች ለ{crop} ጠንካራ የበሽታ ግፊት አያሳዩም።', 'om' => 'Haalli ammaa {crop} irratti dhiibbaa dhukkubaa cimaa hin agarsiisu.', 'ti' => 'ኩነታት ሕጂ ን{crop} ጠንካራ ጸቕጢ በሽታ ኣየርእይን።', 'en' => 'Current conditions do not show strong disease pressure for {crop}.'],
        'prevention_risk_headline' => ['am' => '{risk} የበሽታ አደጋ ለ{crop}። {driver}', 'om' => 'Balaan dhukkubaa {risk} {crop} irratti. {driver}', 'ti' => '{risk} ሓደጋ በሽታ ን{crop}። {driver}', 'en' => '{risk} disease risk for {crop}. {driver}'],
        'soil_headline_stable' => ['am' => 'የአፈር ሁኔታ በአጠቃላይ የተረጋጋ ነው።', 'om' => 'Haalli biyyee waliigalaan tasgabbaa’aa dha.', 'ti' => 'ኩነታት መሬት ብሓፈሻ ርጉእ እዩ።', 'en' => 'Soil conditions are broadly stable.'],
        'soil_headline_urgent' => ['am' => 'ከቀጣዩ የማሳ ስራ በፊት የአፈር ሁኔታ አስቸኳይ ማስተካከያ ይፈልጋል።', 'om' => 'Hojii dirree itti aanu dura haalli biyyee sirreeffama ariifachiisaa barbaada.', 'ti' => 'ቅድሚ ቀጻሊ ስራሕ ማሳ ኩነታት መሬት ቅልጡፍ ምእራም የድሊ።', 'en' => 'Soil conditions need urgent correction before the next field operation.'],
        'soil_headline_attention' => ['am' => 'ለተሻለ የሰብል አፈጻጸም የአፈር ሁኔታ የተመረጠ ማስተካከያ ይፈልጋል።', 'om' => 'Bu’aa midhaanii fooyya’aa argachuuf haalli biyyee sirreeffama xiyyeeffannoo qabu barbaada.', 'ti' => 'ንዝሓሸ ፍርያት ሰብል ኩነታት መሬት ዝተወሰነ ምእራም የድሊ።', 'en' => 'Soil conditions need targeted adjustment for better crop performance.'],
        'active_crop_sentence' => ['am' => 'ንቁ የሰብል አውድ፦ {crop}።', 'om' => 'Haalli midhaan hojiirra jiru: {crop}.', 'ti' => 'ንጡፍ ኩነታት ሰብል፦ {crop}።', 'en' => 'Active crop context: {crop}.'],
        'soil_far_below' => ['am' => '{metric} ከታለመው ክልል በጣም በታች ነው።', 'om' => '{metric} daangaa kaayyoo irraa baay’ee gadi jira.', 'ti' => '{metric} ካብ ዝተዓለመ ወሰን ኣዝዩ ትሕቲ እዩ።', 'en' => '{metric} is far below the target range.'],
        'soil_slightly_below' => ['am' => '{metric} ከታለመው ክልል ትንሽ በታች ነው።', 'om' => '{metric} daangaa kaayyoo irraa xiqqoo gadi jira.', 'ti' => '{metric} ካብ ዝተዓለመ ወሰን ቁሩብ ትሕቲ እዩ።', 'en' => '{metric} is slightly below the target range.'],
        'soil_far_above' => ['am' => '{metric} ከታለመው ክልል በጣም በላይ ነው።', 'om' => '{metric} daangaa kaayyoo irraa baay’ee ol jira.', 'ti' => '{metric} ካብ ዝተዓለመ ወሰን ኣዝዩ ልዕሊ እዩ።', 'en' => '{metric} is far above the target range.'],
        'soil_slightly_above' => ['am' => '{metric} ከታለመው ክልል ትንሽ በላይ ነው።', 'om' => '{metric} daangaa kaayyoo irraa xiqqoo ol jira.', 'ti' => '{metric} ካብ ዝተዓለመ ወሰን ቁሩብ ልዕሊ እዩ።', 'en' => '{metric} is slightly above the target range.'],
        'soil_metric_good' => ['am' => '{metric} በስራ የሚያገለግል ክልል ውስጥ ነው።', 'om' => '{metric} daangaa hojii keessatti jira.', 'ti' => '{metric} ኣብ ዝሰርሕ ወሰን ውሽጢ እዩ።', 'en' => '{metric} is within the working range.'],
        'trend_stable' => ['am' => '{metric} ከቀደመው ሙከራ ጋር ሲነጻጸር የተረጋጋ ነው።', 'om' => '{metric} qorannoo darbe waliin wal bira qabamee tasgabbaa’aa dha.', 'ti' => '{metric} ምስ ዝሓለፈ ፈተነ ክነጻጸር ከሎ ርጉእ እዩ።', 'en' => '{metric} is stable compared with the previous test.'],
        'trend_changed' => ['am' => '{metric} ከቀደመው ሙከራ ጀምሮ ተቀይሯል።', 'om' => '{metric} qorannoo darbe irraa eegalee jijjiirameera.', 'ti' => '{metric} ካብ ዝሓለፈ ፈተነ ጀሚሩ ተቐይሩ።', 'en' => '{metric} changed since the previous test.'],
        'weather_headline_low' => ['am' => 'የአየር ሁኔታው ለመደበኛ የማሳ ስራ በአሁኑ ጊዜ የተረጋጋ ነው።', 'om' => 'Haalli qilleensaa yeroo ammaa hojii dirree idileef tasgabbaa’aa dha.', 'ti' => 'ኩነታት ኣየር ሕጂ ንስሩዕ ስራሕ ማሳ ርጉእ እዩ።', 'en' => 'Weather conditions are currently stable for routine field work.'],
        'weather_headline_moderate' => ['am' => 'የአየር ሁኔታ መካከለኛ ትኩረት ይፈልጋል።', 'om' => 'Haalli qilleensaa xiyyeeffannoo giddu-galeessaa barbaada.', 'ti' => 'ኩነታት ኣየር ማእከላይ ትኹረት የድሊ።', 'en' => 'Weather conditions need moderate attention.'],
        'weather_headline_high' => ['am' => 'እርጥብ የአየር ሁኔታ የበሽታና የማሳ መዳረሻ አደጋን እየጨመረ ነው።', 'om' => 'Haalli qilleensa jiidhaan balaa dhukkubaa fi seensa dirree dabalaa jira.', 'ti' => 'እርጥብ ኩነታት ኣየር ሓደጋ በሽታን መእተዊ ማሳን ይውስኽ ኣሎ።', 'en' => 'Wet weather conditions are increasing disease and field-access risk.'],
        'yield_band_unknown' => ['am' => 'መነሻ መረጃ የለም', 'om' => 'Bu’uura hin jiru', 'ti' => 'መነሻ የለን', 'en' => 'Baseline not available'],
        'yield_band_above_baseline' => ['am' => 'ከመነሻው በላይ', 'om' => 'Bu’uura ol', 'ti' => 'ካብ መነሻ ልዕሊ', 'en' => 'Above baseline'],
        'yield_band_below_baseline' => ['am' => 'ከመነሻው በታች', 'om' => 'Bu’uura gadi', 'ti' => 'ካብ መነሻ ትሕቲ', 'en' => 'Below baseline'],
        'yield_band_near_baseline' => ['am' => 'መነሻውን ቅርብ', 'om' => 'Bu’uura biratti', 'ti' => 'ናብ መነሻ ቀረባ', 'en' => 'Near baseline'],
        'growth_stage_unknown' => ['am' => 'የእድገት ደረጃ አልተገኘም', 'om' => 'Sadarkaan guddinaa hin jiru', 'ti' => 'ደረጃ ዕብየት የለን', 'en' => 'Growth stage not available'],
        'growth_stage_early_establishment' => ['am' => 'የመጀመሪያ መቋቋም', 'om' => 'Hundeeffama jalqabaa', 'ti' => 'ቀዳማይ ምትካል', 'en' => 'Early establishment'],
        'growth_stage_vegetative_growth' => ['am' => 'የቅጠል እድገት', 'om' => 'Guddina baalaa', 'ti' => 'ዕብየት ቅጠል', 'en' => 'Vegetative growth'],
        'growth_stage_reproductive' => ['am' => 'አበባ/ፍሬ ወይም እህል መፈጠር', 'om' => 'Daraaraa fi uumama firii yookaan midhaanii', 'ti' => 'ምፍጣር ዕምባባ/ፍረ ወይ እኽሊ', 'en' => 'Flowering / fruit or grain formation'],
        'growth_stage_maturation' => ['am' => 'መብሰል', 'om' => 'Bilchina', 'ti' => 'ምብሳል', 'en' => 'Maturation'],
        'growth_stage_past_expected_harvest' => ['am' => 'ከተጠበቀው የምርት ጊዜ አልፏል', 'om' => 'Yeroo haamaa eegame darbeera', 'ti' => 'ካብ ዝተጠበቐ ግዜ ምርት ሓሊፉ', 'en' => 'Past expected harvest window'],
        'yield_headline_above' => ['am' => '{crop} አሁን ያሉት ሁኔታዎች ከቀጠሉ ከመነሻ የምርት አቅሙ በላይ እየተከታተለ ነው።', 'om' => '{crop} yoo haalli ammaa itti fufe bu’aa bu’uuraa isaa ol deema.', 'ti' => '{crop} ኩነታት ሕጂ እንተቐጺሉ ካብ መነሻ ዓቕሚ ፍርያት ልዕሊ ይኸይድ ኣሎ።', 'en' => '{crop} is tracking above its baseline yield potential if current conditions hold.'],
        'yield_headline_below_risk' => ['am' => '{crop} የማሳ አደጋዎች የምርት አቅሙን ስለሚጫኑ ከመነሻ በታች እየተከታተለ ነው።', 'om' => '{crop} balaa dirree irraa kan ka’e bu’uura gadi deema.', 'ti' => '{crop} ሓደጋታት ማሳ ዓቕሚ ፍርያት ስለዝጸቕጡ ካብ መነሻ ትሕቲ ይኸይድ ኣሎ።', 'en' => '{crop} is currently tracking below baseline because field risks are suppressing yield potential.'],
        'yield_headline_below' => ['am' => '{crop} ከመነሻ በታች እየተከታተለ ነው፣ ምርትን ለማስመለስ የማሳ ማስተካከያ ይፈልጋል።', 'om' => '{crop} bu’uura gadi deema; bu’aa deebisuuf sirreeffama dirree barbaada.', 'ti' => '{crop} ካብ መነሻ ትሕቲ ይኸይድ ኣሎ፣ ፍርያት ንምምላስ ምእራም ማሳ የድሊ።', 'en' => '{crop} is currently tracking below baseline and needs field adjustments to recover yield.'],
        'yield_headline_near_risk' => ['am' => '{crop} መነሻውን ቅርብ ነው፣ ግን ንቁ የማሳ አደጋዎች የመጨረሻ ምርቱን ሊቀንሱ ይችላሉ።', 'om' => '{crop} bu’uura biratti jira, garuu balaa dirree jiru bu’aa dhumaa hir’isuu danda’a.', 'ti' => '{crop} ናብ መነሻ ቀረባ እዩ፣ ግን ንጡፍ ሓደጋታት ማሳ ፍርያት መወዳእታ ክንክዩ ይኽእሉ።', 'en' => '{crop} is near baseline, but active field risks could still reduce the final harvest.'],
        'yield_headline_near' => ['am' => '{crop} በአሁኑ የማሳ ሁኔታ ከተጠበቀው ምርት ቅርብ እየተከታተለ ነው።', 'om' => '{crop} haala dirree ammaa keessatti bu’aa eegamu biratti deema.', 'ti' => '{crop} ኣብ ኩነታት ማሳ ሕጂ ናብ ዝተጠበቐ ፍርያት ቀረባ ይኸይድ ኣሎ።', 'en' => '{crop} is tracking close to its expected yield under the current field conditions.'],
    ];

    private const METRICS = [
        'soil ph' => ['am' => 'የአፈር pH', 'om' => 'pH biyyee', 'ti' => 'pH መሬት', 'en' => 'Soil pH'],
        'nitrogen' => ['am' => 'ናይትሮጅን', 'om' => 'Naayitroojinii', 'ti' => 'ናይትሮጅን', 'en' => 'Nitrogen'],
        'phosphorus' => ['am' => 'ፎስፎረስ', 'om' => 'Foosfoorasii', 'ti' => 'ፎስፎረስ', 'en' => 'Phosphorus'],
        'potassium' => ['am' => 'ፖታስየም', 'om' => 'Potaasiyemii', 'ti' => 'ፖታስየም', 'en' => 'Potassium'],
        'organic matter' => ['am' => 'ኦርጋኒክ ንጥረ ነገር', 'om' => 'Qabeenya orgaanikii', 'ti' => 'ኦርጋኒክ ንጥረ ነገር', 'en' => 'Organic matter'],
        'soil moisture' => ['am' => 'የአፈር እርጥበት', 'om' => 'Jiidhina biyyee', 'ti' => 'ርስሓት መሬት', 'en' => 'Soil moisture'],
    ];

    private const DRIVER_LABELS = [
        'fungal_pressure' => ['am' => 'ከፍተኛ እርጥበት የፈንገስ መስፋፋትን እያገዘ ነው።', 'om' => 'Jiidhinni ol’aanaan babal’ina fangasii ni deeggarra.', 'ti' => 'ልዑል እርጥበት ምዝርጋሕ ፈንገስ ይድግፍ ኣሎ።', 'en' => 'High humidity is favoring fungal spread.'],
        'surface_wetness' => ['am' => 'የቅጠል እርጥበት ሁኔታ እየተጨመረ ነው።', 'om' => 'Haalli jiidhina baalaa dabalaa jira.', 'ti' => 'ኩነታት ርስሓት ቅጠል ይውስኽ ኣሎ።', 'en' => 'Leaf wetness conditions are building up.'],
        'root_zone_pressure' => ['am' => 'እርጥብ የሥር አካባቢ የአፈር ወለድ በሽታ ግፊትን እየጨመረ ነው።', 'om' => 'Naannoon hundee jiidhaan dhiibbaa dhukkuba biyyee keessaa dabalaa jira.', 'ti' => 'እርጥብ ከባቢ ሱር ጸቕጢ በሽታ መሬት ይውስኽ ኣሎ።', 'en' => 'Wet root-zone conditions are increasing soil-borne disease pressure.'],
        'bacterial_pressure' => ['am' => 'ሞቃትና እርጥብ ሁኔታ የባክቴሪያ በሽታን ሊያፋጥን ይችላል።', 'om' => 'Ho’i fi jiidhinni dhukkuba baakteeriyaa saffisiisuu danda’a.', 'ti' => 'ሙቐትን እርጥበትን በሽታ ባክቴርያ ከቀላጥፍ ይኽእል።', 'en' => 'Warm humid conditions can accelerate bacterial infection.'],
        'stress_pressure' => ['am' => 'ሙቀትና ድርቀት የሰብል ጽናትን እያዳከመ ነው።', 'om' => 'Ho’i fi goginsi jabina midhaanii laaffisaa jiru.', 'ti' => 'ሙቐትን ደረቕነትን ጽንዓት ሰብል የዳኽም ኣሎ።', 'en' => 'Heat and dryness are weakening crop resilience.'],
        'nutrition_pressure' => ['am' => 'የአፈር ምግብ እጥረት ሰብሉን ለበሽታ ያጋልጣል።', 'om' => 'Hanqinni nyaata biyyee midhaan dhukkubaaf saaxila.', 'ti' => 'ጉድለት ምግቢ መሬት ሰብል ንበሽታ የቓልዕ።', 'en' => 'Soil nutrition is leaving the crop less resilient to infection.'],
    ];

    private const EXACT = [
        'Watch leaf undersides and lower canopy for fresh spotting or mildew growth.' => ['am' => 'አዲስ ነጠብጣብ ወይም ሻጋታ እድገት ለማየት የቅጠል ታችኛውን ክፍልና ዝቅተኛ ቅጠላማ ክፍል ይመልከቱ።', 'om' => 'Madoobbii haaraa yookaan biqila fangasii ilaaluuf jala baalaa fi gubbaa gadi aanaa ilaali.', 'ti' => 'ሓድሽ ነጥቢ ወይ ሻጋታ ንምርኣይ ታሕቲ ቅጠልን ታሕተዋይ ሽፋንን ተመልከት።', 'en' => 'Watch leaf undersides and lower canopy for fresh spotting or mildew growth.'],
        'Open the canopy where possible and avoid wetting foliage late in the day.' => ['am' => 'በተቻለ መጠን ቅጠላማውን ክፍል ይክፈቱና ቀኑ ሲያመሽ ቅጠሎችን ከማርጠብ ይቆጠቡ።', 'om' => 'Bakka danda’ametti gubbaa banaa taasisi; galgala baala jiisuu irraa of qusadhu.', 'ti' => 'ኣብ ዝከኣለሉ ሽፋን ክፈት፣ ኣብ መወዳእታ መዓልቲ ቅጠል ምርጣብ ኣወግድ።', 'en' => 'Open the canopy where possible and avoid wetting foliage late in the day.'],
        'Inspect low spots and poorly drained areas first.' => ['am' => 'መጀመሪያ ዝቅተኛ ቦታዎችን ውሃ የማይወጣባቸውን አካባቢዎች ይመርምሩ።', 'om' => 'Jalqaba bakka gadi aanaa fi bakka bishaan itti hin yaa’in qoradhu.', 'ti' => 'ቅድሚ ኩሉ ትሑት ቦታታትን ዘይጽቡቕ ፍሳስ ዘለዎም ከባቢታትን መርምር።', 'en' => 'Inspect low spots and poorly drained areas first.'],
        'Improve drainage flow and reduce unnecessary irrigation until the root zone dries back.' => ['am' => 'የውሃ ፍሳሽን ያሻሽሉና የሥር አካባቢው እስኪደርቅ ድረስ አላስፈላጊ መስኖን ይቀንሱ።', 'om' => 'Yaa’insa bishaanii fooyyessi; hanga naannoon hundee gogutti bishaan hin barbaachifne hir’isi.', 'ti' => 'ፍሳስ ማይ ኣሻሽል፣ ከባቢ ሱር ክሳብ ዝነቅጽ ዘየድሊ መስኖ ኣጉድል።', 'en' => 'Improve drainage flow and reduce unnecessary irrigation until the root zone dries back.'],
        'Limit field work when foliage is wet to reduce spread between plants.' => ['am' => 'ቅጠሎች እርጥብ ሲሆኑ በማሳ ስራ ላይ ገደብ ያድርጉ፣ በተክሎች መካከል መስፋፋትን ለመቀነስ።', 'om' => 'Baalli jiidhaa yeroo ta’u hojii dirree daangeessi; tamsa’ina gidduu biqiltootaa hir’isuuf.', 'ti' => 'ቅጠል ርሑስ ከሎ ስራሕ ማሳ ገድብ፣ ምዝርጋሕ ኣብ መንጎ ተኽልታት ንምንካይ።', 'en' => 'Limit field work when foliage is wet to reduce spread between plants.'],
        'Maintain field scouting, sanitation, and stable irrigation because current disease pressure is limited.' => ['am' => 'አሁን የበሽታ ግፊት ዝቅተኛ ስለሆነ የማሳ ክትትል፣ ንጽህናና የተረጋጋ መስኖ ይቀጥሉ።', 'om' => 'Dhiibbaan dhukkubaa amma daangeffamaa waan ta’eef sakatta’insa dirree, qulqullina, fi bishaan tasgabbaa’aa itti fufi.', 'ti' => 'ጸቕጢ በሽታ ሕጂ ውሱን ስለዝኾነ ክትትል ማሳ፣ ጽሬትን ርጉእ መስኖን ቀጽል።', 'en' => 'Maintain field scouting, sanitation, and stable irrigation because current disease pressure is limited.'],
        'Continue checking new growth and shaded canopy zones during routine field visits.' => ['am' => 'በመደበኛ የማሳ ጉብኝት ጊዜ አዲስ እድገትንና ጥላማ ቅጠላማ አካባቢዎችን መመርመር ይቀጥሉ።', 'om' => 'Daawwannaa dirree idilee keessatti guddina haaraa fi bakka gaaddisa gubbaa ilaalu itti fufi.', 'ti' => 'ኣብ ስሩዕ ምብጻሕ ማሳ ሓድሽ ዕብየትን ጽላል ዘለዎ ሽፋንን ምርመራ ቀጽል።', 'en' => 'Continue checking new growth and shaded canopy zones during routine field visits.'],
        'Retest this plot after the next management change to confirm the soil response.' => ['am' => 'የአፈር ምላሽን ለማረጋገጥ ከቀጣዩ የአስተዳደር ለውጥ በኋላ ይህን ማሳ እንደገና ይፈትኑ።', 'om' => 'Deebii biyyee mirkaneessuuf jijjiirama bulchiinsa itti aanu booda lafa kana irra deebi’i qoradhu.', 'ti' => 'ምላሽ መሬት ንምርግጋጽ ድሕሪ ቀጻሊ ለውጢ ኣመራርሓ እዚ ማሳ ደጊምካ ፈትን።', 'en' => 'Retest this plot after the next management change to confirm the soil response.'],
        'Ask a supporter or expert to validate this soil record before relying on it for chemical input decisions.' => ['am' => 'በኬሚካል ግብዓት ውሳኔ ላይ ከመታመንዎ በፊት ይህን የአፈር መዝገብ ድጋፍ ሰጪ ወይም ባለሙያ እንዲያረጋግጥ ይጠይቁ።', 'om' => 'Murtii galtee keemikaalaa dura galmee biyyee kana deeggartaa yookaan ogeessaan mirkaneessisi.', 'ti' => 'ቅድሚ ኣብ ውሳነ ኬሚካላዊ እታዎት ምምርኳስ እዚ መዝገብ መሬት ብደጋፊ ወይ ባለሞያ ኣረጋግጽ።', 'en' => 'Ask a supporter or expert to validate this soil record before relying on it for chemical input decisions.'],
        'Monitor low-lying plots for standing water and prolonged leaf wetness.' => ['am' => 'ዝቅተኛ ማሳዎችን ለቆመ ውሃና ረዥም የቅጠል እርጥበት ይከታተሉ።', 'om' => 'Lafa gadi aanaa bishaan dhaabbatuu fi jiidhina baalaa dheeratuuf hordofi.', 'ti' => 'ትሑት ማሳታት ንዝቆመ ማይን ነዊሕ ርስሓት ቅጠልን ተከታተል።', 'en' => 'Monitor low-lying plots for standing water and prolonged leaf wetness.'],
        'Prioritize drainage checks and avoid unnecessary overhead irrigation.' => ['am' => 'የውሃ ፍሳሽ ምርመራን ቅድሚያ ይስጡና አላስፈላጊ ከላይ መስኖን ይቀንሱ።', 'om' => 'Sakatta’iinsa yaa’insa bishaanii dursi; bishaan gubbaa hin barbaachifne irraa of qusadhu.', 'ti' => 'ምርመራ ፍሳስ ማይ ቀዳምነት ሃብ፣ ዘየድሊ ላዕለዋይ መስኖ ኣወግድ።', 'en' => 'Prioritize drainage checks and avoid unnecessary overhead irrigation.'],
        'Look for wilting and fast soil moisture decline during the hottest hours.' => ['am' => 'በበጣም ሞቃት ሰዓታት መድከምን ፈጣን የአፈር እርጥበት መቀነስን ይመልከቱ።', 'om' => 'Sa’aatii ho’a cimaa keessatti coollaguu fi jiidhina biyyee saffisaan hir’achuu ilaali.', 'ti' => 'ኣብ ዝሞቐ ሰዓታት ምድካምን ቅልጡፍ ምንካይ ርስሓት መሬትን ተመልከት።', 'en' => 'Look for wilting and fast soil moisture decline during the hottest hours.'],
        'Shift irrigation earlier in the day and protect exposed seedlings if possible.' => ['am' => 'መስኖን ወደ ቀኑ መጀመሪያ ያዛውሩና በተቻለ መጠን የተጋለጡ ችግኞችን ይጠብቁ።', 'om' => 'Bishaan obaasuu ganamaatti dabarsi; yoo danda’ame biqiltuu ifatti saaxilame eegi.', 'ti' => 'መስኖ ናብ መጀመርታ መዓልቲ ኣዛውር፣ እንተከኣለ ዝተቓልዑ ችግኝታት ሓልው።', 'en' => 'Shift irrigation earlier in the day and protect exposed seedlings if possible.'],
        'Continue regular field checks and note any sudden weather changes.' => ['am' => 'መደበኛ የማሳ ምርመራ ይቀጥሉና ድንገተኛ የአየር ለውጦችን ይመዝግቡ።', 'om' => 'Sakatta’iinsa dirree idilee itti fufi; jijjiirama qilleensaa tasaa galmeessi.', 'ti' => 'ስሩዕ ምርመራ ማሳ ቀጽል፣ ድንገተኛ ለውጢ ኣየር መዝግብ።', 'en' => 'Continue regular field checks and note any sudden weather changes.'],
        'Use the current weather window for routine scouting, weeding, and planning.' => ['am' => 'አሁኑን የአየር ሁኔታ ለመደበኛ ክትትል፣ አረም ማጥፋትና እቅድ ይጠቀሙ።', 'om' => 'Yeroo qilleensaa ammaa sakatta’iinsa, aramaa fi karoora idileef fayyadami.', 'ti' => 'መስኮት ኣየር ሕጂ ንስሩዕ ክትትል፣ ምምንጫት ሳዕሪን ውጥንን ተጠቐም።', 'en' => 'Use the current weather window for routine scouting, weeding, and planning.'],
        'Prioritize irrigation scheduling and mulching because the crop is under moisture stress.' => ['am' => 'ሰብሉ በእርጥበት ጭንቀት ስለሆነ የመስኖ መርሃ ግብርና መሸፈኛ ቅድሚያ ይስጡ።', 'om' => 'Midhaan hanqina jiidhinaa keessa waan jiruuf sagantaa bishaanii fi mulch dursi.', 'ti' => 'ሰብል ኣብ ጸቕጢ ርስሓት ስለዘሎ መደብ መስኖን ሙልችን ቀዳምነት ሃብ።', 'en' => 'Prioritize irrigation scheduling and mulching because the crop is under moisture stress.'],
        'Reduce midday heat stress with shade support where practical and maintain steady watering.' => ['am' => 'በተቻለ መጠን በጥላ የቀትር ሙቀት ጭንቀትን ይቀንሱና የተረጋጋ መስኖ ይጠብቁ።', 'om' => 'Bakka danda’ametti gaaddisa fayyadamuun ho’a walakkaa guyyaa hir’isi; bishaan tasgabbaa’aa eegi.', 'ti' => 'ኣብ ዝከኣለሉ ብጽላል ጸቕጢ ሙቐት ቀትሪ ኣጉድል፣ ርጉእ መስኖ ሓልው።', 'en' => 'Reduce midday heat stress with shade support where practical and maintain steady watering.'],
        'Review the latest soil test and correct the weakest nutrient before the crop reaches final grain or fruit fill.' => ['am' => 'የቅርብ የአፈር ሙከራን ይመልከቱና ሰብሉ የመጨረሻ እህል/ፍሬ መሙላት ከመድረሱ በፊት ደካማውን ንጥረ ምግብ ያስተካክሉ።', 'om' => 'Qorannoo biyyee isa dhiyoo ilaali; midhaan guutinsa firii dhumaa dura nyaata laafaa sirreessi.', 'ti' => 'ናይ ቀረባ ፈተነ መሬት ተመልከት፣ ቅድሚ መወዳእታ ምምላእ ፍረ/እኽሊ ዝደኸመ ንጥረ ምግቢ ኣስተኻኽል።', 'en' => 'Review the latest soil test and correct the weakest nutrient before the crop reaches final grain or fruit fill.'],
        'Protect establishment with consistent moisture and early weed control to preserve yield potential.' => ['am' => 'የምርት አቅምን ለመጠበቅ መጀመሪያ መቋቋምን በቋሚ እርጥበትና ቀደም ያለ አረም ቁጥጥር ይጠብቁ።', 'om' => 'Dandeettii bu’aa eeguuf hundeeffama jiidhina tasgabbaa’aa fi to’annoo aramaa duraan eegi.', 'ti' => 'ዓቕሚ ፍርያት ንምሕላው ምትካል ብቐጻሊ ርስሓትን ቀዳማይ ቁጽጽር ሳዕርን ሓልው።', 'en' => 'Protect establishment with consistent moisture and early weed control to preserve yield potential.'],
        'Keep moisture and nutrient supply steady during flowering and fruit or grain set because this stage drives final yield.' => ['am' => 'ይህ ደረጃ የመጨረሻ ምርትን ስለሚወስን በአበባና ፍሬ/እህል መያዝ ጊዜ እርጥበትና ንጥረ ምግብ አቅርቦትን የተረጋጋ ያድርጉ።', 'om' => 'Sadarkaan kun bu’aa dhumaa waan murteessuuf yeroo daraaraa fi hidhaa firii/ midhaanii jiidhina fi nyaata tasgabbaa’aa eegi.', 'ti' => 'እዚ ደረጃ ፍርያት መወዳእታ ስለዝውስን ኣብ ዕምባባን ምትሓዝ ፍረ/እኽልን ርስሓትን ንጥረ ምግብን ርጉእ ግበር።', 'en' => 'Keep moisture and nutrient supply steady during flowering and fruit or grain set because this stage drives final yield.'],
        'Diagnosis was rejected by reviewer.' => ['am' => 'ምርመራው በገምጋሚ ውድቅ ተደርጓል።', 'om' => 'Bu’aan qorannoo gamaaggamaan kufsiifameera.', 'ti' => 'ምርመራ ብገምጋሚ ተነጺጉ።', 'en' => 'Diagnosis was rejected by reviewer.'],
        'Diagnosis is not yet confirmed by supporter/expert.' => ['am' => 'ምርመራው ገና በድጋፍ ሰጪ/ባለሙያ አልተረጋገጠም።', 'om' => 'Bu’aan qorannoo deeggartaa/ogeessaan amma hin mirkanoofne.', 'ti' => 'ምርመራ ገና ብደጋፊ/ባለሞያ ኣይተረጋገጸን።', 'en' => 'Diagnosis is not yet confirmed by supporter/expert.'],
        'Prediction confidence is below reliable treatment threshold.' => ['am' => 'የትንበያ እርግጠኝነት ከሕክምና የሚታመን ገደብ በታች ነው።', 'om' => 'Amanamummaan tilmaamaa daangaa wal’aansa amanamaa gadi jira.', 'ti' => 'እምነት ትንበያ ካብ ደረት ሕክምና ዝእመን ትሕቲ እዩ።', 'en' => 'Prediction confidence is below reliable treatment threshold.'],
        'Predicted disease family does not match selected crop.' => ['am' => 'የተተነበየው የበሽታ ቤተሰብ ከተመረጠው ሰብል ጋር አይዛመድም።', 'om' => 'Maatiin dhukkubaa tilmaamame midhaan filatame waliin hin simatu.', 'ti' => 'ቤተሰብ በሽታ ዝተተነበየ ምስ ዝተመረጸ ሰብል ኣይሰማማዕን።', 'en' => 'Predicted disease family does not match selected crop.'],
        'No actionable disease treatment required for healthy result.' => ['am' => 'ጤናማ ውጤት ስለሆነ የበሽታ ሕክምና አያስፈልግም።', 'om' => 'Bu’aan fayyaa waan ta’eef wal’aansi dhukkubaa hin barbaachisu.', 'ti' => 'ውጽኢት ጥዑይ ስለዝኾነ ሕክምና በሽታ ኣየድልን።', 'en' => 'No actionable disease treatment required for healthy result.'],
        'Treatment guidance is based on verified diagnosis and crop context.' => ['am' => 'የሕክምና መመሪያው በተረጋገጠ ምርመራና በሰብል አውድ ላይ የተመሠረተ ነው።', 'om' => 'Qajeelfamni wal’aansaa bu’aa mirkanaa’ee fi haala midhaanii irratti hundaa’a.', 'ti' => 'መምርሒ ሕክምና ኣብ ዝተረጋገጸ ምርመራን ኩነታት ሰብልን ይመርኮስ።', 'en' => 'Treatment guidance is based on verified diagnosis and crop context.'],
        'Symptoms continue spreading after the recommended observation period.' => ['am' => 'ከተመከረው የክትትል ጊዜ በኋላ ምልክቶች መስፋፋት ቀጥለዋል።', 'om' => 'Mallattooleen yeroo hordoffii gorfame booda babal’achuu itti fufan.', 'ti' => 'ምልክታት ድሕሪ ዝተመከረ ግዜ ክትትል ምዝርጋሕ ቀጺሎም።', 'en' => 'Symptoms continue spreading after the recommended observation period.'],
        'More than one plot shows similar symptoms.' => ['am' => 'ከአንድ በላይ ማሳ ተመሳሳይ ምልክቶችን ያሳያል።', 'om' => 'Lafa tokko caalaa mallattoolee wal fakkaatan agarsiisa.', 'ti' => 'ካብ ሓደ ንላዕሊ ማሳ ተመሳሳሊ ምልክታት የርኢ።', 'en' => 'More than one plot shows similar symptoms.'],
        'Farmer cannot confirm safe product label, PPE, PHI, or REI.' => ['am' => 'ገበሬው የምርት መለያ፣ PPE፣ PHI ወይም REI ደህንነትን ማረጋገጥ አልቻለም።', 'om' => 'Qonnaan bulaan asxaa oomishaa, PPE, PHI yookaan REI nageenya mirkaneessuu hin dandeenye.', 'ti' => 'ገበሬ ውሑስ መለያ ምርት፣ PPE፣ PHI ወይ REI ከረጋግጽ ኣይከኣለን።', 'en' => 'Farmer cannot confirm safe product label, PPE, PHI, or REI.'],
    ];
}
