<?php

namespace Yale\Yes3Exporter2;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$module = new Yes3Exporter2();

use Yale\Yes3\Yes3;
use REDCap;
use HtmlPage;

use Normalizer;
use Collator;
use Transliterator;

$HtmlPage = new HtmlPage();
$HtmlPage->ProjectHeader();

//testTheSanitizer();

//testTheFileType();

//phpinfo();

//testTheLegacyTransfer();

//echo $module->getModuleId();

echo "<pre>";

//xliterate();

echo print_r(testSpecialValuesets(), true);

echo "</pre>";

exit();

function testSpecialValuesets(){
    global $module;

    $dag_valueset = [];
    if ($groupNames = $module->getGroupNames()) {
       foreach ($groupNames as $group_id => $group_name) {
            $dag_valueset[] = ['value' => strval($group_id), 'label' => $group_name];
        }
    }

    $event_valueset = [];
    if ($eventNames = $module->getEventNames(true)) {
        foreach ($eventNames as $event_id => $event_name) {
            $event_valueset[] = ['value' => strval($event_id), 'label' => $event_name];
        }
    }

    return [
        'dag_valueset' => $dag_valueset,
        'event_valueset' => $event_valueset
    ];

}

function xliterate(){

    // Requires: intl (for Normalizer, Collator, Transliterator, grapheme_*)
    // Optional but nice: mbstring

    $cases = [
    'simple_ascii'        => 'Hello, world!',
    'latin_accents'       => "Curaçao, Ångström, façade, über, naïve",
    'combining_mark'      => "e\u{0301} = e + COMBINING ACUTE (looks like é)",
    'precomposed'         => "é (precomposed)",
    'rtl_arabic'          => "Arabic: مَرْحَبًا بالعالم",
    'rtl_hebrew'          => "Hebrew: שָׁלוֹם עולם",
    'thai'                => "สวัสดี",
    'khmer'               => "សួស្តី",
    'cjk'                 => "中文，日本語，한국어",
    'hangul_decomposed'   => "\u{1112}\u{1161}\u{11AB}\u{1100}\u{1173}\u{11AF} (decomposed 한글)",
    'emoji_simple'        => "🙂 😬 🤖",
    'emoji_skin_tone'     => "👍🏽 👩🏾‍💻",
    'emoji_zwj_family'    => "Family: 👨‍👩‍👧‍👦",
    'emoji_flag'          => "Flag: 🇺🇳 (regional indicators)",
    'zwj_sequence'        => "Ninja cat: 🐱‍👤",
    'zero_width'          => "ZWSP here\u{200B}and\u{200B}here",
    'variation_selector'  => "HEAVY CHECK ✔︎ vs ✔ (VS-16)",
    ];

    echo "== Bytes vs Grapheme clusters ==\n";
    foreach ($cases as $k => $s) {
    $bytes = strlen($s);
    $graphemes = function_exists('grapheme_strlen') ? grapheme_strlen($s) : 'n/a';
    echo str_pad($k, 22) . " bytes=" . str_pad($bytes, 4) . " graphemes=" . $graphemes . " | $s\n";
    }

    echo "\n== Normalization (NFD → NFC) ==\n";
    $weird = "e\u{0301} + \u{212B} (Angstrom sign) + \u{00E9}";
    if (class_exists('Normalizer')) {
    echo "Original:  $weird\n";
    echo "is NFC?   " . (Normalizer::isNormalized($weird, Normalizer::FORM_C) ? 'yes' : 'no') . "\n";
    $nfc = Normalizer::normalize($weird, Normalizer::FORM_C);
    echo "To NFC:   $nfc\n";
    echo "is NFC?   " . (Normalizer::isNormalized($nfc, Normalizer::FORM_C) ? 'yes' : 'no') . "\n";
    } else {
    echo "Normalizer not available.\n";
    }

    echo "\n== Locale-aware sort (Collator) ==\n";
    $names = ["Zoe", "Zoë", "Åsa", "Álvaro", "Angstrom", "Åke"];
    if (class_exists('Collator')) {
    foreach (['en_US','sv_SE','es_ES'] as $loc) {
        $c = new Collator($loc);
        $tmp = $names;
        $c->sort($tmp);
        echo "$loc: " . implode(', ', $tmp) . "\n";
    }
    } else {
    echo "Collator not available.\n";
    }

    echo "\n== Transliteration (strip accents / Latinize) ==\n";
    if (class_exists('Transliterator')) {
    $t = Transliterator::create('Any-Latin; NFD; [:Nonspacing Mark:] Remove; NFC');
    $src = "àéîøü — Русский — Ελληνικά — 中文 — 日本語 — 한국어";
    echo "Src : $src\n";
    echo "Latinized: " . $t->transliterate($src) . "\n";
    } else {
    echo "Transliterator not available.\n";
    }

    echo "\n== Regex sanity (grapheme-aware substr) ==\n";
    $g = "👨‍👩‍👧‍👦👍🏽ée\u{0301}";
    if (function_exists('grapheme_substr')) {
    echo "First 5 graphemes: " . grapheme_substr($g, 0, 5) . "\n";
    echo "Length (graphemes): " . grapheme_strlen($g) . "\n";
    } else {
    echo "grapheme_* not available.\n";
    }

    echo "\nDone.\n";

}

