import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { includeIgnoreFile } from '@eslint/compat';
import js from '@eslint/js';
import globals from 'globals';

const gitignorePath = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '.gitignore'
);

export default [
    includeIgnoreFile(gitignorePath),
    { ignores: ['out/**'] },
    js.configs.recommended,
    {
        files: ['endereco.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                $: 'readonly',
                jQuery: 'readonly',
                enderecoLoadAMSConfig: 'readonly',
            },
        },
        rules: {
            eqeqeq: ['error', 'always'],
            'no-shadow': 'error',
            'no-var': 'error',
            'prefer-const': 'error',
            'no-unused-expressions': 'error',
            'no-self-compare': 'error',
            'no-template-curly-in-string': 'error',
            radix: 'error',
            'no-unused-vars': [
                'error',
                { argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
            ],

            semi: ['error', 'always'],
            quotes: ['error', 'single', { avoidEscape: true }],
            indent: ['error', 4, { SwitchCase: 1 }],
            'no-trailing-spaces': 'error',
            'eol-last': ['error', 'always'],
        },
    },
];
