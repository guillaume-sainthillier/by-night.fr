<?php

namespace AppBundle\Utils;

/**
 * Description of Merger.
 *
 * @author Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 */
class Util
{
    protected $stopWordsRegex;

    public function __construct()
    {
        $stopWords = [
            'alors', 'au', 'aucuns', 'aussi', 'autre', 'avant', 'avec', 'avoir', 'bon', 'car', 'ce', 'cela', 'ces',
            'ceux', 'chaque', 'ci', 'comme', 'comment', 'dans', 'des', 'du', 'dedans', 'dehors', 'depuis', 'devrait', 'doit',
            'donc', 'dos', 'début', 'elle', 'elles', 'en', 'encore', 'essai', 'est', 'et', 'eu', 'fait', 'faites', 'fois',
            'font', 'hors', 'ici', 'il', 'ils', 'je', 'juste', 'la', 'le', 'les', 'leur', 'là', 'ma', 'maintenant', 'mais',
            'mes', 'mine', 'moins', 'mon', 'mot', 'même', 'ni', 'nommés', 'notre', 'nous', 'ou', 'où', 'par', 'parce',
            'pas', 'peut', 'peu', 'plupart', 'pour', 'pourquoi', 'quand', 'que', 'quel', 'quelle', 'quelles', 'quels',
            'qui', 'sa', 'sans', 'ses', 'seulement', 'si', 'sien', 'son', 'sont', 'sous', 'soyez', 'sujet', 'sur', 'ta',
            'tandis', 'tellement', 'tels', 'tes', 'ton', 'tous', 'tout', 'trop', 'très', 'tu', 'voient', 'vont', 'votre',
            'vous', 'vu', 'ça', 'étaient', 'état', 'étions', 'été', 'être', 'a', 'about', 'above', 'after', 'again',
            'against', 'all', 'am', 'an', 'and', 'any', 'are', "aren't", 'as', 'at', 'be', 'because', 'been', 'before',
            'being', 'below', 'between', 'both', 'but', 'by', "can't", 'cannot', 'could', "couldn't", 'did', "didn't",
            'do', 'does', "doesn't", 'doing', "don't", 'down', 'during', 'each', 'few', 'for', 'from', 'further', 'had',
            "hadn't", 'has', "hasn't", 'have', "haven't", 'having', 'he', "he'd", "he'll", "he's", 'her', 'here',
            "here's", 'hers', 'herself', 'him', 'himself', 'his', 'how', "how's", 'i', "i'd", "i'll", "i'm", "i've",
            'if', 'in', 'into', 'is', "isn't", 'it', "it's", 'its', 'itself', "let's", 'me', 'more', 'most', "mustn't",
            'my', 'myself', 'no', 'nor', 'not', 'of', 'off', 'on', 'once', 'only', 'or', 'other', 'ought', 'our',
            'ours', 'ourselves', 'out', 'over', 'own', 'same', "shan't", 'she', "she'd", "she'll", "she's", 'should',
            "shouldn't", 'so', 'some', 'such', 'than', 'that', "that's", 'the', 'their', 'theirs', 'them', 'themselves',
            'then', 'there', "there's", 'these', 'they', "they'd", "they'll", "they're", "they've", 'this', 'those',
            'through', 'to', 'too', 'under', 'until', 'up', 'very', 'was', "wasn't", 'we', "we'd", "we'll", "we're",
            "we've", 'were', "weren't", 'what', "what's", 'when', "when's", 'where', "where's", 'which', 'while',
            'who', "who's", 'whom', 'why', "why's", 'with', "won't", 'would', "wouldn't", 'you', "you'd", "you'll",
            "you're", "you've", 'your', 'yours', 'yourself', 'yourselves',
        ];

        $parts                = array_map('preg_quote', $stopWords);
        $this->stopWordsRegex = "/\b(" . implode('|', $parts) . ")\b/imu";
    }

    public function replaceNonNumericChars($string)
    {
        return preg_replace('/[^\d.-]/u', '', $string);
    }

    public function replaceNonAlphanumericChars($string)
    {
        return preg_replace('/[^A-Za-z0-9 ]/u', '', $string);
    }

    public function deleteSpaceBetween($string, $delimiters = '-')
    {
        if (is_string($delimiters) && isset($delimiters[0])) { //Strlen > 0
            return preg_replace('/\s+(' . preg_quote($delimiters) . '\s+/u', '$1', $string);
        } elseif (is_array($delimiters) && count($delimiters) > 0) {
            return preg_replace_callback('/\s+([' . implode('', (array) $delimiters) . '])\s+/u', function ($matches) {
                return $matches[1];
            }, $string);
        }

        return $string;
    }

    public function deleteStopWords($string)
    {
        return preg_replace($this->stopWordsRegex, ' ', $string);
    }

    public function deleteMultipleSpaces($string)
    {
        while (strpos($string, '  ') !== false) {
            $string = str_replace('  ', ' ', $string);
        }

        return $string;
    }

    public function utf8TitleCase($string)
    {
        return mb_convert_case($string, MB_CASE_TITLE, 'UTF-8');
    }

    public function utf8LowerCase($string)
    {
        return mb_convert_case($string, MB_CASE_LOWER, 'UTF-8');
    }

    public function replaceAccents($string)
    {
        $unwanted_array = [
            'Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
            'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U',
            'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'B', 'ß' => 'Ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'a', 'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ý' => 'y', 'þ' => 'b', 'ÿ' => 'y',
        ];

        return strtr($string, $unwanted_array);
    }
}
