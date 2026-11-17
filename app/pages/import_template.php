<?php
require_once ROOT . '/app/xlsx.php';
if (get('errors') && !empty($_SESSION['import'])) {
    $rows = [array_merge(['Row'], IMPORT_COLS, ['Issues'])];
    foreach ($_SESSION['import']['rows'] as $r) if ($r['errors'] || $r['warnings']) $rows[] = array_merge([$r['n']], $r['raw'], [implode('; ', array_merge($r['errors'], $r['warnings']))]);
    $name = 'import_errors_' . date('Ymd_His') . '.xlsx';
} else {
    $v = role() === 'vendor' ? val('SELECT name FROM vendors WHERE id=?', [user()['vendor_id']]) : 'Ashok Jewels';
    $rows = [IMPORT_COLS,
        [$v, '81966', 'KNK01268FS0033', 'Necklace', 1150, 1150, 'Bulk 100%', '925 Silver', 'White rhodium', '2.75 g', '16+2 inch', 'Lobster lock', '925 + ARCL + LGDS', date('d-m-Y', strtotime('+3 days')), 'Spec.pdf'],
        [$v, '81966', 'RNG00451', 'Ring', 500, 500, 'Bulk 100%', '925 Silver', 'White rhodium', '2.40 g', 'Size 6', '', '925', date('d-m-Y', strtotime('+3 days')), 'CAD.jpg'],
        ['Nirvana', '82001', 'ABC001', 'Pendant', 700, 700, 'AQL-1.5', '925 Silver', 'White rhodium', '1.20 g', '20 mm', '', '925', date('d-m-Y', strtotime('+4 days')), ''],
        ['Nirvana', '82002', 'ABC002', 'Chain', 10, 10, 'Sample', '925 Silver', '', '4.00 g', '20 inch', 'Lobster lock', '925', date('d-m-Y', strtotime('+4 days')), ''],
        [$v, '81968', 'KNK00511BR0007', 'Bracelet', 300, 300, 'Defined 50', '925 Silver', 'Rose gold', '6.20 g', '7 inch', 'Box clasp', '925 + ARCL', date('d-m-Y', strtotime('+5 days')), ''],
    ];
    $name = 'QTRACK_QC_REQUIREMENT_TEMPLATE.xlsx';
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $name . '"');
echo xlsx_write($rows, 'QC Requirement');
