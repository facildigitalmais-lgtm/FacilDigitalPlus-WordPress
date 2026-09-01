<?php

declare(strict_types=1);

$seed = get_option('fd_m4_temp_questions', []);
if (is_array($seed)) {
    $repo = new FacilDigital\Core\Questions\QuestionRepository();
    foreach (array_map('intval', $seed) as $id) {
        if ($id > 0) {
            try {
                $repo->delete($id);
            } catch (Throwable) {
            }
        }
    }
}
delete_option('fd_m4_temp_questions');

if (is_file('/workspace/tools/.fd-m4-cli.csv')) {
    @unlink('/workspace/tools/.fd-m4-cli.csv');
}
if (is_file('/workspace/tools/.fd-m4-export.csv')) {
    @unlink('/workspace/tools/.fd-m4-export.csv');
}

if (is_file('/workspace/tools/cleanup-m3.php')) {
    require '/workspace/tools/cleanup-m3.php';
}

echo "M4 cleanup concluido.\n";
