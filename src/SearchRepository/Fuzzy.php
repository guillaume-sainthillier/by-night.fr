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
 * The typos a full-text search forgives: an edit distance by word length ("auto": none up to 2 letters, 1 up to 5, 2
 * beyond), never on the first letter. Without that fixed prefix, each word expanded to every term of the index at
 * that distance ("concert" to "aconcept", "bonhert"…): 105 of the 143 ms of a search, matches by accident included
 * ("toulouse" in the "crtoulous" of a ticketing URL). With it, a search takes about 2.5 times less and keeps its top
 * results.
 */
final class Fuzzy
{
    public const string FUZZINESS = 'auto';

    public const int PREFIX_LENGTH = 1;
}
