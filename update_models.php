<?php
$models = ['GenericCompany', 'GenericProductType', 'GenericStrength', 'GenericFarmula', 'GenericCategory', 'GenericProduct', 'GenericProductParameter', 'GenericProductCategory'];
foreach ($models as $m) {
    $path = __DIR__ . '/app/Models/' . $m . '.php';
    if(file_exists($path)) {
        $content = file_get_contents($path);
        $content = str_replace('use HasFactory;', "use HasFactory;\n    protected \$guarded = [];", $content);
        file_put_contents($path, $content);
    }
}
echo "Models updated successfully.";
