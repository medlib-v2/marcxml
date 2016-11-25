<?php

namespace Medlib\MarcXML;

use Exception;
use Medlib\MarcXML\Records\CatalogueRecord;
use SimpleXmlElement;
use Medlib\MarcXML\Records\AuthorityRecord;
use Medlib\MarcXML\Records\HoldingsRecord;
use Medlib\MarcXML\Records\BibliographicRecord;
use Medlib\MarcXML\Exceptions\InvalidParserException;
use Danmichaelo\QuiteSimpleXmlElement\QuiteSimpleXmlElement;

class Parser
{
	/**
	 * Parser constructor.
	 */
    public function __construct() {}

	/**
	 * @param QuiteSimpleXmlElement|SimpleXmlElement $record
	 * @throws Exception
	 * @throws InvalidParserException
	 * @return Records\Record
	 */
    public function parse($record)
    {
        if ($record instanceof QuiteSimpleXmlElement) {}
		elseif ($record instanceof SimpleXmlElement) {
            $record = new QuiteSimpleXmlElement($record);
        }
		else {
            throw new Exception('Invalid type given to Parser->parse. Expected SimpleXmlElement or QuiteSimpleXmlElement', 1);
        }

		if (preg_match("/FRBNF/i", $record->text('marc:controlfield'))) {

			return new CatalogueRecord($record);
		}

        if($record->text('marc:leader') ==  "") {

	        $collection = $record->children();

	        foreach($collection->record as $records) {

		        $record = new QuiteSimpleXmlElement($records->el());

		        $record->registerXPathNamespaces([ 'marc' => 'http://www.loc.gov/MARC21/slim' ]);

		        //$record = $data->el();

		        $leader = $record->text('marc:leader');

		        ddd($leader); die();

		        //99999 ai a22999997c 4500
		        //00358nam a22000971  4500 sudoc
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
		                throw new InvalidParserException("Unknown record type. ".PHP_EOL . $record->asXML() . PHP_EOL);
		        }
	        }
        }
		else {

	        $leader = $record->text('marc:leader');

	        //99999 ai a22999997c 4500
	        //00744nam a22002053n 450 bnf
	        //00358nam a22000971  4500 sudoc

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
	                throw new InvalidParserException("Unknown record type. ".PHP_EOL . $record->asXML() . PHP_EOL);
	        }

        }
    }
}
