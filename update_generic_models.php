<?php

$methods = [
    'GenericProduct' => "
    public function genericCompany() { return \$this->belongsTo(GenericCompany::class); }
    public function genericType() { return \$this->belongsTo(GenericProductType::class); }
    public function parameters() { return \$this->hasMany(GenericProductParameter::class, 'generic_product_id'); }
",
    'GenericProductParameter' => "
    public function parentCategory() { return \$this->belongsTo(GenericCategory::class, 'parent_generic_category_id'); }
    public function childCategory() { return \$this->belongsTo(GenericCategory::class, 'child_generic_category_id'); }
    public function genericCategory() { return \$this->belongsTo(GenericCategory::class, 'generic_category_id'); }
",
    'GenericProductCategory' => "
    public function parentCategory() { return \$this->belongsTo(GenericCategory::class, 'parent_generic_category_id'); }
    public function childCategory() { return \$this->belongsTo(GenericCategory::class, 'child_generic_category_id'); }
"
];

foreach ($methods as $m => $func) {
    $path = __DIR__ . '/app/Models/' . $m . '.php';
    if(file_exists($path)) {
        $content = file_get_contents($path);
        // insert before closing brace
        $content = preg_replace('/}(?!.*})/', $func . "\n}", $content);
        file_put_contents($path, $content);
    }
}
echo "Model relations mapped.";