function testTheLegacyTransfer(){
    global $module;

    $result = $module->transferLegacyEnvironment();

    echo $result['summary'];

    echo "<pre>";

    echo "Legacy environment transfer log:\n";
    echo $result['log'];
    echo "\n";

    echo "</pre>";

}

function getEMLogParameters( $log_id = null ) {

    $sql = "select * from redcap_external_modules_log_parameters where log_id=?";
    return Yes3Fn::fetchRecords($sql, [ $log_id ]);
}

function transferLegacyEMLogs( $legacy_external_module_id = null ) {
    global $module;

    $transfer_log = "";

    $sql = "select eml.*
from redcap_external_modules_log eml
where eml.external_module_id=? and eml.project_id=?";

    $legacy_logs = Yes3Fn::fetchRecords($sql, [ $legacy_external_module_id, $module->getProjectId() ]);
    $legacy_log_count = count($legacy_logs);
    $transfer_log .= "\nTransferring $legacy_log_count legacy logs";
    foreach ( $legacy_logs as $legacy_log ) {

        $legacy_log_id = $legacy_log['log_id'];
        // gather ye parameters
        $legacy_parameters = getEMLogParameters($legacy_log_id);
        $legacy_parameter_count = count($legacy_parameters);

        $legacy_parameters['legacy_log_id'] = $legacy_log_id; // to prevent multiple transfers

        $transfer_log .= "\nTransferring legacy log $legacy_log_id with $legacy_parameter_count parameters";
    }

    return $transfer_log;
}

function transferLegacySettings( $legacy_external_module_id = null ) {
    global $module;

    $sql = "select ems.*
from redcap_external_modules em
inner join redcap_external_module_settings ems on ems.external_module_id=em.external_module_id
where em.external_module_id=? and ems.project_id=? and ifnull(ems.value, '') <> ''";

    $legacyProjectSettings = Yes3Fn::fetchRecords($sql, [ $legacy_external_module_id, $module->getProjectId() ]);

    $configProjectSettings = $module->getConfig()['project-settings'];

    $settingsTransferred = 0;

    foreach ( $legacyProjectSettings as $setting ) {

        // see if the legacy key is in the config
        foreach ( $configProjectSettings as $configSetting ) {

            if ( $setting['key'] == $configSetting['key'] ) {

                $value = $setting['value'];

                // most legacy booleean settings were set up as 'Y'/'N' radio buttons
                if ( $configSetting['type'] == 'checkbox' ) {

                    if ( $value === 'Y' || $value === '1' ) {
                        $value = "1";
                    } else {
                        $value = "0";
                    }
                }

                $module->setProjectSetting($setting['key'], $value);
                $settingsTransferred++;

                continue 2;
            }
        }
    }

    return $settingsTransferred;
}

function testTheFileType(){
    global $module;

    $filetypeCsv = $module->getProjectSetting("export-filetype-csv");

    $module->determineExportFileType();

    $fileType = $module->EXPORT_DATA_EXTENSION;

    echo "File type setting: $filetypeCsv<br>";

    echo "Determined export file type: $fileType";
}

