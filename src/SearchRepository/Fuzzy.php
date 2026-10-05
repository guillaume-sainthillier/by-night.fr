<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SearchRepository;

/**
 * The typos a full-text search forgives: none in a word of up to 3 letters, one up to 7, two beyond ("AUTO:4,8"), and
 * never on the first letter. With Elasticsearch's "auto" (one typo from 3 letters, two from 6) a word of 6 letters
 * reached too far ("bikini" found "Bibbidi", "enfant" found "enfin"). Without the fixed prefix, each word expanded to
 * every term of the index at that distance ("concert" to "aconcept", "bonhert"…): 105 of the 143 ms of a search,
 * matches by accident included ("toulouse" in the "crtoulous" of a ticketing URL).
 */
final class Fuzzy
{
    public const string FUZZINESS = 'AUTO:4,8';

    public const int PREFIX_LENGTH = 1;
}
