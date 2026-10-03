<?php
declare(strict_types=1);
require __DIR__ . '/../tests/bootstrap.php';
$version = trim(file_get_contents(__DIR__.'/../VERSION'));
$out = "# PHP API reference\n\nGenerated from PHPDoc and reflection; do not edit. Version $version.\n\n";
$root = realpath(__DIR__.'/../src');
$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() === 'php') $files[] = $file->getPathname();
}
sort($files, SORT_STRING);
foreach ($files as $file) {
    $relative = substr($file, strlen($root) + 1, -4);
    $class = new ReflectionClass('Silesco\\AcmeSh\\'.str_replace(['/', '\\'], '\\', $relative));
    $out .= '## '.$class->getName()."\n\n".$class->getDocComment()."\n\n";
    foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() !== $class->getName()) continue;
        if (!$method->getDocComment() && !$class->isEnum()) throw new RuntimeException('docs.missing.'.$method->getName());
        if ($class->isEnum()) continue;
        $out .= '### '.$method->getName()."\n\n".$method->getDocComment()."\n\n```php\n";
        $out .= ($method->isStatic() ? 'static ' : '').$method->getName().'('.implode(', ',array_map(
            fn($p) => (string)$p->getType().' $'.$p->getName().($p->isDefaultValueAvailable() ? ' = '.var_export($p->getDefaultValue(),true) : ''),
            $method->getParameters())).')';
        if ($method->getReturnType()) {
            $type = (string)$method->getReturnType();
            // PHP 8.5 resolves `self` in ReflectionType; canonicalize older versions too.
            if ($type === 'self' || $type === '?self') $type = ($type[0] === '?' ? '?' : '').$class->getName();
            $out .= ': '.$type;
        }
        $out .= "\n```\n\n";
    }
}
$out = str_replace("\r\n", "\n", $out);
if (in_array('--stdout', $argv, true)) { echo $out; exit(0); }
$target = __DIR__.'/../docs/reference/api.md';
if (in_array('--check',$argv,true)) {
    if (file_get_contents($target) !== $out) throw new RuntimeException('docs.stale');
} else { if (!is_dir(dirname($target))) mkdir(dirname($target),0755,true); file_put_contents($target,$out); }
echo "API reference OK\n";
