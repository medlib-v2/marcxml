<?php

namespace Medlib\MarcXML\Records;

use Carbon\Carbon;
use Danmichaelo\QuiteSimpleXmlElement\QuiteSimpleXmlElement;

class Record
{
    protected $data;

    public static $subfieldSeparator = '--';

    /**
     * @param $name
     */
    public function __get($name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        return;
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        if (is_null($value)) {

            unset($this->data[$name]);

        } elseif (is_string($value) && empty($value)) {

            unset($this->data[$name]);

        } else {

            $this->data[$name] = $value;
        }
    }

    /**
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * @param $name
     */
    public function __unset($name)
    {
        unset($this->data[$name]);
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     *
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->data;
    }

    /**
     * Parse a *Representation of Dates and Times* (ISO 8601).
     * The date requires 8 numeric characters in the pattern yyyymmdd.
     * The time requires 8 numeric characters in the pattern hhmmss.f,
     * expressed in terms of the 24-hour (00-23) clock.
     *
     * @param string $value
     *
     * @return Carbon|null
     */
    protected function parseDateTime($value)
    {
        if (strlen($value) == 6) {
            return Carbon::createFromFormat('ymdHis', $value . '000000');
        }

        if (strlen($value) == 8) {
            return Carbon::createFromFormat('YmdHis', $value . '000000');
        }

        if (strlen($value) == 16) {
            return Carbon::createFromFormat('YmdHis', substr($value, 0, 14));
        }
    }

    /**
     * Parse a "name node", personal or corporate, main or added, that
     * might have authority information encapsulated.
     *
     * @param string $authority
     * @param array  &$out
     */
    protected function parseAuthority($authority, &$out)
    {
        if (!empty($authority)) {

            $out['id'] = $authority;

            if (preg_match('/\((.*?)\)(.*)/', $authority, $matches)) {
                // As used by at least OCLC and Bibsys
                $out['vocabulary'] = $matches[1];
                $out['id'] = $matches[2];
            }
        }
    }

    /**
     * Parse a "name node", personal or corporate, main or added, that
     * might have relators encapsulated.
     *
     * @param QuiteSimpleXmlElement &$node
     * @param array                 &$out
     * @param string                $default
     */
    protected function parseRelator(&$node, &$out, $default = null)
    {
        $relterm = $node->text('marc:subfield[@code="e"]');
        $relcode = $node->text('marc:subfield[@code="4"]');

        if (!empty($relcode)) {
            $out['role'] = $relcode;
        } elseif (!empty($relterm)) {
            $out['role'] = $relterm;
        } elseif (!is_null($default)) {
            $out['role'] = $default;
        }
    }

    /**
     * Parse a "relationship node", one that have links to other records encapsulated.
     *
     * @param QuiteSimpleXmlElement $node
     *
     * @return array
     */
    protected function parseRelationship($node)
    {
        $rel = [];
        $x = preg_replace('/\(.*?\)/', '', $node->text('marc:subfield[@code="w"]'));
        if (!empty($x)) {
            $rel['id'] = $x;
        }

        $x = $node->text('marc:subfield[@code="t"]');
        if (!empty($x)) {
            $rel['title'] = $x;
        }

        $x = $node->text('marc:subfield[@code="g"]');
        if (!empty($x)) {
            $rel['parts'] = $x;
        }

        $x = $node->text('marc:subfield[@code="x"]');
        if (!empty($x)) {
            $rel['issn'] = $x;
        }

        $x = $node->text('marc:subfield[@code="z"]');
        if (!empty($x)) {
            $rel['isbn'] = $x;
        }

         return $rel;
    }

    /**
     * The following helper function restores a collection of malformed characters,
     * it is based on nearly 3 years experience with several Z39.50 servers
     *
     * @param $rep_string
     *
     * @return mixed
     */
    public function parseExchangeChars($rep_string)
    {
        $bad_chars = [
            chr(0x00C9)."o", chr(0x00C9)."O", chr(0x00C9)."a", chr(0x00C9)."A",
            chr(0x00C9)."u", chr(0x00C9)."U", chr(137), chr(136), chr(251),
            chr(194)."a", chr(194)."i", chr(194)."e", chr(208)."c", chr(194)."E",
            chr(207)."c", chr(207)."s", chr(207)."S", chr(201)."i", chr(200)."e",
            chr(193)."e", chr(193)."a", chr(193)."i", chr(193)."o", chr(193)."u",
            chr(195)."u", chr(201)."e", chr(195).chr(194), "&amp;#263;", "Ã¤",
            chr(169)."d", "©♭", "©ʼ", "©·", "℗e", "ℓa", "©i", "ài", "℗E", "©",
        ];
        $rep_chars = [
            "&ouml;", "&Ouml;", "&auml;", "&Auml;", "&uuml;", "&Uuml;",
            "", "", "&szlig;", "&aacute;", "&iacute;", "&eacute;",
            "&ccedil;", "&Eacute;", "&#269;", "&#353;", "&#352;",
            "&iuml;", "&euml;", "&egrave;", "&agrave;", "&igrave;",
            "oegrave;", "&ugrave;", "&ucirc;", "&euml;", "&auml;", "&#263;",
            "&auml;", "&#233;d", "&eacute;", "&icirc;", "&ecirc;",
            "&eacute;", "&agrave;", "&icirc;", "&icirc;", "&Eacute;",
            "&agrave;",
        ];
        return str_replace($bad_chars, $rep_chars, $rep_string);
    }
}
