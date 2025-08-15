<?php

namespace Yale\Yes3Exporter;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$module = new Yes3Exporter();

use Yale\Yes3\Yes3;
use REDCap;
use HtmlPage;

$HtmlPage = new HtmlPage();
$HtmlPage->ProjectHeader();


//testTheSanitizer();

testTheFileType();


//phpinfo();

exit();

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
