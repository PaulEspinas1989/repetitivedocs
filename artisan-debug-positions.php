<?php
// Temporary debug script — delete after diagnosis
// Run: php artisan-debug-positions.php on the server

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$templates = App\Models\Template::latest()->take(5)->get();

foreach ($templates as $t) {
    echo "=== Template #{$t->id}: {$t->name} (doc_type: {$t->document_type}) ===\n";
    $doc = $t->uploadedDocument;
    echo "  Uploaded doc: " . ($doc ? $doc->original_name . ' (' . $doc->mime_type . ')' : 'none') . "\n";
    echo "  template_docx_path: " . ($t->template_docx_path ?: 'null') . "\n";

    $vars = $t->approvedVariables()->get();
    echo "  Approved vars: " . $vars->count() . "\n";

    foreach ($vars as $v) {
        $occ  = $v->activeOccurrences()->count();
        $pos  = count($v->text_positions ?? []);
        echo "    [{$v->type}] {$v->name}: occurrences={$occ}, text_positions={$pos}\n";
        if ($occ === 0 && $pos === 0) {
            echo "      *** NO POSITIONS — will be silently skipped in PDF overlay ***\n";
        }
    }
    echo "\n";
}
