<?php
require_once __DIR__ . '/../php/config.php';

$tmpDir = sys_get_temp_dir();
$pass = 0; $fail = 0;
$result = function (bool $ok, string $label) use (&$pass, &$fail) {
    if ($ok) { $pass++; echo "  PASS: $label\n"; }
    else { $fail++; echo "  FAIL: $label\n"; }
};
$cleaned = [];

// 1. Valid PDF
$pdfTmp = tempnam($tmpDir, 'up_') . '.pdf';
file_put_contents($pdfTmp, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF");
$p = handleUpload(['name' => 'resume.pdf', 'tmp_name' => $pdfTmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($pdfTmp)], 'internships');
$result(is_string($p) && strpos($p, 'uploads/internships/') === 0, 'valid PDF stored under uploads/');
if (is_string($p)) $cleaned[] = $p;

// 2a. .php extension disguised
$phpTmp = tempnam($tmpDir, 'up_') . '.php';
file_put_contents($phpTmp, "<?php echo 'xss'; ?>");
$p2 = handleUpload(['name' => 'resume.php', 'tmp_name' => $phpTmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($phpTmp)], 'internships');
$result($p2 === null, '.php extension rejected');

// 2b. PDF-named file containing PHP content (MIME check)
$phpPdfTmp = tempnam($tmpDir, 'up_') . '.pdf';
file_put_contents($phpPdfTmp, "<?php echo 'xss'; ?>");
$p2b = handleUpload(['name' => 'resume.pdf', 'tmp_name' => $phpPdfTmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($phpPdfTmp)], 'internships');
$result($p2b === null, 'PHP content disguised as .pdf rejected');

// 3. Oversized
$bigTmp = tempnam($tmpDir, 'up_') . '.pdf';
file_put_contents($bigTmp, str_repeat('x', 1024));
$p3 = handleUpload(['name' => 'big.pdf', 'tmp_name' => $bigTmp, 'error' => UPLOAD_ERR_OK, 'size' => MAX_FILE_SIZE + 1], 'internships');
$result($p3 === null, 'oversized file rejected');

// Cleanup
foreach ([$pdfTmp, $phpTmp, $phpPdfTmp, $bigTmp] as $t) { if (is_file($t)) @unlink($t); }
foreach ($cleaned as $rel) { $full = __DIR__ . '/../' . $rel; if (is_file($full)) @unlink($full); }

echo $fail === 0 ? "PASS\n" : "FAIL ($fail failure(s))\n";
exit($fail === 0 ? 0 : 1);
