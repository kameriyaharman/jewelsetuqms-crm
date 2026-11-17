<?php
/**
 * ISO 2859-1 single sampling plans, normal inspection (Table 1 + Table 2-A).
 * Arrows in the master table are resolved: ↓ use first plan below, ↑ use first plan above.
 */
const AQL_LETTERS = ['A','B','C','D','E','F','G','H','J','K','L','M','N','P','Q','R'];
const AQL_SAMPLE  = [2,3,5,8,13,20,32,50,80,125,200,315,500,800,1250,2000];
const AQL_VALUES  = [0.010,0.015,0.025,0.040,0.065,0.10,0.15,0.25,0.40,0.65,1.0,1.5,2.5,4.0,6.5,10];
// lot-size ranges => [S1,S2,S3,S4,I,II,III]
const AQL_CODE_TABLE = [
    [2,8,'AAAAAAB'], [9,15,'AAAAABC'], [16,25,'AABBBCD'], [26,50,'ABBCCDE'], [51,90,'BBCCCEF'],
    [91,150,'BBCDDFG'], [151,280,'BCDEEGH'], [281,500,'BCDEFHJ'], [501,1200,'CCEFGJK'],
    [1201,3200,'CDEGHKL'], [3201,10000,'CDFGJLM'], [10001,35000,'CDFHKMN'], [35001,150000,'DEGJLNP'],
    [150001,500000,'DEGJMPQ'], [500001,PHP_INT_MAX,'DEHKNQR'],
];
const AQL_AC = [0,1,2,3,5,7,10,14,21];

function aql_code_letter(int $lot, string $level = 'II'): string {
    $col = ['S1'=>0,'S2'=>1,'S3'=>2,'S4'=>3,'I'=>4,'II'=>5,'III'=>6][$level] ?? 5;
    foreach (AQL_CODE_TABLE as [$a, $b, $codes]) if ($lot >= $a && $lot <= $b) return $codes[$col];
    return 'A';
}
/** one AQL value at one code letter => [letter, sample, ac, re] */
function aql_single(string $letter, float $aql): ?array {
    $j = null;
    foreach (AQL_VALUES as $k => $v) if (abs($v - $aql) < 1e-9) $j = $k;
    if ($j === null) { // nearest standard value
        $best = 99; foreach (AQL_VALUES as $k => $v) if (abs($v - $aql) < $best) { $best = abs($v - $aql); $j = $k; }
    }
    $i = array_search($letter, AQL_LETTERS, true);
    for ($guard = 0; $guard < 20; $guard++) {
        $s = $i + $j; // diagonal position
        if ($s < 14) { $i = 14 - $j; }          // ↓ to Ac 0 plan
        elseif ($s === 15) { $i -= 1; }         // ↑ to Ac 0 plan
        elseif ($s === 16) { $i += 1; }         // ↓ to Ac 1 plan
        elseif ($s > 24) { $i = 24 - $j; }      // ↑ to Ac 21 plan
        else {
            if ($i < 0 || $i > 15) return null;
            $ac = $s === 14 ? 0 : AQL_AC[$s - 16];
            return ['letter' => AQL_LETTERS[$i], 'sample' => AQL_SAMPLE[$i], 'ac' => $ac, 're' => $ac + 1];
        }
        if ($i < 0 || $i > 15) return null;
    }
    return null;
}
/**
 * Full plan for a lot. The sample size used for the lot is the largest required by
 * the three AQL classes (critical/major/minor); if it exceeds the lot → 100% inspection.
 */
function aql_plan(int $lot, string $level, float $critical, float $major, float $minor): array {
    $letter = aql_code_letter(max(2, $lot), $level);
    $out = ['lot' => $lot, 'level' => $level, 'letter' => $letter, 'sample' => 0];
    foreach (['critical' => $critical, 'major' => $major, 'minor' => $minor] as $k => $v) {
        if ($v <= 0) { $out[$k] = ['aql' => 0, 'sample' => null, 'ac' => 0, 're' => 1]; continue; }
        $p = aql_single($letter, $v) ?? ['letter' => $letter, 'sample' => $lot, 'ac' => 0, 're' => 1];
        $out[$k] = ['aql' => $v] + $p;
        $out['sample'] = max($out['sample'], $p['sample']);
    }
    if ($out['sample'] === 0) $out['sample'] = AQL_SAMPLE[array_search($letter, AQL_LETTERS, true)];
    if ($out['sample'] >= $lot) { $out['sample'] = $lot; $out['hundred_pct'] = true; }
    if ($critical <= 0) $out['critical']['sample'] = $out['sample'];
    return $out;
}
