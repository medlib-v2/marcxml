<?php

namespace Medlib\MarcXML\Filter;

use Carbon\Carbon;
use Medlib\MarcXML\Records\BibliographicRecord;

class FilterRecord
{
    /**
     * @param $iterator
     * @return array
     */
    static function traverseStructure($iterator)
    {
        $counter = [[]];
        $order = [',', '.', '[', ']'];
        $replace = "";
        $creatorsOrd = [
            'prf',
            'main',
            'added_corporate',
            'added',
            'lcsh',
            'topic',
            'corporation',
            '',
        ];

        foreach( $iterator as $key => $value ) {
            if($value instanceof BibliographicRecord) {
                $array = $value->toArray();
            } else {
                $array = $value;
            }

            /**
             * On parcours le Array $array
             * @var  $item
             * @var  $data
             */
            foreach($array as $item => $data) {
                if (!is_array($data) && in_array($data, $array, true)) {

                    if(!$item instanceof Carbon || !is_array($item)) {
                        /**
                         * On vérifie si l'index $item est définie et que $data n'est pas
                         * un tableau.
                         */
                        if( isset($counter[(string)$item][$data]) && !is_array($data)){

                            ++$counter[(string)$item][$data];

                        }
                        elseif(!is_array($data) || !$data instanceof Carbon ) {

                            if (!isset($counter[(string)$item][$data]) && !$data instanceof Carbon) {
                                $counter[(string)$item][$data] = 1;
                            }
                        }
                        else { self::traverseStructure($data); }

                    }
                    elseif(is_array($data) && !$data instanceof Carbon){

                        switch($item){
                            case 'notes':
                            case 'genres':
                            case 'subjects':
                            case 'creators':
                            case 'contents':
                            case 'alternativeTitles':
                            case 'series':
                                if(count($data) > 0) {
                                    for($i = 0; $i < count($data); $i++)
                                        self::traverseStructure($data[$i]);
                                }
                                else {
                                    self::traverseStructure($data);
                                }
                                break;
                        }
                    }
                }
                elseif(is_array($data)){
                    switch($item){
                        case 'notes':
                        case 'genres':
                        case 'subjects':
                        case 'creators':
                        case 'contents':
                        case 'alternativeTitles':
                        case 'series':
                            if(count($data) > 0) {

                                foreach($data as $dt) {
                                    /**
                                     * On check si $dt un tableau
                                     */
                                    if(!is_string($dt)){
                                        foreach($dt as $k => $v){
                                            $n = str_replace($order, $replace, $v);

                                            /**
                                             * On check si la variable $n est un string
                                             * et qu'elle est définie $counter[(string)$item][$n]
                                             */
                                            if(is_string($n) && isset($counter[(string)$item][$n])){
                                                switch($k){
                                                    case 'parts':
                                                    case 'term':
                                                    case 'normalizedName':
                                                        if(!in_array($n, $creatorsOrd) && !is_numeric($n)){
                                                            ++$counter[(string)$item][$n];
                                                        }
                                                        break;
                                                    default:
                                                        continue;
                                                }
                                            } elseif(is_array($n)) {
                                                /** Partie à completer */
                                                //var_dump('<br>', $k, $n);
                                            } else {

                                                switch($k){
                                                    case 'parts':
                                                    case 'term':
                                                    case 'normalizedName':
                                                        if(!in_array($n, $creatorsOrd) && !is_numeric($n)){
                                                            $counter[(string)$item][$n] = 1;
                                                        }
                                                        break;
                                                    default:
                                                        continue;
                                                }
                                            }
                                        }
                                    }
                                    /**
                                     *  Sinon $dt n'est pas un tableau
                                     */
                                    else {
                                        $n = str_replace($order, $replace, $dt);

                                        if(isset($counter[(string)$item][$n])){
                                            ++$counter[(string)$item][$n];
                                        } else {
                                            $counter[(string)$item][$n] = 1;
                                        }

                                    }
                                }
                            }
                            else {
                                self::traverseStructure($data);
                            }

                            break;
                        default:
                            continue;
                    }
                }
                else { $counter[$item][$data] = 1; }
            }
        }

        return $counter;
    }
}