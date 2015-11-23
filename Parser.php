<?php

namespace Medlib\MarcXML;

use Exception;
use SimpleXmlElement;
use Medlib\MarcXML\Records\AuthorityRecord;
use Medlib\MarcXML\Records\HoldingsRecord;
use Medlib\MarcXML\Records\BibliographicRecord;
use Medlib\MarcXML\Exceptions\InvalidParserException;
use Danmichaelo\QuiteSimpleXmlElement\QuiteSimpleXmlElement;

class Parser
{
    public function __construct()
    {
    }

    /**
     * @param QuiteSimpleXmlElement|SimpleXmlElement $record
     */
    public function parse($record)
    {
        if ($record instanceof QuiteSimpleXmlElement) {
            // OK
        } elseif ($record instanceof SimpleXmlElement) {
            $record = new QuiteSimpleXmlElement($record);
        } else {
            throw new Exception('Invalid type given to Parser->parse. Expected SimpleXmlElement or QuiteSimpleXmlElement', 1);
        }

        $leader = $record->text('marc:leader');

        //99999 ai a22999997c 4500

        $recordType = substr($leader, 6, 1);

        switch ($recordType) {
            case 'a': // Language material
            case 'c': // Notated music
            case 'd': // Manuscript notated music
            case 'e': // Cartographic material
            case 'f': // Manuscript cartographic material
            case 'g': // Projected medium
            case 'i': // Nonmusical sound recording
            case 'j': // Musical sound recording
            case 'k': // Two-dimensional nonprojectable graphic
            case 'm': // Computer file
            case 'o': // Kit
            case 'p': // Mixed materials
            case 'r': // Three-dimensional artifact or naturally occurring object
            case 't': // Manuscript language material
                return new BibliographicRecord($record);
            case 'z':
                return new AuthorityRecord($record);
            case 'u': // Unknown
            case 'v': // Multipart item holdings
            case 'x': // Single-part item holdings
            case 'y': // Serial item holdings
                return new HoldingsRecord($record);
            default:
                throw new InvalidParserException("Unknown record type. <pre>" . $record->asXML() . "</pre>");
        }
    }
}
