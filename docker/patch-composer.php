<?php

// Patches the shop's composer.json at image build time so that:
//   1. The module's PSR-4 namespace is resolvable after composer dump-autoload.
//   2. The module's runtime dependencies (e.g. guzzlehttp/guzzle) are installed
//      by the subsequent `composer install` step.
//
// php/ext-* constraints are platform requirements that composer checks against
// the runtime environment; they must not be copied into the shop's require block.
$shopJson   = json_decode(file_get_contents('composer.json'), true);
$moduleJson = json_decode(
    file_get_contents('source/modules/endereco/endereco-oxid7-twig-client/composer.json'),
    true
);

$shopJson['autoload']['psr-4']['Endereco\\Oxid7Client\\'] =
    'source/modules/endereco/endereco-oxid7-twig-client/src/';

foreach ($moduleJson['require'] ?? [] as $package => $constraint) {
    if ($package === 'php' || str_starts_with($package, 'ext-')) {
        continue;
    }
    $shopJson['require'][$package] = $constraint;
}

file_put_contents(
    'composer.json',
    json_encode($shopJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

// Write the list of merged packages to a temp file so the Dockerfile can pass
// them to `composer update` without needing inline PHP in the RUN instruction.
$packages = array_keys(array_filter(
    $moduleJson['require'] ?? [],
    fn($p) => $p !== 'php' && !str_starts_with($p, 'ext-'),
    ARRAY_FILTER_USE_KEY
));
file_put_contents('/tmp/module-packages.txt', implode(' ', $packages));