function testTheSanitizer() {

    $testCases = [
        ['input' => 'café', 'expected' => 'cafe'],
        ['input' => 'élève', 'expected' => 'eleve'],
        ['input' => 'über-cool', 'expected' => 'uber-cool'],
        ['input' => 'mañana', 'expected' => 'manana'],
        ['input' => 'voilà!', 'expected' => 'voila!'],
        ['input' => 'crème brûlée', 'expected' => 'creme brulee'],
        ['input' => 'résumé', 'expected' => 'resume'],
        ['input' => 'naïve', 'expected' => 'naive'],
        ['input' => 'fiancée', 'expected' => 'fiancee'],
        ['input' => 'jalapeño', 'expected' => 'jalapeno'],
        ['input' => 'coöperate', 'expected' => 'cooperate'],
        ['input' => 'São Paulo', 'expected' => 'Sao Paulo'],
        ['input' => 'touché', 'expected' => 'touche'],
        ['input' => 'piñata', 'expected' => 'pinata'],
        ['input' => 'smörgåsbord', 'expected' => 'smorgasbord'],
        ['input' => 'déjà vu', 'expected' => 'deja vu'],
        ['input' => '10 m²', 'expected' => '10 m^2'],
        ['input' => '3 kg', 'expected' => '3 kg'],
        ['input' => '– dash –', 'expected' => '- dash -'],
        ['input' => '— em dash —', 'expected' => '- em dash -'],
        ['input' => '±5%', 'expected' => '+/-5%'],
        ['input' => '≈3.14', 'expected' => '~3.14'],
        ['input' => '≤100', 'expected' => '<=100'],
        ['input' => '≥5', 'expected' => '>=5'],
        ['input' => '≠0', 'expected' => '!=0'],
        ['input' => 'Ω resistor', 'expected' => 'ohm resistor'],
        ['input' => 'µm', 'expected' => 'um'],
        ['input' => 'ΔT', 'expected' => 'DeltaT'],
        ['input' => '∑x', 'expected' => 'sumx'],
        ['input' => '½ cup', 'expected' => '1/2 cup'],
        ['input' => '¼ inch', 'expected' => '1/4 inch'],
        ['input' => '¾ full', 'expected' => '3/4 full'],
        ['input' => '⅓ chance', 'expected' => '1/3 chance'],
        ['input' => '⅔ done', 'expected' => '2/3 done'],
        ['input' => '£50', 'expected' => 'GBP50'],
        ['input' => '¥1000', 'expected' => 'YEN1000'],
        ['input' => '€5', 'expected' => 'EUR5'],
        ['input' => '₿0.01', 'expected' => 'BTC0.01'],
        ['input' => '₽500', 'expected' => 'RUB500'],
        ['input' => '₹200', 'expected' => 'INR200'],
        ['input' => '“quote”', 'expected' => "'quote'"],
        ['input' => '‘single’', 'expected' => "'single'"],
        ['input' => '«guillemet»', 'expected' => '"guillemet"'],
        ['input' => '‹angle›', 'expected' => "'angle'"],
        ['input' => 'bullet • point', 'expected' => 'bullet * point'],
        ['input' => 'middle·dot', 'expected' => 'middle*dot'],
        ['input' => '…ellipsis…', 'expected' => '...ellipsis...'],
        ['input' => 'section §1', 'expected' => 'section Section1'],
        ['input' => 'para ¶2', 'expected' => 'para Para2'],
        ['input' => 'c© 2025', 'expected' => 'c(c) 2025'],
        ['input' => 'brand™', 'expected' => 'brandTM'],
        ['input' => 'sound℗recording', 'expected' => 'sound(P)recording'],
        ['input' => 'H₂O', 'expected' => 'H_2O'],
        ['input' => 'x⁵', 'expected' => 'x^5'],
        ['input' => 'log₁₀', 'expected' => 'log_10'],
        ['input' => 'a→b', 'expected' => 'a->b'],
        ['input' => 'c←d', 'expected' => 'c<-d'],
        ['input' => 'p↔q', 'expected' => 'p<->q'],
        ['input' => 'f⇒g', 'expected' => 'f=>g'],
        ['input' => 'h⇔i', 'expected' => 'h<=>i'],
        ['input' => 'cent ¢', 'expected' => 'cent cent'],
        ['input' => '+∞', 'expected' => '+inf'],
        ['input' => 'logic ∧', 'expected' => 'logic AND'],
        ['input' => 'logic ∨', 'expected' => 'logic OR'],
        ['input' => 'forall ∀x', 'expected' => 'forall forallx'],
        ['input' => 'exists ∃y', 'expected' => 'exists existsy'],
        ['input' => 'set ∩', 'expected' => 'set INTERSECT'],
        ['input' => 'set ∪', 'expected' => 'set UNION'],
        ['input' => 'subset ⊆', 'expected' => 'subset subseteq'],
        ['input' => 'subset ⊂', 'expected' => 'subset subset'],
        ['input' => 'ident ≡', 'expected' => 'ident ===' ],
        ['input' => 'much ≪', 'expected' => 'much <<'],
        ['input' => 'much ≫', 'expected' => 'much >>'],
        ['input' => 'dot ⋅ mult', 'expected' => 'dot * mult'],
        ['input' => 'NO-BREAK SPACE', 'expected' => 'NO-BREAK SPACE'],
        ['input' => 'INVIS​IBLE', 'expected' => 'INVISIBLE'],
        ['input' => 'soft­hyphen', 'expected' => 'softhyphen'],
        ['input' => '“Ω≈µ”', 'expected' => "'ohm~u'"],
        ['input' => 'Jalapeño 🌶', 'expected' => 'Jalapeno pepper'],
        ['input' => 'Café ☕', 'expected' => 'Cafe coffee'],
        ['input' => 'résumé 📄', 'expected' => 'resume page'],
        ['input' => 'garçon', 'expected' => 'garcon'],
        ['input' => 'façade', 'expected' => 'facade'],
        ['input' => 'rôle', 'expected' => 'role'],
        ['input' => 'tête-à-tête', 'expected' => 'tete-a-tete'],
        ['input' => 'touché !', 'expected' => 'touche !'],
        ['input' => 'naïveté', 'expected' => 'naivete'],
        ['input' => 'élévation', 'expected' => 'elevation'],
        ['input' => 'über-alles', 'expected' => 'uber-alles'],
        ['input' => 'fiancée ❤️', 'expected' => 'fiancee heart'],
        ['input' => 'smörgås 🥪', 'expected' => 'smorgas sandwich'],
        ['input' => 'déjà vu 🎯', 'expected' => 'deja vu dart'],
        ['input' => 'São Tomé', 'expected' => 'Sao Tome'],
        ['input' => 'voilà mon ami', 'expected' => 'voila mon ami'],
        ['input' => 'crème caramel', 'expected' => 'creme caramel'],
        ['input' => 'résumé long', 'expected' => 'resume long'],
        ['input' => 'naïve girl', 'expected' => 'naive girl'],
        ['input' => 'façade house', 'expected' => 'facade house'],
        ['input' => 'rôle model', 'expected' => 'role model'],
        ['input' => 'tête haute', 'expected' => 'tete haute']
    ];

    echo "<pre>";

    // Run tests
    foreach ($testCases as $testCase) {
        /*
        $output = Yes3Fn::sanitizeForText($testCase['input'],
            0,
            false,
            true,
            true,
            true,
            true
        );
        */

        $output = Yes3Fn::utf8_to_ascii($testCase['input']);

        if ($output !== $testCase['expected']) {
            echo "Input: " . $testCase['input'] . ", Output: " . $output . ", Expected: " . $testCase['expected'] . "\n";
        }
    }

    echo "</pre>";
}

