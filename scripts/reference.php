<?php
declare(strict_types=1);
require __DIR__ . '/../tests/bootstrap.php';
$out = "# PHP API reference\n\nGenerated from PHPDoc and reflection; do not edit. Version 0.1.0-alpha.1.\n\n";
foreach (glob(__DIR__.'/../src/*.php') as $file) {
    $class = new ReflectionClass('Silesco\\AcmeSh\\'.basename($file,'.php'));
    $out .= '## '.$class->getName()."\n\n".$class->getDocComment()."\n\n";
    foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() !== $class->getName()) continue;
        if (!$method->getDocComment() && !$class->isEnum()) throw new RuntimeException('docs.missing.'.$method->getName());
        if ($class->isEnum()) continue;
        $out .= '### '.$method->getName()."\n\n".$method->getDocComment()."\n\n```php\n";
        $out .= ($method->isStatic() ? 'static ' : '').$method->getName().'('.implode(', ',array_map(fn($p) => (string)$p->getType().' $'.$p->getName(),$method->getParameters())).')';
        if ($method->getReturnType()) $out .= ': '.$method->getReturnType();
        $out .= "\n```\n\n";
    }
}
$target = __DIR__.'/../docs/reference/api.md';
if (in_array('--check',$argv,true)) {
    if (file_get_contents($target) !== $out) throw new RuntimeException('docs.stale');
} else { if (!is_dir(dirname($target))) mkdir(dirname($target),0755,true); file_put_contents($target,$out); }
echo "API reference OK\n";
