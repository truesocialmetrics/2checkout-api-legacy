<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
;

return PhpCsFixer\Config::create()
    ->setRules([
        '@PSR2' => true,

        'array_syntax' => ['syntax' => 'short'],
        'trim_array_spaces' => false,
        'single_blank_line_at_eof' => true,

        // recommended
        'binary_operator_spaces' => [
            'align_double_arrow' => true,
        ],
        'concat_space' => [
            'spacing' => 'one',
        ],
    ])
    ->setFinder($finder)
;