function miscFnTests() {    
    global $module;

    $event_id = 44; // 45 day interview

    print "<pre>";

    print "\n-----\nREDCap::isLongitudinal()\n-----\n";
    print print_r(REDCap::isLongitudinal(), true);
    print "\n-----\nmodule->isLongitudinal()\n-----\n";
    print print_r($module->isLongitudinal(), true);

    print "\n-----\nREDCap::getGroupNames()\n-----\n";
    print print_r(REDCap::getGroupNames(), true);
    print "\n-----\nmodule->getGroupNames()\n-----\n";
    print print_r($module->getGroupNames(), true);

    print "\n-----\nREDCap::getGroupNames(true)\n-----\n";
    print print_r(REDCap::getGroupNames(true), true);
    print "\n-----\nmodule->getGroupNames(true)\n-----\n";
    print print_r($module->getGroupNames(true), true);

    print "\n-----\nREDCap::getEventNames()\n-----\n";
    print print_r(REDCap::getEventNames(), true);
    print "\n-----\nmodule->getEventNames()\n-----\n";
    print print_r($module->getEventNames(), true);

    print "\n-----\nREDCap::getEventNames(true)\n-----\n";
    print print_r(REDCap::getEventNames(true), true);
    print "\n-----\nmodule->getEventNames(true)\n-----\n";
    print print_r($module->getEventNames(true), true);

    print "\n-----\nREDCap::getEventNames(true, true, $event_id)\n-----\n";
    print print_r(REDCap::getEventNames(true, true, $event_id), true);
    print "\n-----\nmodule->getEventNames(true, true, $event_id)\n-----\n";
    print print_r($module->getEventNames(true, true, $event_id), true);

    print "</pre>";
}
