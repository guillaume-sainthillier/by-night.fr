<?php

namespace TBN\MajDataBundle\Utils;

/**
 * Description of Merger
 *
 * @author Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 */
class Util {

    public function replaceNonNumericChars($string)
    {
        return preg_replace('/[^\d.]/u', '', $string);
    }

    public function replaceNonAlphanumericChars($string)
    {
        return preg_replace('/[^A-Za-z0-9 ]/u', '', $string);
    }

    public function deleteSpaceBetween($string, $delimiters = '-')
    {
        if(is_string($delimiters) && strlen($delimiters) > 0)
        {
            return preg_replace('/\s+('.preg_quote($delimiters).'\s+/u', '$1', $string);
        }elseif(is_array($delimiters) && count($delimiters) > 0)
        {
            return preg_replace_callback('/\s+(['.implode('', (array)$delimiters).'])\s+/u', function($matches) {
                return $matches[1];
            }, $string);
        }

        return $string;
    }

    public function deleteMultipleSpaces($string)
    {
        return preg_replace('/\s+/u', ' ', $string);
    }

    public function utf8LowerCase($string)
    {
        return mb_convert_case($string, MB_CASE_TITLE, 'UTF-8');
    }
    
    public function replaceAccents($string) {
        return str_replace(array('à', 'á', 'â', 'ã', 'ä', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'À', 'Á', 'Â', 'Ã', 'Ä', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ù', 'Ú', 'Û', 'Ü', 'Ý'), array('a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'A', 'A', 'A', 'A', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'N', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y'), $string);
    }
}
