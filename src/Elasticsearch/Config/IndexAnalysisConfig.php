<?php
/*
 * Copyright (c) 2026 Obione
 *
 * This file is part of BackBee Standalone.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standalone. If not, see <https://www.gnu.org/licenses/>.
 */

namespace BackBee\Elasticsearch\Config;

/**
 * Class IndexAnalysisConfig
 *
 * @author Djoudi Bensid <d.bensid@team-one.fr>
 */
class IndexAnalysisConfig implements IndexAnalysisConfigInterface
{
    /**
     * Return filters and analyzers.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'filter' => $this->filters(),
            'analyzer' => $this->analyzers(),
        ];
    }

    /**
     * Get filters.
     *
     * @return array
     */
    private function filters(): array
    {
        return [
            'autocomplete_filter' => [
                'type' => 'edge_ngram',
                'min_gram' => 1,
                'max_gram' => 20,
            ],
            'my_ascii_folding' => [
                'type' => 'asciifolding',
                'preserve_original' => true,
            ],
            'my_stemmer_french' => [
                'type' => 'stemmer',
                'language' => 'french',
            ],
            'my_stemmer_english' => [
                'type' => 'stemmer',
                'language' => 'english',
            ],
            'my_french_elision' => [
                'type' => 'elision',
                'articles_case' => true,
                'articles' => [
                    0 => 'l',
                    1 => 'm',
                    2 => 't',
                    3 => 'qu',
                    4 => 'n',
                    5 => 's',
                    6 => 'j',
                    7 => 'd',
                    8 => 'c',
                    9 => 'jusqu',
                    10 => 'quoiqu',
                    11 => 'lorsqu',
                    12 => 'puisqu',
                ],
            ],
        ];
    }

    /**
     * Get analyzers.
     *
     * @return array[]
     */
    private function analyzers(): array
    {
        return [
            'std_folded' => [
                'type' => 'custom',
                'tokenizer' => 'standard',
                'filter' => [
                    'lowercase',
                    'asciifolding',
                    'my_stemmer_english',
                    'my_french_elision',
                    'my_stemmer_french',
                ],
            ],
            'autocomplete' => [
                'type' => 'custom',
                'tokenizer' => 'standard',
                'filter' => [
                    'lowercase',
                    'my_ascii_folding',
                    'autocomplete_filter',
                ],
            ],
        ];
    }
}